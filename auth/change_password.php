<?php
declare(strict_types=1);

require_once __DIR__ . '/../security/security.php';
security_no_cache();
session_regenerate_strict();

// Start a guard as soon as the page opens
if (empty($_SESSION['pw_change_guard'])) {
    $_SESSION['pw_change_guard'] = ['started' => true, 'ts' => time()];
}

/* -------------------------------------------
   Unified absolute logout URL (hardcoded)
   ------------------------------------------- */
$logoutUrl = 'https://bugoportal.site/logout.php?reason=pw_flow';

/* --- Early cancel exit: route to central logout and stop --- */
if (isset($_GET['cancel']) || isset($_POST['cancel'])) {
    header('Location: ' . $logoutUrl);
    exit;
}

require_once __DIR__ . '/../include/redirects.php';

if (!isset($_SESSION['id'])) {
    header('Location: ' . $redirects['homepage']);
    exit();
}

// CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

require_once __DIR__ . '/../include/connection.php';
require_once __DIR__ . '/../include/encryption.php';
require_once __DIR__ . '/../logs/logs_trig.php'; // ✅ Trigger include

require_once __DIR__ . '/../vendor/autoload.php';
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$mysqli = db_connection();
$trigger = new Trigger(); // ✅ Instantiate Trigger

if ($mysqli->connect_error) {
    http_response_code(500);
    exit('Database error.');
}

// Progressive backoff state
$_SESSION['cp_fail'] = $_SESSION['cp_fail'] ?? 0;
$_SESSION['cp_last'] = $_SESSION['cp_last'] ?? 0;

$error_message = '';
$PASSWORD_PATTERN = '/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^\w\s]).{8,}$/';

function g($k){ return $_POST[$k] ?? ''; }
function v(string $s): string { return trim($s); }

