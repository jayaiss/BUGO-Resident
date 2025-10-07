<?php
session_start();
require_once __DIR__ . '/../include/connection.php';
$mysqli = db_connection();

/* --- Security headers & JSON --- */
header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');

/* --- Auth --- */
if (!isset($_SESSION['id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

/* --- Input --- */
$month = $_GET['month'] ?? null; // YYYY-MM
if (!$month || !preg_match('/^\d{4}-\d{2}$/', $month)) {
    echo json_encode(['fullyBookedDays' => []]);
    exit;
}

/* --- DB --- */
if ($mysqli->connect_error) {
    error_log('DB error: ' . $mysqli->connect_error);
    echo json_encode(['fullyBookedDays' => []]);
    exit;
}

/* --- Helpers --- */
$daysInMonth = (int)date('t', strtotime($month . '-01'));
$fullyBookedDays = [];

/* prepare slot query (per day to allow custom limit by date) */
$slotSql = "
  SELECT
    ts.Id AS slot_id,
    TIME_FORMAT(ts.time_slot_start, '%H:%i:%s') AS start_time,
    TIME_FORMAT(ts.time_slot_end,   '%H:%i:%s') AS end_time,
    COALESCE(cts.custom_limit, ts.time_slot_number) AS slot_limit
  FROM time_slot ts
  LEFT JOIN custom_time_slots cts
    ON cts.time_slot_id = ts.Id AND cts.date = ?
  WHERE ts.status = 'Active'
  ORDER BY ts.time_slot_start
";

/* count by canonical start/end (parse stored strings) */
$countSql = "
  SELECT COUNT(*) AS booked
  FROM (
    SELECT 1
      FROM schedules
     WHERE selected_date = ?
       AND TIME(STR_TO_DATE(REPLACE(SUBSTRING_INDEX(selected_time,'-',1),' ',''),'%h:%i%p')) = ?
       AND TIME(STR_TO_DATE(REPLACE(SUBSTRING_INDEX(selected_time,'-',-1),' ',''),'%h:%i%p')) = ?
       AND appointment_delete_status = 0
    UNION ALL
    SELECT 1
      FROM cedula
     WHERE appointment_date = ?
       AND TIME(STR_TO_DATE(REPLACE(SUBSTRING_INDEX(appointment_time,'-',1),' ',''),'%h:%i%p')) = ?
       AND TIME(STR_TO_DATE(REPLACE(SUBSTRING_INDEX(appointment_time,'-',-1),' ',''),'%h:%i%p')) = ?
       AND cedula_delete_status = 0
  ) t
";

/* loop days */
for ($d = 1; $d <= $daysInMonth; $d++) {
    $date = sprintf('%s-%02d', $month, $d);

    // fetch slots for this date (with custom limit)
    if (!$slotStmt = $mysqli->prepare($slotSql)) { continue; }
    $slotStmt->bind_param('s', $date);
    $slotStmt->execute();
    $res = $slotStmt->get_result();
    if (!$res) { $slotStmt->close(); continue; }

    $slots = [];
    while ($row = $res->fetch_assoc()) {
        $slots[] = [
            'start_time' => $row['start_time'],         // HH:MM:SS
            'end_time'   => $row['end_time'],           // HH:MM:SS
            'slot_limit' => (int)($row['slot_limit']??0)
        ];
    }
    $slotStmt->close();

    if (!$slots) { continue; }

    $allFull   = true;
    $hasLimits = false;

    foreach ($slots as $slot) {
        $limit = (int)$slot['slot_limit'];
        if ($limit <= 0) { continue; }
        $hasLimits = true;

        if (!$countStmt = $mysqli->prepare($countSql)) { $allFull = false; break; }
        $countStmt->bind_param(
            'ssssss',
            $date, $slot['start_time'], $slot['end_time'],
            $date, $slot['start_time'], $slot['end_time']
        );
        $countStmt->execute();
        $countRes = $countStmt->get_result();
        $booked = 0;
        if ($countRes) {
            $row = $countRes->fetch_assoc();
            $booked = (int)($row['booked'] ?? 0);
        }
        $countStmt->close();

        if ($booked < $limit) {
            $allFull = false; // at least one slot has capacity
            break;
        }
    }

    if ($hasLimits && $allFull) {
        $fullyBookedDays[] = $date;
    }
}

echo json_encode(['fullyBookedDays' => $fullyBookedDays]);

?>
