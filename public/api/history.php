<?php
declare(strict_types=1);
require __DIR__ . '/../../app/bootstrap.php';

$id = (int) ($_GET['id'] ?? 0);
$hours = min(168, max(1, (int) ($_GET['hours'] ?? 24)));
$from = date('Y-m-d H:i:s', time() - $hours * 3600);

$rows = Database::all(
    'SELECT checked_at, status, latency_ms FROM ping_logs WHERE device_id = ? AND checked_at >= ? ORDER BY checked_at',
    [$id, $from]
);
json_out(array_map(static fn(array $r) => [
    't'       => strtotime($r['checked_at']) * 1000,
    'up'      => $r['status'] === 'up',
    'latency' => $r['latency_ms'] === null ? null : (float) $r['latency_ms'],
], $rows));
