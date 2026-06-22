<?php
/**
 * Admin - Lihat Detail Access Point
 * Halaman untuk melihat list dan detail Access Point
 */

$page_title = 'Lihat Detail AP';

require_once dirname(__FILE__) . '/../config/session.php';
require_once dirname(__FILE__) . '/../config/database.php';
require_once dirname(__FILE__) . '/../config/snmp.php';
require_once dirname(__FILE__) . '/../middleware/auth_check.php';
require_once dirname(__FILE__) . '/../helpers/access_point.php';
require_once dirname(__FILE__) . '/../helpers/location.php';

// Proteksi halaman - admin dan superadmin bisa melihat detail AP
requireRole(['admin', 'superadmin']);

// Check apakah menampilkan list atau detail
$view = isset($_GET['view']) ? (int)$_GET['view'] : 0;
$ap_list = getAllAccessPoints($conn);

// Filters (list view): gedung + lantai
$filter_gedung_id = isset($_GET['id_gedung']) ? (int) $_GET['id_gedung'] : 0;
$filter_lantai_id = isset($_GET['id_lantai']) ? (int) $_GET['id_lantai'] : 0;
$gedung_list = getAllGedung($conn);
$lantai_filter_list = ($filter_gedung_id > 0) ? getLantaiByGedung($conn, $filter_gedung_id) : [];

