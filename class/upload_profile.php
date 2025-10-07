<?php
// class/upload_profile.php
declare(strict_types=1);

ini_set('display_errors', '0'); // avoid breaking headers in prod
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

require_once __DIR__ . '/../include/connection.php';
require_once __DIR__ . '/../include/redirects.php';
require_once __DIR__ . '/../logs/logs_trig.php'; // ✅ add trigger

session_start();

$mysqli = db_connection();

if (!isset($_SESSION['id'])) {
    http_response_code(403);
    exit('Not authorized');
}

$loggedInResidentId = (int) $_SESSION['id'];

// Optional: CSRF check if you include a token
// if (!hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'] ?? '')) {
//     $_SESSION['flash_error'] = 'Security check failed.';
//     header('Location: ' . $redirects['profile']);
//     exit;
// }

if (!isset($_FILES['profile_picture']) || $_FILES['profile_picture']['error'] !== UPLOAD_ERR_OK) {
    header('Location: ' . $redirects['profile']);
    exit;
}

// size cap
if ($_FILES['profile_picture']['size'] > 5 * 1024 * 1024) {
    $_SESSION['flash_error'] = 'Image too large (max 2MB).';
    header('Location: ' . $redirects['profile']);
    exit;
}

$path = $_FILES['profile_picture']['tmp_name'];

// validate image + dimensions
$imgInfo = @getimagesize($path);
if ($imgInfo === false) {
    $_SESSION['flash_error'] = 'Invalid image.';
    header('Location: ' . $redirects['profile']);
    exit;
}
[$w, $h] = $imgInfo;
if ($w > 8000 || $h > 8000) {
    $_SESSION['flash_error'] = 'Image dimensions too large.';
    header('Location: ' . $redirects['profile']);
    exit;
}

// strong MIME sniffing
$finfo = new finfo(FILEINFO_MIME_TYPE);
$mime  = $finfo->file($path);
$allowed = [
    'image/jpeg' => 'jpg',
    'image/png'  => 'png',
    'image/gif'  => 'gif',
    'image/webp' => 'webp',
];
if (!isset($allowed[$mime])) {
    $_SESSION['flash_error'] = 'Unsupported file type.';
    header('Location: ' . $redirects['profile']);
    exit;
}

// re-encode if GD available; fallback to raw bytes
$imageData = null;

if (function_exists('imagecreatetruecolor')) {
    ob_start();
    switch ($mime) {
        case 'image/jpeg':
            if (function_exists('imagecreatefromjpeg')) {
                $im = imagecreatefromjpeg($path);
                imagejpeg($im, null, 90);
                imagedestroy($im);
                break;
            }
            // fallthrough to raw
        case 'image/png':
            if (function_exists('imagecreatefrompng')) {
                $im = imagecreatefrompng($path);
                imagepng($im);
                imagedestroy($im);
                break;
            }
        case 'image/gif':
            if (function_exists('imagecreatefromgif')) {
                $im = imagecreatefromgif($path);
                imagegif($im);
                imagedestroy($im);
                break;
            }
        case 'image/webp':
            if (function_exists('imagecreatefromwebp') && function_exists('imagewebp')) {
                $im = imagecreatefromwebp($path);
                imagewebp($im, null, 80);
                imagedestroy($im);
                break;
            }
            // fallback: raw bytes
            ob_end_clean();
            $imageData = file_get_contents($path);
            break;
    }
    if ($imageData === null) {
        $imageData = ob_get_clean();
    }
} else {
    // GD not available
    $imageData = file_get_contents($path);
}

/* ===== Snapshot OLD row before updating (for audit diff) ===== */
$oldData = [];
$stmtOld = $mysqli->prepare("SELECT * FROM residents WHERE id = ?");
$stmtOld->bind_param('i', $loggedInResidentId);
$stmtOld->execute();
$oldData = $stmtOld->get_result()->fetch_assoc() ?: [];
$stmtOld->close();

/* ===== Update BLOB ===== */
$stmt = $mysqli->prepare("UPDATE residents SET profile_picture = ? WHERE id = ?");
$null = null;
$stmt->bind_param('bi', $null, $loggedInResidentId);
$stmt->send_long_data(0, $imageData);
$stmt->execute();
$stmt->close();

/* ===== Audit trigger: log EDIT for RESIDENTS (logs_name = 2) ===== */
try {
    $trigs = new Trigger();
    $trigs->isEdit(19, $loggedInResidentId, $oldData);
} catch (Throwable $e) {
    // Do not block UX on audit failure
    error_log('Audit log (profile picture) failed: ' . $e->getMessage());
}

$_SESSION['flash_success'] = 'Profile picture updated successfully.';
header('Location: ' . $redirects['profile_api']);
exit;
