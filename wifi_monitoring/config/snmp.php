<?php
/**
 * Konfigurasi SNMP - FLEKSIBEL OID
 * - Bisa gunakan oid_status, oid_traffic_in, oid_traffic_out, atau oid_user saja
 * - Support partial OID (tidak perlu semua)
 * - Timeout pendek untuk response cepat
 */

define('SNMP_TIMEOUT', 500000);  // 0.5 detik
define('SNMP_RETRIES', 1);
define('SNMP_PREFER_V2C', true);
// Throttle untuk endpoint refresh agar tidak terlalu berat jika ada auto-refresh / banyak tab.
// Jika refresh dipanggil sebelum interval ini lewat (dan tidak force), endpoint akan mengembalikan snapshot DB terakhir.
define('SNMP_MIN_REFRESH_INTERVAL', 30); // detik
// Interval minimum untuk insert row monitoring (mencegah double insert jika ada multi-tab).
define('MONITORING_MIN_LOG_INTERVAL', 5); // detik

$_status_cache = [];
$_status_cache_time = [];

function isSnmpErrorString($value) {
    if (!is_string($value)) {
        return false;
    }
    $v = strtolower(trim($value));
    if ($v === '') return false;

    // Common SNMP error strings (Net-SNMP / PHP SNMP)
    return (
        strpos($v, 'timeout') !== false ||
        strpos($v, 'no such') !== false ||
        strpos($v, 'unknown object') !== false ||
        strpos($v, 'end of mib') !== false ||
        strpos($v, 'error') !== false
    );
}

function cleanSnmpRawValue($value) {
    if ($value === false || $value === null) {
        return false;
    }
    if (!is_string($value)) {
        $value = (string) $value;
    }

    $value = trim(str_replace('"', '', $value));
    if ($value === '' || isSnmpErrorString($value)) {
        return false;
    }

    return $value;
}

/**
 * Fungsi untuk get SNMP value
 */
function snmpGetValue($ip_address, $community, $oid) {
    try {
        if (empty($ip_address) || empty($community) || empty($oid)) {
            return false;
        }
        
        if (extension_loaded('snmp')) {
            @snmp_set_oid_output_format(SNMP_OID_OUTPUT_NUMERIC);
            snmp_set_quick_print(true);

            $value = false;

            // Prefer SNMP v2c when possible (better for Counter64 OIDs used by MikroTik traffic stats)
            if (SNMP_PREFER_V2C && function_exists('snmp2_get')) {
                $value = @snmp2_get($ip_address, $community, $oid, SNMP_TIMEOUT, SNMP_RETRIES);
                $value = cleanSnmpRawValue($value);
            }

            // Fallback to SNMP v1
            if ($value === false) {
                $value = @snmpget($ip_address, $community, $oid, SNMP_TIMEOUT, SNMP_RETRIES);
                $value = cleanSnmpRawValue($value);
            }

            return $value;
        }
    } catch (Exception $e) {
        return false;
    }
    return false;
}

/**
 * Fungsi untuk snmpwalk multiple values
 */
function snmpWalkValues($ip_address, $community, $oid) {
    try {
        if (empty($ip_address) || empty($community) || empty($oid)) {
            return [];
        }
        
        if (extension_loaded('snmp')) {
            @snmp_set_oid_output_format(SNMP_OID_OUTPUT_NUMERIC);
            snmp_set_quick_print(true);

            $values = false;

            if (SNMP_PREFER_V2C && function_exists('snmp2_walk')) {
                $values = @snmp2_walk($ip_address, $community, $oid, SNMP_TIMEOUT, SNMP_RETRIES);
            }

            if ($values === false) {
                $values = @snmpwalk($ip_address, $community, $oid, SNMP_TIMEOUT, SNMP_RETRIES);
            }

            if ($values === false || !is_array($values)) {
                return [];
            }

            $cleaned = [];
            foreach ($values as $value) {
                $v = cleanSnmpRawValue($value);
                if ($v !== false) {
                    $cleaned[] = $v;
                }
            }
            return $cleaned;
        }
    } catch (Exception $e) {
        return [];
    }
    return [];
}

/**
 * Cek status AP - Hanya menggunakan SNMP
 * Support: oid_status, oid_traffic_in, oid_traffic_out, atau oid_user saja
 * Catatan: PING/ICMP fallback telah dihapus; monitoring hanya via SNMP.
 * 
 * @param string $ip_address IP Address AP
 * @param string $community SNMP Community
 * @param array $oids Array dari OID yang tersedia ['oid_status' => '...', 'oid_traffic_in' => '...']
 * @param bool $force Jika true, bypass cache dan lakukan SNMP query ulang (dipakai saat tombol refresh)
 * @return string 'Online' atau 'Offline'
 */
