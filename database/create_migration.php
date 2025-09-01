<?php
/**
 * Create New Migration Script
 * Usage: php create_migration.php migration_name
 */

if (php_sapi_name() !== 'cli') {
    die("This script can only be run from command line\n");
}

if ($argc < 2) {
    echo "Usage: php create_migration.php migration_name\n";
    echo "Example: php create_migration.php add_orders_table\n";
    exit(1);
}

// Include required files
require_once '../config/config.php';
require_once 'Migration.php';

try {
    $database = new Database();
    $pdo = $database->getConnection();
    $migration = new Migration($pdo);
    
    $migration_name = $argv[1];
    $filepath = $migration->create($migration_name);
    
    echo "✅ Migration created: {$filepath}\n";
    echo "Edit the file and then run: php migrate_cli.php\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}
?>
