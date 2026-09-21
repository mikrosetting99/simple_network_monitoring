<?php
declare(strict_types=1);
require __DIR__ . '/../../app/bootstrap.php';

if (!is_post()) {
    json_out(['error' => 'POST only'], 405);
}
csrf_verify();

$device = Database::one('SELECT * FROM devices WHERE id = ?', [(int) ($_POST['id'] ?? 0)]);
if (!$device) {
    json_out(['error' => 'Perangkat tidak ditemukan'], 404);
}
if (!PingService::canRun()) {
    json_out(['error' => 'proc_open dinonaktifkan di php.ini (disable_functions)'], 500);
}

$res = PingService::checkOne($device['ip_address'], (int) ($config['ping_timeout'] ?? 5));
DeviceMonitor::record($device, $res, date('Y-m-d H:i:s'));
json_out($res);
