<?php
// security/security.php
// Call with: require_once __DIR__ . '/security/security.php';

declare(strict_types=1);

// --- HTTPS detection for secure cookies ---
$httpsDetected =
  (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
  || (isset($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443)
  || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');

// --- Security headers (safe defaults) ---
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('Referrer-Policy: no-referrer-when-downgrade');
// NOTE: keep CSP minimal to avoid breaking; harden later if desired
header('Content-Security-Policy: frame-ancestors \'self\'');

// No-cache for auth pages by default
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

if ($httpsDetected) {
    // HSTS (adjust max-age if needed)
    header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
}

ini_set('session.cookie_httponly', '1');
ini_set('session.cookie_secure', $httpsDetected ? '1' : '0');
// session_set_cookie_params([
//     'lifetime' => 3600,
//     'secure'   => $httpsDetected,
//     'httponly' => true,
//     'samesite' => 'Strict',
// ]);
// $cookieParams = [
//     'lifetime' => 3600,
//     'secure'   => $httpsDetected,
//     'httponly' => true,
//     'samesite' => 'Strict',
// ];
// AFTER
session_set_cookie_params([
    'lifetime' => 0,           // session cookie (ends on browser close)
    'secure'   => $httpsDetected,
    'httponly' => true,
    'samesite' => 'Lax',       // <-- allows top-level email link nav to send cookie
]);
$cookieParams = [
    'lifetime' => 0,
    'secure'   => $httpsDetected,
    'httponly' => true,
    'samesite' => 'Lax',
];

if (session_status() !== PHP_SESSION_ACTIVE) {
    ini_set('session.cookie_httponly', '1');
    ini_set('session.cookie_secure', $httpsDetected ? '1' : '0');
    session_set_cookie_params($cookieParams);
    session_start();
} else {
    // Session already active: don't change params, just ensure flags
    ini_set('session.cookie_httponly', '1');
    if ($httpsDetected) ini_set('session.cookie_secure', '1');
}

// ---- Helpers ----
function security_no_cache(): void {
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    header('Expires: 0');
}

function csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}
function validate_csrf_token(?string $token): bool {
    return isset($_SESSION['csrf_token']) && is_string($token) && hash_equals($_SESSION['csrf_token'], $token);
}
function csrf_input(): string {
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') . '">';
}

// Bind session to UA + /16 IP (optional but recommended)
function bind_session(): void {
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';

    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
        $parts = explode('.', $ip);
        $ipSeg = $parts[0] . '.' . $parts[1] . '.0.0';
    } else {
        $ipSeg = substr($ip, 0, 7);
    }

    $finger = hash('sha256', $ua . '|' . $ipSeg);

    if (!isset($_SESSION['sess_fpr'])) {
        $_SESSION['sess_fpr'] = $finger;
    } elseif (!hash_equals($_SESSION['sess_fpr'], $finger)) {
        session_unset();
        session_destroy();
        header('Location: /index.php');
        exit;
    }
}
bind_session();

// Regenerate ID on privilege changes or login/logout boundaries
function session_regenerate_strict(): void {
    if (!isset($_SESSION['__last_regen'])) {
        $_SESSION['__last_regen'] = time();
        session_regenerate_id(true);
        return;
    }
    if ((time() - (int)$_SESSION['__last_regen']) > 300) { // every 5 mins
        $_SESSION['__last_regen'] = time();
        session_regenerate_id(true);
    }
}

// -------- Password-change flow gate (prevents back-button bypass) --------
// -------- Password-change flow gate (logout on back) --------
function enforce_pending_password_change(): void {
    $twofa   = $_SESSION['twofa'] ?? null;
    $pending = $_SESSION['pending_pw_change'] ?? null;
    $guard   = !empty($_SESSION['pw_change_guard']); // set when change_password.php opens

    // No flow markers → do nothing
    if (!$guard && !$twofa && !$pending) return;
    if (!$guard && (($twofa['purpose'] ?? '') !== 'password_change') && !$pending) return;

    // Pages allowed to be shown while the flow is active
    $allowedBasenames = [
        'change_password.php',
        'cp_2fa.php',
        'verify_change.php',
        'logout.php',  // allow explicit logout
    ];

    $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
    $base = strtolower(basename($path));

    if (!in_array($base, $allowedBasenames, true)) {
        security_no_cache();
        // 🔐 Any attempt to go elsewhere (e.g., Back to dashboard) → force logout
        header('Location: /logout.php?reason=pw_flow');
        exit;
    }
}



// -------- RESIDENT LOGIN ATTEMPT HELPERS --------
function record_failed_resident_attempt(mysqli $db, int $resident_id, int $maxFailures = 3, int $lockSeconds = 60): void {
    $q = $db->prepare("SELECT attempts FROM resident_login_attempts WHERE resident_id = ?");
    $q->bind_param("i", $resident_id);
    $q->execute();
    $r = $q->get_result();

    if ($row = $r->fetch_assoc()) {
        $attempts = ((int)$row['attempts']) + 1;
        if ($attempts >= $maxFailures) {
            $u = $db->prepare("
                UPDATE resident_login_attempts
                   SET attempts = ?, locked_until = DATE_ADD(NOW(), INTERVAL ? SECOND), last_attempt = NOW()
                 WHERE resident_id = ?");
            $u->bind_param("iii", $attempts, $lockSeconds, $resident_id);
        } else {
            $u = $db->prepare("
                UPDATE resident_login_attempts
                   SET attempts = ?, last_attempt = NOW()
                 WHERE resident_id = ?");
            $u->bind_param("ii", $attempts, $resident_id);
        }
        $u->execute();
    } else {
        $i = $db->prepare("
            INSERT INTO resident_login_attempts (resident_id, attempts, last_attempt)
            VALUES (?, 1, NOW())");
        $i->bind_param("i", $resident_id);
        $i->execute();
    }
}

function is_resident_locked_out(mysqli $db, int $resident_id) {
    $q = $db->prepare("
        SELECT TIMESTAMPDIFF(SECOND, NOW(), locked_until) AS remaining
          FROM resident_login_attempts
         WHERE resident_id = ? AND locked_until IS NOT NULL");
    $q->bind_param("i", $resident_id);
    $q->execute();
    $r = $q->get_result();
    if ($row = $r->fetch_assoc()) {
        $rem = (int)$row['remaining'];
        if ($rem > 0) return $rem;
    }
    return false;
}

function reset_resident_attempts(mysqli $db, int $resident_id): void {
    $d = $db->prepare("DELETE FROM resident_login_attempts WHERE resident_id = ?");
    $d->bind_param("i", $resident_id);
    $d->execute();
}
