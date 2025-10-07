<?php
declare(strict_types=1);

ini_set('display_errors', '1'); // set to 0 in production
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

if (basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'])) {
    http_response_code(403);
    require_once __DIR__ . '/../security/403.html';
    exit;
}

include 'class/session_timeout.php';
require_once __DIR__ . '/../include/connection.php';
$mysqli = db_connection();
$perPage = 5; // adjust as you like (8/10/12…)
$page    = max(1, (int)($_GET['pagenum'] ?? 1));

// Total rows (same WHERE/JOIN as data query)
$countSql = "
  SELECT COUNT(*) AS cnt
  FROM events e
  LEFT JOIN event_name en ON en.id = e.event_title
  WHERE e.events_delete_status = 0
    AND (en.status = 1 OR en.status IS NULL)
";
$totalRows = 0;
if ($res = $mysqli->query($countSql)) {
  $totalRows = (int)($res->fetch_assoc()['cnt'] ?? 0);
  $res->free();
}
$total_pages = max(1, (int)ceil($totalRows / $perPage));
if ($page > $total_pages) $page = $total_pages;
$offset = ($page - 1) * $perPage;

// ---- Fetch Events ----
$eventsQuery = "
  SELECT 
      en.event_name       AS title,
      e.event_description AS event_description,
      e.event_location    AS event_location,
      e.event_time        AS event_time,
      e.event_date        AS event_date
  FROM events e
  LEFT JOIN event_name en ON en.id = e.event_title
  WHERE e.events_delete_status = 0
    AND (en.status = 1 OR en.status IS NULL)
  ORDER BY e.event_date ASC, e.event_time ASC
  LIMIT ? OFFSET ?
";
$eventsResult = null;
if ($stmt = $mysqli->prepare($eventsQuery)) {
  $stmt->bind_param('ii', $perPage, $offset);
  $stmt->execute();
  $eventsResult = $stmt->get_result();
} else {
  $dbErr = $mysqli->error;
}

if ($eventsResult) {
    $today    = new DateTimeImmutable('today');
    $tomorrow = $today->modify('+1 day');

    while ($e = $eventsResult->fetch_assoc()) {
        $date = (string)($e['event_date'] ?? '');
        $time = (string)($e['event_time'] ?? '');

        $dateTs = $date ? strtotime($date) : false;
        $timeTs = $time ? strtotime($time) : false;

        $dateFmt = $dateTs ? date('F j, Y', $dateTs) : ($date ?: '—');
        $timeFmt = $timeTs ? date('h:i A', $timeTs) : ($time ?: '—');

        $label = 'Past';
        if ($dateTs) {
            $dOnly = DateTimeImmutable::createFromFormat('Y-m-d', date('Y-m-d', $dateTs));
            if ($dOnly == $today)        $label = 'Today';
            elseif ($dOnly == $tomorrow) $label = 'Tomorrow';
            elseif ($dOnly > $today)     $label = 'Upcoming';
        }

        $rows[] = [
            'title'   => (string)($e['title'] ?? ''),              // <- use alias
            'desc'    => (string)($e['event_description'] ?? ''),
            'loc'     => (string)($e['event_location'] ?? ''),
            'date'    => $date,
            'time'    => $time,
            'dateFmt' => $dateFmt,
            'timeFmt' => $timeFmt,
            'label'   => $label,
        ];
    }
}
$mysqli->close();

function badge_class(string $label): string {
    return match ($label) {
        'Today'    => 'bg-warning-subtle text-warning-emphasis border border-warning-subtle',
        'Tomorrow' => 'bg-info-subtle text-info-emphasis border border-info-subtle',
        'Upcoming' => 'bg-success-subtle text-success-emphasis border border-success-subtle',
        default    => 'bg-secondary-subtle text-secondary-emphasis border border-secondary-subtle',
    };
}
function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Event Calendar</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<link rel="stylesheet" href="assets/css/Event/Event.css?=v4">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet"> <!-- para sa log out and notif button-->


<style>
</style>
</head>
<body>
  <?php include 'components/Event/view/Event.php'; ?>
<!-- <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script> -->
<script src="components/Event/js/Event.js?=v4"></script>
</body>
</html>
