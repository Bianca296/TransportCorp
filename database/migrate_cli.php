<?php
/**
 * Command Line Migration Runner
 * Usage: php migrate_cli.php [action]
 * Actions: run, status, help
 */

if (php_sapi_name() !== 'cli') {
    die("This script can only be run from command line\n");
}

// Include required files
require_once '../config/config.php';
require_once 'Migration.php';

$action = $argv[1] ?? 'run';

try {
    $database = new Database();
    $pdo = $database->getConnection();
    
    if (!$pdo) {
        throw new Exception("Database connection failed!");
    }
    
    $migration = new Migration($pdo);
    
    switch ($action) {
        case 'run':
            echo "🚀 Running migrations...\n";
            $results = $migration->run();
            foreach ($results as $result) {
                echo $result . "\n";
            }
            break;
            
        case 'status':
            echo "📊 Migration Status:\n";
            echo str_repeat("-", 80) . "\n";
            printf("%-40s %-15s %-20s %s\n", "Migration", "Status", "Executed At", "Batch");
            echo str_repeat("-", 80) . "\n";
            
            $status = $migration->status();
            foreach ($status as $item) {
                $statusText = $item['status'] === 'executed' ? '✅ Executed' : '⏳ Pending';
                printf("%-40s %-15s %-20s %s\n", 
                    $item['migration'], 
                    $statusText, 
                    $item['executed_at'] ?? '-', 
                    $item['batch'] ?? '-'
                );
            }
            
            $pending = array_filter($status, function($item) { 
                return $item['status'] === 'pending'; 
            });
            
            if (!empty($pending)) {
                echo "\n⚠️  You have " . count($pending) . " pending migration(s).\n";
                echo "Run: php migrate_cli.php run\n";
            } else {
                echo "\n✅ All migrations are up to date!\n";
            }
            break;
            
        case 'help':
        default:
            echo "Database Migration Tool\n";
            echo "Usage: php migrate_cli.php [action]\n\n";
            echo "Actions:\n";
            echo "  run     - Run all pending migrations\n";
            echo "  status  - Show migration status\n";
            echo "  help    - Show this help message\n\n";
            echo "Create new migration:\n";
            echo "  php create_migration.php migration_name\n";
            break;
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}
?>
