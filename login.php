<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';

if (current_user()) { header('Location: dashboard.php'); exit; }

$err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $id   = trim($_POST['identifier'] ?? '');
    $pass = (string)($_POST['password'] ?? '');
    $st = db()->prepare('SELECT * FROM users WHERE username=? OR email=? LIMIT 1');
    $st->execute([$id, $id]);
    $u = $st->fetch();
    if ($u && password_verify($pass, $u['password_hash'])) {
        login_user((int)$u['id']);
        header('Location: dashboard.php'); exit;
    } else {
        $err = 'Invalid credentials.';
    }
}

$title = 'Log in';
$page  = 'login';
require __DIR__ . '/includes/header.php';
?>
<div class="auth-wrap">
  <form class="card auth-card" method="post" autocomplete="off">
    <h1>Welcome back</h1>
    <?php if ($err): ?><div class="flash flash-error"><?= e($err) ?></div><?php endif; ?>
    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
    <label>Username or email<input name="identifier" required autofocus></label>
    <label>Password<input type="password" name="password" required></label>
    <button class="btn btn-primary">Log in</button>
    <p class="muted">No account? <a href="register.php">Register</a></p>
  </form>
</div>
<?php require __DIR__ . '/includes/footer.php';
