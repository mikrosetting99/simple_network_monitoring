<?php
declare(strict_types=1);
require __DIR__ . '/_layout.php';

$id = (int) ($_GET['id'] ?? 0);
$device = $id ? Database::one('SELECT * FROM devices WHERE id = ?', [$id]) : null;
if ($id && !$device) {
    http_response_code(404);
    exit('Perangkat tidak ditemukan.');
}

$groups = Database::all('SELECT id, name FROM `groups` ORDER BY name');
$errors = [];
$form = $device ?? [
    'name' => '', 'ip_address' => '', 'group_id' => '',
    'interval_sec' => Settings::get('default_interval'),
    'fail_threshold' => Settings::get('default_threshold'),
    'is_active' => 1,
];

if (is_post()) {
    csrf_verify();
    $form = [
        'name'           => trim((string) ($_POST['name'] ?? '')),
        'ip_address'     => trim((string) ($_POST['ip_address'] ?? '')),
        'group_id'       => $_POST['group_id'] ?? '',
        'interval_sec'   => (int) ($_POST['interval_sec'] ?? 60),
        'fail_threshold' => (int) ($_POST['fail_threshold'] ?? 3),
        'is_active'      => isset($_POST['is_active']) ? 1 : 0,
    ];
    if ($form['name'] === '' || mb_strlen($form['name']) > 100) {
        $errors[] = 'Nama wajib diisi (maksimal 100 karakter).';
    }
    if (!PingService::isValidTarget($form['ip_address'])) {
        $errors[] = 'IP atau hostname tidak valid.';
    }
    if ($form['interval_sec'] < 10 || $form['interval_sec'] > 3600) {
        $errors[] = 'Interval harus 10–3600 detik.';
    }
    if ($form['fail_threshold'] < 1 || $form['fail_threshold'] > 20) {
        $errors[] = 'Ambang gagal harus 1–20.';
    }

    if (!$errors) {
        $gid = $form['group_id'] === '' ? null : (int) $form['group_id'];
        $params = [$gid, $form['name'], $form['ip_address'], $form['interval_sec'], $form['fail_threshold'], $form['is_active']];
        if ($device) {
            Database::query('UPDATE devices SET group_id = ?, name = ?, ip_address = ?, interval_sec = ?, fail_threshold = ?, is_active = ? WHERE id = ?', [...$params, $id]);
            if (!$form['is_active'] && $device['is_active']) {
                DeviceMonitor::deactivate($id);
            }
            flash('Perangkat diperbarui.');
        } else {
            Database::query('INSERT INTO devices (group_id, name, ip_address, interval_sec, fail_threshold, is_active) VALUES (?, ?, ?, ?, ?, ?)', $params);
            flash('Perangkat ditambahkan.');
        }
        redirect('devices.php');
    }
}

layout_header($device ? 'Edit perangkat' : 'Tambah perangkat', 'devices');
?>
<h4><?= $device ? 'Edit perangkat' : 'Tambah perangkat' ?></h4>
<?php foreach ($errors as $err): ?><div class="alert alert-danger py-2"><?= e($err) ?></div><?php endforeach; ?>
<form method="post" class="card card-body" style="max-width:640px">
  <?= csrf_field() ?>
  <div class="mb-3"><label class="form-label">Nama</label><input name="name" class="form-control" value="<?= e($form['name']) ?>" required maxlength="100"></div>
  <div class="mb-3"><label class="form-label">IP atau hostname</label><input name="ip_address" class="form-control mono" value="<?= e($form['ip_address']) ?>" required></div>
  <div class="mb-3"><label class="form-label">Grup</label>
    <select name="group_id" class="form-select"><option value="">— tanpa grup —</option>
      <?php foreach ($groups as $g): ?><option value="<?= (int) $g['id'] ?>" <?= (string) $form['group_id'] === (string) $g['id'] ? 'selected' : '' ?>><?= e($g['name']) ?></option><?php endforeach; ?>
    </select></div>
  <div class="row">
    <div class="col-6 mb-3"><label class="form-label">Interval ping (detik)</label><input type="number" name="interval_sec" class="form-control" min="10" max="3600" value="<?= (int) $form['interval_sec'] ?>"></div>
    <div class="col-6 mb-3"><label class="form-label">Ambang gagal (siklus berturut-turut)</label><input type="number" name="fail_threshold" class="form-control" min="1" max="20" value="<?= (int) $form['fail_threshold'] ?>"></div>
  </div>
  <div class="form-check mb-3"><input type="checkbox" class="form-check-input" id="act" name="is_active" <?= $form['is_active'] ? 'checked' : '' ?>><label class="form-check-label" for="act">Aktif (dipantau)</label></div>
  <div><button class="btn btn-primary">Simpan</button> <a href="devices.php" class="btn btn-outline-secondary">Batal</a></div>
</form>
<?php layout_footer();
