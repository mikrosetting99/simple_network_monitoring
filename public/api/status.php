<?php
declare(strict_types=1);
require __DIR__ . '/../../app/bootstrap.php';

$now = date('Y-m-d H:i:s');
$rows = Database::all(
    'SELECT d.id, d.name, d.ip_address AS ip, d.group_id, g.name AS grp, d.current_status AS status,
            d.last_latency_ms AS latency, d.down_since, d.last_checked_at,
            (d.maintenance_until IS NOT NULL AND d.maintenance_until > ?) AS maint,
            EXISTS(SELECT 1 FROM incidents i WHERE i.device_id = d.id AND i.seen_at IS NULL) AS unseen
     FROM devices d LEFT JOIN `groups` g ON g.id = d.group_id
     WHERE d.is_active = 1',
    [$now]
);

$devices = array_map(static fn(array $r) => [
    'id'      => (int) $r['id'],
    'name'    => $r['name'],
    'ip'      => $r['ip'],
    'gid'     => $r['group_id'] === null ? null : (int) $r['group_id'],
    'grp'     => $r['grp'],
    'status'  => $r['status'],
    'latency' => $r['latency'] === null ? null : (float) $r['latency'],
    'since'   => $r['down_since'] ? strtotime($r['down_since']) : null,
    'checked' => $r['last_checked_at'] ? strtotime($r['last_checked_at']) : null,
    'maint'   => (bool) $r['maint'],
    'unseen'  => (bool) $r['unseen'],
], $rows);

$health = worker_health();
json_out([
    'now'     => time(),
    'sound'   => Settings::get('sound_alert') === '1',
    'worker'  => ['ok' => $health['ok'], 'text' => $health['text']],
    'devices' => $devices,
]);
