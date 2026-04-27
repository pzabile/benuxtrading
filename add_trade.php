<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';
$user = require_login();

$cfg = require __DIR__ . '/includes/config.php';
$err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    try {
        $pair      = normalize_pair($_POST['pair'] ?? '');
        $direction = ($_POST['direction'] ?? 'BUY') === 'SELL' ? 'SELL' : 'BUY';
        $lot       = (float)($_POST['lot_size'] ?? 0);
        $entry     = (float)($_POST['entry_price'] ?? 0);
        $exit      = $_POST['exit_price'] !== '' ? (float)$_POST['exit_price'] : null;
        $sl        = $_POST['stop_loss']   !== '' ? (float)$_POST['stop_loss']   : null;
        $tp        = $_POST['take_profit'] !== '' ? (float)$_POST['take_profit'] : null;
        $fees      = (float)($_POST['fees'] ?? 0);
        $risk      = $_POST['risk_pct'] !== '' ? (float)$_POST['risk_pct'] : null;
        $outcome   = strtoupper($_POST['outcome'] ?? 'OPEN');
        if (!in_array($outcome, ['OPEN','TP','SL','BE','MANUAL'], true)) $outcome = 'OPEN';
        $strategy  = trim($_POST['strategy'] ?? '');
        $setup     = trim($_POST['setup'] ?? '');
        $tags      = trim($_POST['tags'] ?? '');
        $emotion   = $_POST['emotion']    !== '' ? (int)$_POST['emotion']    : null;
        $confidence= $_POST['confidence'] !== '' ? (int)$_POST['confidence'] : null;
        $notes     = trim($_POST['notes'] ?? '');
        $mistakes  = trim($_POST['mistakes'] ?? '');
        $open_t    = $_POST['open_time']  ?: date('Y-m-d H:i');
        $close_t   = $_POST['close_time'] ?: null;

        if ($pair === '' || $entry <= 0 || $lot <= 0) {
            throw new Exception('Pair, entry price and lot size are required.');
        }

        $session = detect_session($open_t, $user['timezone']);

        // If outcome is BE/SL/TP and no exit price, fill it from sl/tp/entry.
        if ($exit === null) {
            if     ($outcome === 'TP' && $tp !== null) $exit = $tp;
            elseif ($outcome === 'SL' && $sl !== null) $exit = $sl;
            elseif ($outcome === 'BE')                 $exit = $entry;
        }

        $pnl = compute_pnl($pair, $direction, $entry, $exit, $lot, $fees);
        $rrP = rr_planned($direction, $entry, $sl, $tp);
        $rrA = ($exit !== null && $sl !== null) ?
            round(abs($exit - $entry) / max(0.0000001, abs($entry - $sl)) * (($pnl['pnl'] ?? 0) >= 0 ? 1 : -1), 2)
            : null;

        $st = db()->prepare(
            'INSERT INTO trades
             (user_id,pair,direction,lot_size,entry_price,exit_price,stop_loss,take_profit,
              pnl,pnl_pips,risk_pct,rr_planned,rr_actual,fees,outcome,session,strategy,setup,
              emotion,confidence,tags,notes,mistakes,open_time,close_time)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)'
        );
        $st->execute([
            $user['id'], $pair, $direction, $lot, $entry, $exit, $sl, $tp,
            $pnl['pnl'], $pnl['pips'], $risk, $rrP, $rrA, $fees, $outcome, $session,
            $strategy ?: null, $setup ?: null,
            $emotion, $confidence, $tags ?: null, $notes ?: null, $mistakes ?: null,
            $open_t, $close_t,
        ]);
        $tradeId = (int)db()->lastInsertId();

        // Screenshot URL(s) -- pasted external links, one per line.
        $links = preg_split('/\r?\n/', trim((string)($_POST['screenshot_urls'] ?? '')));
        foreach ($links as $url) {
            $url = trim($url);
            if ($url === '') continue;
            $u = filter_var($url, FILTER_VALIDATE_URL);
            if (!$u) continue;
            $ins = db()->prepare('INSERT INTO screenshots (trade_id,url,kind) VALUES (?,?,?)');
            $ins->execute([$tradeId, $u, 'OTHER']);
        }

        // File uploads
        if (!empty($_FILES['screenshots']['name'][0])) {
            $names = $_FILES['screenshots']['name'];
            $tmps  = $_FILES['screenshots']['tmp_name'];
            $errs  = $_FILES['screenshots']['error'];
            $sizes = $_FILES['screenshots']['size'];
            if (!is_dir($cfg['upload_dir'])) @mkdir($cfg['upload_dir'], 0775, true);
            $userDir = $cfg['upload_dir'] . '/u' . $user['id'];
            if (!is_dir($userDir)) @mkdir($userDir, 0775, true);
            for ($i = 0; $i < count($names); $i++) {
                if ($errs[$i] !== UPLOAD_ERR_OK) continue;
                if ($sizes[$i] > $cfg['upload_max']) continue;
                $ext = strtolower(pathinfo($names[$i], PATHINFO_EXTENSION));
                if (!in_array($ext, $cfg['allowed_ext'], true)) continue;
                $fname = 't' . $tradeId . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
                $dest  = $userDir . '/' . $fname;
                if (move_uploaded_file($tmps[$i], $dest)) {
                    $rel = $cfg['upload_url'] . '/u' . $user['id'] . '/' . $fname;
                    $ins = db()->prepare('INSERT INTO screenshots (trade_id,file_path,kind) VALUES (?,?,?)');
                    $ins->execute([$tradeId, $rel, 'OTHER']);
                }
            }
        }

        flash('Trade saved.', 'success');
        header('Location: trade_view.php?id=' . $tradeId); exit;
    } catch (Throwable $e) {
        $err = $e->getMessage();
    }
}

