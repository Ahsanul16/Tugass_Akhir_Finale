<?php
/**
 * API realtime Access Point tanpa menyimpan ke database.
 *
 * ENDPOINT: GET /api/get_ap_realtime_status.php?id_ap=ID
 */

header('Content-Type: application/json');
date_default_timezone_set('Asia/Jakarta');

require_once dirname(__FILE__) . '/../config/database.php';
require_once dirname(__FILE__) . '/../config/snmp.php';
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
        SELECT id_ap, ip_address, community, oid_status, oid_traffic_in, oid_traffic_out, oid_user
        FROM access_point
        WHERE id_ap = ?
    ');

    if (!$stmt) {
        throw new Exception($conn->error);
    }

    $stmt->bind_param('i', $id_ap);
    $stmt->execute();
    $result = $stmt->get_result();
    $ap = $result->fetch_assoc();
    $stmt->close();

    if (!$ap) {
        http_response_code(404);
        echo json_encode(['status' => 'error', 'message' => 'Access Point not found']);
        exit();
    }

    $oids = [
        'oid_status' => $ap['oid_status'] ?? '',
        'oid_traffic_in' => $ap['oid_traffic_in'] ?? '',
        'oid_traffic_out' => $ap['oid_traffic_out'] ?? '',
        'oid_user' => $ap['oid_user'] ?? ''
    ];

    if (
        empty($oids['oid_status']) &&
        empty($oids['oid_traffic_in']) &&
        empty($oids['oid_traffic_out']) &&
        empty($oids['oid_user'])
    ) {
        $statusOffline = 'Offline';
        $stmt = $conn->prepare('UPDATE access_point SET status = ? WHERE id_ap = ?');
        if ($stmt) {
            $stmt->bind_param('si', $statusOffline, $id_ap);
            $stmt->execute();
            $stmt->close();
        }

        echo json_encode([
            'status' => 'success',
            'ap_status' => 'Offline',
            'trafik_in' => 0,
            'trafik_out' => 0,
            'jumlah_user' => 0,
            'traffic_in_label' => formatBandwidth(0),
            'traffic_out_label' => formatBandwidth(0),
            'checked_at' => date('H:i:s'),
            'message' => 'No OID configured'
        ]);
        exit();
    }

    $ap_status = checkAccessPointStatus($ap['ip_address'], $ap['community'] ?? '', $oids, true);
    $trafik_in = 0;
    $trafik_out = 0;
    $jumlah_user = 0;

    if ($ap_status === 'Online') {
        $trafik_in = getTrafficInData($ap['ip_address'], $ap['community'] ?? '', $ap['oid_traffic_in'] ?? '');
        $trafik_out = getTrafficOutData($ap['ip_address'], $ap['community'] ?? '', $ap['oid_traffic_out'] ?? '');
        $jumlah_user = getUserCount($ap['ip_address'], $ap['community'] ?? '', $ap['oid_user'] ?? '');
    }

    // Sinkronkan status utama AP agar kolom Status dan dashboard tidak berbeda dari hasil realtime.
    // Data trafik/user realtime tetap tidak disimpan ke tabel monitoring.
    $stmt = $conn->prepare('UPDATE access_point SET status = ? WHERE id_ap = ?');
    if ($stmt) {
        $stmt->bind_param('si', $ap_status, $id_ap);
        $stmt->execute();
        $stmt->close();
    }

    echo json_encode([
        'status' => 'success',
        'ap_status' => $ap_status,
        'trafik_in' => (float) $trafik_in,
        'trafik_out' => (float) $trafik_out,
        'jumlah_user' => (int) $jumlah_user,
        'traffic_in_label' => formatBandwidth($trafik_in),
        'traffic_out_label' => formatBandwidth($trafik_out),
        'checked_at' => date('H:i:s')
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>
