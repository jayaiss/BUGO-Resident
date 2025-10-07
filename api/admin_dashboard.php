<?php
if (basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'])) {
    http_response_code(403);
    require_once __DIR__ . '/../security/403.html';
    exit;
}
include 'class/session_timeout.php';
require_once __DIR__ . '/../include/connection.php';
$mysqli = db_connection();

$resCount = 0;
$eventsNext7 = 0;
$myUpcomingCount = 0;
$myNextAppt = null;

$today = (new DateTime('today'))->format('Y-m-d');
$weekAhead = (new DateTime('+7 days'))->format('Y-m-d');
$residentId = isset($_SESSION['id']) ? (int)$_SESSION['id'] : 0;

/* Residents count */
if ($resResult = $mysqli->query("SELECT COUNT(*) AS total FROM residents WHERE resident_delete_status = 0 ")) {
    $row = $resResult->fetch_assoc();
    $resCount = (int)($row['total'] ?? 0);
}

/* Events next 7 days */
$eventsStmt = $mysqli->prepare("
    SELECT COUNT(*) 
    FROM events 
    WHERE events_delete_status = 0 
      AND event_date BETWEEN ? AND ?
");
if ($eventsStmt) {
    $eventsStmt->bind_param('ss', $today, $weekAhead);
    $eventsStmt->execute();
    $eventsStmt->bind_result($eventsNext7);
    $eventsStmt->fetch();
    $eventsStmt->close();
}

/* My appointments from schedules */
$apptCountSql = "
    SELECT COUNT(*)
    FROM schedules
    WHERE appointment_delete_status = 0
      AND res_id = ?
      AND (status IS NULL OR status NOT IN ('Rejected','Released'))
      AND selected_date >= ?
";
$nextApptSql = "
    SELECT selected_date, selected_time, COALESCE(status,'Pending') AS status
    FROM schedules
    WHERE appointment_delete_status = 0
      AND res_id = ?
      AND (status IS NULL OR status NOT IN ('Released','Rejected'))
      AND selected_date >= ?
    ORDER BY selected_date ASC,
             STR_TO_DATE(SUBSTRING_INDEX(selected_time,'-',1), '%h:%i%p') ASC
    LIMIT 1
";

if ($residentId > 0) {
    if ($stmt = $mysqli->prepare($apptCountSql)) {
        $stmt->bind_param('is', $residentId, $today);
        if ($stmt->execute()) {
            $stmt->bind_result($myUpcomingCount);
            $stmt->fetch();
        }
        $stmt->close();
    }
    if ($stmt2 = $mysqli->prepare($nextApptSql)) {
        $stmt2->bind_param('is', $residentId, $today);
        if ($stmt2->execute()) {
            $res = $stmt2->get_result();
            $myNextAppt = $res->fetch_assoc() ?: null;
        }
        $stmt2->close();
    }
}
?>
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
<link rel="stylesheet" href="assets/css/dashboard.css">

<h2 class="fw-bold section-header"><i class="bi bi-house-door-fill me-2"></i>DASHBOARD</h2>

<div class="container mt-4">
<?php include 'components/dashboard/DashboardCard.php';?>
</div>

<script src="components/dashboard/js/dashboard.js"></script>