// Jika ada parameter view, tampilkan detail AP
if ($view > 0) {
    $ap = getAccessPointById($conn, $view);
    if (!$ap) {
        header('Location: ' . getBaseUrl() . '/admin/access_point.php?error=not_found');
        exit();
    }

    // Filter + paging untuk tabel monitoring (100 data per halaman)
    $mon_date = isset($_GET['mon_date']) ? trim((string) $_GET['mon_date']) : '';
    if ($mon_date !== '' && !preg_match('/^\\d{4}-\\d{2}-\\d{2}$/', $mon_date)) {
        $mon_date = '';
    }
    $mon_status = isset($_GET['mon_status']) ? trim((string) $_GET['mon_status']) : '';
    if (!in_array($mon_status, ['', 'Online', 'Offline'], true)) {
        $mon_status = '';
    }

    $page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
    if ($page < 1) $page = 1;
    $per_page = 100;
    $total_monitoring_rows = getMonitoringCount($conn, $view, $mon_date ?: null, $mon_status ?: null);
    $total_pages = (int) ceil(max(1, $total_monitoring_rows) / $per_page);
    if ($page > $total_pages) $page = $total_pages;
    $offset = ($page - 1) * $per_page;

    $monitoring_data = getMonitoringDataPaged($conn, $view, $per_page, $offset, $mon_date ?: null, $mon_status ?: null);
    $latest_row = getLatestMonitoringRow($conn, $view);
    $stats = getMonitoringStats($conn, $view);
    $has_user_oid = !empty(trim((string)($ap['oid_user'] ?? '')));
    // "User Saat Ini" selalu dari snapshot terbaru (bukan dari halaman tabel)
    $latest_user_count = ($latest_row && isset($latest_row['jumlah_user']) && is_numeric($latest_row['jumlah_user']))
        ? (int) $latest_row['jumlah_user']
        : 0;
    $display_user_count = $has_user_oid ? $latest_user_count : 0;

    // Chart dari DB (AJAX). SNMP hanya saat klik tombol refresh.
    $additional_js = '<script>
        let detailChart = null;
        let detailInFlight = false;

        function loadChart() {
            return fetch("' . getBaseUrl() . '/api/get_ap_chart.php?id_ap=' . $view . '")
                .then(response => response.json())
                .then(data => {
                    if (data.status === "success") {
                        if (!detailChart) {
                            detailChart = createTrafficChart("detailChart", data.labels, data.data_in, data.data_out);
                        } else {
                            if (typeof applyTrafficChartData === "function") {
                                applyTrafficChartData(detailChart, data.labels, data.data_in, data.data_out);
                            } else {
                                detailChart.data.labels = data.labels;
                                detailChart.data.datasets[0].data = data.data_in;
                                detailChart.data.datasets[1].data = data.data_out;
                                detailChart.update();
                            }
                        }
                    }
                })
                .catch(err => console.log("Chart load error:", err));
        }

        const currentMonitoringPage = ' . (int) $page . ';
        const perPage = ' . (int) $per_page . ';
        const monDate = ' . json_encode($mon_date) . ';
        const monStatus = ' . json_encode($mon_status) . ';

        function refreshMonitoringTable() {
            if (currentMonitoringPage !== 1) return Promise.resolve();

            let url = "' . getBaseUrl() . '/api/get_ap_monitoring_rows.php?id_ap=' . $view . '&limit=" + perPage;
            if (monDate) url += "&mon_date=" + encodeURIComponent(monDate);
            if (monStatus) url += "&mon_status=" + encodeURIComponent(monStatus);

            return fetch(url)
                .then(r => r.json())
                .then(data => {
                    if (data && data.status === "success") {
                        const tbody = document.getElementById("monitoring-tbody");
                        if (tbody) tbody.innerHTML = data.html;
                    }
                })
                .catch(() => {});
        }

        function refreshStatsCards() {
            return fetch("' . getBaseUrl() . '/api/get_ap_stats.php?id_ap=' . $view . '")
                .then(r => r.json())
                .then(data => {
                    if (!data || data.status !== "success") return;

                    const set = (id, val) => {
                        const el = document.getElementById(id);
                        if (el) el.textContent = val;
                    };

                    set("stat-avg-in", data.avg_in);
                    set("stat-max-in", data.max_in);
                    set("stat-min-in", data.min_in);
                    set("stat-avg-out", data.avg_out);
                    set("stat-max-out", data.max_out);
                    set("stat-min-out", data.min_out);
                })
                .catch(() => {});
        }

        function refreshSNMP(force, silent) {
            if (detailInFlight) return;
            detailInFlight = true;

            const btn = document.getElementById("btn-refresh-snmp");
            if (!silent && btn) {
                btn.disabled = true;
                btn.innerHTML = "<i class=\"bi bi-arrow-repeat\"></i> Refreshing...";
            }

            // log=1 agar snapshot masuk ke tabel monitoring walau SNMP sedang di-throttle.
            const url = "' . getBaseUrl() . '/api/refresh_ap_status.php?id_ap=' . $view . '&log=1" + (force ? "&force=1" : "");
            return fetch(url)
                .then(r => r.json())
                .then(data => {
                    if (data && data.status === "success") {
                        const badge = document.getElementById("ap-status-badge");
                        const icon = document.getElementById("ap-status-icon");
                        const text = document.getElementById("ap-status-text");

                        if (badge && data.badge_class) {
                            badge.classList.remove("bg-success", "bg-danger");
                            badge.classList.add(data.badge_class);
                        }
                        if (icon && data.status_icon) {
                            icon.className = "bi " + data.status_icon;
                        }
                        if (text && data.ap_status) {
                            text.textContent = data.ap_status;
                        }

                        const users = document.getElementById("ap-user-count");
                        if (users && typeof data.jumlah_user !== "undefined") {
                            users.textContent = data.jumlah_user;
                        }

                        // Reload chart data from DB (new snapshot inserted by refresh endpoint)
                        return Promise.all([loadChart(), refreshMonitoringTable(), refreshStatsCards()]);
                    }
                })
                .catch(() => {})
                .finally(() => {
                    if (!silent && btn) {
                        btn.disabled = false;
                        btn.innerHTML = "<i class=\"bi bi-arrow-repeat\"></i> Refresh SNMP";
                    }
                    detailInFlight = false;
                });
        }

        document.addEventListener("DOMContentLoaded", function() {
            loadChart();
            refreshMonitoringTable();
            refreshStatsCards();

            // Realtime ringan: update otomatis tiap 5 detik (SNMP di-throttle di server).
            setInterval(() => refreshSNMP(false, true), 5000);
        });
    </script>';

    $is_detail = true;
} else {
    $is_detail = false;
}

include dirname(__FILE__) . '/../templates/header.php';
?>

