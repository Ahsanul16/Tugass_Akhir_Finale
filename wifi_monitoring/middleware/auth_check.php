<?php
/**
 * Middleware Autentikasi
 * Mengecek apakah user sudah login dan memiliki akses
 */

require_once dirname(__FILE__) . '/../config/session.php';

/**
 * Fungsi untuk mengecek apakah user sudah login
 * 
 * @return bool true jika user sudah login
 */
function isLoggedIn() {
    return isset($_SESSION['id_user']) && isset($_SESSION['username']) && isset($_SESSION['role']);
}

/**
 * Fungsi untuk mengecek role user
 * 
 * @param string|array $required_role Role yang diizinkan
 * @return bool true jika user memiliki akses
 */
function hasRole($required_role) {
    if (!isLoggedIn()) {
        return false;
    }
    
    if (is_array($required_role)) {
        return in_array($_SESSION['role'], $required_role);
    }
    
    return $_SESSION['role'] === $required_role;
}

/**
 * Fungsi untuk redirect ke login jika belum login
 */
function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: ' . getBaseUrl() . '/auth/login.php');
        exit();
    }
}

/**
 * Fungsi untuk redirect jika tidak memiliki role tertentu
 * 
 * @param string|array $required_role Role yang diizinkan
 */
function requireRole($required_role) {
    requireLogin();
    
    if (!hasRole($required_role)) {
        header('Location: ' . getBaseUrl() . '/index.php?error=access_denied');
        exit();
    }
}

/**
 * Fungsi untuk mendapatkan base URL aplikasi
 * 
 * @return string Base URL
 */
function getBaseUrl() {
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'];
    
    // base url
    $basePath = '/wifi_monitoring';
    
    return $protocol . '://' . $host . $basePath;
}

/**
 * Fungsi untuk mendapatkan informasi user dari session
 * 
 * @return array|null User data atau null
 */
function getCurrentUser() {
    if (!isLoggedIn()) {
        return null;
    }
    
    return [
        'id_user' => $_SESSION['id_user'],
        'username' => $_SESSION['username'],
        'nama' => $_SESSION['nama'],
        'role' => $_SESSION['role']
    ];
}

/**
 * Fungsi untuk logout
 */
function logout() {
    session_destroy();
    header('Location: ' . getBaseUrl() . '/auth/login.php');
    exit();
}

?>
