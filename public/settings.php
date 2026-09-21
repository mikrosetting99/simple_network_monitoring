<?php
declare(strict_types=1);
require __DIR__ . '/_layout.php';

$testResult = null;

if (is_post()) {
    csrf_verify();
    $action = $_POST['action'] ?? 'save';

    if ($action === 'test') {
        $testResult = PingService::canRun() ? PingService::checkOne('127.0.0.1') : ['error' => 'proc_open dinonaktifkan'];
    } else {
        Settings::set('default_interval', (string) min(3600, max(10, (int) $_POST['default_interval'])));
        Settings::set('default_threshold', (string) min(20, max(1, (int) $_POST['default_threshold'])));
        Settings::set('retention_days', (string) min(365, max(1, (int) $_POST['retention_days'])));
        Settings::set('sound_alert', isset($_POST['sound_alert']) ? '1' : '0');
        flash('Pengaturan disimpan.');
        redirect('settings.php');
    }
}

$s = Settings::all();
$canRun = PingService::canRun();
$hasFping = !PingService::isWindows() && trim((string) @shell_exec('command -v fping 2>/dev/null')) !== '';
$health = worker_health();

layout_header('Pengaturan', 'settings');
?>
<h4>Pengaturan</h4>
<div class="row g-3">
  <div class="col-lg-6">
    <form method="post" class="card card-body">
      <?= csrf_field() ?><input type="hidden" name="action" value="save">
      <div class="row">
        <div class="col-6 mb-3"><label class="form-label">Interval ping default (detik)</label><input type="number" name="default_interval" class="form-control" min="10" max="3600" value="<?= (int) $s['default_interval'] ?>"><div class="form-text">Untuk perangkat baru.</div></div>
        <div class="col-6 mb-3"><label class="form-label">Ambang gagal default</label><input type="number" name="default_threshold" class="form-control" min="1" max="20" value="<?= (int) $s['default_threshold'] ?>"><div class="form-text">Siklus gagal berturut-turut sebelum dianggap down.</div></div>
      </div>
      <div class="mb-3"><label class="form-label">Retensi log mentah (hari)</label><input type="number" name="retention_days" class="form-control" min="1" max="365" value="<?= (int) $s['retention_days'] ?>"><div class="form-text">Sesudahnya diringkas per jam oleh cron/cleanup.php dan disimpan 12 bulan.</div></div>
      <div class="form-check mb-3"><input type="checkbox" class="form-check-input" id="snd" name="sound_alert" <?= $s['sound_alert'] === '1' ? 'checked' : '' ?>><label class="form-check-label" for="snd">Tampilkan tombol suara alert di dashboard</label><div class="form-text">Berbunyi hanya saat dashboard terbuka; tiap browser menyalakannya sendiri lewat tombol di dashboard.</div></div>
      <div><button class="btn btn-primary">Simpan</button></div>
    </form>
  </div>

  <div class="col-lg-6">
    <div class="card">
      <div class="card-header py-2">Diagnostik</div>
      <ul class="list-group list-group-flush small">
        <li class="list-group-item d-flex justify-content-between"><span>PHP</span><span><?= e(PHP_VERSION) ?> (<?= e(PHP_OS_FAMILY) ?>)</span></li>
        <li class="list-group-item d-flex justify-content-between"><span>proc_open (dibutuhkan worker)</span><span class="<?= $canRun ? 'text-success' : 'text-danger fw-bold' ?>"><?= $canRun ? 'tersedia' : 'DINONAKTIFKAN — hapus dari disable_functions di php.ini' ?></span></li>
        <li class="list-group-item d-flex justify-content-between"><span>fping (Linux, opsional)</span><span><?= PingService::isWindows() ? 'n/a' : ($hasFping ? 'terpasang' : 'tidak ada') ?></span></li>
        <li class="list-group-item d-flex justify-content-between"><span>Worker</span><span class="<?= $health['ok'] ? 'text-success' : 'text-danger fw-bold' ?>"><?= e($health['text']) ?></span></li>
        <li class="list-group-item d-flex justify-content-between"><span>Siklus terakhir</span><span><?= $s['last_cycle_at'] !== '' ? e($s['last_cycle_count']) . ' perangkat, ' . e($s['last_cycle_sec']) . ' d' : '–' ?></span></li>
      </ul>
      <div class="card-body">
        <form method="post" class="d-flex align-items-center gap-2"><?= csrf_field() ?><input type="hidden" name="action" value="test">
          <button class="btn btn-sm btn-outline-primary">Jalankan ping uji (127.0.0.1)</button>
          <?php if ($testResult): ?>
            <span class="small <?= !empty($testResult['up']) ? 'text-success' : 'text-danger' ?>">
              <?= isset($testResult['error']) ? e($testResult['error']) : (!empty($testResult['up']) ? 'Berhasil, latensi ' . e($testResult['latency'] ?? '–') . ' ms' : 'Gagal — cek apakah perintah ping bisa dijalankan PHP') ?>
            </span>
          <?php endif; ?>
        </form>
      </div>
    </div>
  </div>
</div>
<?php layout_footer();
