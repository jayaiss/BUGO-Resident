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

$response = [];

$query = "SELECT Certificates_Name FROM certificates WHERE status = 'Active'";
$result = $mysqli->query($query);

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $response[] = [
            'Certificates_Name' => $row['Certificates_Name']
        ];
    }
} else {
    error_log('Certificates query error: ' . $mysqli->error);
    // Response is empty array by default
}

echo json_encode($response);
?>
