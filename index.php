<?php
declare(strict_types=1);

$autoload = __DIR__ . '/vendor/autoload.php';
if (!is_file($autoload)) {
  http_response_code(500);
  echo 'Dependency autoloader not found. Please run <code>composer install</code> and deploy the vendor folder.';
  exit();
}
require_once $autoload;
require_once __DIR__ . '/security/security.php';
require_once __DIR__ . '/security/remember.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/include/redirects.php';
require_once __DIR__ . '/include/encryption.php';
require_once __DIR__ . '/version/version.php';
require_once __DIR__ . '/logs/logs_trig.php';
$trigger = new Trigger();
/** ✅ Query layer aggregator for Index page */
require_once __DIR__ . '/components/Login/app/queries/helper.php';

header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

/* -----------------------------
   🔐 reCAPTCHA keys (v2 checkbox)
------------------------------ */
const RECAPTCHA_SITE   = '6Ldid00rAAAAAJW0Uh8pFV_ZPyvhICFCnqesb6Mv';
const RECAPTCHA_SECRET = '6Ldid00rAAAAAOXCldjZkhQfad_-fxzaRZVxg9oB';

if (session_status() === PHP_SESSION_NONE) {
  session_start();
}

/**
 * Consider 2FA “skippable” if the email is missing/invalid.
 */
function can_skip_2fa_email(?string $email): bool {
  $e = trim((string)$email);
  return ($e === '' || !filter_var($e, FILTER_VALIDATE_EMAIL));
}

/**
 * Log the user in (bypassing 2FA), optionally honoring a validated redirect.
 * Also handles remember-me if your helper exists.
 */
function finish_login_and_redirect(int $residentId, string $validatedRedirect = ''): void {
  // make Trigger available here
  global $trigger;
  if (!$trigger instanceof Trigger) { $trigger = new Trigger(); }

  // finalize session
  $_SESSION['id'] = $residentId;
  session_regenerate_id(true);

  // ✅ LOGIN audit (action_made = 6)
  try { $trigger->isLogin(6, $_SESSION['id']); } catch (\Throwable $e) { /* no-op */ }

  // handle remember-me if requested (when bypassing 2FA)
  if (!empty($_SESSION['remember_me_request'])) {
    if (function_exists('remember_issue_token')) {
      $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
      try { remember_issue_token($GLOBALS['mysqli'], $residentId, $ua); } catch (\Throwable $e) {}
    }
    unset($_SESSION['remember_me_request']);
  }

  // clean temp 2FA artifacts
  unset($_SESSION['temp_user_id'], $_SESSION['temp_email'], $_SESSION['2fa_code']);

  // destination
  $dest = 'index_Admin.php?page=' . urlencode(encrypt('homepage'));
  if ($validatedRedirect === 'schedule_appointment') {
    $dest = 'index_Admin.php?page=' . urlencode(encrypt('schedule_appointment'));
  }

  header("Location: {$dest}");
  exit();
}


/**
 * Send 2FA email (cPanel mailbox SMTP + fallbacks)
 */
