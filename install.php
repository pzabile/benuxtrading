<?php
/**
 * BENUX Trading - One-time installer.
 * Open this in your browser ONCE after uploading & editing /includes/config.php.
 * It will create the database tables. Delete this file after install.
 */

require_once __DIR__ . '/../includes/db.php';

$title = 'Install';
$page  = '';
require __DIR__ . '/../includes/header.php';

try {
    $sql = file_get_contents(__DIR__ . '/../sql/schema.sql');
    // Split on ';' that ends a statement.
    $stmts = array_filter(array_map('trim', explode(';', $sql)));
    foreach ($stmts as $s) {
        if ($s !== '') db()->exec($s);
    }
    echo '<div class="card"><h2>Install complete</h2>';
    echo '<p>The BENUX Trading tables were created or already existed.</p>';
    echo '<p><strong>Next steps:</strong></p><ol>';
    echo '<li>Delete <code>install.php</code> from your server.</li>';
    echo '<li>Open <a href="register.php">register.php</a> to create your first account.</li>';
    echo '<li>Then go to <a href="login.php">login.php</a> and start logging trades.</li>';
    echo '</ol></div>';
} catch (Throwable $e) {
    echo '<div class="card flash flash-error"><h2>Install failed</h2><pre>' . e($e->getMessage()) . '</pre>';
    echo '<p>Check <code>/includes/config.php</code> credentials, then reload this page.</p></div>';
}

require __DIR__ . '/../includes/footer.php';
