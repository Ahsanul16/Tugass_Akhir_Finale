<?php
/**
 * Template Header
 * Digunakan di semua halaman
 */

require_once dirname(__FILE__) . '/../middleware/auth_check.php';

// Pastikan user sudah login
requireLogin();

$current_user = getCurrentUser();
$base_url = getBaseUrl();
$current_page = basename($_SERVER['PHP_SELF']);

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? htmlspecialchars($page_title) . ' - ' : ''; ?>Monitoring Access Point</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css">
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@3.7.1/dist/chart.min.js"></script>
    <!-- Custom CSS -->
    <link rel="stylesheet" href="<?php echo $base_url; ?>/assets/css/style.css">
    
    <?php if (isset($additional_css)): ?>
        <?php echo $additional_css; ?>
    <?php endif; ?>
</head>
<body>
    <!-- Navigation Bar -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-gradient">
        <div class="container-fluid">
            <a class="navbar-brand fw-bold" href="<?php echo $base_url; ?>">
                <i class="bi bi-wifi"></i> Monitoring AP
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <span class="nav-link">
                            <i class="bi bi-person-circle"></i> 
                            <?php echo htmlspecialchars($current_user['nama']); ?> 
                            <?php 
                                $role = $current_user['role'];
                                $badge_class = ($role === 'superadmin') ? 'bg-danger' : 'bg-warning';
                                $role_icon = ($role === 'superadmin') ? 'bi-shield-star' : 'bi-shield-check';
                            ?>
                            <span class="badge <?php echo $badge_class; ?>" style="font-size: 0.9rem;">
                                <i class="bi <?php echo $role_icon; ?>"></i> 
                                <?php echo ucfirst($role); ?>
                            </span>
                        </span>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo $base_url; ?>/auth/logout.php">
                            <i class="bi bi-box-arrow-right"></i> Logout
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <nav class="col-md-3 col-lg-2 d-md-block bg-sidebar sidebar">
                <div class="position-sticky pt-3">
                    <ul class="nav flex-column">
                        <?php if ($current_user['role'] === 'superadmin'): ?>
                            <!-- SuperAdmin Menu -->
                            <li class="nav-item">
                                <a class="nav-link <?php echo ($current_page === 'dashboard.php') ? 'active' : ''; ?>" 
                                   href="<?php echo $base_url; ?>/superadmin/dashboard.php">
                                    <i class="bi bi-house-door"></i> Dashboard
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link <?php echo ($current_page === 'manage_access_point.php') ? 'active' : ''; ?>" 
                                   href="<?php echo $base_url; ?>/superadmin/manage_access_point.php">
                                    <i class="bi bi-router"></i> Kelola AP
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link <?php echo ($current_page === 'manage_gedung.php') ? 'active' : ''; ?>"
                                   href="<?php echo $base_url; ?>/superadmin/manage_gedung.php">
                                    <i class="bi bi-building"></i> Kelola Gedung
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link <?php echo ($current_page === 'manage_lantai.php') ? 'active' : ''; ?>"
                                   href="<?php echo $base_url; ?>/superadmin/manage_lantai.php">
                                    <i class="bi bi-layers"></i> Kelola Lantai
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link <?php echo ($current_page === 'access_point.php') ? 'active' : ''; ?>" 
                                   href="<?php echo $base_url; ?>/admin/access_point.php">
                                    <i class="bi bi-graph-up"></i> Lihat Detail AP
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link <?php echo ($current_page === 'manage_admin.php') ? 'active' : ''; ?>" 
                                   href="<?php echo $base_url; ?>/superadmin/manage_admin.php">
                                    <i class="bi bi-people"></i> Kelola Admin
                                </a>
                            </li>
                        <?php else: ?>
                            <!-- Admin Menu -->
                            <li class="nav-item">
                                <a class="nav-link <?php echo ($current_page === 'dashboard.php') ? 'active' : ''; ?>" 
                                   href="<?php echo $base_url; ?>/admin/dashboard.php">
                                    <i class="bi bi-house-door"></i> Dashboard
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link <?php echo ($current_page === 'manage_access_point.php') ? 'active' : ''; ?>" 
                                   href="<?php echo $base_url; ?>/admin/manage_access_point.php">
                                    <i class="bi bi-router"></i> Kelola AP
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link <?php echo ($current_page === 'manage_gedung.php') ? 'active' : ''; ?>"
                                   href="<?php echo $base_url; ?>/admin/manage_gedung.php">
                                    <i class="bi bi-building"></i> Kelola Gedung
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link <?php echo ($current_page === 'manage_lantai.php') ? 'active' : ''; ?>"
                                   href="<?php echo $base_url; ?>/admin/manage_lantai.php">
                                    <i class="bi bi-layers"></i> Kelola Lantai
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link <?php echo ($current_page === 'access_point.php') ? 'active' : ''; ?>" 
                                   href="<?php echo $base_url; ?>/admin/access_point.php">
                                    <i class="bi bi-graph-up"></i> Lihat Detail AP
                                </a>
                            </li>
                        <?php endif; ?>
                    </ul>
                </div>
            </nav>

            <!-- Main Content -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-4 py-4 main-content">
