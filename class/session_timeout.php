<?php
// ---- Session timeout handler using the reference modal UI ----
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// CSRF token (from reference)
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Timeout duration (set to 1 minute here); use 600 for 10 minutes like the reference
$timeout_duration = 6000; // seconds

// Check inactivity
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > $timeout_duration) {
    // End session
    session_unset();
    session_destroy();

    // Reference modal UI + auto-redirect to index.php (~2.5–3s)
    echo '
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Session expired</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<style>
  :root{
    --bg:#0b1220; --card:#111827; --muted:#9ca3af; --text:#e5e7eb;
    --border:#1f2937; --brand:#2563eb; --warn:#f59e0b;
  }
  *{box-sizing:border-box}
  html,body{height:100%;margin:0;background:var(--bg);color:var(--text);font:14px/1.4 system-ui,Segoe UI,Roboto,Helvetica,Arial,sans-serif}
  .overlay{
    position:fixed;inset:0;display:flex;align-items:center;justify-content:center;
    background:rgba(0,0,0,.55);backdrop-filter:saturate(120%) blur(2px);
  }
  .card{
    width:min(92vw,440px);background:var(--card);border:1px solid var(--border);
    border-radius:16px;padding:20px 18px;box-shadow:0 10px 35px rgba(0,0,0,.4);
    text-align:left; animation:pop .12s ease-out;
  }
  @keyframes pop{from{transform:translateY(6px);opacity:.6}to{transform:none;opacity:1}}
  .row{display:flex;gap:14px}
  .icon{
    width:42px;height:42px;border-radius:12px;display:grid;place-items:center;
    background:rgba(245,158,11,.12); border:1px solid rgba(245,158,11,.25);
    flex:0 0 42px;
  }
  .title{margin:0 0 6px;font-weight:600;font-size:18px}
  .muted{margin:0;color:var(--muted)}
  .progress{
    height:8px;border-radius:999px;background:#0f172a;border:1px solid var(--border);
    overflow:hidden;margin-top:14px;position:relative;
  }
  .bar{
    height:100%;width:0;background:linear-gradient(90deg,var(--brand),#4f46e5);
    transition:width .2s linear;
  }
  .actions{margin-top:14px;display:flex;gap:8px}
  .btn{
    border:1px solid var(--border);background:#0f172a;color:var(--text);
    border-radius:10px;padding:8px 12px;font-weight:500;text-decoration:none;
  }
  .btn.primary{background:var(--brand);border-color:#1d4ed8}
</style>
</head>
<body>
  <div class="overlay" role="dialog" aria-modal="true" aria-labelledby="ttl" aria-describedby="desc">
    <div class="card">
      <div class="row">
        <div class="icon" aria-hidden="true">
          <!-- warning triangle -->
          <svg width="22" height="22" viewBox="0 0 24 24" fill="none">
            <path d="M12 8v5" stroke="var(--warn)" stroke-width="2" stroke-linecap="round"/>
            <circle cx="12" cy="17" r="1.2" fill="var(--warn)"/>
            <path d="M10.3 3.9 1.9 18a2 2 0 0 0 1.7 3h16.9a2 2 0 0 0 1.7-3L13.5 3.9a2 2 0 0 0-3.2 0Z"
                  stroke="var(--warn)" stroke-width="1.2" fill="transparent"/>
          </svg>
        </div>
        <div>
          <h1 id="ttl" class="title">Session expired</h1>
          <p id="desc" class="muted">Your session ended due to inactivity. You will be redirected to the login page.</p>
        </div>
      </div>
      <div class="progress" aria-hidden="true"><div class="bar" id="bar"></div></div>
      <div class="actions">
        <a class="btn" href="index.php">Go now</a>
        <span class="muted" id="count">Redirecting in 2.5s…</span>
      </div>
    </div>
  </div>

  <script>
    (function(){
      var total = 2500; // ms to wait before redirect
      var start = Date.now();
      var bar   = document.getElementById("bar");
      var text  = document.getElementById("count");
      function tick(){
        var elapsed = Date.now() - start;
        var p = Math.min(1, elapsed / total);
        if (bar) bar.style.width = (p * 100) + "%";
        if (text) {
          var remain = Math.max(0, total - elapsed);
          text.textContent = "Redirecting in " + (remain/1000).toFixed(1) + "s…";
        }
        if (elapsed >= total) {
          window.location.replace("index.php");
          return;
        }
        requestAnimationFrame(tick);
      }
      setTimeout(tick, 30);
      setTimeout(function(){ window.location.replace("index.php"); }, 3000);
    })();
  </script>
</body>
</html>';
    exit();
}

// Still active: update last activity timestamp
$_SESSION['last_activity'] = time();
?>
