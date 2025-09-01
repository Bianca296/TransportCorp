<?php
/**
 * Database Migration Manager
 * Handles sequential database schema changes
 */

class Migration {
    private $pdo;
    private $migrations_table = 'schema_migrations';
    private $migrations_dir;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
        $this->migrations_dir = __DIR__ . '/migrations/';
        $this->ensureMigrationsTable();
    }
    
    /**
     * Create migrations tracking table if it doesn't exist
     */
    private function ensureMigrationsTable() {
        $sql = "CREATE TABLE IF NOT EXISTS {$this->migrations_table} (
            id INT AUTO_INCREMENT PRIMARY KEY,
            migration VARCHAR(255) NOT NULL UNIQUE,
            executed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            batch INT NOT NULL DEFAULT 1
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
        
        $this->pdo->exec($sql);
    }
    
    /**
     * Run all pending migrations
     * @return array Results of migration execution
     */
    public function run() {
        $results = [];
        $pending = $this->getPendingMigrations();
        
        if (empty($pending)) {
            $results[] = "No pending migrations.";
            return $results;
        }
        
        $batch = $this->getNextBatch();
        
        foreach ($pending as $migration) {
            try {
                $this->pdo->beginTransaction();
                
                // Execute migration
                $this->executeMigration($migration);
                
                // Record migration as executed
                $this->recordMigration($migration, $batch);
                
                $this->pdo->commit();
                $results[] = "✅ Executed: {$migration}";
                
            } catch (Exception $e) {
                $this->pdo->rollBack();
                $results[] = "❌ Failed: {$migration} - " . $e->getMessage();
                break; // Stop on first failure
            }
        }
        
        return $results;
    }
    
    /**
     * Get list of pending migrations
     * @return array
     */
    private function getPendingMigrations() {
        // Get all migration files
        $files = glob($this->migrations_dir . '*.sql');
        $migrations = [];
        
        foreach ($files as $file) {
            $migrations[] = basename($file, '.sql');
        }
        
        // Sort by filename (ensures order)
        sort($migrations);
        
        // Get executed migrations
        $stmt = $this->pdo->prepare("SELECT migration FROM {$this->migrations_table}");
        $stmt->execute();
        $executed = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        // Return only pending migrations
        return array_diff($migrations, $executed);
    }
    
    /**
     * Execute a single migration file
     * @param string $migration Migration name
     */
    private function executeMigration($migration) {
        $file = $this->migrations_dir . $migration . '.sql';
        
        if (!file_exists($file)) {
            throw new Exception("Migration file not found: {$file}");
        }
        
        $sql = file_get_contents($file);
        
        if ($sql === false) {
            throw new Exception("Could not read migration file: {$file}");
        }
        
        // Split and execute each statement
        $statements = $this->splitSqlStatements($sql);
        
        foreach ($statements as $statement) {
            if (!empty(trim($statement))) {
                $this->pdo->exec($statement);
            }
        }
    }
    
    /**
     * Record migration as executed
     * @param string $migration Migration name
     * @param int $batch Batch number
     */
    private function recordMigration($migration, $batch) {
        $stmt = $this->pdo->prepare("INSERT INTO {$this->migrations_table} (migration, batch) VALUES (?, ?)");
        $stmt->execute([$migration, $batch]);
    }
    
    /**
     * Get next batch number
     * @return int
     */
    private function getNextBatch() {
        $stmt = $this->pdo->prepare("SELECT COALESCE(MAX(batch), 0) + 1 FROM {$this->migrations_table}");
        $stmt->execute();
        return $stmt->fetchColumn();
    }
    
    /**
     * Split SQL into individual statements
     * @param string $sql
     * @return array
     */
    private function splitSqlStatements($sql) {
        // Remove comments and split by semicolon
        $sql = preg_replace('/--.*$/m', '', $sql);
        $statements = explode(';', $sql);
        
        return array_filter(array_map('trim', $statements));
    }
    
    /**
     * Get migration status
     * @return array
     */
    public function status() {
        $all_migrations = glob($this->migrations_dir . '*.sql');
        $migration_names = array_map(function($file) {
            return basename($file, '.sql');
        }, $all_migrations);
        sort($migration_names);
        
        $stmt = $this->pdo->prepare("SELECT migration, executed_at, batch FROM {$this->migrations_table} ORDER BY executed_at");
        $stmt->execute();
        $executed = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $executed_map = [];
        foreach ($executed as $migration) {
            $executed_map[$migration['migration']] = $migration;
        }
        
        $status = [];
        foreach ($migration_names as $migration) {
            if (isset($executed_map[$migration])) {
                $status[] = [
                    'migration' => $migration,
                    'status' => 'executed',
                    'executed_at' => $executed_map[$migration]['executed_at'],
                    'batch' => $executed_map[$migration]['batch']
                ];
            } else {
                $status[] = [
                    'migration' => $migration,
                    'status' => 'pending',
                    'executed_at' => null,
                    'batch' => null
                ];
            }
        }
        
        return $status;
    }
    
    /**
     * Create a new migration file
     * @param string $name Migration name
     * @return string Created file path
     */
    public function create($name) {
        $timestamp = date('Y_m_d_His');
        $filename = "{$timestamp}_{$name}.sql";
        $filepath = $this->migrations_dir . $filename;
        
        $template = "-- Migration: {$name}
-- Created: " . date('Y-m-d H:i:s') . "

-- Add your SQL statements here
-- Example:
-- ALTER TABLE users ADD COLUMN new_field VARCHAR(255);

-- Remember to test your migration before running in production!
";
        
        if (file_put_contents($filepath, $template) !== false) {
            return $filepath;
        }
        
        throw new Exception("Could not create migration file: {$filepath}");
    }
}
?>
