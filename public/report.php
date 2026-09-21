<?php
declare(strict_types=1);
require __DIR__ . '/_layout.php';

$today = date('Y-m-d');
$from = preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['from'] ?? '') ? $_GET['from'] : date('Y-m-d', strtotime('-6 days'));
$to = preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['to'] ?? '') ? $_GET['to'] : $today;
if ($to < $from) {
    [$from, $to] = [$to, $from];
}
$gid = (int) ($_GET['group'] ?? 0);

$startTs = strtotime($from . ' 00:00:00');
$endTs = min(strtotime($to . ' 23:59:59'), time());
$rangeSec = max(1, $endTs - $startTs);
$startStr = date('Y-m-d H:i:s', $startTs);
$endStr = date('Y-m-d H:i:s', $endTs);

$devices = Database::all(
    'SELECT d.id, d.name, d.ip_address, d.group_id, g.name AS grp FROM devices d LEFT JOIN `groups` g ON g.id = d.group_id
     WHERE d.is_active = 1' . ($gid ? ' AND d.group_id = ' . $gid : '') . ' ORDER BY g.name, d.name'
);

// Downtime = irisan insiden dengan rentang laporan (insiden berjalan dihitung sampai sekarang).
$down = $count = [];
foreach (Database::all(
    'SELECT device_id, started_at, ended_at FROM incidents WHERE started_at <= ? AND COALESCE(ended_at, ?) >= ?',
    [$endStr, $endStr, $startStr]
) as $i) {
    $s = max(strtotime($i['started_at']), $startTs);
    $e = min($i['ended_at'] ? strtotime($i['ended_at']) : $endTs, $endTs);
    $d = (int) $i['device_id'];
    $down[$d] = ($down[$d] ?? 0) + max(0, $e - $s);
    $count[$d] = ($count[$d] ?? 0) + 1;
}

// Latensi rata-rata tertimbang dari log mentah + agregat per jam.
$lat = [];
foreach (Database::all('SELECT device_id, SUM(latency_ms) s, COUNT(latency_ms) c FROM ping_logs WHERE checked_at BETWEEN ? AND ? GROUP BY device_id', [$startStr, $endStr]) as $r) {
    $lat[$r['device_id']] = ['s' => (float) $r['s'], 'c' => (int) $r['c']];
}
foreach (Database::all('SELECT device_id, SUM(avg_latency * lat_samples) s, SUM(lat_samples) c FROM ping_hourly WHERE hour_start BETWEEN ? AND ? GROUP BY device_id', [$startStr, $endStr]) as $r) {
    $lat[$r['device_id']]['s'] = ($lat[$r['device_id']]['s'] ?? 0) + (float) $r['s'];
    $lat[$r['device_id']]['c'] = ($lat[$r['device_id']]['c'] ?? 0) + (int) $r['c'];
}

$report = [];
$byGroup = [];
foreach ($devices as $d) {
    $id = (int) $d['id'];
    $dt = min($rangeSec, $down[$id] ?? 0);
    $up = 100 * (1 - $dt / $rangeSec);
    $avg = !empty($lat[$id]['c']) ? $lat[$id]['s'] / $lat[$id]['c'] : null;
    $report[] = ['grp' => $d['grp'] ?? '(tanpa grup)', 'name' => $d['name'], 'ip' => $d['ip_address'], 'uptime' => $up, 'inc' => $count[$id] ?? 0, 'down' => $dt, 'lat' => $avg, 'id' => $id];
    $g = $d['grp'] ?? '(tanpa grup)';
    $byGroup[$g]['n'] = ($byGroup[$g]['n'] ?? 0) + 1;
    $byGroup[$g]['up'] = ($byGroup[$g]['up'] ?? 0) + $up;
    $byGroup[$g]['inc'] = ($byGroup[$g]['inc'] ?? 0) + ($count[$id] ?? 0);
    $byGroup[$g]['down'] = ($byGroup[$g]['down'] ?? 0) + $dt;
}

