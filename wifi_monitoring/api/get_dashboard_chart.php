<?php
/**
 * API untuk chart dashboard (24 jam terakhir) berbasis database (tanpa SNMP).
 *
 * ENDPOINT: GET /api/get_dashboard_chart.php?id_ap=ID
 *
 * RETURN:
 * {
 *   "status":"success",
 *   "labels":["10:00","11:00",...],
 *   "data_in":[...],
 *   "data_out":[...]
 * }
 */

header('Content-Type: application/json');

require_once dirname(__FILE__) . '/../config/database.php';
require_once dirname(__FILE__) . '/../middleware/auth_check.php';

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit();
}

$id_ap = isset($_GET['id_ap']) ? (int) $_GET['id_ap'] : 0;
if ($id_ap <= 0) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'id_ap parameter is required']);
    exit();
}

try {
    $stmt = $conn->prepare('
        SELECT DATE_FORMAT(waktu_monitoring, "%H:00") AS jam,
               AVG(trafik_in) AS avg_traffic_in,
               AVG(trafik_out) AS avg_traffic_out,
               MIN(waktu_monitoring) AS first_time
        FROM monitoring
        WHERE id_ap = ?
          AND waktu_monitoring >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
        GROUP BY DATE_FORMAT(waktu_monitoring, "%Y-%m-%d %H:00:00")
        ORDER BY first_time ASC
    ');

    if (!$stmt) {
        throw new Exception($conn->error);
    }

    $stmt->bind_param('i', $id_ap);
    $stmt->execute();
    $result = $stmt->get_result();

    $labels = [];
    $dataIn = [];
    $dataOut = [];

    while ($row = $result->fetch_assoc()) {
        $labels[] = $row['jam'];
        $dataIn[] = (float) ($row['avg_traffic_in'] / 1024 / 1024);
        $dataOut[] = (float) ($row['avg_traffic_out'] / 1024 / 1024);
    }

    $stmt->close();

    if (empty($labels)) {
        $labels = ['00:00', '12:00', '23:00'];
        $dataIn = [0, 0, 0];
        $dataOut = [0, 0, 0];
    }

    echo json_encode([
        'status' => 'success',
        'labels' => $labels,
        'data_in' => $dataIn,
        'data_out' => $dataOut
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}

?>

