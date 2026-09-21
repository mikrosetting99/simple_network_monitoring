<?php
declare(strict_types=1);
require __DIR__ . '/_layout.php';

if (is_post()) {
    csrf_verify();
    $action = $_POST['action'] ?? '';

    if ($action === 'delete') {
        Database::query('DELETE FROM devices WHERE id = ?', [(int) ($_POST['id'] ?? 0)]);
        flash('Perangkat dihapus.');
    } elseif ($action === 'import') {
        $upload = $_FILES['file'] ?? null;
        if (!$upload || ($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file($upload['tmp_name'])) {
            flash('Pilih file Excel (.xlsx) atau CSV terlebih dahulu (maksimal ' . ini_get('upload_max_filesize') . ').', 'danger');
            redirect('devices.php');
        }
        try {
            $rows = DeviceImporter::rowsFromUpload($upload['tmp_name'], (string) $upload['name']);
            $r = DeviceImporter::import($rows);
        } catch (Throwable $e) {
            flash('Import gagal: ' . $e->getMessage(), 'danger');
            redirect('devices.php');
        }
        $msg = "Import selesai: {$r['added']} perangkat ditambahkan";
        if ($r['groupsCreated']) {
            $msg .= ", {$r['groupsCreated']} grup baru dibuat";
        }
        if ($r['duplicate']) {
            $msg .= ", {$r['duplicate']} dilewati karena IP sudah terdaftar";
        }
        if ($r['invalid']) {
            $msg .= ", {$r['invalid']} baris tidak valid (nama kosong atau IP/hostname salah) di baris " . implode(', ', array_slice($r['invalidLines'], 0, 10)) . (count($r['invalidLines']) > 10 ? ', ...' : '');
        }
        flash($msg . '.', $r['added'] ? 'success' : 'warning');
    }
    redirect('devices.php');
}

$devices = Database::all(
    'SELECT d.*, g.name AS grp FROM devices d LEFT JOIN `groups` g ON g.id = d.group_id ORDER BY d.name'
);

layout_header('Kelola perangkat', 'devices');
?>
<div class="d-flex flex-wrap gap-2 align-items-center mb-2">
  <h4 class="mb-0">Perangkat</h4>
  <span class="text-muted small"><?= count($devices) ?> terdaftar</span>
  <input type="search" id="filter" class="form-control form-control-sm w-auto ms-3" placeholder="Filter nama atau IP">
  <a href="device_form.php" class="btn btn-sm btn-primary ms-auto">+ Tambah perangkat</a>
  <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="collapse" data-bs-target="#import">Import Excel</button>
</div>

<div class="collapse mb-3" id="import">
  <form method="post" enctype="multipart/form-data" class="card card-body py-2 d-flex flex-row flex-wrap gap-2 align-items-center">
    <?= csrf_field() ?><input type="hidden" name="action" value="import">
    <input type="file" name="file" accept=".xlsx,.csv,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,text/csv" class="form-control form-control-sm w-auto" required>
    <button class="btn btn-sm btn-primary">Import</button>
    <a href="template.php" class="btn btn-sm btn-outline-secondary">Unduh template .xlsx</a>
    <span class="small text-muted w-100">Sheet pertama, kolom A–C: <code>nama</code>, <code>ip</code> (atau hostname), <code>grup</code> (boleh kosong; grup baru dibuat otomatis). Baris header opsional. IP yang sudah terdaftar dilewati. Maks. 5000 baris. File .csv juga diterima.</span>
  </form>
</div>

<div class="card">
  <table class="table table-sm table-hover mb-0 dev-table" id="tbl">
    <thead><tr><th>Nama</th><th>IP / Host</th><th>Grup</th><th class="text-end">Interval</th><th class="text-end">Ambang</th><th>Aktif</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($devices as $d): ?>
      <tr>
        <td><a href="device.php?id=<?= (int) $d['id'] ?>" class="text-decoration-none"><?= e($d['name']) ?></a></td>
        <td class="ip"><?= e($d['ip_address']) ?></td>
        <td><?= e($d['grp'] ?? '–') ?></td>
        <td class="text-end"><?= (int) $d['interval_sec'] ?> d</td>
        <td class="text-end"><?= (int) $d['fail_threshold'] ?>×</td>
        <td><?= $d['is_active'] ? 'ya' : '<span class="text-muted">tidak</span>' ?></td>
        <td class="text-end">
          <a href="device_form.php?id=<?= (int) $d['id'] ?>" class="btn btn-sm btn-link py-0">Edit</a>
          <form method="post" class="d-inline" onsubmit="return confirm('Hapus <?= e(addslashes($d['name'])) ?> beserta seluruh riwayatnya?')">
            <?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int) $d['id'] ?>">
            <button class="btn btn-sm btn-link text-danger py-0">Hapus</button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$devices): ?><tr><td colspan="7" class="text-muted">Belum ada perangkat.</td></tr><?php endif; ?>
    </tbody>
  </table>
</div>
<?php layout_footer(<<<'HTML'
<script>
document.getElementById('filter').addEventListener('input', function (e) {
  var q = e.target.value.toLowerCase();
  document.querySelectorAll('#tbl tbody tr').forEach(function (tr) {
    tr.style.display = tr.textContent.toLowerCase().indexOf(q) === -1 ? 'none' : '';
  });
});
</script>
HTML);
