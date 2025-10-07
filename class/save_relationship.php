<?php
declare(strict_types=1);

ini_set('display_errors', '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);

require_once __DIR__ . '/../include/connection.php';
$mysqli = db_connection();
require_once __DIR__ . '/../include/redirects.php';
require_once __DIR__ . '/../logs/logs_trig.php'; // ✅ add trigger include

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

/**
 * Output a SweetAlert2 modal and exit.
 */
function swal_exit(string $icon, string $title, string $text, ?string $redirect = null): void {
    echo '<!DOCTYPE html><html><head><meta charset="utf-8">';
    echo '<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script></head><body>';
    echo '<script>
        Swal.fire({
            icon: ' . json_encode($icon) . ',
            title: ' . json_encode($title) . ',
            text: ' . json_encode($text) . ',
            confirmButtonText: ' . json_encode($redirect ? 'Go to Profile' : 'Go Back') . '
        }).then(()=>{' .
            ($redirect ? 'window.location.href=' . json_encode($redirect) . ';' : 'history.back();') .
        '});
    </script></body></html>';
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    swal_exit('error', 'Invalid request', 'Use the form to submit the relationship.');
}

// (optional) CSRF check
if (function_exists('validate_csrf_token')) {
    $csrf = $_POST['csrf_token'] ?? '';
    if (!validate_csrf_token($csrf)) {
        swal_exit('error', 'Security check failed', 'Please refresh the page and try again.');
    }
}

// Inputs from form
$child_id  = filter_input(INPUT_POST, 'child_id',  FILTER_VALIDATE_INT);
$parent_id = filter_input(INPUT_POST, 'parent_id', FILTER_VALIDATE_INT);
$type      = trim((string)($_POST['relationship_type'] ?? ''));
$typeNorm  = strtolower($type);

$allowedTypes = ['father','mother','parent','guardian','grandparent','grandchild','sibling','spouse','child'];
if (!in_array($typeNorm, $allowedTypes, true)) {
    swal_exit('error', 'Invalid relationship type', 'Please pick a valid relationship type.');
}
if (!$child_id || !$parent_id) {
    swal_exit('error', 'Missing data', 'Please select both child and parent.');
}
if ($child_id === $parent_id) {
    swal_exit('error', 'Error', 'A person cannot be their own parent.');
}

// --- Orientation logic ---
if ($typeNorm === 'parent' || $typeNorm === 'grandparent') {
    [$parent_id, $child_id] = [$child_id, $parent_id];
}

// Helper to fetch DOB
$fetchDob = function(mysqli $db, int $id): ?string {
    $q = $db->prepare("SELECT birth_date FROM residents WHERE id = ? AND resident_delete_status = 0");
    $q->bind_param('i', $id);
    $q->execute();
    $row = $q->get_result()->fetch_assoc();
    $q->close();
    return $row['birth_date'] ?? null;
};

// Age gap rule
$requiresGap = in_array($typeNorm, ['parent','child','grandparent','grandchild'], true);
$minGapYears = ($typeNorm === 'grandparent' || $typeNorm === 'grandchild') ? 25 : 12;

if ($requiresGap) {
    $childDOB  = $fetchDob($mysqli, $child_id);
    $parentDOB = $fetchDob($mysqli, $parent_id);

    if (!$childDOB || !$parentDOB) {
        swal_exit('error', 'Cannot validate ages', 'One of the selected residents is missing a birth date.');
    }

    try {
        $childDate  = new DateTime($childDOB);
        $parentDate = new DateTime($parentDOB);
    } catch (Throwable $e) {
        swal_exit('error', 'Invalid birth date', 'Could not parse birth dates for the selected residents.');
    }

    $ageGap = $parentDate->diff($childDate)->y;
    if ($ageGap < $minGapYears) {
        swal_exit('error', 'Invalid relationship', "Age gap must be at least {$minGapYears} years for this relationship.");
    }
}

// Prevent duplicate link
$dup = $mysqli->prepare("
    SELECT 1
    FROM resident_relationships
    WHERE related_resident_id = ? AND relationship_type = ?
    LIMIT 1
");
$dup->bind_param('is', $child_id, $type);
$dup->execute();
$exists = (bool)$dup->get_result()->fetch_row();
$dup->close();
if ($exists) {
    swal_exit('error', 'Already linked', "This child is already linked to a " . ucfirst($type) . ".");
}

// --- File upload validation ---
if (
    !isset($_FILES['birth_certificate']) ||
    $_FILES['birth_certificate']['error'] !== UPLOAD_ERR_OK ||
    !is_uploaded_file($_FILES['birth_certificate']['tmp_name'])
) {
    swal_exit('error', 'Missing file', 'Birth certificate is required.');
}

$fileTmp  = $_FILES['birth_certificate']['tmp_name'];
$fileSize = (int)$_FILES['birth_certificate']['size'];
$maxSize  = 2 * 1024 * 1024; // 2MB
if ($fileSize <= 0 || $fileSize > $maxSize) {
    swal_exit('error', 'File too large', 'Max 2MB allowed.');
}

// MIME/type check
$finfo = new finfo(FILEINFO_MIME_TYPE);
$mime  = $finfo->file($fileTmp) ?: '';
$allowedMime = ['image/jpeg','image/png','image/webp','application/pdf'];
if (!in_array($mime, $allowedMime, true)) {
    swal_exit('error', 'Invalid file type', 'Only JPG, PNG, WEBP, or PDF allowed.');
}

$birthCertContent = file_get_contents($fileTmp);
if ($birthCertContent === false) {
    swal_exit('error', 'Upload failed', 'Could not read the uploaded file.');
}

// --- DB write ---
$mysqli->begin_transaction();
try {
    $stmt = $mysqli->prepare("
        INSERT INTO resident_relationships
            (related_resident_id, resident_id, relationship_type, id_birthcertificate)
        VALUES (?,?,?,?)
    ");
    $stmt->bind_param('iiss', $child_id, $parent_id, $type, $birthCertContent);
    $stmt->send_long_data(3, $birthCertContent);
    $stmt->execute();
    $stmt->close();

    $mysqli->commit();

    /* ✅ Audit: record that a relationship was ADDED under RESIDENTS (logs_name=2)
       We log against the *parent_id* so the audit ties to the profile being updated. */
    try {
        $trigs = new Trigger();
        $trigs->isAdded(32, (int)$parent_id);
    } catch (Throwable $ae) {
        error_log('[save_relationship] audit failed: ' . $ae->getMessage());
    }

    $redirect = $redirects['profile_api'];
    swal_exit('success', 'Success!', 'Relationship linked successfully!', $redirect);

} catch (Throwable $e) {
    $mysqli->rollback();
    error_log('[save_relationship] ' . $e->getMessage());
    swal_exit('error', 'Failed to link relationship', 'Something went wrong. Please try again.');
}
