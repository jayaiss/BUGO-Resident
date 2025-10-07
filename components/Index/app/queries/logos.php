<?php
declare(strict_types=1);

/** @return array|null single active barangay logo row or null */
function q_get_active_barangay_logo(mysqli $db): ?array {
    $res = $db->query("SELECT * FROM logos WHERE logo_name LIKE '%Barangay%' AND status = 'active' LIMIT 1");
    if ($res && $res->num_rows > 0) return $res->fetch_assoc();
    return null;
}
?>