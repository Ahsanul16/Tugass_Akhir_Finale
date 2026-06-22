<?php
/**
 * API untuk ambil statistik monitoring AP (tanpa SNMP, dari DB).
 *
 * ENDPOINT: GET /api/get_ap_stats.php?id_ap=ID
 *
 * RETURN:
 * {
 *   "status":"success",
 *   "avg_in":"1.23 KB",
 *   "max_in":"9.87 KB",
 *   "min_in":"0 B",
 *   "avg_out":"...",
 *   "max_out":"...",
 *   "min_out":"..."
 * }
 */

header('Content-Type: application/json');

require_once dirname(__FILE__) . '/../config/database.php';
require_once dirname(__FILE__) . '/../config/snmp.php'; // formatBandwidth()
require_once dirname(__FILE__) . '/../middleware/auth_check.php';
require_once dirname(__FILE__) . '/../helpers/access_point.php';

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
    $stats = getMonitoringStats($conn, $id_ap);

    $avg_in = isset($stats['avg_traffic_in']) ? (float) $stats['avg_traffic_in'] : 0;
    $max_in = isset($stats['max_traffic_in']) ? (float) $stats['max_traffic_in'] : 0;
    $min_in = isset($stats['min_traffic_in']) ? (float) $stats['min_traffic_in'] : 0;

    $avg_out = isset($stats['avg_traffic_out']) ? (float) $stats['avg_traffic_out'] : 0;
    $max_out = isset($stats['max_traffic_out']) ? (float) $stats['max_traffic_out'] : 0;
    $min_out = isset($stats['min_traffic_out']) ? (float) $stats['min_traffic_out'] : 0;

    echo json_encode([
        'status' => 'success',
        'avg_in' => formatBandwidth($avg_in),
        'max_in' => formatBandwidth($max_in),
        'min_in' => formatBandwidth($min_in),
        'avg_out' => formatBandwidth($avg_out),
        'max_out' => formatBandwidth($max_out),
        'min_out' => formatBandwidth($min_out)
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}

?>

