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
    echo json_encode([]);
    exit;
}

$parentId = $_SESSION['id'];
$children = [];

$stmt = $mysqli->prepare("
    SELECT 
        r.id AS res_id, 
        CONCAT(r.first_name, ' ', COALESCE(r.middle_name, ''), ' ', r.last_name) AS full_name, 
        r.birth_date
    FROM resident_relationships rr
    JOIN residents r ON rr.related_resident_id = r.id
    WHERE rr.resident_id = ? 
      AND rr.status = 'approved' 
      AND r.resident_delete_status = 0
");
if (!$stmt) {
    error_log('Get children prepare failed: ' . $mysqli->error);
    echo json_encode([]);
    exit;
}

$stmt->bind_param("i", $parentId);
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $children[] = $row;
}

$stmt->close();

echo json_encode($children);
?>
