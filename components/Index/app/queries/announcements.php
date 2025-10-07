<?php
declare(strict_types=1);

/**
 * Try to fetch announcements for a given Y-m-d date.
 * If none found, fall back to the most recent $limit announcements (any date).
 *
 * @return string[] announcement_details
 */
function q_get_announcements_with_fallback(mysqli $db, string $ymd, int $limit = 5): array {
    // 1) Try today's announcements
    $sqlToday = "SELECT announcement_details
                 FROM announcement
                 WHERE DATE(created) = ? AND delete_status = 0
                 ORDER BY created DESC";
    $out = [];
    if ($st = $db->prepare($sqlToday)) {
        $st->bind_param("s", $ymd);
        $st->execute();
        $res = $st->get_result();
        while ($row = $res->fetch_assoc()) {
            $out[] = (string)$row['announcement_details'];
        }
        $st->close();
    }

    // 2) If none for today, fall back to latest N (any date)
    if (!$out) {
        $sqlRecent = "SELECT announcement_details
                      FROM announcement
                      WHERE delete_status = 0
                      ORDER BY created DESC
                      LIMIT ?";
        if ($st = $db->prepare($sqlRecent)) {
            $st->bind_param("i", $limit);
            $st->execute();
            $res = $st->get_result();
            while ($row = $res->fetch_assoc()) {
                $out[] = (string)$row['announcement_details'];
            }
            $st->close();
        }
    }

    return $out;
}
