<?php
/**
 * File untuk pengecekan login
 * Redirect ke halaman yang sesuai
 */

require_once dirname(__FILE__) . '/../config/session.php';
require_once dirname(__FILE__) . '/../middleware/auth_check.php';

// Cek apakah user sudah login
if (!isLoggedIn()) {
    header('Location: ' . getBaseUrl() . '/auth/login.php');
    exit();
}

// Redirect sesuai role
if ($_SESSION['role'] === 'superadmin') {
    header('Location: ' . getBaseUrl() . '/superadmin/dashboard.php');
} else {
    header('Location: ' . getBaseUrl() . '/admin/dashboard.php');
}
exit();

?>
