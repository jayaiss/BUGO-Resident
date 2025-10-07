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

// --- Check DB connection ---
if ($mysqli->connect_error) {
    error_log('Database connection error: ' . $mysqli->connect_error);
    echo json_encode([
        'used' => false, 
        'has_beso_record' => false, 
        'has_residency_ftj' => false
    ]);
    exit;
}

// --- Validate and sanitize res_id ---
$resId = isset($_GET['res_id']) ? intval($_GET['res_id']) : 0;
$response = [
    'used' => false, 
    'has_beso_record' => false,
    'has_residency_ftj' => false
];

if ($resId > 0) {
    // 1. Residency already USED for BESO?
    $stmt = $mysqli->prepare("
        SELECT COUNT(*) FROM (
            SELECT barangay_residency_used_for_beso FROM archived_schedules
            WHERE res_id = ? AND certificate = 'Barangay Residency' AND purpose = 'First Time Jobseeker' AND barangay_residency_used_for_beso = 1
            UNION ALL
            SELECT barangay_residency_used_for_beso FROM archived_urgent_request
            WHERE res_id = ? AND certificate = 'Barangay Residency' AND purpose = 'First Time Jobseeker' AND barangay_residency_used_for_beso = 1
            UNION ALL
            SELECT barangay_residency_used_for_beso FROM schedules
            WHERE res_id = ? AND certificate = 'Barangay Residency' AND purpose = 'First Time Jobseeker' AND barangay_residency_used_for_beso = 1
            UNION ALL
            SELECT barangay_residency_used_for_beso FROM urgent_request
            WHERE res_id = ? AND certificate = 'Barangay Residency' AND purpose = 'First Time Jobseeker' AND barangay_residency_used_for_beso = 1
        ) AS combined
    ");
    if ($stmt) {
        $stmt->bind_param("iiii", $resId, $resId, $resId, $resId);
        $stmt->execute();
        $stmt->bind_result($usedCount);
        $stmt->fetch();
        $stmt->close();
    } else {
        error_log('BESO used query failed: ' . $mysqli->error);
        $usedCount = 0;
    }

    // 2. Resident has BESO record?
    $stmt = $mysqli->prepare("SELECT COUNT(*) FROM beso WHERE res_id = ? AND beso_delete_status = 0");
    if ($stmt) {
        $stmt->bind_param("i", $resId);
        $stmt->execute();
        $stmt->bind_result($besoCount);
        $stmt->fetch();
        $stmt->close();
    } else {
        error_log('BESO record query failed: ' . $mysqli->error);
        $besoCount = 0;
    }

    // 3. Any Residency (First Time Jobseeker)?
    $stmt = $mysqli->prepare("
        SELECT COUNT(*) FROM (
            SELECT 1 FROM schedules
            WHERE res_id = ? AND certificate = 'Barangay Residency' AND purpose = 'First Time Jobseeker'
            UNION ALL
            SELECT 1 FROM urgent_request
            WHERE res_id = ? AND certificate = 'Barangay Residency' AND purpose = 'First Time Jobseeker'
            UNION ALL
            SELECT 1 FROM archived_schedules
            WHERE res_id = ? AND certificate = 'Barangay Residency' AND purpose = 'First Time Jobseeker'
            UNION ALL
            SELECT 1 FROM archived_urgent_request
            WHERE res_id = ? AND certificate = 'Barangay Residency' AND purpose = 'First Time Jobseeker'
        ) AS combined
    ");
    if ($stmt) {
        $stmt->bind_param("iiii", $resId, $resId, $resId, $resId);
        $stmt->execute();
        $stmt->bind_result($residencyFTJCount);
        $stmt->fetch();
        $stmt->close();
    } else {
        error_log('Residency FTJ query failed: ' . $mysqli->error);
        $residencyFTJCount = 0;
    }

    $response = [
        'used'               => isset($usedCount) ? $usedCount > 0 : false,
        'has_beso_record'    => isset($besoCount) ? $besoCount > 0 : false,
        'has_residency_ftj'  => isset($residencyFTJCount) ? $residencyFTJCount > 0 : false
    ];
}

echo json_encode($response);
?>
