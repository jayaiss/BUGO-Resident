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

// --- Check DB Connection ---
if ($mysqli->connect_error) {
    error_log('Database connection error: ' . $mysqli->connect_error);
    echo json_encode([]);
    exit;
}

// --- Validate input cert name ---
$certName = trim($_GET['cert'] ?? '');
if ($certName === '' || strlen($certName) > 100) {
    echo json_encode([]);
    exit;
}

$stmt = $mysqli->prepare("
    SELECT purpose_name 
    FROM purposes 
    WHERE cert_id = (SELECT Cert_Id FROM certificates WHERE Certificates_Name = ? LIMIT 1) 
    AND status = 'active'
");
if (!$stmt) {
    error_log('Prepare failed: ' . $mysqli->error);
    echo json_encode([]);
    exit;
}
$stmt->bind_param("s", $certName);
$stmt->execute();
$result = $stmt->get_result();

$purposes = [];
while ($row = $result->fetch_assoc()) {
    $purposes[] = $row;
}
$stmt->close();

echo json_encode($purposes);
?>
