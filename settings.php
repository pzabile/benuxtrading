<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';
$user = require_login();

$err = $msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $start = (float)($_POST['starting_balance'] ?? $user['starting_balance']);
    $tz    = trim($_POST['timezone'] ?? 'UTC');
    if (!in_array($tz, timezone_identifiers_list(), true)) {
        $err = 'Invalid timezone.';
    } else {
        $up = db()->prepare('UPDATE users SET starting_balance=?, timezone=? WHERE id=?');
        $up->execute([$start, $tz, $user['id']]);
        $msg = 'Saved.';
        $user = current_user();
    }

    if (!empty($_POST['new_password'])) {
        if (!password_verify($_POST['current_password'] ?? '', db()->query('SELECT password_hash FROM users WHERE id=' . (int)$user['id'])->fetchColumn())) {
            $err = 'Current password is incorrect.';
        } elseif (strlen($_POST['new_password']) < 8) {
            $err = 'New password must be at least 8 characters.';
        } else {
            $up = db()->prepare('UPDATE users SET password_hash=? WHERE id=?');
            $up->execute([password_hash($_POST['new_password'], PASSWORD_DEFAULT), $user['id']]);
            $msg = 'Password updated.';
        }
    }
}

$title = 'Settings';
$page  = 'settings';
require __DIR__ . '/includes/header.php';
?>
<div class="card">
  <h1>Settings</h1>
  <?php if ($err): ?><div class="flash flash-error"><?= e($err) ?></div><?php endif; ?>
  <?php if ($msg): ?><div class="flash flash-success"><?= e($msg) ?></div><?php endif; ?>
  <form method="post" class="grid-form">
    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
    <fieldset><legend>Account</legend>
      <label>Username<input value="<?= e($user['username']) ?>" disabled></label>
      <label>Email<input value="<?= e($user['email']) ?>" disabled></label>
      <label>Starting balance ($)
        <input type="number" step="0.01" name="starting_balance" value="<?= e($user['starting_balance']) ?>">
      </label>
      <label>Timezone
        <select name="timezone">
          <?php foreach (timezone_identifiers_list() as $tz): ?>
            <option value="<?= e($tz) ?>" <?= $tz===$user['timezone']?'selected':'' ?>><?= e($tz) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
    </fieldset>
    <fieldset><legend>Change password</legend>
      <label>Current password<input type="password" name="current_password"></label>
      <label>New password<input type="password" name="new_password" minlength="8"></label>
    </fieldset>
    <div class="form-actions"><button class="btn btn-primary">Save</button></div>
  </form>
</div>
<?php require __DIR__ . '/includes/footer.php';
