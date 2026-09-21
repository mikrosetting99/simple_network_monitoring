# Monitoring Ping Perangkat (PHP / XAMPP)

Implementasi PRD v1: ping terjadwal ke daftar IP, dashboard status, riwayat kejadian down, laporan uptime. Tanpa login; akses dibatasi di level jaringan.

## Instalasi

1. Salin folder ini ke `C:\xampp\htdocs\monitoring` (Linux: `/opt/lampp/htdocs/monitoring`).
2. Buka phpMyAdmin → Import `database/schema.sql` (membuat database `db_monitoring`).
3. Salin `config.sample.php` menjadi `config.php`, sesuaikan kredensial DB.
4. Sesuaikan `Require ip ...` di `.htaccess` dengan LAN/VPN Anda. **Jangan port-forward ke internet.**
5. Buka `http://localhost/monitoring/` → menu **Pengaturan** → cek diagnostik dan jalankan ping uji.
6. Tambah perangkat (atau import CSV berkolom `nama,ip,grup`).

## Penjadwalan

Worker ping, tiap 1 menit (Windows, PowerShell sebagai Administrator):

```powershell
schtasks /Create /TN "MonitoringPing" /SC MINUTE /MO 1 /RU SYSTEM /TR "C:\xampp\php\php.exe C:\xampp\htdocs\monitoring\cron\ping_worker.php"
schtasks /Create /TN "MonitoringCleanup" /SC DAILY /ST 03:00 /RU SYSTEM /TR "C:\xampp\php\php.exe C:\xampp\htdocs\monitoring\cron\cleanup.php"
```

Linux (crontab):

```
* * * * * /opt/lampp/bin/php /opt/lampp/htdocs/monitoring/cron/ping_worker.php
0 3 * * * /opt/lampp/bin/php /opt/lampp/htdocs/monitoring/cron/cleanup.php
```

Header aplikasi menampilkan waktu siklus terakhir dan berubah kuning bila worker berhenti lebih dari 5 menit.

## Catatan perilaku

- Down = `fail_threshold` siklus gagal berturut-turut (default 3). `down_since` dan awal insiden memakai ping gagal pertama dari rentetan itu.
- Maintenance: ping tetap dicatat, tetapi tidak ada insiden baru.
- Membuka detail perangkat menandai kejadiannya "sudah dilihat" (tanda "baru" hilang).
- Uptime laporan dihitung dari irisan insiden dengan rentang laporan, sehingga tetap akurat setelah log mentah diringkas.
- Berbeda dari PRD: ditambah tabel `settings` (pengaturan + status siklus worker) dan `ping_hourly` (agregat retensi); kolom `ip_address` diperlebar ke 255 untuk hostname.
