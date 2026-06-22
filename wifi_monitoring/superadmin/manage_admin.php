<?php
/**
 * SuperAdmin - Kelola Admin
 * Halaman untuk CRUD user management
 */

$page_title = 'Kelola Admin';

require_once dirname(__FILE__) . '/../config/session.php';
require_once dirname(__FILE__) . '/../config/database.php';
require_once dirname(__FILE__) . '/../middleware/auth_check.php';
require_once dirname(__FILE__) . '/../middleware/csrf.php';
require_once dirname(__FILE__) . '/../helpers/user.php';

// Proteksi halaman - hanya superadmin
requireRole('superadmin');

$action = isset($_GET['action']) ? $_GET['action'] : 'list';
$message = '';
$error = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf_token = isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '';
    
    if (!verifyCSRFToken($csrf_token)) {
        $error = 'Invalid CSRF token!';
    } else {
        if ($action === 'add') {
            $result = createUser($conn, $_POST);
            if ($result['status'] === 'success') {
                $message = $result['message'];
                header('Location: ' . getBaseUrl() . '/superadmin/manage_admin.php?success=add');
                exit();
            } else {
                $error = $result['message'];
            }
        } elseif ($action === 'edit') {
            $id_user = isset($_POST['id_user']) ? (int)$_POST['id_user'] : 0;
            $result = updateUser($conn, $id_user, $_POST);
            if ($result['status'] === 'success') {
                $message = $result['message'];
                header('Location: ' . getBaseUrl() . '/superadmin/manage_admin.php?success=edit');
                exit();
            } else {
                $error = $result['message'];
            }
        }
    }
}

// Handle delete
if ($action === 'delete') {
    $id_user = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    if ($id_user > 0) {
        // Prevent deleting superadmin account
        $user = getUserById($conn, $id_user);
        if ($user && $user['role'] === 'superadmin' && $user['id_user'] === $_SESSION['id_user']) {
            $error = 'Anda tidak dapat menghapus akun superadmin Anda sendiri!';
            $action = 'list';
        } else {
            $result = deleteUser($conn, $id_user);
            if ($result['status'] === 'success') {
                header('Location: ' . getBaseUrl() . '/superadmin/manage_admin.php?success=delete');
                exit();
            } else {
                $error = $result['message'];
                $action = 'list';
            }
        }
    }
}

$csrf_token = generateCSRFToken();

// Get data for edit action
$user_data = null;
if ($action === 'edit') {
    $id_user = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    if ($id_user > 0) {
        $user_data = getUserById($conn, $id_user);
        if (!$user_data) {
            header('Location: ' . getBaseUrl() . '/superadmin/manage_admin.php');
            exit();
        }
    }
}

// Get all users
$user_list = getAllUsers($conn);

// Check success messages
if (isset($_GET['success'])) {
    $success_type = $_GET['success'];
    if ($success_type === 'add') {
        $message = 'User berhasil ditambahkan!';
    } elseif ($success_type === 'edit') {
        $message = 'User berhasil diperbarui!';
    } elseif ($success_type === 'delete') {
        $message = 'User berhasil dihapus!';
    }
}

include dirname(__FILE__) . '/../templates/header.php';
?>

<!-- Breadcrumb -->
<nav aria-label="breadcrumb">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="<?php echo $base_url; ?>/superadmin/dashboard.php">Dashboard</a></li>
        <li class="breadcrumb-item active">Kelola Admin</li>
    </ol>
</nav>

