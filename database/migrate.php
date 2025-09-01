<?php
/**
 * Migration Runner Script
 * Run database migrations from command line or web interface
 */

// Include configuration
require_once '../config/config.php';
require_once 'Migration.php';

// Get database connection
$database = new Database();
$pdo = $database->getConnection();

if (!$pdo) {
    die("Database connection failed!");
}

$migration = new Migration($pdo);
$action = $_GET['action'] ?? 'run';

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Database Migrations - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="<?php echo SITE_URL; ?>/public/css/style.css">
    <style>
        .migration-container { max-width: 800px; margin: 2rem auto; padding: 2rem; }
        .migration-status { font-family: monospace; background: #f4f4f4; padding: 1rem; border-radius: 5px; }
        .status-executed { color: #28a745; }
        .status-pending { color: #ffc107; }
        .migration-result { margin: 0.5rem 0; padding: 0.5rem; border-radius: 3px; }
        .migration-result.success { background: #d4edda; color: #155724; }
        .migration-result.error { background: #f8d7da; color: #721c24; }
        .nav-links { margin-bottom: 2rem; }
        .nav-links a { margin-right: 1rem; padding: 0.5rem 1rem; background: #007bff; color: white; text-decoration: none; border-radius: 3px; }
        .nav-links a:hover { background: #0056b3; }
        table { width: 100%; border-collapse: collapse; margin: 1rem 0; }
        th, td { padding: 0.75rem; text-align: left; border-bottom: 1px solid #ddd; }
        th { background-color: #f8f9fa; }
    </style>
</head>
<body>
    <div class="migration-container">
        <h1>🗄️ Database Migrations</h1>
        
        <div class="nav-links">
            <a href="?action=status">Migration Status</a>
            <a href="?action=run">Run Migrations</a>
            <a href="../index.php">← Back to Site</a>
        </div>

        <?php
        switch ($action) {
            case 'run':
                echo "<h2>Running Migrations...</h2>";
                $results = $migration->run();
                foreach ($results as $result) {
                    $class = strpos($result, '✅') !== false ? 'success' : 
                           (strpos($result, '❌') !== false ? 'error' : '');
                    echo "<div class='migration-result {$class}'>{$result}</div>";
                }
                echo "<p><a href='?action=status'>View Status</a></p>";
                break;
                
            case 'status':
                echo "<h2>Migration Status</h2>";
                $status = $migration->status();
                
                echo "<table>";
                echo "<tr><th>Migration</th><th>Status</th><th>Executed At</th><th>Batch</th></tr>";
                
                foreach ($status as $item) {
                    $statusClass = $item['status'] === 'executed' ? 'status-executed' : 'status-pending';
                    $statusText = $item['status'] === 'executed' ? '✅ Executed' : '⏳ Pending';
                    
                    echo "<tr>";
                    echo "<td><code>{$item['migration']}</code></td>";
                    echo "<td class='{$statusClass}'>{$statusText}</td>";
                    echo "<td>" . ($item['executed_at'] ?? '-') . "</td>";
                    echo "<td>" . ($item['batch'] ?? '-') . "</td>";
                    echo "</tr>";
                }
                
                echo "</table>";
                
                $pending = array_filter($status, function($item) { return $item['status'] === 'pending'; });
                if (!empty($pending)) {
                    echo "<p><strong>You have " . count($pending) . " pending migration(s).</strong></p>";
                    echo "<p><a href='?action=run' style='background: #28a745; color: white; padding: 0.5rem 1rem; text-decoration: none; border-radius: 3px;'>Run Pending Migrations</a></p>";
                } else {
                    echo "<p>✅ All migrations are up to date!</p>";
                }
                break;
                
            default:
                echo "<h2>Migration Manager</h2>";
                echo "<p>Use the navigation above to run migrations or check status.</p>";
        }
        ?>
    </div>
</body>
</html>
