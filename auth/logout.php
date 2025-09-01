<?php
/**
 * Logout Script
 * Handle user logout and session termination
 */

// Include configuration
require_once '../config/config.php';

// Initialize Auth service
$database = new Database();
$pdo = $database->getConnection();
$auth = new Auth($pdo);

// Perform logout
$auth->logout();

// Set success message
$_SESSION['success_message'] = 'You have been successfully logged out.';

// Redirect to homepage
redirect(SITE_URL . '/index.php');
?>
