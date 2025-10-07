<?php
declare(strict_types=1);

// ------------------------------------------------------------
// 0) Bootstrap & Security
// ------------------------------------------------------------
ob_start();

require_once __DIR__ . '/security/security.php';
security_no_cache();
session_regenerate_strict();
enforce_pending_password_change();

require_once __DIR__ . '/include/connection.php';
require_once __DIR__ . '/include/encryption.php';
require_once __DIR__ . '/include/redirects.php';
require_once __DIR__ . '/class/session_timeout.php';
require_once __DIR__ . '/version/version.php';

// Query modules
require_once __DIR__ . '/components/Index/app/queries/announcements.php';
require_once __DIR__ . '/components/Index/app/queries/notifications.php';
require_once __DIR__ . '/components/Index/app/queries/residents.php';
require_once __DIR__ . '/components/Index/app/queries/barangay.php';
require_once __DIR__ . '/components/Index/app/queries/logos.php';
require_once __DIR__ . '/components/Index/app/queries/feedback.php';

$mysqli = db_connection();

// Guard: must be logged in and DB healthy
if (!isset($_SESSION['id']) || ($mysqli && $mysqli->connect_error)) {
    header('Location: index.php'); exit;
}

date_default_timezone_set('Asia/Manila');
$today      = date('Y-m-d');
$residentId = (int)($_SESSION['id'] ?? 0);

// ------------------------------------------------------------
// 1) Auto-logout if admin soft-deleted the resident
// ------------------------------------------------------------
if ($residentId > 0 && $mysqli instanceof mysqli) {
    if ($stmt = $mysqli->prepare("SELECT resident_delete_status FROM residents WHERE id = ? LIMIT 1")) {
        $stmt->bind_param("i", $residentId);
        $stmt->execute();
        $stmt->bind_result($deleteStatus);
        $rowFound = $stmt->fetch();
        $stmt->close();

        if ($rowFound && (int)$deleteStatus === 1) {
            if (session_status() === PHP_SESSION_ACTIVE) {
                session_unset();
                session_destroy();
            }
            if (ob_get_level() > 0) { @ob_end_clean(); }
            header('Location: index.php?msg=account_removed');
            exit;
        }
    }
}

// ------------------------------------------------------------
// 2) Small helpers
// ------------------------------------------------------------
function sanitize_input(string $data): string {
    return htmlspecialchars(strip_tags(trim($data)), ENT_QUOTES, 'UTF-8');
}

/** Legacy helper (kept for compatibility, not used to decide the nudge). */
function pick_valid_email_from_profile(array $profile): string {
    $candidates = ['email','resident_email','email_address'];
    foreach ($candidates as $k) {
        if (!isset($profile[$k]) || !is_string($profile[$k])) continue;
        $candidate = trim($profile[$k]);
        if ($candidate === '') continue;

        if (function_exists('decrypt')) {
            try {
                $dec = decrypt($candidate);
                if (is_string($dec) && strpos($dec, '@') !== false) {
                    $candidate = trim($dec);
                }
            } catch (Throwable $e) { /* ignore */ }
        }
        $candidate = preg_replace('/\s+/u', '', $candidate);
        if (filter_var($candidate, FILTER_VALIDATE_EMAIL)) {
            return $candidate;
        }
    }
    return '';
}

