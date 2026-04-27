<?php
/**
 * BENUX Trading - Helper functions: pip math, sessions, stats.
 */

/** Escape for HTML output. */
function e($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

/** Decimal places used to convert price diff to "pips" for a pair. */
function pip_factor(string $pair): float {
    $p = strtoupper($pair);
    // JPY pairs: 1 pip = 0.01.  Most others: 1 pip = 0.0001.  XAU/USD: 1 pip = 0.1.
    if (strpos($p, 'JPY') !== false) return 100.0;            // diff * 100 = pips
    if (strpos($p, 'XAU') !== false) return 10.0;             // gold convention
    return 10000.0;
}

/** Approximate pip value (USD) per 1.0 lot for quick PnL math. */
function pip_value_per_lot(string $pair): float {
    $p = strtoupper($pair);
    if (strpos($p, 'JPY') !== false) return 9.0;              // rough; depends on USDJPY
    if (strpos($p, 'XAU') !== false) return 10.0;             // 1 lot gold ~ $1 / $0.10
    return 10.0;                                              // most majors at 1.0 lot
}

/** Convert price diff -> pips for the pair. */
function to_pips(string $pair, float $diff): float {
    return round($diff * pip_factor($pair), 2);
}

/**
 * Compute PnL ($) and pips given direction, prices, lot size, fees, and pair.
 * If exit_price missing, returns nulls.
 */
function compute_pnl(string $pair, string $direction, float $entry, ?float $exit, float $lot, float $fees = 0.0): array {
    if ($exit === null) return ['pnl' => null, 'pips' => null];
    $diff = ($direction === 'BUY') ? ($exit - $entry) : ($entry - $exit);
    $pips = to_pips($pair, $diff);
    $pnl  = round($pips * pip_value_per_lot($pair) * $lot - $fees, 2);
    return ['pnl' => $pnl, 'pips' => $pips];
}

/**
 * Risk:Reward of a planned trade given entry, sl, tp.
 */
function rr_planned(string $direction, float $entry, ?float $sl, ?float $tp): ?float {
    if ($sl === null || $tp === null) return null;
    $risk   = abs($entry - $sl);
    $reward = abs($tp - $entry);
    if ($risk <= 0) return null;
    return round($reward / $risk, 2);
}

/** Detect FX session for a UTC datetime string. Rough but useful. */
function detect_session(string $datetime, string $tz = 'UTC'): string {
    try {
        $d = new DateTime($datetime, new DateTimeZone($tz));
        $d->setTimezone(new DateTimeZone('UTC'));
    } catch (Exception $e) { return 'OTHER'; }
    $h = (int)$d->format('G');
    $asian   = ($h >= 0  && $h < 8);
    $london  = ($h >= 7  && $h < 16);
    $newyork = ($h >= 13 && $h < 22);
    if ($london && $newyork)            return 'OVERLAP';
    if ($london)                         return 'LONDON';
    if ($newyork)                        return 'NEWYORK';
    if ($asian)                          return 'ASIAN';
    return 'OTHER';
}

/** Normalize user input pair like "eurusd" -> "EURUSD". */
function normalize_pair(string $pair): string {
    return strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $pair));
}

/** Format money. */
function money(float $v): string {
    $sign = $v < 0 ? '-' : '';
    return $sign . '$' . number_format(abs($v), 2);
}

/**
 * Compute aggregate stats from an array of trades (already filtered).
 * Returns arrays ready for both the dashboard cards and Chart.js charts.
 */
