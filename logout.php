<?php
declare(strict_types=1);

session_start();

// If you log logout events, keep the DB include; otherwise you can remove the DB bits.
// We need DB here to revoke the remember-me token securely.
require_once __DIR__ . '/include/connection.php';
require_once __DIR__ . '/security/remember.php';
$mysqli = db_connection();
require_once './logs/logs_trig.php';
// ——— Security & cache headers ———
header('Content-Type: text/html; charset=UTF-8');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: no-referrer');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

// ——— Revoke persistent "Remember me" token (current device) ———
$trigger = new Trigger();
$residentId = $_SESSION['id'] ?? null;

if ($residentId) {
    $trigger->isLogout(7, $residentId);
}
if (!empty($_COOKIE[REMEMBER_COOKIE_NAME])) {
  $cookie = $_COOKIE[REMEMBER_COOKIE_NAME];
  // Cookie format: selector:validator — we only need selector to delete the DB row
  if (strpos($cookie, ':') !== false) {
    [$selector] = explode(':', $cookie, 2);
    if (!empty($selector) && $mysqli && !$mysqli->connect_error) {
      remember_delete_by_selector($mysqli, $selector);
    }
  }
  // Always clear the browser cookie
  remember_clear_cookie();
}

// OPTIONAL: revoke ALL devices for this user (uncomment to enforce global logout)
if ($residentId && $mysqli && !$mysqli->connect_error) {
  remember_delete_all_for_user($mysqli, (int)$residentId);
}

// ——— Kill session data and cookie ———
$_SESSION = [];
if (ini_get('session.use_cookies')) {
  $p = session_get_cookie_params();
  setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
}
session_destroy();

$redirect = 'index.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Logged out</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="preconnect" href="https://cdn.jsdelivr.net" crossorigin>
  <link rel="icon" type="image/png" href="assets/logo/logo.png">
  <script defer src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <style>
    html,body{height:100%}
    body{
      margin:0;
      display:grid;
      place-items:center;
      background:#f2f4f7;
      font-family:system-ui,-apple-system,"Segoe UI",Roboto,"Helvetica Neue",Arial,"Noto Sans";
    }
    .fallback{
      display:none;
      text-align:center;
      background:#fff;
      border-radius:16px;
      padding:24px;
      box-shadow:0 10px 30px rgba(16,24,40,.12);
      max-width:420px;
    }
    .fallback h1{margin:0 0 .25rem;font-size:1.25rem}
    .fallback p{margin:.25rem 0 1rem;color:#667085}
    .btn{display:inline-block;padding:.5rem 1rem;border-radius:.5rem;border:0;background:#0d6efd;color:#fff;text-decoration:none}
    @media (prefers-color-scheme: dark){
      body{background:#0b1020;color:#e6e9f2}
      .fallback{background:#12182b;box-shadow:0 12px 30px rgba(0,0,0,.4)}
    }
  </style>
  <!-- No-JS fallback -->
  <noscript><meta http-equiv="refresh" content="0; url=<?= htmlspecialchars($redirect, ENT_QUOTES) ?>"></noscript>
</head>
<body>
  <!-- Fallback card (shows if SweetAlert2 fails to load) -->
  <div class="fallback" id="fallback">
    <h1>Logged Out</h1>
    <p>You have been successfully logged out.</p>
    <a class="btn" href="<?= htmlspecialchars($redirect, ENT_QUOTES) ?>">Continue</a>
  </div>

  <script>
  (function(){
    // Prevent navigating back to a cached authenticated page
    if (history.replaceState) {
      history.replaceState(null, document.title, location.href);
      addEventListener('popstate', function(){ location.replace('<?= $redirect ?>'); });
    }

    function showFallback(){
      var el = document.getElementById('fallback');
      if (el) el.style.display = 'block';
    }

    if (!window.Swal || !Swal.fire) { showFallback(); return; }

    // Small toast that auto-redirects—no tap needed
    Swal.fire({
      toast: true,
      position: 'top',
      icon: 'success',
      title: 'Logged out',
      showConfirmButton: false,
      timer: 1400,
      timerProgressBar: true,
      didOpen: (t)=>{ t.setAttribute('aria-live','polite'); },
      willClose: ()=>{ location.replace('<?= $redirect ?>'); }
    }).catch(showFallback);
  })();
  </script>
</body>
</html>