<?php if ($is_detail): ?>
    <!-- Detail View -->
    <!-- Breadcrumb -->
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="<?php echo $base_url; ?>/admin/dashboard.php">Dashboard</a></li>
            <li class="breadcrumb-item"><a href="<?php echo $base_url; ?>/admin/access_point.php">Lihat Detail AP</a></li>
            <li class="breadcrumb-item active"><?php echo htmlspecialchars($ap['nama_ap']); ?></li>
        </ol>
    </nav>

    <!-- Page Header -->
    <div class="row mb-4">
        <div class="col-md-8">
            <h2 class="fw-bold">
                <i class="bi bi-router"></i> <?php echo htmlspecialchars($ap['nama_ap']); ?>
            </h2>
            <div class="text-muted small">
                <i class="bi bi-building"></i> <?php echo htmlspecialchars($ap['nama_gedung'] ?? '-'); ?>
                <span class="mx-2">|</span>
                <i class="bi bi-layers"></i> <?php echo htmlspecialchars($ap['nama_lantai'] ?? '-'); ?>
            </div>
        </div>
        <div class="col-md-4 text-end">
            <button id="btn-refresh-snmp" class="btn btn-primary btn-sm" onclick="refreshSNMP(true, false);">
                <i class="bi bi-arrow-repeat"></i> Refresh SNMP
            </button>
            <a href="<?php echo $base_url; ?>/admin/access_point.php" class="btn btn-secondary btn-sm">
                <i class="bi bi-arrow-left"></i> Kembali ke List
            </a>
        </div>
    </div>

    <!-- Info Cards -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card">
                <div class="card-body">
                    <label class="text-muted small">Status</label>
                    <div class="mt-2">
                        <?php 
                        // Load status dari database dulu (instant), real-time akan di-load via AJAX
                        $status = $ap['status'] ?? 'Offline';
                        $badge_class = ($status === 'Online') ? 'bg-success' : 'bg-danger';
                        $status_icon = ($status === 'Online') ? 'bi-wifi' : 'bi-wifi-off';
                        ?>
                        <span id="ap-status-badge" class="badge <?php echo $badge_class; ?> fw-bold" style="font-size: 1rem; padding: 0.65rem 0.85rem;">
                            <i id="ap-status-icon" class="bi <?php echo $status_icon; ?>"></i>
                            <span id="ap-status-text"><?php echo $status; ?></span>
                            <span id="ap-status-loading" style="display:none;">
                                <span class="spinner-border spinner-border-sm ms-2"></span>
                            </span>
                        </span>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-3">
            <div class="card">
                <div class="card-body">
                    <label class="text-muted small">IP Address</label>
                    <div class="mt-2">
                        <code><?php echo htmlspecialchars($ap['ip_address']); ?></code>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-3">
            <div class="card">
                <div class="card-body">
                    <label class="text-muted small">Merek</label>
                    <div class="mt-2">
                        <strong><?php echo htmlspecialchars($ap['merk'] ?? 'N/A'); ?></strong>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-3">
            <div class="card">
                <div class="card-body">
                    <label class="text-muted small">Lokasi</label>
                    <div class="mt-2">
                        <strong><?php echo htmlspecialchars($ap['lokasi'] ?? 'N/A'); ?></strong>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Traffic Chart -->
    <div class="row mb-4">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header">
                    <i class="bi bi-graph-up"></i> Grafik Trafik Bandwidth (Download/Upload)
                </div>
                <div class="card-body">
                    <div class="chart-container" style="height: 400px;">
                        <canvas id="detailChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Statistics -->
    <div class="row mb-4">
        <div class="col-md-4">
            <div class="card">
                <div class="card-header">
                    <i class="bi bi-arrow-down"></i> Download (Traffic In)
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="text-muted small">Rata-rata</label>
                        <p class="text-primary fw-bold" style="font-size: 18px;">
                            <span id="stat-avg-in"><?php echo formatBandwidth($stats['avg_traffic_in'] ?? 0); ?></span>
                        </p>
                    </div>
                    <div class="mb-3">
                        <label class="text-muted small">Maksimal</label>
                        <p class="text-success fw-bold" style="font-size: 18px;">
                            <span id="stat-max-in"><?php echo formatBandwidth($stats['max_traffic_in'] ?? 0); ?></span>
                        </p>
                    </div>
                    <div>
                        <label class="text-muted small">Minimal</label>
                        <p class="text-warning fw-bold" style="font-size: 18px;">
                            <span id="stat-min-in"><?php echo formatBandwidth($stats['min_traffic_in'] ?? 0); ?></span>
                        </p>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-4">
            <div class="card">
                <div class="card-header">
                    <i class="bi bi-arrow-up"></i> Upload (Traffic Out)
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="text-muted small">Rata-rata</label>
                        <p class="text-primary fw-bold" style="font-size: 18px;">
                            <span id="stat-avg-out"><?php echo formatBandwidth($stats['avg_traffic_out'] ?? 0); ?></span>
                        </p>
                    </div>
                    <div class="mb-3">
                        <label class="text-muted small">Maksimal</label>
                        <p class="text-success fw-bold" style="font-size: 18px;">
                            <span id="stat-max-out"><?php echo formatBandwidth($stats['max_traffic_out'] ?? 0); ?></span>
                        </p>
                    </div>
                    <div>
                        <label class="text-muted small">Minimal</label>
                        <p class="text-warning fw-bold" style="font-size: 18px;">
                            <span id="stat-min-out"><?php echo formatBandwidth($stats['min_traffic_out'] ?? 0); ?></span>
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card">
                <div class="card-header">
                    <i class="bi bi-people"></i> Pengguna & Status
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="text-muted small">User Saat Ini</label>
                        <p class="text-primary fw-bold" style="font-size: 18px;">
                            <span id="ap-user-count"><?php echo $display_user_count; ?></span> user
                        </p>
                    </div>
                    <div class="row">
                        <div class="col-6">
                            <label class="text-muted small">Online</label>
                            <p class="text-success fw-bold"><?php echo $stats['online_count'] ?? 0; ?></p>
                        </div>
                        <div class="col-6">
                            <label class="text-muted small">Offline</label>
                            <p class="text-danger fw-bold"><?php echo $stats['offline_count'] ?? 0; ?></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Monitoring Data Table -->
    <div class="row">
        <div class="col-md-12">
                <div class="card">
                    <div class="card-header">
                        <i class="bi bi-table"></i> Data Monitoring Terbaru
                    </div>
                    <div class="card-body">
                        <form method="GET" class="row g-2 align-items-end mb-3">
                            <input type="hidden" name="view" value="<?php echo (int) $view; ?>">
                            <input type="hidden" name="page" value="1">

                            <div class="col-md-3">
                                <label class="form-label mb-1">Filter Hari</label>
                                <input id="filter-mon-date" type="date" name="mon_date" class="form-control" value="<?php echo htmlspecialchars($mon_date); ?>">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label mb-1">Filter Status</label>
                                <select id="filter-mon-status" name="mon_status" class="form-select">
                                    <option value="" <?php echo ($mon_status === '') ? 'selected' : ''; ?>>Semua</option>
                                    <option value="Online" <?php echo ($mon_status === 'Online') ? 'selected' : ''; ?>>Online</option>
                                    <option value="Offline" <?php echo ($mon_status === 'Offline') ? 'selected' : ''; ?>>Offline</option>
                                </select>
                            </div>
                            <div class="col-md-6 text-end">
                                <button class="btn btn-outline-primary btn-sm" type="submit">
                                    <i class="bi bi-funnel"></i> Terapkan
                                </button>
                                <a class="btn btn-outline-secondary btn-sm" href="<?php echo $base_url; ?>/admin/access_point.php?view=<?php echo (int) $view; ?>">
                                    <i class="bi bi-x-circle"></i> Reset
                                </a>
                            </div>
                        </form>

                        <div class="table-responsive">
                            <table class="table table-sm table-hover">
                                <thead>
                                <tr>
                                    <th>Waktu</th>
                                    <th>Status</th>
                                    <th>Download (In)</th>
                                    <th>Upload (Out)</th>
                                    <th>Pengguna</th>
                                </tr>
                            </thead>
                            <tbody id="monitoring-tbody">
                                <?php if (count($monitoring_data) > 0): ?>
                                    <?php foreach ($monitoring_data as $data): ?>
                                        <tr>
                                            <td>
                                                <small><?php echo date('d M H:i:s', strtotime($data['waktu_monitoring'])); ?></small>
                                            </td>
                                            <td>
                                                <?php 
                                                $badge_class = ($data['status_ap'] === 'Online') ? 'bg-success' : 'bg-danger';
                                                ?>
                                                <span class="badge <?php echo $badge_class; ?>">
                                                    <?php echo $data['status_ap']; ?>
                                                </span>
                                            </td>
                                            <td>
                                                <strong><?php echo formatBandwidth($data['trafik_in']); ?></strong>
                                            </td>
                                            <td>
                                                <strong><?php echo formatBandwidth($data['trafik_out']); ?></strong>
                                            </td>
                                            <td>
                                                <?php echo $data['jumlah_user']; ?> user
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="5" class="text-center py-3">
                                            <i class="bi bi-inbox"></i> Belum ada data monitoring
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <?php if (!empty($total_monitoring_rows) && $total_monitoring_rows > $per_page): ?>
                        <div class="d-flex justify-content-between align-items-center mt-2">
                            <small class="text-muted">
                                Menampilkan <?php echo count($monitoring_data); ?> dari <?php echo (int) $total_monitoring_rows; ?> data
                            </small>
                            <nav aria-label="Pagination monitoring">
                                <ul class="pagination pagination-sm mb-0">
                                    <?php
                                    $prev_page = $page - 1;
                                    $next_page = $page + 1;
                                    $base_link = $base_url . '/admin/access_point.php?view=' . (int) $view;
                                    if (!empty($mon_date)) {
                                        $base_link .= '&mon_date=' . urlencode($mon_date);
                                    }
                                    if (!empty($mon_status)) {
                                        $base_link .= '&mon_status=' . urlencode($mon_status);
                                    }
                                    $base_link .= '&page=';
                                    ?>
                                    <li class="page-item <?php echo ($page <= 1) ? 'disabled' : ''; ?>">
                                        <a class="page-link" href="<?php echo ($page <= 1) ? '#' : htmlspecialchars($base_link . $prev_page); ?>" aria-label="Previous">
                                            <i class="bi bi-chevron-left"></i>
                                        </a>
                                    </li>
                                    <li class="page-item disabled">
                                        <span class="page-link">
                                            <?php echo (int) $page; ?> / <?php echo (int) $total_pages; ?>
                                        </span>
                                    </li>
                                    <li class="page-item <?php echo ($page >= $total_pages) ? 'disabled' : ''; ?>">
                                        <a class="page-link" href="<?php echo ($page >= $total_pages) ? '#' : htmlspecialchars($base_link . $next_page); ?>" aria-label="Next">
                                            <i class="bi bi-chevron-right"></i>
                                        </a>
                                    </li>
                                </ul>
                            </nav>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

