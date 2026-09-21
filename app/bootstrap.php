<?php
declare(strict_types=1);

define('APP_ROOT', dirname(__DIR__));

$configFile = APP_ROOT . '/config.php';
if (!is_file($configFile)) {
    if (PHP_SAPI !== 'cli') {
        http_response_code(500);
    }
    exit("config.php belum ada. Salin config.sample.php menjadi config.php lalu isi kredensial database.\n");
}
$config = require $configFile;
date_default_timezone_set($config['timezone'] ?? 'Asia/Jakarta');

require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/Settings.php';
require_once __DIR__ . '/PingService.php';
require_once __DIR__ . '/DeviceMonitor.php';
require_once __DIR__ . '/Xlsx.php';
require_once __DIR__ . '/DeviceImporter.php';
require_once __DIR__ . '/helpers.php';

if (PHP_SAPI !== 'cli' && session_status() === PHP_SESSION_NONE) {
    session_start();
}
