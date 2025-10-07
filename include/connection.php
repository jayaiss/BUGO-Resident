<?php
declare(strict_types=1);

// --- one-time bootstrap guard ---
if (defined('BUGO_CONNECTION_BOOTSTRAPPED')) return;
define('BUGO_CONNECTION_BOOTSTRAPPED', true);

// --- never show raw errors to end users (log instead) ---
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
if (!ini_get('error_log')) {
    @ini_set('error_log', __DIR__ . '/../logs/php_errors.log');
}

// --- block direct access (serve harmless page) ---
if (basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'] ?? '')) {
    http_response_code(403);
    $p = __DIR__ . '/../security/403.html';
    if (is_file($p)) { require $p; } else { echo 'Forbidden'; }
    exit;
}

// --- dotenv (composer) ---
require_once __DIR__ . '/../vendor/autoload.php';
Dotenv\Dotenv::createImmutable(dirname(__DIR__))->safeLoad();

// --- envs ---
$dbHost    = $_ENV['DB_HOST']    ?? 'localhost';
$dbPort    = (int)($_ENV['DB_PORT'] ?? 3306);
$dbName    = $_ENV['DB_NAME']    ?? '';
$dbUser    = $_ENV['DB_USER']    ?? '';
$dbPass    = $_ENV['DB_PASS']    ?? '';
$dbCharset = $_ENV['DB_CHARSET'] ?? 'utf8mb4';

// --- mysqli secure init (throw exceptions on errors) ---
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

global $mysqli;

/**
 * Minimal helper to output a friendly error and quit.
 * Chooses JSON for API/AJAX callers automatically (no BUGO_WANTS_JSON flag).
 */
if (!function_exists('bugo_render_friendly_error')) {
    function bugo_render_friendly_error(int $status, string $message, string $htmlFile = ''): void {
        // CLI: print to STDERR
        if (PHP_SAPI === 'cli') {
            fwrite(STDERR, $message . PHP_EOL);
            exit(1);
        }

        http_response_code($status);

        // Heuristics for JSON response
        $acceptsJson = stripos($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') !== false;
        $isAjax      = strcasecmp($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '', 'xmlhttprequest') === 0;
        // Adjust ^/api to your API prefix if different
        $isApiPath   = (bool) preg_match('#^/api(?:/|$)#i', $_SERVER['REQUEST_URI'] ?? '');
        $wantsJson   = $acceptsJson || $isAjax || $isApiPath;

        if ($wantsJson) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['success' => false, 'message' => $message]);
            exit;
        }

        if ($htmlFile && is_file($htmlFile)) {
            require $htmlFile;
            exit;
        }

        // Fallback inline page (no sensitive info)
        echo '<!doctype html><meta charset="utf-8"><title>Temporarily Unavailable</title>'
           . '<style>body{font-family:system-ui,-apple-system,Segoe UI,Roboto,Helvetica,Arial,sans-serif;margin:0;'
           . 'display:grid;place-items:center;height:100vh;background:#0b121a;color:#e8eef5}'
           . '.card{background:#111923;border:1px solid #1f2a36;border-radius:12px;padding:32px;'
           . 'box-shadow:0 10px 24px rgba(0,0,0,.25);max-width:520px}'
           . 'h1{font-size:20px;margin:0 0 8px}p{margin:0;color:#bcc6d3}</style>'
           . '<div class="card"><h1>We’re doing a quick tune-up</h1>'
           . '<p>Please try again in a moment.</p></div>';
        exit;
    }
}

/**
 * Connect or reconnect to the DB.
 * Returns a live mysqli connection; never leaks internals on failure.
 */
if (!function_exists('db_connection')) {
    function db_connection(): mysqli {
        global $mysqli, $dbHost, $dbUser, $dbPass, $dbName, $dbPort, $dbCharset;

        // Reuse existing live connection if possible
        if ($mysqli instanceof mysqli) {
            try {
                if ($mysqli->ping()) {
                    return $mysqli;
                }
            } catch (Throwable $t) {
                // fall through to reconnect
            }
        }

        try {
            $mysqli = mysqli_init();
            if (!$mysqli) {
                throw new RuntimeException('Database driver not available.');
            }

            // Harden options
            $mysqli->options(MYSQLI_OPT_LOCAL_INFILE, 0);
            if (defined('MYSQLI_OPT_CONNECT_TIMEOUT')) {
                $mysqli->options(MYSQLI_OPT_CONNECT_TIMEOUT, 5);
            }

            // Connect
            $mysqli->real_connect($dbHost, $dbUser, $dbPass, $dbName, $dbPort);

            // Charset
            $mysqli->set_charset($dbCharset);
            $mysqli->query("SET time_zone = '+08:00'");

            return $mysqli;

        } catch (mysqli_sql_exception $e) {
            // Log full details server-side ONLY
            error_log(sprintf(
                '[DB CONNECT] (%d) %s in %s:%d',
                $e->getCode(),
                $e->getMessage(),
                $e->getFile(),
                $e->getLine()
            ));
            // Re-throw generic error (caught below so nothing leaks)
            throw new RuntimeException('Service temporarily unavailable.');
        }
    }
}

// --- initialize first connection safely ---
try {
    $mysqli = db_connection();
} catch (Throwable $e) {
    error_log('[DB INIT FAIL] ' . $e);
    // Serve friendly 503 (HTML or JSON). Provide your custom page if you have one.
    bugo_render_friendly_error(
        503,
        'Service temporarily unavailable. Please try again shortly.',
        __DIR__ . '/../security/503.html'
    );
}
