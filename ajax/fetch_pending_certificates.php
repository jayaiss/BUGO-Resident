<?php
session_start();
header('Content-Type: application/json');
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


$resId = $_GET['res_id'] ?? null;
$pendingCerts = [];

if ($resId) {
    $stmt = $mysqli->prepare("
        SELECT certificate FROM schedules 
        WHERE res_id = ? AND status = 'Pending' AND appointment_delete_status = 0
        UNION
        SELECT certificate FROM urgent_request 
        WHERE res_id = ? AND status = 'Pending' AND urgent_delete_status = 0
    ");
    $stmt->bind_param("ii", $resId, $resId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $pendingCerts[] = $row['certificate'];
    }

    $stmt->close();
}
if ($resId) {
    $stmt = $mysqli->prepare("
        SELECT certificate FROM schedules 
        WHERE res_id = ? AND status = 'Approved' AND appointment_delete_status = 0
        UNION
        SELECT certificate FROM urgent_request 
        WHERE res_id = ? AND status = 'Approved' AND urgent_delete_status = 0
    ");
    $stmt->bind_param("ii", $resId, $resId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $ApprovedCerts[] = $row['certificate'];
    }

    $stmt->close();
}
header('Content-Type: application/json');
echo json_encode($pendingCerts);
