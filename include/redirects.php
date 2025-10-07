<?php
declare(strict_types=1);

require_once __DIR__ . '/encryption.php';

// Make index path ABSOLUTE so it never inherits /auth/
// const INDEX_FILE = '/index_Admin.php';

$redirects = [
    // Pages routed via index (from anywhere)
    'dashboard'             => enc_page('admin_dashboard',        '',   INDEX_FILE),
    'schedule_appointment'  => enc_page('schedule_appointment',   '',   INDEX_FILE),
    'resident_appointments' => enc_page('resident_appointment',   '',   INDEX_FILE),
    'profile_api'           => enc_page('resident_profile',       '',   INDEX_FILE),
    'cedula'                => enc_page('cedula',                 '',   INDEX_FILE),
    'upload_cedula_form'    => enc_page('upload_cedula_form',     '',   INDEX_FILE),
    'homepage'              => enc_page('homepage',               '',   INDEX_FILE),
    'event_calendar'        => enc_page('event_calendar',         '',   INDEX_FILE),
    'logout'   => '/logout.php',

    // Direct file (no router)
    'cp_2fa'                => '/auth/cp_2fa.php',

    // API/“called from /class” variants (go up one level first)
    'dashboard_api'         => enc_page('admin_dashboard',       '../', INDEX_FILE),
    'resident_appointment'  => enc_page('resident_appointment',  '../', INDEX_FILE),
    'profile'               => enc_page('resident_profile',      '../', INDEX_FILE),

    // Direct endpoint (no router)
    'upload_profile'        => '/class/upload_profile.php',
];

function get_redirect_url(string $key, bool $isApi = false): string {
    global $redirects;
    $lookup = $isApi ? "{$key}_api" : $key;
    return $redirects[$lookup] ?? enc_page('admin_dashboard', '', INDEX_FILE);
}
