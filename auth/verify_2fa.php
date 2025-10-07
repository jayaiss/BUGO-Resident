<?php
declare(strict_types=1);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../errors.log');
error_reporting(E_ALL);


require_once __DIR__ . '/../security/security.php'; // csrf helpers + hardening
security_no_cache();
session_regenerate_strict();

require_once __DIR__ . '/../include/redirects.php';
require_once __DIR__ . '/../include/encryption.php';

// ▼ Remember-me helpers + DB
require_once __DIR__ . '/../security/remember.php';
require_once __DIR__ . '/../include/connection.php';
require_once __DIR__ . '/../logs/logs_trig.php';
$trigger = new Trigger();
if (!isset($mysqli) || $mysqli->connect_error) {
    http_response_code(500);
    exit('Database connection failed.');
}

// Must come from the pre-login flow
if (!isset($_SESSION['temp_user_id']) || !isset($_SESSION['2fa_code'])) {
header('Location: /index.php');
$homeUrl = $redirects['homepage'] ?? ('/index_Admin.php?page=' . urlencode(encrypt('homepage')));
    exit;
}

$error = '';
$showSuccess = false;

// For redirect after success; fall back to encrypted homepage param
$homeUrl     = $redirects['homepage'] ?? ('/index_Admin.php?page=' . urlencode(encrypt('homepage')));
$redirectUrl = $homeUrl; // default; may be overridden after successful 2FA

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!validate_csrf_token($token)) {
        $error = 'Security check failed. Please refresh and try again.';
    } else {
        // Accept "BUGO-123456" (dash variations) or raw "123456"
        $entered = strtoupper(trim($_POST['code'] ?? ''));
        if (strpos($entered, 'BUGO-') !== 0) {
            if (preg_match('/BUGO[-–—]?(\d{6})/i', $entered, $m)) {
                $entered = 'BUGO-' . $m[1];
            } else {
                $digits  = preg_replace('/\D+/', '', $entered);
                $entered = 'BUGO-' . substr($digits, 0, 6);
            }
        }

        if (hash_equals((string)$_SESSION['2fa_code'], (string)$entered)) {
            // Finalize login
            $_SESSION['id'] = (int)$_SESSION['temp_user_id'];
            session_regenerate_id(true);
            $trigger->isLogin(6, $_SESSION['id']);

            // ===== (B) Always mint a fresh token if either condition is true =====
            $shouldRemember =
                !empty($_SESSION['remember_me_request']) ||
                !empty($_SESSION['from_remember_cookie']);

            if ($shouldRemember) {
                // Clean up any currently-used selector (avoid stale rows)
                if (!empty($_COOKIE[REMEMBER_COOKIE_NAME]) && strpos($_COOKIE[REMEMBER_COOKIE_NAME], ':') !== false) {
                    [$sel] = explode(':', $_COOKIE[REMEMBER_COOKIE_NAME], 2);
                    if (!empty($sel)) {
                        try { remember_delete_by_selector($mysqli, $sel); } catch (\Throwable $e) { /* log if desired */ }
                    }
                }
                // Issue a fresh 30-day token, bound to current UA
                $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
                try { remember_issue_token($mysqli, (int)$_SESSION['id'], $ua); } catch (\Throwable $e) { /* log if desired */ }
            }

            // Clear remember-me flags regardless
            unset($_SESSION['remember_me_request'], $_SESSION['from_remember_cookie']);

            // Cleanup temp vars
            unset($_SESSION['temp_user_id'], $_SESSION['temp_email'], $_SESSION['2fa_code']);

            // ✅ Decide final landing page (honor "Book now" intent captured during login)
            $target = $_SESSION['post_login_redirect'] ?? '';
            unset($_SESSION['post_login_redirect']); // one-time use

            if ($target === 'schedule_appointment') {
                $redirectUrl = '/index_Admin.php?page=' . urlencode(encrypt('schedule_appointment'));
            } else {
                $redirectUrl = $homeUrl;
            }

            $showSuccess = true;
        } else {
            $error = 'Invalid verification code. Please try again.';
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Verify Code</title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <link rel="icon" type="image/png" href="/assets/logo/logo.png">
  <link rel="stylesheet" href="/auth/assets/cp_2fa.css?=v4">
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body>

<?php if ($showSuccess): ?>
<script>
  Swal.fire({
    icon: 'success',
    title: 'Verification Successful',
    text: 'Redirecting...',
    timer: 1800,
    timerProgressBar: true,
    showConfirmButton: false
  }).then(() => {
    window.location.href = <?= json_encode($redirectUrl) ?>;
  });
</script>
<?php endif; ?>

<div class="card" <?= $showSuccess ? 'style="display:none"' : '' ?>>
  <div class="header">
    <img class="logo" src="/bugo-resident-side/assets/logo/logo.png" alt="Logo" onerror="this.style.display='none'">
    <div class="title">Verify your email</div>
  </div>
  <div class="sub">Enter the 6-digit code we sent to your email to confirm your login.</div>

  <?php if (!empty($error)): ?>
    <script>Swal.fire({icon:'error',title:'Oops',text:<?= json_encode($error) ?>});</script>
  <?php endif; ?>

  <form id="verifyForm" method="post" autocomplete="off">
    <?= csrf_input(); ?>
    <input type="hidden" name="code" id="fullCode">

    <div style="display:flex;justify-content:center;align-items:center">
      <span class="prefix">BUGO-</span>
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
      <button id="submitBtn" type="submit" class="btn btn-primary">Verify & Sign In</button>
    </div>

    <div class="muted" id="hint">Tip: you can paste the whole code (e.g., <b>BUGO-123456</b>)</div>
  </form>
</div>

<script>
  // OTP UX (paste-friendly)
  const inputs = Array.from(document.querySelectorAll('.otp-input'));
  const hidden = document.getElementById('fullCode');
  const form   = document.getElementById('verifyForm');
  const submitBtn = document.getElementById('submitBtn');

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
      if (e.key === 'Backspace' && !e.target.value && idx > 0) inputs[idx-1].focus();
    });
    inp.addEventListener('paste', e => {
      const text = (e.clipboardData || window.clipboardData).getData('text') || '';
      if (!text) return;
      e.preventDefault();
      let m = text.toUpperCase().match(/BUGO[-–—]?(\d{6})/);
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

  // Prevent BFCache from showing completed page
  if ('scrollRestoration' in history) history.scrollRestoration = 'manual';
  window.addEventListener('pageshow', e => { if (e.persisted) location.reload(); });
</script>

</body>
</html>
