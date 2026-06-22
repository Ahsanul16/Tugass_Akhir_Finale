# WiFi Monitoring (SNMP) - PHP + MySQL

Web monitoring Access Point / MikroTik menggunakan SNMP (v1/v2c) + MySQL.

Fitur
- Status Online/Offline (berdasarkan SNMP).
- Trafik bandwidth in/out (chart).
- Jumlah user (scalar OID via snmpget, atau table OID via snmpwalk + count).
- Realtime update (default setiap 5 detik) dengan throttle agar tidak berat.

Setup (XAMPP)
1. Import database:
   - Jalankan [db/schema.sql](db/schema.sql)
   - Jalankan [db/indexes.sql](db/indexes.sql)
   - (Opsional) Jalankan [db/seed.sql](db/seed.sql)
2. Pastikan PHP SNMP extension aktif.
3. Buka aplikasi dan login.

Endpoint Utama
- `GET /api/refresh_ap_status.php?id_ap=ID&log=1`
  - Dipakai oleh realtime UI untuk insert snapshot monitoring.
- `GET /api/refresh_ap_status.php?id_ap=ID&force=1&log=1`
  - Manual refresh: selalu SNMP query ulang.

Endpoint Pendukung (DB only)
- `GET /api/get_dashboard_chart.php?id_ap=ID`
- `GET /api/get_ap_chart.php?id_ap=ID`
- `GET /api/get_ap_monitoring_rows.php?id_ap=ID&limit=100[&mon_date=YYYY-MM-DD][&mon_status=Online|Offline]`
- `GET /api/get_ap_stats.php?id_ap=ID`

Konfigurasi Throttle
Edit [config/snmp.php](config/snmp.php):
- `SNMP_MIN_REFRESH_INTERVAL` (detik): minimal jarak SNMP query sungguhan.
- `MONITORING_MIN_LOG_INTERVAL` (detik): minimal jarak insert row monitoring.

Catatan
- Endpoint lama sudah dihapus permanen.
