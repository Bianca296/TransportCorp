<?php
/**
 * Authentication Service Class
 * Handles login, logout, session management, and access control
 */

class Auth {
    private $pdo;
    private $user_model;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
        $this->user_model = new User($pdo);
    }
    
    /**
     * Login user with username/email and password
     * @param string $login Username or email
     * @param string $password Password
     * @param bool $remember_me Remember login
     * @return array Result array with success status and message
     */
    public function login($login, $password, $remember_me = false) {
        try {
            // Sanitize input
            $login = sanitize_input($login);
            
            if (empty($login) || empty($password)) {
                return ['success' => false, 'message' => 'Username/email and password are required'];
            }
            
            // Authenticate user
            $user = $this->user_model->authenticate($login, $password);
            
            if ($user) {
                // Start user session
                $this->startUserSession($user, $remember_me);
                
                return [
                    'success' => true, 
                    'message' => 'Login successful',
                    'user' => $user,
                    'redirect' => $this->getRedirectUrl($user['role'])
                ];
            } else {
                return ['success' => false, 'message' => 'Invalid credentials or account not active'];
            }
            
        } catch (Exception $e) {
            error_log("Login error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Login failed. Please try again.'];
        }
    }
    
    /**
     * Register new user
     * @param array $data User registration data
     * @return array Result array with success status and message
     */
    public function register($data) {
        try {
            // Sanitize all input data
            foreach ($data as $key => $value) {
                if ($key !== 'password') { // Don't sanitize password
                    $data[$key] = sanitize_input($value);
                }
            }
            
            // Create user account
            $result = $this->user_model->create($data);
            
            if ($result === true) {
                return [
                    'success' => true, 
                    'message' => 'Account created successfully! You can now login.',
                    'user_id' => $this->user_model->id
                ];
            } else {
                return ['success' => false, 'message' => $result];
            }
            
        } catch (Exception $e) {
            error_log("Registration error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Registration failed. Please try again.'];
        }
    }
    
    /**
     * Logout current user
     * @return bool Success
     */
    public function logout() {
        try {
            // Destroy session data
            $_SESSION = array();
            
            // Delete session cookie
            if (ini_get("session.use_cookies")) {
                $params = session_get_cookie_params();
                setcookie(session_name(), '', time() - 42000,
                    $params["path"], $params["domain"],
                    $params["secure"], $params["httponly"]
                );
            }
            
            // Destroy session
            session_destroy();
            
            // Start new session for flash messages
            session_start();
            session_regenerate_id();
            
            return true;
            
        } catch (Exception $e) {
            error_log("Logout error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Check if user is logged in
     * @return bool Is logged in
     */
    public function isLoggedIn() {
        return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
    }
    
    /**
     * Get current logged in user
     * @return array|false User data or false
     */
    public function getCurrentUser() {
        if (!$this->isLoggedIn()) {
            return false;
        }
        
        return $this->user_model->findById($_SESSION['user_id']);
    }
    
    /**
     * Check if current user has specific role
     * @param string $role Role to check
     * @return bool Has role
     */
    public function hasRole($role) {
        $user = $this->getCurrentUser();
        return $user && $user['role'] === $role;
    }
    
    /**
     * Check if current user is admin
     * @return bool Is admin
     */
    public function isAdmin() {
        return $this->hasRole('admin');
    }
    
    /**
     * Check if current user is employee or admin
     * @return bool Is employee or admin
     */
    public function isEmployee() {
        $user = $this->getCurrentUser();
        return $user && in_array($user['role'], ['employee', 'admin']);
    }
    
    /**
     * Require user to be logged in (redirect if not)
     * @param string $redirect_url Where to redirect after login
     */
    public function requireLogin($redirect_url = null) {
        if (!$this->isLoggedIn()) {
            if ($redirect_url) {
                $_SESSION['redirect_after_login'] = $redirect_url;
            }
            redirect(SITE_URL . '/auth/login.php');
        }
    }
    
    /**
     * Require user to have specific role
     * @param string $required_role Required role
     * @param string $error_message Custom error message
     */
    public function requireRole($required_role, $error_message = null) {
        $this->requireLogin();
        
        if (!$this->hasRole($required_role)) {
            if (!$error_message) {
                $error_message = "Access denied. {$required_role} role required.";
            }
            $_SESSION['error_message'] = $error_message;
            redirect(SITE_URL . '/index.php');
        }
    }
    
    /**
     * Require admin access
     */
    public function requireAdmin() {
        $this->requireRole('admin', 'Admin access required.');
    }
    
    /**
     * Require employee or admin access
     */
    public function requireEmployee() {
        $this->requireLogin();
        
        if (!$this->isEmployee()) {
            $_SESSION['error_message'] = 'Employee access required.';
            redirect(SITE_URL . '/index.php');
        }
    }
    
    /**
     * Generate CSRF token
     * @return string CSRF token
     */
    public function generateCSRFToken() {
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }
    
    /**
     * Verify CSRF token
     * @param string $token Token to verify
     * @return bool Valid token
     */
    public function verifyCSRFToken($token) {
        return isset($_SESSION['csrf_token']) && 
               hash_equals($_SESSION['csrf_token'], $token);
    }
    
    // Private helper methods
    
    /**
     * Start user session after successful login
     * @param array $user User data
     * @param bool $remember_me Remember login
     */
    private function startUserSession($user, $remember_me = false) {
        // Regenerate session ID for security
        session_regenerate_id(true);
        
        // Set session data
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['email'] = $user['email'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['full_name'] = $user['first_name'] . ' ' . $user['last_name'];
        $_SESSION['logged_in_at'] = time();
        
        // Set remember me cookie if requested
        if ($remember_me) {
            $this->setRememberMeCookie($user['id']);
        }
    }
    
    /**
     * Set remember me cookie
     * @param int $user_id User ID
     */
    private function setRememberMeCookie($user_id) {
        $token = bin2hex(random_bytes(32));
        $expires = time() + (30 * 24 * 60 * 60); // 30 days
        
        try {
            // Store session token in database
            $sql = "INSERT INTO user_sessions (user_id, session_token, ip_address, user_agent, expires_at) 
                    VALUES (?, ?, ?, ?, FROM_UNIXTIME(?))";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                $user_id,
                $token,
                $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',
                $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown',
                $expires
            ]);
            
            // Set cookie
            setcookie('remember_token', $token, $expires, '/', '', false, true);
            $_SESSION['remember_token'] = $token;
            
        } catch (PDOException $e) {
            error_log("Remember me cookie error: " . $e->getMessage());
            // Fall back to simple cookie without database storage
            setcookie('remember_token', $token, $expires, '/', '', false, true);
            $_SESSION['remember_token'] = $token;
        }
    }
    
    /**
     * Get redirect URL based on user role
     * @param string $role User role
     * @return string Redirect URL
     */
    public function getRedirectUrl($role) {
        // Check if there's a saved redirect URL
        if (isset($_SESSION['redirect_after_login'])) {
            $url = $_SESSION['redirect_after_login'];
            unset($_SESSION['redirect_after_login']);
            return $url;
        }
        
        // Default role-based redirects
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
    
    /**
     * Validate password strength
     * @param string $password Password to validate
     * @return array Validation result
     */
    public function validatePassword($password) {
        $errors = [];
        
        if (strlen($password) < 8) {
            $errors[] = "Password must be at least 8 characters long";
        }
        
        if (!preg_match('/[A-Z]/', $password)) {
            $errors[] = "Password must contain at least one uppercase letter";
        }
        
        if (!preg_match('/[a-z]/', $password)) {
            $errors[] = "Password must contain at least one lowercase letter";
        }
        
        if (!preg_match('/[0-9]/', $password)) {
            $errors[] = "Password must contain at least one number";
        }
        
        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }
}
?>
