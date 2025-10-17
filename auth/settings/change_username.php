<?php
// auth/settings/change_username.php  (FIXED: referer-aware, no redirect loops)
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

if (session_status() === PHP_SESSION_NONE) { session_start(); }

require_once __DIR__ . '/../../include/connection.php';
$mysqli = db_connection();
require_once __DIR__ . '/../../include/encryption.php'; // for enc_page()

// Flash helper (fallback)
if (!function_exists('set_flash')) {
    function set_flash($key, $val) { $_SESSION['flash'][$key] = $val; }
}

/* ------------------ Build canonical Settings URL ------------------ *
 * Prefer the exact page you came from (router view), else fallback.  */
$settingsUrl = '/auth/settings/settings.php'; // default fallback

$ref = $_SERVER['HTTP_REFERER'] ?? '';
if ($ref) {
    $refPath = parse_url($ref, PHP_URL_PATH) ?? '';
    // Accept router or standalone settings as valid referers
    if (preg_match('~/(index_Admin\.php|auth/settings/settings\.php)$~i', $refPath)) {
        $settingsUrl = $ref;
    }
}

// If your app uses encrypted page param and there was no valid referer, use it
if ($settingsUrl === '/auth/settings/settings.php' && function_exists('enc_page')) {
    $settingsUrl = enc_page('settings_section', 'settings.php?'); // /auth/settings/settings.php?page=<enc>
}

// NEVER redirect back to this handler
$handlerPath = '/auth/settings/change_username.php';
if (parse_url($settingsUrl, PHP_URL_PATH) === $handlerPath) {
    $settingsUrl = '/auth/settings/settings.php';
    if (function_exists('enc_page')) {
        $settingsUrl = enc_page('settings_section', 'settings.php?');
    }
}

$loggedInResidentId = $_SESSION['id'] ?? null;
if (!$loggedInResidentId) {
    header('Location: /index.php');
    exit();
}

// Only allow POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    set_flash('message', ['type' => 'err', 'text' => 'Invalid request method.']);
    header("Location: {$settingsUrl}");
    exit();
}

// CSRF
if (!hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? '')) {
    set_flash('message', ['type' => 'err', 'text' => 'Invalid session. Please try again.']);
    header("Location: {$settingsUrl}");
    exit();
}
// Rotate CSRF
$_SESSION['csrf'] = bin2hex(random_bytes(32));

$currentPassword = trim($_POST['current_password'] ?? '');
$newUsername     = trim($_POST['new_username'] ?? '');

if ($currentPassword === '' || $newUsername === '') {
    set_flash('message', ['type' => 'err', 'text' => 'All fields are required.']);
    header("Location: {$settingsUrl}");
    exit();
}
if (!preg_match('/^[A-Za-z0-9._-]{4,30}$/', $newUsername)) {
    set_flash('message', ['type' => 'err', 'text' => 'Invalid username format.']);
    header("Location: {$settingsUrl}");
    exit();
}

// Fetch current hash + username
$stmt = $mysqli->prepare("SELECT username, password FROM residents WHERE id = ?");
$stmt->bind_param('i', $loggedInResidentId);
$stmt->execute();
$stmt->bind_result($currentUsername, $passwordHash);
if (!$stmt->fetch()) {
    $stmt->close();
    set_flash('message', ['type' => 'err', 'text' => 'Account not found.']);
    header("Location: {$settingsUrl}");
    exit();
}
$stmt->close();

// Verify password
if (!password_verify($currentPassword, $passwordHash)) {
    set_flash('message', ['type' => 'err', 'text' => 'Incorrect password.']);
    header("Location: {$settingsUrl}");
    exit();
}

// No-op check
if (strcasecmp($newUsername, $currentUsername) === 0) {
    set_flash('message', ['type' => 'ok', 'text' => 'That is already your username.']);
    header("Location: {$settingsUrl}");
    exit();
}

// Unique username
$stmt = $mysqli->prepare("SELECT id FROM residents WHERE username = ? LIMIT 1");
$stmt->bind_param('s', $newUsername);
$stmt->execute();
$stmt->store_result();
if ($stmt->num_rows > 0) {
    $stmt->close();
    set_flash('message', ['type' => 'err', 'text' => 'Username is already taken.']);
    header("Location: {$settingsUrl}");
    exit();
}
$stmt->close();

// Update username
$stmt = $mysqli->prepare("UPDATE residents SET username = ? WHERE id = ?");
$stmt->bind_param('si', $newUsername, $loggedInResidentId);
$ok = $stmt->execute();
$stmt->close();

if (!$ok) {
    set_flash('message', ['type' => 'err', 'text' => 'Failed to update username. Please try again.']);
    header("Location: {$settingsUrl}");
    exit();
}

// Refresh session cache
$_SESSION['username'] = $newUsername;

set_flash('message', ['type' => 'ok', 'text' => 'Username updated successfully.']);
header("Location: {$settingsUrl}");
exit();
