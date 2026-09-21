<?php
declare(strict_types=1);
require __DIR__ . '/../app/bootstrap.php';

if (!Xlsx::available()) {
    http_response_code(500);
    exit('Ekstensi zip PHP belum aktif.');
}

$tmp = tempnam(sys_get_temp_dir(), 'tpl');
Xlsx::write($tmp, [
    ['nama', 'ip', 'grup'],
    ['Router Pelanggan A', '192.168.10.1', 'Pelanggan'],
    ['AP Gudang', '192.168.10.20', 'Access Point'],
    ['Server Utama', '10.0.0.5', ''],
]);

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="template_import_perangkat.xlsx"');
header('Content-Length: ' . filesize($tmp));
readfile($tmp);
@unlink($tmp);
