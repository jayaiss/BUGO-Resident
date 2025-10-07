<?php
require_once __DIR__ . '/../../include/connection.php';
$mysqli = db_connection();
require_once __DIR__ . '/../../include/encryption.php';
require_once __DIR__ . '/../../logs/logs_trig.php';
session_start();

// Composer autoload for PHPMailer
require_once __DIR__ . '/../../vendor/autoload.php';
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

/** -----------------------------
 *  Flash message helpers (PRG)
 *  ----------------------------- */
function set_flash($key, $val) {
    $_SESSION['flash'][$key] = $val;
}
function get_flash($key) {
    if (!isset($_SESSION['flash'][$key])) return null;
    $v = $_SESSION['flash'][$key];
    unset($_SESSION['flash'][$key]);
    return $v;
}

/** -----------------------------
 *  CSRF token bootstrap
 *  ----------------------------- */
if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
}
$csrf = $_SESSION['csrf'];

$loggedInResidentId = $_SESSION['id'] ?? null;

if (!$loggedInResidentId) {
    header("Location: /index.php");
    exit();
}

// ✅ Prevent redirect loop by checking if URL is already encrypted
$page = $_GET['page'] ?? '';
$decodedPage = decrypt($page);

if (!empty($page) && !$decodedPage) {
    header("Location: /../../security/404.html");
    exit();
}

if ($decodedPage !== 'settings_section') {
    header("Location: " . enc_self('settings_section'));
    exit();
}

/**
 * Send the password-change verification link via cPanel SMTP with fallbacks:
 *  - Try SMTPS 465
 *  - If that fails, try STARTTLS 587
 *  - If that fails, fall back to local sendmail
 * TEMP: BCCs the sender so you can confirm delivery; remove once stable.
 */
