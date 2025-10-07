<!DOCTYPE html>
<html lang="en">
<?php
require_once __DIR__ . '/../include/connection.php';
$mysqli = db_connection();

/* --- Events --- */
$eventsQuery = "
    SELECT e.id, en.event_name AS event_title, e.event_description, e.event_date, e.event_time, e.event_location, e.event_image, e.image_type 
    FROM events e
    JOIN event_name en ON e.event_title = en.Id
    WHERE e.events_delete_status = 0 
    ORDER BY e.event_date DESC 
    LIMIT 6
";
$eventsResult = $mysqli->query($eventsQuery);
if (!$eventsResult) { die('Query failed: ' . $mysqli->error); }

/* --- Active residents --- */
$res_query = "SELECT COUNT(*) AS active_residents FROM residents WHERE resident_delete_status = 0";
$res_result = $mysqli->query($res_query);
$active_residents = $res_result->fetch_assoc()['active_residents'] ?? 0;

/* --- Total requests --- */
$total_requests_query = "
    SELECT SUM(total) AS total_count
    FROM (
        SELECT COUNT(*) AS total FROM cedula WHERE cedula_delete_status = 0
        UNION ALL
        SELECT COUNT(*) AS total FROM schedules WHERE appointment_delete_status = 0
        UNION ALL
        SELECT COUNT(*) AS total FROM urgent_request WHERE urgent_delete_status = 0
        UNION ALL
        SELECT COUNT(*) AS total FROM urgent_cedula_request WHERE cedula_delete_status = 0
        UNION ALL
        SELECT COUNT(*) AS total FROM archived_cedula WHERE cedula_delete_status = 0
        UNION ALL
        SELECT COUNT(*) AS total FROM archived_schedules WHERE appointment_delete_status = 0
        UNION ALL
        SELECT COUNT(*) AS total FROM archived_urgent_request WHERE urgent_delete_status = 0
        UNION ALL
        SELECT COUNT(*) AS total FROM archived_urgent_cedula_request WHERE cedula_delete_status = 0
    ) AS all_requests
";
$total_requests_result = $mysqli->query($total_requests_query);
$total_requests = $total_requests_result->fetch_assoc()['total_count'] ?? 0;

/* --- Issued certs --- */
$issued_certs_query = "
    SELECT SUM(issued) AS issued_count
    FROM (
        SELECT COUNT(*) AS issued FROM cedula WHERE cedula_status = 'Issued' AND cedula_delete_status = 0
        UNION ALL
        SELECT COUNT(*) AS issued FROM schedules WHERE status = 'Issued' AND appointment_delete_status = 0
        UNION ALL
        SELECT COUNT(*) AS issued FROM urgent_request WHERE status = 'Issued' AND urgent_delete_status = 0
        UNION ALL
        SELECT COUNT(*) AS issued FROM urgent_cedula_request WHERE cedula_status = 'Issued' AND cedula_delete_status = 0
        UNION ALL
        SELECT COUNT(*) AS issued FROM archived_cedula WHERE cedula_status = 'Issued' AND cedula_delete_status = 0
        UNION ALL
        SELECT COUNT(*) AS issued FROM archived_schedules WHERE status = 'Issued' AND appointment_delete_status = 0
        UNION ALL
        SELECT COUNT(*) AS issued FROM archived_urgent_request WHERE urgent_delete_status = 0
        UNION ALL
        SELECT COUNT(*) AS issued FROM archived_urgent_cedula_request WHERE cedula_status = 'Issued' AND cedula_delete_status = 0
    ) AS all_issued_certificates
";
$issued_certs_result = $mysqli->query($issued_certs_query);
$total_issued_certificates = $issued_certs_result->fetch_assoc()['issued_count'] ?? 0;
$certificates_percentage = ($total_requests > 0) ? round(($total_issued_certificates / $total_requests) * 100) : 0;

