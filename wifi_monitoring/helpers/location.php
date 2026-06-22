<?php
/**
 * Helper Functions untuk Gedung & Lantai
 * - Gedung: master building
 * - Lantai: master floor (belongs to gedung)
 */

require_once dirname(__FILE__) . '/../config/database.php';

// ===== GEDUNG =====

function getAllGedung($conn) {
    $result = $conn->query('SELECT id_gedung, nama_gedung FROM gedung ORDER BY nama_gedung ASC');
    return fetchAll($result);
}

function getGedungById($conn, $id_gedung) {
    $stmt = $conn->prepare('SELECT id_gedung, nama_gedung FROM gedung WHERE id_gedung = ?');
    $stmt->bind_param('i', $id_gedung);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row;
}

function createGedung($conn, $data) {
    $nama = isset($data['nama_gedung']) ? trim((string) $data['nama_gedung']) : '';
    if ($nama === '') {
        return ['status' => 'error', 'message' => 'Nama gedung harus diisi!'];
    }

    $stmt = $conn->prepare('INSERT INTO gedung (nama_gedung) VALUES (?)');
    if (!$stmt) {
        return ['status' => 'error', 'message' => $conn->error];
    }

    $stmt->bind_param('s', $nama);
    if ($stmt->execute()) {
        $stmt->close();
        return ['status' => 'success', 'message' => 'Gedung berhasil ditambahkan!'];
    }

    $err = $stmt->error;
    $stmt->close();
    return ['status' => 'error', 'message' => $err];
}

function updateGedung($conn, $id_gedung, $data) {
    $nama = isset($data['nama_gedung']) ? trim((string) $data['nama_gedung']) : '';
    if ($nama === '') {
        return ['status' => 'error', 'message' => 'Nama gedung harus diisi!'];
    }

    $stmt = $conn->prepare('UPDATE gedung SET nama_gedung = ? WHERE id_gedung = ?');
    if (!$stmt) {
        return ['status' => 'error', 'message' => $conn->error];
    }

    $stmt->bind_param('si', $nama, $id_gedung);
    if ($stmt->execute()) {
        $stmt->close();
        return ['status' => 'success', 'message' => 'Gedung berhasil diperbarui!'];
    }

    $err = $stmt->error;
    $stmt->close();
    return ['status' => 'error', 'message' => $err];
}

function deleteGedung($conn, $id_gedung) {
    // FK lantai ON DELETE CASCADE akan hapus lantai, dan access_point ON DELETE SET NULL.
    $stmt = $conn->prepare('DELETE FROM gedung WHERE id_gedung = ?');
    if (!$stmt) {
        return ['status' => 'error', 'message' => $conn->error];
    }

    $stmt->bind_param('i', $id_gedung);
    if ($stmt->execute()) {
        $stmt->close();
        return ['status' => 'success', 'message' => 'Gedung berhasil dihapus!'];
    }

    $err = $stmt->error;
    $stmt->close();
    return ['status' => 'error', 'message' => $err];
}

// ===== LANTAI =====

function getAllLantai($conn) {
    $query = '
        SELECT l.id_lantai, l.id_gedung, l.nama_lantai, g.nama_gedung
        FROM lantai l
        JOIN gedung g ON g.id_gedung = l.id_gedung
        ORDER BY g.nama_gedung ASC, l.nama_lantai ASC
    ';
    $result = $conn->query($query);
    return fetchAll($result);
}

function getLantaiById($conn, $id_lantai) {
    $stmt = $conn->prepare('
        SELECT l.id_lantai, l.id_gedung, l.nama_lantai, g.nama_gedung
        FROM lantai l
        JOIN gedung g ON g.id_gedung = l.id_gedung
        WHERE l.id_lantai = ?
    ');
    $stmt->bind_param('i', $id_lantai);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row;
}

function getLantaiByGedung($conn, $id_gedung) {
    $stmt = $conn->prepare('
        SELECT id_lantai, id_gedung, nama_lantai
        FROM lantai
        WHERE id_gedung = ?
        ORDER BY nama_lantai ASC
    ');
    $stmt->bind_param('i', $id_gedung);
    $stmt->execute();
    $rows = fetchAll($stmt->get_result());
    $stmt->close();
    return $rows;
}

function createLantai($conn, $data) {
    $id_gedung = isset($data['id_gedung']) ? (int) $data['id_gedung'] : 0;
    $nama = isset($data['nama_lantai']) ? trim((string) $data['nama_lantai']) : '';

    if ($id_gedung <= 0) {
        return ['status' => 'error', 'message' => 'Gedung harus dipilih!'];
    }
    if ($nama === '') {
        return ['status' => 'error', 'message' => 'Nama lantai harus diisi!'];
    }

    $stmt = $conn->prepare('INSERT INTO lantai (id_gedung, nama_lantai) VALUES (?, ?)');
    if (!$stmt) {
        return ['status' => 'error', 'message' => $conn->error];
    }

    $stmt->bind_param('is', $id_gedung, $nama);
    if ($stmt->execute()) {
        $stmt->close();
        return ['status' => 'success', 'message' => 'Lantai berhasil ditambahkan!'];
    }

    $err = $stmt->error;
    $stmt->close();
    return ['status' => 'error', 'message' => $err];
}

function updateLantai($conn, $id_lantai, $data) {
    $id_gedung = isset($data['id_gedung']) ? (int) $data['id_gedung'] : 0;
    $nama = isset($data['nama_lantai']) ? trim((string) $data['nama_lantai']) : '';

    if ($id_gedung <= 0) {
        return ['status' => 'error', 'message' => 'Gedung harus dipilih!'];
    }
    if ($nama === '') {
        return ['status' => 'error', 'message' => 'Nama lantai harus diisi!'];
    }

    $stmt = $conn->prepare('UPDATE lantai SET id_gedung = ?, nama_lantai = ? WHERE id_lantai = ?');
    if (!$stmt) {
        return ['status' => 'error', 'message' => $conn->error];
    }

    $stmt->bind_param('isi', $id_gedung, $nama, $id_lantai);
    if ($stmt->execute()) {
        $stmt->close();
        return ['status' => 'success', 'message' => 'Lantai berhasil diperbarui!'];
    }

    $err = $stmt->error;
    $stmt->close();
    return ['status' => 'error', 'message' => $err];
}

function deleteLantai($conn, $id_lantai) {
    // access_point FK ON DELETE SET NULL
    $stmt = $conn->prepare('DELETE FROM lantai WHERE id_lantai = ?');
    if (!$stmt) {
        return ['status' => 'error', 'message' => $conn->error];
    }

    $stmt->bind_param('i', $id_lantai);
    if ($stmt->execute()) {
        $stmt->close();
        return ['status' => 'success', 'message' => 'Lantai berhasil dihapus!'];
    }

    $err = $stmt->error;
    $stmt->close();
    return ['status' => 'error', 'message' => $err];
}

?>

