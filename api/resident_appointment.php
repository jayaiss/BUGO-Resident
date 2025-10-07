<?php
declare(strict_types=1);

ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

// Block direct access if this is a view-only include file (optional)
// If this file IS your main route, keep this guard commented out.
if (basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'])) {
    // http_response_code(403);
    // require_once __DIR__ . '/../security/403.html';
    // exit;
}

require_once __DIR__ . '/../include/connection.php';
include 'class/session_timeout.php';

$mysqli = db_connection();

$user_id = $_SESSION['id'] ?? null;
if (!$user_id) {
    header('Location: index.php');
    exit;
}

/* ----------------------------
   Pagination + Filters (GET)
---------------------------- */
$perPage = 10; // adjust page size
$page    = max(1, (int)($_GET['pagenum'] ?? 1));

$qParam  = trim($_GET['q']      ?? '');
$sParam  = trim($_GET['status'] ?? '');  // exact match
$cParam  = trim($_GET['cert']   ?? '');
$showAll = ($_GET['showall'] ?? '') === '1';

$qLower = strtolower($qParam);
$sLower = strtolower($sParam);
$cLower = strtolower($cParam);

$qLike = '%' . $qLower . '%';
$cLike = '%' . $cLower . '%';

/* ----------------------------
   COUNT (with filters)
---------------------------- */
$countSql = "
  SELECT
    (
      SELECT COUNT(*)
      FROM schedules s
      WHERE s.res_id = ?
        AND s.appointment_delete_status = 0
        AND (? = '' OR LOWER(CONCAT_WS(' ',
              COALESCE(s.certificate, s.purpose, 'Barangay Certificate'),
              s.status,
              s.tracking_number)) LIKE ?)
        AND (? = '' OR LOWER(TRIM(s.status)) = ?)
        AND (? = '' OR LOWER(COALESCE(s.certificate, s.purpose, 'Barangay Certificate')) LIKE ?)
    )
    +
    (
      SELECT COUNT(*)
      FROM cedula c
      WHERE c.res_id = ?
        AND c.cedula_delete_status = 0
        AND (? = '' OR LOWER(CONCAT_WS(' ',
              'Cedula',
              c.cedula_status,
              c.tracking_number)) LIKE ?)
        AND (? = '' OR LOWER(TRIM(c.cedula_status)) = ?)
        AND (? = '' OR LOWER('Cedula') LIKE ?)
    )
    +
    (
      SELECT COUNT(*)
      FROM urgent_cedula_request u
      WHERE u.res_id = ?
        AND u.cedula_delete_status = 0
        AND (? = '' OR LOWER(CONCAT_WS(' ',
              'Cedula',   -- Adjusted query to match Cedula structure
              u.cedula_status,
              u.tracking_number)) LIKE ?)
        AND (? = '' OR LOWER(TRIM(u.cedula_status)) = ?)
    )
    +
    (
      SELECT COUNT(*)
      FROM urgent_request u
      WHERE u.res_id = ?
        AND u.urgent_delete_status = 0
        AND (? = '' OR LOWER(CONCAT_WS(' ',
              u.certificate,
              u.status,
              u.tracking_number)) LIKE ?)
        AND (? = '' OR LOWER(TRIM(u.status)) = ?)
    ) AS cnt
";

$totalRows = 0;
$stmt = $mysqli->prepare($countSql);
if (!$stmt) {
    die('Prepare failed (count): ' . $mysqli->error);
}

// Correct number of bind parameters (32 placeholders = 32 bind parameters)
$stmt->bind_param(
    'issssssissssssissssissss', // 24 chars - CORRECT
    // schedules (7)
    $user_id, $qLower, $qLike, $sLower, $sLower, $cLower, $cLike,
    // cedula (7)
    $user_id, $qLower, $qLike, $sLower, $sLower, $cLower, $cLike,
    // urgent_cedula_request (5)
    $user_id, $qLower, $qLike, $sLower, $sLower,
    // urgent_request (5)
    $user_id, $qLower, $qLike, $sLower, $sLower
);
$stmt->execute();
$totalRows = (int)($stmt->get_result()->fetch_assoc()['cnt'] ?? 0);
$stmt->close();

