<?php
// Salin file ini menjadi config.php lalu sesuaikan. config.php tidak masuk repository.
return [
    'db' => [
        'host' => '127.0.0.1',
        'port' => 3306,
        'name' => 'db_monitoring',
        'user' => 'root',
        'pass' => '',
    ],
    'timezone'     => 'Asia/Jakarta',
    'parallel'     => 30,  // jumlah proses ping paralel per batch
    'ping_timeout' => 5,   // detik, batas keras per perangkat
];
