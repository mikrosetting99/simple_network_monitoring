<?php
declare(strict_types=1);
require __DIR__ . '/_layout.php';

$id = (int) ($_GET['id'] ?? 0);
$device = Database::one('SELECT d.*, g.name AS grp FROM devices d LEFT JOIN `groups` g ON g.id = d.group_id WHERE d.id = ?', [$id]);
if (!$device) {
    http_response_code(404);
    exit('Perangkat tidak ditemukan.');
}

if (is_post()) {
    csrf_verify();
    if (($_POST['action'] ?? '') === 'maint') {
        $min = (int) ($_POST['minutes'] ?? 0);
        Database::query(
            'UPDATE devices SET maintenance_until = ? WHERE id = ?',
            [$min > 0 ? date('Y-m-d H:i:s', time() + $min * 60) : null, $id]
        );
        flash($min > 0 ? "Mode maintenance aktif selama $min menit." : 'Mode maintenance dimatikan.');
    }
    redirect('device.php?id=' . $id);
}

// Membuka detail = kejadian perangkat ini dianggap sudah dilihat.
Database::query('UPDATE incidents SET seen_at = ? WHERE device_id = ? AND seen_at IS NULL', [date('Y-m-d H:i:s'), $id]);

$days = in_array((int) ($_GET['days'] ?? 7), [7, 30], true) ? (int) $_GET['days'] : 7;
$incidents = Database::all(
    'SELECT * FROM incidents WHERE device_id = ? AND started_at >= ? ORDER BY started_at DESC',
    [$id, date('Y-m-d H:i:s', strtotime("-$days days"))]
);

$now = date('Y-m-d H:i:s');
$inMaint = $device['maintenance_until'] !== null && $device['maintenance_until'] > $now;
$st = $inMaint ? 'maint' : $device['current_status'];
$stLabel = ['up' => 'HIDUP', 'down' => 'MATI', 'unknown' => '—', 'maint' => 'MAINT'][$st];

layout_header($device['name'], 'devices');
?>
<div class="d-flex flex-wrap align-items-center gap-2 mb-3">
  <a href="index.php" class="btn btn-sm btn-outline-secondary">&larr; Dashboard</a>
  <h4 class="mb-0"><?= e($device['name']) ?></h4>
  <span class="badge-st <?= $st ?>"><?= $stLabel ?></span>
  <span class="mono text-muted"><?= e($device['ip_address']) ?></span>
  <div class="ms-auto d-flex gap-2">
    <button id="ping-now" class="btn btn-sm btn-primary" data-id="<?= $id ?>">Ping sekarang</button>
    <a href="device_form.php?id=<?= $id ?>" class="btn btn-sm btn-outline-secondary">Edit</a>
  </div>
</div>
<div id="ping-result" class="alert alert-info py-2 d-none"></div>

