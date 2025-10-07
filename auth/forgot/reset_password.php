<?php
// auth/forgot/reset_password.php
declare(strict_types=1);

if (!isset($_SESSION)) session_start();

require_once __DIR__ . '/../../include/connection.php';
$mysqli = db_connection();

// ✅ trigger for audit
require_once __DIR__ . '/../../logs/logs_trig.php';
$trigger = new Trigger();

if (!isset($_SESSION['reset_email']) || empty($_SESSION['code_verified'])) {
    header('Location: forgot_password.php');
    exit;
}

$email   = $_SESSION['reset_email'];
$error   = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $new_password     = trim($_POST['new_password'] ?? '');
    $confirm_password = trim($_POST['confirm_password'] ?? '');

    // ✅ Policy: at least 8 chars, 1 upper, 1 lower, 1 digit, 1 special char
    $password_pattern = '/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^A-Za-z\d]).{8,}$/';

    if ($new_password === '' || $confirm_password === '') {
        $error = 'All fields are required.';
    } elseif (!preg_match($password_pattern, $new_password)) {
        $error = 'Password must be at least 8 characters and include uppercase, lowercase, a number, and a special character.';
    } elseif (!hash_equals($new_password, $confirm_password)) {
        $error = 'Passwords do not match.';
    } else {
        $stmt = $mysqli->prepare("SELECT id, password FROM residents WHERE email = ? AND resident_delete_status = 0");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result && $result->num_rows === 1) {
            $row = $result->fetch_assoc();
            $resident_id             = (int)$row['id'];
            $current_hashed_password = (string)$row['password'];

            if (password_verify($new_password, $current_hashed_password)) {
                $error = 'You cannot reuse an old password.';
            } else {
                // Check history
                $stmt_hist = $mysqli->prepare("SELECT old_password FROM res_password_history WHERE res_id = ?");
                $stmt_hist->bind_param("i", $resident_id);
                $stmt_hist->execute();
                $hist_result = $stmt_hist->get_result();

                while ($hist_row = $hist_result->fetch_assoc()) {
                    if (password_verify($new_password, $hist_row['old_password'])) {
                        $error = 'You cannot reuse an old password.';
                        break;
                    }
                }
                $stmt_hist->close();

                if ($error === '') {
                    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);

                    // Store current to history
                    $stmt_store = $mysqli->prepare("INSERT INTO res_password_history (old_password, res_id) VALUES (?, ?)");
                    $stmt_store->bind_param("si", $current_hashed_password, $resident_id);
                    $stmt_store->execute();
                    $stmt_store->close();

                    // Update to new
                    $stmt_update = $mysqli->prepare("UPDATE residents SET password = ?, res_pass_change = 1 WHERE email = ?");
                    $stmt_update->bind_param("ss", $hashed_password, $email);

                    if ($stmt_update->execute()) {
                        // ✅ Audit: forgot password reset success (use action 12; map it in transform_action_made)
                        try { $trigger->isForgotPasswordVerify(12, $resident_id); } catch (\Throwable $e) {}

                        $success = 'Password reset successfully! Redirecting to login…';
                        unset($_SESSION['reset_email'], $_SESSION['reset_code'], $_SESSION['code_verified']);
                        session_regenerate_id(true);
                        header('Refresh: 2; url=../../index.php');
                    } else {
                        $error = 'Failed to reset password. Please try again.';
                    }
                    $stmt_update->close();
                }
            }
        } else {
            $error = 'Account not found.';
        }

        $stmt?->close();
        $mysqli->close();
    }
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Reset Password</title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <link rel="icon" type="image/png" href="/assets/logo/logo.png">
  <!-- Reuse 2FA card styling -->
  <link rel="stylesheet" href="/auth/assets/cp_2fa.css">
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <style>
    /* small tweak for ultra-narrow devices */
    @media (max-width: 360px){
      .card{ padding: 16px 14px; }
      .actions{ flex-wrap: wrap; }
      .actions .btn{ width: 100%; }
    }
  </style>
</head>
<body>

<?php if ($error): ?>
<script>Swal.fire({icon:'error', title:'Oops', text: <?= json_encode($error) ?>});</script>
<?php endif; ?>

