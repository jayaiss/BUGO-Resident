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
    echo json_encode(['exists' => false]);
    exit;
}

// --- Get and validate input ---
$email   = isset($_GET['email'])   ? trim($_GET['email']) : '';
$exclude = isset($_GET['exclude']) ? intval($_GET['exclude']) : 0;

// --- Basic email/username check (prevent empty or very short values) ---
if (!$email || strlen($email) < 4) {
    echo json_encode(['exists' => false]);
    exit;
}

if ($exclude) {
    $stmt = $mysqli->prepare("
        SELECT id FROM residents 
        WHERE (email = ? OR username = ?) AND id <> ? 
        LIMIT 1
    ");
    if (!$stmt) {
        error_log('Email exists prepare failed: ' . $mysqli->error);
        echo json_encode(['exists' => false]);
        exit;
    }
    $stmt->bind_param("ssi", $email, $email, $exclude);
} else {
    $stmt = $mysqli->prepare("
        SELECT id FROM residents 
        WHERE email = ? OR username = ? 
        LIMIT 1
    ");
    if (!$stmt) {
        error_log('Email exists prepare failed: ' . $mysqli->error);
        echo json_encode(['exists' => false]);
        exit;
    }
    $stmt->bind_param("ss", $email, $email);
}

$stmt->execute();
$stmt->store_result();

echo json_encode(['exists' => $stmt->num_rows > 0]);
?>
