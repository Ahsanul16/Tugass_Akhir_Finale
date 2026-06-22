<?php
/**
 * SuperAdmin - Kelola Gedung (CRUD)
 */

$page_title = 'Kelola Gedung';

require_once dirname(__FILE__) . '/../config/session.php';
require_once dirname(__FILE__) . '/../config/database.php';
require_once dirname(__FILE__) . '/../middleware/auth_check.php';
require_once dirname(__FILE__) . '/../middleware/csrf.php';
require_once dirname(__FILE__) . '/../helpers/location.php';

$base_url = getBaseUrl();
requireRole('superadmin');

$action = isset($_GET['action']) ? (string) $_GET['action'] : 'list';
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf_token = isset($_POST['csrf_token']) ? (string) $_POST['csrf_token'] : '';
    $form_action = isset($_POST['form_action']) ? (string) $_POST['form_action'] : $action;

    if (!verifyCSRFToken($csrf_token)) {
        $error = 'Invalid CSRF token!';
    } else {
        if ($form_action === 'add') {
            $res = createGedung($conn, $_POST);
            if ($res['status'] === 'success') {
                header('Location: ' . $base_url . '/superadmin/manage_gedung.php?success=add');
                exit();
            }
            $error = $res['message'];
        } elseif ($form_action === 'edit') {
            $id = isset($_POST['id_gedung']) ? (int) $_POST['id_gedung'] : 0;
            $res = updateGedung($conn, $id, $_POST);
            if ($res['status'] === 'success') {
                header('Location: ' . $base_url . '/superadmin/manage_gedung.php?success=edit');
                exit();
            }
            $error = $res['message'];
        }
    }
}

if ($action === 'delete') {
    $id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
    if ($id > 0) {
        deleteGedung($conn, $id);
        header('Location: ' . $base_url . '/superadmin/manage_gedung.php?success=delete');
        exit();
    }
}

$csrf_token = generateCSRFToken();

$gedung_data = null;
if ($action === 'edit') {
    $id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
    if ($id > 0) {
        $gedung_data = getGedungById($conn, $id);
        if (!$gedung_data) {
            header('Location: ' . $base_url . '/superadmin/manage_gedung.php');
            exit();
        }
    }
}

$gedung_list = getAllGedung($conn);

if (isset($_GET['success'])) {
    $t = (string) $_GET['success'];
    if ($t === 'add') $message = 'Gedung berhasil ditambahkan!';
    if ($t === 'edit') $message = 'Gedung berhasil diperbarui!';
    if ($t === 'delete') $message = 'Gedung berhasil dihapus!';
}

include dirname(__FILE__) . '/../templates/header.php';
?>

<nav aria-label="breadcrumb">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="<?php echo $base_url; ?>/superadmin/dashboard.php">Dashboard</a></li>
        <li class="breadcrumb-item active">Kelola Gedung</li>
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
    <div class="row">
        <div class="col-md-7">
            <div class="card">
                <div class="card-header">
                    <i class="bi bi-building"></i>
                    <?php echo ($action === 'add') ? 'Tambah Gedung' : 'Edit Gedung'; ?>
                </div>
                <div class="card-body">
                    <form method="POST" action="">
                        <input type="hidden" name="form_action" value="<?php echo $action; ?>">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                        <?php if ($action === 'edit'): ?>
                            <input type="hidden" name="id_gedung" value="<?php echo (int) $gedung_data['id_gedung']; ?>">
                        <?php endif; ?>

                        <div class="mb-3">
                            <label class="form-label">Nama Gedung *</label>
                            <input type="text" class="form-control" name="nama_gedung"
                                   value="<?php echo $gedung_data ? htmlspecialchars($gedung_data['nama_gedung']) : ''; ?>"
                                   placeholder="Contoh: Gedung A" required>
                        </div>

                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-check-circle"></i> <?php echo ($action === 'add') ? 'Tambah' : 'Update'; ?>
                            </button>
                            <a href="<?php echo $base_url; ?>/superadmin/manage_gedung.php" class="btn btn-secondary">
                                <i class="bi bi-x-circle"></i> Batal
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <div class="col-md-5">
            <div class="card bg-light">
                <div class="card-header">
                    <i class="bi bi-info-circle"></i> Catatan
                </div>
                <div class="card-body small text-muted">
                    Gunakan Gedung untuk mengelompokkan Access Point. Setelah gedung dibuat, buat lantai di menu "Kelola Lantai".
                </div>
            </div>
        </div>
    </div>
<?php else: ?>
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h3 class="m-0"><i class="bi bi-building"></i> Daftar Gedung</h3>
        <a class="btn btn-success" href="<?php echo $base_url; ?>/superadmin/manage_gedung.php?action=add">
            <i class="bi bi-plus-circle"></i> Tambah Gedung
        </a>
    </div>

    <div class="card">
        <div class="card-body">
            <?php if (empty($gedung_list)): ?>
                <div class="alert alert-info m-0"><i class="bi bi-info-circle"></i> Belum ada gedung.</div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover table-striped">
                        <thead class="table-light">
                            <tr>
                                <th style="width: 10%">#</th>
                                <th>Nama Gedung</th>
                                <th style="width: 25%">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $no = 1; foreach ($gedung_list as $g): ?>
                                <tr>
                                    <td><?php echo $no++; ?></td>
                                    <td><strong><?php echo htmlspecialchars($g['nama_gedung']); ?></strong></td>
                                    <td class="d-flex gap-2">
                                        <a class="btn btn-sm btn-warning" href="<?php echo $base_url; ?>/superadmin/manage_gedung.php?action=edit&id=<?php echo (int) $g['id_gedung']; ?>">
                                            <i class="bi bi-pencil-square"></i> Edit
                                        </a>
                                        <a class="btn btn-sm btn-danger" href="<?php echo $base_url; ?>/superadmin/manage_gedung.php?action=delete&id=<?php echo (int) $g['id_gedung']; ?>"
                                           onclick="return confirmDelete('Hapus gedung ini? Lantai di dalamnya juga akan terhapus.');">
                                            <i class="bi bi-trash"></i> Hapus
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
<?php endif; ?>

<?php include dirname(__FILE__) . '/../templates/footer.php'; ?>

