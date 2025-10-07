<?php
// security/remember.php
if (!defined('REMEMBER_COOKIE_NAME')) {
  define('REMEMBER_COOKIE_NAME', 'BUGO_RME');
  define('REMEMBER_LIFETIME_DAYS', 30);
}

function base64url_encode($s){ return rtrim(strtr(base64_encode($s), '+/', '-_'), '='); }
function base64url_decode($s){ return base64_decode(strtr($s, '-_', '+/')); }

function is_https() {
  if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') return true;
  if (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') return true;
  return false;
}

function remember_cookie_params() {
  return [
    'expires'  => time() + (REMEMBER_LIFETIME_DAYS * 86400),
    'path'     => '/',
    'secure'   => is_https(),        // must be true in prod (HTTPS)
    'httponly' => true,
    'samesite' => 'Lax',
  ];
}

function remember_clear_cookie() {
  if (!isset($_COOKIE[REMEMBER_COOKIE_NAME])) return;
  setcookie(REMEMBER_COOKIE_NAME, '', [
    'expires'  => time() - 3600,
    'path'     => '/',
    'secure'   => is_https(),
    'httponly' => true,
    'samesite' => 'Lax',
  ]);
  unset($_COOKIE[REMEMBER_COOKIE_NAME]);
}

function remember_generate_pair() {
  $selector  = base64url_encode(random_bytes(12));   // 16 -> 24 b64url chars
  $validator = base64url_encode(random_bytes(32));   // 32 bytes entropy
  return [$selector, $validator];
}

function remember_issue_token(mysqli $db, int $residentId, string $ua): void {
  // optional: cap devices per user (e.g., 10)
  $db->query("DELETE FROM res_auth_remember_tokens WHERE resident_id={$residentId} AND expires_at < NOW()");
  $res = $db->query("SELECT COUNT(*) AS c FROM res_auth_remember_tokens WHERE resident_id={$residentId}");
  if ($res && ($row = $res->fetch_assoc()) && (int)$row['c'] >= 10) {
    // remove oldest
    $db->query("DELETE FROM res_auth_remember_tokens WHERE resident_id={$residentId} ORDER BY created_at ASC LIMIT 1");
  }

  [$selector, $validator] = remember_generate_pair();
  $hash = hash('sha256', $validator, false);
  $uaHash = hash('sha256', $ua ?? '', false);
  $exp = (new DateTimeImmutable('now'))->modify('+'.REMEMBER_LIFETIME_DAYS.' days')->format('Y-m-d H:i:s');

  $stmt = $db->prepare("INSERT INTO res_auth_remember_tokens (resident_id, selector, token_hash, user_agent_hash, expires_at) VALUES (?, ?, ?, ?, ?)");
  $stmt->bind_param('issss', $residentId, $selector, $hash, $uaHash, $exp);
  $stmt->execute();
  $stmt->close();

  $cookieValue = $selector . ':' . $validator;
  setcookie(REMEMBER_COOKIE_NAME, $cookieValue, remember_cookie_params());
}

function remember_rotate_token(mysqli $db, string $selector, string $ua): ?string {
  // rotate validator only (update token_hash, expires)
  [$newSel, $newVal] = remember_generate_pair(); // rotate selector too (hard rotate)
  $newHash = hash('sha256', $newVal, false);
  $uaHash = hash('sha256', $ua ?? '', false);
  $exp = (new DateTimeImmutable('now'))->modify('+'.REMEMBER_LIFETIME_DAYS.' days')->format('Y-m-d H:i:s');

  // replace the row atomically by selector
  $stmt = $db->prepare("UPDATE res_auth_remember_tokens SET selector=?, token_hash=?, user_agent_hash=?, expires_at=? WHERE selector=?");
  $stmt->bind_param('sssss', $newSel, $newHash, $uaHash, $exp, $selector);
  $ok = $stmt->execute();
  $stmt->close();

  if (!$ok || $db->affected_rows < 1) return null;

  $cookieValue = $newSel . ':' . $newVal;
  setcookie(REMEMBER_COOKIE_NAME, $cookieValue, remember_cookie_params());
  return $cookieValue;
}

function remember_purge_expired(mysqli $db): void {
  $db->query("DELETE FROM res_auth_remember_tokens WHERE expires_at < NOW()");
}

function remember_delete_by_selector(mysqli $db, string $selector): void {
  $stmt = $db->prepare("DELETE FROM res_auth_remember_tokens WHERE selector=?");
  $stmt->bind_param('s', $selector);
  $stmt->execute();
  $stmt->close();
}

function remember_delete_all_for_user(mysqli $db, int $residentId): void {
  $stmt = $db->prepare("DELETE FROM res_auth_remember_tokens WHERE resident_id=?");
  $stmt->bind_param('i', $residentId);
  $stmt->execute();
  $stmt->close();
}

function remember_find_and_verify(mysqli $db, string $cookie, string $ua): ?array {
  // returns ['resident_id'=>..,'selector'=>..] or null
  if (strpos($cookie, ':') === false) return null;
  [$selector, $validator] = explode(':', $cookie, 2);
  if (!$selector || !$validator) return null;

  $stmt = $db->prepare("SELECT resident_id, selector, token_hash, user_agent_hash, expires_at FROM res_auth_remember_tokens WHERE selector=? LIMIT 1");
  $stmt->bind_param('s', $selector);
  $stmt->execute();
  $res = $stmt->get_result();
  $row = $res ? $res->fetch_assoc() : null;
  $stmt->close();

  if (!$row) return null;
  if (strtotime($row['expires_at']) <= time()) {
    remember_delete_by_selector($db, $selector);
    return null;
  }

  $calc = hash('sha256', $validator, false);
  if (!hash_equals($row['token_hash'], $calc)) {
    // possible theft -> invalidate
    remember_delete_by_selector($db, $selector);
    return null;
  }

  // bind partially to UA (prevents trivial reuse on different device)
  $uaHash = hash('sha256', $ua ?? '', false);
  if (!hash_equals($row['user_agent_hash'], $uaHash)) {
    remember_delete_by_selector($db, $selector);
    return null;
  }

  return ['resident_id' => (int)$row['resident_id'], 'selector' => $row['selector']];
}
