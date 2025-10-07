<?php
declare(strict_types=1);

const ENCRYPTION_KEY = 'thisIsA32ByteLongSecretKey123456'; // 32 bytes
const INDEX_FILE     = '/index_Admin.php';                 // absolute, correct case

// ---- base64url
function b64u_encode(string $bin): string {
    return rtrim(strtr(base64_encode($bin), '+/', '-_'), '=');
}
function b64u_decode(string $b64u): string {
    $pad = 4 - (strlen($b64u) % 4);
    if ($pad < 4) $b64u .= str_repeat('=', $pad);
    return base64_decode(strtr($b64u, '-_', '+/'));
}

// ---- crypto v2 (GCM) + legacy v1 (CBC)
function encrypt_v2(string $plaintext, string $key = ENCRYPTION_KEY): string {
    $k  = hash('sha256', $key, true);
    $iv = random_bytes(12);
    $tag = '';
    $ct = openssl_encrypt($plaintext, 'aes-256-gcm', $k, OPENSSL_RAW_DATA, $iv, $tag, '');
    if ($ct === false) throw new RuntimeException('Encrypt failed');
    return 'v2.' . b64u_encode($iv) . '.' . b64u_encode($tag) . '.' . b64u_encode($ct);
}
function decrypt_v2(string $token, string $key = ENCRYPTION_KEY): string|false {
    $k = hash('sha256', $key, true);
    $parts = explode('.', $token, 4);
    if (count($parts) !== 4 || $parts[0] !== 'v2') return false;
    [, $ivb, $tagb, $ctb] = $parts;
    return openssl_decrypt(
        b64u_decode($ctb), 'aes-256-gcm', $k, OPENSSL_RAW_DATA,
        b64u_decode($ivb), b64u_decode($tagb), ''
    );
}
function encrypt_v1(string $plaintext, string $key = ENCRYPTION_KEY): string {
    $k  = hash('sha256', $key, true);
    $iv = random_bytes(16);
    $ct = openssl_encrypt($plaintext, 'aes-256-cbc', $k, OPENSSL_RAW_DATA, $iv);
    return 'v1.' . b64u_encode($iv . $ct);
}
function decrypt_v1(string $token, string $key = ENCRYPTION_KEY): string|false {
    $k   = hash('sha256', $key, true);
    $raw = b64u_decode($token);
    if (strlen($raw) <= 16) return false;
    return openssl_decrypt(substr($raw, 16), 'aes-256-cbc', $k, OPENSSL_RAW_DATA, substr($raw, 0, 16));
}
function encrypt(string $plaintext): string { return encrypt_v2($plaintext); }
function decrypt(string $token): string|false {
    if (str_starts_with($token, 'v2.')) return decrypt_v2($token);
    if (str_starts_with($token, 'v1.')) return decrypt_v1(substr($token, 3));
    return decrypt_v1($token); // legacy
}

// ---- URL builders (root-absolute, no subfolder)
function enc_page(string $pageName, string $extraQuery = '', string $index = INDEX_FILE): string {
    $url = $index . '?page=' . rawurlencode(encrypt($pageName));
    if ($extraQuery !== '') $url .= '&' . ltrim($extraQuery, '&');
    return $url;
}

// kept for compatibility; now just calls enc_page()
function enc_url(string $pageName, string $index = INDEX_FILE): string {
    return enc_page($pageName, '', $index);
}

// current script (absolute) ?page=...
function enc_self(string $pageName): string {
    return $_SERVER['SCRIPT_NAME'] . '?page=' . rawurlencode(encrypt($pageName));
}
