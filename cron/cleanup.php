<?php
declare(strict_types=1);

// Jalankan sekali sehari (mis. jam 03:00): ringkas ping_logs lama jadi rata-rata per jam, lalu hapus baris mentah.
if (PHP_SAPI !== 'cli') {
    exit('CLI only');
}
require __DIR__ . '/../app/bootstrap.php';

$days = max(1, (int) Settings::get('retention_days'));
// Batas dibulatkan ke awal jam supaya setiap jam yang diringkas selalu lengkap.
$cutoff = date('Y-m-d H:00:00', strtotime("-$days days"));

Database::query(
    "INSERT INTO ping_hourly (device_id, hour_start, samples, up_count, lat_samples, avg_latency, avg_loss)
     SELECT device_id, DATE_FORMAT(checked_at, '%Y-%m-%d %H:00:00'), COUNT(*), SUM(status = 'up'), COUNT(latency_ms), AVG(latency_ms), AVG(packet_loss)
     FROM ping_logs WHERE checked_at < ?
     GROUP BY device_id, DATE_FORMAT(checked_at, '%Y-%m-%d %H:00:00')
     ON DUPLICATE KEY UPDATE samples = VALUES(samples), up_count = VALUES(up_count), lat_samples = VALUES(lat_samples), avg_latency = VALUES(avg_latency), avg_loss = VALUES(avg_loss)",
    [$cutoff]
);

// Hapus bertahap agar tabel tidak terkunci lama.
$deleted = 0;
do {
    $n = Database::query('DELETE FROM ping_logs WHERE checked_at < ? LIMIT 50000', [$cutoff])->rowCount();
    $deleted += $n;
} while ($n > 0);

$old = Database::query('DELETE FROM ping_hourly WHERE hour_start < ?', [date('Y-m-d H:i:s', strtotime('-12 months'))])->rowCount();

echo date('c') . " cleanup: $deleted ping_logs dihapus, $old baris ping_hourly kedaluwarsa dihapus\n";