<?php else: ?>
    <!-- List View -->
    <!-- Breadcrumb -->
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="<?php echo $base_url; ?>/admin/dashboard.php">Dashboard</a></li>
            <li class="breadcrumb-item active">Lihat Detail AP</li>
        </ol>
    </nav>

    <!-- Page Header -->
    <div class="row mb-4">
        <div class="col-md-8">
            <h2 class="fw-bold">
                <i class="bi bi-graph-up"></i> Lihat Detail Access Point
            </h2>
            <p class="text-muted">Pilih Access Point untuk melihat detail monitoring dan statistik</p>
        </div>
        <div class="col-md-4 text-end">
            <button class="btn btn-primary btn-sm" onclick="location.reload();">
                <i class="bi bi-arrow-clockwise"></i> Refresh
            </button>
        </div>
    </div>

    <!-- Filters -->
    <div class="row mb-3">
        <div class="col-md-12">
            <div class="card">
                <div class="card-body">
                    <form method="GET" class="row g-2 align-items-end">
                        <div class="col-md-4">
                            <label class="form-label mb-1">Filter Gedung</label>
                            <select id="filter-gedung" name="id_gedung" class="form-select">
                                <option value="0">Semua Gedung</option>
                                <?php foreach ($gedung_list as $g): ?>
                                    <option value="<?php echo (int) $g['id_gedung']; ?>" <?php echo ((int) $g['id_gedung'] === (int) $filter_gedung_id) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($g['nama_gedung']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label mb-1">Filter Lantai</label>
                            <select id="filter-lantai" name="id_lantai" class="form-select" <?php echo ($filter_gedung_id <= 0) ? 'disabled' : ''; ?>>
                                <option value="0">Semua Lantai</option>
                                <?php foreach ($lantai_filter_list as $l): ?>
                                    <option value="<?php echo (int) $l['id_lantai']; ?>" <?php echo ((int) $l['id_lantai'] === (int) $filter_lantai_id) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($l['nama_lantai']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4 d-flex gap-2">
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-funnel"></i> Terapkan
                            </button>
                            <a href="<?php echo $base_url; ?>/admin/access_point.php" class="btn btn-secondary">
                                <i class="bi bi-x-circle"></i> Reset
                            </a>
                        </div>
                    </form>

                    <script>
                    (function() {
                        const baseUrl = <?php echo json_encode($base_url); ?>;
                        const selGedung = document.getElementById('filter-gedung');
                        const selLantai = document.getElementById('filter-lantai');
                        if (!selGedung || !selLantai) return;

                        function setOptions(items) {
                            selLantai.innerHTML = '<option value="0">Semua Lantai</option>';
                            (items || []).forEach(it => {
                                const opt = document.createElement('option');
                                opt.value = String(it.id_lantai);
                                opt.textContent = it.nama_lantai;
                                selLantai.appendChild(opt);
                            });
                        }

                        selGedung.addEventListener('change', function() {
                            const gid = selGedung.value;
                            selLantai.disabled = !(gid && gid !== '0');
                            if (selLantai.disabled) {
                                setOptions([]);
                                return;
                            }
                            fetch(baseUrl + '/api/get_lantai.php?id_gedung=' + encodeURIComponent(gid))
                                .then(r => r.json())
                                .then(data => {
                                    if (data && data.status === 'success') {
                                        setOptions(data.data);
                                    }
                                })
                                .catch(() => {});
                        });
                    })();
                    </script>
                </div>
            </div>
        </div>
    </div>

    <!-- Access Points List -->
    <div class="row">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header">
                    <i class="bi bi-router"></i> Daftar Access Point
                </div>
                <div class="card-body">
                    <?php
                    // Apply filters in PHP (list kecil, cukup cepat).
                    $filtered_list = $ap_list;
                    if ($filter_gedung_id > 0) {
                        $filtered_list = array_values(array_filter($filtered_list, function($ap) use ($filter_gedung_id) {
                            return isset($ap['id_gedung']) && (int) $ap['id_gedung'] === (int) $filter_gedung_id;
                        }));
                    }
                    if ($filter_lantai_id > 0) {
                        $filtered_list = array_values(array_filter($filtered_list, function($ap) use ($filter_lantai_id) {
                            return isset($ap['id_lantai']) && (int) $ap['id_lantai'] === (int) $filter_lantai_id;
                        }));
                    }
                    ?>

                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Gedung / Lantai / AP</th>
                                    <th>IP Address</th>
                                    <th>Merek</th>
                                    <th>Lokasi</th>
                                    <th>Status</th>
                                    <th>Community</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (count($filtered_list) > 0): ?>
                                    <?php foreach ($filtered_list as $ap): ?>
                                        <tr>
                                            <td>
                                                <small class="text-muted d-block"><?php echo htmlspecialchars($ap['nama_gedung'] ?? '-'); ?></small>
                                                <small class="text-muted d-block"><?php echo htmlspecialchars($ap['nama_lantai'] ?? '-'); ?></small>
                                                <strong><?php echo htmlspecialchars($ap['nama_ap']); ?></strong>
                                                <div class="ap-live-panel mt-2" data-ap-live="<?php echo (int) $ap['id_ap']; ?>" style="display: grid; grid-template-columns: 430px 190px; gap: 16px; align-items: start; width: 636px; max-width: none;">
                                                    <div class="ap-live-chart-wrap" style="width: 430px; min-width: 430px;">
                                                        <div class="ap-mini-chart" style="height: 130px;">
                                                            <canvas id="ap-live-chart-<?php echo (int) $ap['id_ap']; ?>"></canvas>
                                                        </div>
                                                    </div>
                                                    <div class="ap-live-info" style="width: 190px; min-width: 190px; align-content: start;">
                                                        <div class="ap-live-top">
                                                            <span class="ap-live-status">Memuat</span>
                                                            <span class="ap-live-users"><i class="bi bi-people"></i> 0 user</span>
                                                        </div>
                                                        <div class="ap-live-metrics">
                                                            <div class="ap-live-stat">
                                                                <div class="ap-live-stat-head">
                                                                    <span>Down</span>
                                                                    <strong class="ap-mini-value" data-metric="down-label">0 B</strong>
                                                                </div>
                                                                <span class="ap-mini-bar down"><span class="ap-mini-fill down"></span></span>
                                                            </div>
                                                            <div class="ap-live-stat">
                                                                <div class="ap-live-stat-head">
                                                                    <span>Up</span>
                                                                    <strong class="ap-mini-value" data-metric="up-label">0 B</strong>
                                                                </div>
                                                                <span class="ap-mini-bar up"><span class="ap-mini-fill up"></span></span>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <code><?php echo htmlspecialchars($ap['ip_address']); ?></code>
                                            </td>
                                            <td>
                                                <?php echo htmlspecialchars($ap['merk'] ?? '-'); ?>
                                            </td>
                                            <td>
                                                <small><?php echo htmlspecialchars($ap['lokasi'] ?? '-'); ?></small>
                                            </td>
                                            <td>
                                                <?php 
                                                $badge_class = ($ap['status'] === 'Online') ? 'bg-success' : 'bg-danger';
                                                $status_icon = ($ap['status'] === 'Online') ? 'bi-wifi' : 'bi-wifi-off';
                                                ?>
                                                <span id="list-status-badge-<?php echo (int) $ap['id_ap']; ?>" class="badge <?php echo $badge_class; ?> fw-bold" style="font-size: 0.95rem; padding: 0.55rem 0.8rem;">
                                                    <i id="list-status-icon-<?php echo (int) $ap['id_ap']; ?>" class="bi <?php echo $status_icon; ?>"></i>
                                                    <span id="list-status-text-<?php echo (int) $ap['id_ap']; ?>"><?php echo $ap['status']; ?></span>
                                                </span>
                                            </td>
                                            <td>
                                                <small><?php echo htmlspecialchars($ap['community']); ?></small>
                                            </td>
                                            <td>
                                                <a href="<?php echo $base_url; ?>/admin/access_point.php?view=<?php echo (int) $ap['id_ap']; ?>" 
                                                   class="btn btn-sm btn-info js-detail-view"
                                                   data-ap-id="<?php echo (int) $ap['id_ap']; ?>"
                                                   title="Lihat Detail">
                                                    <i class="bi bi-eye"></i> Lihat
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="7" class="text-center py-4">
                                            <i class="bi bi-inbox" style="font-size: 24px;"></i>
                                            <p class="mt-2">Belum ada data Access Point</p>
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

    <script>
    (function() {
        const baseUrl = <?php echo json_encode($base_url); ?>;
        const panels = Array.from(document.querySelectorAll('[data-ap-live]'));
        const liveCharts = {};
        let refreshRunning = false;

        function formatWidth(value, maxValue) {
            if (!maxValue || maxValue <= 0) return '4%';
            return Math.max(4, Math.min(100, (value / maxValue) * 100)) + '%';
        }

        function setPanelLoading(panel, text) {
            const status = panel.querySelector('.ap-live-status');
            if (status) {
                status.classList.remove('online', 'offline');
                status.textContent = text || 'Memuat';
            }
        }

        function formatRealtimeBytes(bytes) {
            if (typeof formatBytes === 'function') return formatBytes(bytes || 0);
            const value = Number(bytes || 0);
            if (value >= 1073741824) return (value / 1073741824).toFixed(2) + ' GB';
            if (value >= 1048576) return (value / 1048576).toFixed(2) + ' MB';
            if (value >= 1024) return (value / 1024).toFixed(2) + ' KB';
            return value.toFixed(0) + ' B';
        }

        function getLiveChart(panel) {
            if (typeof Chart === 'undefined') return null;

            const id = panel.getAttribute('data-ap-live');
            if (liveCharts[id]) return liveCharts[id];

            const canvas = document.getElementById('ap-live-chart-' + id);
            if (!canvas) return null;

            liveCharts[id] = new Chart(canvas, {
                type: 'line',
                data: {
                    labels: [],
                    datasets: [
                        {
                            label: 'Download (In)',
                            data: [],
                            borderColor: '#667eea',
                            backgroundColor: 'rgba(102, 126, 234, 0.12)',
                            borderWidth: 2,
                            tension: 0.4,
                            pointBackgroundColor: '#667eea',
                            pointBorderColor: '#fff',
                            pointBorderWidth: 1,
                            pointRadius: 2.5,
                            fill: true
                        },
                        {
                            label: 'Upload (Out)',
                            data: [],
                            borderColor: '#f5803e',
                            backgroundColor: 'rgba(245, 128, 62, 0.12)',
                            borderWidth: 2,
                            tension: 0.4,
                            pointBackgroundColor: '#f5803e',
                            pointBorderColor: '#fff',
                            pointBorderWidth: 1,
                            pointRadius: 2.5,
                            fill: true
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    animation: false,
                    plugins: {
                        legend: {
                            display: true,
                            labels: {
                                usePointStyle: true,
                                boxWidth: 7,
                                padding: 8,
                                font: {
                                    size: 10
                                }
                            }
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    return context.dataset.label + ': ' + formatRealtimeBytes(context.parsed.y || 0);
                                }
                            }
                        }
                    },
                    scales: {
                        x: {
                            display: false
                        },
                        y: {
                            display: true,
                            beginAtZero: true,
                            grid: {
                                color: 'rgba(0, 0, 0, 0.06)'
                            },
                            ticks: {
                                maxTicksLimit: 3,
                                font: {
                                    size: 9
                                },
                                callback: function(value) {
                                    return formatRealtimeBytes(value);
                                }
                            }
                        }
                    },
                    elements: {
                        line: { capBezierPoints: true }
                    }
                }
            });

            return liveCharts[id];
        }

        function pushChartPoint(panel, data, down, up) {
            const chart = getLiveChart(panel);
            if (!chart) return;

            const label = new Date().toLocaleTimeString('id-ID', {
                hour: '2-digit',
                minute: '2-digit',
                second: '2-digit',
                hour12: false
            });
            chart.data.labels.push(label);
            chart.data.datasets[0].data.push(down);
            chart.data.datasets[1].data.push(up);

            while (chart.data.labels.length > 10) {
                chart.data.labels.shift();
                chart.data.datasets[0].data.shift();
                chart.data.datasets[1].data.shift();
            }

            chart.update('none');
        }

        function updatePanel(panel, data) {
            const down = Number(data.trafik_in || 0);
            const up = Number(data.trafik_out || 0);
            const max = Math.max(down, up, 1);
            const isOnline = data.ap_status === 'Online';
            const id = panel.getAttribute('data-ap-live');

            const status = panel.querySelector('.ap-live-status');
            const users = panel.querySelector('.ap-live-users');
            const downFill = panel.querySelector('.ap-mini-fill.down');
            const upFill = panel.querySelector('.ap-mini-fill.up');
            const downLabel = panel.querySelector('[data-metric="down-label"]');
            const upLabel = panel.querySelector('[data-metric="up-label"]');

            if (status) {
                status.classList.toggle('online', isOnline);
                status.classList.toggle('offline', !isOnline);
                status.textContent = isOnline ? 'Online' : 'Offline';
            }
            if (users) users.innerHTML = '<i class="bi bi-people"></i> ' + (data.jumlah_user || 0) + ' user';
            if (downFill) downFill.style.width = formatWidth(down, max);
            if (upFill) upFill.style.width = formatWidth(up, max);
            if (downLabel) downLabel.textContent = data.traffic_in_label || '0 B';
            if (upLabel) upLabel.textContent = data.traffic_out_label || '0 B';

            const listBadge = document.getElementById('list-status-badge-' + id);
            const listIcon = document.getElementById('list-status-icon-' + id);
            const listText = document.getElementById('list-status-text-' + id);
            if (listBadge) {
                listBadge.classList.remove('bg-success', 'bg-danger');
                listBadge.classList.add(isOnline ? 'bg-success' : 'bg-danger');
            }
            if (listIcon) listIcon.className = 'bi ' + (isOnline ? 'bi-wifi' : 'bi-wifi-off');
            if (listText) listText.textContent = isOnline ? 'Online' : 'Offline';

            pushChartPoint(panel, data, down, up);
        }

        function fetchPanel(panel) {
            const id = panel.getAttribute('data-ap-live');
            return fetch(baseUrl + '/api/get_ap_realtime_status.php?id_ap=' + encodeURIComponent(id), { cache: 'no-store' })
                .then(r => r.json())
                .then(data => {
                    if (data && data.status === 'success') {
                        updatePanel(panel, data);
                    } else {
                        setPanelLoading(panel, 'Error');
                    }
                })
                .catch(() => setPanelLoading(panel, 'Error'));
        }

        function refreshAllRealtimeRows() {
            if (!panels.length || refreshRunning || document.hidden) return Promise.resolve();
            refreshRunning = true;
            let index = 0;
            const workers = Math.min(3, panels.length);
            const runNext = () => {
                if (index >= panels.length) return Promise.resolve();
                const panel = panels[index++];
                return fetchPanel(panel).then(runNext);
            };

            const jobs = [];
            for (let i = 0; i < workers; i++) jobs.push(runNext());
            return Promise.all(jobs).finally(() => {
                refreshRunning = false;
            });
        }

        document.querySelectorAll('.js-detail-view').forEach(link => {
            link.addEventListener('click', function(e) {
                e.preventDefault();
                const href = this.href;
                const id = this.getAttribute('data-ap-id');

                this.classList.add('disabled');
                this.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Lihat';

                fetch(baseUrl + '/api/refresh_ap_status.php?id_ap=' + encodeURIComponent(id) + '&force=1&log=1', { cache: 'no-store' })
                    .finally(() => {
                        window.location.href = href;
                    });
            });
        });

        refreshAllRealtimeRows();
        setInterval(refreshAllRealtimeRows, 15000);
    })();
    </script>

<?php endif; ?>

<?php include dirname(__FILE__) . '/../templates/footer.php'; ?>

