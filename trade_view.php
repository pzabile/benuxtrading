<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';
$user = require_login();

$id = (int)($_GET['id'] ?? 0);
$st = db()->prepare('SELECT * FROM trades WHERE id=? AND user_id=?');
$st->execute([$id, $user['id']]);
$t = $st->fetch();
if (!$t) { http_response_code(404); exit('Trade not found.'); }

$shotSt = db()->prepare('SELECT * FROM screenshots WHERE trade_id=? ORDER BY id');
$shotSt->execute([$id]);
$shots = $shotSt->fetchAll();

$title = 'Trade #' . $id;
$page  = 'trades';
require __DIR__ . '/includes/header.php';
?>
<div class="card">
  <div class="row-between">
    <h1>Trade #<?= (int)$t['id'] ?> &middot; <?= e($t['pair']) ?> <?= e($t['direction']) ?></h1>
    <div>
      <a href="edit_trade.php?id=<?= (int)$t['id'] ?>" class="btn btn-primary">Edit</a>
      <form method="post" action="delete_trade.php" style="display:inline" onsubmit="return confirm('Delete this trade?');">
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="id" value="<?= (int)$t['id'] ?>">
        <button class="btn btn-ghost">Delete</button>
      </form>
    </div>
  </div>

  <div class="kpis">
    <div class="kpi"><span>Outcome</span><strong class="pill pill-<?= strtolower($t['outcome']) ?>"><?= e($t['outcome']) ?></strong></div>
    <div class="kpi"><span>P&amp;L</span><strong class="<?= ((float)$t['pnl'])>=0?'pos':'neg' ?>"><?= $t['pnl']!==null?money((float)$t['pnl']):'-' ?></strong></div>
    <div class="kpi"><span>Pips</span><strong><?= $t['pnl_pips']!==null?e($t['pnl_pips']):'-' ?></strong></div>
    <div class="kpi"><span>R:R planned</span><strong><?= $t['rr_planned']!==null?e($t['rr_planned']):'-' ?></strong></div>
    <div class="kpi"><span>R:R actual</span><strong><?= $t['rr_actual']!==null?e($t['rr_actual']):'-' ?></strong></div>
    <div class="kpi"><span>Risk</span><strong><?= $t['risk_pct']!==null?e($t['risk_pct']).'%':'-' ?></strong></div>
    <div class="kpi"><span>Session</span><strong><?= e($t['session']) ?></strong></div>
  </div>

  <div class="grid-2">
    <div>
      <h3>Levels</h3>
      <table class="kv">
        <tr><th>Lot size</th><td><?= e($t['lot_size']) ?></td></tr>
        <tr><th>Entry</th><td><?= e($t['entry_price']) ?></td></tr>
        <tr><th>Stop loss</th><td><?= e($t['stop_loss']??'-') ?></td></tr>
        <tr><th>Take profit</th><td><?= e($t['take_profit']??'-') ?></td></tr>
        <tr><th>Exit</th><td><?= e($t['exit_price']??'-') ?></td></tr>
        <tr><th>Fees</th><td><?= e($t['fees']) ?></td></tr>
        <tr><th>Open</th><td><?= e($t['open_time']) ?></td></tr>
        <tr><th>Close</th><td><?= e($t['close_time']??'-') ?></td></tr>
      </table>
    </div>
    <div>
      <h3>Context</h3>
      <table class="kv">
        <tr><th>Strategy</th><td><?= e($t['strategy']??'-') ?></td></tr>
        <tr><th>Setup</th><td><?= e($t['setup']??'-') ?></td></tr>
        <tr><th>Tags</th><td><?= e($t['tags']??'-') ?></td></tr>
        <tr><th>Emotion</th><td><?= e($t['emotion']??'-') ?></td></tr>
        <tr><th>Confidence</th><td><?= e($t['confidence']??'-') ?></td></tr>
      </table>
      <h3>Notes</h3>
      <p><?= nl2br(e($t['notes']??'-')) ?></p>
      <h3>Mistakes / lessons</h3>
      <p><?= nl2br(e($t['mistakes']??'-')) ?></p>
    </div>
  </div>

  <h3>Screenshots</h3>
  <?php if (!$shots): ?>
    <p class="muted">No screenshots yet.</p>
  <?php else: ?>
    <div class="shot-grid">
      <?php foreach ($shots as $s):
        $src = $s['file_path'] ?: $s['url']; ?>
        <a class="shot" href="<?= e($src) ?>" target="_blank" rel="noopener">
          <?php if ($s['file_path']): ?>
            <img src="<?= e($s['file_path']) ?>" alt="<?= e($s['caption']??'') ?>">
          <?php else: ?>
            <span class="shot-link">&#128279; <?= e($s['url']) ?></span>
          <?php endif; ?>
        </a>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>
<?php require __DIR__ . '/includes/footer.php';
