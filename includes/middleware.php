<?php
/**
 * Access Control Middleware
 */

// Require user to be logged in
function requireLogin($redirect_after_login = null) {
    $auth = getAuth();
    $auth->requireLogin($redirect_after_login);
}

// Require specific user role
function requireRole($required_role, $error_message = null) {
    $auth = getAuth();
    $auth->requireRole($required_role, $error_message);
}

/**
 * Require admin access
 */
function requireAdmin() {
    $auth = getAuth();
    $auth->requireAdmin();
}

/**
 * Require employee or admin access
 */
function requireEmployee() {
    $auth = getAuth();
    $auth->requireEmployee();
}

/**
 * Check if current user has permission to access page
 * @param array $allowed_roles Array of allowed roles
 * @return bool Has permission
 */
function hasPermission($allowed_roles) {
    $auth = getAuth();
    
    if (!$auth->isLoggedIn()) {
        return false;
    }
    
    $user = $auth->getCurrentUser();
    return $user && in_array($user['role'], $allowed_roles);
}

/**
 * Get current user or redirect if not logged in
 * @return array User data
 */
function getCurrentUserOrRedirect() {
    $auth = getAuth();
    
    if (!$auth->isLoggedIn()) {
        $_SESSION['error_message'] = 'Please log in to access this page.';
        redirect(SITE_URL . '/auth/login.php');
    }
    
    $user = $auth->getCurrentUser();
    
    if (!$user) {
        $_SESSION['error_message'] = 'User session invalid. Please log in again.';
        redirect(SITE_URL . '/auth/login.php');
    }
    
    return $user;
}

/**
 * Display access denied message and redirect
 * @param string $message Custom error message
 * @param string $redirect_url Where to redirect
 */
function accessDenied($message = null, $redirect_url = null) {
    if (!$message) {
        $message = 'Access denied. You do not have permission to view this page.';
    }
    
    if (!$redirect_url) {
        $redirect_url = SITE_URL . '/index.php';
    }
    
    $_SESSION['error_message'] = $message;
    redirect($redirect_url);
}

/**
 * Dashboard access control helper
 * Ensures user can only access their appropriate dashboard
 * @param string $required_role Required role for this dashboard
 */
function dashboardAccessControl($required_role) {
    $user = getCurrentUserOrRedirect();
    
    // Allow admin to access any dashboard
    if ($user['role'] === 'admin') {
        return $user;
    }
    
    // Check if user has the required role
    if ($user['role'] !== $required_role) {
        $proper_dashboard = getDashboardUrl($user['role']);
        $_SESSION['error_message'] = "Redirected to your {$user['role']} dashboard.";
        redirect($proper_dashboard);
    }
    
    return $user;
}

/**
 * Get dashboard URL for user role
 * @param string $role User role
 * @return string Dashboard URL
 */
function getDashboardUrl($role) {
    switch ($role) {
        case 'admin':
            return SITE_URL . '/admin/dashboard.php';
        case 'employee':
            return SITE_URL . '/employee/dashboard.php';
        case 'customer':
            return SITE_URL . '/customer/dashboard.php';
        default:
            return SITE_URL . '/index.php';
    }
}
?>
