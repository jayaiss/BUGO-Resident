<?php
session_start();
require_once __DIR__ . '/../include/connection.php';
$mysqli = db_connection();

// --- Security Headers ---
header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');

// --- Session check ---
if (!isset($_SESSION['id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// --- Validate input date ---
$date = $_GET['date'] ?? null;
if (!$date) {
    echo json_encode(['isFullyBooked' => false, 'timeSlots' => []]);
    exit;
}

// --- Check DB connection ---
if ($mysqli->connect_error) {
    error_log('Database connection error: ' . $mysqli->connect_error);
    echo json_encode(['isFullyBooked' => false, 'timeSlots' => []]);
    exit;
}

// --- Fetch all active slots, with custom limit override if exists ---
$slotQuery = "
  SELECT
    ts.Id AS slot_id,
    TIME_FORMAT(ts.time_slot_start, '%H:%i:%s') AS start_time,
    TIME_FORMAT(ts.time_slot_end,   '%H:%i:%s') AS end_time,
    CONCAT(
      DATE_FORMAT(ts.time_slot_start, '%h:%i%p'),
      '-',
      DATE_FORMAT(ts.time_slot_end,   '%h:%i%p')
    ) AS label,
    COALESCE(
      (SELECT c.custom_limit
         FROM custom_time_slots c
        WHERE c.time_slot_id = ts.Id
          AND c.date = ?
        LIMIT 1),
      ts.time_slot_number
    ) AS slot_limit
  FROM time_slot ts
  WHERE ts.status = 'Active'
  ORDER BY ts.time_slot_start ASC
";

$slotStmt = $mysqli->prepare($slotQuery);
if (!$slotStmt) {
    error_log('Slot query prepare failed: ' . $mysqli->error);
    echo json_encode(['isFullyBooked' => false, 'timeSlots' => []]);
    exit;
}

$slotStmt->bind_param("s", $date);
$slotStmt->execute();
$slotResult = $slotStmt->get_result();

$slots = [];
while ($row = $slotResult->fetch_assoc()) {
    $row['slot_limit'] = (int)($row['slot_limit'] ?? 0);
    $row['booked'] = 0;
    $slots[] = $row;
}
$slotStmt->close();

// --- Loop through each slot to count bookings (distinct per resident) ---
foreach ($slots as &$slot) {
    $start = $slot['start_time']; // "HH:MM:SS"
    $end   = $slot['end_time'];   // "HH:MM:SS"

$countQuery = "
    SELECT COUNT(DISTINCT rid) AS booked
    FROM (
        -- schedules: exclude rejected
        SELECT s.res_id AS rid
          FROM schedules s
         WHERE s.selected_date = ?
           AND TIME(STR_TO_DATE(REPLACE(SUBSTRING_INDEX(s.selected_time,'-',1),' ',''),'%h:%i%p')) = ?
           AND TIME(STR_TO_DATE(REPLACE(SUBSTRING_INDEX(s.selected_time,'-',-1),' ',''),'%h:%i%p')) = ?
           AND s.appointment_delete_status = 0
           AND (s.status IS NULL OR s.status <> 'Rejected')

        UNION ALL

        -- cedula: exclude rejected
        SELECT c.res_id AS rid
          FROM cedula c
         WHERE c.appointment_date = ?
           AND TIME(STR_TO_DATE(REPLACE(SUBSTRING_INDEX(c.appointment_time,'-',1),' ',''),'%h:%i%p')) = ?
           AND TIME(STR_TO_DATE(REPLACE(SUBSTRING_INDEX(c.appointment_time,'-',-1),' ',''),'%h:%i%p')) = ?
           AND c.cedula_delete_status = 0
           AND (c.cedula_status IS NULL OR c.cedula_status <> 'Rejected')
    ) AS combined
";

    $countStmt = $mysqli->prepare($countQuery);
    if (!$countStmt) { error_log('Count query prepare failed: '.$mysqli->error); continue; }

    $countStmt->bind_param("ssssss", $date, $start, $end, $date, $start, $end);
    $countStmt->execute();
    $countStmt->bind_result($booked);
    $countStmt->fetch();
    $slot['booked'] = (int)($booked ?? 0);
    $countStmt->close();
}
unset($slot);

// --- Check if fully booked ---
$allFullyBooked = true;
$hasValidSlots = false;
foreach ($slots as $slot) {
    $limit  = (int)$slot['slot_limit'];
    $booked = (int)$slot['booked'];
    if ($limit > 0) {
        $hasValidSlots = true;
        if ($booked < $limit) {
            $allFullyBooked = false;
            break;
        }
    }
}
$isFullyBooked = $hasValidSlots && $allFullyBooked;

// --- Debug info (remove in prod) ---
$debug = [
    'file' => __FILE__,
    'ver' => 'slots-v3-distinct-resident',
    'slotCount' => count($slots)
];

// --- Send JSON response ---
echo json_encode([
    'isFullyBooked' => $isFullyBooked,
    'timeSlots'     => $slots,
    'debug'         => $debug
]);
exit;
?>
