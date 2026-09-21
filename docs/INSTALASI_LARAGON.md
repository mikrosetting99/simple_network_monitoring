# Tutorial Instalasi di Laragon (Windows)

Panduan memasang **Simple Network Monitoring** dari nol sampai perangkat pertama termonitor. Perkiraan waktu: 10–15 menit.

**Yang dibutuhkan:** Laragon versi Full (sudah termasuk Apache, PHP 8.1+, MySQL, dan Git). Ekstensi `pdo_mysql`, `mbstring`, `zip`, dan `SimpleXML` sudah aktif secara bawaan di Laragon.

> Contoh di bawah memakai folder `C:\laragon\www\simple_network_monitoring`. Ganti bila Laragon Anda dipasang di lokasi lain.

---

## 1. Jalankan Laragon

1. Buka Laragon, klik **Start All** (Apache dan MySQL harus berwarna hijau).
2. Agar otomatis menyala saat Windows hidup: **Menu → Preferences → General**, centang *Run Laragon when Windows starts* dan *Start All automatically*.

Worker ping bergantung pada MySQL, jadi Laragon harus selalu menyala selama monitoring berjalan.

## 2. Ambil kode aplikasi

Klik tombol **Terminal** di Laragon, lalu:

```bash
cd C:\laragon\www
git clone https://github.com/mikrosetting99/simple_network_monitoring.git
```

Tanpa Git, unduh ZIP dari GitHub dan ekstrak ke `C:\laragon\www\simple_network_monitoring`.

## 3. Buat database

Cara termudah lewat Terminal Laragon:

```bash
cd C:\laragon\www\simple_network_monitoring
mysql -uroot < database\schema.sql
```

Perintah ini membuat database `db_monitoring` beserta seluruh tabelnya. Laragon memakai user `root` tanpa password secara bawaan.

Alternatif lewat GUI: klik kanan Laragon → **Database → HeidiSQL** (atau **phpMyAdmin**), lalu impor file `database/schema.sql`.

## 4. Buat file konfigurasi

```bash
copy config.sample.php config.php
```

Buka `config.php` dan cek:

| Kunci | Nilai bawaan | Keterangan |
| --- | --- | --- |
| `db.user` / `db.pass` | `root` / kosong | Sesuai bawaan Laragon; ubah bila Anda sudah memberi password MySQL |
| `timezone` | `Asia/Jakarta` | Ganti ke `Asia/Makassar` atau `Asia/Jayapura` bila perlu |
| `parallel` | `30` | Jumlah ping bersamaan |
| `ping_timeout` | `5` | Detik per perangkat. **Disarankan naik ke `8`** agar perangkat yang packet loss-nya tinggi tidak terpotong |

`config.php` tidak ikut Git, jadi aman dari `git pull`.

## 5. Buka aplikasi

Buka **http://localhost/simple_network_monitoring/public/** di browser.

Anda akan melihat dashboard kosong dengan peringatan kuning *"Worker belum pernah berjalan"*. Itu normal, worker dipasang di langkah 7.

Bila muncul tulisan *"config.php belum ada"*, ulangi langkah 4.

## 6. Cek diagnostik dan tambah perangkat

1. Buka menu **Pengaturan**. Pastikan `proc_open` berstatus *tersedia*, lalu klik **Jalankan ping uji (127.0.0.1)**. Harus muncul *Berhasil*.
2. Tambah perangkat di menu **Perangkat**:
   - satu per satu lewat **+ Tambah perangkat**, atau
   - massal lewat **Import Excel**: unduh *template .xlsx*, isi kolom `nama`, `ip`, `grup`, lalu unggah.

## 7. Pasang worker ping (penjadwal tiap 1 menit)

Tanpa langkah ini tidak ada ping yang berjalan. Cari dulu lokasi `php.exe` Laragon:

```bash
where php
```

Contoh hasil: `C:\laragon\bin\php\php-8.3.33-Win32-vs16-x64\php.exe` (nomor versi di komputer Anda bisa berbeda).

Buka **PowerShell sebagai Administrator**, ganti path PHP sesuai hasil di atas, lalu jalankan:

