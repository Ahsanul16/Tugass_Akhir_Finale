<?php
/**
 * API untuk ambil data monitoring terbaru (HTML rows) dari database (tanpa SNMP).
 *
 * ENDPOINT: GET /api/get_ap_monitoring_rows.php?id_ap=ID&limit=100[&mon_date=YYYY-MM-DD][&mon_status=Online|Offline]
 *
 * RETURN:
 * {
 *   "status":"success",
 *   "html":"<tr>...</tr>...",
 *   "total":123
 * }
 */

header('Content-Type: application/json');

require_once dirname(__FILE__) . '/../config/database.php';
require_once dirname(__FILE__) . '/../config/snmp.php'; // for formatBandwidth()
require_once dirname(__FILE__) . '/../middleware/auth_check.php';
require_once dirname(__FILE__) . '/../helpers/access_point.php';

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit();
}

$id_ap = isset($_GET['id_ap']) ? (int) $_GET['id_ap'] : 0;
$limit = isset($_GET['limit']) ? (int) $_GET['limit'] : 100;
if ($limit < 1) $limit = 100;
if ($limit > 200) $limit = 200;

$mon_date = isset($_GET['mon_date']) ? trim((string) $_GET['mon_date']) : '';
$mon_status = isset($_GET['mon_status']) ? trim((string) $_GET['mon_status']) : '';

if ($mon_date !== '' && !preg_match('/^\\d{4}-\\d{2}-\\d{2}$/', $mon_date)) {
    $mon_date = '';
}
if (!in_array($mon_status, ['', 'Online', 'Offline'], true)) {
    $mon_status = '';
}

if ($id_ap <= 0) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'id_ap parameter is required']);
    exit();
}

try {
    $rows = getMonitoringDataPaged($conn, $id_ap, $limit, 0, $mon_date ?: null, $mon_status ?: null);
    $total = getMonitoringCount($conn, $id_ap, $mon_date ?: null, $mon_status ?: null);

    ob_start();
    if (!empty($rows)) {
        foreach ($rows as $data) {
            $badge_class = ($data['status_ap'] === 'Online') ? 'bg-success' : 'bg-danger';
            ?>
            <tr>
                <td><small><?php echo date('d M H:i:s', strtotime($data['waktu_monitoring'])); ?></small></td>
                <td>
                    <span class="badge <?php echo $badge_class; ?>">
                        <?php echo htmlspecialchars($data['status_ap']); ?>
                    </span>
                </td>
                <td><strong><?php echo htmlspecialchars(formatBandwidth((float) $data['trafik_in'])); ?></strong></td>
                <td><strong><?php echo htmlspecialchars(formatBandwidth((float) $data['trafik_out'])); ?></strong></td>
                <td><?php echo (int) $data['jumlah_user']; ?> user</td>
            </tr>
            <?php
        }
    } else {
        ?>
        <tr>
            <td colspan="5" class="text-center py-3">
                <i class="bi bi-inbox"></i> Belum ada data monitoring
            </td>
        </tr>
        <?php
    }
    $html = ob_get_clean();

    echo json_encode([
        'status' => 'success',
        'html' => $html,
        'total' => (int) $total
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}

?>
