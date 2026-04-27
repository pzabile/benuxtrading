<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/helpers.php';
$cfg  = require __DIR__ . '/config.php';
$user = current_user();
$page = $page ?? '';
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= e($cfg['app_name']) ?> &mdash; <?= e($title ?? 'Dashboard') ?></title>
<link rel="icon" type="image/svg+xml" href="assets/img/logo.svg">
<link rel="stylesheet" href="assets/css/style.css">
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js" defer></script>
</head>
<body>
<header class="topbar">
  <a class="brand" href="dashboard.php">
    <img src="assets/img/logo.svg" alt="BENUX" class="brand-logo">
    <span class="brand-name">BENUX<span class="brand-dot">.</span></span>
    <span class="brand-tag">Trading</span>
  </a>
  <?php if ($user): ?>
  <nav class="topnav">
    <a href="dashboard.php"  class="<?= $page==='dashboard'?'active':'' ?>">Dashboard</a>
    <a href="trades.php"     class="<?= $page==='trades'?'active':'' ?>">Trades</a>
    <a href="add_trade.php"  class="<?= $page==='add'?'active':'' ?>">+ New Trade</a>
    <a href="analytics.php"  class="<?= $page==='analytics'?'active':'' ?>">Analytics</a>
    <a href="settings.php"   class="<?= $page==='settings'?'active':'' ?>">Settings</a>
  </nav>
  <div class="user-chip">
    <span><?= e($user['username']) ?></span>
    <a href="logout.php" class="btn btn-ghost btn-sm">Log out</a>
  </div>
  <?php endif; ?>
</header>
<main class="container">
<?php foreach (flash_pop() as $f): ?>
  <div class="flash flash-<?= e($f['type']) ?>"><?= e($f['msg']) ?></div>
<?php endforeach; ?>