function send_cp_otp_mail(string $toEmail, string $toName, string $otpRaw): void {
    // NOTE: move these to env vars in production
    $mailboxUser = 'admin@bugoportal.site';
    $mailboxPass = 'Jayacop@100';
    $smtpHost    = 'mail.bugoportal.site';

    $safeName = htmlspecialchars($toName ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $safeCode = htmlspecialchars($otpRaw, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

    $buildMessage = function(PHPMailer $m) use ($toEmail, $safeName, $safeCode, $mailboxUser) {
        $m->setFrom($mailboxUser, 'Barangay Bugo');
        $m->addAddress($toEmail, $safeName);
        $m->addBCC($mailboxUser);
        $m->isHTML(true);
        $m->Subject = 'Barangay Bugo 2FA Code';
        $m->Body = "<p>Hello <strong>{$safeName}</strong>,</p>
            <p>Your verification code is:</p>
            <h2 style='color:#0d6efd;margin:8px 0;'>{$safeCode}</h2>
            <p>This code is valid for 5 minutes.</p>
            <br><p>Thank you,<br>Barangay Bugo Portal</p>";
        $m->AltBody  = "Your verification code is: {$safeCode}\nThis code is valid for 5 minutes.";
        $m->CharSet  = 'UTF-8';
        $m->Hostname = 'bugoportal.site';
        $m->Sender   = $mailboxUser;
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
        $mail->Timeout       = 10;
        $mail->SMTPAutoTLS   = true;
        $mail->SMTPKeepAlive = false;
        $mail->SMTPOptions = [
            'ssl' => [
                'verify_peer'       => false,
                'verify_peer_name'  => false,
                'allow_self_signed' => true,
            ]
        ];
        $mail->SMTPSecure = ($mode === 'ssl') ? PHPMailer::ENCRYPTION_SMTPS : PHPMailer::ENCRYPTION_STARTTLS;
        $buildMessage($mail);
        $mail->send();
    };

    try {
        $attempt('ssl', 465);
    } catch (\Throwable $e1) {
        try {
            $attempt('tls', 587);
        } catch (\Throwable $e2) {
            $fallback = new PHPMailer(true);
            $fallback->isMail();
            $buildMessage($fallback);
            $fallback->send();
        }
    }
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $postedToken = g('csrf_token');
    if (!hash_equals($_SESSION['csrf_token'], $postedToken)) {
        http_response_code(403);
        $error_message = "Security check failed. Please refresh and try again.";
    }

    $now = time();
    $since = $now - (int)$_SESSION['cp_last'];
    $fail = (int)$_SESSION['cp_fail'];
    $backoff = min(8, $fail) ** 2;
    if (!$error_message && $since < $backoff) {
        $error_message = "Please wait a moment before trying again.";
    }

    $current_password = v(g('current_password'));
    $new_password     = v(g('new_password'));
    $confirm_password = v(g('confirm_password'));
    $resident_id      = (int)$_SESSION['id'];

    if (!$error_message) {
        if ($current_password === '' || $new_password === '' || $confirm_password === '') {
            $error_message = "All fields are required.";
        } elseif (!preg_match($PASSWORD_PATTERN, $new_password)) {
            $error_message = "Use 8+ chars with upper/lowercase, a number, and a special character.";
        } elseif ($new_password !== $confirm_password) {
            $error_message = "New password and confirmation do not match.";
        }
    }

    // Fetch email/username/password/temp
    $residentEmail = $residentUsername = null;
    $current_hash  = null;
    $temp_password = null;
    $res_pass_change = 0;

    if (!$error_message) {
        $stmt = $mysqli->prepare("
            SELECT email, username, password, temp_password, res_pass_change
              FROM residents 
             WHERE id = ? AND resident_delete_status = 0
        ");
        $stmt->bind_param("i", $resident_id);
        $stmt->execute();
        $stmt->bind_result($residentEmail, $residentUsername, $current_hash, $temp_password, $res_pass_change);
        if (!$stmt->fetch()) {
            $error_message = "User not found or account is deleted.";
        }
        $stmt->close();
    }

    // Verify current password (support temp_password)
    if (!$error_message) {
        $ok = false;

        if ((int)$res_pass_change === 0 && $temp_password !== null) {
            if (hash_equals($temp_password, $current_password)) {
                $ok = true;
            }
        }

        if (!$ok && $current_hash && password_verify($current_password, $current_hash)) {
            $ok = true;
        }

        if (!$ok) {
            $error_message = "Current password is incorrect.";
        } elseif ($current_hash && password_verify($new_password, $current_hash)) {
            $error_message = "New password cannot be the same as the current password.";
        } elseif ((int)$res_pass_change === 0 && $new_password === $temp_password) {
            $error_message = "New password cannot be the same as your temporary password.";
        }
    }

    // History check
    if (!$error_message) {
        $stmtH = $mysqli->prepare("
            SELECT old_password FROM res_password_history
             WHERE res_id = ? ORDER BY change_date DESC LIMIT 5
        ");
        $resident_id_str = (string)$resident_id;
        $stmtH->bind_param("s", $resident_id_str);
        $stmtH->execute();
        $rH = $stmtH->get_result();
        while ($row = $rH->fetch_assoc()) {
            if (password_verify($new_password, $row['old_password'])) {
                $error_message = "You cannot reuse a recent password.";
                break;
            }
        }
        $stmtH->close();
    }

    if (!$error_message) {
        $new_hash = password_hash($new_password, PASSWORD_DEFAULT);

        // ✅ If no working email, finalize immediately (skip OTP)
        if (empty($residentEmail) || !filter_var($residentEmail, FILTER_VALIDATE_EMAIL)) {
            $resident_id_str = (string)$resident_id;
            $mysqli->begin_transaction();
            try {
                $stmt1 = $mysqli->prepare("
                    INSERT INTO res_password_history (res_id, old_password, change_date)
                    VALUES (?, ?, NOW())
                ");
                $stmt1->bind_param("ss", $resident_id_str, $current_hash);
                $stmt1->execute();
                $stmt1->close();

                $mysqli->query("
                    DELETE FROM res_password_history 
                     WHERE res_id = '{$mysqli->real_escape_string($resident_id_str)}'
                       AND id NOT IN (
                         SELECT id FROM (
                           SELECT id FROM res_password_history
                            WHERE res_id = '{$mysqli->real_escape_string($resident_id_str)}'
                            ORDER BY change_date DESC
                            LIMIT 10
                         ) t
                       )
                ");

                $stmt2 = $mysqli->prepare("
                    UPDATE residents
                       SET password = ?, res_pass_change = 1,
                           temp_password = NULL,
                           pass_updated_at = NOW()
                     WHERE id = ?
                ");
                $stmt2->bind_param("si", $new_hash, $resident_id);
                $stmt2->execute();
                $stmt2->close();

                $mysqli->commit();

                // ✅ LOGIN audit when bypassing OTP
                try { $trigger->isLogin(6, $resident_id); } catch (\Throwable $e) {}

                unset($_SESSION['twofa'], $_SESSION['pending_pw_change'], $_SESSION['pw_change_guard']);
                session_regenerate_id(true);

                $_SESSION['flash_info'] = "Password changed successfully.";
                header('Location: ' . $redirects['homepage']);
                exit;
            } catch (Throwable $e) {
                $mysqli->rollback();
                $error_message = 'Error finalizing password change: ' . $e->getMessage();
            }
        }

        // Otherwise → with email → send OTP
        $otpRaw  = 'BUGO-' . random_int(100000, 999999);
        $otpHash = password_hash($otpRaw, PASSWORD_DEFAULT);

        $_SESSION['twofa'] = [
            'purpose'    => 'password_change',
            'res_id'     => $resident_id,
            'email'      => $residentEmail ?? '',
            'code_hash'  => $otpHash,
            'expires'    => time() + 300,
            'attempts'   => 0,
            'last_send'  => time(),
            'lock_until' => 0,
        ];

        $_SESSION['pending_pw_change'] = [
            'resident_id'  => $resident_id,
            'new_hash'     => $new_hash,
            'current_hash' => $current_hash,
        ];

        session_regenerate_id(true);

        try {
            send_cp_otp_mail($residentEmail, $residentUsername ?: 'Resident', $otpRaw);
            $_SESSION['flash_info'] = "We sent a verification code to your email.";
            header('Location: ' . $redirects['cp_2fa']);
            exit;
        } catch (\Throwable $e) {
            unset($_SESSION['twofa'], $_SESSION['pending_pw_change']);
            session_regenerate_id(true);
            $error_message = "We couldn't send the verification email. Please try again.";
        }
    }

    $_SESSION['cp_fail'] = $error_message ? ((int)$_SESSION['cp_fail'] + 1) : 0;
    $_SESSION['cp_last'] = time();
}

$mysqli->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Change Password</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta http-equiv="Cache-Control" content="no-store" />
  <meta http-equiv="Pragma" content="no-cache" />
  <meta http-equiv="Expires" content="0" />
  <link rel="icon" type="image/png" href="assets/logo/logo.png">
  <link href="https://maxcdn.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <style>
    .requirement { color: gray; }
    .requirement.valid { color: green; }
    .requirement.invalid { color: red; }
  </style>
</head>
<body>
<div class="container mt-5">
  <h2 class="text-center">Change Password</h2>

  <div class="alert alert-info">
    Use at least 8 characters with uppercase, lowercase, a number, and a special character.
  </div>

  <?php if (!empty($error_message)): ?>
    <div class="alert alert-danger alert-dismissible fade show" id="errorMessage">
      <?= htmlspecialchars($error_message, ENT_QUOTES, 'UTF-8'); ?>
    </div>
  <?php endif; ?>

  <form method="POST" action="" autocomplete="off" novalidate>
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">

    <div class="form-group">
      <label for="current_password">Current Password</label>
      <div class="input-group">
        <input type="password" class="form-control" id="current_password" name="current_password" required autocomplete="current-password">
        <div class="input-group-append">
          <button class="btn btn-outline-secondary toggle-password" type="button"
                  data-target="#current_password" aria-label="Show password">
            <i class="bi bi-eye"></i>
          </button>
        </div>
      </div>
    </div>

    <div class="form-group">
      <label for="new_password">New Password</label>
      <div class="input-group">
        <input type="password" class="form-control" id="new_password" name="new_password" required autocomplete="new-password">
        <div class="input-group-append">
          <button class="btn btn-outline-secondary toggle-password" type="button"
                  data-target="#new_password" aria-label="Show password">
            <i class="bi bi-eye"></i>
          </button>
        </div>
      </div>
      <div class="mt-2">
        <small class="requirement" id="length">At least 8 characters</small><br>
        <small class="requirement" id="uppercase">At least one uppercase letter</small><br>
        <small class="requirement" id="lowercase">At least one lowercase letter</small><br>
        <small class="requirement" id="number">At least one number</small><br>
        <small class="requirement" id="special">At least one special character</small>
      </div>
    </div>

    <div class="form-group">
      <label for="confirm_password">Confirm New Password</label>
      <div class="input-group">
        <input type="password" class="form-control" id="confirm_password" name="confirm_password" required autocomplete="new-password">
        <div class="input-group-append">
          <button class="btn btn-outline-secondary toggle-password" type="button"
                  data-target="#confirm_password" aria-label="Show password">
            <i class="bi bi-eye"></i>
          </button>
        </div>
      </div>
    </div>

    <button type="submit" class="btn btn-primary">Send Verification Code</button>
    <a class="btn btn-secondary" href="<?= htmlspecialchars($logoutUrl, ENT_QUOTES, 'UTF-8') ?>">Cancel</a>
  </form>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
$(function () {
  // Strength meter highlights
  $('#new_password').on('input', function () {
    const p = $(this).val();
    $('#length').toggleClass('valid', p.length >= 8).toggleClass('invalid', p.length < 8);
    $('#uppercase').toggleClass('valid', /[A-Z]/.test(p)).toggleClass('invalid', !/[A-Z]/.test(p));
    $('#lowercase').toggleClass('valid', /[a-z]/.test(p)).toggleClass('invalid', !/[a-z]/.test(p));
    $('#number').toggleClass('valid', /\d/.test(p)).toggleClass('invalid', !/\d/.test(p));
    $('#special').toggleClass('valid', /[^\w\s]/.test(p)).toggleClass('invalid', !/[^\w\s]/.test(p));
  });

  // Fade error message if present
  if ($("#errorMessage").length) {
    setTimeout(() => $("#errorMessage").fadeOut("slow"), 2500);
  }

  // Eye toggle for all password fields
  $(document).on('click', '.toggle-password', function () {
    const $btn = $(this);
    const $input = $($btn.data('target'));
    const hidden = $input.attr('type') === 'password';

    $input.attr('type', hidden ? 'text' : 'password');
    $btn.attr('aria-label', hidden ? 'Hide password' : 'Show password');
    $btn.find('i').toggleClass('bi-eye bi-eye-slash');
  });
});
</script>

<!-- Robust back-button trap using the same absolute logout URL -->
<script>
  const logoutUrl = <?= json_encode($logoutUrl) ?>;
  history.replaceState({pwflow:true}, '', location.href);
  history.pushState({pwflow:true}, '', location.href + '#guard');
  window.addEventListener('popstate', function () {
    location.assign(logoutUrl);
  });
</script>
</body>
</html>
