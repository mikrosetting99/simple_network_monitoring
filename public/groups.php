<?php
declare(strict_types=1);
require __DIR__ . '/_layout.php';

if (is_post()) {
    csrf_verify();
    $action = $_POST['action'] ?? '';
    $name = trim((string) ($_POST['name'] ?? ''));
    $gid = (int) ($_POST['id'] ?? 0);

    if ($action === 'add' && $name !== '') {
        Database::query('INSERT INTO `groups` (name) VALUES (?)', [mb_substr($name, 0, 100)]);
        flash('Grup ditambahkan.');
    } elseif ($action === 'rename' && $name !== '' && $gid) {
        Database::query('UPDATE `groups` SET name = ? WHERE id = ?', [mb_substr($name, 0, 100), $gid]);
        flash('Nama grup diubah.');
    } elseif ($action === 'delete' && $gid) {
        Database::query('DELETE FROM `groups` WHERE id = ?', [$gid]); // perangkat jadi tanpa grup (ON DELETE SET NULL)
        flash('Grup dihapus; perangkat di dalamnya menjadi tanpa grup.');
    }
    redirect('groups.php');
}

$groups = Database::all(
    'SELECT g.id, g.name, COUNT(d.id) AS n FROM `groups` g LEFT JOIN devices d ON d.group_id = g.id GROUP BY g.id, g.name ORDER BY g.name'
);

layout_header('Kelola grup', 'groups');
?>
<h4>Grup perangkat</h4>
<form method="post" class="d-flex gap-2 mb-3" style="max-width:480px">
  <?= csrf_field() ?><input type="hidden" name="action" value="add">
  <input name="name" class="form-control form-control-sm" placeholder="Nama grup baru" required maxlength="100">
  <button class="btn btn-sm btn-primary text-nowrap">+ Tambah</button>
</form>
<div class="card" style="max-width:720px">
  <table class="table table-sm mb-0 align-middle">
    <thead><tr><th>Nama</th><th class="text-end">Perangkat</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($groups as $g): ?>
      <tr>
        <td>
          <form method="post" class="d-flex gap-2">
            <?= csrf_field() ?><input type="hidden" name="action" value="rename"><input type="hidden" name="id" value="<?= (int) $g['id'] ?>">
            <input name="name" value="<?= e($g['name']) ?>" class="form-control form-control-sm" maxlength="100">
            <button class="btn btn-sm btn-outline-secondary">Ubah</button>
          </form>
        </td>
        <td class="text-end"><?= (int) $g['n'] ?></td>
        <td class="text-end">
          <form method="post" onsubmit="return confirm('Hapus grup ini? Perangkatnya tidak ikut terhapus.')">
            <?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int) $g['id'] ?>">
            <button class="btn btn-sm btn-link text-danger py-0">Hapus</button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$groups): ?><tr><td colspan="3" class="text-muted">Belum ada grup.</td></tr><?php endif; ?>
    </tbody>
  </table>
</div>
<?php layout_footer();
