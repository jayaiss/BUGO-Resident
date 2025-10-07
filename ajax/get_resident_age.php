<?php
session_start();
require_once __DIR__ . '/../include/connection.php';
$mysqli = db_connection();

// --- Security Headers ---
header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');

// --- Require AJAX request ---
if (!isset($_SERVER['HTTP_X_REQUESTED_WITH']) || $_SERVER['HTTP_X_REQUESTED_WITH'] !== 'XMLHttpRequest') {
    echo json_encode(["error" => "Invalid request"]);
    exit;
}

// --- Require logged-in session ---
if (!isset($_SESSION['id'])) {
    echo json_encode(["error" => "Unauthorized"]);
    exit;
}

// --- Check if res_id is present ---
if (!isset($_GET['res_id'])) {
    echo json_encode(["error" => "Missing res_id"]);
    exit;
}

$res_id = intval($_GET['res_id']);

// --- Check DB Connection ---
if ($mysqli->connect_error) {
    error_log('Database connection error: ' . $mysqli->connect_error);
    echo json_encode(["error" => "Internal server error"]);
    exit;
}

// --- Fetch resident details securely ---
$query = $mysqli->prepare("
    SELECT first_name, middle_name, last_name, suffix_name, birth_date, birth_place, res_zone, res_street_address
    FROM residents
    WHERE id = ?
");
if (!$query) {
    error_log('Resident query prepare failed: ' . $mysqli->error);
    echo json_encode(["error" => "Internal server error"]);
    exit;
}
$query->bind_param("i", $res_id);
$query->execute();
$result = $query->get_result();

if ($row = $result->fetch_assoc()) {
    // Construct full name
    $fullName = trim(
        $row['first_name'] . ' ' .
        ($row['middle_name'] ?? '') . ' ' .
        $row['last_name'] .
        (!empty($row['suffix_name']) ? ', ' . $row['suffix_name'] : '')
    );

    // Construct full address
    $fullAddress = trim(
        (!empty($row['res_zone']) ? 'Zone ' . $row['res_zone'] : '') .
        (!empty($row['res_street_address']) ? ', Street ' . $row['res_street_address'] : '')
    );

    // Calculate age
    try {
        $birthDate = new DateTime($row['birth_date']);
        $today = new DateTime();
        $age = $today->diff($birthDate)->y;
    } catch (Exception $e) {
        error_log('DateTime error: ' . $e->getMessage());
        $age = null;
    }

    echo json_encode([
        "full_name"    => htmlspecialchars($fullName, ENT_QUOTES, 'UTF-8'),
        "birth_date"   => $row['birth_date'],
        "birth_place"  => $row['birth_place'],
        "full_address" => htmlspecialchars($fullAddress, ENT_QUOTES, 'UTF-8'),
        "age"          => $age
    ]);
} else {
    echo json_encode(["error" => "Resident not found"]);
}
?>
