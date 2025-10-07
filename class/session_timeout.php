<?php
// session_start();

$timeout_duration = 60000; // 1 minute in seconds

// Check if session has expired (inactivity longer than 1 minute)
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > $timeout_duration) {
    // Unset session variables and destroy the session
    session_unset();
    session_destroy();

    // Display the alert and then automatically redirect after the user closes the alert
    echo "
    <script>
        // Display an alert with the message
        alert('Your session has expired due to inactivity. Please log in again.');
        
        // Redirect to the login page after the alert is closed
        window.location.href = 'index.php'; // Redirect to the login page
    </script>";
    exit();
}

// Update last activity time
$_SESSION['last_activity'] = time();
?>
