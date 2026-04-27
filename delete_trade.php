<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';
$user = require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: trades.php'); exit; }
csrf_check();

$id = (int)($_POST['id'] ?? 0);

// Remove uploaded files first.
$cfg = require __DIR__ . '/../includes/config.php';
$st = db()->prepare(
    'SELECT s.file_path FROM screenshots s
     JOIN trades t ON t.id=s.trade_id
     WHERE s.trade_id=? AND t.user_id=? AND s.file_path IS NOT NULL'
);
$st->execute([$id, $user['id']]);
foreach ($st->fetchAll() as $row) {
    $abs = $cfg['upload_dir'] . '/' . preg_replace('#^uploads/#', '', $row['file_path']);
    if (is_file($abs)) @unlink($abs);
}

$del = db()->prepare('DELETE FROM trades WHERE id=? AND user_id=?');
$del->execute([$id, $user['id']]);

flash('Trade deleted.', 'success');
header('Location: trades.php');