function send_pw_change_link(string $toEmail, string $verifyUrl): void {
    $mailboxUser = 'admin@bugoportal.site';
    $mailboxPass = 'Jayacop@100';          // ⚠️ your real cPanel mailbox password
    $smtpHost    = 'mail.bugoportal.site';

    $safeUrl = htmlspecialchars($verifyUrl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

    $buildMessage = function(PHPMailer $m) use ($toEmail, $safeUrl, $mailboxUser) {
        $m->setFrom($mailboxUser, 'Barangay Bugo');
        $m->addAddress($toEmail);
        $m->addBCC($mailboxUser); // TEMP: verify delivery; remove later
        $m->isHTML(true);
        $m->Subject = 'Confirm Your Password Change';
        $m->Body = '
            <p>Hello,</p>
            <p>You requested to change your password. Please confirm by clicking the button below:</p>
            <p><a href="'.$safeUrl.'" style="background:#0d6efd;color:#fff;padding:10px 16px;border-radius:6px;text-decoration:none;">Confirm Password Change</a></p>
            <p>This link expires in 20 minutes. If you didn’t request this, you can ignore this email.</p>
        ';
        $m->AltBody = "Confirm your password change: {$verifyUrl}";
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
        $mail->Timeout       = 10;     // keep connection attempts short
        $mail->SMTPAutoTLS   = true;
        $mail->SMTPKeepAlive = false;

        // TEMP: relax TLS checks if cert/hostname mismatch; remove when DNS/SSL is clean
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

        // Optional SMTP dialog logs:
        // $mail->SMTPDebug   = 2;
        // $mail->Debugoutput = function($str,$lvl){ error_log("SMTP[$lvl] $str"); };

        $buildMessage($mail);
        $mail->send();
    };

    error_log("PW-CHANGE: sending link to {$toEmail}");

    try {
        error_log('PW-CHANGE: trying SMTP SSL 465…');
        $attempt('ssl', 465);
        error_log('PW-CHANGE: sent via 465 SSL');
    } catch (\Throwable $e1) {
        error_log('PW-CHANGE: 465 failed: '.$e1->getMessage());

        try {
            error_log('PW-CHANGE: trying SMTP STARTTLS 587…');
            $attempt('tls', 587);
            error_log('PW-CHANGE: sent via 587 STARTTLS');
        } catch (\Throwable $e2) {
            error_log('PW-CHANGE: 587 failed: '.$e2->getMessage());

            // Last resort: local MTA (sendmail)
            try {
                error_log('PW-CHANGE: falling back to local sendmail…');
                $fallback = new PHPMailer(true);
                $fallback->isMail();
                $buildMessage($fallback);
                $fallback->send();
                error_log('PW-CHANGE: sent via local sendmail');
            } catch (\Throwable $e3) {
                error_log('PW-CHANGE: sendmail failed: '.$e3->getMessage());
                throw new \RuntimeException('Could not send verification email.');
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

/** -----------------------------
 *  Handle POST (Change Password)
 *  ----------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['current_password'])) {
    // CSRF check first
    if (!hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? '')) {
        set_flash('message', ['type' => 'err', 'text' => 'Invalid session. Please try again.']);
        header("Location: " . enc_self('settings_section'));
        exit();
    }
    // Rotate CSRF token (one-time use) to reduce accidental resubmits
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
    $csrf = $_SESSION['csrf'];

    $currentPassword = $_POST['current_password'] ?? '';
    $newPassword     = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    // 1) Get current hash + email
    $stmt = $mysqli->prepare("SELECT email, password FROM residents WHERE id = ?");
    $stmt->bind_param("i", $loggedInResidentId);
    $stmt->execute();
    $stmt->bind_result($residentEmail, $hashedPassword);
    $stmt->fetch();
    $stmt->close();

    if (!$residentEmail || !filter_var($residentEmail, FILTER_VALIDATE_EMAIL)) {
        set_flash('message', ['type' => 'err', 'text' => 'Your account email looks invalid. Please update your email first.']);
        header("Location: " . enc_self('settings_section'));
        exit();
    }

    if (!password_verify($currentPassword, $hashedPassword)) {
        set_flash('message', ['type' => 'err', 'text' => 'Current password is incorrect.']);
        header("Location: " . enc_self('settings_section'));
        exit();
    }

    if ($newPassword !== $confirmPassword) {
        set_flash('message', ['type' => 'err', 'text' => 'New passwords do not match.']);
        header("Location: " . enc_self('settings_section'));
        exit();
    }

    if (password_verify($newPassword, $hashedPassword)) {
        set_flash('message', ['type' => 'err', 'text' => 'New password cannot be the same as your current password.']);
        header("Location: " . enc_self('settings_section'));
        exit();
    }

    // 2) Complexity rules
    $ok =
        strlen($newPassword) >= 8 &&
        preg_match('/[A-Z]/', $newPassword) &&
        preg_match('/[a-z]/', $newPassword) &&
        preg_match('/[0-9]/', $newPassword) &&
        preg_match('/[^a-zA-Z0-9]/', $newPassword);

    if (!$ok) {
        set_flash('message', ['type' => 'err', 'text' => 'Password must include 1 uppercase letter, 1 lowercase letter, 1 number, and 1 special character.']);
        header("Location: " . enc_self('settings_section'));
        exit();
    }

    // 3) Create pending request instead of updating immediately
    $rawToken     = bin2hex(random_bytes(32));   // 64 hex chars
    $tokenHashHex = hash('sha256', $rawToken);   // store hex hash
    $newHash      = password_hash($newPassword, PASSWORD_DEFAULT);

    $createdAt = date('Y-m-d H:i:s');
    $expiresAt = date('Y-m-d H:i:s', time() + 20 * 60); // 20 min

    // Ensure only one active request at a time
    $stmt = $mysqli->prepare("DELETE FROM password_change_requests WHERE resident_id = ? AND used = 0");
    $stmt->bind_param("i", $loggedInResidentId);
    $stmt->execute();
    $stmt->close();

    $stmt = $mysqli->prepare("
        INSERT INTO password_change_requests (resident_id, token_hash, new_password_hash, created_at, expires_at, used)
        VALUES (?, ?, ?, ?, ?, 0)
    ");
    $stmt->bind_param("issss", $loggedInResidentId, $tokenHashHex, $newHash, $createdAt, $expiresAt);
    $okInsert = $stmt->execute();
    $stmt->close();

    if (!$okInsert) {
        set_flash('message', ['type' => 'err', 'text' => 'Could not create verification request. Please try again.']);
        header("Location: " . enc_self('settings_section'));
        exit();
    }

    // 4) Build verify link pointing to auth/settings/verify_change.php
    $scheme   = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host     = $_SERVER['HTTP_HOST'];
    $baseDir  = rtrim(str_replace('\\','/', dirname($_SERVER['SCRIPT_NAME'])), '/');
    $verifyUrl = sprintf(
        '%s://%s%s/verify_change.php?rid=%d&t=%s',
        $scheme,
        $host,
        $baseDir,
        (int)$loggedInResidentId,
        urlencode($rawToken)
    );

    // 5) Send email (with fallbacks)
    try {
        send_pw_change_link($residentEmail, $verifyUrl);

        set_flash('message', ['type' => 'ok', 'text' => 'We sent a verification link to your email. The password will change after you confirm.']);
        header("Location: " . enc_self('settings_section'));
        exit();
    } catch (Throwable $e) {
        // Clean up pending record if email fails
        $stmt = $mysqli->prepare("DELETE FROM password_change_requests WHERE resident_id = ? AND used = 0");
        $stmt->bind_param("i", $loggedInResidentId);
        $stmt->execute();
        $stmt->close();

        set_flash('message', ['type' => 'err', 'text' => 'Could not send verification email. Please try again.']);
        header("Location: " . enc_self('settings_section'));
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <title>Account Settings</title>
    <meta name="viewport" content="width=device-width, initial-scale=1" />

    <!-- Bootstrap -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="icon" type="image/png" href="/assets/logo/logo.png">

   <style>
  :root{
    /* ✅ Default = Light Theme */
    --bg: #f4f6f9;          /* soft page background */
    --card: #ffffff;        /* white cards */
    --sidebar-bg: #ffffff;  /* white sidebar */
    --sidebar-hover:#f1f3f6;/* light hover */
    --accent:#0d6efd;
    --border:#dee2e6;
    --text:#212529;
    --text-muted:#6c757d;
  }

  /* 🌙 Comfort-Dark (not black) — still light and readable */
  @media (prefers-color-scheme: dark){
    :root{
      --bg:#eef2f6;             /* very light gray instead of dark */
      --card:#ffffff;           /* keep cards white */
      --sidebar-bg:#ffffff;     /* white sidebar */
      --sidebar-hover:#f1f3f6;  /* subtle hover */
      --accent:#0d6efd;
      --border:#d9dee5;         /* soft border */
      --text:#1f2a37;           /* dark text for readability */
      --text-muted:#556170;
    }
  }

  html, body{ height:100%; }
  body{
    background:var(--bg);
    color:var(--text);
  }

  /* Topbar (mobile) — keep light in both modes */
  .topbar{
    position:sticky; top:0; z-index:1030;
    -webkit-backdrop-filter:saturate(180%) blur(6px);
    backdrop-filter:saturate(180%) blur(6px);
    background:rgba(255,255,255,.75);
    border-bottom:1px solid var(--border);
  }

  /* Sidebar */
  .sidebar{
    background:var(--sidebar-bg);
    color:var(--text);
    min-width:240px; max-width:240px;
  }
  .sidebar a{
    color:var(--text);
    text-decoration:none;
    display:flex; align-items:center; gap:.6rem;
    padding:.75rem 1rem; border-left:4px solid transparent;
    transition: background-color .15s ease, color .15s ease;
  }
  .sidebar a:hover{
    background:var(--sidebar-hover);
    color:var(--text);                 /* ❗ no white text on light bg */
  }
  .sidebar a.active{
    background:var(--sidebar-hover);
    border-left-color:var(--accent);
    color:var(--text);
    font-weight:600;
  }

  /* Main layout */
  .layout{
    display:grid; grid-template-columns:1fr; gap:1.25rem;
  }
  @media (min-width: 992px){
    .layout{
      grid-template-columns:260px 1fr; align-items:start;
    }
    .sidebar-wrapper{
      position:sticky; top:72px; /* below topbar */
      height:calc(100dvh - 88px);
      overflow:auto;
      border-right:1px solid var(--border);
      background:var(--sidebar-bg);
    }
  }

  /* Cards / modules */
  .module{
    background:var(--card);
    border:1px solid var(--border);
    border-radius:16px;
    padding:20px;
    box-shadow:0 4px 14px rgba(0,0,0,.04); /* softer, lighter */
  }
  .module h5{
    display:flex; align-items:center; gap:.6rem; margin-bottom:1rem;
  }
  .helper-text{ color:var(--text-muted); }

  /* Password strength checklist */
  .req-list{ list-style:none; padding-left:0; margin:.5rem 0 0; }
  .req-list li{ display:flex; align-items:center; gap:.5rem; font-size:.9rem; }
  .req-ok{ color:#198754; }
  .req-bad{ color:#dc3545; }

  /* Focus */
  .btn:focus, .form-control:focus{
    box-shadow:0 0 0 .2rem rgba(13,110,253,.15);
  }

  /* Page padding */
  .page-wrap{ padding:16px; }
  @media (min-width: 992px){
    .page-wrap{ padding:24px; }
  }
</style>

</head>
<body>

<!-- Mobile topbar -->
<nav class="topbar navbar navbar-light px-2 px-sm-3">
    <div class="d-flex w-100 align-items-end justify-content-center">
        <!-- <button class="btn btn-outline-secondary d-lg-none" data-bs-toggle="offcanvas" data-bs-target="#offcanvasNav" aria-controls="offcanvasNav" aria-label="Open navigation">
            <i class="bi bi-list"></i>
        </button> -->
        <span class="fw-semibold">Account Settings</span>
        <!-- <a class="btn btn-outline-primary btn-sm d-none d-sm-inline-flex" href="/bugo-resident-side/<?php echo enc_page('admin_dashboard'); ?>">
            <i class="bi bi-house-door me-1"></i> Home
        </a> -->
    </div>
</nav>

<div class="page-wrap container-fluid">
    <div class="layout">
        <!-- Desktop sidebar -->
        <div class="sidebar-wrapper d-none d-lg-block rounded-end">
            <div class="sidebar h-100 py-3">
                <div class="px-3 pb-3">
                    <div class="text-white-50 small">Navigation</div>
                </div>
                <a href="<?php echo enc_page('homepage'); ?>"
                   class="<?php echo (isset($_GET['page']) && $_GET['page'] === 'homepage') ? 'active' : ''; ?>">
                    <i class="bi bi-house-door"></i> Home
                </a>
                <?php $settings_link = enc_page('settings_section', 'settings.php?'); ?>
                <a href="<?= $settings_link ?>" class="active">
                    <i class="bi bi-gear"></i> Settings
                </a>
            </div>
        </div>

        <!-- Content -->
        <main class="content">
            <!-- Flash messages -->
            <div aria-live="polite" aria-atomic="true">
                <?php if ($flash = get_flash('message')): ?>
                    <div class="alert <?php echo $flash['type'] === 'ok' ? 'alert-success' : 'alert-danger'; ?> alert-dismissible fade show" role="alert">
                        <i class="bi <?php echo $flash['type'] === 'ok' ? 'bi-check-circle' : 'bi-exclamation-triangle'; ?> me-2"></i>
                        <?php echo htmlspecialchars($flash['text']); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Modules -->
            <div class="row g-3">
                <div class="col-12 col-xl-8">
                    <div class="module">
                        <h5><i class="bi bi-shield-lock"></i> Change Password</h5>

                        <form method="POST" onsubmit="disableSubmit(this)" novalidate>
                            <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrf); ?>">

                            <div class="mb-3">
                                <label class="form-label">Current Password</label>
                                <div class="input-group">
                                    <input type="password" name="current_password" class="form-control" autocomplete="current-password" required>
                                    <button class="btn btn-outline-secondary toggle-password" type="button">
                                        <i class="bi bi-eye-slash"></i> <span class="toggle-text">Show</span>
                                    </button>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">New Password</label>
                                <div class="position-relative mb-2">
                                    <input
                                        type="password"
                                        class="form-control pe-5"
                                        id="new_password"
                                        name="new_password"
                                        autocomplete="new-password"
                                        required
                                        aria-describedby="pwHelp"
                                        oninput="checkPasswordStrength(this.value)"
                                    >
                                    <button type="button"
                                        class="btn btn-sm btn-outline-secondary position-absolute top-50 end-0 translate-middle-y me-2"
                                        onclick="togglePassword('new_password', this)">
                                        <i class="bi bi-eye-slash"></i> <span class="toggle-text">Show</span>
                                    </button>
                                </div>

                                <!-- Strength meter -->
                                <div class="progress mb-2" role="progressbar" aria-label="Password strength">
                                    <div id="pw-bar" class="progress-bar" style="width:0%"></div>
                                </div>

                                <div id="password-strength" class="small fw-semibold text-muted mb-2"></div>

                                <div id="pwHelp" class="helper-text small">
                                    Use at least 8 characters and include:
                                    <ul class="req-list mt-1" id="reqList">
                                        <li id="reqLen"><i class="bi bi-x-circle req-bad"></i> Minimum 8 characters</li>
                                        <li id="reqCase"><i class="bi bi-x-circle req-bad"></i> Upper &amp; lower case letters</li>
                                        <li id="reqNum"><i class="bi bi-x-circle req-bad"></i> At least one number</li>
                                        <li id="reqSym"><i class="bi bi-x-circle req-bad"></i> At least one symbol</li>
                                    </ul>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Confirm New Password</label>
                                <div class="input-group">
                                    <input type="password" name="confirm_password" class="form-control" autocomplete="new-password" required>
                                    <button class="btn btn-outline-secondary toggle-password" type="button">
                                        <i class="bi bi-eye-slash"></i> <span class="toggle-text">Show</span>
                                    </button>
                                </div>
                            </div>

                            <div class="d-flex gap-2">
                                <button type="submit" class="btn btn-primary">
                                    <span class="btn-text"><i class="bi bi-arrow-repeat me-1"></i> Update Password</span>
                                </button>
                                <a href="<?php echo enc_page('homepage'); ?>" class="btn btn-outline-secondary">
                                    <i class="bi bi-house-door me-1"></i> Back to Home
                                </a>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Optional promo/empty column for scalability -->
                <div class="col-12 col-xl-4">
                    <div class="module h-100">
                        <h5><i class="bi bi-info-circle"></i> Tips</h5>
                        <ul class="small mb-0">
                            <li>Use a unique password you don’t use elsewhere.</li>
                            <li>A passphrase (e.g., 3–4 random words with symbols) is both strong and memorable.</li>
                            <li>Never share your password or verification codes.</li>
                        </ul>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>

<!-- Offcanvas (mobile sidebar) -->
<div class="offcanvas offcanvas-start text-bg-dark" tabindex="-1" id="offcanvasNav" aria-labelledby="offcanvasLabel">
  <div class="offcanvas-header">
    <h5 class="offcanvas-title" id="offcanvasLabel"><i class="bi bi-gear me-2"></i> Menu</h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas" aria-label="Close"></button>
  </div>
  <div class="offcanvas-body p-0">
    <div class="list-group list-group-flush">
      <a class="list-group-item list-group-item-action d-flex align-items-center gap-2" href="<?php echo enc_page('homepage'); ?>">
        <i class="bi bi-house-door"></i> Home
      </a>
      <?php $settings_link = enc_page('settings_section', 'settings.php?'); ?>
      <a class="list-group-item list-group-item-action active d-flex align-items-center gap-2" href="<?= $settings_link ?>">
        <i class="bi bi-gear"></i> Settings
      </a>
    </div>
  </div>
</div>

<!-- JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Toggle buttons inside .input-group
document.querySelectorAll('.toggle-password').forEach(btn => {
    btn.addEventListener('click', () => {
        const input = btn.closest('.input-group').querySelector('input');
        const icon = btn.querySelector('i');
        const text = btn.querySelector('.toggle-text');
        if (input.type === 'password') {
            input.type = 'text';
            icon.classList.replace('bi-eye-slash', 'bi-eye');
            text.textContent = 'Hide';
        } else {
            input.type = 'password';
            icon.classList.replace('bi-eye', 'bi-eye-slash');
            text.textContent = 'Show';
        }
    });
});

// Single toggle function for standalone field
function togglePassword(inputId, button) {
    const input = document.getElementById(inputId);
    const icon = button.querySelector('i');
    const text = button.querySelector('.toggle-text');
    if (input.type === 'password') {
        input.type = 'text';
        icon.classList.replace('bi-eye-slash', 'bi-eye');
        text.textContent = 'Hide';
    } else {
        input.type = 'password';
        icon.classList.replace('bi-eye', 'bi-eye-slash');
        text.textContent = 'Show';
    }
}

const pwBar = document.getElementById('pw-bar');
const strengthText = document.getElementById('password-strength');

// Update checklist icon state
function setReqState(el, ok){
    const icon = el.querySelector('i');
    icon.classList.toggle('bi-x-circle', !ok);
    icon.classList.toggle('bi-check-circle', ok);
    icon.classList.toggle('req-bad', !ok);
    icon.classList.toggle('req-ok', ok);
}

// One canonical password-strength checker
function checkPasswordStrength(password) {
    let strength = 0;

    const hasLen  = password.length >= 8;
    const hasCase = /[a-z]/.test(password) && /[A-Z]/.test(password);
    const hasNum  = /[0-9]/.test(password);
    const hasSym  = /[^a-zA-Z0-9]/.test(password);

    setReqState(document.getElementById('reqLen'), hasLen);
    setReqState(document.getElementById('reqCase'), hasCase);
    setReqState(document.getElementById('reqNum'), hasNum);
    setReqState(document.getElementById('reqSym'), hasSym);

    if (hasLen) strength++;
    if (hasCase) strength++;
    if (hasNum) strength++;
    if (hasSym) strength++;

    const pct = (strength / 4) * 100;
    pwBar.style.width = pct + '%';
    pwBar.classList.remove('bg-danger','bg-warning','bg-success');

    if (!password.length){
        strengthText.textContent = '';
        pwBar.style.width = '0%';
        return;
    }

    if (strength <= 1){
        pwBar.classList.add('bg-danger');
        strengthText.textContent = 'Password Strength: Weak';
    } else if (strength === 2){
        pwBar.classList.add('bg-warning');
        strengthText.textContent = 'Password Strength: Medium';
    } else {
        pwBar.classList.add('bg-success');
        strengthText.textContent = 'Password Strength: Strong';
    }
}

document.addEventListener("DOMContentLoaded", function () {
    const passwordInput = document.getElementById('new_password');
    if (passwordInput) {
        passwordInput.addEventListener('input', function () {
            checkPasswordStrength(this.value);
        });
    }
});

// Disable submit on first click (UX only)
function disableSubmit(form) {
    const btn = form.querySelector('button[type="submit"]');
    if (btn) {
        btn.disabled = true;
        btn.classList.add('disabled');
        const span = btn.querySelector('.btn-text');
        if (span){
            span.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span> Processing...';
        } else {
            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span> Processing...';
        }
    }
}
</script>

</body>
</html>
