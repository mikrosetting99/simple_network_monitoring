<?php
declare(strict_types=1);
require __DIR__ . '/_layout.php';

$groups = Database::all('SELECT id, name FROM `groups` ORDER BY name');
$unseen = (int) Database::value('SELECT COUNT(*) FROM incidents WHERE seen_at IS NULL');

layout_header('Dashboard', 'dashboard');
?>
<div class="row g-2 mb-3">
  <div class="col-6 col-md-3"><div class="card stat-card down" data-filter="down"><div class="card-body py-2"><div class="text-muted small">Mati</div><div class="num text-danger" id="n-down">–</div></div></div></div>
  <div class="col-6 col-md-3"><div class="card stat-card up" data-filter="up"><div class="card-body py-2"><div class="text-muted small">Hidup</div><div class="num text-success" id="n-up">–</div></div></div></div>
  <div class="col-6 col-md-3"><div class="card stat-card" data-filter="unknown"><div class="card-body py-2"><div class="text-muted small">Belum dicek</div><div class="num" id="n-unknown">–</div></div></div></div>
  <div class="col-6 col-md-3"><a href="incidents.php" class="text-decoration-none"><div class="card stat-card"><div class="card-body py-2"><div class="text-muted small">Kejadian belum dilihat</div><div class="num text-dark"><?= $unseen ?></div></div></div></a></div>
</div>

<div class="d-flex flex-wrap gap-2 align-items-center mb-2">
  <input type="search" id="q" class="form-control form-control-sm w-auto" placeholder="Cari nama atau IP" autocomplete="off">
  <select id="grp" class="form-select form-select-sm w-auto">
    <option value="">Semua grup</option>
    <?php foreach ($groups as $g): ?><option value="<?= (int) $g['id'] ?>"><?= e($g['name']) ?></option><?php endforeach; ?>
  </select>
  <button type="button" id="only-down" class="btn btn-sm btn-outline-danger">Hanya yang mati</button>
  <button type="button" id="sound" class="btn btn-sm btn-outline-secondary d-none">Suara: mati</button>
  <span class="ms-auto small text-muted" id="updated"></span>
</div>

<div class="card">
  <table class="table table-sm table-hover dev-table">
    <thead><tr><th style="width:6rem">Status</th><th>Nama</th><th>IP</th><th class="text-end">Latensi</th><th class="text-end">Lama mati</th></tr></thead>
    <tbody id="rows"></tbody>
  </table>
  <div class="card-footer d-flex justify-content-between align-items-center py-1">
    <span class="small text-muted" id="count"></span>
    <div class="btn-group btn-group-sm"><button class="btn btn-outline-secondary" id="prev">&laquo;</button><span class="btn btn-outline-secondary disabled" id="page">1</span><button class="btn btn-outline-secondary" id="next">&raquo;</button></div>
  </div>
</div>
<?php layout_footer('<script src="assets/app.js"></script>');