<div class="row g-3">
  <div class="col-lg-9">
    <div class="card mb-3"><div class="card-header py-2">Latensi 24 jam terakhir</div><div class="card-body"><div style="height:280px"><canvas id="chart"></canvas></div></div></div>

    <div class="card">
      <div class="card-header py-2 d-flex justify-content-between">
        <span>Kejadian down (<?= $days ?> hari)</span>
        <span class="small"><a href="?id=<?= $id ?>&days=7">7 hari</a> · <a href="?id=<?= $id ?>&days=30">30 hari</a></span>
      </div>
      <table class="table table-sm mb-0">
        <thead><tr><th>Mulai</th><th>Selesai</th><th>Durasi</th></tr></thead>
        <tbody>
        <?php foreach ($incidents as $i): ?>
          <tr>
            <td><?= e($i['started_at']) ?></td>
            <td><?= $i['ended_at'] ? e($i['ended_at']) : '<span class="text-danger fw-bold">masih down</span>' ?></td>
            <td><?= $i['ended_at'] ? e(fmt_duration((int) $i['duration_sec'])) : e(fmt_duration(time() - strtotime($i['started_at']))) ?></td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$incidents): ?><tr><td colspan="3" class="text-muted">Tidak ada kejadian down.</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <div class="col-lg-3">
    <div class="card mb-3">
      <div class="card-header py-2">Info</div>
      <ul class="list-group list-group-flush small">
        <li class="list-group-item d-flex justify-content-between"><span>Grup</span><span><?= e($device['grp'] ?? '–') ?></span></li>
        <li class="list-group-item d-flex justify-content-between"><span>Interval</span><span><?= (int) $device['interval_sec'] ?> d</span></li>
        <li class="list-group-item d-flex justify-content-between"><span>Ambang gagal</span><span><?= (int) $device['fail_threshold'] ?>×</span></li>
        <li class="list-group-item d-flex justify-content-between"><span>Latensi terakhir</span><span><?= $device['last_latency_ms'] !== null ? e($device['last_latency_ms']) . ' ms' : '–' ?></span></li>
        <li class="list-group-item d-flex justify-content-between"><span>Dicek terakhir</span><span><?= e($device['last_checked_at'] ?? '–') ?></span></li>
        <?php if ($device['down_since']): ?>
        <li class="list-group-item d-flex justify-content-between"><span>Mati sejak</span><span class="text-danger"><?= e($device['down_since']) ?></span></li>
        <?php endif; ?>
        <li class="list-group-item d-flex justify-content-between"><span>Aktif</span><span><?= $device['is_active'] ? 'ya' : 'tidak' ?></span></li>
      </ul>
    </div>
    <div class="card">
      <div class="card-header py-2">Maintenance</div>
      <div class="card-body small">
        <?php if ($inMaint): ?>
          <p class="mb-2">Aktif sampai <strong><?= e($device['maintenance_until']) ?></strong>. Tidak ada insiden baru selama masa ini.</p>
          <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="maint"><input type="hidden" name="minutes" value="0"><button class="btn btn-sm btn-outline-secondary">Matikan</button></form>
        <?php else: ?>
          <form method="post" class="d-flex gap-2"><?= csrf_field() ?><input type="hidden" name="action" value="maint">
            <select name="minutes" class="form-select form-select-sm"><option value="30">30 menit</option><option value="60">1 jam</option><option value="240">4 jam</option><option value="1440">24 jam</option></select>
            <button class="btn btn-sm btn-outline-secondary">Aktifkan</button>
          </form>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>
<?php
$csrf = e(csrf_token());
layout_footer(<<<HTML
<script src="assets/vendor/chart.umd.js"></script>
<script>
(function () {
  var id = $id;
  fetch('api/history.php?id=' + id + '&hours=24', { cache: 'no-store' }).then(function (r) { return r.json(); }).then(function (rows) {
    new Chart(document.getElementById('chart'), {
      type: 'line',
      data: {
        datasets: [
          { label: 'Latensi (ms)', data: rows.map(function (r) { return { x: r.t, y: r.latency }; }), borderColor: '#0d6efd', backgroundColor: '#0d6efd', borderWidth: 1.5, pointRadius: 0, spanGaps: false },
          { label: 'Down', type: 'scatter', data: rows.filter(function (r) { return !r.up; }).map(function (r) { return { x: r.t, y: 0 }; }), backgroundColor: '#dc3545', pointRadius: 3 }
        ]
      },
      options: {
        maintainAspectRatio: false, animation: false,
        scales: {
          x: { type: 'linear', ticks: { maxTicksLimit: 8, callback: function (v) { return new Date(v).toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' }); } } },
          y: { beginAtZero: true, title: { display: true, text: 'ms' } }
        },
        plugins: { tooltip: { callbacks: { title: function (i) { return new Date(i[0].parsed.x).toLocaleString('id-ID'); } } } }
      }
    });
  });

  var btn = document.getElementById('ping-now'), out = document.getElementById('ping-result');
  btn.addEventListener('click', function () {
    btn.disabled = true; btn.textContent = 'Mengecek...';
    var body = new URLSearchParams({ id: id, csrf: '$csrf' });
    fetch('api/ping_now.php', { method: 'POST', body: body }).then(function (r) { return r.json(); }).then(function (r) {
      out.classList.remove('d-none');
      out.className = 'alert py-2 alert-' + (r.error ? 'danger' : r.up ? 'success' : 'warning');
      out.textContent = r.error ? r.error : (r.up ? 'Membalas, latensi ' + (r.latency === null ? '–' : r.latency + ' ms') + ', packet loss ' + r.loss + '%' : 'Tidak membalas (packet loss ' + r.loss + '%)');
    }).catch(function () { out.className = 'alert alert-danger py-2'; out.textContent = 'Gagal menghubungi server.'; })
      .finally(function () { btn.disabled = false; btn.textContent = 'Ping sekarang'; });
  });
})();
</script>
HTML);
