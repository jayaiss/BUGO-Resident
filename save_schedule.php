<?php
session_start();

/* -------------------------------------------------------
 * Always return clean JSON (never HTML/JS)
 * ----------------------------------------------------- */
header('Content-Type: application/json; charset=UTF-8');
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
date_default_timezone_set('Asia/Manila');
// Buffer any accidental output so we can discard it
ob_start();

try {
    require_once 'include/connection.php';
    $mysqli = db_connection();
    $mysqli->query("SET time_zone = '+08:00'");
    require_once 'include/encryption.php';
    require_once 'include/redirects.php';
    require_once __DIR__ . '/logs/logs_trig.php'; // ✅ add trigger include
    $trigs = new Trigger();                       // ✅ instantiate

    /* ------------------- helpers ------------------- */
    function sanitize_input($val) {
        return htmlspecialchars(strip_tags(trim((string)($val ?? ''))), ENT_QUOTES, 'UTF-8');
    }
    // ❗ FIX: don't use placeholders in SHOW statements
    function columnExists(mysqli $db, string $table, string $column): bool {
        $safeTable = preg_replace('/[^A-Za-z0-9_]/', '', $table);
        if ($safeTable === '') return false;
        $safeCol = $db->real_escape_string($column);
        $sql = "SHOW COLUMNS FROM `{$safeTable}` LIKE '{$safeCol}'";
        if (!$res = $db->query($sql)) {
            error_log("[columnExists] SQL error: ".$db->error." | SQL: ".$sql);
            return false;
        }
        $exists = $res->num_rows > 0;
        $res->free();
        return $exists;
    }
    function getResidentColumn(mysqli $db, string $table): ?string {
        foreach (['res_id','resident_id','user_id','residentID','residentId'] as $c) {
            if (columnExists($db, $table, $c)) return $c;
        }
        return null;
    }
    function getOrderByColumn(mysqli $db, string $table): string {
        if (columnExists($db, $table, 'created_at'))         return 'created_at';
        if (columnExists($db, $table, 'id'))                  return 'id';
        if (columnExists($db, $table, 'appointment_date'))    return 'appointment_date';
        return ''; // no ORDER BY
    }

    /* ----------------------------------------------------------------------
     * Guard clauses
     * -------------------------------------------------------------------- */
    if (!isset($_SESSION['id'])) {
        ob_clean();
        echo json_encode(['success' => false, 'message' => 'User not logged in']);
        exit;
    }

    $loggedInUserId = (int)$_SESSION['id'];
    $isJson         = stripos($_SERVER['CONTENT_TYPE'] ?? '', 'application/json') !== false;
    $data           = $isJson ? json_decode(file_get_contents('php://input'), true) : $_POST;

    if (!is_array($data)) {
        ob_clean();
        echo json_encode(['success' => false, 'message' => 'Invalid form or JSON data']);
        exit;
    }

    /* ----------------------------------------------------------------------
     * Extract and sanitise incoming fields
     * -------------------------------------------------------------------- */
    $purpose            = sanitize_input($data['purpose']            ?? '');
    $additionalDetails  = sanitize_input($data['additionalDetails']  ?? '');
    $selectedDate       = sanitize_input($data['selectedDate']       ?? '');
    $selectedTime       = sanitize_input($data['selectedTime']       ?? '');
    $certificatesRaw    = $data['certificates'] ?? [];          // strings or [{name,purpose}]
    $education          = sanitize_input($data['education']          ?? '');
    $course             = sanitize_input($data['course']             ?? '');
    $cedulaMode         = sanitize_input($data['cedulaMode']         ?? '');
    $res_id             = (int)($data['userId']                      ?? 0);
    $cedula_payment     = isset($data['cedula_payment']) ? (float)$data['cedula_payment'] : 0.0;
    $cert_payment       = isset($data['cert_payment'])   ? (float)$data['cert_payment']   : 0.0;

    $certificates = [];
    $purposeMap   = [];

    foreach ((array)$certificatesRaw as $item) {
        if (is_array($item)) {
            $name    = sanitize_input($item['name']    ?? '');
            $perCert = sanitize_input($item['purpose'] ?? '');
        } else {
            $name    = sanitize_input($item);
            $perCert = $purpose;
        }
        if ($name === '') continue;
        $certificates[]                = $name;
        $purposeMap[strtolower($name)] = $perCert;
    }

    if (!$res_id || $selectedDate === '' || $selectedTime === '' || count($certificates) === 0) {
        ob_clean();
        echo json_encode(['success' => false, 'message' => 'Required fields are missing']);
        exit;
    }

    /* ----------------------------------------------------------------------
     * Handle Cedula separately
     * -------------------------------------------------------------------- */
    if (in_array('cedula', array_map('strtolower', $certificates), true)) {
        $cedulaMode       = strtolower($cedulaMode);
        $cedulaTracking   = 'CEDULA-' . strtoupper(uniqid());
        $cedulaStatus     = 'Pending';
        [$appointment_date, $appointment_time] = explode(' ', trim($selectedDate.' '.$selectedTime), 2);

        // Archive old Cedula
        if (columnExists($mysqli, 'cedula', 'res_id')) {
            $stmtUpd = $mysqli->prepare("UPDATE cedula SET cedula_delete_status = 1 WHERE res_id = ? AND cedula_delete_status = 0");
            $stmtUpd->bind_param('i', $res_id);
            $stmtUpd->execute();
            $stmtUpd->close();
        }

        if ($cedulaMode === 'request') {
            $income = (isset($data['income']) && is_numeric($data['income'])) ? (float)$data['income'] : null;
            if ($income === null) {
                ob_clean();
                echo json_encode(['success' => false, 'message' => 'Income is required for Cedula request']);
                exit;
            }

            // ===== Cedula Payment Calculation (monthly income -> annual gross, month-based % add-on) =====
            // 1) Gross = monthly income × 12
            $gross = $income * 12;

            // 2) Base payment = floor(gross / 1000) + ₱5
            $payment  = floor($gross / 1000);
            $cedBase  = $payment + 5;

            // 3) Month-based interest rate (Mar = 0.04%, then +0.02% each month after)
            $month = (int) date('n', strtotime($appointment_date)); // 1..12
            $rate  = 0.0;
            if ($month >= 3) {
                // e.g., Mar=0.04%, Apr=0.06%, May=0.08%, ...
                $rate = (0.04 + 0.02 * ($month - 2)) / 100.0;
            }

            // 4) Interest on gross + final amount (rounded to nearest peso, min ₱50)
            $interest        = $gross * $rate;
            $cedulaPayment   = $cedBase + $interest;
            $cedula_payment  = (float) max(50, round($cedulaPayment));

            $cedulaNumber = '';
            $issuedAt     = 'Bugo, Cagayan de Oro City';
            $issuedOn     = date("Y-m-d");

            $insert = $mysqli->prepare("
                INSERT INTO cedula (
                    res_id, income, appointment_date, appointment_time,
                    tracking_number, cedula_status, cedula_delete_status,
                    cedula_number, issued_at, issued_on, total_payment
                ) VALUES (?, ?, ?, ?, ?, ?, 0, ?, ?, ?, ?)
            ");
            $insert->bind_param(
                "idsssssssd",
                $res_id, $income, $appointment_date, $appointment_time,
                $cedulaTracking, $cedulaStatus,
                $cedulaNumber, $issuedAt, $issuedOn, $cedula_payment
            );
            $insert->execute();

            // ✅ Audit: Cedula added (logs_name = 4)
            try {
                $newCedulaId = $mysqli->insert_id;
                $trigs->isSchedAdded(4, (int)$newCedulaId);
            } catch (Throwable $e) {
                error_log('[save_schedule] audit cedula(request) failed: ' . $e->getMessage());
            }

            $insert->close();

        } elseif ($cedulaMode === 'upload') {
            $cedulaNumber = sanitize_input($data['cedula_number'] ?? '');
            $issuedAt     = sanitize_input($data['issued_at']      ?? '');
            $issuedOn     = sanitize_input($data['issued_on']      ?? '');
            $income       = (isset($data['income']) && is_numeric($data['income'])) ? (float)$data['income'] : null;
            $imageBase64  = $data['cedula_image_base64'] ?? '';

            if ($cedulaNumber === '' || $issuedAt === '' || $issuedOn === '' || $income === null || $imageBase64 === '') {
                ob_clean();
                echo json_encode(['success' => false, 'message' => 'Missing fields for Cedula upload']);
                exit;
            }

            $imageData = base64_decode(preg_replace('#^data:image/\w+;base64,#i', '', $imageBase64));

            // ===== Cedula Payment Calculation (monthly income -> annual gross, month-based % add-on) =====
            $gross   = $income * 12;
            $payment = floor($gross / 1000);
            $cedBase = $payment + 5;

            $month = (int) date('n', strtotime($appointment_date)); // 1..12
            $rate  = 0.0;
            if ($month >= 3) {
                $rate = (0.04 + 0.02 * ($month - 2)) / 100.0;
            }

            $interest        = $gross * $rate;
            $cedulaPayment   = $cedBase + $interest;
            $cedula_payment  = (float) max(50, round($cedulaPayment));

            $insert = $mysqli->prepare("
                INSERT INTO cedula (
                    res_id, income, appointment_date, appointment_time,
                    tracking_number, cedula_status, cedula_delete_status,
                    cedula_number, issued_at, issued_on, cedula_img, total_payment
                ) VALUES (?, ?, ?, ?, ?, ?, 0, ?, ?, ?, ?, ?)
            ");
            $insert->bind_param(
                "idsssssssbd",
                $res_id, $income, $appointment_date, $appointment_time,
                $cedulaTracking, $cedulaStatus,
                $cedulaNumber, $issuedAt, $issuedOn, $imageData, $cedula_payment
            );
            $insert->execute();

            // ✅ Audit: Cedula added (logs_name = 4)
            try {
                $newCedulaId = $mysqli->insert_id;
                $trigs->isSchedAdded(4, (int)$newCedulaId);
            } catch (Throwable $e) {
                error_log('[save_schedule] audit cedula(upload) failed: ' . $e->getMessage());
            }

            $insert->close();

        } else {
            ob_clean();
            echo json_encode(['success' => false, 'message' => 'Invalid Cedula mode']);
            exit;
        }
    }

    /* ----------------------------------------------------------------------
     * Loop over every requested certificate (Cedula already handled)
     * -------------------------------------------------------------------- */
    foreach ($certificates as $certRaw) {

        $certificate    = ucwords(sanitize_input($certRaw));   // e.g. "Barangay Clearance"
        $certLower      = strtolower($certificate);
        $purposeForCert = sanitize_input($purposeMap[$certLower] ?? $purpose);

        if ($certLower === 'cedula') continue;

        $trackingNumber = 'BUGOTRK' . date('Ymd') . random_int(1000, 9999);

        /* Case-check rule for Barangay Clearance */
        if ($certLower === 'barangay clearance' && $res_id === $loggedInUserId) {
            if (columnExists($mysqli, 'cases', 'res_id') && columnExists($mysqli, 'cases', 'action_taken')) {
                $caseCheck = $mysqli->prepare("
                    SELECT COUNT(*)
                    FROM cases
                    WHERE res_id = ?
                      AND action_taken IN ('Pending', 'Ongoing')
                ");
                $caseCheck->bind_param("i", $loggedInUserId);
                $caseCheck->execute();
                $caseCheck->bind_result($ongoingCount);
                $caseCheck->fetch();
                $caseCheck->close();

                if ($ongoingCount > 0) {
                    ob_clean();
                    echo json_encode([
                        'success' => false,
                        'message' => 'You cannot schedule Barangay Clearance while you have pending or ongoing cases.'
                    ]);
                    exit;
                }
            }
        }

        // Conditional payment logic
        $excludePurpose = strtolower($purposeForCert) === 'medical assistance';
        $excludeCert    = $certLower === 'beso application';
        $finalPayment   = ($excludePurpose || $excludeCert) ? 0.00 : 50.00;

        // Insert certificate into schedules
        $stmt = $mysqli->prepare("
            INSERT INTO schedules (
                res_id, purpose, additional_details, selected_date,
                selected_time, certificate, tracking_number, total_payment
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->bind_param(
            "issssssd",
            $res_id, $purposeForCert, $additionalDetails,
            $selectedDate, $selectedTime, $certificate, $trackingNumber, $finalPayment
        );
        $stmt->execute();

        // ✅ Audit: Schedule added (logs_name = 3)
        try {
            $newSchedId = $mysqli->insert_id;
            $trigs->isSchedAdded(3, (int)$newSchedId);
        } catch (Throwable $e) {
            error_log('[save_schedule] audit schedules failed: ' . $e->getMessage());
        }

        $stmt->close();

        /* ==================================================
         *  Mark BESO as used_for_clearance for FTJ
         * ================================================== */
        if ($certLower === 'barangay clearance' &&
            strtolower($purposeForCert) === 'first time jobseeker') {

            $besoTables = [
                'schedules'               => 'appointment_delete_status = 0',
                'urgent_request'          => 'urgent_delete_status     = 0',
                'archived_schedules'      => '1',
                'archived_urgent_request' => '1'
            ];

            foreach ($besoTables as $table => $where) {
                $residentCol = getResidentColumn($mysqli, $table);
                if (!$residentCol || !columnExists($mysqli, $table, 'certificate') || !columnExists($mysqli, $table, 'used_for_clearance')) {
                    continue; // skip if schema doesn’t match
                }
                $orderCol = getOrderByColumn($mysqli, $table);
                $orderSql = $orderCol ? "ORDER BY `$orderCol` DESC" : "";

                $sql = "
                    UPDATE `$table`
                    SET used_for_clearance = 1
                    WHERE `$residentCol` = ?
                      AND certificate = 'BESO Application'
                      AND used_for_clearance = 0
                      AND $where
                    $orderSql
                    LIMIT 1
                ";
                if ($upd = $mysqli->prepare($sql)) {
                    $upd->bind_param('i', $res_id);
                    $upd->execute();
                    $upd->close();
                }
            }
        }

        /* ==================================================
         *  Mark BESO as used_for_indigency for FTJ
         * ================================================== */
        if ($certLower === 'barangay indigency' &&
            strtolower($purposeForCert) === 'first time jobseeker') {

            $besoTables = [
                'schedules'               => 'appointment_delete_status = 0',
                'urgent_request'          => 'urgent_delete_status     = 0',
                'archived_schedules'      => '1',
                'archived_urgent_request' => '1'
            ];

            foreach ($besoTables as $table => $where) {
                $residentCol = getResidentColumn($mysqli, $table);
                if (!$residentCol || !columnExists($mysqli, $table, 'certificate') || !columnExists($mysqli, $table, 'used_for_indigency')) {
                    continue;
                }
                $orderCol = getOrderByColumn($mysqli, $table);
                $orderSql = $orderCol ? "ORDER BY `$orderCol` DESC" : "";

                $sql = "
                    UPDATE `$table`
                    SET used_for_indigency = 1
                    WHERE `$residentCol` = ?
                      AND certificate = 'BESO Application'
                      AND used_for_indigency = 0
                      AND $where
                    $orderSql
                    LIMIT 1
                ";
                if ($upd = $mysqli->prepare($sql)) {
                    $upd->bind_param('i', $res_id);
                    $upd->execute();
                    $upd->close();
                }
            }
        }

        /* --------------------------------------------------
         *  Additional logic for BESO Application itself
         * -------------------------------------------------- */
        if ($certLower === 'beso application') {
            if ($education === '' || $course === '') {
                ob_clean();
                echo json_encode([
                    'success' => false,
                    'message' => 'Educational background and course are required for BESO Application.'
                ]);
                $mysqli->close();
                exit;
            }

            $residencyTables = [
                'schedules'               => 'appointment_delete_status = 0 AND status = "Released"',
                'urgent_request'          => 'urgent_delete_status     = 0 AND status = "Released"',
                'archived_schedules'      => '1',
                'archived_urgent_request' => '1'
            ];

            foreach ($residencyTables as $table => $whereClause) {
                $residentCol = getResidentColumn($mysqli, $table);
                if (!$residentCol || !columnExists($mysqli, $table, 'certificate') || !columnExists($mysqli, $table, 'barangay_residency_used_for_beso')) {
                    continue;
                }
                $orderCol = getOrderByColumn($mysqli, $table);
                $orderSql = $orderCol ? "ORDER BY `$orderCol` DESC" : "";

                $updResSql = "
                    UPDATE `$table`
                    SET barangay_residency_used_for_beso = 1
                    WHERE `$residentCol` = ?
                      AND certificate = 'Barangay Residency'
                      AND purpose = 'First Time Jobseeker'
                      AND barangay_residency_used_for_beso = 0
                      AND $whereClause
                    $orderSql
                    LIMIT 1
                ";
                if ($updRes = $mysqli->prepare($updResSql)) {
                    $updRes->bind_param("i", $res_id);
                    $updRes->execute();
                    $updRes->close();
                }
            }

            $nowPh = (new DateTime('now', new DateTimeZone('Asia/Manila')))->format('Y-m-d H:i:s');
            $besoInsert = $mysqli->prepare("
                INSERT INTO beso (
                    res_id, education_attainment, course, employee_id, beso_delete_status, created_at
                ) VALUES (?, ?, ?, 0, 0, ?)
            ");
            $besoInsert->bind_param("isss", $res_id, $education, $course, $nowPh);
            $besoInsert->execute();

            // ✅ Audit: BESO record added (logs_name = 28)
            try {
                $newBesoId = $mysqli->insert_id;
                $trigs->isSchedAdded(28, (int)$newBesoId);
            } catch (Throwable $e) {
                error_log('[save_schedule] audit beso failed: ' . $e->getMessage());
            }

            $besoInsert->close();
        }
    }

    /* ----------------------------------------------------------------------
     * All done
     * -------------------------------------------------------------------- */
    $mysqli->close();

    ob_clean();
    echo json_encode([
        'success' => true,
        'message' => 'Schedule saved successfully!',
        'trackingNumber' => $cedulaTracking ?? null
    ]);

} catch (Throwable $e) {
    error_log('[save_schedule.php] '.$e->getMessage());
    ob_clean();
    echo json_encode([
        'success' => false,
        'message' => 'Server error while saving.',
        'debug'   => $e->getMessage()   // TEMP: remove in prod
    ]);
}
