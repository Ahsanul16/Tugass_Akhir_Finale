<?php
/**
 * Helper Functions untuk Access Point Management
 * CRUD Operations
 */

require_once dirname(__FILE__) . '/../config/database.php';

/**
 * Validasi IPv4 untuk IP Access Point
 */
function isValidIpv4Address($ip_address) {
    $ip_address = trim((string) $ip_address);
    return $ip_address !== '' && filter_var($ip_address, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false;
}

/**
 * Dapatkan semua Access Point
 */
function getAllAccessPoints($conn) {
    $query = '
        SELECT
            ap.id_ap, ap.nama_ap, ap.merk, ap.lokasi, ap.id_lantai,
            ap.ip_address, ap.community,
            ap.oid_status, ap.oid_traffic_in, ap.oid_traffic_out, ap.oid_user,
            ap.status, ap.created_at, ap.updated_at,
            l.nama_lantai,
            g.id_gedung, g.nama_gedung
        FROM access_point ap
        LEFT JOIN lantai l ON l.id_lantai = ap.id_lantai
        LEFT JOIN gedung g ON g.id_gedung = l.id_gedung
        ORDER BY ap.nama_ap ASC
    ';
    $result = $conn->query($query);
    if ($result) {
        return fetchAll($result);
    }

    // Fallback (compat): database belum punya tabel/kolom lokasi baru.
    $result2 = $conn->query('SELECT * FROM access_point ORDER BY nama_ap ASC');
    $rows = fetchAll($result2);
    foreach ($rows as &$r) {
        if (!isset($r['id_lantai'])) $r['id_lantai'] = null;
        $r['nama_lantai'] = null;
        $r['id_gedung'] = null;
        $r['nama_gedung'] = null;
    }
    return $rows;
}

/**
 * Dapatkan Access Point berdasarkan ID
 */
function getAccessPointById($conn, $id_ap) {
    $stmt = $conn->prepare('
        SELECT
            ap.id_ap, ap.nama_ap, ap.merk, ap.lokasi, ap.id_lantai,
            ap.ip_address, ap.community,
            ap.oid_status, ap.oid_traffic_in, ap.oid_traffic_out, ap.oid_user,
            ap.status, ap.created_at, ap.updated_at,
            l.nama_lantai,
            g.id_gedung, g.nama_gedung
        FROM access_point ap
        LEFT JOIN lantai l ON l.id_lantai = ap.id_lantai
        LEFT JOIN gedung g ON g.id_gedung = l.id_gedung
        WHERE ap.id_ap = ?
    ');
    if ($stmt) {
        $stmt->bind_param('i', $id_ap);
        $stmt->execute();
        $result = $stmt->get_result();
        $ap = $result->fetch_assoc();
        $stmt->close();
        return $ap;
    }

    // Fallback (compat): database belum punya tabel/kolom lokasi baru.
    $stmt2 = $conn->prepare('SELECT * FROM access_point WHERE id_ap = ?');
    if (!$stmt2) {
        return null;
    }
    $stmt2->bind_param('i', $id_ap);
    $stmt2->execute();
    $ap = $stmt2->get_result()->fetch_assoc();
    $stmt2->close();
    if (!$ap) return null;

    if (!isset($ap['id_lantai'])) $ap['id_lantai'] = null;
    $ap['nama_lantai'] = null;
    $ap['id_gedung'] = null;
    $ap['nama_gedung'] = null;
    return $ap;
}

/**
 * Tambah Access Point baru
 */
function createAccessPoint($conn, $data) {
    $ip_address = trim(isset($data['ip_address']) ? (string) $data['ip_address'] : '');

    // Validasi data
    if (empty($data['nama_ap']) || $ip_address === '') {
        return ['status' => 'error', 'message' => 'Nama AP dan IP Address harus diisi!'];
    }

    if (!isValidIpv4Address($ip_address)) {
        return ['status' => 'error', 'message' => 'IP Address harus berupa format IPv4 yang valid!'];
    }

    $data['ip_address'] = $ip_address;
    
    // Check duplicate IP
    $stmt = $conn->prepare('SELECT id_ap FROM access_point WHERE ip_address = ?');
    $stmt->bind_param('s', $data['ip_address']);
    $stmt->execute();
    if ($stmt->get_result()->num_rows > 0) {
        $stmt->close();
        return ['status' => 'error', 'message' => 'IP Address sudah terdaftar!'];
    }
    $stmt->close();
    
    // Require SNMP configuration: community + minimal satu OID
    if (empty($data['community']) || (empty($data['oid_status']) && empty($data['oid_traffic_in']) && empty($data['oid_traffic_out']) && empty($data['oid_user']))) {
        return ['status' => 'error', 'message' => 'SNMP community dan minimal satu OID harus diisi untuk monitoring.'];
    }
    
    // Require building/floor (id_lantai)
    $id_lantai = isset($data['id_lantai']) ? (int) $data['id_lantai'] : 0;
    if ($id_lantai <= 0) {
        return ['status' => 'error', 'message' => 'Gedung dan lantai harus dipilih!'];
    }

    // Insert
    $stmt = $conn->prepare('
        INSERT INTO access_point (nama_ap, merk, lokasi, id_lantai, ip_address, community, oid_status, oid_traffic_in, oid_traffic_out, oid_user, status)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ');
    
    if (!$stmt) {
        return ['status' => 'error', 'message' => $conn->error];
    }
    
    $status = 'Offline';
    // SNMP fields are required for monitoring. Monitoring without SNMP (PING fallback) is not supported.
    $merk = !empty($data['merk']) ? $data['merk'] : '';
    $community = !empty($data['community']) ? $data['community'] : '';
    $oid_status = !empty($data['oid_status']) ? $data['oid_status'] : '';
    $oid_traffic_in = !empty($data['oid_traffic_in']) ? $data['oid_traffic_in'] : '';
    $oid_traffic_out = !empty($data['oid_traffic_out']) ? $data['oid_traffic_out'] : '';
    $oid_user = !empty($data['oid_user']) ? $data['oid_user'] : '';
    
    $stmt->bind_param(
        'sssisssssss',
        $data['nama_ap'],
        $merk,
        $data['lokasi'],
        $id_lantai,
        $data['ip_address'],
        $community,
        $oid_status,
        $oid_traffic_in,
        $oid_traffic_out,
        $oid_user,
        $status
    );
    
    if ($stmt->execute()) {
        $id_ap = $conn->insert_id;
        $stmt->close();
        return ['status' => 'success', 'message' => 'Access Point berhasil ditambahkan!', 'id_ap' => $id_ap];
    } else {
        $error = $stmt->error;
        $stmt->close();
        return ['status' => 'error', 'message' => $error];
    }
}

/**
 * Update Access Point
 */
function updateAccessPoint($conn, $id_ap, $data) {
    $ip_address = trim(isset($data['ip_address']) ? (string) $data['ip_address'] : '');

    // Validasi
    if (empty($data['nama_ap']) || $ip_address === '') {
        return ['status' => 'error', 'message' => 'Nama AP dan IP Address harus diisi!'];
    }

    if (!isValidIpv4Address($ip_address)) {
        return ['status' => 'error', 'message' => 'IP Address harus berupa format IPv4 yang valid!'];
    }

    $data['ip_address'] = $ip_address;
    
    // Check duplicate IP (excluding current AP)
    $stmt = $conn->prepare('SELECT id_ap FROM access_point WHERE ip_address = ? AND id_ap != ?');
    $stmt->bind_param('si', $data['ip_address'], $id_ap);
    $stmt->execute();
    if ($stmt->get_result()->num_rows > 0) {
        $stmt->close();
        return ['status' => 'error', 'message' => 'IP Address sudah terdaftar!'];
    }
    $stmt->close();
    
    // Require SNMP configuration for monitoring
    if (empty($data['community']) || (empty($data['oid_status']) && empty($data['oid_traffic_in']) && empty($data['oid_traffic_out']) && empty($data['oid_user']))) {
        return ['status' => 'error', 'message' => 'SNMP community dan minimal satu OID harus diisi untuk monitoring.'];
    }
    
    // Require building/floor (id_lantai)
    $id_lantai = isset($data['id_lantai']) ? (int) $data['id_lantai'] : 0;
    if ($id_lantai <= 0) {
        return ['status' => 'error', 'message' => 'Gedung dan lantai harus dipilih!'];
    }

    // Update
    $stmt = $conn->prepare('
        UPDATE access_point 
        SET nama_ap = ?, merk = ?, lokasi = ?, id_lantai = ?, ip_address = ?, community = ?, 
            oid_status = ?, oid_traffic_in = ?, oid_traffic_out = ?, oid_user = ?
        WHERE id_ap = ?
    ');
    
    if (!$stmt) {
        return ['status' => 'error', 'message' => $conn->error];
    }
    
    // SNMP fields are required for monitoring. Monitoring without SNMP (PING fallback) is not supported.
    $merk = !empty($data['merk']) ? $data['merk'] : '';
    $community = !empty($data['community']) ? $data['community'] : '';
    $oid_status = !empty($data['oid_status']) ? $data['oid_status'] : '';
    $oid_traffic_in = !empty($data['oid_traffic_in']) ? $data['oid_traffic_in'] : '';
    $oid_traffic_out = !empty($data['oid_traffic_out']) ? $data['oid_traffic_out'] : '';
    $oid_user = !empty($data['oid_user']) ? $data['oid_user'] : '';
    
    $stmt->bind_param(
        'sssissssssi',
        $data['nama_ap'],
        $merk,
        $data['lokasi'],
        $id_lantai,
        $data['ip_address'],
        $community,
        $oid_status,
        $oid_traffic_in,
        $oid_traffic_out,
        $oid_user,
        $id_ap
    );
    
    if ($stmt->execute()) {
        $stmt->close();
        return ['status' => 'success', 'message' => 'Access Point berhasil diperbarui!'];
    } else {
        $error = $stmt->error;
        $stmt->close();
        return ['status' => 'error', 'message' => $error];
    }
}

/**
 * Hapus Access Point
 */
function deleteAccessPoint($conn, $id_ap) {
    // Cek apakah AP ada
    $stmt = $conn->prepare('SELECT id_ap FROM access_point WHERE id_ap = ?');
    $stmt->bind_param('i', $id_ap);
    $stmt->execute();
    if ($stmt->get_result()->num_rows == 0) {
        $stmt->close();
        return ['status' => 'error', 'message' => 'Access Point tidak ditemukan!'];
    }
    $stmt->close();
    
    // Delete (foreign key cascade akan hapus monitoring data)
    $stmt = $conn->prepare('DELETE FROM access_point WHERE id_ap = ?');
    $stmt->bind_param('i', $id_ap);
    
    if ($stmt->execute()) {
        $stmt->close();
        return ['status' => 'success', 'message' => 'Access Point berhasil dihapus!'];
    } else {
        $error = $stmt->error;
        $stmt->close();
        return ['status' => 'error', 'message' => $error];
    }
}

/**
 * Dapatkan monitoring data untuk AP tertentu
 */
function getMonitoringData($conn, $id_ap, $limit = 100) {
    $stmt = $conn->prepare('
        SELECT id_monitor, id_ap, trafik_in, trafik_out, jumlah_user, status_ap, waktu_monitoring
        FROM monitoring
        WHERE id_ap = ?
        ORDER BY waktu_monitoring DESC
        LIMIT ?
    ');
    
    $stmt->bind_param('ii', $id_ap, $limit);
    $stmt->execute();
    $result = $stmt->get_result();
    $data = fetchAll($result);
    $stmt->close();
    
    return $data;
}

/**
 * Hitung total record monitoring untuk AP tertentu.
 */
function getMonitoringCount($conn, $id_ap, $mon_date = null, $mon_status = null) {
    $mon_date = is_string($mon_date) ? trim($mon_date) : null;
    $mon_status = is_string($mon_status) ? trim($mon_status) : null;

    $has_date = !empty($mon_date);
    $has_status = !empty($mon_status);

    // Filter tanggal pakai range agar index waktu_monitoring tetap kepakai.
    $start = null;
    $end = null;
    if ($has_date) {
        $start = $mon_date . ' 00:00:00';
        $end = date('Y-m-d H:i:s', strtotime($mon_date . ' +1 day'));
    }

    if ($has_date && $has_status) {
        $stmt = $conn->prepare('
            SELECT COUNT(*) AS cnt
            FROM monitoring
            WHERE id_ap = ?
              AND status_ap = ?
              AND waktu_monitoring >= ?
              AND waktu_monitoring < ?
        ');
        $stmt->bind_param('isss', $id_ap, $mon_status, $start, $end);
    } elseif ($has_date) {
        $stmt = $conn->prepare('
            SELECT COUNT(*) AS cnt
            FROM monitoring
            WHERE id_ap = ?
              AND waktu_monitoring >= ?
              AND waktu_monitoring < ?
        ');
        $stmt->bind_param('iss', $id_ap, $start, $end);
    } elseif ($has_status) {
        $stmt = $conn->prepare('
            SELECT COUNT(*) AS cnt
            FROM monitoring
            WHERE id_ap = ?
              AND status_ap = ?
        ');
        $stmt->bind_param('is', $id_ap, $mon_status);
    } else {
        $stmt = $conn->prepare('
            SELECT COUNT(*) AS cnt
            FROM monitoring
            WHERE id_ap = ?
        ');
        $stmt->bind_param('i', $id_ap);
    }

    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return isset($row['cnt']) ? (int) $row['cnt'] : 0;
}

/**
 * Ambil data monitoring dengan paging (LIMIT/OFFSET) agar tabel tidak terlalu panjang.
 */
function getMonitoringDataPaged($conn, $id_ap, $limit = 10, $offset = 0, $mon_date = null, $mon_status = null) {
    $limit = max(1, (int) $limit);
    $offset = max(0, (int) $offset);

    $mon_date = is_string($mon_date) ? trim($mon_date) : null;
    $mon_status = is_string($mon_status) ? trim($mon_status) : null;

    $has_date = !empty($mon_date);
    $has_status = !empty($mon_status);

    $start = null;
    $end = null;
    if ($has_date) {
        $start = $mon_date . ' 00:00:00';
        $end = date('Y-m-d H:i:s', strtotime($mon_date . ' +1 day'));
    }

    if ($has_date && $has_status) {
        $stmt = $conn->prepare('
            SELECT id_monitor, id_ap, trafik_in, trafik_out, jumlah_user, status_ap, waktu_monitoring
            FROM monitoring
            WHERE id_ap = ?
              AND status_ap = ?
              AND waktu_monitoring >= ?
              AND waktu_monitoring < ?
            ORDER BY waktu_monitoring DESC
            LIMIT ? OFFSET ?
        ');
        $stmt->bind_param('isssii', $id_ap, $mon_status, $start, $end, $limit, $offset);
    } elseif ($has_date) {
        $stmt = $conn->prepare('
            SELECT id_monitor, id_ap, trafik_in, trafik_out, jumlah_user, status_ap, waktu_monitoring
            FROM monitoring
            WHERE id_ap = ?
              AND waktu_monitoring >= ?
              AND waktu_monitoring < ?
            ORDER BY waktu_monitoring DESC
            LIMIT ? OFFSET ?
        ');
        $stmt->bind_param('issii', $id_ap, $start, $end, $limit, $offset);
    } elseif ($has_status) {
        $stmt = $conn->prepare('
            SELECT id_monitor, id_ap, trafik_in, trafik_out, jumlah_user, status_ap, waktu_monitoring
            FROM monitoring
            WHERE id_ap = ?
              AND status_ap = ?
            ORDER BY waktu_monitoring DESC
            LIMIT ? OFFSET ?
        ');
        $stmt->bind_param('isii', $id_ap, $mon_status, $limit, $offset);
    } else {
        $stmt = $conn->prepare('
            SELECT id_monitor, id_ap, trafik_in, trafik_out, jumlah_user, status_ap, waktu_monitoring
            FROM monitoring
            WHERE id_ap = ?
            ORDER BY waktu_monitoring DESC
            LIMIT ? OFFSET ?
        ');
        $stmt->bind_param('iii', $id_ap, $limit, $offset);
    }

    $stmt->execute();
    $result = $stmt->get_result();
    $data = fetchAll($result);
    $stmt->close();

    return $data;
}

/**
 * Ambil 1 record monitoring terbaru untuk AP tertentu.
 */
function getLatestMonitoringRow($conn, $id_ap) {
    $rows = getMonitoringData($conn, $id_ap, 1);
    return (count($rows) > 0) ? $rows[0] : null;
}

/**
 * Hapus data monitoring yang lebih lama dari N hari untuk AP tertentu.
 * Dipanggil setelah insert monitoring (refresh SNMP) agar tabel tetap kecil dan query tetap cepat.
 */
function cleanupOldMonitoringData($conn, $id_ap, $days = 30) {
    $days = (int) $days;
    if ($days <= 0) {
        return;
    }

    $stmt = $conn->prepare('
        DELETE FROM monitoring
        WHERE id_ap = ?
          AND waktu_monitoring < DATE_SUB(NOW(), INTERVAL ? DAY)
    ');

    if (!$stmt) {
        return;
    }

    $stmt->bind_param('ii', $id_ap, $days);
    $stmt->execute();
    $stmt->close();
}

/**
 * Dapatkan statistik monitoring
 */
function getMonitoringStats($conn, $id_ap) {
    $stmt = $conn->prepare('
        SELECT 
            COUNT(*) as total_records,
            AVG(trafik_in) as avg_traffic_in,
            AVG(trafik_out) as avg_traffic_out,
            MAX(trafik_in) as max_traffic_in,
            MAX(trafik_out) as max_traffic_out,
            MIN(trafik_in) as min_traffic_in,
            MIN(trafik_out) as min_traffic_out,
            AVG(jumlah_user) as avg_users,
            MAX(jumlah_user) as max_users,
            SUM(CASE WHEN status_ap = "Online" THEN 1 ELSE 0 END) as online_count,
            SUM(CASE WHEN status_ap = "Offline" THEN 1 ELSE 0 END) as offline_count
        FROM monitoring
        WHERE id_ap = ?
    ');
    
    $stmt->bind_param('i', $id_ap);
    $stmt->execute();
    $result = $stmt->get_result();
    $stats = $result->fetch_assoc();
    $stmt->close();
    
    return $stats;
}

/**
 * Dapatkan summary status semua AP
 */
function getAccessPointsSummary($conn) {
    $query = '
        SELECT 
            ap.id_ap,
            ap.nama_ap,
            ap.ip_address,
            ap.status,
            l.nama_lantai,
            g.nama_gedung,
            (SELECT trafik_in FROM monitoring WHERE id_ap = ap.id_ap ORDER BY waktu_monitoring DESC LIMIT 1) as last_traffic_in,
            (SELECT trafik_out FROM monitoring WHERE id_ap = ap.id_ap ORDER BY waktu_monitoring DESC LIMIT 1) as last_traffic_out,
            (SELECT jumlah_user FROM monitoring WHERE id_ap = ap.id_ap ORDER BY waktu_monitoring DESC LIMIT 1) as last_users,
            (SELECT waktu_monitoring FROM monitoring WHERE id_ap = ap.id_ap ORDER BY waktu_monitoring DESC LIMIT 1) as last_monitoring
        FROM access_point ap
        LEFT JOIN lantai l ON l.id_lantai = ap.id_lantai
        LEFT JOIN gedung g ON g.id_gedung = l.id_gedung
        ORDER BY ap.nama_ap ASC
    ';
    
    $result = $conn->query($query);
    if ($result) {
        return fetchAll($result);
    }

    // Fallback (compat): database belum punya tabel/kolom lokasi baru.
    $query2 = '
        SELECT 
            ap.id_ap,
            ap.nama_ap,
            ap.ip_address,
            ap.status,
            NULL as nama_lantai,
            NULL as nama_gedung,
            (SELECT trafik_in FROM monitoring WHERE id_ap = ap.id_ap ORDER BY waktu_monitoring DESC LIMIT 1) as last_traffic_in,
            (SELECT trafik_out FROM monitoring WHERE id_ap = ap.id_ap ORDER BY waktu_monitoring DESC LIMIT 1) as last_traffic_out,
            (SELECT jumlah_user FROM monitoring WHERE id_ap = ap.id_ap ORDER BY waktu_monitoring DESC LIMIT 1) as last_users,
            (SELECT waktu_monitoring FROM monitoring WHERE id_ap = ap.id_ap ORDER BY waktu_monitoring DESC LIMIT 1) as last_monitoring
        FROM access_point ap
        ORDER BY ap.nama_ap ASC
    ';

    $result2 = $conn->query($query2);
    return fetchAll($result2);
}

?>
