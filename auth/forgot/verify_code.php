<?php
// auth/forgot/verify_code.php
declare(strict_types=1);

require_once __DIR__ . '/../../security/security.php'; // csrf + hardening (if available)
security_no_cache();
session_regenerate_strict();

if (!isset($_SESSION)) session_start();

if (!isset($_SESSION['reset_code']) || !isset($_SESSION['reset_email'])) {
    header('Location: forgot_password.php');
    exit;
}

$error = '';
$ok = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF (graceful if helpers don’t exist)
    $token = $_POST['csrf_token'] ?? '';
    if (function_exists('validate_csrf_token') && !validate_csrf_token($token)) {
        $error = 'Security check failed. Please refresh and try again.';
    } else {
        // Normalize the code: accept "BUGO-123456" or "123456"
        $entered = strtoupper(trim($_POST['code'] ?? ''));
        if (strpos($entered, 'BUGO-') !== 0) {
            $digits = preg_replace('/\D+/', '', $entered);
            $entered = 'BUGO-' . substr($digits, 0, 6);
        }
        $sessionCode = (string)$_SESSION['reset_code'];
        // If stored code was digits only, normalize for compare too
        if (strpos($sessionCode, 'BUGO-') !== 0) {
            $sessionCode = 'BUGO-' . substr(preg_replace('/\D+/', '', $sessionCode), 0, 6);
        }

        if (hash_equals($sessionCode, (string)$entered)) {
            $_SESSION['code_verified'] = true;
            $ok = true;
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

  <!-- Reuse the same style as login_2fa / cp_2fa -->
  <link rel="stylesheet" href="/auth/assets/cp_2fa.css">
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body>

<?php if ($ok): ?>
<script>
  Swal.fire({
    icon: 'success',
    title: 'Code verified',
    text: 'Redirecting to reset your password…',
    timer: 1500,
    timerProgressBar: true,
    showConfirmButton: false
  }).then(() => {
    window.location.href = 'reset_password.php';
  });
</script>
<?php endif; ?>

<div class="card" <?= $ok ? 'style="display:none"' : '' ?>>
  <div class="header">
    <img class="logo" src="/assets/logo/logo.png" alt="Logo" onerror="this.style.display='none'">
    <div class="title">Verify your email</div>
  </div>
  <div class="sub">Enter the 6‑digit code we sent to your email to continue.</div>

  <?php if (!empty($error)): ?>
    <script>Swal.fire({icon:'error',title:'Oops',text:<?= json_encode($error) ?>});</script>
  <?php endif; ?>

  <form id="verifyForm" method="post" autocomplete="off">
    <?php if (function_exists('csrf_input')) echo csrf_input(); ?>
    <input type="hidden" name="code" id="fullCode">

    <div style="display:flex;justify-content:center;align-items:center">
      <!-- <span class="prefix">BUGO‑</span> -->
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
      <button id="submitBtn" type="submit" class="btn btn-primary">Verify Code</button>
    </div>

    <div class="muted" id="hint">Tip: you can paste the whole code (e.g., <b>123456</b>)</div>
  </form>
</div>

<script>
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

  form?.addEventListener('submit', (e) => {
    if (!syncHidden()) {
      e.preventDefault();
      Swal.fire({icon:'warning',title:'Incomplete',text:'Please enter all 6 digits.'});
      return;
    }
    submitBtn?.setAttribute('disabled','disabled');
    submitBtn.textContent = 'Verifying…';
  });

  if ('scrollRestoration' in history) history.scrollRestoration = 'manual';
  window.addEventListener('pageshow', e => { if (e.persisted) location.reload(); });
</script>

</body>
</html>