/** Return the first valid email for a resident directly from DB. */
function get_valid_resident_email(mysqli $mysqli, int $residentId): string {
    $sql = "SELECT
              TRIM(COALESCE(email,''))          AS e1
            FROM residents
            WHERE id = ?
            LIMIT 1";
    if (!$stmt = $mysqli->prepare($sql)) { return ''; }
    $stmt->bind_param('i', $residentId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc() ?: [];
    $stmt->close();

    foreach (['e1','e2','e3'] as $k) {
        $cand = isset($row[$k]) ? trim((string)$row[$k]) : '';
        if ($cand === '') continue;

        // Try decrypt if your app stores encrypted emails
        if (function_exists('decrypt')) {
            try {
                $dec = decrypt($cand);
                if (is_string($dec) && strpos($dec, '@') !== false) {
                    $cand = trim($dec);
                }
            } catch (Throwable $e) { /* ignore */ }
        }

        $cand = preg_replace('/\s+/u', '', $cand);
        if (filter_var($cand, FILTER_VALIDATE_EMAIL)) {
            return $cand;
        }
    }
    return '';
}

// ------------------------------------------------------------
// 3) Feedback submit (POST)
// ------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_feedback'])) {
    // CSRF + login guards
    $postedToken = $_POST['csrf_token'] ?? '';
    if (function_exists('validate_csrf_token') && !validate_csrf_token($postedToken)) {
        echo "<script>Swal.fire({icon:'error',title:'Security check failed',text:'Please refresh and try again.'});</script>";
        exit;
    }
    if (empty($_SESSION['id'])) {
        echo "<script>Swal.fire({icon:'error',title:'Not signed in',text:'Please login to submit feedback.'});</script>";
        exit;
    }

    // Collect
    $feedbackText  = trim((string)($_POST['feedback'] ?? ''));
    $rating        = isset($_POST['rating']) ? (int)$_POST['rating'] : null;
    $tags          = trim((string)($_POST['tags'] ?? ''));
    $contactName   = trim((string)($_POST['name'] ?? ''));
    $contactEmail  = trim((string)($_POST['email'] ?? ''));
    $allowFollowup = isset($_POST['allow_followup']) ? 1 : 0;

    // Validate
    if (mb_strlen($feedbackText) < 10 || mb_strlen($feedbackText) > 800) {
        echo "<script>Swal.fire({icon:'warning',title:'Please add details',text:'Feedback must be 10–800 characters.'});</script>";
        exit;
    }
    if ($rating !== null && ($rating < 1 || $rating > 5)) $rating = null;
    if ($contactEmail !== '' && !filter_var($contactEmail, FILTER_VALIDATE_EMAIL)) {
        echo "<script>Swal.fire({icon:'warning',title:'Invalid email',text:'Please enter a valid email or leave it blank.'});</script>";
        exit;
    }

    // Trim to column sizes
    $tags         = mb_substr($tags, 0, 255);
    $contactName  = mb_substr($contactName, 0, 100);
    $contactEmail = mb_substr($contactEmail, 0, 254);

    // Sanitize
    $feedbackText = sanitize_input($feedbackText);
    $tags         = sanitize_input($tags);
    $contactName  = sanitize_input($contactName);
    $contactEmail = sanitize_input($contactEmail);

    // Dedupe hash (per day)
    $norm   = preg_replace('/\s+/u', ' ', mb_strtolower($feedbackText));
    $dedupe = hash('sha256', $residentId . '|' . $norm . '|' . date('Y-m-d'));

    // Insert via query module
    $payload = [
        'resident_id'    => $residentId,
        'feedback_text'  => $feedbackText,
        'rating'         => $rating,
        'tags'           => $tags,
        'contact_name'   => $contactName,
        'contact_email'  => $contactEmail,
        'allow_followup' => $allowFollowup,
        'ua'             => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255),
        'ip'             => $_SERVER['REMOTE_ADDR'] ?? '',
        'dedupe_hash'    => $dedupe,
    ];
    $res = q_insert_feedback($mysqli, $payload);

    if ($res['ok']) {
        try {
            if (!class_exists('Trigger')) {
                foreach ([__DIR__ . '/../logs/logs_trig.php', __DIR__ . '/logs/logs_trig.php', __DIR__ . '/../../logs/logs_trig.php'] as $p) {
                    if (file_exists($p)) { require_once $p; break; }
                }
            }
            if (class_exists('Trigger')) {
                $trig = new Trigger();
                $addedId = isset($res['id']) && is_numeric($res['id']) ? (int)$res['id'] : 0;
                $trig->isAdded(20, $addedId);
            } else {
                error_log('[feedback] Trigger class not found; audit not recorded.');
            }
        } catch (Throwable $e) {
            error_log('[feedback] audit failed: ' . $e->getMessage());
        }

        echo "
        <script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script>
        <script>
        document.addEventListener('DOMContentLoaded', function () {
          Swal.fire({
            icon: 'success',
            title: 'Feedback Submitted!',
            text: 'Thank you for your feedback. We appreciate your input, and we will get back to you if you requested follow-up.',
            confirmButtonText: 'Go to Dashboard',
            background: '#2c3e50',
            color: '#ecf0f1',
            confirmButtonColor: '#3498db'
          }).then((result) => {
            if (result.isConfirmed) {
              window.location.href = '{$redirects['dashboard']}';
            }
          });
        });
        </script>";
        exit;
    }

    $isDup = !empty($res['duplicate']);
    $msg   = $isDup ? "Looks like you submitted this already today." : "Could not save. Please try again later.";
    echo "
    <script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script>
    <script>
    document.addEventListener('DOMContentLoaded', function () {
      Swal.fire({
        icon: '".($isDup ? "info" : "error")."',
        title: '".($isDup ? "Already received" : "Error")."',
        text: ".json_encode($msg)."
      }).then(() => { window.location.href = '{$redirects['dashboard']}'; });
    });
    </script>";
    exit;
}

// ------------------------------------------------------------
// 4) Resident display name & profile (USE DIRECT DB EMAIL)
// ------------------------------------------------------------
$residentName    = 'Unknown';
$profileUrl      = 'assets/img/default-avatar.png';
$residentEmail   = '';
$needsEmailSetup = false;