<?php if (!empty($message)): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="bi bi-check-circle"></i> <?php echo htmlspecialchars($message); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<?php if (!empty($error)): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="bi bi-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<?php if ($action === 'add' || $action === 'edit'): ?>
    <!-- Form Add/Edit -->
    <div class="row">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <i class="bi bi-person-plus"></i> 
                    <?php echo ($action === 'add') ? 'Tambah User Baru' : 'Edit User'; ?>
                </div>
                <div class="card-body">
                    <form method="POST" action="">
                        <div class="mb-3">
                            <label for="username" class="form-label">Username *</label>
                            <input type="text" class="form-control" id="username" name="username" 
                                   value="<?php echo $user_data ? htmlspecialchars($user_data['username']) : ''; ?>" 
                                   required>
                            <small class="text-muted">Username harus unik</small>
                        </div>
                        
                        <div class="mb-3">
                            <label for="nama" class="form-label">Nama Lengkap *</label>
                            <input type="text" class="form-control" id="nama" name="nama" 
                                   value="<?php echo $user_data ? htmlspecialchars($user_data['nama']) : ''; ?>" 
                                   required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="password" class="form-label">
                                Password <?php echo ($action === 'edit') ? '(Kosongkan jika tidak ingin mengubah)' : '*'; ?>
                            </label>
                            <input type="password" class="form-control" id="password" name="password" 
                                   <?php echo ($action === 'add') ? 'required' : ''; ?>>
                            <?php if ($action === 'add'): ?>
                                <small class="text-muted">Minimal 6 karakter</small>
                            <?php endif; ?>
                        </div>
                        
                        <div class="mb-3">
                            <label for="role" class="form-label">Role *</label>
                            <select class="form-select" id="role" name="role" required>
                                <option value="">-- Pilih Role --</option>
                                <option value="superadmin" <?php echo ($user_data && $user_data['role'] === 'superadmin') ? 'selected' : ''; ?>>
                                    SuperAdmin
                                </option>
                                <option value="admin" <?php echo ($user_data && $user_data['role'] === 'admin') ? 'selected' : ''; ?>>
                                    Admin
                                </option>
                            </select>
                        </div>
                        
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                        <?php if ($action === 'edit' && $user_data): ?>
                            <input type="hidden" name="id_user" value="<?php echo $user_data['id_user']; ?>">
                        <?php endif; ?>
                        
                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-check-circle"></i> 
                                <?php echo ($action === 'add') ? 'Tambah' : 'Update'; ?>
                            </button>
                            <a href="<?php echo $base_url; ?>/superadmin/manage_admin.php" class="btn btn-secondary">
                                <i class="bi bi-x-circle"></i> Batal
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        
        <div class="col-md-6">
            <div class="card bg-light">
                <div class="card-header bg-info text-white">
                    <i class="bi bi-info-circle"></i> Informasi Role
                </div>
                <div class="card-body">
                    <p class="small mb-3"><strong>SuperAdmin:</strong></p>
                    <ul class="small mb-3">
                        <li>Akses penuh ke semua fitur</li>
                        <li>Dapat mengelola user</li>
                        <li>Dapat mengelola Access Point</li>
                        <li>Dapat melihat semua data monitoring</li>
                    </ul>
                    
                    <p class="small mb-3"><strong>Admin:</strong></p>
                    <ul class="small">
                        <li>Dapat melihat dashboard</li>
                        <li>Dapat melihat data Access Point</li>
                        <li>Dapat melihat data monitoring</li>
                        <li>Tidak dapat mengelola user</li>
                        <li>Tidak dapat menghapus akun</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

<?php else: ?>
    <!-- List View -->
    <div class="row">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span><i class="bi bi-people"></i> Daftar User</span>
                    <a href="<?php echo $base_url; ?>/superadmin/manage_admin.php?action=add" class="btn btn-sm btn-success">
                        <i class="bi bi-person-plus"></i> Tambah User
                    </a>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Username</th>
                                    <th>Nama Lengkap</th>
                                    <th>Role</th>
                                    <th>Terdaftar Sejak</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (count($user_list) > 0): ?>
                                    <?php foreach ($user_list as $user): ?>
                                        <tr>
                                            <td>
                                                <small class="text-muted">#<?php echo $user['id_user']; ?></small>
                                            </td>
                                            <td>
                                                <strong><?php echo htmlspecialchars($user['username']); ?></strong>
                                            </td>
                                            <td>
                                                <?php echo htmlspecialchars($user['nama']); ?>
                                            </td>
                                            <td>
                                                <?php 
                                                $badge_class = ($user['role'] === 'superadmin') ? 'bg-danger' : 'bg-info';
                                                $role_icon = ($user['role'] === 'superadmin') ? 'bi-shield-star' : 'bi-shield-check';
                                                ?>
                                                <span class="badge <?php echo $badge_class; ?> fw-bold" style="font-size: 0.95rem; padding: 0.5rem 0.75rem;">
                                                    <i class="bi <?php echo $role_icon; ?>"></i>
                                                    <?php echo ucfirst($user['role']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <small class="text-muted">
                                                    <?php echo date('d M Y H:i', strtotime($user['created_at'])); ?>
                                                </small>
                                            </td>
                                            <td>
                                                <a href="<?php echo $base_url; ?>/superadmin/manage_admin.php?action=edit&id=<?php echo $user['id_user']; ?>" 
                                                   class="btn btn-sm btn-warning" title="Edit">
                                                    <i class="bi bi-pencil"></i>
                                                </a>
                                                <?php if ($user['id_user'] !== $_SESSION['id_user']): ?>
                                                    <a href="<?php echo $base_url; ?>/superadmin/manage_admin.php?action=delete&id=<?php echo $user['id_user']; ?>" 
                                                       class="btn btn-sm btn-danger" title="Hapus" onclick="return confirm('Apakah Anda yakin ingin menghapus user ini?')">
                                                        <i class="bi bi-trash"></i>
                                                    </a>
                                                <?php else: ?>
                                                    <span class="badge bg-secondary">Akun Anda</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="6" class="text-center py-4">
                                            <i class="bi bi-inbox" style="font-size: 24px;"></i>
                                            <p class="mt-2">Belum ada user</p>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

<?php endif; ?>

<?php include dirname(__FILE__) . '/../templates/footer.php'; ?>
