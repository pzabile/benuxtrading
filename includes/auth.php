<?php
/**
 * BENUX Trading - Auth & session helpers.
 */

require_once __DIR__ . '/db.php';

function start_session(): void {
    if (session_status() === PHP_SESSION_ACTIVE) return;
    $cfg = require __DIR__ . '/config.php';
    session_name($cfg['session_name']);
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => (bool)$cfg['cookie_secure'],
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

function current_user(): ?array {
    start_session();
    if (empty($_SESSION['uid'])) return null;
    static $u = null;
    if ($u && (int)$u['id'] === (int)$_SESSION['uid']) return $u;
    $st = db()->prepare('SELECT id, username, email, starting_balance, timezone, created_at FROM users WHERE id=?');
    $st->execute([(int)$_SESSION['uid']]);
    $u = $st->fetch() ?: null;
    return $u;
}

function require_login(): array {
    $u = current_user();
    if (!$u) {
        header('Location: login.php');
        exit;
    }
    if (!empty($u['timezone'])) {
        @date_default_timezone_set($u['timezone']);
    }
    return $u;
}

function csrf_token(): string {
    start_session();
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_check(): void {
    start_session();
    $t = $_POST['csrf'] ?? '';
    if (!hash_equals($_SESSION['csrf'] ?? '', $t)) {
        http_response_code(419);
        exit('CSRF token invalid. Reload the page and try again.');
    }
}

function login_user(int $uid): void {
    start_session();
    session_regenerate_id(true);
    $_SESSION['uid'] = $uid;
}

function logout_user(): void {
    start_session();
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}

function flash(string $msg, string $type = 'info'): void {
    start_session();
    $_SESSION['flash'][] = ['msg' => $msg, 'type' => $type];
}

function flash_pop(): array {
    start_session();
    $f = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $f;
}
