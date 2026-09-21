<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit('CLI only');
}
require __DIR__ . '/../app/bootstrap.php';

// File lock: dua siklus tidak boleh berjalan bersamaan.
$lock = fopen(APP_ROOT . '/storage/worker.lock', 'c');
if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) {
    exit(0);
}

$started = microtime(true);
$now = date('Y-m-d H:i:s');
$nowTs = strtotime($now);

$devices = Database::all('SELECT * FROM devices WHERE is_active = 1');
$due = [];
foreach ($devices as $d) {
    $last = $d['last_checked_at'] ? strtotime($d['last_checked_at']) : 0;
    // Toleransi 5 detik karena siklus dijadwalkan tiap menit.
    if ($nowTs - $last >= (int) $d['interval_sec'] - 5) {
        $due[(int) $d['id']] = $d;
    }
}

$targets = [];
foreach ($due as $id => $d) {
    $targets[$id] = $d['ip_address'];
}
$results = $targets ? PingService::runBatch($targets, (int) ($config['parallel'] ?? 30), (int) ($config['ping_timeout'] ?? 5)) : [];

foreach ($results as $id => $res) {
    try {
        DeviceMonitor::record($due[$id], $res, $now);
    } catch (Throwable $e) {
        // Kegagalan satu perangkat tidak menghentikan siklus.
        error_log(date('c') . " device $id: " . $e->getMessage() . "\n", 3, APP_ROOT . '/storage/worker.log');
    }
}

$sec = round(microtime(true) - $started, 1);
Settings::set('last_cycle_at', $now);
Settings::set('last_cycle_sec', (string) $sec);
Settings::set('last_cycle_count', (string) count($due));

// Pertahankan log siklus tetap kecil.
$logFile = APP_ROOT . '/storage/worker.log';
if (is_file($logFile) && filesize($logFile) > 1024 * 1024) {
    file_put_contents($logFile, '');
}
file_put_contents($logFile, "$now cycle: " . count($due) . " devices, {$sec}s\n", FILE_APPEND);
