<?php
// auth/forgot/forgot_password.php
declare(strict_types=1);

if (!isset($_SESSION)) session_start();

require_once __DIR__ . '/../../include/connection.php';
$mysqli = db_connection();

require __DIR__ . '/../../vendor/autoload.php'; // PHPMailer
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$error   = '';
$success = '';

/**
 * Send password reset code via cPanel mailbox SMTP with fallbacks.
 */
function send_reset_code_mail(string $toEmail, string $code, string $toName = ''): void {
    $mailboxUser = 'admin@bugoportal.site';
    $mailboxPass = 'Jayacop@100';             // ⚠️ your real cPanel mailbox password
    $smtpHost    = 'mail.bugoportal.site';

    $safeName = htmlspecialchars($toName ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $safeCode = htmlspecialchars($code, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

    $buildMessage = function(PHPMailer $m) use ($toEmail, $safeName, $safeCode, $mailboxUser) {
        $m->setFrom($mailboxUser, 'Barangay Bugo');
        $m->addAddress($toEmail, $safeName);
        $m->addBCC($mailboxUser); // TEMP: verify delivery; remove later
        $m->isHTML(true);
        $m->Subject = 'Password Reset Code';
        $m->Body    = "<p>Hello <strong>{$safeName}</strong>,</p>
                       <p>Your password reset code is:</p>
                       <h2 style='color:#0d6efd;'>{$safeCode}</h2>
                       <p>This code is valid for 10 minutes.</p>
                       <br><p>Thank you,<br>Barangay Bugo Portal</p>";
        $m->AltBody = "Your password reset code is: {$safeCode}\nThis code is valid for 10 minutes.";
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
        $mail->Timeout       = 10;     // seconds
        $mail->SMTPAutoTLS   = true;
        $mail->SMTPKeepAlive = false;

        // TEMP: relax TLS checks if cert/hostname mismatch (remove once DNS/SSL is correct)
        $mail->SMTPOptions = [
            'ssl' => [
                'verify_peer'       => false,
                'verify_peer_name'  => false,
                'allow_self_signed' => true,
            ]
        ];

        if ($mode === 'ssl') {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;     // 465
        } else {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;  // 587
        }

        // Optional SMTP dialog logging while debugging:
        // $mail->SMTPDebug   = 2;
        // $mail->Debugoutput = function($str,$lvl){ error_log("SMTP[$lvl] $str"); };

        $buildMessage($mail);
        $mail->send();
    };

    error_log("RESET: sending code={$code} to {$toEmail}");

    try {
        error_log('RESET: trying SMTP SSL 465…');
        $attempt('ssl', 465);
        error_log('RESET: sent via 465 SSL');
    } catch (\Throwable $e1) {
        error_log('RESET: 465 failed: '.$e1->getMessage());

        try {
            error_log('RESET: trying SMTP STARTTLS 587…');
            $attempt('tls', 587);
            error_log('RESET: sent via 587 STARTTLS');
        } catch (\Throwable $e2) {
            error_log('RESET: 587 failed: '.$e2->getMessage());

            // Last resort: local MTA (sendmail)
            try {
                error_log('RESET: falling back to local sendmail…');
                $fallback = new PHPMailer(true);
                $fallback->isMail();
                $buildMessage($fallback);
                $fallback->send();
                error_log('RESET: sent via local sendmail');
            } catch (\Throwable $e3) {
                error_log('RESET: sendmail failed: '.$e3->getMessage());
                throw new \RuntimeException('Unable to send password reset email right now.');
            }
        }
    } finally {
        // Keep DB connection alive after SMTP attempts (in case they were slow)
        if (isset($GLOBALS['mysqli']) && $GLOBALS['mysqli'] instanceof mysqli) {
            if (!@$GLOBALS['mysqli']->ping() && function_exists('db_connection')) {
                $GLOBALS['mysqli'] = db_connection();
            }
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Use a plain trim + simple validation for email
    $email = trim($_POST['email'] ?? '');
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } else {
        $stmt = $mysqli->prepare("SELECT id, first_name FROM residents WHERE email = ? AND resident_delete_status = 0");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result && $result->num_rows === 1) {
            $row = $result->fetch_assoc();
            $resident_id = (int)$row['id'];
            $first_name  = (string)($row['first_name'] ?? '');

            // 6-digit numeric code
            $code = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);

            // Store in session for verification step
            $_SESSION['reset_code']   = $code;
            $_SESSION['reset_email']  = $email;
            $_SESSION['reset_issued'] = time(); // optional: use to enforce expiration

            try {
                send_reset_code_mail($email, $code, $first_name);
                $success = 'Verification code sent. Please check your email.';
            } catch (\Throwable $e) {
                $error = 'Email could not be sent. Please try again in a moment.';
            }
        } else {
            $error = 'Email not found.';
        }

        $stmt->close();
    }

    $mysqli->close();
}
?>

<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Forgot Password</title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <link rel="icon" type="image/png" href="/bugo-resident-side/assets/logo/logo.png">

  <!-- Reuse the same card styling as your 2FA screen -->
  <link rel="stylesheet" href="/auth/assets/cp_2fa.css">

  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body>

<?php if (!empty($error)): ?>
<script>
  Swal.fire({ icon:'error', title:'Oops', text: <?= json_encode($error) ?> });
</script>
<?php endif; ?>

<?php if (!empty($success)): ?>
<script>
  Swal.fire({
    icon: 'success',
    title: 'Sent!',
    text: <?= json_encode($success) ?>,
    timer: 1500,
    timerProgressBar: true,
    showConfirmButton: false
  }).then(() => {
    window.location.href = 'verify_code.php';
  });
</script>
<?php endif; ?>

<div class="card">
  <div class="header">
    <img class="logo" src="/bugo-resident-side/assets/logo/logo.png" alt="Logo" onerror="this.style.display='none'">
    <div class="title">Forgot your password?</div>
  </div>
  <div class="sub">Enter your registered email. We’ll send a 6‑digit code to verify it’s really you.</div>

  <form method="post" autocomplete="off">
    <div class="form-group" style="margin-top:16px">
      <label class="label" for="email">Email address</label>
      <input
        id="email"
        class="otp-input"
        style="width:100%; padding:12px 14px; letter-spacing:0; text-align:left"
        type="email"
        name="email"
        inputmode="email"
        placeholder="you@example.com"
        required
      >
    </div>

    <div class="actions" style="margin-top:18px">
      <button type="submit" class="btn btn-primary">Send verification code</button>
      <a class="btn" href="../../index.php" style="margin-left:8px">Back to Login</a>
    </div>

    <div class="muted" style="margin-top:10px">
      Tip: check your spam/junk if you don’t see the email in a minute.
    </div>
  </form>
</div>

</body>
</html>
