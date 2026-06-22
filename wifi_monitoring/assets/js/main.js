/* ========================================
   Main JavaScript Functions
   ======================================== */

// Format bytes ke mb,gb, dll
function formatBytes(bytes) {
    if (bytes === 0) return '0 B';
    const k = 1024;
    const sizes = ['B', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
}

// Format number with thousand separator
function formatNumber(num) {
    return num.toString().replace(/\B(?=(\d{3})+(?!\d))/g, '.');
}

// Show loading spinner
function showLoading(elementId) {
    const element = document.getElementById(elementId);
    if (element) {
        element.innerHTML = '<div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading...</span></div>';
    }
}

// Check SNMP and fetch data
function fetchMonitoringData(apId) {
    // Gunakan satu endpoint saja untuk refresh SNMP + insert monitoring.
    const url = '/api/refresh_ap_status.php?id_ap=' + apId;
    
    fetch(url)
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success') {
                // Compat: beberapa endpoint mengembalikan {data:{...}}, sementara refresh_ap_status mengembalikan field di root.
                const payload = data.data ? data.data : {
                    id_ap: apId,
                    status_ap: data.ap_status,
                    trafik_in: data.trafik_in || 0,
                    trafik_out: data.trafik_out || 0,
                    jumlah_user: data.jumlah_user || 0
                };
                updateMonitoringDisplay(payload);
            } else {
                console.error('Error:', data.message);
            }
        })
        .catch(error => console.error('Fetch error:', error));
}

// Update monitoring display
function updateMonitoringDisplay(data) {
    // Update status
    const statusBadge = document.getElementById('status-' + data.id_ap);
    if (statusBadge) {
        statusBadge.innerHTML = '<span class="status-indicator ' + (data.status_ap === 'Online' ? 'online' : 'offline') + '"></span>' + data.status_ap;
    }
    
    // Update traffic in
    const trafficInElement = document.getElementById('traffic-in-' + data.id_ap);
    if (trafficInElement) {
        trafficInElement.textContent = formatBytes(data.trafik_in);
    }
    
    // Update traffic out
    const trafficOutElement = document.getElementById('traffic-out-' + data.id_ap);
    if (trafficOutElement) {
        trafficOutElement.textContent = formatBytes(data.trafik_out);
    }
    
    // Update user count
    const userElement = document.getElementById('users-' + data.id_ap);
    if (userElement) {
        userElement.textContent = formatNumber(data.jumlah_user) + ' user';
    }
}

// Refresh all monitoring data
function refreshAllData() {
    const refreshBtn = document.querySelector('.btn-refresh');
    if (refreshBtn) {
        refreshBtn.disabled = true;
        const originalText = refreshBtn.innerHTML;
        refreshBtn.innerHTML = '<i class="bi bi-arrow-clockwise"></i> Refreshing...';
        
        setTimeout(() => {
            location.reload();
        }, 1000);
    }
}

// Confirm delete action
function confirmDelete(message) {
    return confirm(message || 'Apakah Anda yakin ingin menghapus data ini?');
}

// Initialize tooltips
function initializeTooltips() {
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
}

// Initialize popovers
function initializePopovers() {
    const popoverTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="popover"]'));
    popoverTriggerList.map(function (popoverTriggerEl) {
        return new bootstrap.Popover(popoverTriggerEl);
    });
}

// Setup chart default options
Chart.defaults.font.family = "'Segoe UI', Tahoma, Geneva, Verdana, sans-serif";
Chart.defaults.plugins.legend.display = true;
Chart.defaults.plugins.legend.position = 'top';
Chart.defaults.plugins.tooltip.backgroundColor = 'rgba(0, 0, 0, 0.8)';
Chart.defaults.plugins.tooltip.borderColor = '#ddd';
Chart.defaults.plugins.tooltip.borderWidth = 1;

function _trafficUnitFromMaxMb(maxMb) {
    // Data sources in this app provide traffic values in MB.
    // If values are very small, show KB/B so angka tidak jadi 0 di grafik.
    if (!isFinite(maxMb) || maxMb <= 0) {
        return { unit: 'MB', scale: 1 };
    }

    // < 1 KB in MB
    if (maxMb < (1 / 1024)) {
        return { unit: 'B', scale: 1024 * 1024 };
    }

    if (maxMb < 1) {
        return { unit: 'KB', scale: 1024 };
    }

    return { unit: 'MB', scale: 1 };
}