function checkAccessPointStatus($ip_address, $community = '', $oids = [], $force = false) {
    global $_status_cache, $_status_cache_time;
    
    // Filter OID yang tidak kosong
    $available_oids = [];
    if (!empty($oids['oid_status'])) $available_oids[] = $oids['oid_status'];
    if (!empty($oids['oid_traffic_in'])) $available_oids[] = $oids['oid_traffic_in'];
    if (!empty($oids['oid_traffic_out'])) $available_oids[] = $oids['oid_traffic_out'];
    if (!empty($oids['oid_user'])) $available_oids[] = $oids['oid_user'];
    
    // Cache key (based on IP + OIDs)
    $cache_key = md5($ip_address . (implode(',', $available_oids) ?: 'snmp'));
    $now = time();
    
    // Cache check (5 menit)
    if (!$force) {
        if (isset($_status_cache[$cache_key]) && isset($_status_cache_time[$cache_key])) {
            if ($now - $_status_cache_time[$cache_key] < 300) {
                return $_status_cache[$cache_key];
            }
        }
    }
    
    $status = 'Offline';
    
    // Only use SNMP to determine status. If community or OID not configured, return 'Offline'.
    if (!empty($community) && !empty($available_oids)) {
        $oid_to_check = $available_oids[0];
        $value = snmpGetValue($ip_address, $community, $oid_to_check);

        if ($value !== false && !empty($value)) {
            $status = 'Online';
        }
    } else {
        // No SNMP configuration available — cannot determine status without SNMP
        $status = 'Offline';
    }
    
    // Cache result
    $_status_cache[$cache_key] = $status;
    $_status_cache_time[$cache_key] = $now;
    
    return $status;
}

/**
 * Get traffic in data - Optional (jika oid_traffic_in kosong return 0)
 * 
 * @param string $ip_address IP Address AP
 * @param string $community SNMP Community
 * @param string $oid_traffic_in OID untuk traffic in (bisa kosong)
 * @return float Nilai traffic in atau 0 jika tidak ada OID
 */
function getTrafficInData($ip_address, $community = '', $oid_traffic_in = '') {
    // Jika salah satu kosong = tidak cek traffic
    if (empty($community) || empty($oid_traffic_in)) {
        return 0;
    }
    
    $value = snmpGetValue($ip_address, $community, $oid_traffic_in);
    
    if ($value === false || empty($value)) {
        return 0;
    }
    
    // Extract numeric value
    $numeric_value = (float) preg_replace('/[^0-9.]/', '', $value);
    return $numeric_value;
}

/**
 * Get traffic out data - Optional (jika oid_traffic_out kosong return 0)
 * 
 * @param string $ip_address IP Address AP
 * @param string $community SNMP Community
 * @param string $oid_traffic_out OID untuk traffic out (bisa kosong)
 * @return float Nilai traffic out atau 0 jika tidak ada OID
 */
function getTrafficOutData($ip_address, $community = '', $oid_traffic_out = '') {
    // Jika salah satu kosong = tidak cek traffic
    if (empty($community) || empty($oid_traffic_out)) {
        return 0;
    }
    
    $value = snmpGetValue($ip_address, $community, $oid_traffic_out);
    
    if ($value === false || empty($value)) {
        return 0;
    }
    
    // Extract numeric value
    $numeric_value = (float) preg_replace('/[^0-9.]/', '', $value);
    return $numeric_value;
}

/**
 * Get user count - Optional (jika oid_user kosong return 0)
 * 
 * @param string $ip_address IP Address AP
 * @param string $community SNMP Community
 * @param string $oid_user OID untuk user count (bisa kosong)
 * @return int Jumlah user atau 0 jika tidak ada OID
 */
function getUserCount($ip_address, $community = '', $oid_user = '') {
    // Jika salah satu kosong = tidak cek user
    if (empty($community) || empty($oid_user)) {
        return 0;
    }

    $oid_user = trim($oid_user);

    // Jika scalar OID (umumnya berakhir .0) maka pakai snmpget untuk ambil angka langsung.
    // Jika bukan scalar, anggap itu kolom table (mis. MikroTik mtxrHotspotActiveUserName)
    // dan hitung jumlah entry via snmpwalk.
    if (substr($oid_user, -2) === '.0') {
        $value = snmpGetValue($ip_address, $community, $oid_user);

        if ($value === false || $value === '') {
            return 0;
        }

        // Extract numeric value
        $count = (int) preg_replace('/[^0-9]/', '', $value);
        return max(0, $count);
    }

    $values = snmpWalkValues($ip_address, $community, $oid_user);
    if (empty($values)) {
        return 0;
    }

    return count($values);
}

/**
 * Format bandwidth
 */
function formatBandwidth($bytes) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= (1 << (10 * $pow));
    
    return round($bytes, 2) . ' ' . $units[$pow];
}

?>
