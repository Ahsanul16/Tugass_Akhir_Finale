<?php
/**
 * Admin Dashboard
 */

$page_title = 'Dashboard Admin';

require_once dirname(__FILE__) . '/../config/session.php';
require_once dirname(__FILE__) . '/../config/database.php';
require_once dirname(__FILE__) . '/../config/snmp.php';
require_once dirname(__FILE__) . '/../middleware/auth_check.php';
require_once dirname(__FILE__) . '/../helpers/access_point.php';

// Proteksi halaman - hanya admin
requireRole('admin');

// Get data
$ap_list = getAllAccessPoints($conn);
$online_count = 0;
$offline_count = 0;

// Count status dari database
foreach ($ap_list as $ap) {
    if ($ap['status'] === 'Online') {
        $online_count++;
    } else {
        $offline_count++;
    }
}

// AP selection untuk chart (default: AP pertama)
$selected_ap_id = isset($_GET['id_ap']) ? (int) $_GET['id_ap'] : 0;
$selected_ap = null;

if (!empty($ap_list)) {
    if ($selected_ap_id > 0) {
        foreach ($ap_list as $ap_item) {
            if ((int) $ap_item['id_ap'] === $selected_ap_id) {
                $selected_ap = $ap_item;
                break;
            }
        }
    }
    if (!$selected_ap) {
        $selected_ap = $ap_list[0];
        $selected_ap_id = (int) $selected_ap['id_ap'];
    }
}

$selected_ap_name = $selected_ap ? $selected_ap['nama_ap'] : 'Belum ada AP';

// Dashboard chart sekarang memakai data realtime SNMP, bukan agregasi 24 jam.
$chart_labels = [];
$chart_values_in = [];
$chart_values_out = [];

$additional_js = '<script>
    let dashInFlight = false;
    const dashLabels = [];
    const dashDataIn = [];
    const dashDataOut = [];

    function tickDashboard(apId) {
        if (dashInFlight) return;
        dashInFlight = true;

        fetch("' . getBaseUrl() . '/api/get_ap_realtime_status.php?id_ap=" + apId, { cache: "no-store" })
            .then(r => r.json())
            .then(data => {
                if (!data || data.status !== "success") return;

                const label = new Date().toLocaleTimeString("id-ID", {
                    hour: "2-digit",
                    minute: "2-digit",
                    second: "2-digit",
                    hour12: false
                });

                dashLabels.push(label);
                dashDataIn.push((Number(data.trafik_in || 0) / 1024 / 1024));
                dashDataOut.push((Number(data.trafik_out || 0) / 1024 / 1024));

                while (dashLabels.length > 20) {
                    dashLabels.shift();
                    dashDataIn.shift();
                    dashDataOut.shift();
                }

                if (typeof applyTrafficChartData === "function") {
                    applyTrafficChartData(window.trafficChart, dashLabels, dashDataIn, dashDataOut);
                }
            })
            .catch(() => {})
            .finally(() => { dashInFlight = false; });
    }

    document.addEventListener("DOMContentLoaded", function() {
        const apId = ' . (int) $selected_ap_id . ';
        window.trafficChart = createTrafficChart("trafficChart", ' . json_encode($chart_labels) . ', ' . json_encode($chart_values_in) . ', ' . json_encode($chart_values_out) . ');

        if (apId > 0) {
            tickDashboard(apId);
            setInterval(() => tickDashboard(apId), 5000);
        }
    });
</script>';

include dirname(__FILE__) . '/../templates/header.php';
?>

<!-- Breadcrumb -->
<nav aria-label="breadcrumb">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="<?php echo $base_url; ?>/admin/dashboard.php">Dashboard</a></li>
        <li class="breadcrumb-item active">Home</li>
    </ol>
</nav>

<!-- Page Header -->
<div class="row mb-4">
    <div class="col-md-6">
        <h2 class="fw-bold">
            <i class="bi bi-speedometer2"></i> Dashboard Admin
        </h2>
        <p class="text-muted">Pantau status Access Point dan trafik bandwidth</p>
    </div>
    <div class="col-md-6 text-end">
        <p id="clock" class="text-muted"></p>
        <button class="btn btn-primary btn-refresh" onclick="location.reload();">
            <i class="bi bi-arrow-clockwise"></i> Refresh Data
        </button>
    </div>
