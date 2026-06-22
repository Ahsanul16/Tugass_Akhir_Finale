<?php
/**
 * API: list lantai by gedung (for dropdown).
 *
 * ENDPOINT: GET /api/get_lantai.php?id_gedung=ID
 *
 * RETURN:
 * { "status":"success", "data":[ {id_lantai, nama_lantai}, ... ] }
 */

header('Content-Type: application/json');

require_once dirname(__FILE__) . '/../config/database.php';
require_once dirname(__FILE__) . '/../middleware/auth_check.php';
require_once dirname(__FILE__) . '/../helpers/location.php';

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit();
}

$id_gedung = isset($_GET['id_gedung']) ? (int) $_GET['id_gedung'] : 0;
if ($id_gedung <= 0) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'id_gedung parameter is required']);
    exit();
}

try {
    $rows = getLantaiByGedung($conn, $id_gedung);
    $data = [];
    foreach ($rows as $r) {
        $data[] = [
            'id_lantai' => (int) $r['id_lantai'],
            'nama_lantai' => (string) $r['nama_lantai']
        ];
    }

    echo json_encode(['status' => 'success', 'data' => $data]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}

?>

