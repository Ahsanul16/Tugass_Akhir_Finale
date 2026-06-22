<?php
/**
 * Halaman Logout
 */

require_once dirname(__FILE__) . '/../config/session.php';
require_once dirname(__FILE__) . '/../middleware/auth_check.php';

// Bersihkan seluruh data session agar login tidak tersisa setelah logout.
$_SESSION = [];

if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params['path'],
        $params['domain'],
        $params['secure'],
        $params['httponly']
    );
}

session_destroy();

header('Location: ' . getBaseUrl() . '/auth/login.php?success=logout');
exit();
