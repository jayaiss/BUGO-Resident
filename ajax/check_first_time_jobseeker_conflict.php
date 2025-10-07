<?php
session_start();
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
require_once __DIR__ . '/../include/connection.php';
$mysqli = db_connection();

// --- Session check: Only allow logged-in users ---
if (!isset($_SESSION['id'])) {
    header('Content-Type: text/html; charset=UTF-8');
    http_response_code(401);
    require_once __DIR__ . '/../security/401.html';
    exit;
}

// --- Check DB connection ---
if ($mysqli->connect_error) {
    error_log('Database connection error: ' . $mysqli->connect_error);
    echo json_encode([
        'is_pending'              => 0,
        'is_approved'             => 0,
        'is_approved_captain'     => 0,
        'is_rejected'             => 0,
        'used_for_clearance'      => 0,
        'used_for_indigency'      => 0
    ]);
    exit;
}

// --- Validate res_id ---
$res_id = $_GET['res_id'] ?? 0;
if (!ctype_digit((string)$res_id) || intval($res_id) <= 0) {
    echo json_encode([
        'is_pending'              => 0,
        'is_approved'             => 0,
        'is_approved_captain'     => 0,
        'is_rejected'             => 0,
        'used_for_clearance'      => 0,
        'used_for_indigency'      => 0
    ]);
    exit;
}
$res_id = intval($res_id);

// Pull latest BESO Application among the four tables for these statuses
$sql = "
SELECT used_for_clearance, used_for_indigency, created_at, status
FROM (
    SELECT used_for_clearance, used_for_indigency, created_at, status
      FROM schedules
     WHERE certificate = 'BESO Application' AND res_id = ? AND status IN ('Pending','Approved','ApprovedCaptain','Rejected')
    UNION ALL
    SELECT used_for_clearance, used_for_indigency, created_at, status
      FROM archived_schedules
     WHERE certificate = 'BESO Application' AND res_id = ? AND status IN ('Pending','Approved','ApprovedCaptain','Rejected')
    UNION ALL
    SELECT used_for_clearance, used_for_indigency, created_at, status
      FROM urgent_request
     WHERE certificate = 'BESO Application' AND res_id = ? AND status IN ('Pending','Approved','ApprovedCaptain','Rejected')
    UNION ALL
    SELECT used_for_clearance, used_for_indigency, created_at, status
      FROM archived_urgent_request
     WHERE certificate = 'BESO Application' AND res_id = ? AND status IN ('Pending','Approved','ApprovedCaptain','Rejected')
) AS combined
ORDER BY created_at DESC
LIMIT 1
";

$stmt = $mysqli->prepare($sql);
if (!$stmt) {
    error_log('Failed to prepare statement: ' . $mysqli->error);
    echo json_encode([
        'is_pending'              => 0,
        'is_approved'             => 0,
        'is_approved_captain'     => 0,
        'is_rejected'             => 0,
        'used_for_clearance'      => 0,
        'used_for_indigency'      => 0
    ]);
    exit;
}

$stmt->bind_param('iiii', $res_id, $res_id, $res_id, $res_id);
if (!$stmt->execute()) {
    error_log('Failed to execute statement: ' . $stmt->error);
    echo json_encode([
        'is_pending'              => 0,
        'is_approved'             => 0,
        'is_approved_captain'     => 0,
        'is_rejected'             => 0,
        'used_for_clearance'      => 0,
        'used_for_indigency'      => 0
    ]);
    exit;
}

$result = $stmt->get_result();
$row    = $result ? $result->fetch_assoc() : null;

// --- Derive status flags (kept existing vars, only added new keys) ---
$status = $row['status'] ?? null;
$is_pending          = ($status === 'Pending') ? 1 : 0;
$is_approved         = ($status === 'Approved') ? 1 : 0;
$is_approved_captain = ($status === 'ApprovedCaptain') ? 1 : 0;
$is_rejected         = ($status === 'Rejected') ? 1 : 0;

echo json_encode([
    'is_pending'              => $is_pending,
    'is_approved'             => $is_approved,
    'is_approved_captain'     => $is_approved_captain,
    'is_rejected'             => $is_rejected,
    'used_for_clearance'      => $row['used_for_clearance'] ?? 0,
    'used_for_indigency'      => $row['used_for_indigency'] ?? 0
]);
?>
