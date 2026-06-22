<?php
/**
 * API untuk mendapatkan chart data Access Point
 * 
 * ENDPOINT: GET /api/get_ap_chart.php?id_ap=ID
 * 
 * FUNCTION:
 * - Fetch data monitoring AP dari database (20 record terakhir)
 * - Format data menjadi labels dan values untuk Chart.js
 * - Digunakan untuk display traffic in/out chart di detail AP page
 * 
 * CHART DATA:
 * - Labels: Waktu monitoring (format HH:MM)
 * - Values In: Download traffic dalam MB
 * - Values Out: Upload traffic dalam MB
 * - Display: 20 data point terakhir (3-4 jam monitoring)
 * 
 * PARAMETER:
 * - id_ap (GET): ID Access Point
 * 
 * RETURN (JSON):
 * {
 *   "status": "success",
 *   "labels": ["10:00", "10:05", "10:10", ...],
 *   "data_in": [25.5, 30.2, 28.1, ...],
 *   "data_out": [15.2, 12.5, 18.3, ...]
 * }
 */

header('Content-Type: application/json');

require_once dirname(__FILE__) . '/../config/database.php';
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

if (empty($id_ap)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'id_ap parameter is required']);
    exit();
}

try {
    // ===== FETCH MONITORING DATA =====
    // Query database untuk 20 data monitoring terakhir dari AP.
    // Tabel 'monitoring' dipopulate oleh /api/refresh_ap_status.php (realtime + manual refresh).
    $monitoring_data = getMonitoringData($conn, $id_ap, 20);
    
    // ===== FORMAT CHART DATA =====
    // Ubah data monitoring menjadi format yang sesuai untuk Chart.js
    // labels: waktu (HH:MM format)
    // values: traffic in dan traffic out (dalam MB)
    $chart_labels = [];
    $chart_values_in = [];
    $chart_values_out = [];

    // Loop through monitoring data (reverse untuk tampil dari tua ke baru)
    foreach (array_reverse($monitoring_data) as $data) {
        // Format waktu menjadi HH:MM
        $chart_labels[] = date('H:i', strtotime($data['waktu_monitoring']));
        // Convert traffic dari bytes ke MB (jangan rounding di server agar nilai kecil (KB) tetap terlihat di chart).
        $chart_values_in[] = (float) ($data['trafik_in'] / 1024 / 1024);
        $chart_values_out[] = (float) ($data['trafik_out'] / 1024 / 1024);
    }

    // Fallback jika tidak ada data
    if (empty($chart_labels)) {
        $chart_labels = ['No Data'];
        $chart_values_in = [0];
        $chart_values_out = [0];
    }

    // ===== SUCCESS RESPONSE =====
    // Return chart data dalam format Chart.js
    echo json_encode([
        'status' => 'success',
        'labels' => $chart_labels,
        'data_in' => $chart_values_in,
        'data_out' => $chart_values_out
    ]);
    
} catch (Exception $e) {
    // ===== ERROR RESPONSE =====
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>