function send_2fa_mail(string $toEmail, string $toName, string $code): void {
  $mailboxUser = 'admin@bugoportal.site';
  $mailboxPass = 'Jayacop@100'; // ⚠️ cPanel mailbox password
  $smtpHost    = 'mail.bugoportal.site';

  $safeName = htmlspecialchars($toName ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
  $safeCode = htmlspecialchars($code, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

  $buildMessage = function(PHPMailer $m) use ($toEmail, $safeName, $safeCode, $mailboxUser) {
    $m->setFrom($mailboxUser, 'Barangay Bugo');
    $m->addAddress($toEmail, $safeName);
    $m->addBCC($mailboxUser); // TEMP: verify delivery; remove later
    $m->isHTML(true);
    $m->Subject = 'Barangay Bugo 2FA Code';
    $m->Body = "<p>Hello <strong>{$safeName}</strong>,</p>
                <p>Your verification code is:</p>
                <h2 style='color:#0d6efd;'>{$safeCode}</h2>
                <p>This code is valid for 5 minutes.</p>
                <br><p>Thank you,<br>Barangay Bugo Portal</p>";
    // helpful headers
    $m->AltBody  = "Your verification code is: {$safeCode}\nThis code is valid for 5 minutes.";
    $m->CharSet  = 'UTF-8';
    $m->Hostname = 'bugoportal.site';
    $m->Sender   = $mailboxUser; // envelope-from
    $m->addReplyTo($mailboxUser, 'Barangay Bugo');
  };

  $attempt = function(string $mode, int $port) use ($smtpHost, $mailboxUser, $mailboxPass, $buildMessage) {
    $mail = new PHPMailer(true);
    $mail->isSMTP();
    $mail->Host          = $smtpHost;
    $mail->SMTPAuth      = true;
    $mail->Username      = $mailboxUser;
    $mail->Password      = $mailboxPass;
    $mail->Port          = $port;
    $mail->Timeout       = 10;    // short timeouts
    $mail->SMTPAutoTLS   = true;
    $mail->SMTPKeepAlive = false;

    // TEMP: relax TLS checks if the server cert name doesn't match yet
    $mail->SMTPOptions = [
      'ssl' => [
        'verify_peer'       => false,
        'verify_peer_name'  => false,
        'allow_self_signed' => true,
      ]
    ];

    if ($mode === 'ssl') {
      $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;    // port 465
    } else {
      $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS; // port 587
    }

    $buildMessage($mail);
    $mail->send();
  };

  // Log code (TEMP — remove when working)
  error_log("2FA: sending code={$code} to {$toEmail}");

  try {
    error_log('2FA: trying SMTP SSL 465…');
    $attempt('ssl', 465);
    error_log('2FA: sent via 465 SSL');
  } catch (\Throwable $e1) {
    error_log('2FA: 465 failed: '.$e1->getMessage());

    try {
      error_log('2FA: trying SMTP STARTTLS 587…');
      $attempt('tls', 587);
      error_log('2FA: sent via 587 STARTTLS');
    } catch (\Throwable $e2) {
      error_log('2FA: 587 failed: '.$e2->getMessage());

      // Last resort: local MTA (sendmail)
      try {
        error_log('2FA: falling back to local sendmail…');
        $fallback = new PHPMailer(true);
        $fallback->isMail();
        $buildMessage($fallback);
        $fallback->send();
        error_log('2FA: sent via local sendmail');
      } catch (\Throwable $e3) {
        error_log('2FA: sendmail failed: '.$e3->getMessage());
        throw new \RuntimeException('Unable to send verification email right now.');
      }
    }
  } finally {
    // Keep DB connection alive after SMTP attempts
    if (isset($GLOBALS['mysqli']) && $GLOBALS['mysqli'] instanceof mysqli) {
      if (!@$GLOBALS['mysqli']->ping() && function_exists('db_connection')) {
        $GLOBALS['mysqli'] = db_connection();
      }
    }
  }
}

/** If user is already logged in, redirect to homepage */
if (isset($_SESSION['id'])) {
  header('Location: index_Admin.php?page=' . urlencode(encrypt('homepage')));
  exit();
}

/** DB connection */
require_once __DIR__ . '/include/connection.php';
if ($mysqli->connect_error) {
  http_response_code(500);
  require_once __DIR__ . '/security/500.html';
  exit();
}

/** Remember-me: purge old & attempt auto-login (still requires 2FA unless email unusable or mail fails) */
remember_purge_expired($mysqli);

if (!isset($_SESSION['id']) && empty($_SESSION['temp_user_id']) && !empty($_COOKIE[REMEMBER_COOKIE_NAME])) {
  $ua  = $_SERVER['HTTP_USER_AGENT'] ?? '';
  $hit = remember_find_and_verify($mysqli, $_COOKIE[REMEMBER_COOKIE_NAME], $ua);
  $_SESSION['from_remember_cookie'] = true;

  if ($hit) {
    remember_rotate_token($mysqli, $hit['selector'], $ua);

    $stmt = $mysqli->prepare('
      SELECT id, email, first_name, res_pass_change
      FROM residents
      WHERE id = ? AND resident_delete_status = 0
      LIMIT 1
    ');
    $stmt->bind_param('i', $hit['resident_id']);
    $stmt->execute();
    $res  = $stmt->get_result();
    $user = $res ? $res->fetch_assoc() : null;
    $stmt->close();

    if ($user) {
      if ((int)$user['res_pass_change'] === 0) {
        $_SESSION['id'] = (int)$user['id'];
        session_regenerate_id(true);
        header('Location: auth/change_password.php');
        exit();
      }

      $residentId = (int)$user['id'];

      // NEW: Skip 2FA if email unusable
      if (can_skip_2fa_email($user['email'] ?? '')) {
        finish_login_and_redirect($residentId);
      }

      // Otherwise try 2FA; on failure, fall back to bypass
      $_SESSION['temp_user_id'] = $residentId;
      $_SESSION['temp_email']   = $user['email'];
      $code = 'BUGO-' . random_int(100000, 999999);
      $_SESSION['2fa_code'] = $code;

      try {
        send_2fa_mail($user['email'], $user['first_name'] ?? '', $code);
        header('Location: auth/verify_2fa.php');
        exit();
      } catch (\Throwable $e) {
        // Could not send (server down / mailbox bad) → bypass 2FA
        finish_login_and_redirect($residentId);
      }
    }
  } else {
    remember_clear_cookie();
  }
}

/** Login POST */
$id = '';
$password = '';
$error_message = '';
$lockRemaining = 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $postedToken = $_POST['csrf_token'] ?? '';
  if (!validate_csrf_token($postedToken)) {
    $error_message = 'Security check failed. Please refresh the page and try again.';
  } elseif (isset($_POST['id'], $_POST['password'])) {

    /* ------------------------------------
       ✅ reCAPTCHA server-side verification
    ------------------------------------- */
    $captchaResp = $_POST['g-recaptcha-response'] ?? '';
    if ($captchaResp === '') {
      $error_message = 'Please complete the reCAPTCHA.';
    } else {
      $verifyUrl = 'https://www.google.com/recaptcha/api/siteverify';
      $payload   = http_build_query([
        'secret'   => RECAPTCHA_SECRET,
        'response' => $captchaResp,
        'remoteip' => $_SERVER['REMOTE_ADDR'] ?? null,
      ]);

      $context = stream_context_create([
        'http' => [
          'method'  => 'POST',
          'header'  => "Content-type: application/x-www-form-urlencoded\r\n"
                    . "Content-Length: " . strlen($payload) . "\r\n",
          'content' => $payload,
          'timeout' => 5,
        ]
      ]);
      $resp   = @file_get_contents($verifyUrl, false, $context);
      $json   = $resp ? json_decode($resp, true) : ['success' => false];

      if (empty($json['success'])) {
        $errorCodes = isset($json['['.'error-codes'.']']) ? implode(', ', (array)$json['['.'error-codes'.']']) : '';
        error_log('reCAPTCHA failed: ' . $errorCodes);
        $error_message = 'reCAPTCHA verification failed. Please try again.';
      }
    }

    /* ------------------------------------ */

    if ($error_message === '') {
      $id       = trim($_POST['id']);
      $password = trim($_POST['password']);

      if ($id === '' || $password === '') {
        $error_message = 'Please fill out both fields.';
      } else {
        $stmt = $mysqli->prepare('
          SELECT id, username, password, res_pass_change, email, first_name, temp_password
          FROM residents
          WHERE BINARY username = ? AND resident_delete_status = 0
        ');
        $stmt->bind_param('s', $id);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result && $result->num_rows > 0) {
          $row        = $result->fetch_assoc();
          $residentId = (int)$row['id'];

          $remaining = is_resident_locked_out($mysqli, $residentId);
          if ($remaining !== false) {
            $lockRemaining = $remaining;
            $error_message = 'Too many failed attempts. Please wait before trying again.';
          } else {
            // ✅ Allow temp password ONLY when res_pass_change = 0
            $hashedOk    = password_verify($password, $row['password']);
            $tempPlain   = (string)($row['temp_password'] ?? '');
            $needsChange = ((int)$row['res_pass_change'] === 0);
            $usingTemp   = $needsChange && $tempPlain !== '' && hash_equals($tempPlain, $password);

            if ($hashedOk || $usingTemp) {
              reset_resident_attempts($mysqli, $residentId);

              if ($needsChange) {
                // Force password-change flow immediately for users with pending change
                $_SESSION['id'] = $residentId;
                session_regenerate_id(true);

                unset($_SESSION['temp_user_id'], $_SESSION['temp_email'], $_SESSION['2fa_code']);

                header('Location: auth/change_password.php');
                exit();
              }

              // Normal path (password already changed)
              $requestedRedirect = $_POST['redirect_to'] ?? '';
              $allowedRedirects  = ['schedule_appointment'];
              $validatedRedirect = in_array($requestedRedirect, $allowedRedirects, true)
                ? $requestedRedirect
                : '';

              $_SESSION['remember_me_request'] = (!empty($_POST['remember_me']) && $_POST['remember_me'] === '1');

              // NEW: Skip 2FA if email unusable
              if (can_skip_2fa_email($row['email'] ?? '')) {
                finish_login_and_redirect($residentId, $validatedRedirect);
              }

              // Otherwise attempt 2FA; on failure, bypass
              $_SESSION['temp_user_id'] = $residentId;
              $_SESSION['temp_email']   = $row['email'];
              $code = 'BUGO-' . random_int(100000, 999999);
              $_SESSION['2fa_code']     = $code;

              try {
                send_2fa_mail($row['email'], $row['first_name'] ?? '', $code);
                header('Location: auth/verify_2fa.php'); // Redirect after sending OTP
                exit();
              } catch (\Throwable $e) {
                // SMTP error / mailbox problem → bypass 2FA
                finish_login_and_redirect($residentId, $validatedRedirect);
              }
            } else {
              // invalid credentials path
              record_failed_resident_attempt($mysqli, $residentId);
              $remainingAfter = is_resident_locked_out($mysqli, $residentId);
              if ($remainingAfter !== false) {
                $lockRemaining = $remainingAfter;
                $error_message = 'Too many failed attempts. Please wait before trying again.';
              } else {
                $error_message = 'Invalid username or password.';
              }
            }
          }
        } else {
          $error_message = 'Invalid username or password.';
        }
        $stmt->close();
      }
    }
  }
}

/** Queries via query layer (unchanged from your existing file) */
$events_result               = q_get_recent_events($mysqli, 3);
$captain                     = q_get_captain($mysqli);
$active_residents            = q_get_active_residents_count($mysqli);
$total_events                = q_get_total_events_count($mysqli);
$total_requests              = q_get_total_requests_count($mysqli);
$total_issued_certificates   = q_get_issued_certificates_count($mysqli);
$certificates_percentage     = ($total_requests > 0) ? (int)round(($total_issued_certificates / $total_requests) * 100) : 0;

$appts                 = q_get_appointments_summary($mysqli);
$total_appointments    = $appts['total'];
$approved_appointments = $appts['approved'];
$approval_rate         = $appts['rate'];

$contacts        = q_get_barangay_contacts($mysqli);
$telephoneNumber = $contacts['telephone_number'];
$mobileNumber    = $contacts['mobile_number'];
$barangayName    = q_get_barangay_display_name($mysqli);

$guide_result = q_get_guidelines($mysqli);
$faqresult    = q_get_faqs_by_status($mysqli, 'Active');

$officials = [];
if ($res = q_get_active_officials($mysqli)) {
  while ($row = $res->fetch_assoc()) { $officials[] = $row; }
}
?>
<!-- Your existing HTML/template continues below -->

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <meta name="theme-color" content="#F7F7F2" />
  <title>Barangay Bugo | Resident Portal</title>

  <link rel="preconnect" href="https://cdn.jsdelivr.net" crossorigin>
  <link rel="icon" type="image/png" href="assets/logo/logo.png">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
  <link rel="stylesheet" href="assets/css/Index/Login/Login.css">
  <!-- 👇 reCAPTCHA client script -->
  <script src="https://www.google.com/recaptcha/api.js" defer></script>
</head>
<body
  data-lock-remaining="<?= (int)$lockRemaining ?>"
  data-has-error="<?= (!empty($error_message) && (int)$lockRemaining === 0) ? '1' : '0' ?>"
  data-error="<?= htmlspecialchars($error_message, ENT_QUOTES, 'UTF-8') ?>"
>
<?php include 'components/Login/app/modal/TopNav.php'; ?>
<?php include 'components/Login/app/modal/header.php'; ?>
<?php include 'components/Login/app/modal/stats.php'; ?>
<div class="section-divider" aria-hidden="true"></div>

<?php include 'components/Login/app/modal/guidelines.php'; ?>
<?php include 'components/Login/app/modal/officials.php'; ?>
<?php include 'components/Login/app/modal/EventNews.php'; ?>
<?php include 'components/Login/app/modal/FAQModal.php'; ?>
<?php include 'components/Login/app/modal/LoginModal.php'; ?>
<section id="bugo-map" class="container my-5">
  <div class="row justify-content-center">
    <div class="col-lg-10">
      <div class="card shadow-sm border-0">
        <div class="card-body p-0">
          <div class="ratio ratio-16x9">
            <iframe
              loading="lazy"
              allowfullscreen
              referrerpolicy="no-referrer-when-downgrade"
              src="https://www.google.com/maps?q=8.50775,124.75505&z=17&output=embed"
              title="Barangay Bugo Hall Location"
            ></iframe>
          </div>
        </div>
        <div class="card-footer bg-white d-flex flex-wrap gap-2 justify-content-between align-items-center">
          <div>
            <strong>Barangay Bugo Hall</strong><br>
            Bugo, Cagayan de Oro City, Misamis Oriental
          </div>
          <a class="btn btn-primary"
             href="https://www.google.com/maps/search/?api=1&query=8.50775%2C124.75505"
             target="_blank" rel="noopener">
            <i class="bi bi-geo-alt-fill me-1"></i> Get Directions
          </a>
        </div>
      </div>
    </div>
  </div>
</section>
<?php include 'components/Login/app/modal/footer.php'; ?>
<!-- JS (externalized) -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js" defer></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11" defer></script>
<script src="components/Login/app/js/helper.js" defer></script>

<!-- ✅ Friendly alert when account was removed -->
<?php $msg = $_GET['msg'] ?? ''; ?>
<script>
(function () {
  const msg = <?= json_encode($msg) ?>;
  function showNotice() {
    if (typeof Swal === 'undefined') { setTimeout(showNotice, 50); return; }
    if (msg === 'account_removed') {
      Swal.fire({
        icon: 'warning',
        title: 'Account Removed',
        text: 'Your account has been removed by the administrator. For assistance, please contact the Barangay Bugo office.',
        confirmButtonText: 'OK',
        background: '#f8f9fa',
        confirmButtonColor: '#d33',
        allowOutsideClick: true
      });
    }
  }
  if (document.readyState === 'complete') {
    showNotice();
  } else {
    window.addEventListener('load', showNotice);
  }
})();
</script>
</body>
</html>
