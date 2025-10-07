<?php
require_once __DIR__ . '/../include/connection.php';
$mysqli = db_connection();
if (isset($_GET['id'])) {
    $id = $_GET['id'];
    // Fetch detailed data for the resident
    $sql = "SELECT * FROM residents WHERE id = $id";
    $result = $mhysqli->query($sql);

    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $resident = array(
            'id' => $row["id"],
            'full_name' => $row["first_name"] . " " . $row["middle_name"] . " " . $row["last_name"],
            'gender' => $row["gender"],
            'birth_date' => $row["birth_date"],
            'age' => $row["age"],
            'civil_status' => $row["civil_status"],
            'contact_number' => $row["contact_number"],
            'email' => $row["email"],
            'purok' => $row["purok"],
            'citizenship' => $row["citizenship"],
            'religion' => $row["religion"],
            'occupation' => $row["occupation"]
        );
        echo json_encode($resident); // Return as JSON
    } else {
        echo json_encode(array('error' => 'No details found.'));
    }
}

$mysqli->close();
?>