if ($profile = q_get_resident_profile($mysqli, $residentId)) {
    $residentName = $profile['full_name'] ?? 'Unknown';
    if (!empty($profile['profile_picture'])) {
        $profileUrl = 'data:image/jpeg;base64,' . base64_encode($profile['profile_picture']);
    }
}

// Decide the nudge using the DB truth, not the profile array
$residentEmail   = get_valid_resident_email($mysqli, $residentId);
$needsEmailSetup = ($residentEmail === '');

// If email became valid, clear the session flag so it won’t re-pop
if (!$needsEmailSetup && !empty($_SESSION['email_nudge_shown'])) {
    unset($_SESSION['email_nudge_shown']);
}

// Show the nudge only once per session (and only if still missing)
$showEmailNudge = false;
if ($needsEmailSetup && empty($_SESSION['email_nudge_shown'])) {
    $_SESSION['email_nudge_shown'] = 1;
    $showEmailNudge = true;
}

// Optional debug (comment out after verifying)
// error_log('[email-nudge] id='.$residentId.' email="'.$residentEmail.'" needs=' . ($needsEmailSetup?'1':'0'));

// ------------------------------------------------------------
// 5) Announcements + Branding info (safe default)
// ------------------------------------------------------------
$announcementMarquee = "<div class='marquee-text'>No announcements yet.</div>";

// Contacts / Branding
$contacts         = q_get_barangay_contacts($mysqli);
$telephoneNumber  = $contacts['telephone_number'] ?? '';
$mobileNumber     = $contacts['mobile_number'] ?? '';
$barangayName     = q_get_barangay_display_name($mysqli);
$logo             = q_get_active_barangay_logo($mysqli);

// ------------------------------------------------------------
// 6) Notifications
// ------------------------------------------------------------
$counts = q_get_unread_counts($mysqli, $residentId);
$cedulaNotifCount = (int)($counts['cedula_unread'] ?? 0) + (int)($counts['event_unread'] ?? 0);
$notifQuery    = q_get_notifications($mysqli, $residentId, 5);
$allNotifQuery = q_get_notifications($mysqli, $residentId, 5);

// ------------------------------------------------------------
// 7) Mark-as-read / Delete endpoints
// ------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_read'])) {
    q_mark_all_read($mysqli, $residentId);
    echo 'done';
    exit();
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_notif'], $_POST['tracking_number'], $_POST['source'])) {
    q_dismiss_notification(
        $mysqli,
        $residentId,
        sanitize_input((string)$_POST['source']),
        sanitize_input((string)$_POST['tracking_number'])
    );
    exit();
}

// ------------------------------------------------------------
// 8) Logout (POST)
// ------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['logout'])) {
    session_unset();
    session_destroy();
    header("Location: index.php"); exit();
}

// ------------------------------------------------------------
// 9) Page routing param
// ------------------------------------------------------------
$decryptedPage = 'admin_dashboard';
if (isset($_GET['page'])) {
    $tmp = decrypt($_GET['page']);
    if ($tmp !== false) $decryptedPage = $tmp;
}
$currentPage = $decryptedPage;

// ------------------------------------------------------------
// 9b) Age gate for Schedule Appointment
// ------------------------------------------------------------
$canScheduleAppointment = true;
if ($residentId > 0 && $mysqli instanceof mysqli) {
    if ($stmt = $mysqli->prepare("SELECT birth_date FROM residents WHERE id = ? LIMIT 1")) {
        $stmt->bind_param("i", $residentId);
        $stmt->execute();
        $stmt->bind_result($birthdateStr);
        if ($stmt->fetch() && $birthdateStr) {
            try {
                $age = (new DateTime($birthdateStr))->diff(new DateTime('today'))->y;
                $canScheduleAppointment = ($age >= 18);
            } catch (Throwable $e) { $canScheduleAppointment = true; }
        }
        $stmt->close();
    }
}
if ($currentPage === 'schedule_appointment' && !$canScheduleAppointment) {
    echo "<script>
            Swal.fire({icon:'info',title:'Unavailable',text:'Scheduling is for 18+ only.'})
              .then(()=>{ window.location.href = '".enc_page('resident_appointment')."'; });
          </script>";
    exit;
}

