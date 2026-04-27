<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';
$user = require_login();
$cfg  = require __DIR__ . '/../includes/config.php';

$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
$st = db()->prepare('SELECT * FROM trades WHERE id=? AND user_id=?');
$st->execute([$id, $user['id']]);
$t = $st->fetch();
if (!$t) { http_response_code(404); exit('Trade not found.'); }

$err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    try {
        $pair      = normalize_pair($_POST['pair'] ?? $t['pair']);
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
        $open_t    = $_POST['open_time']  ?: $t['open_time'];
        $close_t   = $_POST['close_time'] ?: null;

        if ($exit === null) {
            if     ($outcome === 'TP' && $tp !== null) $exit = $tp;
            elseif ($outcome === 'SL' && $sl !== null) $exit = $sl;
            elseif ($outcome === 'BE')                 $exit = $entry;
        }

        $session = detect_session($open_t, $user['timezone']);
        $pnl = compute_pnl($pair, $direction, $entry, $exit, $lot, $fees);
        $rrP = rr_planned($direction, $entry, $sl, $tp);
        $rrA = ($exit !== null && $sl !== null) ?
            round(abs($exit - $entry) / max(0.0000001, abs($entry - $sl)) * (($pnl['pnl'] ?? 0) >= 0 ? 1 : -1), 2)
            : null;

        $up = db()->prepare(
            'UPDATE trades SET pair=?,direction=?,lot_size=?,entry_price=?,exit_price=?,stop_loss=?,
              take_profit=?,pnl=?,pnl_pips=?,risk_pct=?,rr_planned=?,rr_actual=?,fees=?,outcome=?,session=?,
              strategy=?,setup=?,emotion=?,confidence=?,tags=?,notes=?,mistakes=?,open_time=?,close_time=?
              WHERE id=? AND user_id=?'
        );
        $up->execute([
            $pair, $direction, $lot, $entry, $exit, $sl, $tp,
            $pnl['pnl'], $pnl['pips'], $risk, $rrP, $rrA, $fees, $outcome, $session,
            $strategy ?: null, $setup ?: null,
            $emotion, $confidence, $tags ?: null, $notes ?: null, $mistakes ?: null,
            $open_t, $close_t,
            $id, $user['id'],
        ]);

        // Add new screenshot links
        $links = preg_split('/\r?\n/', trim((string)($_POST['screenshot_urls'] ?? '')));
        foreach ($links as $url) {
            $url = trim($url);
            if ($url === '') continue;
            $u = filter_var($url, FILTER_VALIDATE_URL);
            if (!$u) continue;
            $ins = db()->prepare('INSERT INTO screenshots (trade_id,url,kind) VALUES (?,?,?)');
            $ins->execute([$id, $u, 'OTHER']);
        }
        // Add uploaded screenshots
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
                $fname = 't' . $id . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
                $dest  = $userDir . '/' . $fname;
                if (move_uploaded_file($tmps[$i], $dest)) {
                    $rel = $cfg['upload_url'] . '/u' . $user['id'] . '/' . $fname;
                    $ins = db()->prepare('INSERT INTO screenshots (trade_id,file_path,kind) VALUES (?,?,?)');
                    $ins->execute([$id, $rel, 'OTHER']);
                }
            }
        }
        // Delete selected screenshots
        if (!empty($_POST['delete_shot']) && is_array($_POST['delete_shot'])) {
            foreach ($_POST['delete_shot'] as $sid) {
                $sid = (int)$sid;
                $del = db()->prepare(
                    'DELETE s FROM screenshots s JOIN trades tr ON tr.id=s.trade_id
                     WHERE s.id=? AND tr.user_id=?'
                );
                $del->execute([$sid, $user['id']]);
            }
        }

        flash('Trade updated.', 'success');
        header('Location: trade_view.php?id=' . $id); exit;
    } catch (Throwable $e) {
        $err = $e->getMessage();
    }
}

$shotSt = db()->prepare('SELECT * FROM screenshots WHERE trade_id=? ORDER BY id');
$shotSt->execute([$id]);
$shots = $shotSt->fetchAll();