/* ----------------------------
   Paging math (overridden by show-all)
---------------------------- */
$total_pages = max(1, (int)ceil($totalRows / $perPage));
if ($page > $total_pages) $page = $total_pages;
$offset = max(0, ($page - 1) * $perPage);

$filtersActive = ($qParam !== '' || $sParam !== '' || $cParam !== '');
if ($filtersActive || $showAll) {
    // return ALL matches on one page
    $perPage = max(1, $totalRows);
    $page = 1;
    $offset = 0;
    $total_pages = 1;
}

/* ----------------------------
   DATA (with filters) + LIMIT/OFFSET
---------------------------- */
$sql = "
  SELECT * FROM (
      SELECT 
          COALESCE(s.certificate, s.purpose, 'Barangay Certificate') AS cert_raw,
          s.selected_date, 
          s.selected_time, 
          s.tracking_number, 
          s.status
      FROM schedules s
      WHERE s.res_id = ?
        AND s.appointment_delete_status = 0
        AND (? = '' OR LOWER(CONCAT_WS(' ',
              COALESCE(s.certificate, s.purpose, 'Barangay Certificate'),
              s.status,
              s.tracking_number)) LIKE ?)
        AND (? = '' OR LOWER(TRIM(s.status)) = ?)
        AND (? = '' OR LOWER(COALESCE(s.certificate, s.purpose, 'Barangay Certificate')) LIKE ?)

      UNION ALL

      SELECT 
          'Cedula' AS cert_raw, 
          c.appointment_date  AS selected_date, 
          c.appointment_time  AS selected_time, 
          c.tracking_number, 
          c.cedula_status     AS status
      FROM cedula c
      WHERE c.res_id = ?
        AND c.cedula_delete_status = 0
        AND (? = '' OR LOWER(CONCAT_WS(' ',
              'Cedula',
              c.cedula_status,
              c.tracking_number)) LIKE ?)
        AND (? = '' OR LOWER(TRIM(c.cedula_status)) = ?)
        AND (? = '' OR LOWER('Cedula') LIKE ?)

      UNION ALL

      SELECT 
          'Cedula' AS cert_raw, 
          ucr.appointment_date  AS selected_date, 
          NULL                  AS selected_time, -- <-- FIX: Add this NULL placeholder
          ucr.tracking_number, 
          ucr.cedula_status     AS status         -- <-- Added alias for consistency
      FROM urgent_cedula_request ucr
      WHERE ucr.res_id = ? 
        AND ucr.cedula_delete_status = 0 
        AND (? = '' OR LOWER(CONCAT_WS(' ', 
                'Cedula', 
                ucr.cedula_status, 
                ucr.tracking_number)) LIKE ?) 
        AND (? = '' OR LOWER(TRIM(ucr.cedula_status)) = ?)

      UNION ALL

      SELECT 
          u.certificate AS cert_raw,
          u.selected_date,
          u.selected_time,
          u.tracking_number,
          u.status
      FROM urgent_request u
      WHERE u.res_id = ?
        AND u.urgent_delete_status = 0
        AND (? = '' OR LOWER(CONCAT_WS(' ',
              u.certificate,  -- Assuming 'certificate' exists
              u.status,
              u.tracking_number)) LIKE ?)
        AND (? = '' OR LOWER(TRIM(u.status)) = ?)
  ) t
  ORDER BY t.selected_date DESC, t.selected_time DESC
  LIMIT ? OFFSET ?
";
$stmt = $mysqli->prepare($sql);
if (!$stmt) {
    die('Prepare failed (data): ' . $mysqli->error);
}

