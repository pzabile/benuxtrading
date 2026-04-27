<?php
/**
 * BENUX Trading - Configuration
 * Edit the values below to match your Hostinger MySQL database.
 *
 * Hostinger: hPanel -> Databases -> MySQL Databases
 * Use the host (usually localhost), database name, user and password shown there.
 */

return [
    // --- Database (MySQL / MariaDB) ---
    'db' => [
        'host'    => getenv('DB_HOST') ?: 'localhost',
        'port'    => (int)(getenv('DB_PORT') ?: 3306),
        'name'    => getenv('DB_NAME') ?: 'benux_trading',
        'user'    => getenv('DB_USER') ?: 'root',
        'pass'    => getenv('DB_PASS') ?: '',
        'charset' => 'utf8mb4',
    ],

    // --- App ---
    'app_name'   => 'BENUX Trading',
    'base_url'   => '',                              // leave empty to auto-detect
    'timezone'   => 'UTC',                           // default tz; user can override per account

    // --- Uploads ---
    'upload_dir'   => __DIR__ . '/../uploads',
    'upload_url'   => 'uploads',                     // relative URL from app root
    'upload_max'   => 8 * 1024 * 1024,               // 8 MB
    'allowed_ext'  => ['png','jpg','jpeg','gif','webp'],

    // --- Security ---
    'session_name' => 'BENUX_SESSION',
    'cookie_secure'=> false,                         // set TRUE on HTTPS production
];
