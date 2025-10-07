<?php
// Include the connection file to establish the database connection
require_once __DIR__ . '/../include/connection.php';
$mysqli = db_connection();

class soft_delete {
    public function delete_resident($resident_id) {
        global $mysqli; // Use the global variable $mysqli from connection.php

        // Check if the connection is successful
        if ($mysqli->connect_error) {
            die("Connection failed: " . $mysqli->connect_error);
        }

        // Ensure that the resident_id is valid
        if ($resident_id === NULL || $resident_id === '') {
            die("Resident ID is not set or empty.");
        }

        // SQL query to update the soft delete status in the residents table
        $sql = "UPDATE `residents` SET `resident_delete_status` = '1' WHERE id = $resident_id";

        // Execute the query and check if the update was successful
        if ($mysqli->query($sql) === TRUE) {
            // Show success message and immediately redirect to the Resident Info page
            echo '<script>
                    alert("Resident record deleted successfully");
                    window.location.href = "http://localhost/bugo/index_admin.php?page=resident_info"; // Redirect to the specified page
                  </script>';
        } else {
            echo "Error: " . $sql . "<br>" . $mysqli->error;
        }
    }
}

?>
