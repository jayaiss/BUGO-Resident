<?php
session_start();
require_once __DIR__ . '/../include/connection.php';
$mysqli = db_connection();

// --- Security Headers ---
header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');

// --- Optional: Remove for production ---
// ini_set('display_errors', 1);
// error_reporting(E_ALL);

// --- Require login/session ---
if (!isset($_SESSION['id'])) {
    header('Content-Type: text/html; charset=UTF-8');
    http_response_code(401);
    require_once __DIR__ . '/../security/401.html';
    exit;
}

// --- Check DB Connection ---
if ($mysqli->connect_error) {
    error_log('Database connection error: ' . $mysqli->connect_error);
    echo json_encode([]);
    exit;
}

$holidays = [];

// --- Step 1: Load official holidays ---
$query = "SELECT holiday_date, holiday_name FROM holiday WHERE status = 'Active'";
$result = $mysqli->query($query);

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $holidays[] = [
            'date' => $row['holiday_date'],
            'name' => $row['holiday_name']
        ];
    }
} else {
    error_log('Holiday query error: ' . $mysqli->error);
    echo json_encode([]);
    exit;
}

// --- Step 2: Load fully booked dates ---
$fullQuery = "
    SELECT 
        s.selected_date AS date,
        ts.time_slot_name,
        COUNT(DISTINCT s.res_id) AS total_booked,
        COALESCE(cts.custom_limit, ts.time_slot_number) AS slot_limit
    FROM time_slot ts
    JOIN (
        SELECT selected_date, selected_time, res_id
        FROM schedules
        WHERE appointment_delete_status = 0
        UNION ALL
        SELECT appointment_date AS selected_date, appointment_time AS selected_time, res_id
        FROM cedula
        WHERE cedula_delete_status = 0
    ) s ON s.selected_time = ts.time_slot_name
    LEFT JOIN custom_time_slots cts 
        ON cts.date = s.selected_date AND cts.time_slot_id = ts.id
    GROUP BY s.selected_date, ts.time_slot_name
    HAVING total_booked >= slot_limit
";

$fullResult = $mysqli->query($fullQuery);

if ($fullResult) {
    $fullDates = [];
    while ($row = $fullResult->fetch_assoc()) {
        $fullDates[] = $row['date'];
    }
    $fullDates = array_unique($fullDates);
    foreach ($fullDates as $date) {
        $holidays[] = [
            'date' => $date,
            'name' => 'Fully Booked'
        ];
    }
} else {
    error_log('Full query error: ' . $mysqli->error);
    echo json_encode($holidays); // Only show official holidays if fully booked fails
    exit;
}

// --- Final Output ---
echo json_encode($holidays);
?>
