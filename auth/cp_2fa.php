<?php
declare(strict_types=1);

require_once __DIR__ . '/../security/security.php'; // session/cookie hardening
require_once __DIR__ . '/../include/connection.php';
require_once __DIR__ . '/../include/encryption.php';
require_once __DIR__ . '/../include/redirects.php';
require_once __DIR__ . '/../logs/logs_trig.php';   // ✅ trigger include
enforce_pending_password_change();
session_regenerate_strict();

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$mysqli   = db_connection();
$trigger  = new Trigger(); // ✅ instantiate Trigger
if ($mysqli->connect_error) {
    http_response_code(500);
    exit('Database error.');
}

$twofa   = $_SESSION['twofa'] ?? null;
$pending = $_SESSION['pending_pw_change'] ?? null;
$error   = '';
$info    = '';
$now     = time();

// --- AUTO-FINALIZE if no valid email ---
if ($twofa && $pending && (empty($twofa['email']) || !filter_var($twofa['email'], FILTER_VALIDATE_EMAIL))) {
    $resident_id  = (int)$pending['resident_id'];
    $new_hash     = (string)$pending['new_hash'];
    $current_hash = (string)$pending['current_hash'];
    $res_id_str   = (string)$resident_id;

    $mysqli->begin_transaction();
    try {
        // Insert old hash into history
        $stmt1 = $mysqli->prepare("
            INSERT INTO res_password_history (res_id, old_password, change_date)
            VALUES (?, ?, NOW())
        ");
        $stmt1->bind_param("ss", $res_id_str, $current_hash);
        $stmt1->execute();
        $stmt1->close();

        // Prune to last 10
        $mysqli->query("
            DELETE FROM res_password_history 
            WHERE res_id = '{$mysqli->real_escape_string($res_id_str)}'
              AND id NOT IN (
                SELECT id FROM (
                    SELECT id FROM res_password_history
                    WHERE res_id = '{$mysqli->real_escape_string($res_id_str)}'
                    ORDER BY change_date DESC
                    LIMIT 10
                ) t
              )
        ");

        // Update residents: new hash, flag changed, clear temp_password
        $stmt2 = $mysqli->prepare("
            UPDATE residents
               SET password = ?, res_pass_change = 1,
                   temp_password = NULL, pass_updated_at = NOW()
             WHERE id = ?
        ");
        $stmt2->bind_param("si", $new_hash, $resident_id);
        $stmt2->execute();
        $stmt2->close();

        $mysqli->commit();
        try { $trigger->isLogin(6, $resident_id); } catch (Throwable $e) {}

        unset($_SESSION['twofa'], $_SESSION['pending_pw_change'], $_SESSION['pw_change_guard']);
        session_regenerate_id(true);

        header('Location: ' . $redirects['homepage']);
        exit;
    } catch (Throwable $e) {
        $mysqli->rollback();
        $error = 'Error finalizing password change: ' . $e->getMessage();
    }
}

// --- Normal 2FA flow ---
if (!$twofa || !$pending || ($twofa['purpose'] ?? '') !== 'password_change') {
    $error = 'No pending password change found. Please restart the process.';
}

if (!$error && !empty($twofa['lock_until']) && $now < (int)$twofa['lock_until']) {
    $remain = (int)$twofa['lock_until'] - $now;
    $error = "Too many attempts. Try again in {$remain} seconds.";
}

// Handle POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($info)) {
    if (isset($_POST['resend'])) {
        // Resend with cooldown
        $cooldown = 45;
        if (!empty($twofa['last_send']) && time() - (int)$twofa['last_send'] < $cooldown) {
            $error = 'Please wait a bit before requesting another code.';
        } else {
            $otpRaw = 'BUGO-' . random_int(100000, 999999);
            $_SESSION['twofa']['code_hash'] = password_hash($otpRaw, PASSWORD_DEFAULT);
            $_SESSION['twofa']['expires']   = time() + 300; // 5 mins
            $_SESSION['twofa']['attempts']  = 0;
            $_SESSION['twofa']['last_send'] = time();

            // Send again via PHPMailer using cPanel SMTP
            require_once __DIR__ . '/../vendor/autoload.php';

            try {
                // cPanel mailbox credentials
                $smtpHost = 'mail.bugoportal.site';
                $smtpUser = 'admin@bugoportal.site';
                $smtpPass = 'Jayacop@100';

                // helper to build the message contents
                $buildMessage = function (PHPMailer $m) use ($twofa, $smtpUser, $otpRaw) {
                    $to = $twofa['email'] ?? '';
                    if (!$to) {
                        throw new \RuntimeException('No recipient email on file.');
                    }
                    $m->setFrom($smtpUser, 'Barangay Bugo');
                    $m->addAddress($to);
                    // $m->addBCC($smtpUser); // optional while testing
                    $m->addReplyTo($smtpUser, 'Barangay Bugo');

                    $m->isHTML(true);
                    $m->Subject = 'Barangay Bugo 2FA Code (Resent)';
                    $m->Body    = "
                        <p>Your new verification code is:</p>
                        <h2 style='color:#0d6efd;margin:8px 0;'>{$otpRaw}</h2>
                        <p>This code is valid for 5 minutes.</p>
                    ";
                    $m->AltBody = "Your new verification code is: {$otpRaw}\nThis code is valid for 5 minutes.";
                    $m->CharSet = 'UTF-8';
                    $m->Hostname = 'bugoportal.site';
                    $m->Sender   = $smtpUser; // envelope-from
                };

                // Try SMTPS 465 first
                $mail = new PHPMailer(true);
                $mail->isSMTP();
                $mail->Host          = $smtpHost;
                $mail->SMTPAuth      = true;
                $mail->Username      = $smtpUser;
                $mail->Password      = $smtpPass;
                $mail->SMTPSecure    = PHPMailer::ENCRYPTION_SMTPS; // 465
                $mail->Port          = 465;
                $mail->Timeout       = 12;
                $mail->SMTPAutoTLS   = true;
                $mail->SMTPKeepAlive = false;
                // (Optional) relax TLS checks on shared hosting
                $mail->SMTPOptions = ['ssl' => [
                    'verify_peer'       => false,
                    'verify_peer_name'  => false,
                    'allow_self_signed' => true,
                ]];
                $buildMessage($mail);
                $mail->send();

                $info = 'We resent a new code to your email.';
            } catch (\Throwable $e1) {
                // Fallback: STARTTLS 587
                try {
                    $mail = new PHPMailer(true);
                    $mail->isSMTP();
                    $mail->Host          = $smtpHost;
                    $mail->SMTPAuth      = true;
                    $mail->Username      = $smtpUser;
                    $mail->Password      = $smtpPass;
                    $mail->SMTPSecure    = PHPMailer::ENCRYPTION_STARTTLS; // 587
                    $mail->Port          = 587;
                    $mail->Timeout       = 12;
                    $mail->SMTPAutoTLS   = true;
                    $mail->SMTPKeepAlive = false;
                    $mail->SMTPOptions = ['ssl' => [
                        'verify_peer'       => false,
                        'verify_peer_name'  => false,
                        'allow_self_signed' => true,
                    ]];
                    $buildMessage($mail);
                    $mail->send();

                    $info = 'We resent a new code to your email.';
                } catch (\Throwable $e2) {
                    $error = 'Failed to resend the code. Please try again.';
                }
            }
        }
    } elseif (isset($_POST['code'])) {
        if (!$error) {
            $code = strtoupper(trim($_POST['code'] ?? ''));
            if ($now > (int)$twofa['expires']) {
                $error = 'Code expired. Please request a new one.';
            } elseif ($code === '') {
                $error = 'Please enter the code.';
            } else {
                if (strpos($code, 'BUGO-') !== 0) {
                    $code = 'BUGO-' . preg_replace('/\D+/', '', $code);
                }

                if (!password_verify($code, $twofa['code_hash'])) {
                    $_SESSION['twofa']['attempts'] = (int)$twofa['attempts'] + 1;
                    if ($_SESSION['twofa']['attempts'] >= 5) {
                        $_SESSION['twofa']['lock_until'] = time() + 120; // 2 mins lock
                    }
                    $error = 'Invalid code.';
                } else {
                    // ✅ Finalize password change atomically
                    $resident_id  = (int)$pending['resident_id'];
                    $new_hash     = (string)$pending['new_hash'];
                    $current_hash = (string)$pending['current_hash'];

                    $mysqli->begin_transaction();
                    try {
                        // 1) Insert old into history
                        $stmt1 = $mysqli->prepare("
                            INSERT INTO res_password_history (res_id, old_password, change_date)
                            VALUES (?, ?, NOW())
                        ");
                        $res_id_str = (string)$resident_id; // schema uses VARCHAR
                        $stmt1->bind_param("ss", $res_id_str, $current_hash);
                        $stmt1->execute();
                        $stmt1->close();

                        // 2) Prune to last 10 (id column exists)
                        $mysqli->query("
                            DELETE FROM res_password_history 
                            WHERE res_id = '{$mysqli->real_escape_string($res_id_str)}'
                              AND id NOT IN (
                                SELECT id FROM (
                                    SELECT id FROM res_password_history
                                    WHERE res_id = '{$mysqli->real_escape_string($res_id_str)}'
                                    ORDER BY change_date DESC
                                    LIMIT 10
                                ) t
                              )
                        ");

                        // 3) Update main password (adapt to available columns) + NULL temp_password
                        $hasUpdatedAt = false;
                        $hasResPassChange = false;
                        $hasPassUpdatedAt = false;

                        $colCheck = "
                          SELECT COLUMN_NAME
                          FROM INFORMATION_SCHEMA.COLUMNS
                          WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'residents'
                            AND COLUMN_NAME IN ('updated_at','res_pass_change','pass_updated_at')
                        ";
                        if ($resC = $mysqli->query($colCheck)) {
                            while ($r = $resC->fetch_assoc()) {
                                if ($r['COLUMN_NAME'] === 'updated_at')      $hasUpdatedAt = true;
                                if ($r['COLUMN_NAME'] === 'res_pass_change') $hasResPassChange = true;
                                if ($r['COLUMN_NAME'] === 'pass_updated_at') $hasPassUpdatedAt = true;
                            }
                            $resC->close();
                        }

                        if ($hasResPassChange && $hasUpdatedAt) {
                            $stmt2 = $mysqli->prepare("
                                UPDATE residents 
                                SET password = ?, res_pass_change = 1, updated_at = NOW(), temp_password = NULL 
                                WHERE id = ?
                            ");
                        } elseif ($hasResPassChange && $hasPassUpdatedAt) {
                            $stmt2 = $mysqli->prepare("
                                UPDATE residents 
                                SET password = ?, res_pass_change = 1, pass_updated_at = NOW(), temp_password = NULL 
                                WHERE id = ?
                            ");
                        } elseif ($hasPassUpdatedAt) {
                            $stmt2 = $mysqli->prepare("
                                UPDATE residents 
                                SET password = ?, pass_updated_at = NOW(), temp_password = NULL 
                                WHERE id = ?
                            ");
                        } elseif ($hasResPassChange) {
                            $stmt2 = $mysqli->prepare("
                                UPDATE residents 
                                SET password = ?, res_pass_change = 1, temp_password = NULL 
                                WHERE id = ?
                            ");
                        } else {
                            $stmt2 = $mysqli->prepare("
                                UPDATE residents 
                                SET password = ?, temp_password = NULL 
                                WHERE id = ?
                            ");
                        }
                        $stmt2->bind_param("si", $new_hash, $resident_id);
                        $stmt2->execute();
                        $stmt2->close();

                        // 4) Invalidate remember tokens — detect the correct column first
                        $col = null;
                        $sqlCol = "
                            SELECT COLUMN_NAME
                            FROM INFORMATION_SCHEMA.COLUMNS
                            WHERE TABLE_SCHEMA = DATABASE()
                              AND TABLE_NAME = 'login_tokens'
                              AND COLUMN_NAME IN ('resident_id','user_id','employee_id','account_id')
                            LIMIT 1
                        ";
                        if ($res = $mysqli->query($sqlCol)) {
                            if ($row = $res->fetch_row()) {
                                $col = $row[0];
                            }
                            $res->close();
                        }
                        if ($col) {
                            $q = "DELETE FROM login_tokens WHERE `$col` = ?";
                            $stmtTok = $mysqli->prepare($q);
                            $stmtTok->bind_param("i", $resident_id);
                            $stmtTok->execute();
                            $stmtTok->close();
                        }

                        $mysqli->commit();
                        try { $trigger->isLogin(6, $resident_id); } catch (Throwable $e) {}

                        unset($_SESSION['twofa'], $_SESSION['pending_pw_change'], $_SESSION['pw_change_guard']);
                        session_regenerate_id(true);
                        $info = "Password changed successfully.";
                    } catch (Throwable $e) {
                        $mysqli->rollback();
                        $error = 'Error finalizing password change: ' . $e->getMessage();
                    }
                }
            }
        }
    }
}
?>

<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Verify Code</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="icon" type="image/png" href="/assets/logo/logo.png">
  <link rel="stylesheet" href="/auth/assets/cp_2fa.css">
</head>
<body>
  <div class="card">
    <div class="header">
      <img class="logo" src="/assets/logo/logo.png" alt="Logo" onerror="this.style.display='none'">
      <div class="title">Verify your email</div>
    </div>
    <div class="sub">Enter the 6‑digit code we sent to your email to confirm your password change.</div>

    <?php if (!empty($error)): ?>
      <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
      <script>Swal.fire({icon:'error',title:'Oops',text:<?= json_encode($error) ?>});</script>
    <?php endif; ?>

    <?php if (empty($info)): ?>
      <form id="verifyForm" method="post" autocomplete="off">
        <input type="hidden" name="code" id="fullCode">
        <div style="display:flex;justify-content:center;align-items:center">
          <span class="prefix">BUGO‑</span>
          <div class="otp-wrap" id="otpWrap">
            <input class="otp-input" type="text" inputmode="numeric" maxlength="1" pattern="[0-9]*">
            <input class="otp-input" type="text" inputmode="numeric" maxlength="1" pattern="[0-9]*">
            <input class="otp-input" type="text" inputmode="numeric" maxlength="1" pattern="[0-9]*">
            <input class="otp-input" type="text" inputmode="numeric" maxlength="1" pattern="[0-9]*">
            <input class="otp-input" type="text" inputmode="numeric" maxlength="1" pattern="[0-9]*">
            <input class="otp-input" type="text" inputmode="numeric" maxlength="1" pattern="[0-9]*">
          </div>
        </div>
        <div class="actions">
          <button id="submitBtn" type="submit" class="btn btn-primary">Verify & Change Password</button>
          <button id="resendBtn" name="resend" value="1" class="btn" type="submit">Resend code</button>
        </div>
        <div class="muted" id="hint">Tip: you can paste the whole code (e.g., <b>BUGO‑123456</b>)</div>
      </form>
    <?php endif; ?>
  </div>
  <script>
        const inputs = Array.from(document.querySelectorAll('.otp-input'));
    const hidden = document.getElementById('fullCode');
    const form = document.getElementById('verifyForm');
    const submitBtn = document.getElementById('submitBtn');
    const resendBtn = document.getElementById('resendBtn');

    function syncHidden() {
      const digits = inputs.map(i => i.value.replace(/\D/g,'')).join('').slice(0,6);
      hidden.value = digits ? ('BUGO-' + digits) : '';
      return digits.length === 6;
    }

    inputs.forEach((inp, idx) => {
      inp.addEventListener('input', e => {
        e.target.value = e.target.value.replace(/\D/g,'').slice(0,1);
        if (e.target.value && idx < inputs.length - 1) inputs[idx+1].focus();
        syncHidden();
      });
      inp.addEventListener('keydown', e => {
        if (e.key === 'Backspace' && !e.target.value && idx > 0) {
          inputs[idx-1].focus();
        }
      });
      inp.addEventListener('paste', e => {
        const text = (e.clipboardData || window.clipboardData).getData('text');
        if (!text) return;
        e.preventDefault();
        let m = text.toUpperCase().match(/BUGO[-–—]?(\d{6})/); // accept -, – , —
        const digits = m ? m[1] : text.replace(/\D/g,'').slice(0,6);
        for (let i=0;i<inputs.length;i++) inputs[i].value = digits[i] || '';
        inputs[Math.min(digits.length,5)].focus();
        syncHidden();
      });
    });

    form?.addEventListener('submit', () => {
      submitBtn?.setAttribute('disabled','disabled');
      submitBtn.textContent = 'Verifying...';
    });
    // Resend cooldown on client (doesn't replace server check)
    <?php
      $cool = 45;
      $remaining = 0;
      if (!empty($twofa['last_send'])) {
          $delta = time() - (int)$twofa['last_send'];
          $remaining = max(0, $cool - $delta);
      }
    ?>
    let remaining = <?= (int)$remaining ?>;
    const origResend = resendBtn ? resendBtn.textContent : '';
    function tick() {
      if (!resendBtn) return;
      if (remaining > 0) {
        resendBtn.setAttribute('disabled','disabled');
        resendBtn.textContent = `Resend in ${remaining}s`;
        remaining--;
        setTimeout(tick, 1000);
      } else {
        resendBtn.removeAttribute('disabled');
        resendBtn.textContent = origResend || 'Resend code';
      }
    }
    if (remaining > 0) tick();
  </script>

  <?php if (!empty($info)): ?>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
      Swal.fire({
        icon: 'success',
        title: 'Password Changed!',
        text: 'Your password was updated successfully.',
        showConfirmButton: false,
        timer: 2000,
        timerProgressBar: true,
        willOpen: () => {
          const b = document.body;
          b.classList.add('swal2-dark'); // optional
        },
        didClose: () => {
          // Redirect to dashboard after 2s
          window.location.href = "<?= htmlspecialchars($redirects['homepage'], ENT_QUOTES, 'UTF-8') ?>";
        }
      });
    </script>
  <?php endif; ?>

  <?php if (!empty($info) || !empty($error)): ?>
    <!-- prevent showing the stale form after post when using back -->
    <script>if ('scrollRestoration' in history) history.scrollRestoration = 'manual';</script>
  <?php endif; ?>
  <script>
  // Ask before leaving with Back; if they agree, we clear the guard (via ?cancel=1)
  history.pushState(null, '', location.href);
  window.addEventListener('popstate', function () {
    if (confirm('You haven’t finished changing your password. Leave this step?')) {
      window.location.href = '?cancel=1';
    } else {
      history.pushState(null, '', location.href);
    }
  });

  // Avoid browser BFCache showing old page on return
  window.addEventListener('pageshow', e => { if (e.persisted) location.reload(); });
</script>
</body>
</html>