if (isset($_GET['export'])) {
    header('Content-Type: text/csv; charset=utf-8');
    header("Content-Disposition: attachment; filename=\"uptime_{$from}_{$to}.csv\"");
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF"); // BOM agar Excel membaca UTF-8
    fputcsv($out, ['Grup', 'Nama', 'IP', 'Uptime %', 'Jumlah kejadian', 'Total downtime (detik)', 'Latensi rata-rata (ms)']);
    foreach ($report as $r) {
        fputcsv($out, [$r['grp'], $r['name'], $r['ip'], number_format($r['uptime'], 3, '.', ''), $r['inc'], $r['down'], $r['lat'] === null ? '' : number_format($r['lat'], 2, '.', '')]);
    }
    exit;
}

$groups = Database::all('SELECT id, name FROM `groups` ORDER BY name');
$qs = http_build_query(['from' => $from, 'to' => $to, 'group' => $gid ?: '', 'export' => 1]);

layout_header('Laporan uptime', 'report');
?>
<h4>Laporan uptime</h4>
<form method="get" class="d-flex flex-wrap gap-2 align-items-end mb-3">
  <div><label class="form-label small mb-0">Dari</label><input type="date" name="from" value="<?= e($from) ?>" class="form-control form-control-sm"></div>
  <div><label class="form-label small mb-0">Sampai</label><input type="date" name="to" value="<?= e($to) ?>" class="form-control form-control-sm"></div>
  <div><label class="form-label small mb-0">Grup</label>
    <select name="group" class="form-select form-select-sm"><option value="0">Semua</option>
      <?php foreach ($groups as $g): ?><option value="<?= (int) $g['id'] ?>" <?= $gid === (int) $g['id'] ? 'selected' : '' ?>><?= e($g['name']) ?></option><?php endforeach; ?>
    </select></div>
  <button class="btn btn-sm btn-primary">Tampilkan</button>
  <a class="btn btn-sm btn-outline-secondary" href="?<?= e($qs) ?>">Ekspor CSV</a>
</form>

<div class="card mb-3">
  <div class="card-header py-2">Per grup</div>
  <table class="table table-sm mb-0">
    <thead><tr><th>Grup</th><th class="text-end">Perangkat</th><th class="text-end">Uptime rata-rata</th><th class="text-end">Kejadian</th><th class="text-end">Total downtime</th></tr></thead>
    <tbody>
    <?php foreach ($byGroup as $name => $g): ?>
      <tr><td><?= e($name) ?></td><td class="text-end"><?= $g['n'] ?></td><td class="text-end"><?= number_format($g['up'] / $g['n'], 3) ?>%</td><td class="text-end"><?= $g['inc'] ?></td><td class="text-end"><?= e(fmt_duration($g['down'])) ?: '0d' ?></td></tr>
    <?php endforeach; ?>
    <?php if (!$byGroup): ?><tr><td colspan="5" class="text-muted">Tidak ada perangkat.</td></tr><?php endif; ?>
    </tbody>
  </table>
</div>

<div class="card">
  <div class="card-header py-2">Per perangkat</div>
  <table class="table table-sm table-hover mb-0 dev-table">
    <thead><tr><th>Grup</th><th>Nama</th><th>IP</th><th class="text-end">Uptime</th><th class="text-end">Kejadian</th><th class="text-end">Downtime</th><th class="text-end">Latensi rata-rata</th></tr></thead>
    <tbody>
    <?php foreach ($report as $r): ?>
      <tr>
        <td><?= e($r['grp']) ?></td>
        <td><a href="device.php?id=<?= $r['id'] ?>" class="text-decoration-none"><?= e($r['name']) ?></a></td>
        <td class="ip"><?= e($r['ip']) ?></td>
        <td class="text-end <?= $r['uptime'] < 99 ? 'text-danger fw-bold' : '' ?>"><?= number_format($r['uptime'], 3) ?>%</td>
        <td class="text-end"><?= $r['inc'] ?></td>
        <td class="text-end"><?= e(fmt_duration($r['down'])) ?: '0d' ?></td>
        <td class="text-end"><?= $r['lat'] === null ? '–' : number_format($r['lat'], 1) . ' ms' ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>
<p class="small text-muted mt-2">Uptime dihitung dari total durasi kejadian down dibanding rentang laporan (<?= e($startStr) ?> – <?= e($endStr) ?>).</p>
<?php layout_footer();
