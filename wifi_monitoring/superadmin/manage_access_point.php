<?php
/**
 * SuperAdmin - Kelola Access Point
 */

$page_title = 'Kelola Access Point';

require_once dirname(__FILE__) . '/../config/session.php';
require_once dirname(__FILE__) . '/../config/database.php';
require_once dirname(__FILE__) . '/../middleware/auth_check.php';
require_once dirname(__FILE__) . '/../middleware/csrf.php';
require_once dirname(__FILE__) . '/../helpers/access_point.php';
require_once dirname(__FILE__) . '/../helpers/location.php';

// Define $base_url untuk digunakan sebelum header.php di-include
$base_url = getBaseUrl();

requireRole('superadmin');

$action = isset($_GET['action']) ? $_GET['action'] : 'list';
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf_token = isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '';
    $form_action = isset($_POST['form_action']) ? $_POST['form_action'] : $action;
    
    if (!verifyCSRFToken($csrf_token)) {
        $error = 'Invalid CSRF token!';
    } else {
        if ($form_action === 'add') {
            $result = createAccessPoint($conn, $_POST);
            if ($result['status'] === 'success') {
                header('Location: ' . $base_url . '/superadmin/manage_access_point.php?success=add');
                exit();
            } else {
                $error = $result['message'];
            }
        } elseif ($form_action === 'edit') {
            $id_ap = isset($_POST['id_ap']) ? (int)$_POST['id_ap'] : 0;
            $result = updateAccessPoint($conn, $id_ap, $_POST);
            if ($result['status'] === 'success') {
                header('Location: ' . $base_url . '/superadmin/manage_access_point.php?success=edit');
                exit();
            } else {
                $error = $result['message'];
            }
        }
    }
}

if ($action === 'delete') {
    $id_ap = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    if ($id_ap > 0) {
        deleteAccessPoint($conn, $id_ap);
        header('Location: ' . $base_url . '/superadmin/manage_access_point.php?success=delete');
        exit();
    }
}

$csrf_token = generateCSRFToken();

$ap_data = null;
if ($action === 'edit') {
    $id_ap = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    if ($id_ap > 0) {
        $ap_data = getAccessPointById($conn, $id_ap);
        if (!$ap_data) {
            header('Location: ' . $base_url . '/superadmin/manage_access_point.php');
            exit();
        }
    }
}

// Location dropdown data (Gedung + Lantai)
$gedung_list = getAllGedung($conn);
$selected_gedung_id = 0;
$selected_lantai_id = 0;

if ($ap_data) {
    $selected_gedung_id = isset($ap_data['id_gedung']) ? (int) $ap_data['id_gedung'] : 0;
    $selected_lantai_id = isset($ap_data['id_lantai']) ? (int) $ap_data['id_lantai'] : 0;
} else {
    if (!empty($gedung_list)) {
        $selected_gedung_id = (int) $gedung_list[0]['id_gedung'];
    }
}

$lantai_list = ($selected_gedung_id > 0) ? getLantaiByGedung($conn, $selected_gedung_id) : [];

$ap_list = getAllAccessPoints($conn);

if (isset($_GET['success'])) {
    $success_type = $_GET['success'];
    if ($success_type === 'add') {
        $message = 'Access Point berhasil ditambahkan!';
    } elseif ($success_type === 'edit') {
        $message = 'Access Point berhasil diperbarui!';
    } elseif ($success_type === 'delete') {
        $message = 'Access Point berhasil dihapus!';
    }
}

include dirname(__FILE__) . '/../templates/header.php';
?>

