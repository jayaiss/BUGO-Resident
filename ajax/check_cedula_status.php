    <?php
    session_start();
require_once __DIR__ . '/../include/connection.php';
$mysqli = db_connection();
    if (!isset($_SESSION['id'])) {
    header('Content-Type: text/html; charset=UTF-8');
    http_response_code(401);
    require_once __DIR__ . '/../security/401.html';
    exit;
}

    // 🔐 Sanitize & validate input
    $res_id = isset($_GET['res_id']) ? intval($_GET['res_id']) : 0;

    if (!$res_id) {
        echo json_encode([
            'status' => 'error',
            'message' => 'Missing or invalid resident ID',
            'res_id' => null
        ]);
        exit;
    }

    $response = ['res_id' => $res_id];

    // 🔍 Get latest Cedula status
    $stmt = $mysqli->prepare("
        SELECT cedula_status, cedula_delete_status 
        FROM cedula 
        WHERE res_id = ? 
        ORDER BY Ced_Id DESC 
        LIMIT 1
    ");

    if (!$stmt) {
        echo json_encode([
            'status' => 'error',
            'message' => 'Database prepare failed: ' . $mysqli->error,
            'res_id' => $res_id
        ]);
        exit;
    }

    $stmt->bind_param("i", $res_id);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows === 0) {
        $response['status'] = 'missing';
    } else {
        $stmt->bind_result($status, $cedula_delete_status);
        $stmt->fetch();
        $status = strtolower(trim($status));

        if ($cedula_delete_status == 1) {
            $response['status'] = 'archived';
        } elseif ($status === 'pending') {
            $response['status'] = 'pending';
        } elseif ($status === 'rejected') {
            $response['status'] = 'rejected';
        } elseif ($status === 'approved') {
            $response['status'] = 'approved';
        } elseif ($status === 'released') {
            $response['status'] = 'valid';
        } else {
            $response['status'] = 'missing';
        }
    }
    $stmt->close();
    $mysqli->close();

    // ✅ Return JSON
    header('Content-Type: application/json');
    echo json_encode($response);
