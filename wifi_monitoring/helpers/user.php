<?php
/**
 * Helper Functions untuk User Management
 * CRUD Operations untuk users
 */

require_once dirname(__FILE__) . '/../config/database.php';

/**
 * Dapatkan semua users
 */
function getAllUsers($conn) {
    $query = 'SELECT id_user, username, nama, role, created_at FROM users ORDER BY created_at DESC';
    $result = $conn->query($query);
    return fetchAll($result);
}

/**
 * Dapatkan user berdasarkan ID
 */
function getUserById($conn, $id_user) {
    $stmt = $conn->prepare('SELECT id_user, username, nama, role, created_at FROM users WHERE id_user = ?');
    $stmt->bind_param('i', $id_user);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();
    return $user;
}

/**
 * Tambah user baru
 */
function createUser($conn, $data) {
    // Validasi
    if (empty($data['username']) || empty($data['password']) || empty($data['nama'])) {
        return ['status' => 'error', 'message' => 'Username, password, dan nama harus diisi!'];
    }
    
    // Cek username duplikat
    $stmt = $conn->prepare('SELECT id_user FROM users WHERE username = ?');
    $stmt->bind_param('s', $data['username']);
    $stmt->execute();
    if ($stmt->get_result()->num_rows > 0) {
        $stmt->close();
        return ['status' => 'error', 'message' => 'Username sudah terdaftar!'];
    }
    $stmt->close();
    
    // Hash password
    $password_hash = password_hash($data['password'], PASSWORD_BCRYPT, ['cost' => 10]);
    $role = $data['role'] ?? 'admin';
    
    // Insert user
    $stmt = $conn->prepare('
        INSERT INTO users (username, password, nama, role)
        VALUES (?, ?, ?, ?)
    ');
    
    if (!$stmt) {
        return ['status' => 'error', 'message' => $conn->error];
    }
    
    $stmt->bind_param('ssss', $data['username'], $password_hash, $data['nama'], $role);
    
    if ($stmt->execute()) {
        $id_user = $conn->insert_id;
        $stmt->close();
        return ['status' => 'success', 'message' => 'User berhasil dibuat!', 'id_user' => $id_user];
    } else {
        $error = $stmt->error;
        $stmt->close();
        return ['status' => 'error', 'message' => $error];
    }
}

/**
 * Update user
 */
function updateUser($conn, $id_user, $data) {
    // Validasi
    if (empty($data['username']) || empty($data['nama'])) {
        return ['status' => 'error', 'message' => 'Username dan nama harus diisi!'];
    }
    
    // Cek username duplikat (exclude current user)
    $stmt = $conn->prepare('SELECT id_user FROM users WHERE username = ? AND id_user != ?');
    $stmt->bind_param('si', $data['username'], $id_user);
    $stmt->execute();
    if ($stmt->get_result()->num_rows > 0) {
        $stmt->close();
        return ['status' => 'error', 'message' => 'Username sudah terdaftar!'];
    }
    $stmt->close();
    
    // Update tanpa password
    if (empty($data['password'])) {
        $stmt = $conn->prepare('
            UPDATE users 
            SET username = ?, nama = ?, role = ?
            WHERE id_user = ?
        ');
        
        $stmt->bind_param('sssi', $data['username'], $data['nama'], $data['role'], $id_user);
    } else {
        // Update dengan password
        $password_hash = password_hash($data['password'], PASSWORD_BCRYPT, ['cost' => 10]);
        $stmt = $conn->prepare('
            UPDATE users 
            SET username = ?, password = ?, nama = ?, role = ?
            WHERE id_user = ?
        ');
        
        $stmt->bind_param('ssssi', $data['username'], $password_hash, $data['nama'], $data['role'], $id_user);
    }
    
    if (!$stmt) {
        return ['status' => 'error', 'message' => $conn->error];
    }
    
    if ($stmt->execute()) {
        $stmt->close();
        return ['status' => 'success', 'message' => 'User berhasil diperbarui!'];
    } else {
        $error = $stmt->error;
        $stmt->close();
        return ['status' => 'error', 'message' => $error];
    }
}

/**
 * Hapus user
 */
function deleteUser($conn, $id_user) {
    // Cek apakah user ada
    $stmt = $conn->prepare('SELECT id_user FROM users WHERE id_user = ?');
    $stmt->bind_param('i', $id_user);
    $stmt->execute();
    if ($stmt->get_result()->num_rows == 0) {
        $stmt->close();
        return ['status' => 'error', 'message' => 'User tidak ditemukan!'];
    }
    $stmt->close();
    
    // Hapus user
    $stmt = $conn->prepare('DELETE FROM users WHERE id_user = ?');
    $stmt->bind_param('i', $id_user);
    
    if ($stmt->execute()) {
        $stmt->close();
        return ['status' => 'success', 'message' => 'User berhasil dihapus!'];
    } else {
        $error = $stmt->error;
        $stmt->close();
        return ['status' => 'error', 'message' => $error];
    }
}

/**
 * Hitung total user per role
 */
function getUserStatsByRole($conn) {
    $query = 'SELECT role, COUNT(*) as total FROM users GROUP BY role';
    $result = $conn->query($query);
    $stats = [];
    
    while ($row = $result->fetch_assoc()) {
        $stats[$row['role']] = $row['total'];
    }
    
    return $stats;
}

?>
