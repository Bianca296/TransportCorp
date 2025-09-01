<?php
/**
 * Database Installation Script
 * Transport Company Web Application
 * 
 * Run this file once to set up the database schema
 */

// Include configuration
require_once '../config/config.php';

// Get database connection
$database = new Database();
$pdo = $database->getConnection();

if (!$pdo) {
    die("Database connection failed!");
}

try {
    // Read and execute schema file
    $schema = file_get_contents(__DIR__ . '/schema.sql');
    
    if ($schema === false) {
        throw new Exception("Could not read schema.sql file");
    }
    
    // Split SQL statements by semicolon and execute each one
    $statements = array_filter(array_map('trim', explode(';', $schema)));
    
    foreach ($statements as $statement) {
        if (!empty($statement) && !preg_match('/^\s*--/', $statement)) {
            $pdo->exec($statement);
        }
    }
    
    echo "<h2>✅ Database Installation Successful!</h2>";
    echo "<p>The following tables have been created:</p>";
    echo "<ul>";
    echo "<li>✅ users - User accounts and authentication</li>";
    echo "<li>✅ user_sessions - Session management</li>";
    echo "<li>✅ login_attempts - Security tracking</li>";
    echo "</ul>";
    
    echo "<h3>Default Users Created:</h3>";
    echo "<table border='1' cellpadding='5' cellspacing='0'>";
    echo "<tr><th>Username</th><th>Email</th><th>Role</th><th>Password</th></tr>";
    echo "<tr><td>admin</td><td>admin@transportcorp.com</td><td>admin</td><td>password</td></tr>";
    echo "<tr><td>customer1</td><td>customer@example.com</td><td>customer</td><td>password</td></tr>";
    echo "<tr><td>employee1</td><td>employee@transportcorp.com</td><td>employee</td><td>password</td></tr>";
    echo "</table>";
    
    echo "<p><strong>⚠️ Important:</strong> Change the default passwords after installation!</p>";
    
    echo "<h3>🔄 Migration System Available</h3>";
    echo "<p>For future schema changes, use the migration system:</p>";
    echo "<p><a href='migrate.php'>Manage Database Migrations</a></p>";
    
    echo "<p><a href='../index.php'>← Back to Homepage</a> | <a href='../auth/login.php'>Login with Demo Account</a></p>";
    
} catch (Exception $e) {
    echo "<h2>❌ Database Installation Failed!</h2>";
    echo "<p>Error: " . $e->getMessage() . "</p>";
    
    if (strpos($e->getMessage(), 'Invalid default value') !== false) {
        echo "<p><strong>This looks like a MySQL strict mode issue.</strong></p>";
        echo "<p>Solution: The schema has been updated to fix this. Try installing again, or run the fix script if you already have the database.</p>";
    }
    
    echo "<p>Please check your database configuration and try again.</p>";
    echo "<p><a href='run-fix.php'>Try Schema Fix</a> | <a href='../index.php'>Back to Homepage</a></p>";
}
?>
