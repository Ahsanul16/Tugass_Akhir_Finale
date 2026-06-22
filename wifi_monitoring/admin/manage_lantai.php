<?php
/**
 * Admin - Kelola Lantai (CRUD)
 */

$page_title = 'Kelola Lantai';

require_once dirname(__FILE__) . '/../config/session.php';
require_once dirname(__FILE__) . '/../config/database.php';
require_once dirname(__FILE__) . '/../middleware/auth_check.php';
require_once dirname(__FILE__) . '/../middleware/csrf.php';
require_once dirname(__FILE__) . '/../helpers/location.php';

$base_url = getBaseUrl();
requireRole(['admin', 'superadmin']);

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
            $res = createLantai($conn, $_POST);
            if ($res['status'] === 'success') {
                header('Location: ' . $base_url . '/admin/manage_lantai.php?success=add');
                exit();
            }
            $error = $res['message'];
        } elseif ($form_action === 'edit') {
            $id = isset($_POST['id_lantai']) ? (int) $_POST['id_lantai'] : 0;
            $res = updateLantai($conn, $id, $_POST);
            if ($res['status'] === 'success') {
                header('Location: ' . $base_url . '/admin/manage_lantai.php?success=edit');
                exit();
            }
            $error = $res['message'];
        }
    }
}

if ($action === 'delete') {
    $id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
    if ($id > 0) {
        deleteLantai($conn, $id);
        header('Location: ' . $base_url . '/admin/manage_lantai.php?success=delete');
        exit();
    }
}

$csrf_token = generateCSRFToken();
$gedung_list = getAllGedung($conn);

$lantai_data = null;
if ($action === 'edit') {
    $id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
    if ($id > 0) {
        $lantai_data = getLantaiById($conn, $id);
        if (!$lantai_data) {
            header('Location: ' . $base_url . '/admin/manage_lantai.php');
            exit();
        }
    }
}

$lantai_list = getAllLantai($conn);

if (isset($_GET['success'])) {
    $t = (string) $_GET['success'];
    if ($t === 'add') $message = 'Lantai berhasil ditambahkan!';
    if ($t === 'edit') $message = 'Lantai berhasil diperbarui!';
    if ($t === 'delete') $message = 'Lantai berhasil dihapus!';
}

include dirname(__FILE__) . '/../templates/header.php';
?>

<nav aria-label="breadcrumb">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="<?php echo $base_url; ?>/admin/dashboard.php">Dashboard</a></li>
        <li class="breadcrumb-item active">Kelola Lantai</li>
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
                    <i class="bi bi-layers"></i>
                    <?php echo ($action === 'add') ? 'Tambah Lantai' : 'Edit Lantai'; ?>
                </div>
                <div class="card-body">
                    <form method="POST" action="">
                        <input type="hidden" name="form_action" value="<?php echo $action; ?>">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                        <?php if ($action === 'edit'): ?>
                            <input type="hidden" name="id_lantai" value="<?php echo (int) $lantai_data['id_lantai']; ?>">
                        <?php endif; ?>

                        <div class="mb-3">
                            <label class="form-label">Gedung *</label>
                            <select class="form-select" name="id_gedung" required>
                                <option value="">-- Pilih Gedung --</option>
                                <?php foreach ($gedung_list as $g): ?>
                                    <?php $sel = ($lantai_data && (int) $lantai_data['id_gedung'] === (int) $g['id_gedung']) ? 'selected' : ''; ?>
                                    <option value="<?php echo (int) $g['id_gedung']; ?>" <?php echo $sel; ?>>
                                        <?php echo htmlspecialchars($g['nama_gedung']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Nama Lantai *</label>
                            <input type="text" class="form-control" name="nama_lantai"
                                   value="<?php echo $lantai_data ? htmlspecialchars($lantai_data['nama_lantai']) : ''; ?>"
                                   placeholder="Contoh: Lantai 1" required>
                        </div>

                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-check-circle"></i> <?php echo ($action === 'add') ? 'Tambah' : 'Update'; ?>
                            </button>
                            <a href="<?php echo $base_url; ?>/admin/manage_lantai.php" class="btn btn-secondary">
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
                    Lantai harus berada di dalam Gedung. Jika Gedung belum ada, buat dulu di menu "Kelola Gedung".
                </div>
            </div>
        </div>
    </div>
<?php else: ?>
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h3 class="m-0"><i class="bi bi-layers"></i> Daftar Lantai</h3>
        <a class="btn btn-success" href="<?php echo $base_url; ?>/admin/manage_lantai.php?action=add">
            <i class="bi bi-plus-circle"></i> Tambah Lantai
        </a>
    </div>

    <div class="card">
        <div class="card-body">
            <?php if (empty($lantai_list)): ?>
                <div class="alert alert-info m-0"><i class="bi bi-info-circle"></i> Belum ada lantai.</div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover table-striped">
                        <thead class="table-light">
                            <tr>
                                <th style="width: 10%">#</th>
                                <th style="width: 35%">Gedung</th>
                                <th>Nama Lantai</th>
                                <th style="width: 25%">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $no = 1; foreach ($lantai_list as $l): ?>
                                <tr>
                                    <td><?php echo $no++; ?></td>
                                    <td><strong><?php echo htmlspecialchars($l['nama_gedung']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($l['nama_lantai']); ?></td>
                                    <td class="d-flex gap-2">
                                        <a class="btn btn-sm btn-warning" href="<?php echo $base_url; ?>/admin/manage_lantai.php?action=edit&id=<?php echo (int) $l['id_lantai']; ?>">
                                            <i class="bi bi-pencil-square"></i> Edit
                                        </a>
                                        <a class="btn btn-sm btn-danger" href="<?php echo $base_url; ?>/admin/manage_lantai.php?action=delete&id=<?php echo (int) $l['id_lantai']; ?>"
                                           onclick="return confirmDelete('Hapus lantai ini? Access Point yang memakai lantai ini akan menjadi tanpa lantai (NULL).');">
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

