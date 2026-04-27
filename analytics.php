<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';
$user = require_login();

$from = $_GET['from'] ?? '';
$to   = $_GET['to']   ?? '';
$pair = trim($_GET['pair'] ?? '');

$where = ['user_id = ?']; $params = [$user['id']];
if ($from !== '') { $where[] = 'open_time >= ?'; $params[] = $from . ' 00:00:00'; }
if ($to !== '')   { $where[] = 'open_time <= ?'; $params[] = $to   . ' 23:59:59'; }
if ($pair !== '') { $where[] = 'pair = ?';       $params[] = normalize_pair($pair); }

$st = db()->prepare('SELECT * FROM trades WHERE ' . implode(' AND ', $where) . ' ORDER BY open_time');
$st->execute($params);
$rows = $st->fetchAll();
$s = compute_stats($rows, (float)$user['starting_balance']);

$pairsSt = db()->prepare('SELECT DISTINCT pair FROM trades WHERE user_id=? ORDER BY pair');
$pairsSt->execute([$user['id']]);
$pairs = array_column($pairsSt->fetchAll(), 'pair');

$title = 'Analytics';
$page  = 'analytics';
require __DIR__ . '/includes/header.php';
?>
<div class="card">
  <div class="row-between"><h1>Analytics</h1></div>
  <form class="filters" method="get">
    <input type="date" name="from" value="<?= e($from) ?>">
    <input type="date" name="to"   value="<?= e($to) ?>">
    <select name="pair">
      <option value="">All pairs</option>
      <?php foreach ($pairs as $p): ?>
        <option value="<?= e($p) ?>" <?= $pair===$p?'selected':'' ?>><?= e($p) ?></option>
      <?php endforeach; ?>
    </select>
    <button class="btn btn-ghost">Apply</button>
    <a href="analytics.php" class="btn btn-ghost btn-sm">Reset</a>
  </form>
</div>

<div class="kpi-grid">
  <div class="kpi"><span>Net P&amp;L</span><strong class="<?= $s['total_pnl']>=0?'pos':'neg' ?>"><?= money($s['total_pnl']) ?></strong></div>
  <div class="kpi"><span>Trades</span><strong><?= (int)$s['count_closed'] ?></strong></div>
  <div class="kpi"><span>Win / Loss / BE</span><strong><?= (int)$s['count_wins'] ?> / <?= (int)$s['count_losses'] ?> / <?= (int)$s['count_be'] ?></strong></div>
  <div class="kpi"><span>Win rate</span><strong><?= e($s['win_rate']) ?>%</strong></div>
  <div class="kpi"><span>Profit factor</span><strong><?= e($s['profit_factor']) ?></strong></div>
  <div class="kpi"><span>Expectancy</span><strong><?= money($s['expectancy']) ?></strong></div>
  <div class="kpi"><span>Avg win</span><strong class="pos"><?= money($s['avg_win']) ?></strong></div>
  <div class="kpi"><span>Avg loss</span><strong class="neg"><?= money($s['avg_loss']) ?></strong></div>
  <div class="kpi"><span>Best</span><strong class="pos"><?= money((float)$s['best_trade']) ?></strong></div>
  <div class="kpi"><span>Worst</span><strong class="neg"><?= money((float)$s['worst_trade']) ?></strong></div>
  <div class="kpi"><span>Max DD</span><strong class="neg">-<?= money($s['max_drawdown']) ?></strong></div>
  <div class="kpi"><span>Avg R:R</span><strong><?= e($s['avg_rr']) ?></strong></div>
</div>

<div class="grid-2">
  <div class="card"><h2>Equity curve</h2><canvas id="equityChart" height="140"></canvas></div>
  <div class="card"><h2>P&amp;L by pair</h2><canvas id="pairChart" height="140"></canvas></div>
</div>

<div class="grid-2">
  <div class="card"><h2>P&amp;L by hour</h2><canvas id="hourChart" height="140"></canvas></div>
  <div class="card"><h2>P&amp;L by day of week</h2><canvas id="dayChart" height="140"></canvas></div>
</div>

<div class="grid-2">
  <div class="card"><h2>P&amp;L by session</h2><canvas id="sessionChart" height="140"></canvas></div>
  <div class="card"><h2>P&amp;L by strategy</h2><canvas id="strategyChart" height="140"></canvas></div>
</div>

<div class="card">
  <h2>Coaching insights</h2>
  <ul class="insights">
    <?php
    // Generate plain-English suggestions.
    $insights = [];
    if ($s['count_closed'] === 0) {
        $insights[] = 'No closed trades in this range yet — log some trades to get insights.';
    } else {
        if ($s['profit_factor'] < 1)         $insights[] = 'Profit factor is below 1 — your losers are larger or more frequent than your winners. Tighten risk and/or let winners run.';
        if ($s['win_rate'] < 40 && $s['avg_rr'] < 2) $insights[] = 'Low win rate paired with R:R under 2 is unsustainable. Either improve entries or aim for fewer, bigger winners.';
        if (abs($s['avg_loss']) > $s['avg_win'])     $insights[] = 'Average loss is bigger than average win — consider tighter stops or moving to break-even sooner.';
        if ($s['max_streak_loss'] >= 5)              $insights[] = 'You\'ve hit a 5+ trade losing streak. Build a rule: after 3 losses, stop trading for the day and review.';
        // Worst hour
        $worstHour = null; $worstHourPnl = 0;
        foreach ($s['by_hour'] as $h => $p) if ($p < $worstHourPnl) { $worstHourPnl = $p; $worstHour = $h; }
        if ($worstHour !== null) $insights[] = 'Worst trading hour: ' . $worstHour . ':00 (' . money($worstHourPnl) . '). Consider avoiding it.';
        // Best pair
        if ($s['by_pair']) {
            arsort($s['by_pair']);
            $bestPair = array_key_first($s['by_pair']);
            $insights[] = 'Best pair: ' . $bestPair . ' (' . money($s['by_pair'][$bestPair]) . '). Lean into what works.';
        }
        if ($s['by_session']) {
            arsort($s['by_session']);
            $bestSession = array_key_first($s['by_session']);
            $insights[] = 'Best session: ' . $bestSession . '.';
        }
    }
    foreach ($insights as $i) echo '<li>' . e($i) . '</li>';
    ?>
  </ul>
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
<?php require __DIR__ . '/includes/footer.php';
