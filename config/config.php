<?php
/**
 * Application Configuration
 */

// Session handling
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Site settings
define('SITE_NAME', 'TransportCorp');
define('SITE_URL', 'http://localhost/DAW');
define('SITE_DESCRIPTION', 'Professional Transport & Shipping Services');

// Development error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Timezone setting
date_default_timezone_set('UTC');

// Include database configuration
require_once __DIR__ . '/database.php';

// Include core classes
require_once __DIR__ . '/../classes/User.php';
require_once __DIR__ . '/../classes/Auth.php';
require_once __DIR__ . '/../classes/Order.php';
require_once __DIR__ . '/../classes/OrderHandler.php';

// Include middleware (optional - only when needed)
// require_once __DIR__ . '/../includes/middleware.php';

// Utility functions

// Sanitize user input
function sanitize_input($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

// Redirect to a page
function redirect($location) {
    header("Location: $location");
    exit;
}

// Check if user is logged in
function is_logged_in() {
    return isset($_SESSION['user_id']);
}

// Get Auth instance
function getAuth() {
    static $auth = null;
    if ($auth === null) {
        $database = new Database();
        $pdo = $database->getConnection();
        $auth = new Auth($pdo);
    }
    return $auth;
}
?>
