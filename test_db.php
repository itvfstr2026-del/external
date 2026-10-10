<?php
/**
 * InfinityFree Diagnostics & Database Connection Test
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);

$host = 'sql211.infinityfree.com';
$port = 3306;
$dbname = 'if0_43129095_externalfeedback';
$user = 'if0_43129095';
$pass = 'L23cGvfcDKR5qpE';

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>InfinityFree Setup Diagnostics</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; background: #f0f4f8; color: #1e293b; padding: 2rem; }
        .card { max-width: 650px; margin: 0 auto; background: #fff; border-radius: 12px; box-shadow: 0 4px 16px rgba(0,0,0,0.08); padding: 2rem; }
        h1 { font-size: 1.5rem; margin-top: 0; color: #0f172a; border-bottom: 2px solid #e2e8f0; padding-bottom: 0.75rem; }
        .item { display: flex; align-items: center; justify-content: space-between; padding: 0.75rem 0; border-bottom: 1px solid #f1f5f9; }
        .badge { padding: 0.25rem 0.75rem; border-radius: 9999px; font-weight: 600; font-size: 0.85rem; }
        .badge-success { background: #dcfce7; color: #15803d; }
        .badge-error { background: #fee2e2; color: #b91c1c; }
        .log-box { background: #0f172a; color: #f8fafc; font-family: monospace; padding: 1rem; border-radius: 8px; margin-top: 1.5rem; font-size: 0.85rem; overflow-x: auto; }
        .btn { display: inline-block; background: #0284c7; color: #fff; padding: 0.6rem 1.25rem; border-radius: 6px; text-decoration: none; font-weight: 600; margin-top: 1rem; }
    </style>
</head>
<body>
<div class="card">
    <h1><span style="color:#0284c7;">InfinityFree</span> Diagnostics Check</h1>
    
    <div class="item">
        <span>PHP Version</span>
        <span class="badge badge-success"><?= phpversion() ?></span>
    </div>

    <div class="item">
        <span>PDO MySQL Extension</span>
        <?php if (extension_loaded('pdo_mysql')): ?>
            <span class="badge badge-success">Enabled</span>
        <?php else: ?>
            <span class="badge badge-error">Missing</span>
        <?php endif; ?>
    </div>

    <div class="item">
        <span>ZipArchive Extension (.docx & ZIP)</span>
        <?php if (class_exists('ZipArchive')): ?>
            <span class="badge badge-success">Enabled</span>
        <?php else: ?>
            <span class="badge badge-error">Missing</span>
        <?php endif; ?>
    </div>

    <div class="item">
        <span>Config File Status</span>
        <?php if (file_exists(__DIR__ . '/config/config.php')): ?>
            <span class="badge badge-success">Found (/config/config.php)</span>
        <?php else: ?>
            <span class="badge badge-error">Missing in /config/</span>
        <?php endif; ?>
    </div>

    <div class="item">
        <span>Includes Folder Status</span>
        <?php if (is_dir(__DIR__ . '/includes')): ?>
            <span class="badge badge-success">Found (/includes)</span>
        <?php else: ?>
            <span class="badge badge-error">Missing /includes folder</span>
        <?php endif; ?>
    </div>

    <div class="item">
        <span>Database Connection</span>
        <?php
        $dbConnected = false;
        $dbError = '';
        try {
            $dsn = "mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4";
            $pdo = new PDO($dsn, $user, $pass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_TIMEOUT => 5
            ]);
            $dbConnected = true;
        } catch (PDOException $e) {
            $dbError = $e->getMessage();
        }
        ?>
        <?php if ($dbConnected): ?>
            <span class="badge badge-success">Connected Successfully!</span>
        <?php else: ?>
            <span class="badge badge-error">Failed</span>
        <?php endif; ?>
    </div>

    <?php if ($dbConnected): ?>
        <p style="color: #15803d; margin-top: 1.5rem; font-weight: 600;">
            All system requirements &amp; database connection passed!
        </p>
        <a href="seed.php" class="btn">Initialize Tables &amp; Go to Seed Page</a>
    <?php else: ?>
        <div class="log-box">
            <strong>Database Error Details:</strong><br>
            <?= htmlspecialchars($dbError) ?><br><br>
            <strong>Configured Settings:</strong><br>
            Host: <?= htmlspecialchars($host) ?><br>
            Database: <?= htmlspecialchars($dbname) ?><br>
            User: <?= htmlspecialchars($user) ?><br>
            Port: <?= htmlspecialchars((string)$port) ?>
        </div>
    <?php endif; ?>
</div>
</body>
</html>
