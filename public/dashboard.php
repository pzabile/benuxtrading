<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';
$user = require_login();

$st = db()->prepare('SELECT * FROM trades WHERE user_id=? ORDER BY open_time');
$st->execute([$user['id']]);
$rows = $st->fetchAll();
$s = compute_stats($rows, (float)$user['starting_balance']);

$recent = array_slice(array_reverse($rows), 0, 8);

$title = 'Dashboard';
$page  = 'dashboard';
require __DIR__ . '/../includes/header.php';
?>
<div class="hero">
  <div>
    <h1>Welcome back, <?= e($user['username']) ?></h1>
    <p class="muted">Snapshot of your trading performance.</p>
  </div>
  <div class="hero-actions">
    <a href="add_trade.php" class="btn btn-primary">+ Log a trade</a>
    <a href="analytics.php" class="btn btn-ghost">Deep analytics</a>
  </div>
</div>

<div class="kpi-grid">
  <div class="kpi"><span>Total P&amp;L</span><strong class="<?= $s['total_pnl']>=0?'pos':'neg' ?>"><?= money($s['total_pnl']) ?></strong></div>
  <div class="kpi"><span>Equity</span><strong><?= money($s['final_equity']) ?></strong></div>
  <div class="kpi"><span>Win rate</span><strong><?= e($s['win_rate']) ?>%</strong></div>
  <div class="kpi"><span>Profit factor</span><strong><?= e($s['profit_factor']) ?></strong></div>
  <div class="kpi"><span>Expectancy</span><strong><?= money($s['expectancy']) ?></strong></div>
  <div class="kpi"><span>Max drawdown</span><strong class="neg">-<?= money($s['max_drawdown']) ?></strong></div>
  <div class="kpi"><span>Trades</span><strong><?= (int)$s['count_total'] ?></strong></div>
  <div class="kpi"><span>Open</span><strong><?= (int)$s['count_open'] ?></strong></div>
  <div class="kpi"><span>Avg R:R</span><strong><?= e($s['avg_rr']) ?></strong></div>
  <div class="kpi"><span>Avg win</span><strong class="pos"><?= money($s['avg_win']) ?></strong></div>
  <div class="kpi"><span>Avg loss</span><strong class="neg"><?= money($s['avg_loss']) ?></strong></div>
  <div class="kpi"><span>Streak W / L</span><strong><?= (int)$s['max_streak_win'] ?> / <?= (int)$s['max_streak_loss'] ?></strong></div>
</div>

<div class="grid-2">
  <div class="card">
    <h2>Equity curve</h2>
    <canvas id="equityChart" height="120"></canvas>
  </div>
  <div class="card">
    <h2>P&amp;L by pair</h2>
    <canvas id="pairChart" height="120"></canvas>
  </div>
</div>

<div class="grid-2">
  <div class="card">
    <h2>P&amp;L by hour (open time)</h2>
    <canvas id="hourChart" height="120"></canvas>
  </div>
  <div class="card">
    <h2>P&amp;L by session</h2>
    <canvas id="sessionChart" height="120"></canvas>
  </div>
</div>

<div class="card">
  <div class="row-between"><h2>Recent trades</h2><a href="trades.php">All trades &rarr;</a></div>
  <?php if (!$recent): ?>
    <p class="muted">No trades yet. <a href="add_trade.php">Log your first one.</a></p>
  <?php else: ?>
    <table class="trades">
      <thead><tr><th>#</th><th>Open</th><th>Pair</th><th>Dir</th><th>Pips</th><th>P&amp;L</th><th>Out</th><th>Strategy</th></tr></thead>
      <tbody>
        <?php foreach ($recent as $r): ?>
        <tr class="<?= ((float)($r['pnl']??0))>0?'win':(((float)($r['pnl']??0))<0?'loss':'') ?>">
          <td><a href="trade_view.php?id=<?= (int)$r['id'] ?>">#<?= (int)$r['id'] ?></a></td>
          <td><?= e(substr($r['open_time'],0,16)) ?></td>
          <td><strong><?= e($r['pair']) ?></strong></td>
          <td><?= e($r['direction']) ?></td>
          <td><?= $r['pnl_pips']!==null ? e($r['pnl_pips']) : '-' ?></td>
          <td><?= $r['pnl']!==null ? money((float)$r['pnl']) : '-' ?></td>
          <td><span class="pill pill-<?= strtolower($r['outcome']) ?>"><?= e($r['outcome']) ?></span></td>
          <td><?= e($r['strategy']) ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>

<script id="benux-data" type="application/json"><?= json_encode([
    'equity'   => $s['equity_curve'],
    'pairs'    => $s['by_pair'],
    'hours'    => $s['by_hour'],
    'sessions' => $s['by_session'],
    'days'     => $s['by_day'],
    'strategy' => $s['by_strategy'],
], JSON_UNESCAPED_SLASHES) ?></script>
<script src="assets/js/dashboard.js" defer></script>
<?php require __DIR__ . '/../includes/footer.php';
