<?php
declare(strict_types=1);

/**
 * Query helpers for the public landing / login page.
 * Each function expects an open mysqli connection passed in.
 */

function q_get_recent_events(mysqli $db, int $limit = 3): mysqli_result {
    $sql = "SELECT e.*, en.event_name
            FROM events e
            LEFT JOIN event_name en ON en.Id = e.event_title
            WHERE e.events_delete_status = 0
            ORDER BY e.event_date DESC
            LIMIT ?";
    $stmt = $db->prepare($sql);
    $stmt->bind_param('i', $limit);
    $stmt->execute();
    return $stmt->get_result();
}

function q_get_captain(mysqli $db): ?array {
    $sql = "SELECT b.photo, r.first_name, r.middle_name, r.last_name, r.suffix_name
            FROM barangay_information AS b
            JOIN residents AS r ON b.official_id = r.id
            WHERE b.status = 'active' AND b.position = 'Punong Barangay'
            ORDER BY b.id ASC
            LIMIT 1";
    if ($res = $db->query($sql)) {
        $row = $res->fetch_assoc();
        $res->close();
        return $row ?: null;
    }
    return null;
}

function q_get_active_residents_count(mysqli $db): int {
    $res = $db->query("SELECT COUNT(*) AS c FROM residents WHERE resident_delete_status = 0");
    $row = $res ? $res->fetch_assoc() : null;
    if ($res) $res->close();
    return (int)($row['c'] ?? 0);
}

function q_get_total_events_count(mysqli $db): int {
    $res = $db->query("SELECT COUNT(*) AS c FROM events WHERE events_delete_status = 0");
    $row = $res ? $res->fetch_assoc() : null;
    if ($res) $res->close();
    return (int)($row['c'] ?? 0);
}

function q_get_total_requests_count(mysqli $db): int {
    $sql = "SELECT SUM(total) AS total_count
            FROM (
                SELECT COUNT(*) AS total FROM cedula WHERE cedula_delete_status = 0
                UNION ALL
                SELECT COUNT(*) AS total FROM schedules WHERE appointment_delete_status = 0
                UNION ALL
                SELECT COUNT(*) AS total FROM urgent_request WHERE urgent_delete_status = 0
                UNION ALL
                SELECT COUNT(*) AS total FROM urgent_cedula_request WHERE cedula_delete_status = 0
                UNION ALL
                SELECT COUNT(*) AS total FROM archived_cedula WHERE cedula_delete_status = 0
                UNION ALL
                SELECT COUNT(*) AS total FROM archived_schedules WHERE appointment_delete_status = 0
                UNION ALL
                SELECT COUNT(*) AS total FROM archived_urgent_request WHERE urgent_delete_status = 0
                UNION ALL
                SELECT COUNT(*) AS total FROM archived_urgent_cedula_request WHERE cedula_delete_status = 0
            ) all_requests";
    $res = $db->query($sql);
    $row = $res ? $res->fetch_assoc() : null;
    if ($res) $res->close();
    return (int)($row['total_count'] ?? 0);
}

function q_get_issued_certificates_count(mysqli $db): int {
    $sql = "SELECT SUM(issued) AS issued_count
            FROM (
                SELECT COUNT(*) AS issued FROM cedula WHERE cedula_status = 'Issued' AND cedula_delete_status = 0
                UNION ALL
                SELECT COUNT(*) AS issued FROM schedules WHERE status = 'Issued' AND appointment_delete_status = 0
                UNION ALL
                SELECT COUNT(*) AS issued FROM urgent_request WHERE status = 'Issued' AND urgent_delete_status = 0
                UNION ALL
                SELECT COUNT(*) AS issued FROM urgent_cedula_request WHERE cedula_status = 'Issued' AND cedula_delete_status = 0
                UNION ALL
                SELECT COUNT(*) AS issued FROM archived_cedula WHERE cedula_status = 'Issued' AND cedula_delete_status = 0
                UNION ALL
                SELECT COUNT(*) AS issued FROM archived_schedules WHERE status = 'Issued' AND appointment_delete_status = 0
                UNION ALL
                SELECT COUNT(*) AS issued FROM archived_urgent_request WHERE urgent_delete_status = 0
                UNION ALL
                SELECT COUNT(*) AS issued FROM archived_urgent_cedula_request WHERE cedula_status = 'Issued' AND cedula_delete_status = 0
            ) all_issued";
    $res = $db->query($sql);
    $row = $res ? $res->fetch_assoc() : null;
    if ($res) $res->close();
    return (int)($row['issued_count'] ?? 0);
}