$title = 'New Trade';
$page  = 'add';
require __DIR__ . '/includes/header.php';
?>
<div class="card">
  <h1>Log a new trade</h1>
  <p class="muted">Capture entry, exit, risk, and your reasoning. Stats update automatically.</p>
  <?php if ($err): ?><div class="flash flash-error"><?= e($err) ?></div><?php endif; ?>
  <form method="post" enctype="multipart/form-data" class="grid-form">
    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">

    <fieldset><legend>Trade</legend>
      <label>Pair<input name="pair" required placeholder="EURUSD" list="pairs">
        <datalist id="pairs">
          <option>EURUSD</option><option>GBPUSD</option><option>USDJPY</option>
          <option>AUDUSD</option><option>USDCAD</option><option>USDCHF</option>
          <option>NZDUSD</option><option>EURJPY</option><option>GBPJPY</option>
          <option>EURGBP</option><option>XAUUSD</option><option>BTCUSD</option>
        </datalist>
      </label>
      <label>Direction
        <select name="direction"><option>BUY</option><option>SELL</option></select>
      </label>
      <label>Lot size<input type="number" step="0.0001" min="0" name="lot_size" value="0.10" required></label>
      <label>Entry price<input type="number" step="0.00001" name="entry_price" required></label>
      <label>Stop loss<input type="number" step="0.00001" name="stop_loss"></label>
      <label>Take profit<input type="number" step="0.00001" name="take_profit"></label>
      <label>Exit price<input type="number" step="0.00001" name="exit_price"></label>
      <label>Fees / commission<input type="number" step="0.01" name="fees" value="0"></label>
      <label>Risk %<input type="number" step="0.01" name="risk_pct" placeholder="e.g. 1.0"></label>
      <label>Outcome
        <select name="outcome">
          <option value="OPEN">Still open</option>
          <option value="TP">Take Profit</option>
          <option value="SL">Stop Loss</option>
          <option value="BE">Break Even</option>
          <option value="MANUAL">Manual close</option>
        </select>
      </label>
      <label>Open time<input type="datetime-local" name="open_time" value="<?= e(date('Y-m-d\TH:i')) ?>" required></label>
      <label>Close time<input type="datetime-local" name="close_time"></label>
    </fieldset>

    <fieldset><legend>Context</legend>
      <label>Strategy<input name="strategy" placeholder="ICT, SMC, Breakout..."></label>
      <label>Setup<input name="setup" placeholder="London open, FVG retest..."></label>
      <label>Tags<input name="tags" placeholder="news, scalp, htf-trend"></label>
      <label>Emotion (1 calm &mdash; 5 anxious)
        <select name="emotion"><option value="">-</option><?php for($i=1;$i<=5;$i++) echo "<option>$i</option>"; ?></select>
      </label>
      <label>Confidence (1 low &mdash; 5 high)
        <select name="confidence"><option value="">-</option><?php for($i=1;$i<=5;$i++) echo "<option>$i</option>"; ?></select>
      </label>
      <label class="full">Reasoning / Notes<textarea name="notes" rows="3" placeholder="Why did you take this trade?"></textarea></label>
      <label class="full">Mistakes / lessons<textarea name="mistakes" rows="2" placeholder="What would you change?"></textarea></label>
    </fieldset>

    <fieldset><legend>Screenshots</legend>
      <label class="full">External links (one per line)
        <textarea name="screenshot_urls" rows="3" placeholder="https://www.tradingview.com/x/abcd1234/"></textarea>
      </label>
      <label class="full">Upload images
        <input type="file" name="screenshots[]" multiple accept="image/*">
      </label>
    </fieldset>

    <div class="form-actions">
      <button class="btn btn-primary">Save trade</button>
      <a href="trades.php" class="btn btn-ghost">Cancel</a>
    </div>
  </form>
</div>
<?php require __DIR__ . '/includes/footer.php';
