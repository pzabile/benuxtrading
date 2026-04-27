<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';
$user = require_login();

$where  = ['user_id = ?'];
$params = [$user['id']];

foreach (['pair' => 'pair', 'outcome' => 'outcome', 'strategy' => 'strategy'] as $k => $col) {
    $v = trim($_GET[$k] ?? '');
    if ($v !== '') { $where[] = "$col = ?"; $params[] = ($k === 'pair') ? normalize_pair($v) : ($k === 'outcome' ? strtoupper($v) : $v); }
}
if (!empty($_GET['from'])) { $where[] = 'open_time >= ?'; $params[] = $_GET['from'] . ' 00:00:00'; }
if (!empty($_GET['to']))   { $where[] = 'open_time <= ?'; $params[] = $_GET['to']   . ' 23:59:59'; }

$st = db()->prepare('SELECT * FROM trades WHERE ' . implode(' AND ', $where) . ' ORDER BY open_time');
$st->execute($params);

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="benux_trades_' . date('Ymd_His') . '.csv"');
$out = fopen('php://output', 'w');
$cols = ['id','open_time','close_time','pair','direction','lot_size','entry_price','exit_price',
         'stop_loss','take_profit','pnl','pnl_pips','rr_planned','rr_actual','risk_pct','fees',
         'outcome','session','strategy','setup','tags','emotion','confidence','notes','mistakes'];
fputcsv($out, $cols);
while ($r = $st->fetch()) {
    $row = [];
    foreach ($cols as $c) $row[] = $r[$c] ?? '';
    fputcsv($out, $row);
}
fclose($out);