function compute_stats(array $trades, float $startBalance = 10000.0): array {
    $closed = array_values(array_filter($trades, fn($t) => $t['outcome'] !== 'OPEN' && $t['pnl'] !== null));
    $wins   = array_values(array_filter($closed, fn($t) => (float)$t['pnl'] >  0));
    $losses = array_values(array_filter($closed, fn($t) => (float)$t['pnl'] <  0));
    $bes    = array_values(array_filter($closed, fn($t) => (float)$t['pnl'] == 0));

    $sum = fn($a, $k) => array_sum(array_map(fn($x) => (float)$x[$k], $a));
    $avg = fn($a, $k) => $a ? $sum($a, $k) / count($a) : 0.0;

    $totalPnl   = $sum($closed, 'pnl');
    $totalFees  = $sum($closed, 'fees');
    $grossWin   = $sum($wins, 'pnl');
    $grossLoss  = abs($sum($losses, 'pnl'));
    $winRate    = $closed ? count($wins) / count($closed) * 100.0 : 0.0;
    $expectancy = $closed ? $totalPnl / count($closed) : 0.0;
    $profitFactor = $grossLoss > 0 ? $grossWin / $grossLoss : ($grossWin > 0 ? INF : 0);

    // Equity curve + drawdown
    usort($closed, fn($a,$b) => strcmp($a['close_time'] ?? '', $b['close_time'] ?? ''));
    $equity = $startBalance;
    $peak   = $startBalance;
    $maxDD  = 0.0;
    $curve  = [['t' => 'Start', 'equity' => round($equity, 2)]];
    foreach ($closed as $t) {
        $equity += (float)$t['pnl'];
        $peak    = max($peak, $equity);
        $dd      = $peak - $equity;
        if ($dd > $maxDD) $maxDD = $dd;
        $curve[] = ['t' => $t['close_time'], 'equity' => round($equity, 2)];
    }

    // Buckets
    $byPair = $byHour = $byDay = $bySession = $byStrategy = [];
    foreach ($closed as $t) {
        $byPair[$t['pair']]                 = ($byPair[$t['pair']] ?? 0) + (float)$t['pnl'];
        $bySession[$t['session'] ?: 'OTHER']= ($bySession[$t['session'] ?: 'OTHER'] ?? 0) + (float)$t['pnl'];
        $strat                              = $t['strategy'] ?: '(none)';
        $byStrategy[$strat]                 = ($byStrategy[$strat] ?? 0) + (float)$t['pnl'];
        if (!empty($t['open_time'])) {
            try {
                $d = new DateTime($t['open_time']);
                $byHour[(int)$d->format('G')] = ($byHour[(int)$d->format('G')] ?? 0) + (float)$t['pnl'];
                $byDay[$d->format('D')]       = ($byDay[$d->format('D')] ?? 0) + (float)$t['pnl'];
            } catch (Exception $e) {}
        }
    }
    ksort($byHour);

    // Streaks
    $streakW = $streakL = $maxW = $maxL = 0;
    foreach ($closed as $t) {
        if ((float)$t['pnl'] > 0) { $streakW++; $streakL = 0; }
        elseif ((float)$t['pnl'] < 0) { $streakL++; $streakW = 0; }
        else { $streakW = 0; $streakL = 0; }
        $maxW = max($maxW, $streakW);
        $maxL = max($maxL, $streakL);
    }

    return [
        'count_total'    => count($trades),
        'count_closed'   => count($closed),
        'count_open'     => count($trades) - count($closed),
        'count_wins'     => count($wins),
        'count_losses'   => count($losses),
        'count_be'       => count($bes),
        'win_rate'       => round($winRate, 2),
        'total_pnl'      => round($totalPnl, 2),
        'total_fees'     => round($totalFees, 2),
        'avg_win'        => round($avg($wins, 'pnl'), 2),
        'avg_loss'       => round($avg($losses, 'pnl'), 2),
        'expectancy'     => round($expectancy, 2),
        'profit_factor'  => is_finite($profitFactor) ? round($profitFactor, 2) : 999.99,
        'best_trade'     => $closed ? max(array_column($closed, 'pnl')) : 0,
        'worst_trade'    => $closed ? min(array_column($closed, 'pnl')) : 0,
        'avg_rr'         => round($avg($closed, 'rr_actual'), 2),
        'max_drawdown'   => round($maxDD, 2),
        'max_streak_win' => $maxW,
        'max_streak_loss'=> $maxL,
        'equity_curve'   => $curve,
        'final_equity'   => round($equity, 2),
        'by_pair'        => $byPair,
        'by_hour'        => $byHour,
        'by_day'         => $byDay,
        'by_session'     => $bySession,
        'by_strategy'    => $byStrategy,
    ];
}