function q_get_appointments_summary(mysqli $db): array {
    $sql = "SELECT SUM(total) as total_appointments, SUM(approved) as approved_appointments
            FROM (
                SELECT COUNT(*) as total,
                       SUM(CASE WHEN status = 'Approved' THEN 1 ELSE 0 END) as approved
                FROM schedules WHERE appointment_delete_status = 0
                UNION ALL
                SELECT COUNT(*) as total,
                       SUM(CASE WHEN cedula_status = 'Approved' THEN 1 ELSE 0 END) as approved
                FROM cedula WHERE cedula_delete_status = 0
                UNION ALL
                SELECT COUNT(*) as total,
                       SUM(CASE WHEN status = 'Approved' THEN 1 ELSE 0 END) as approved
                FROM urgent_request WHERE urgent_delete_status = 0
                UNION ALL
                SELECT COUNT(*) as total,
                       SUM(CASE WHEN cedula_status = 'Approved' THEN 1 ELSE 0 END) as approved
                FROM urgent_cedula_request WHERE cedula_delete_status = 0
            ) t";
    $res = $db->query($sql);
    $row = $res ? $res->fetch_assoc() : ['total_appointments'=>0,'approved_appointments'=>0];
    if ($res) $res->close();
    $total    = (int)($row['total_appointments'] ?? 0);
    $approved = (int)($row['approved_appointments'] ?? 0);
    $rate     = $total > 0 ? (int)round(($approved / $total) * 100) : 0;
    return ['total'=>$total, 'approved'=>$approved, 'rate'=>$rate];
}

function q_get_barangay_contacts(mysqli $db): array {
    $sql = "SELECT telephone_number, mobile_number FROM barangay_info WHERE id = 1";
    $res = $db->query($sql);
    $row = $res ? $res->fetch_assoc() : null;
    if ($res) $res->close();
    return [
        'telephone_number' => $row['telephone_number'] ?? 'No telephone number found',
        'mobile_number'    => $row['mobile_number'] ?? 'No mobile number found',
    ];
}

function q_get_barangay_display_name(mysqli $db): string {
    $sql = "SELECT bm.city_municipality_name, b.barangay_name
            FROM barangay_info bi
            LEFT JOIN city_municipality bm ON bi.city_municipality_id = bm.city_municipality_id
            LEFT JOIN barangay b          ON bi.barangay_id = b.barangay_id
            WHERE bi.id = 1";
    $res = $db->query($sql);
    if ($res && ($row = $res->fetch_assoc())) {
        $res->close();
        $bn = (string)($row['barangay_name'] ?? '');
        $bn = preg_replace('/\s*\(Pob\.\)\s*/i', '', $bn);
        if (stripos($bn, 'Barangay') !== false) return $bn;
        if (stripos($bn, 'Pob') !== false && stripos($bn, 'Poblacion') === false) return "Poblacion " . $bn;
        return (stripos($bn, 'Poblacion') !== false) ? $bn : ("Barangay " . $bn);
    }
    if ($res) $res->close();
    return 'NO BARANGAY FOUND';
}

function q_get_guidelines(mysqli $db): ?mysqli_result {
    $sql = "SELECT guide_description FROM guidelines WHERE status = 1 ORDER BY created_at DESC";
    return $db->query($sql);
}

function q_get_faqs_by_status(mysqli $db, string $status): ?mysqli_result {
    $stmt = $db->prepare("SELECT faq_id, faq_question, faq_answer FROM faqs WHERE faq_status = ? ORDER BY created_at DESC");
    $stmt->bind_param('s', $status);
    $stmt->execute();
    return $stmt->get_result();
}

function q_get_active_officials(mysqli $db): ?mysqli_result {
    $sql = "SELECT b.position, b.photo, r.first_name, r.middle_name, r.last_name, r.suffix_name
            FROM barangay_information AS b
            JOIN residents AS r ON b.official_id = r.id
            WHERE b.status = 'active'
            ORDER BY b.id ASC";
    return $db->query($sql);
}