function applyTrafficChartData(chart, labels, dataInMb, dataOutMb) {
    if (!chart) return;

    const maxIn = Math.max.apply(null, (dataInMb && dataInMb.length) ? dataInMb : [0]);
    const maxOut = Math.max.apply(null, (dataOutMb && dataOutMb.length) ? dataOutMb : [0]);
    const maxMb = Math.max(maxIn || 0, maxOut || 0);
    const info = _trafficUnitFromMaxMb(maxMb);

    const scale = info.scale;
    const unit = info.unit;

    const scaledIn = (dataInMb || []).map(v => Number(((parseFloat(v) || 0) * scale).toFixed(2)));
    const scaledOut = (dataOutMb || []).map(v => Number(((parseFloat(v) || 0) * scale).toFixed(2)));

    chart.data.labels = labels || [];
    chart.data.datasets[0].data = scaledIn;
    chart.data.datasets[1].data = scaledOut;

    // Update axis unit label
    if (chart.options && chart.options.scales && chart.options.scales.y && chart.options.scales.y.ticks) {
        chart.options.scales.y.ticks.callback = function(value) {
            return value + ' ' + unit;
        };
    }

    // Store for debugging/consistency
    chart.__trafficUnit = unit;
    chart.__trafficScale = scale;

    chart.update();
}

// Create line chart for traffic (upload/download)
function createTrafficChart(canvasId, labels, dataIn, dataOut) {
    const ctx = document.getElementById(canvasId);
    if (!ctx) return null;

    const chart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: [],
            datasets: [
                {
                    label: 'Download (In)',
                    data: [],
                    borderColor: '#667eea',
                    backgroundColor: 'rgba(102, 126, 234, 0.1)',
                    borderWidth: 2,
                    fill: true,
                    tension: 0.4,
                    pointBackgroundColor: '#667eea',
                    pointBorderColor: '#fff',
                    pointBorderWidth: 2,
                    pointRadius: 4,
                    pointHoverRadius: 6
                },
                {
                    label: 'Upload (Out)',
                    data: [],
                    borderColor: '#f5803e',
                    backgroundColor: 'rgba(245, 128, 62, 0.1)',
                    borderWidth: 2,
                    fill: true,
                    tension: 0.4,
                    pointBackgroundColor: '#f5803e',
                    pointBorderColor: '#fff',
                    pointBorderWidth: 2,
                    pointRadius: 4,
                    pointHoverRadius: 6
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: true,
                    labels: {
                        usePointStyle: true,
                        padding: 20
                    }
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            const unit = (context && context.chart && context.chart.__trafficUnit) ? context.chart.__trafficUnit : 'MB';
                            const y = (context && context.parsed && typeof context.parsed.y !== 'undefined') ? context.parsed.y : 0;
                            const val = Number(parseFloat(y) || 0).toFixed(2);
                            return context.dataset.label + ': ' + val + ' ' + unit;
                        }
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback: function(value) {
                            return value + ' MB';
                        }
                    }
                }
            }
        }
    });

    applyTrafficChartData(chart, labels, dataIn, dataOut);
    return chart;
}

// Create bar chart for multiple AP status
function createStatusChart(canvasId, labels, onlineData, offlineData) {
    const ctx = document.getElementById(canvasId);
    if (!ctx) return null;

    return new Chart(ctx, {
        type: 'bar',
        data: {
            labels: labels,
            datasets: [{
                label: 'Online',
                data: onlineData,
                backgroundColor: '#28a745'
            }, {
                label: 'Offline',
                data: offlineData,
                backgroundColor: '#dc3545'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            indexAxis: 'y',
            plugins: {
                legend: {
                    display: true
                }
            },
            scales: {
                x: {
                    stacked: true
                },
                y: {
                    stacked: true
                }
            }
        }
    });
}

// Real-time clock
function updateClock() {
    const now = new Date();
    const timeString = now.toLocaleTimeString('id-ID');
    const dateString = now.toLocaleDateString('id-ID', {
        weekday: 'long',
        year: 'numeric',
        month: 'long',
        day: 'numeric'
    });
    
    const clockElement = document.getElementById('clock');
    if (clockElement) {
        clockElement.textContent = dateString + ' ' + timeString;
    }
}

// Initialize on document ready
document.addEventListener('DOMContentLoaded', function() {
    initializeTooltips();
    initializePopovers();
    updateClock();
    setInterval(updateClock, 1000);
});
