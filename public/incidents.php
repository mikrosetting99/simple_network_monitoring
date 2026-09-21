<?php
declare(strict_types=1);
require __DIR__ . '/_layout.php';

if (is_post()) {
    csrf_verify();
    Database::query('UPDATE incidents SET seen_at = ? WHERE seen_at IS NULL', [date('Y-m-d H:i:s')]);
    flash('Semua kejadian ditandai sudah dilihat.');
    redirect('incidents.php');
}

$rows = Database::all(
    'SELECT i.*, d.name, d.ip_address FROM incidents i JOIN devices d ON d.id = i.device_id
     ORDER BY (i.seen_at IS NULL) DESC, i.started_at DESC LIMIT 200'
);
$unseen = count(array_filter($rows, static fn($r) => $r['seen_at'] === null));

layout_header('Kejadian', 'incidents');
?>
<div class="d-flex align-items-center mb-2">
  <h4 class="mb-0">Kejadian down</h4>
  <span class="ms-2 text-muted small"><?= $unseen ?> belum dilihat · 200 terbaru</span>
  <?php if ($unseen): ?>
    <form method="post" class="ms-auto"><?= csrf_field() ?><button class="btn btn-sm btn-outline-secondary">Tandai semua sudah dilihat</button></form>
  <?php endif; ?>
</div>
<div class="card">
  <table class="table table-sm table-hover mb-0">
    <thead><tr><th>Perangkat</th><th>IP</th><th>Mulai</th><th>Selesai</th><th>Durasi</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($rows as $r): ?>
      <tr>
        <td><a href="device.php?id=<?= (int) $r['device_id'] ?>" class="text-decoration-none"><?= e($r['name']) ?></a></td>
        <td class="mono"><?= e($r['ip_address']) ?></td>
        <td><?= e($r['started_at']) ?></td>
        <td><?= $r['ended_at'] ? e($r['ended_at']) : '<span class="text-danger fw-bold">masih down</span>' ?></td>
        <td><?= e(fmt_duration($r['ended_at'] ? (int) $r['duration_sec'] : time() - strtotime($r['started_at']))) ?></td>
        <td><?= $r['seen_at'] === null ? '<span class="new-flag">baru</span>' : '' ?></td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$rows): ?><tr><td colspan="6" class="text-muted">Belum ada kejadian.</td></tr><?php endif; ?>
    </tbody>
  </table>
</div>
<?php layout_footer();