</div>

<!-- Role Information Card -->
<div class="row mb-4">
    <div class="col-md-12">
        <div class="card bg-light border-primary">
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col-md-8">
                        <h5 class="card-title fw-bold">
                            <i class="bi bi-shield-check"></i> Profil Admin
                        </h5>
                        <p class="mb-1">
                            <strong>Nama:</strong> <?php echo htmlspecialchars($current_user['nama']); ?>
                        </p>
                        <p class="mb-1">
                            <strong>Username:</strong> <?php echo htmlspecialchars($current_user['username']); ?>
                        </p>
                        <p class="mb-0">
                            <strong>Status:</strong> 
                            <span class="badge bg-primary" style="font-size: 0.95rem;">
                                <i class="bi bi-shield-check"></i> 
                                <?php echo ucfirst($current_user['role']); ?>
                            </span>
                        </p>
                    </div>
                    <div class="col-md-4 text-end">
                        <div style="font-size: 3rem; color: #0d6efd;">
                            <i class="bi bi-shield-check"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Summary Cards -->
<div class="row mb-4">
    <div class="col-md-2-5">
        <div class="stat-card online">
            <div class="stat-icon">
                <i class="bi bi-wifi"></i>
            </div>
            <div class="stat-label">Online</div>
            <div class="stat-value"><?php echo $online_count; ?></div>
        </div>
    </div>
    
    <div class="col-md-2-5">
        <div class="stat-card offline">
            <div class="stat-icon">
                <i class="bi bi-wifi-off"></i>
            </div>
            <div class="stat-label">Offline</div>
            <div class="stat-value"><?php echo $offline_count; ?></div>
        </div>
    </div>
    
    <div class="col-md-2-5">
        <div class="stat-card">
            <div class="stat-icon">
                <i class="bi bi-router"></i>
            </div>
            <div class="stat-label">Total AP</div>
            <div class="stat-value"><?php echo count($ap_list); ?></div>
        </div>
    </div>
    
    <div class="col-md-2-5">
        <div class="stat-card">
            <div class="stat-icon">
                <i class="bi bi-shield-check"></i>
            </div>
            <div class="stat-label">Uptime</div>
            <div class="stat-value" style="font-size: 24px;">
                <?php 
                $total = $online_count + $offline_count;
                $uptime = $total > 0 ? round(($online_count / $total) * 100, 1) : 0;
                echo $uptime . '%';
                ?>
            </div>
        </div>
    </div>
</div>

<!-- Add CSS for 4 column layout -->
<style>
@media (min-width: 768px) {
    .col-md-2-5 {
        flex: 0 0 25%;
        max-width: 25%;
    }
}
@media (max-width: 768px) {
    .col-md-2-5 {
        flex: 0 0 50%;
        max-width: 50%;
    }
}
</style>

<!-- Bandwidth Chart -->
<div class="row mb-4">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header">
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
                    <span>
                        <i class="bi bi-graph-up"></i> Trafik Bandwidth AP:
                        <strong><?php echo htmlspecialchars($selected_ap_name); ?></strong> (Realtime)
                    </span>
                    <form method="GET" class="d-flex align-items-center gap-2 m-0">
                        <label for="id_ap" class="small text-white-50 mb-0">Pilih AP</label>
                        <select id="id_ap" name="id_ap" class="form-select form-select-sm" onchange="this.form.submit()">
                            <?php foreach ($ap_list as $ap_option): ?>
                                <option value="<?php echo (int) $ap_option['id_ap']; ?>" <?php echo ((int) $ap_option['id_ap'] === (int) $selected_ap_id) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($ap_option['nama_ap']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </form>
                </div>
            </div>
            <div class="card-body">
                <div class="chart-container">
                    <canvas id="trafficChart"></canvas>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include dirname(__FILE__) . '/../templates/footer.php'; ?>
