<?php
declare(strict_types=1);

/** @return array{telephone_number:string, mobile_number:string} */
function q_get_barangay_contacts(mysqli $db): array {
    $out = ['telephone_number' => 'No telephone number found', 'mobile_number' => 'No mobile number found'];
    $res = $db->query("SELECT telephone_number, mobile_number FROM barangay_info WHERE id = 1");
    if ($res && $res->num_rows > 0) {
        $row = $res->fetch_assoc();
        $out['telephone_number'] = (string)($row['telephone_number'] ?? $out['telephone_number']);
        $out['mobile_number']    = (string)($row['mobile_number'] ?? $out['mobile_number']);
    }
    return $out;
}

/** Compute display-friendly barangay name */
function q_get_barangay_display_name(mysqli $db): string {
    $sql = "SELECT b.barangay_name
            FROM barangay_info bi
            LEFT JOIN barangay b ON bi.barangay_id = b.barangay_id
            WHERE bi.id = 1";
    $name = "NO BARANGAY FOUND";
    if ($res = $db->query($sql)) {
        if ($row = $res->fetch_assoc()) {
            $bn = (string)($row['barangay_name'] ?? '');
            $bn = preg_replace('/\s*\(Pob\.\)\s*/i', '', $bn);
            if (stripos($bn, 'Barangay') !== false) {
                $name = $bn;
            } elseif (stripos($bn, 'Pob') !== false && stripos($bn, 'Poblacion') === false) {
                $name = "Poblacion " . $bn;
            } else {
                $name = (stripos($bn, 'Poblacion') !== false) ? $bn : ("Barangay " . $bn);
            }
        }
    }
    return $name;
}
?>