$stmt->bind_param(
    'issssssissssssissssissssii', // 26 chars - CORRECT
    // schedules (7)
    $user_id, $qLower, $qLike, $sLower, $sLower, $cLower, $cLike,
    // cedula (7)
    $user_id, $qLower, $qLike, $sLower, $sLower, $cLower, $cLike,
    // urgent_cedula_request (5)
    $user_id, $qLower, $qLike, $sLower, $sLower,
    // urgent_request (5)
    $user_id, $qLower, $qLike, $sLower, $sLower,
    // paging (2)
    $perPage, $offset
);
$stmt->execute();
$result = $stmt->get_result();

/* ----------------------------
   Normalize rows
---------------------------- */
$rows = [];
$today = new DateTimeImmutable('today');
while ($r = $result->fetch_assoc()) {
    $date   = (string)($r['selected_date'] ?? '');
    $time   = (string)($r['selected_time'] ?? '');
    $status = trim((string)$r['status'] ?? '');

    $cert = normalize_certificate((string)($r['cert_raw'] ?? $r['certificate'] ?? ''));

    $rows[] = [
        'certificate' => $cert,
        'date'        => $date,
        'time'        => $time,
        'tracking'    => (string)($r['tracking_number'] ?? ''),
        'status'      => $status,
        'is_future'   => (strtotime($date) ?: 0) >= $today->getTimestamp(),
    ];
}
$stmt->close();
$mysqli->close();

/* ----------------------------
   Helpers
---------------------------- */
function status_class(string $s): string {
    $k = strtolower(trim($s));

    // Special style for "approved captain" (must appear before plain 'approved')
    if (preg_match('/\bapproved\s*captain\b/i', $s)) {
        return 'bg-teal-subtle text-teal-emphasis border border-teal-subtle';
    }

    return match (true) {
        str_contains($k, 'approved')  => 'bg-success-subtle text-success-emphasis border border-success-subtle',
        str_contains($k, 'released')  => 'bg-primary-subtle text-primary-emphasis border border-primary-subtle',
        str_contains($k, 'pending')   => 'bg-warning-subtle text-warning-emphasis border border-warning-subtle',
        str_contains($k, 'rejected'),
        str_contains($k, 'declined')  => 'bg-danger-subtle text-danger-emphasis border border-danger-subtle',
        default                       => 'bg-secondary-subtle text-secondary-emphasis border border-secondary-subtle',
    };
}

function cert_class(string $c): string {
    $k = strtolower($c);
    return $k === 'cedula'
        ? 'bg-info-subtle text-info-emphasis border border-info-subtle'
        : 'bg-teal-subtle text-teal-emphasis border border-teal-subtle';
}

function normalize_certificate(string $raw): string {
    $k = strtolower(trim($raw));
    return match (true) {
        str_contains($k, 'cedula')                      => 'Cedula',
        str_contains($k, 'clearance')                   => 'Barangay Clearance',
        str_contains($k, 'indigency')                   => 'Barangay Indigency',
        str_contains($k, 'residency')                   => 'Barangay Residency',
        str_contains($k, 'beso') || str_contains($k, 'scholar') => 'BESO Application',
        default                                         => 'Barangay Certificate',
    };
}

function pretty_status(string $s): string {
    $s = trim($s);
    $s = preg_replace('/[_\-]+/',' ', $s);
    $s = preg_replace('/([a-z])([A-Z])/', '$1 $2', $s);
    $s = preg_replace('/\s*captain\s*/i', ' Captain', $s);
    $s = preg_replace('/\s+/', ' ', $s);
    return ucwords(strtolower($s));
}

// For escaping in HTML
function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>My Appointments</title>

<!-- Light CSP for safer embeds -->
<meta http-equiv="Content-Security-Policy"
      content="default-src 'self' blob: data:; 
               img-src 'self' data: https:; 
               style-src 'self' https://cdn.jsdelivr.net 'unsafe-inline'; 
               script-src 'self' https://cdn.jsdelivr.net; 
               font-src https://cdn.jsdelivr.net data:;">


<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">

<link rel="stylesheet" href="assets/css/MyApp/MyApp.css">
</head>
<body class="bg-light">
<?php include 'components/Myappointments/View/ViewApp.php'; ?>
<!-- <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script> -->
<script src="components/Myappointments/js/MyApp.js"></script>
</body>
</html>
