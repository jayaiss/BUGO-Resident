<?php
declare(strict_types=1);

function q_build_notifications_union_sql(): string {
    return <<<SQL
      SELECT tracking_number, status, issued_time, is_read, source, rejection_reason,
             appointment_date, appointment_time, appointment_end_time,
             selected_date, selected_time, certificate, total_payment
      FROM (
        SELECT tracking_number, cedula_status AS status,
               COALESCE(update_time, issued_on) AS issued_time,
               is_read, 'cedula' AS source, rejection_reason,
               appointment_date, appointment_time,
               NULL AS appointment_end_time, NULL AS selected_date, NULL AS selected_time,
               NULL AS certificate, total_payment
        FROM cedula
        WHERE res_id = ? AND notif_sent = 1 AND cedula_status != 'Pending' AND cedula_delete_status = 0

        UNION ALL
        SELECT tracking_number, status,
               COALESCE(update_time, created_at) AS issued_time,
               is_read, 'schedule' AS source, rejection_reason,
               NULL, NULL, NULL, selected_date, selected_time, certificate, total_payment
        FROM schedules
        WHERE res_id = ? AND notif_sent = 1 AND appointment_delete_status = 0

        UNION ALL
        SELECT tracking_number, status,
               COALESCE(update_time, created_at) AS issued_time,
               is_read, 'urgent' AS source, rejection_reason,
               NULL, NULL, NULL, selected_date, selected_time, certificate, NULL
        FROM urgent_request
        WHERE res_id = ? AND notif_sent = 1 AND urgent_delete_status = 0

        UNION ALL
        SELECT tracking_number, cedula_status AS status,
               COALESCE(update_time, issued_on) AS issued_time,
               is_read, 'urgent_cedula' AS source, rejection_reason,
               appointment_date, appointment_time, NULL, NULL, NULL, NULL, total_payment
        FROM urgent_cedula_request
        WHERE res_id = ? AND notif_sent = 1 AND cedula_status != 'Pending' AND cedula_delete_status = 0

        UNION ALL
        SELECT CAST(e.id AS CHAR), 
               CASE WHEN e.event_date >= CURDATE() THEN 'Upcoming' ELSE 'New' END,
               e.created_at, COALESCE(r.is_read, 0),
               'event', NULL,
               e.event_date, e.event_time, e.event_end_time,
               NULL, NULL, COALESCE(en.event_name, CAST(e.event_title AS CHAR)), NULL
        FROM events e
        LEFT JOIN event_reads r ON r.event_id = e.id AND r.resident_id = ?
        LEFT JOIN event_name en ON en.id = e.event_title
        WHERE e.events_delete_status = 0 AND COALESCE(r.dismissed,0) = 0
      ) AS all_notifications
    SQL;
}

/** Latest notifications list (returns mysqli_result for compatibility) */
function q_get_notifications(mysqli $db, int $residentId, int $limit = 5): mysqli_result {
    $sql = q_build_notifications_union_sql() . " ORDER BY issued_time DESC LIMIT " . (int)$limit;
    $st  = $db->prepare($sql);
    $st->bind_param("iiiii", $residentId, $residentId, $residentId, $residentId, $residentId);
    $st->execute();
    return $st->get_result(); // caller can fetch & free; stmt closes when result freed
}

