<?php
declare(strict_types=1);

/**
 * Insert feedback; returns ['ok'=>bool,'duplicate'=>bool,'error'=>string|null]
 * Throws no exceptions — handles duplicate key (1062) internally.
 */
function q_insert_feedback(mysqli $db, array $data): array {
    $sql = "
      INSERT INTO feedback
        (resident_id, feedback_text, rating, tags, contact_name, contact_email,
         allow_followup, ua, ip, dedupe_hash, created_at)
      VALUES (?, ?, ?, ?, ?, ?, ?, ?, INET6_ATON(?), ?, NOW())
    ";
    $st = $db->prepare($sql);
    if (!$st) return ['ok'=>false,'duplicate'=>false,'error'=>$db->error];

    $st->bind_param(
        "isisssisss",
        $data['resident_id'],
        $data['feedback_text'],
        $data['rating'],
        $data['tags'],
        $data['contact_name'],
        $data['contact_email'],
        $data['allow_followup'],
        $data['ua'],
        $data['ip'],
        $data['dedupe_hash']
    );

    try {
        $st->execute();
        $st->close();
        return ['ok'=>true,'duplicate'=>false,'error'=>null];
    } catch (mysqli_sql_exception $e) {
        $dup = ((int)$e->getCode() === 1062);
        $st->close();
        return ['ok'=>false,'duplicate'=>$dup,'error'=>$e->getMessage()];
    }
}
?>