<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

if (current_user()) { header('Location: dashboard.php'); exit; }

$err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $username = trim($_POST['username'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $pass     = (string)($_POST['password'] ?? '');
    $start    = (float)($_POST['starting_balance'] ?? 10000);
    $tz       = trim($_POST['timezone'] ?? 'UTC');

    if (!preg_match('/^[A-Za-z0-9_.-]{3,32}$/', $username))         $err = 'Username must be 3-32 chars (letters, digits, _ . -).';
    elseif (!filter_var($email, FILTER_VALIDATE_EMAIL))             $err = 'Invalid email.';
    elseif (strlen($pass) < 8)                                       $err = 'Password must be at least 8 characters.';
    elseif (!in_array($tz, timezone_identifiers_list(), true))       $err = 'Invalid timezone.';
    else {
        try {
            $st = db()->prepare(
                'INSERT INTO users (username,email,password_hash,starting_balance,timezone) VALUES (?,?,?,?,?)'
            );
            $st->execute([$username, $email, password_hash($pass, PASSWORD_DEFAULT), $start, $tz]);
            login_user((int)db()->lastInsertId());
            flash('Welcome to BENUX Trading. Log your first trade!', 'success');
            header('Location: dashboard.php'); exit;
        } catch (PDOException $e) {
            $err = (str_contains($e->getMessage(), 'Duplicate'))
                 ? 'That username or email is already taken.'
                 : 'Database error: ' . $e->getMessage();
        }
    }
}

$title = 'Register';
$page  = 'register';
require __DIR__ . '/../includes/header.php';
?>
<div class="auth-wrap">
  <form class="card auth-card" method="post" autocomplete="off">
    <h1>Create your BENUX account</h1>
    <?php if ($err): ?><div class="flash flash-error"><?= e($err) ?></div><?php endif; ?>
    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
    <label>Username<input name="username" required minlength="3" maxlength="32"></label>
    <label>Email<input type="email" name="email" required></label>
    <label>Password<input type="password" name="password" required minlength="8"></label>
    <label>Starting balance ($)<input type="number" step="0.01" name="starting_balance" value="10000"></label>
    <label>Timezone
      <select name="timezone">
        <?php foreach (timezone_identifiers_list() as $tz): ?>
          <option value="<?= e($tz) ?>" <?= $tz==='UTC'?'selected':'' ?>><?= e($tz) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <button class="btn btn-primary">Create account</button>
    <p class="muted">Already have an account? <a href="login.php">Log in</a></p>
  </form>
</div>
<?php require __DIR__ . '/../includes/footer.php';
