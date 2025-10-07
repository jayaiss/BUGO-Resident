<?php
declare(strict_types=1);
session_start();

require_once __DIR__ . '/../../include/connection.php';
require_once __DIR__ . '/../../include/redirects.php';
require_once __DIR__ . '/../../logs/logs_trig.php';

$mysqli = db_connection();
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$mysqli->set_charset('utf8mb4');

// Inputs from email link
$rid      = isset($_GET['rid']) ? (int)$_GET['rid'] : 0;
$rawToken = $_GET['t'] ?? '';

if ($rid <= 0 || !is_string($rawToken) || strlen($rawToken) !== 64) {
    http_response_code(400);
    echo "Invalid link.";
    exit;
}

// Compare hashed token with stored hex
$tokenHashHex = hash('sha256', $rawToken);

try {
    // Find matching, unused, unexpired request
    $stmt = $mysqli->prepare("
        SELECT id, new_password_hash, expires_at, used
        FROM password_change_requests
        WHERE resident_id = ?
          AND used = 0
          AND token_hash = ?
        LIMIT 1
    ");
    $stmt->bind_param("is", $rid, $tokenHashHex);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$row) {
        echo "This link is invalid or has already been used.";
        exit;
    }

    if (strtotime($row['expires_at']) < time()) {
        echo "This link has expired. Please start again.";
        exit;
    }

    $newHash = $row['new_password_hash'];

    // Apply update atomically
    $mysqli->begin_transaction();

    // Update resident's password (+ timestamp)
    $stmt = $mysqli->prepare("
        UPDATE residents
        SET password = ?, pass_updated_at = NOW()
        WHERE id = ?
    ");
    $stmt->bind_param("si", $newHash, $rid);
    $stmt->execute();
    $affected = $stmt->affected_rows;
    $stmt->close();

    if ($affected < 1) {
        throw new RuntimeException("UPDATE residents affected 0 rows. Check that residents.id=$rid exists.");
    }

    // Mark this request as used
    $stmt = $mysqli->prepare("UPDATE password_change_requests SET used = 1 WHERE id = ?");
    $stmt->bind_param("i", $row['id']);
    $stmt->execute();
    $stmt->close();

    // Optional: clean up any other unused requests for this resident
    $stmt = $mysqli->prepare("DELETE FROM password_change_requests WHERE resident_id = ? AND used = 0");
    $stmt->bind_param("i", $rid);
    $stmt->execute();
    $stmt->close();

    $mysqli->commit();

    // ✅ Keep the user logged in — DO NOT unset/destroy session here.
    // Rotate session ID for safety without logging out:
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_regenerate_id(true);
    }
    try {
        $trig = new Trigger();
        // 12 = PASSWORD_RESET per your transform_action_made
        $trig->isForgotPasswordVerify(12, (int)$rid);
    } catch (Throwable $ae) {
        error_log('[verify_change] audit failed: ' . $ae->getMessage());
    }

} catch (Throwable $e) {
    try { $mysqli->rollback(); } catch (Throwable $e2) {}
    http_response_code(500);
    echo "Unable to complete the request. Please try again.<br><small>"
       . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8')
       . "</small>";
    exit;
}

// Destinations
$stayUrl   = $redirects['homepage'] ?? '/index.php';
$logoutUrl = '/logout.php';

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Password Updated</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="d-flex justify-content-center align-items-center vh-100 bg-light">

<div class="card shadow-lg p-4 text-center" style="max-width: 520px;">
    <h3 class="text-success mb-3">Password Updated Successfully</h3>
    <p class="mb-4">
        Your password has been changed. You can stay logged in and go to the home page,
        or log out now if you prefer.
    </p>
    <div class="d-flex justify-content-center gap-2">
        <!-- Stay logged in: just go home; session remains intact -->
        <a class="btn btn-primary" href="<?php echo htmlspecialchars($stayUrl, ENT_QUOTES, 'UTF-8'); ?>">
            Stay Logged In
        </a>

        <!-- Log out explicitly -->
        <a class="btn btn-outline-danger" href="<?php echo htmlspecialchars($logoutUrl, ENT_QUOTES, 'UTF-8'); ?>">
            Log Out
        </a>
    </div>
</div>

</body>
</html>
