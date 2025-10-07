<?php
// bootstrap/version.php
if (defined('APP_VERSION')) return;

// Resolve repo root (override via APP_ROOT if needed)
$REPO_ROOT  = getenv('APP_ROOT') ?: realpath(dirname(__DIR__));
$CACHE_FILE = $REPO_ROOT . '/storage/app_version.txt';

// detect app env
$env   = strtolower((string) (getenv('APP_ENV') ?: ($_ENV['APP_ENV'] ?? '')));
$isProd = in_array($env, ['prod', 'production'], true);

// Helper: write version to cache if we can (best-effort)
$persist = function (string $v) use ($CACHE_FILE) {
    $dir = dirname($CACHE_FILE);
    if (@is_dir($dir) && @is_writable($dir)) {
        @file_put_contents($CACHE_FILE, $v, LOCK_EX);
    }
};

// 0) Prefer explicit env (set this in your deploy/Actions job)
$envVer = getenv('APP_VERSION') ?: ($_SERVER['APP_VERSION'] ?? ($_ENV['APP_VERSION'] ?? null));
if ($envVer) {
    $v = trim((string) $envVer);
    if ($v !== '') {
        define('APP_VERSION', $v);
        $persist($v);
        return;
    }
}

// 0.1) Auto-derive from GitHub Actions env if present
// - If deploying a tagged release, we'll expose the tag (GITHUB_REF_TYPE=tag, GITHUB_REF_NAME=v1.2.3)
// - Otherwise, we’ll use the short commit SHA (GITHUB_SHA)
$ghSha     = getenv('GITHUB_SHA') ?: '';
$ghRefType = strtolower((string) (getenv('GITHUB_REF_TYPE') ?: ''));
$ghRefName = getenv('GITHUB_REF_NAME') ?: '';
if ($ghSha !== '' || $ghRefName !== '') {
    $v = '';
    if ($ghRefType === 'tag' && $ghRefName !== '') {
        $v = trim($ghRefName);
    } elseif ($ghSha !== '') {
        $v = substr($ghSha, 0, 7);
    }
    if ($v !== '') {
        define('APP_VERSION', $v);
        $persist($v);
        return;
    }
}

// 1) Baked file (written at build/deploy)
if (is_file($CACHE_FILE)) {
    $v = trim((string) @file_get_contents($CACHE_FILE));
    if ($v !== '') { define('APP_VERSION', $v); return; }
}

// 2) Non-prod fallback: use Git if present & allowed
$gitDir = $REPO_ROOT . '/.git';
if (!$isProd && (is_dir($gitDir) || is_file($gitDir))) {
    $v = detect_git_version($REPO_ROOT);
    if ($v) {
        // optional: never show "-dirty" anywhere
        $v = preg_replace('/-dirty$/', '', $v);
        $persist($v);
        define('APP_VERSION', $v);
        return;
    }
}

// 3) Final fallback so the constant is always defined
define('APP_VERSION', '0.0.0+local');

/**
 * Get version from Git tag/commit:
 * 1) git describe --tags --always --dirty --abbrev=7
 * 2) Windows fallback with common git.exe path
 * 3) Read .git/HEAD if exec disabled
 */
function detect_git_version(string $root): ?string {
    // a) shell path
    $cmd = 'git -C ' . escapeshellarg($root) . ' describe --tags --always --dirty --abbrev=7 2>&1';

    $disabled = array_map('trim', explode(',', (string) ini_get('disable_functions')));
    $shellOk  = function_exists('shell_exec') && !in_array('shell_exec', $disabled, true);

    if ($shellOk) {
        $out = @shell_exec($cmd);
        $v = trim((string) $out);
        if ($v !== '') return $v;

        // b) Windows fallback
        if (PHP_OS_FAMILY === 'Windows') {
            $gitWin = 'C:\Program Files\Git\bin\git.exe';
            if (is_file($gitWin)) {
                $cmd = escapeshellarg($gitWin) . ' -C ' . escapeshellarg($root) . ' describe --tags --always --dirty --abbrev=7 2>&1';
                $out = @shell_exec($cmd);
                $v = trim((string) $out);
                if ($v !== '') return $v;
            }
        }
    }

    // c) No-exec fallback: parse .git
    $gitDir = $root . '/.git';
    if (!is_dir($gitDir) && is_file($gitDir)) { // handle "gitdir: ..." pointer
        $pointer = trim((string) @file_get_contents($gitDir));
        if (preg_match('/^gitdir:\s*(.+)$/i', $pointer, $m)) {
            $p = $m[1];
            if (!preg_match('#^([A-Z]:\\\\|/)#i', $p)) $p = $root . DIRECTORY_SEPARATOR . $p;
            $gitDir = $p;
        }
    }
    $head = @file_get_contents($gitDir . '/HEAD');
    if ($head) {
        $head = trim($head);
        if (preg_match('#^ref:\s*(.+)$#', $head, $m)) {
            $refFile = $gitDir . '/' . $m[1];
            $hash = @file_get_contents($refFile);
            if ($hash) return substr(trim($hash), 0, 7);
        } else {
            return substr($head, 0, 7); // detached HEAD
        }
    }
    return null;
}
