<?php
declare(strict_types=1);

require_once __DIR__ . '/encryption.php';

/**
 * Safeguards: ensure INDEX_URL exists (encryption.php should define it,
 * but we fall back gracefully if not).
 */
if (!defined('OFFICE_BASE_URL')) {
    define('OFFICE_BASE_URL', (isset($_SERVER['HTTP_HOST']) && strpos($_SERVER['HTTP_HOST'], 'localhost') !== false)
        ? 'http://localhost/BUGO-Resident'
        : 'https://bugoportal.site'); // no trailing slash
}
if (!defined('INDEX_FILE')) {
    define('INDEX_FILE', '/index_Admin.php'); // absolute, correct case
}
if (!defined('INDEX_URL')) {
    define('INDEX_URL', OFFICE_BASE_URL . INDEX_FILE); // full URL
}

// Helpers
function route_page(string $page): string {
    // Always route via full index URL (works from any folder)
    return enc_page($page, '', INDEX_URL);
}
function abs_url(string $path): string {
    // Append a root-absolute path to the environment base URL
    return OFFICE_BASE_URL . $path; // OFFICE_BASE_URL has no trailing slash
}

$redirects = [
    // Pages routed via index (absolute URLs)
    'dashboard'             => route_page('admin_dashboard'),
    'schedule_appointment'  => route_page('schedule_appointment'),
    'resident_appointments' => route_page('resident_appointment'),
    'profile_api'           => route_page('resident_profile'),
    'cedula'                => route_page('cedula'),
    'upload_cedula_form'    => route_page('upload_cedula_form'),
    'homepage'              => route_page('homepage'),
    'event_calendar'        => route_page('event_calendar'),

    // Direct files (absolute URLs; no router)
    'logout'                => abs_url('/logout.php'),
    'cp_2fa'                => abs_url('/auth/cp_2fa.php'),
    'upload_profile'        => abs_url('/class/upload_profile.php'),

    // API / called from /class variants (no "../" needed since we use absolute URLs)
    'dashboard_api'         => route_page('admin_dashboard'),
    'resident_appointment'  => route_page('resident_appointment'),
    'profile'               => route_page('resident_profile'),
];

function get_redirect_url(string $key, bool $isApi = false): string {
    global $redirects;
    $lookup = $isApi ? "{$key}_api" : $key;
    return $redirects[$lookup] ?? route_page('admin_dashboard');
}
