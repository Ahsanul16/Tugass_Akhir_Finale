# File Map (Penjelasan Per File)

Dokumen ini menjelaskan fungsi tiap file utama di project `wifi_monitoring` dan "cara pakai" (URL / parameter) agar mudah dipelajari.

## Entry Point

- `index.php`
  - Halaman awal aplikasi. Umumnya redirect ke login atau dashboard sesuai session.

## Konfigurasi

- `config/database.php`
  - Koneksi MySQL (mysqli) dan variabel `$conn` yang dipakai di seluruh aplikasi.

- `config/session.php`
  - Inisialisasi session + beberapa setting runtime (mis. timeout).

- `config/snmp.php`
  - Helper SNMP: ambil value, walk table, convert traffic/user, dan status Online/Offline.
  - Throttle:
    - `SNMP_MIN_REFRESH_INTERVAL`: minimal jarak SNMP query sungguhan.
    - `MONITORING_MIN_LOG_INTERVAL`: minimal jarak insert row monitoring.
  - Catatan MikroTik:
    - `snmp2_get` dicoba dulu (v2c), fallback ke `snmpget` (v1).
    - `getUserCount()`:
      - OID berakhir `.0` -> `snmpget` (scalar number)
      - OID tanpa `.0` -> `snmpwalk` + `count()` (table column, mis. hotspot active users)

## Middleware

- `middleware/auth_check.php`
  - Fungsi auth seperti `isLoggedIn()` dan `requireRole()`.

- `middleware/csrf.php`
  - Generate dan verify CSRF token untuk form POST.

## Helpers

- `helpers/access_point.php`
  - CRUD Access Point, query monitoring, paging table, statistik avg/max/min, dan cleanup monitoring 30 hari.

- `helpers/user.php`
  - Helper query user/role.

## API (Dipakai)

- `api/refresh_ap_status.php`
  - Endpoint utama untuk refresh SNMP + insert data ke tabel `monitoring`.
  - URL:
    - `GET /api/refresh_ap_status.php?id_ap=ID&log=1`
      - Dipakai realtime UI: insert snapshot monitoring (dengan throttle).
    - `GET /api/refresh_ap_status.php?id_ap=ID&force=1&log=1`
      - Manual refresh: selalu SNMP query ulang.
  - Parameter:
    - `id_ap` (wajib)
    - `force=1` (opsional) -> bypass throttle dan query SNMP.
    - `log=1` (opsional) -> pastikan row monitoring ditulis walau SNMP sedang di-throttle.

- `api/get_dashboard_chart.php`
  - Data chart dashboard (DB only, 24 jam terakhir).
  - URL: `GET /api/get_dashboard_chart.php?id_ap=ID`

- `api/get_ap_chart.php`
  - Data chart detail AP (DB only, 20 data terakhir).
  - URL: `GET /api/get_ap_chart.php?id_ap=ID`

- `api/get_ap_monitoring_rows.php`
  - Ambil HTML `<tr>...</tr>` untuk tabel monitoring detail (DB only).
  - URL: `GET /api/get_ap_monitoring_rows.php?id_ap=ID&limit=100[&mon_date=YYYY-MM-DD][&mon_status=Online|Offline]`

- `api/get_ap_stats.php`
  - Ambil statistik avg/max/min dari DB untuk detail AP.
  - URL: `GET /api/get_ap_stats.php?id_ap=ID`

- `api/get_lantai.php`
  - List lantai berdasarkan gedung (untuk dropdown form Kelola AP).
  - URL: `GET /api/get_lantai.php?id_gedung=ID`

## API (Catatan)

- Endpoint lama sudah dihapus permanen agar tidak membingungkan.

## Halaman Admin

- `admin/dashboard.php`
  - Dashboard admin + chart.
  - Realtime: melakukan request otomatis tiap 5 detik:
    - `refresh_ap_status.php?id_ap=...&log=1`
    - lalu `get_dashboard_chart.php?id_ap=...`

- `admin/access_point.php`
  - Detail AP: status, chart, tabel monitoring, statistik, dan tombol manual refresh.
  - Realtime: request otomatis tiap 5 detik:
    - `refresh_ap_status.php?id_ap=...&log=1`
    - `get_ap_chart.php`, `get_ap_monitoring_rows.php`, `get_ap_stats.php`

- `admin/manage_access_point.php`
  - CRUD AP untuk admin.

- `admin/manage_gedung.php`, `admin/manage_lantai.php`
  - CRUD master lokasi (Gedung & Lantai).

## Halaman SuperAdmin

- `superadmin/dashboard.php`
  - Dashboard superadmin + chart realtime (mirip admin).

- `superadmin/manage_access_point.php`
  - CRUD AP untuk superadmin.

- `superadmin/manage_gedung.php`, `superadmin/manage_lantai.php`
  - CRUD master lokasi (Gedung & Lantai).

- `superadmin/manage_admin.php`
  - CRUD user admin.

## Auth

- `auth/login.php`, `auth/logout.php`, `auth/cek_login.php`
  - Login/logout flow.

## Templates

- `templates/header.php`, `templates/footer.php`
  - Layout dan menu (menentukan link halaman admin/superadmin).

## Assets

- `assets/js/main.js`
  - Chart.js helper:
    - `createTrafficChart()`
    - `applyTrafficChartData()` -> auto unit KB/MB/B supaya angka kecil tetap terlihat.

- `assets/css/style.css`
  - Styling UI.

## Database Scripts

- `db/schema.sql`
  - Membuat tabel (users, access_point, monitoring).

- `db/indexes.sql`
  - Menambahkan index untuk performa query monitoring.

- `db/seed.sql`
  - Data awal (contoh user login).
