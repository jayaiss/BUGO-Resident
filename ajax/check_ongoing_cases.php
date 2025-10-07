<?php
session_start();
header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');

require_once __DIR__ . '/../include/connection.php';
$mysqli = db_connection();

/* ---- Auth guard ---- */
if (!isset($_SESSION['id'])) {
    header('Content-Type: text/html; charset=UTF-8');
    http_response_code(401);
    require_once __DIR__ . '/../security/401.html';
    exit;
}

/* ---- Input ---- */
$res_id = isset($_GET['res_id']) ? (int)$_GET['res_id'] : 0;
if ($res_id <= 0) {
    echo json_encode(['ongoing_cases' => 0]);
    exit;
}

/* ---- Connection check ---- */
if ($mysqli->connect_error) {
    error_log('DB connect error: ' . $mysqli->connect_error);
    echo json_encode(['ongoing_cases' => 0]);
    exit;
}

/* -----------------------------------------------------------------
   1) Look up the resident’s name parts from `residents` by id
------------------------------------------------------------------ */
$rStmt = $mysqli->prepare("
    SELECT 
        COALESCE(first_name,  '')  AS first_name,
        COALESCE(middle_name, '')  AS middle_name,
        COALESCE(last_name,   '')  AS last_name,
        COALESCE(suffix_name, '')  AS suffix_name
    FROM residents
    WHERE id = ?
    LIMIT 1
");
if (!$rStmt) {
    error_log('prepare residents failed: ' . $mysqli->error);
    echo json_encode(['ongoing_cases' => 0]);
    exit;
}

$rStmt->bind_param('i', $res_id);
if (!$rStmt->execute()) {
    error_log('execute residents failed: ' . $rStmt->error);
    echo json_encode(['ongoing_cases' => 0]);
    $rStmt->close();
    exit;
}

$res = $rStmt->get_result();
$resident = $res->fetch_assoc();
$rStmt->close();

if (!$resident) {
    // No such resident
    echo json_encode(['ongoing_cases' => 0]);
    exit;
}

// Normalize PHP-side values (we’ll still normalize in SQL for safety)
$first  = $resident['first_name'];
$middle = $resident['middle_name'];
$last   = $resident['last_name'];
$suffix = $resident['suffix_name'];

/* -----------------------------------------------------------------
   2) Count `cases` where RESPONDENT matches the resident by name
      and action_taken is Pending/Ongoing.

   Matching rules:
   - First & last name must match (case/space-insensitive).
   - Middle name is optional; if present, either full-name or initial
     match is accepted (e.g., “P”, “P.”, “Paulo” -> initial “P”).
   - Suffix is optional; if present, must match case-insensitively.
------------------------------------------------------------------ */
$sql = "
SELECT COUNT(*) AS cnt
FROM cases c
WHERE 
    /* First & Last name strict (case/space-insensitive) */
    UPPER(TRIM(c.Resp_First_Name)) = UPPER(TRIM(?))
    AND UPPER(TRIM(c.Resp_Last_Name))  = UPPER(TRIM(?))

    /* Middle name flexible:
       - If cases.Resp_Middle_Name empty -> OK
       - Else it must either equal resident middle OR share the same initial
    */
    AND (
        c.Resp_Middle_Name IS NULL OR c.Resp_Middle_Name = '' 
        OR UPPER(TRIM(c.Resp_Middle_Name)) = UPPER(TRIM(?))
        OR LEFT(UPPER(TRIM(c.Resp_Middle_Name)), 1) = LEFT(UPPER(TRIM(?)), 1)
    )

    /* Suffix optional: empty is OK, otherwise must match */
    AND (
        c.Resp_Suffix_Name IS NULL OR c.Resp_Suffix_Name = ''
        OR UPPER(TRIM(c.Resp_Suffix_Name)) = UPPER(TRIM(?))
    )

    AND c.action_taken IN ('Pending', 'Ongoing')
";

$cStmt = $mysqli->prepare($sql);
if (!$cStmt) {
    error_log('prepare cases failed: ' . $mysqli->error);
    echo json_encode(['ongoing_cases' => 0]);
    exit;
}

/* Bind:
   1) first, 2) last,
   3) middle (full), 4) middle (again for initial compare),
   5) suffix
*/
$cStmt->bind_param(
    'sssss',
    $first,
    $last,
    $middle,
    $middle,
    $suffix
);

if (!$cStmt->execute()) {
    error_log('execute cases failed: ' . $cStmt->error);
    echo json_encode(['ongoing_cases' => 0]);
    $cStmt->close();
    exit;
}

$cStmt->bind_result($cnt);
$cStmt->fetch();
$cStmt->close();

echo json_encode(['ongoing_cases' => (int)$cnt]);
