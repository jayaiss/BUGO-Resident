<?php
declare(strict_types=1);

ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

// Block direct access
if (basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'])) {
    http_response_code(403);
    require_once __DIR__ . '/../security/403.html';
    exit;
}

require_once __DIR__ . '/../include/connection.php';
require_once __DIR__ . '/../logs/logs_trig.php';
$mysqli = db_connection();

include 'class/session_timeout.php';
require_once __DIR__ . '/../include/redirects.php';

// Logged-in resident guard
$loggedInResidentId = $_SESSION['id'] ?? null;
if (!$loggedInResidentId) {
    header('Location: index.php');
    exit;
}

// ====== HANDLE POST (Update Profile) ======
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Resident personal information
    $gender          = sanitize_input($_POST['gender'] ?? '');
    $civilStatus     = sanitize_input($_POST['civil_status'] ?? '');
    $birthDate       = sanitize_input($_POST['birth_date'] ?? '');
    $age             = sanitize_input($_POST['age'] ?? ''); // will be recalculated
    $contactNumber   = sanitize_input($_POST['contact_number'] ?? '');
    $email           = sanitize_input($_POST['email'] ?? '');
    $citizenship     = sanitize_input($_POST['citizenship'] ?? '');
    $religion        = sanitize_input($_POST['religion'] ?? '');
    $occupation      = sanitize_input($_POST['occupation'] ?? '');
    $resStreetAddr   = sanitize_input($_POST['res_street_address'] ?? '');
    $birthPlace      = sanitize_input($_POST['birth_place'] ?? '');

    // Emergency contact (combined name -> split)
    $emergencyContactName = preg_replace('/\s+/', ' ', trim(sanitize_input($_POST['emergency_contact_name'] ?? '')));
    $parts = explode(' ', $emergencyContactName, 3);
    $emergencyFirstName  = $parts[0] ?? '';
    $emergencyMiddleName = $parts[1] ?? '';
    $emergencyLastName   = $parts[2] ?? '';

    $emergencySuffixName       = sanitize_input($_POST['emergency_suffix_name'] ?? '');
    $emergencyContactPhone     = sanitize_input($_POST['emergency_contact_phone'] ?? '');
    $emergencyContactEmail     = sanitize_input($_POST['emergency_contact_email'] ?? '');
    $emergencyContactAddress   = sanitize_input($_POST['emergency_contact_address'] ?? '');
    $emergencyContactRelation  = sanitize_input($_POST['relationship'] ?? '');

    // Recalculate age from birth_date (YYYY-mm-dd)
    $currentDate  = new DateTime('now');
    $birthDateObj = DateTime::createFromFormat('Y-m-d', $birthDate);
    if ($birthDateObj && $birthDateObj < $currentDate) {
        $age = (string)$currentDate->diff($birthDateObj)->y;
    } else {
        $age = '0';
    }

    // Capture OLD snapshot before any update (for audit diff)
    $oldData = [];
    $stmtOld = $mysqli->prepare("SELECT * FROM residents WHERE id = ?");
    $stmtOld->bind_param("i", $loggedInResidentId);
    $stmtOld->execute();
    $oldData = $stmtOld->get_result()->fetch_assoc() ?: [];
    $stmtOld->close();

    // Use exceptions for mysqli errors
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

    try {
        $mysqli->begin_transaction();

        // Update resident profile
        $stmt = $mysqli->prepare("
            UPDATE residents
               SET gender = ?, civil_status = ?, birth_date = ?, age = ?, birth_place = ?,
                   contact_number = ?, email = ?, citizenship = ?, religion = ?, occupation = ?, res_street_address = ?
             WHERE id = ?
        ");
        $stmt->bind_param(
            "sssisssssssi",
            $gender, $civilStatus, $birthDate, $age, $birthPlace,
            $contactNumber, $email, $citizenship, $religion, $occupation, $resStreetAddr,
            $loggedInResidentId
        );
        $stmt->execute();
        $stmt->close();

        // Check if emergency contact exists
        $stmt = $mysqli->prepare("SELECT id FROM emergency_contact WHERE resident_id = ?");
        $stmt->bind_param("i", $loggedInResidentId);
        $stmt->execute();
        $stmt->store_result();
        $contactExists = $stmt->num_rows > 0;
        $stmt->close();

        if (!$contactExists) {
            // Insert new emergency contact
            $stmt = $mysqli->prepare("
                INSERT INTO emergency_contact (
                    resident_id, emergency_first_name, emergency_middle_name, emergency_last_name,
                    emergency_suffix_name, emergency_contact_phone, emergency_contact_email,
                    emergency_contact_address, emergency_contact_relationship
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->bind_param(
                "issssssss",
                $loggedInResidentId, $emergencyFirstName, $emergencyMiddleName, $emergencyLastName,
                $emergencySuffixName, $emergencyContactPhone, $emergencyContactEmail,
                $emergencyContactAddress, $emergencyContactRelation
            );
            $stmt->execute();
            $stmt->close();
        } else {
            // Update existing emergency contact
            $stmt = $mysqli->prepare("
                UPDATE emergency_contact
                   SET emergency_first_name = ?, emergency_middle_name = ?, emergency_last_name = ?,
                       emergency_suffix_name = ?, emergency_contact_phone = ?, emergency_contact_email = ?,
                       emergency_contact_address = ?, emergency_contact_relationship = ?
                 WHERE resident_id = ?
            ");
            $stmt->bind_param(
                "ssssssssi",
                $emergencyFirstName, $emergencyMiddleName, $emergencyLastName,
                $emergencySuffixName, $emergencyContactPhone, $emergencyContactEmail,
                $emergencyContactAddress, $emergencyContactRelation, $loggedInResidentId
            );
            $stmt->execute();
            $stmt->close();
        }

        // Optional: Upload profile picture
        if (isset($_FILES['profile_picture']) && is_uploaded_file($_FILES['profile_picture']['tmp_name'])) {
            $fileTmpName = $_FILES['profile_picture']['tmp_name'];
            $fileType    = mime_content_type($fileTmpName) ?: ($_FILES['profile_picture']['type'] ?? '');
            $fileSize    = (int)($_FILES['profile_picture']['size'] ?? 0);

            $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
            $maxBytes     = 5 * 1024 * 1024; // 5MB

            if (in_array($fileType, $allowedTypes, true) && $fileSize > 0 && $fileSize <= $maxBytes) {
                $imageData = file_get_contents($fileTmpName);

                $stmt = $mysqli->prepare("UPDATE residents SET profile_picture = ? WHERE id = ?");
                // For BLOBs: bind a NULL placeholder and then send data
                $null = null;
                $stmt->bind_param("bi", $null, $loggedInResidentId);
                $stmt->send_long_data(0, $imageData);
                $stmt->execute();
                $stmt->close();
            }
        }

        // Commit once, after all changes
        $mysqli->commit();

        // === AUDIT: log EDIT with old/new diffs (logs_name = 2 => RESIDENTS) ===
        try {
            require_once __DIR__ . '/../logs/logs_trig.php';
            $trigs = new Trigger();
            $trigs->isEdit(18, $loggedInResidentId, $oldData);
        } catch (Throwable $auditErr) {
            error_log('Audit log failed: ' . $auditErr->getMessage());
        }

        echo "<script>
            Swal.fire({
                icon: 'success',
                title: 'Profile Updated Successfully!',
                text: 'Your profile has been updated.',
                confirmButtonColor: '#3085d6'
            }).then(() => {
                window.location.href = '{$redirects['profile_api']}';
            });
        </script>";
    } catch (Throwable $e) {
        $mysqli->rollback();
        // Optionally log $e->getMessage() to a secure server-side log
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Error!',
                text: 'Something went wrong while updating your profile. Please try again.',
                confirmButtonColor: '#d33'
            });
        </script>";
    }
}


// ====== FETCH PROFILE + EMERGENCY CONTACT ======
$stmt = $mysqli->prepare("
    SELECT r.first_name, r.middle_name, r.last_name, r.gender, r.civil_status, r.birth_date, r.birth_place,
           r.contact_number, r.email, r.citizenship, r.religion, r.occupation, r.res_street_address, r.profile_picture,
           ec.emergency_first_name, ec.emergency_middle_name, ec.emergency_last_name, ec.emergency_contact_phone,
           ec.emergency_contact_email, ec.emergency_contact_address, ec.emergency_contact_relationship
      FROM residents r
 LEFT JOIN emergency_contact ec ON r.id = ec.resident_id
     WHERE r.id = ?
");
$stmt->bind_param("i", $loggedInResidentId);
$stmt->execute();
$stmt->bind_result(
    $firstName, $middleName, $lastName, $gender, $civilStatus, $birthDate, $birthPlace,
    $contactNumber, $email, $citizenship, $religion, $occupation, $resStreetAddress, $profilePicture,
    $emergencyFirstName, $emergencyMiddleName, $emergencyLastName, $emergencyContactPhone,
    $emergencyContactEmail, $emergencyContactAddress, $emergencyContactRelationship
);
$stmt->fetch();
$stmt->close();

$emergencyContactName = trim(preg_replace('/\s+/', ' ', ($emergencyFirstName ?? '') . ' ' . ($emergencyMiddleName ?? '') . ' ' . ($emergencyLastName ?? '')));

// ====== FETCH FAMILY MEMBERS ======
$familyStmt = $mysqli->prepare("
    SELECT r.first_name, r.middle_name, r.last_name, r.age, r.gender, rr.relationship_type, rr.status
      FROM resident_relationships rr
      JOIN residents r ON r.id = rr.related_resident_id
     WHERE rr.resident_id = ?
");
$familyStmt->bind_param("i", $loggedInResidentId);
$familyStmt->execute();
$familyResult = $familyStmt->get_result();

$familyMembers = [];
while ($row = $familyResult->fetch_assoc()) {
    $role   = ($row['gender'] === 'Male') ? 'Son' : 'Daughter';
    $status = $row['status'] ?? 'pending';
    $full   = trim(preg_replace('/\s+/', ' ', "{$row['first_name']} {$row['middle_name']} {$row['last_name']}"));

    $familyMembers[] = [
        'full_name' => $full,
        'age'       => (int)$row['age'],
        'label'     => $role,
        'status'    => $status
    ];
}
$familyStmt->close();

// ====== RESIDENTS FOR DROPDOWN (excluding logged-in) ======
$stmt = $mysqli->prepare("
    SELECT id,
           first_name,
           COALESCE(middle_name,'') AS middle_name,
           last_name,
           TIMESTAMPDIFF(YEAR, birth_date, CURDATE()) AS age
      FROM residents
     WHERE id <> ? AND resident_delete_status = 0
");
$stmt->bind_param("i", $loggedInResidentId);
$stmt->execute();
$res = $stmt->get_result();

$rows = [];
while ($r = $res->fetch_assoc()) {
    $full = trim(preg_replace('/\s+/', ' ', "{$r['first_name']} {$r['middle_name']} {$r['last_name']}"));
    $rows[] = [
        'id'   => (int)$r['id'],
        'name' => $full,
        'age'  => (int)$r['age'],
    ];
}
$stmt->close();

// Logged-in resident's age (reliable)
$ageStmt = $mysqli->prepare("
    SELECT TIMESTAMPDIFF(YEAR, birth_date, CURDATE()) AS age
      FROM residents
     WHERE id = ? AND resident_delete_status = 0
");
$ageStmt->bind_param("i", $loggedInResidentId);
$ageStmt->execute();
$ageRow = $ageStmt->get_result()->fetch_assoc();
$ageStmt->close();
$loggedInResidentAge = isset($ageRow['age']) ? (int)$ageRow['age'] : 0;
?>
<script>
  // Make data available to your JS
  window.familyMembers = <?= json_encode($familyMembers, JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) ?>;
  window.residents = <?= json_encode($rows, JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) ?>;
  window.loggedInResidentAge = <?= json_encode($loggedInResidentAge) ?>;
</script>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Resident Profile</title>

  <!-- <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css"> -->
  <!-- <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css"> -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <link rel="stylesheet" href="assets/css/resident.css">
  <link rel="stylesheet" href="assets/css/modal/link_modal.css">
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
      <link rel="stylesheet" href="https://unpkg.com/cropperjs@1.6.2/dist/cropper.min.css">
    <script src="https://unpkg.com/cropperjs@1.6.2/dist/cropper.min.js"></script>
</head>
<body>
<?php if (!empty($_SESSION['flash_success'])): ?>
<script>
  Swal.fire({ icon:'success', title:'Success', text:'<?= addslashes($_SESSION['flash_success']); ?>' });
</script>
<?php unset($_SESSION['flash_success']); endif; ?>

<?php if (!empty($_SESSION['flash_error'])): ?>
<script>
  Swal.fire({ icon:'error', title:'Oops', text:'<?= addslashes($_SESSION['flash_error']); ?>' });
</script>
<?php unset($_SESSION['flash_error']); endif; ?>

<?php include 'components/Resident/ResidentView.php'; ?>

<?php include 'components/Resident/modal/link_family_modal.php'; ?>
<?php include 'components/Resident/modal/modal.php'; ?>
<?php include 'components/Resident/modal/cropper.php'; ?>

<script src="components/Resident/js/resident.js"></script>
<script src="components/Resident/js/link_modal.js"></script>
<script src="components/Resident/js/crop_prof.js"></script>
<!-- <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script> -->


<script src="js/email.js"></script>
</body>
</html>
