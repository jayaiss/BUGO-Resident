<?php
declare(strict_types=1);

/** @return array{full_name:string, profile_picture: ?string}|null */
function q_get_resident_profile(mysqli $db, int $residentId): ?array {
    $sql = "SELECT CONCAT(first_name, ' ', COALESCE(middle_name,''), ' ', last_name) AS full_name,
                   profile_picture
            FROM residents WHERE id = ? LIMIT 1";
    $st = $db->prepare($sql);
    $st->bind_param("i", $residentId);
    $st->execute();
    $res = $st->get_result();
    $row = $res->fetch_assoc() ?: null;
    $st->close();
    return $row;
}
?>