<?php if ($success): ?>
<script>
  Swal.fire({
    icon:'success',
    title:'Success',
    text: <?= json_encode($success) ?>,
    timer: 1500,
    timerProgressBar: true,
    showConfirmButton: false
  });
</script>
<?php endif; ?>

<div class="card">
  <div class="header">
    <img class="logo" src="/assets/logo/logo.png" alt="Logo" onerror="this.style.display='none'">
    <div class="title">Reset your password</div>
  </div>
  <div class="sub">Create a strong password you haven’t used here before.</div>

  <form method="post" autocomplete="off" id="resetForm">
    <div class="form-group" style="margin-top:16px">
      <label class="label" for="new_password">New password</label>
      <div style="position:relative">
        <input id="new_password" name="new_password" type="password"
               class="otp-input"
               style="width:100%; padding:12px 44px 12px 14px; letter-spacing:0; text-align:left"
               minlength="8" required
               placeholder="At least 8 characters, incl. special char"
               pattern="(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^A-Za-z\d]).{8,}"
               title="At least 8 characters with uppercase, lowercase, number, and special character">
        <button type="button" id="toggle1" class="btn"
                style="position:absolute; right:6px; top:6px; padding:6px 10px">Show</button>
      </div>
      <div id="meter" class="muted" style="margin-top:6px" aria-live="polite">Strength: <b id="meterText">—</b></div>
      <ul class="muted" style="margin-top:6px; line-height:1.3">
        <li>Use uppercase, lowercase, a number, and a special character</li>
        <li>Minimum <strong>8</strong> characters</li>
        <li>Avoid old or common passwords</li>
      </ul>
    </div>

    <div class="form-group" style="margin-top:14px">
      <label class="label" for="confirm_password">Confirm new password</label>
      <div style="position:relative">
        <input id="confirm_password" name="confirm_password" type="password"
               class="otp-input"
               style="width:100%; padding:12px 44px 12px 14px; letter-spacing:0; text-align:left"
               minlength="8" required placeholder="Re-enter password">
        <button type="button" id="toggle2" class="btn"
                style="position:absolute; right:6px; top:6px; padding:6px 10px">Show</button>
      </div>
    </div>

    <div class="actions" style="margin-top:18px">
      <button type="submit" class="btn btn-primary">Reset Password</button>
      <a class="btn" href="../../index.php" style="margin-left:8px">Cancel</a>
    </div>
  </form>
</div>

<script>
  // Show/Hide toggles
  function toggle(id, btnId) {
    const inp = document.getElementById(id);
    const btn = document.getElementById(btnId);
    btn.addEventListener('click', () => {
      inp.type = (inp.type === 'password') ? 'text' : 'password';
      btn.textContent = (inp.type === 'password') ? 'Show' : 'Hide';
    });
  }
  toggle('new_password', 'toggle1');
  toggle('confirm_password', 'toggle2');

  // Strength meter
  (function(){
    const np = document.getElementById('new_password');
    const meterText = document.getElementById('meterText');

    function evaluate(v){
      if (!v) return {label:'—', total:0};
      const hasLower = /[a-z]/.test(v);
      const hasUpper = /[A-Z]/.test(v);
      const hasDigit = /\d/.test(v);
      const hasSpec  = /[^A-Za-z\d]/.test(v);

      let lenScore = 0;
      if (v.length >= 8 && v.length <= 11) lenScore = 1;
      else if (v.length >= 12 && v.length <= 15) lenScore = 2;
      else if (v.length >= 16) lenScore = 3;

      const variety = [hasLower, hasUpper, hasDigit, hasSpec].filter(Boolean).length;
      const total = lenScore + variety; // 0..7

      let label = 'Very weak';
      if (total <= 2) label = 'Very weak';
      else if (total <= 4) label = 'Weak';
      else if (total === 5) label = 'Fair';
      else if (total === 6) label = 'Good';
      else label = 'Strong';

      return {label, total};
    }

    np.addEventListener('input', () => {
      const {label} = evaluate(np.value);
      meterText.textContent = label;
    });
  })();

  // Prevent double-submit
  const form = document.getElementById('resetForm');
  form.addEventListener('submit', () => {
    const a = document.querySelector('.actions .btn.btn-primary');
    a.setAttribute('disabled', 'disabled');
    a.textContent = 'Saving…';
  });
</script>

</body>
</html>
