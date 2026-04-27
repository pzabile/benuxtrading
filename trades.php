<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';
$user = require_login();

// Filters
$pair    = trim($_GET['pair'] ?? '');
$outcome = trim($_GET['outcome'] ?? '');
$from    = trim($_GET['from'] ?? '');
$to      = trim($_GET['to'] ?? '');
$strat   = trim($_GET['strategy'] ?? '');

$where  = ['user_id = ?'];
$params = [$user['id']];

if ($pair !== '')    { $where[] = 'pair = ?';      $params[] = normalize_pair($pair); }
if ($outcome !== '') { $where[] = 'outcome = ?';   $params[] = strtoupper($outcome); }
if ($strat !== '')   { $where[] = 'strategy = ?';  $params[] = $strat; }
if ($from !== '')    { $where[] = 'open_time >= ?'; $params[] = $from . ' 00:00:00'; }
if ($to !== '')      { $where[] = 'open_time <= ?'; $params[] = $to . ' 23:59:59'; }

$sql = 'SELECT * FROM trades WHERE ' . implode(' AND ', $where) . ' ORDER BY open_time DESC LIMIT 500';
$st  = db()->prepare($sql);
$st->execute($params);
$rows = $st->fetchAll();

$pairsSt = db()->prepare('SELECT DISTINCT pair FROM trades WHERE user_id=? ORDER BY pair');
$pairsSt->execute([$user['id']]);
$pairs = array_column($pairsSt->fetchAll(), 'pair');

$stratsSt = db()->prepare('SELECT DISTINCT strategy FROM trades WHERE user_id=? AND strategy IS NOT NULL ORDER BY strategy');
$stratsSt->execute([$user['id']]);
$strats = array_column($stratsSt->fetchAll(), 'strategy');

$title = 'Trades';
$page  = 'trades';
require __DIR__ . '/../includes/header.php';
?>
<div class="card">
  <div class="row-between">
    <h1>Trades</h1>
    <a href="add_trade.php" class="btn btn-primary">+ New trade</a>
  </div>

  <form class="filters" method="get">
    <select name="pair">
      <option value="">All pairs</option>
      <?php foreach ($pairs as $p): ?>
        <option value="<?= e($p) ?>" <?= $pair===$p?'selected':'' ?>><?= e($p) ?></option>
      <?php endforeach; ?>
    </select>
    <select name="outcome">
      <option value="">All outcomes</option>
      <?php foreach (['OPEN','TP','SL','BE','MANUAL'] as $o): ?>
        <option value="<?= $o ?>" <?= $outcome===$o?'selected':'' ?>><?= $o ?></option>
      <?php endforeach; ?>
    </select>
    <select name="strategy">
      <option value="">All strategies</option>
      <?php foreach ($strats as $s): ?>
        <option value="<?= e($s) ?>" <?= $strat===$s?'selected':'' ?>><?= e($s) ?></option>
      <?php endforeach; ?>
    </select>
    <input type="date" name="from" value="<?= e($from) ?>">
    <input type="date" name="to"   value="<?= e($to) ?>">
    <button class="btn btn-ghost">Filter</button>
    <a class="btn btn-ghost btn-sm" href="trades.php">Reset</a>
    <a class="btn btn-ghost btn-sm" href="export.php?<?= e(http_build_query($_GET)) ?>">Export CSV</a>
  </form>

  <?php if (!$rows): ?>
    <p class="muted">No trades yet. <a href="add_trade.php">Log your first trade.</a></p>
  <?php else: ?>
    <div class="table-wrap">
    <table class="trades">
      <thead><tr>
        <th>#</th><th>Open</th><th>Pair</th><th>Dir</th><th>Lot</th>
        <th>Entry</th><th>Exit</th><th>Pips</th><th>P&amp;L</th>
        <th>R:R</th><th>Out</th><th>Session</th><th>Strategy</th><th></th>
      </tr></thead>
      <tbody>
      <?php foreach ($rows as $r): ?>
        <tr class="<?= ((float)($r['pnl']??0))>0?'win':(((float)($r['pnl']??0))<0?'loss':'') ?>">
          <td>#<?= (int)$r['id'] ?></td>
          <td><?= e(substr($r['open_time'],0,16)) ?></td>
          <td><strong><?= e($r['pair']) ?></strong></td>
          <td><?= e($r['direction']) ?></td>
          <td><?= e(rtrim(rtrim((string)$r['lot_size'],'0'),'.')) ?></td>
          <td><?= e($r['entry_price']) ?></td>
          <td><?= $r['exit_price']!==null ? e($r['exit_price']) : '-' ?></td>
          <td><?= $r['pnl_pips']!==null ? e($r['pnl_pips']) : '-' ?></td>
          <td><?= $r['pnl']!==null ? money((float)$r['pnl']) : '-' ?></td>
          <td><?= $r['rr_actual']!==null ? e($r['rr_actual']) : ($r['rr_planned']!==null?'('.e($r['rr_planned']).')':'-') ?></td>
          <td><span class="pill pill-<?= strtolower($r['outcome']) ?>"><?= e($r['outcome']) ?></span></td>
          <td><?= e($r['session']) ?></td>
          <td><?= e($r['strategy']) ?></td>
          <td>
            <a href="trade_view.php?id=<?= (int)$r['id'] ?>" class="btn btn-ghost btn-sm">View</a>
            <a href="edit_trade.php?id=<?= (int)$r['id'] ?>" class="btn btn-ghost btn-sm">Edit</a>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    </div>
  <?php endif; ?>
</div>
<?php require __DIR__ . '/../includes/footer.php';
