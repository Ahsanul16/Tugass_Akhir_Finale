<?php
/**
 * Index.php - Entry Point Aplikasi
 * Halaman utama yang meredirect sesuai status login dan role
 */

require_once dirname(__FILE__) . '/config/session.php';
require_once dirname(__FILE__) . '/middleware/auth_check.php';

// Jika user sudah login, redirect ke dashboard sesuai role
if (isLoggedIn()) {
    if ($_SESSION['role'] === 'superadmin') {
        header('Location: ' . getBaseUrl() . '/superadmin/dashboard.php');
    } else {
        header('Location: ' . getBaseUrl() . '/admin/dashboard.php');
    }
    exit();
}

// Jika belum login, redirect ke halaman login
header('Location: ' . getBaseUrl() . '/auth/login.php');
exit();

?>