$title = 'Edit Trade #' . $id;
$page  = 'trades';
require __DIR__ . '/../includes/header.php';
?>
<div class="card">
  <h1>Edit trade #<?= (int)$t['id'] ?></h1>
  <?php if ($err): ?><div class="flash flash-error"><?= e($err) ?></div><?php endif; ?>
  <form method="post" enctype="multipart/form-data" class="grid-form">
    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
    <input type="hidden" name="id" value="<?= (int)$t['id'] ?>">

    <fieldset><legend>Trade</legend>
      <label>Pair<input name="pair" value="<?= e($t['pair']) ?>" required></label>
      <label>Direction
        <select name="direction">
          <option <?= $t['direction']==='BUY'?'selected':'' ?>>BUY</option>
          <option <?= $t['direction']==='SELL'?'selected':'' ?>>SELL</option>
        </select>
      </label>
      <label>Lot size<input type="number" step="0.0001" name="lot_size" value="<?= e($t['lot_size']) ?>" required></label>
      <label>Entry price<input type="number" step="0.00001" name="entry_price" value="<?= e($t['entry_price']) ?>" required></label>
      <label>Stop loss<input type="number" step="0.00001" name="stop_loss" value="<?= e($t['stop_loss']??'') ?>"></label>
      <label>Take profit<input type="number" step="0.00001" name="take_profit" value="<?= e($t['take_profit']??'') ?>"></label>
      <label>Exit price<input type="number" step="0.00001" name="exit_price" value="<?= e($t['exit_price']??'') ?>"></label>
      <label>Fees<input type="number" step="0.01" name="fees" value="<?= e($t['fees']) ?>"></label>
      <label>Risk %<input type="number" step="0.01" name="risk_pct" value="<?= e($t['risk_pct']??'') ?>"></label>
      <label>Outcome
        <select name="outcome">
          <?php foreach (['OPEN','TP','SL','BE','MANUAL'] as $o): ?>
            <option <?= $t['outcome']===$o?'selected':'' ?>><?= $o ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <label>Open time<input type="datetime-local" name="open_time" value="<?= e(str_replace(' ', 'T', substr($t['open_time'],0,16))) ?>" required></label>
      <label>Close time<input type="datetime-local" name="close_time" value="<?= $t['close_time']?e(str_replace(' ', 'T', substr($t['close_time'],0,16))):'' ?>"></label>
    </fieldset>

    <fieldset><legend>Context</legend>
      <label>Strategy<input name="strategy" value="<?= e($t['strategy']??'') ?>"></label>
      <label>Setup<input name="setup" value="<?= e($t['setup']??'') ?>"></label>
      <label>Tags<input name="tags" value="<?= e($t['tags']??'') ?>"></label>
      <label>Emotion
        <select name="emotion"><option value="">-</option>
          <?php for($i=1;$i<=5;$i++): ?>
            <option <?= (int)$t['emotion']===$i?'selected':'' ?>><?= $i ?></option>
          <?php endfor; ?>
        </select>
      </label>
      <label>Confidence
        <select name="confidence"><option value="">-</option>
          <?php for($i=1;$i<=5;$i++): ?>
            <option <?= (int)$t['confidence']===$i?'selected':'' ?>><?= $i ?></option>
          <?php endfor; ?>
        </select>
      </label>
      <label class="full">Notes<textarea name="notes" rows="3"><?= e($t['notes']??'') ?></textarea></label>
      <label class="full">Mistakes<textarea name="mistakes" rows="2"><?= e($t['mistakes']??'') ?></textarea></label>
    </fieldset>

    <fieldset><legend>Screenshots</legend>
      <?php if ($shots): ?>
        <div class="shot-grid">
          <?php foreach ($shots as $s): $src = $s['file_path'] ?: $s['url']; ?>
            <label class="shot shot-edit">
              <?php if ($s['file_path']): ?>
                <img src="<?= e($s['file_path']) ?>" alt="">
              <?php else: ?>
                <span class="shot-link">&#128279; <?= e($s['url']) ?></span>
              <?php endif; ?>
              <span class="shot-del">
                <input type="checkbox" name="delete_shot[]" value="<?= (int)$s['id'] ?>"> delete
              </span>
            </label>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
      <label class="full">Add external links (one per line)
        <textarea name="screenshot_urls" rows="2" placeholder="https://www.tradingview.com/x/abcd1234/"></textarea>
      </label>
      <label class="full">Upload more images
        <input type="file" name="screenshots[]" multiple accept="image/*">
      </label>
    </fieldset>

    <div class="form-actions">
      <button class="btn btn-primary">Save changes</button>
      <a href="trade_view.php?id=<?= (int)$t['id'] ?>" class="btn btn-ghost">Cancel</a>
    </div>
  </form>
</div>
<?php require __DIR__ . '/../includes/footer.php';