<!-- Breadcrumb -->
<nav aria-label="breadcrumb">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="<?php echo $base_url; ?>/superadmin/dashboard.php">Dashboard</a></li>
        <li class="breadcrumb-item active">Kelola Access Point</li>
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
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <i class="bi bi-plus-circle"></i> 
                    <?php echo ($action === 'add') ? 'Tambah Access Point' : 'Edit Access Point'; ?>
                </div>
                <div class="card-body">
                    <form method="POST" action="">
                        <input type="hidden" name="form_action" value="<?php echo $action; ?>">
                        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                        <?php if ($action === 'edit'): ?>
                            <input type="hidden" name="id_ap" value="<?php echo $ap_data['id_ap']; ?>">
                        <?php endif; ?>

                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Nama Access Point *</label>
                                <input type="text" class="form-control" name="nama_ap" 
                                       value="<?php echo $ap_data ? htmlspecialchars($ap_data['nama_ap']) : ''; ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Merk</label>
                                <input type="text" class="form-control" name="merk" 
                                       value="<?php echo $ap_data ? htmlspecialchars($ap_data['merk']) : ''; ?>" 
                                       placeholder="Contoh: MikroTik, TP-Link, Ubiquiti">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">IP Address *</label>
                                <input type="text" class="form-control" name="ip_address" 
                                       value="<?php echo $ap_data ? htmlspecialchars($ap_data['ip_address']) : ''; ?>"
                                       inputmode="numeric"
                                       pattern="^(25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d)(\.(25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d)){3}$"
                                       title="Masukkan IP Address IPv4 yang valid, contoh: 192.168.1.10"
                                       placeholder="Contoh: 192.168.1.10"
                                       required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">SNMP Community</label>
                                <input type="text" class="form-control" name="community" 
                                       value="<?php echo $ap_data ? htmlspecialchars($ap_data['community']) : 'public'; ?>"
                                       placeholder="Contoh: public, private">
                            </div>
                        </div>

                        <?php if (empty($gedung_list)): ?>
                            <div class="alert alert-warning mt-3">
                                <i class="bi bi-exclamation-triangle"></i>
                                Belum ada data <strong>Gedung</strong> dan <strong>Lantai</strong>.
                                Buat dulu di menu <a href="<?php echo $base_url; ?>/superadmin/manage_gedung.php">Kelola Gedung</a>
                                dan <a href="<?php echo $base_url; ?>/superadmin/manage_lantai.php">Kelola Lantai</a>.
                            </div>
                        <?php endif; ?>

                        <!-- Gedung & Lantai -->
                        <div class="row g-3 mt-1">
                            <div class="col-md-6">
                                <label class="form-label">Gedung *</label>
                                <select id="id_gedung" class="form-select" <?php echo empty($gedung_list) ? 'disabled' : ''; ?>>
                                    <option value="">-- Pilih Gedung --</option>
                                    <?php foreach ($gedung_list as $g): ?>
                                        <option value="<?php echo (int) $g['id_gedung']; ?>" <?php echo ((int) $g['id_gedung'] === (int) $selected_gedung_id) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($g['nama_gedung']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Lantai *</label>
                                <select id="id_lantai" name="id_lantai" class="form-select" required <?php echo empty($gedung_list) ? 'disabled' : ''; ?>>
                                    <option value="">-- Pilih Lantai --</option>
                                    <?php foreach ($lantai_list as $l): ?>
                                        <option value="<?php echo (int) $l['id_lantai']; ?>" <?php echo ((int) $l['id_lantai'] === (int) $selected_lantai_id) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($l['nama_lantai']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-12">
                                <label class="form-label">Lokasi (Detail)</label>
                                <input type="text" class="form-control" name="lokasi" 
                                       value="<?php echo $ap_data ? htmlspecialchars($ap_data['lokasi']) : ''; ?>"
                                       placeholder="Contoh: Ruang Server / Koridor / Lab 1">
                            </div>
                        </div>

                        <div class="row g-3 mt-1">
                            <div class="col-md-6">
                                <label class="form-label">OID Status</label>
                                <input type="text" class="form-control" name="oid_status" 
                                       value="<?php echo $ap_data ? htmlspecialchars($ap_data['oid_status']) : ''; ?>" 
                                       placeholder="1.3.6.1.2.1.1.3.0">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">OID Traffic In (Download)</label>
                                <input type="text" class="form-control" name="oid_traffic_in" 
                                       value="<?php echo $ap_data ? htmlspecialchars($ap_data['oid_traffic_in']) : ''; ?>" 
                                       placeholder="1.3.6.1.2.1.2.2.1.10.1">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">OID Traffic Out (Upload)</label>
                                <input type="text" class="form-control" name="oid_traffic_out" 
                                       value="<?php echo $ap_data ? htmlspecialchars($ap_data['oid_traffic_out']) : ''; ?>" 
                                       placeholder="1.3.6.1.2.1.2.2.1.16.1">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">OID User Count</label>
                                <input type="text" class="form-control" name="oid_user" 
                                       value="<?php echo $ap_data ? htmlspecialchars($ap_data['oid_user']) : ''; ?>" 
                                       placeholder="1.3.6.1.2.1.25.3.2.1.5.1">
                            </div>
                        </div>
                        
                        <div class="d-flex gap-2 mt-4">
                            <button type="submit" class="btn btn-primary" <?php echo empty($gedung_list) ? 'disabled' : ''; ?>>
                                <i class="bi bi-check-circle"></i> 
                                <?php echo ($action === 'add') ? 'Tambah' : 'Update'; ?>
                            </button>
                            <a href="<?php echo $base_url; ?>/superadmin/manage_access_point.php" class="btn btn-secondary">
                                <i class="bi bi-x-circle"></i> Batal
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
    (function() {
        const baseUrl = <?php echo json_encode($base_url); ?>;
        const selGedung = document.getElementById('id_gedung');
        const selLantai = document.getElementById('id_lantai');
        if (!selGedung || !selLantai) return;

        function setOptions(items, selectedId) {
            selLantai.innerHTML = '<option value="">-- Pilih Lantai --</option>';
            (items || []).forEach(it => {
                const opt = document.createElement('option');
                opt.value = String(it.id_lantai);
                opt.textContent = it.nama_lantai;
                if (selectedId && String(selectedId) === String(it.id_lantai)) {
                    opt.selected = true;
                }
                selLantai.appendChild(opt);
            });
        }

        function loadLantai(selectedId) {
            const id = selGedung.value;
            if (!id) {
                setOptions([], null);
                return;
            }
            fetch(baseUrl + '/api/get_lantai.php?id_gedung=' + encodeURIComponent(id))
                .then(r => r.json())
                .then(data => {
                    if (data && data.status === 'success') {
                        setOptions(data.data, selectedId);
                    }
                })
                .catch(() => {});
        }

        selGedung.addEventListener('change', function() {
            loadLantai(null);
        });
    })();
    </script>

<?php else: ?>
    <!-- List View -->
    <div class="row mb-3">
        <div class="col-md-12">
            <div class="d-flex justify-content-between align-items-center">
                <h3><i class="bi bi-table"></i> Daftar Access Point</h3>
                <a href="<?php echo $base_url; ?>/superadmin/manage_access_point.php?action=add" class="btn btn-success">
                    <i class="bi bi-plus-circle"></i> Tambah AP
                </a>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-md-12">
            <div class="card">
                <div class="card-body">
                    <?php if (empty($ap_list)): ?>
                        <div class="alert alert-info">
                            <i class="bi bi-info-circle"></i> Belum ada Access Point.
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover table-striped">
                                <thead class="table-light">
                                    <tr>
                                        <th style="width: 5%">#</th>
                                        <th style="width: 15%">Nama AP</th>
                                        <th style="width: 10%">Merk</th>
                                        <th style="width: 12%">IP Address</th>
                                        <th style="width: 12%">Gedung / Lantai</th>
                                        <th style="width: 26%">OID Configuration</th>
                                        <th style="width: 20%">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php $no = 1; foreach ($ap_list as $ap): ?>
                                    <tr>
                                        <td><?php echo $no++; ?></td>
                                        <td>
                                            <strong><?php echo htmlspecialchars($ap['nama_ap']); ?></strong>
                                        </td>
                                        <td>
                                            <?php echo htmlspecialchars($ap['merk'] ?? '-'); ?>
                                        </td>
                                        <td>
                                            <code><?php echo htmlspecialchars($ap['ip_address']); ?></code>
                                        </td>
                                        <td>
                                            <div>
                                                <small class="text-muted d-block"><?php echo htmlspecialchars($ap['nama_gedung'] ?? '-'); ?></small>
                                                <small class="text-muted d-block"><?php echo htmlspecialchars($ap['nama_lantai'] ?? '-'); ?></small>
                                                <?php if (!empty($ap['lokasi'])): ?>
                                                    <small class="d-block"><?php echo htmlspecialchars($ap['lokasi']); ?></small>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="d-flex gap-2 flex-wrap">
                                                <!-- OID Status -->
                                                <span title="OID Status">
                                                    <?php if (!empty($ap['oid_status'])): ?>
                                                        <span class="badge bg-success">
                                                            <i class="bi bi-check-circle-fill"></i> Status
                                                        </span>
                                                    <?php else: ?>
                                                        <span class="badge bg-danger">
                                                            <i class="bi bi-x-circle-fill"></i> Status
                                                        </span>
                                                    <?php endif; ?>
                                                </span>
                                                
                                                <!-- OID Traffic In -->
                                                <span title="OID Traffic In (Download)">
                                                    <?php if (!empty($ap['oid_traffic_in'])): ?>
                                                        <span class="badge bg-success">
                                                            <i class="bi bi-check-circle-fill"></i> In
                                                        </span>
                                                    <?php else: ?>
                                                        <span class="badge bg-danger">
                                                            <i class="bi bi-x-circle-fill"></i> In
                                                        </span>
                                                    <?php endif; ?>
                                                </span>
                                                
                                                <!-- OID Traffic Out -->
                                                <span title="OID Traffic Out (Upload)">
                                                    <?php if (!empty($ap['oid_traffic_out'])): ?>
                                                        <span class="badge bg-success">
                                                            <i class="bi bi-check-circle-fill"></i> Out
                                                        </span>
                                                    <?php else: ?>
                                                        <span class="badge bg-danger">
                                                            <i class="bi bi-x-circle-fill"></i> Out
                                                        </span>
                                                    <?php endif; ?>
                                                </span>
                                                
                                                <!-- OID User -->
                                                <span title="OID User Count">
                                                    <?php if (!empty($ap['oid_user'])): ?>
                                                        <span class="badge bg-success">
                                                            <i class="bi bi-check-circle-fill"></i> User
                                                        </span>
                                                    <?php else: ?>
                                                        <span class="badge bg-danger">
                                                            <i class="bi bi-x-circle-fill"></i> User
                                                        </span>
                                                    <?php endif; ?>
                                                </span>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="btn-group btn-group-sm" role="group">
                                                <a href="<?php echo $base_url; ?>/superadmin/manage_access_point.php?action=edit&id=<?php echo $ap['id_ap']; ?>" 
                                                   class="btn btn-warning" title="Edit">
                                                    <i class="bi bi-pencil"></i> Edit
                                                </a>
                                                <a href="<?php echo $base_url; ?>/superadmin/manage_access_point.php?action=delete&id=<?php echo $ap['id_ap']; ?>" 
                                                   class="btn btn-danger" 
                                                   onclick="return confirm('Yakin hapus AP ini?');" title="Hapus">
                                                    <i class="bi bi-trash"></i> Hapus
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

<?php endif; ?>

<?php include dirname(__FILE__) . '/../templates/footer.php'; ?>