// ------------------------------------------------------------
// Close DB
// ------------------------------------------------------------
$mysqli->close();
ob_end_flush();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta http-equiv="X-UA-Compatible" content="IE=edge" />
  <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />
  <title>LGU BUGO - Resident Portal</title>

  <link href="https://cdn.jsdelivr.net/npm/simple-datatables@7.1.2/dist/style.min.css" rel="stylesheet" />
  <link href="css/styles.css" rel="stylesheet" />
  <link rel="icon" type="image/png" href="assets/logo/logo.png">
  <link rel="stylesheet" href="css/resident.css">
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <script src="https://use.fontawesome.com/releases/v6.3.0/js/all.js" crossorigin="anonymous"></script>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.3.0/css/all.min.css">
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/fullcalendar@5.11.0/main.css" rel="stylesheet" />
  <script src="https://cdn.jsdelivr.net/npm/fullcalendar@5.11.0/main.min.js"></script>
  <link rel="stylesheet" href="assets/css/Index/index.css">
  <link rel="stylesheet" href="assets/css/Index/StatusModal.css?=v4">
  <link rel="stylesheet" href="assets/css/Index/feedback.css">
  <link rel="stylesheet" href="assets/css/Index/SideNav.css">
</head>
<body class="sb-nav-fixed">

<?php if (!empty($_SESSION['flash_success'])): ?>
  <script>
    Swal.fire({ icon:'success', title:'Success', text:'<?= addslashes($_SESSION['flash_success']); ?>' });
  </script>
  <?php unset($_SESSION['flash_success']); ?>
<?php endif; ?>

<?php if (!empty($_SESSION['flash_error'])): ?>
  <script>
    Swal.fire({ icon:'error', title:'Oops', text:'<?= addslashes($_SESSION['flash_error']); ?>' });
  </script>
  <?php unset($_SESSION['flash_error']); ?>
<?php endif; ?>

<nav class="sb-topnav navbar navbar-expand navbar-dark bg-dark">
  <!-- Left: Logo -->
  <a class="navbar-brand ps-3 d-flex align-items-center">
    <img src="assets/logo/logo.png" alt="Barangay Bugo Logo" style="width:40px;height:auto;margin-right:10px;">
    Barangay Bugo
  </a>

  <div class="marquee-container d-none d-md-block navbar-dark bg-dark">
    <?= $announcementMarquee ?>
  </div>

  <!-- Sidebar Toggle -->
  <button class="btn btn-link btn-sm order-1 order-lg-0 me-4 me-lg-0" id="sidebarToggle">
    <i class="fas fa-bars"></i>
  </button>

  <?php include 'components/Index/modal/notif_modal.php'; ?>
  <?php include 'components/Index/modal/feedback.php'; ?>
</nav>

<div id="layoutSidenav">
  <?php include 'components/Index/SideNav/SideNav.php'; ?>

  <div id="layoutSidenav_content">
    <main>
      <div class="container-fluid px-4">
        <a class="nav-link">
          <ol class="breadcrumb mb-4"></ol>

          <!-- Floating Feedback Button -->
          <button 
            id="feedbackButton"
            class="feedback-btn"
            data-bs-toggle="modal"
            data-bs-target="#feedbackModal"
            title="Send feedback"
            aria-label="Open feedback form">
            <i class="fas fa-comment-dots" style="font-size:20px;"></i>
          </button>
        </a>

        <main id="main" class="main">
          <section class="section">
            <?php include 'components/Index/Router/Route.php'; ?>
          </section>
        </main>
      </div>
    </main>

    <?php include 'components/Index/footer/footer.php'; ?>
  </div>
</div>

<script src="components/Index/js/index.js"></script>
<script src="components/Index/js/feedback.js"></script>
<script src="components/Index/js/notif.js?=v4"></script>
<script src="components/Index/js/logout.js"></script>
<script src="components/Index/js/SideNav.js"></script>
<script src="js/scripts.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/2.8.0/Chart.min.js" crossorigin="anonymous"></script>
<script src="https://cdn.jsdelivr.net/npm/simple-datatables@7.1.2/dist/umd/simple-datatables.min.js" crossorigin="anonymous"></script>
<script src="components/Index/js/footer.js"></script>
<?php include 'components/Index/modal/AllNotifModal.php'; ?>
<?php include 'components/Index/modal/StatusModal.php'; ?>
<?php if (!empty($showEmailNudge)): ?>
<script>
document.addEventListener('DOMContentLoaded', function () {
  Swal.fire({
    icon: 'warning',
    title: 'Add your email to secure your account',
    html: `
      <p>We couldn't find an email on your account. Adding one enhances security, enables password recovery, and lets you turn on <b>Two-Factor Authentication (2FA)</b>.</p>
      <ol class="text-start" style="margin-left:1rem;">
        <li>Go to <b>Profile</b>.</li>
        <li>Click <b>Edit Profile</b> and enter a valid email.</li>
        <li>Save; then <b>2FA</b> will automatically enable.</li>
      </ol>
    `,
    confirmButtonText: 'Go to Profile',
    showCancelButton: true,
    cancelButtonText: 'Later',
    confirmButtonColor: '#3498db'
  }).then((res) => {
    if (res.isConfirmed) {
      window.location.href = '<?= enc_page('resident_profile'); ?>';
    }
  });
});
</script>
<?php endif; ?>
</body>
</html>
