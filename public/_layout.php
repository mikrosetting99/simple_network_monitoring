<?php
declare(strict_types=1);
require_once __DIR__ . '/../app/bootstrap.php';

function layout_header(string $title, string $active = ''): void
{
    $nav = [
        'dashboard' => ['index.php', 'Dashboard'],
        'incidents' => ['incidents.php', 'Kejadian'],
        'devices'   => ['devices.php', 'Perangkat'],
        'groups'    => ['groups.php', 'Grup'],
        'report'    => ['report.php', 'Laporan'],
        'settings'  => ['settings.php', 'Pengaturan'],
    ];
    $health = worker_health();
    $flash = flash();
    ?>
<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($title) ?> - Monitoring Ping</title>
<link href="assets/vendor/bootstrap.min.css" rel="stylesheet">
<link href="assets/app.css" rel="stylesheet">
</head>
<body>
<nav class="navbar navbar-expand-md navbar-dark bg-dark">
  <div class="container-fluid">
    <a class="navbar-brand" href="index.php">Monitoring Ping</a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#nav"><span class="navbar-toggler-icon"></span></button>
    <div class="collapse navbar-collapse" id="nav">
      <ul class="navbar-nav me-auto">
        <?php foreach ($nav as $key => [$href, $label]): ?>
          <li class="nav-item"><a class="nav-link <?= $key === $active ? 'active' : '' ?>" href="<?= $href ?>"><?= $label ?></a></li>
        <?php endforeach; ?>
      </ul>
      <span class="navbar-text small <?= $health['ok'] ? 'text-secondary' : 'text-warning fw-bold' ?>" id="worker-health"><?= e($health['text']) ?><?= $health['ok'] ? '' : ' — data mungkin basi' ?></span>
    </div>
  </div>
</nav>
<main class="container-fluid py-3">
<?php if ($flash): ?>
  <div class="alert alert-<?= e($flash['type']) ?> py-2"><?= e($flash['msg']) ?></div>
<?php endif;
}

function layout_footer(string $extraScript = ''): void
{
    ?>
</main>
<script src="assets/vendor/bootstrap.bundle.min.js"></script>
<?= $extraScript ?>
</body>
</html>
<?php
}