```powershell
$php = "C:\laragon\bin\php\php-8.3.33-Win32-vs16-x64\php.exe"
$app = "C:\laragon\www\simple_network_monitoring"

schtasks /Create /TN "MonitoringPing" /SC MINUTE /MO 1 /RU SYSTEM /TR "`"$php`" `"$app\cron\ping_worker.php`""
schtasks /Create /TN "MonitoringCleanup" /SC DAILY /ST 03:00 /RU SYSTEM /TR "`"$php`" `"$app\cron\cleanup.php`""
```

- **MonitoringPing** menjalankan worker tiap menit.
- **MonitoringCleanup** meringkas log lama tiap jam 03:00 (log mentah disimpan 14 hari, agregat per jam 12 bulan).

Uji manual sebelum menunggu penjadwal:

```bash
php C:\laragon\www\simple_network_monitoring\cron\ping_worker.php
```

Tidak ada output berarti sukses. Segarkan dashboard: status perangkat terisi dan peringatan kuning hilang. Setiap siklus juga tercatat di `storage\worker.log`.

> **Perangkat "MATI" baru muncul setelah 3 siklus gagal berturut-turut** (bawaan, sekitar 3 menit). Ini disengaja supaya satu paket yang hilang tidak memicu alarm palsu.

## 8. Akses dari perangkat lain di jaringan (opsional)

1. Buka **PowerShell sebagai Administrator** dan izinkan port 80 hanya dari subnet lokal:

   ```powershell
   New-NetFirewallRule -DisplayName "Monitoring Ping HTTP" -Direction Inbound -Protocol TCP -LocalPort 80 -Action Allow -Profile Any -RemoteAddress LocalSubnet
   ```

2. Akses dari perangkat lain: `http://IP-KOMPUTER/simple_network_monitoring/public/` (cek IP dengan `ipconfig`).
3. Buka `.htaccess` di folder aplikasi dan pastikan baris `Require ip` mencakup jaringan Anda. Bawaannya `10.0.0.0/8`, `172.16.0.0/12`, `192.168.0.0/16`. Alamat di luar daftar akan mendapat **403 Forbidden**.
4. Restart Apache dari Laragon setelah mengubah `.htaccess`.

> **Penting:** aplikasi ini tidak memakai login. Perlindungannya hanya pembatasan jaringan. Jangan port-forward ke internet.

---

## Memperbarui aplikasi

```bash
cd C:\laragon\www\simple_network_monitoring
git pull
```

`config.php` dan data database Anda tidak tertimpa. Setelah update, cek riwayat commit (`git log`) apakah ada perubahan pada `database/schema.sql`; bila ada, terapkan perubahan itu secara manual.

## Mengatasi masalah

| Gejala | Penyebab dan solusi |
| --- | --- |
| Peringatan kuning *Worker belum pernah berjalan* atau *Siklus terakhir … lalu* | Penjadwal belum terpasang atau berhenti. Cek di Task Scheduler bahwa `MonitoringPing` berstatus *Ready*, lalu jalankan worker manual (langkah 7). Pastikan Laragon (MySQL) menyala |
| Semua perangkat *belum dicek* padahal worker jalan | Cek `storage\worker.log`; pastikan `config.php` benar dan MySQL berjalan |
| Ping uji gagal, atau `proc_open` *DINONAKTIFKAN* | Buka `php.ini` (Menu Laragon → PHP → php.ini), hapus `proc_open` dari `disable_functions`, restart Apache |
| Perangkat sehat tapi terbaca MATI | Perangkat atau firewall-nya memblokir ICMP (ping). Buka Command Prompt dan coba `ping <ip>`. Bila di sana pun gagal, aplikasi tidak bisa memantaunya lewat ping |
| Perangkat packet loss tinggi sering terbaca MATI palsu | Naikkan `ping_timeout` di `config.php` dan/atau naikkan ambang gagal perangkat itu |
| 403 Forbidden dari komputer lain | IP Anda di luar daftar `Require ip` di `.htaccess` (langkah 8) |
| Tidak bisa dibuka dari komputer lain padahal 403 tidak muncul | Firewall Windows memblokir port 80 (langkah 8) |
| `Access denied for user 'root'` | Password MySQL Anda bukan kosong; isi `db.pass` di `config.php` |
| Apache tidak mau start, port 80 bentrok | Program lain memakai port 80 (mis. IIS atau Skype). Hentikan program itu, atau ubah port di Laragon (Menu → Preferences → Services & Ports) dan akses dengan `http://localhost:PORT/...` |
| Import Excel gagal membaca file | Pastikan formatnya `.xlsx` (bukan `.xls`), atau simpan sebagai CSV |

## Alternatif ringan tanpa Apache

Untuk sekadar mencoba, tanpa Apache, dari Terminal Laragon:

```bash
cd C:\laragon\www\simple_network_monitoring
php -S 0.0.0.0:8080 -t public
```

Lalu buka `http://localhost:8080/`. Pada mode ini `.htaccess` **tidak berlaku**, jadi pembatasan IP tidak aktif. Batasi akses lewat Windows Firewall saja.