/* --- Total + Approved appointments --- */
$total_appts_query = "
    SELECT SUM(total) as total_appointments, SUM(approved) as approved_appointments
    FROM (
        SELECT COUNT(*) as total, SUM(CASE WHEN status = 'Approved' THEN 1 ELSE 0 END) as approved FROM schedules WHERE appointment_delete_status = 0
        UNION ALL
        SELECT COUNT(*) as total, SUM(CASE WHEN cedula_status = 'Approved' THEN 1 ELSE 0 END) as approved FROM cedula WHERE cedula_delete_status = 0
        UNION ALL
        SELECT COUNT(*) as total, SUM(CASE WHEN status = 'Approved' THEN 1 ELSE 0 END) as approved FROM urgent_request WHERE urgent_delete_status = 0
        UNION ALL
        SELECT COUNT(*) as total, SUM(CASE WHEN cedula_status = 'Approved' THEN 1 ELSE 0 END) as approved FROM urgent_cedula_request WHERE cedula_delete_status = 0
    ) as total_counts
";
$total_appts_result = $mysqli->query($total_appts_query);
if ($total_appts_result) {
    $appts_data = $total_appts_result->fetch_assoc();
    $total_appointments = $appts_data['total_appointments'] ?? 0;
    $approved_appointments = $appts_data['approved_appointments'] ?? 0;
    $approval_rate = ($total_appointments > 0) ? round(($approved_appointments / $total_appointments) * 100) : 0;
} else {
    $total_appointments = 0;
    $approved_appointments = 0;
    $approval_rate = 0;
}

/* --- Officials (active) --- */
$officials_query = "
    SELECT 
        b.position,
        b.photo,
        r.first_name,
        r.middle_name,
        r.last_name,
        r.suffix_name
    FROM barangay_information AS b
    JOIN residents AS r ON b.official_id = r.id
    WHERE b.status = 'active'
    ORDER BY b.id ASC
";
$officials_result = $mysqli->query($officials_query);
if (!$officials_result) { die('Query failed: ' . $mysqli->error); }
$officials = [];
while ($row = $officials_result->fetch_assoc()) { $officials[] = $row; }

/* --- Captain (add welcome_message to fix undefined index) --- */
$captain_query = "
    SELECT b.photo, b.position,
           r.first_name, r.middle_name, r.last_name, r.suffix_name
    FROM barangay_information AS b
    JOIN residents AS r ON b.official_id = r.id
    WHERE b.status = 'active' AND b.position = 'Punong Barangay'
    ORDER BY b.id ASC
    LIMIT 1
";
$captain_result = $mysqli->query($captain_query);
$captain = $captain_result && $captain_result->num_rows > 0 ? $captain_result->fetch_assoc() : null;

/* --- Helpers --- */
function formatFullName($first, $middle, $last, $suffix) {
    $middle_initial = $middle ? strtoupper($middle[0]) . '.' : '';
    $suffix = $suffix ? ' ' . $suffix : '';
    return trim("$first $middle_initial $last$suffix");
}

/* --- Guidelines --- */
$guide_query = "SELECT guide_description FROM guidelines WHERE status = 1 ORDER BY created_at DESC";
$guide_result = $mysqli->query($guide_query);

/* --- FAQs --- */
$stmt = $mysqli->prepare("
  SELECT faq_id, faq_question, faq_answer
  FROM faqs
  WHERE faq_status = 'Active'
  ORDER BY faq_id DESC
");
$stmt->execute();
$res = $stmt->get_result();
$faqs = $res->fetch_all(MYSQLI_ASSOC);
$stmt->close();

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function safe_answer($html){
  $allowed = '<b><strong><i><em><u><br><p><ul><ol><li><a>';
  $html = strip_tags((string)$html, $allowed);
  $html = preg_replace('#<a\s+([^>]*href=["\'])(javascript:)[^"\']*(["\'])#i', '$1#$3', $html);
  return $html;
}
?>
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Barangay Bugo - Homepage</title>
  <!-- <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet"> -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
  <link rel="stylesheet" href="assets/css/Home/Home.css">
</head>
<body>
<?php include 'components/Home/View/ViewHome.php'; ?>
<?php include 'components/Home/Modal/ViewDetails_Modal.php'; ?>
<!-- Load Bootstrap bundle first -->
<!-- <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script> -->
<script src="components/Home/js/Home.js"> </script>
<!-- Force-toggle FAQ with Bootstrap API (works even if other scripts interfere) -->
</body>
</html>