/** Count unread (cedula-like + events 60d window) */
function q_get_unread_counts(mysqli $db, int $residentId): array {
    // cedula-like unread
    $sqlA = "
      SELECT COUNT(*) AS unread_count FROM (
        SELECT 1 FROM cedula WHERE res_id = ? AND is_read = 0 AND cedula_status != 'Pending' AND cedula_delete_status = 0 AND notif_sent = 1
        UNION ALL
        SELECT 1 FROM schedules WHERE res_id = ? AND is_read = 0 AND appointment_delete_status = 0 AND notif_sent = 1
        UNION ALL
        SELECT 1 FROM urgent_request WHERE res_id = ? AND is_read = 0 AND urgent_delete_status = 0 AND notif_sent = 1
        UNION ALL
        SELECT 1 FROM urgent_cedula_request WHERE res_id = ? AND is_read = 0 AND cedula_status != 'Pending' AND cedula_delete_status = 0 AND notif_sent = 1
      ) t";
    $stA = $db->prepare($sqlA);
    $stA->bind_param("iiii", $residentId, $residentId, $residentId, $residentId);
    $stA->execute();
    $cedulaUnread = (int)($stA->get_result()->fetch_assoc()['unread_count'] ?? 0);
    $stA->close();

    // events unread (60d window)
    $sqlB = "
      SELECT COUNT(*) AS c
      FROM events e
      LEFT JOIN event_reads r ON r.event_id = e.id AND r.resident_id = ?
      WHERE e.events_delete_status = 0
        AND e.created_at >= DATE_SUB(NOW(), INTERVAL 60 DAY)
        AND COALESCE(r.dismissed,0) = 0
        AND COALESCE(r.is_read,0) = 0";
    $stB = $db->prepare($sqlB);
    $stB->bind_param("i", $residentId);
    $stB->execute();
    $eventUnread = (int)($stB->get_result()->fetch_assoc()['c'] ?? 0);
    $stB->close();

    return ['cedula_unread' => $cedulaUnread, 'event_unread' => $eventUnread];
}

/** Mark everything as read for resident */
function q_mark_all_read(mysqli $db, int $residentId): void {
    $db->query("UPDATE cedula SET is_read = 1 WHERE res_id = " . (int)$residentId . " AND is_read = 0");
    $db->query("UPDATE schedules SET is_read = 1 WHERE res_id = " . (int)$residentId . " AND is_read = 0");
    $db->query("UPDATE urgent_request SET is_read = 1 WHERE res_id = " . (int)$residentId . " AND is_read = 0");
    $db->query("UPDATE urgent_cedula_request SET is_read = 1 WHERE res_id = " . (int)$residentId . " AND is_read = 0");

    $sql = "
      INSERT INTO event_reads (resident_id, event_id, is_read)
      SELECT ?, e.id, 1
      FROM events e
      LEFT JOIN event_reads r ON r.event_id = e.id AND r.resident_id = ?
      WHERE e.events_delete_status = 0
        AND COALESCE(r.dismissed,0) = 0
        AND COALESCE(r.is_read,0) = 0
      ON DUPLICATE KEY UPDATE is_read = 1";
    $st = $db->prepare($sql);
    $st->bind_param("ii", $residentId, $residentId);
    $st->execute();
    $st->close();
}

/** Dismiss or clear notif by source */
function q_dismiss_notification(mysqli $db, int $residentId, string $source, string $trackingNumber): void {
    if ($source === 'event') {
        $eventId = (int)$trackingNumber;
        $sql = "INSERT INTO event_reads (resident_id, event_id, is_read, dismissed)
                VALUES (?, ?, 1, 1)
                ON DUPLICATE KEY UPDATE dismissed = 1, is_read = 1";
        $st = $db->prepare($sql);
        $st->bind_param("ii", $residentId, $eventId);
        $st->execute(); $st->close();
        return;
    }
    $map = [
        'cedula'        => 'UPDATE cedula SET notif_sent = 0 WHERE res_id = ? AND tracking_number = ?',
        'schedule'      => 'UPDATE schedules SET notif_sent = 0 WHERE res_id = ? AND tracking_number = ?',
        'urgent'        => 'UPDATE urgent_request SET notif_sent = 0 WHERE res_id = ? AND tracking_number = ?',
        'urgent_cedula' => 'UPDATE urgent_cedula_request SET notif_sent = 0 WHERE res_id = ? AND tracking_number = ?',
    ];
    if (!isset($map[$source])) return;
    $st = $db->prepare($map[$source]);
    $st->bind_param("is", $residentId, $trackingNumber);
    $st->execute(); $st->close();
}
?>