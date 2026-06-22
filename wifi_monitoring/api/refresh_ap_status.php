<?php
/**
 * API untuk manual refresh status Access Point - OPTIMIZED
 * 
 * ENDPOINT: GET /api/refresh_ap_status.php?id_ap=ID
 * 
 * OPTIMASI:
 * - Hanya query OID yang ada (tidak perlu semua OID)
 * - Response time < 2 detik (timeout 1 detik per SNMP call)
 * - Parallel requests untuk multiple OID (jika diperlukan)
 * - Cache 10 menit untuk reduce beban
 */

header('Content-Type: application/json');

require_once dirname(__FILE__) . '/../config/database.php';
require_once dirname(__FILE__) . '/../config/snmp.php';
require_once dirname(__FILE__) . '/../middleware/auth_check.php';
require_once dirname(__FILE__) . '/../helpers/access_point.php';

// ===== AUTHENTICATION CHECK =====
if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit();
}

// ===== PARAMETER VALIDATION =====
$id_ap = isset($_GET['id_ap']) ? (int)$_GET['id_ap'] : 0;
// force=1 untuk benar-benar SNMP query ulang (bypass throttle DB snapshot)
$force = isset($_GET['force']) ? ((int) $_GET['force'] === 1) : false;
// log=1 untuk memastikan snapshot masuk tabel monitoring (dipakai untuk realtime UI)
$log = isset($_GET['log']) ? ((int) $_GET['log'] === 1) : false;

if (empty($id_ap)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'id_ap parameter is required']);
    exit();
}

try {
    // ===== FETCH AP DATA =====
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
    
    // Validasi AP exists
    if (!$ap) {
        http_response_code(404);
        echo json_encode(['status' => 'error', 'message' => 'Access Point not found']);
        exit();
    }
    
    // ===== STATUS CHECK (SNMP ONLY) =====
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
        throw new Exception('No OID configured for this Access Point');
    }

    // ===== THROTTLE (DB SNAPSHOT) =====
    // Supaya auto-refresh tidak berat: jika snapshot terakhir masih baru, kembalikan data DB tanpa SNMP.
    if (!$force && defined('SNMP_MIN_REFRESH_INTERVAL')) {
        $minInterval = (int) SNMP_MIN_REFRESH_INTERVAL;
        if ($minInterval > 0) {
            $latest = getLatestMonitoringRow($conn, $id_ap);
            if ($latest && !empty($latest['waktu_monitoring'])) {
                $age = time() - strtotime($latest['waktu_monitoring']);
                if ($age >= 0 && $age < $minInterval) {
                    $statusCached = $latest['status_ap'] ?? ($ap['status'] ?? 'Offline');

                    // Optional: log snapshot ke tabel monitoring meskipun tanpa SNMP (realtime UI).
                    if ($log && defined('MONITORING_MIN_LOG_INTERVAL')) {
                        $minLog = (int) MONITORING_MIN_LOG_INTERVAL;
                        if ($minLog < 1) $minLog = 1;

                        if ($age >= $minLog) {
                            $trafikInCached = (float) ($latest['trafik_in'] ?? 0);
                            $trafikOutCached = (float) ($latest['trafik_out'] ?? 0);
                            $usersCached = (int) ($latest['jumlah_user'] ?? 0);

                            $stmt = $conn->prepare('
                                INSERT INTO monitoring (id_ap, trafik_in, trafik_out, jumlah_user, status_ap, waktu_monitoring)
                                VALUES (?, ?, ?, ?, ?, NOW())
                            ');
                            if ($stmt) {
                                $stmt->bind_param('iddis', $id_ap, $trafikInCached, $trafikOutCached, $usersCached, $statusCached);
                                $stmt->execute();
                                $stmt->close();
                            }

                            // Keep access_point.status consistent
                            $stmt = $conn->prepare('UPDATE access_point SET status = ? WHERE id_ap = ?');
                            if ($stmt) {
                                $stmt->bind_param('si', $statusCached, $id_ap);
                                $stmt->execute();
                                $stmt->close();
                            }

                            cleanupOldMonitoringData($conn, $id_ap, 30);
                        }
                    }

                    echo json_encode([
                        'status' => 'success',
                        'ap_status' => $statusCached,
                        'trafik_in' => (int) ($latest['trafik_in'] ?? 0),
                        'trafik_out' => (int) ($latest['trafik_out'] ?? 0),
                        'jumlah_user' => (int) ($latest['jumlah_user'] ?? 0),
                        'badge_class' => ($statusCached === 'Online') ? 'bg-success' : 'bg-danger',
                        'status_icon' => ($statusCached === 'Online') ? 'bi-wifi' : 'bi-wifi-off',
                        'message' => 'Using cached snapshot (throttled)'
                    ]);
                    exit();
                }
            }
        }
    }

    // Force bypass cache because this endpoint is explicitly triggered by user refresh button.
    $real_time_status = checkAccessPointStatus($ap['ip_address'], $ap['community'] ?? '', $oids, true);

    // ===== FETCH OPTIONAL METRICS =====
    $trafik_in = 0;
    $trafik_out = 0;
    $jumlah_user = 0;

    if ($real_time_status === 'Online') {
        $trafik_in = getTrafficInData($ap['ip_address'], $ap['community'] ?? '', $ap['oid_traffic_in'] ?? '');
        $trafik_out = getTrafficOutData($ap['ip_address'], $ap['community'] ?? '', $ap['oid_traffic_out'] ?? '');
        $jumlah_user = getUserCount($ap['ip_address'], $ap['community'] ?? '', $ap['oid_user'] ?? '');
    }
    
    // ===== DATABASE UPDATE =====
    // Update status AP di database
    $stmt = $conn->prepare('UPDATE access_point SET status = ? WHERE id_ap = ?');
    if ($stmt) {
        $stmt->bind_param('si', $real_time_status, $id_ap);
        $stmt->execute();
        $stmt->close();
    }
    
    // Insert monitoring snapshot untuk chart
    $stmt = $conn->prepare('
        INSERT INTO monitoring (id_ap, trafik_in, trafik_out, jumlah_user, status_ap, waktu_monitoring)
        VALUES (?, ?, ?, ?, ?, NOW())
    ');
    if ($stmt) {
        $stmt->bind_param('iddis', $id_ap, $trafik_in, $trafik_out, $jumlah_user, $real_time_status);
        $stmt->execute();
        $stmt->close();
    }

    // Cleanup data lama (30 hari) agar tabel monitoring tidak membengkak
    cleanupOldMonitoringData($conn, $id_ap, 30);

    // ===== SUCCESS RESPONSE =====
    echo json_encode([
        'status' => 'success',
        'ap_status' => $real_time_status,
        'trafik_in' => (int) $trafik_in,
        'trafik_out' => (int) $trafik_out,
        'jumlah_user' => (int) $jumlah_user,
        'badge_class' => ($real_time_status === 'Online') ? 'bg-success' : 'bg-danger',
        'status_icon' => ($real_time_status === 'Online') ? 'bi-wifi' : 'bi-wifi-off',
        'message' => 'Status updated: ' . $real_time_status
    ]);
    
} catch (Exception $e) {
    // ===== ERROR RESPONSE =====
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>
