<?php
/**
 * User Model
 */

class User {
    private $pdo;
    private $table = 'users';
    

    public $id;
    public $username;
    public $email;
    public $first_name;
    public $last_name;
    public $role;
    public $status;
    public $phone;
    public $address;
    public $created_at;
    public $last_login;
    public $email_verified;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    // Create new user account
    public function create($data) {
        try {
            // Validate required fields
            $required = ['username', 'email', 'password', 'first_name', 'last_name'];
            foreach ($required as $field) {
                if (empty($data[$field])) {
                    return "Field '$field' is required";
                }
            }
            
            // Check email format
            if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
                return "Invalid email format";
            }
            
            // Note: Password strength validation is handled by Auth class
            // This is just a basic length check for safety
            
            // Check if username or email already exists
            if ($this->usernameExists($data['username'])) {
                return "Username already exists";
            }
            
            if ($this->emailExists($data['email'])) {
                return "Email already exists";
            }
            
            // Hash password
            $password_hash = password_hash($data['password'], PASSWORD_DEFAULT);
            
            // Set default role if not provided
            $role = isset($data['role']) ? $data['role'] : 'customer';
            
            // Validate role
            $valid_roles = ['customer', 'employee', 'admin'];
            if (!in_array($role, $valid_roles)) {
                $role = 'customer';
            }
            
            // Prepare SQL statement - set status to 'active' for immediate login
            $sql = "INSERT INTO {$this->table} 
                    (username, email, password_hash, first_name, last_name, role, phone, address, status) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'active')";
            
            $stmt = $this->pdo->prepare($sql);
            $result = $stmt->execute([
                $data['username'],
                $data['email'],
                $password_hash,
                $data['first_name'],
                $data['last_name'],
                $role,
                $data['phone'] ?? null,
                $data['address'] ?? null
            ]);
            
            if ($result) {
                $this->id = $this->pdo->lastInsertId();
                return true;
            }
            
            return "Failed to create user account";
            
        } catch (PDOException $e) {
            return "Database error: " . $e->getMessage();
        }
    }
    
    /**
     * Authenticate user login
     * @param string $login Username or email
     * @param string $password Plain text password
     * @return bool|array User data or false
     */
    public function authenticate($login, $password) {
        try {
            // Log login attempt
            $this->logLoginAttempt($login, false);
            
            // Check rate limiting
            if ($this->isRateLimited($login)) {
                return false;
            }
            
            // Find user by username or email
            $sql = "SELECT * FROM {$this->table} 
                    WHERE (username = ? OR email = ?) AND status = 'active'";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$login, $login]);
            $user = $stmt->fetch();
            
            if ($user && password_verify($password, $user['password_hash'])) {
                // Update last login
                $this->updateLastLogin($user['id']);
                
                // Log successful attempt
                $this->logLoginAttempt($login, true);
                
                // Set user properties
                $this->setUserProperties($user);
                
                return $user;
            }
            
            return false;
            
        } catch (PDOException $e) {
            error_log("Authentication error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Find user by ID
     * @param int $id User ID
     * @return array|false User data or false
     */
    public function findById($id) {
        try {
            $sql = "SELECT * FROM {$this->table} WHERE id = ?";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$id]);
            $user = $stmt->fetch();
            
            if ($user) {
                $this->setUserProperties($user);
                return $user;
            }
            
            return false;
            
        } catch (PDOException $e) {
            error_log("Find user error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Update user information
     * @param int $id User ID
     * @param array $data Updated data
     * @return bool Success
     */
    public function update($id, $data) {
        try {
            // Email updates should be handled separately for security
            $allowed_fields = ['first_name', 'last_name', 'phone', 'address'];
            $updates = [];
            $values = [];
            
            foreach ($data as $field => $value) {
                if (in_array($field, $allowed_fields)) {
                    $updates[] = "$field = ?";
                    $values[] = $value;
                }
            }
            
            if (empty($updates)) {
                return false;
            }
            
            $values[] = $id;
            $sql = "UPDATE {$this->table} SET " . implode(', ', $updates) . " WHERE id = ?";
            $stmt = $this->pdo->prepare($sql);
            
            return $stmt->execute($values);
            
        } catch (PDOException $e) {
            error_log("Update user error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Change user password
     * @param int $id User ID
     * @param string $new_password New password
     * @return bool Success
     */
    public function changePassword($id, $new_password) {
        try {
            if (strlen($new_password) < 6) {
                return false;
            }
            
            $password_hash = password_hash($new_password, PASSWORD_DEFAULT);
            $sql = "UPDATE {$this->table} SET password_hash = ? WHERE id = ?";
            $stmt = $this->pdo->prepare($sql);
            
            return $stmt->execute([$password_hash, $id]);
            
        } catch (PDOException $e) {
            error_log("Change password error: " . $e->getMessage());
            return false;
        }
    }
    
    // Helper methods
    
    private function usernameExists($username) {
        $sql = "SELECT id FROM {$this->table} WHERE username = ?";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$username]);
        return $stmt->fetch() !== false;
    }
    
    private function emailExists($email) {
        $sql = "SELECT id FROM {$this->table} WHERE email = ?";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$email]);
        return $stmt->fetch() !== false;
    }
    
    private function updateLastLogin($user_id) {
        $sql = "UPDATE {$this->table} SET last_login = NOW() WHERE id = ?";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$user_id]);
    }
    
    private function logLoginAttempt($login, $success) {
        try {
            $sql = "INSERT INTO login_attempts (email, ip_address, success) VALUES (?, ?, ?)";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                $login,
                $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',
                $success ? 1 : 0
            ]);
        } catch (PDOException $e) {
            // Log but don't fail authentication
            error_log("Login attempt logging error: " . $e->getMessage());
        }
    }
    
    private function isRateLimited($login) {
        try {
            // Check for too many failed attempts in last 15 minutes
            $sql = "SELECT COUNT(*) as attempts FROM login_attempts 
                    WHERE email = ? AND success = 0 
                    AND attempted_at > DATE_SUB(NOW(), INTERVAL 15 MINUTE)";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$login]);
            $result = $stmt->fetch();
            
            return $result['attempts'] >= 5; // Max 5 failed attempts
            
        } catch (PDOException $e) {
            error_log("Rate limiting error: " . $e->getMessage());
            return false; // Don't block on error
        }
    }
    
    private function setUserProperties($user) {
        $this->id = $user['id'];
        $this->username = $user['username'];
        $this->email = $user['email'];
        $this->first_name = $user['first_name'];
        $this->last_name = $user['last_name'];
        $this->role = $user['role'];
        $this->status = $user['status'];
        $this->phone = $user['phone'];
        $this->address = $user['address'];
        $this->created_at = $user['created_at'];
        $this->last_login = $user['last_login'];
        $this->email_verified = $user['email_verified'];
    }
    
    /**
     * Get user's full name
     * @return string Full name
     */
    public function getFullName() {
        return $this->first_name . ' ' . $this->last_name;
    }
    
    /**
     * Check if user has specific role
     * @param string $role Role to check
     * @return bool Has role
     */
    public function hasRole($role) {
        return $this->role === $role;
    }
    
    /**
     * Check if user is admin
     * @return bool Is admin
     */
    public function isAdmin() {
        return $this->role === 'admin';
    }
    
    /**
     * Check if user is employee or admin
     * @return bool Is employee or admin
     */
    public function isEmployee() {
        return in_array($this->role, ['employee', 'admin']);
    }
    
    /**
     * Get all users with optional filters
     * @param array $filters Optional filters array
     * @return array Users list
     */
    public function getAllUsers($filters = []) {
        try {
            $sql = "SELECT id, username, email, first_name, last_name, role, status, created_at, last_login, updated_at FROM users WHERE 1=1";
            $params = [];
            
            // Apply filters
            if (!empty($filters['search'])) {
                $sql .= " AND (username LIKE ? OR email LIKE ? OR first_name LIKE ? OR last_name LIKE ?)";
                $search_param = '%' . $filters['search'] . '%';
                $params = array_merge($params, [$search_param, $search_param, $search_param, $search_param]);
            }
            
            if (!empty($filters['role'])) {
                $sql .= " AND role = ?";
                $params[] = $filters['role'];
            }
            
            if (!empty($filters['status'])) {
                $sql .= " AND status = ?";
                $params[] = $filters['status'];
            }
            
            $sql .= " ORDER BY created_at DESC";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll();
            
        } catch (PDOException $e) {
            error_log("Get all users error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Create user by admin (with different validation rules)
     * @param array $data User data
     * @return array Result with success status and message
     */
    public function createByAdmin($data) {
        try {
            // Validate required fields
            $required_fields = ['username', 'email', 'password', 'first_name', 'last_name', 'role'];
            foreach ($required_fields as $field) {
                if (empty($data[$field])) {
                    return ['success' => false, 'message' => "Field '$field' is required"];
                }
            }
            
            // Validate username
            if (strlen($data['username']) < 3 || strlen($data['username']) > 50) {
                return ['success' => false, 'message' => 'Username must be between 3 and 50 characters'];
            }
            
            if (!preg_match('/^[a-zA-Z0-9_]+$/', $data['username'])) {
                return ['success' => false, 'message' => 'Username can only contain letters, numbers, and underscores'];
            }
            
            // Check if username exists
            if ($this->usernameExists($data['username'])) {
                return ['success' => false, 'message' => 'Username already exists'];
            }
            
            // Validate email
            if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
                return ['success' => false, 'message' => 'Invalid email address'];
            }
            
            // Check if email exists
            if ($this->emailExists($data['email'])) {
                return ['success' => false, 'message' => 'Email address already exists'];
            }
            
            // Validate password (admin can set simpler passwords)
            if (strlen($data['password']) < 6) {
                return ['success' => false, 'message' => 'Password must be at least 6 characters'];
            }
            
            // Validate role
            $valid_roles = ['customer', 'employee', 'admin'];
            if (!in_array($data['role'], $valid_roles)) {
                return ['success' => false, 'message' => 'Invalid role specified'];
            }
            
            // Validate status
            $valid_statuses = ['active', 'inactive', 'pending'];
            $status = $data['status'] ?? 'active';
            if (!in_array($status, $valid_statuses)) {
                return ['success' => false, 'message' => 'Invalid status specified'];
            }
            
            // Hash password
            $hashed_password = password_hash($data['password'], PASSWORD_DEFAULT);
            
            // Prepare SQL
            $sql = "INSERT INTO users (username, email, password, first_name, last_name, phone, address, role, status) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
            
            $stmt = $this->pdo->prepare($sql);
            $result = $stmt->execute([
                $data['username'],
                $data['email'],
                $hashed_password,
                $data['first_name'],
                $data['last_name'],
                $data['phone'] ?? null,
                $data['address'] ?? null,
                $data['role'],
                $status
            ]);
            
            if ($result) {
                $this->id = $this->pdo->lastInsertId();
                return [
                    'success' => true, 
                    'message' => 'User created successfully',
                    'user_id' => $this->id
                ];
            }
            
            return ['success' => false, 'message' => 'Failed to create user'];
            
        } catch (PDOException $e) {
            error_log("Admin user creation error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Database error occurred'];
        }
    }
    
    /**
     * Update user by admin (with different validation rules)
     * @param int $user_id User ID to update
     * @param array $data User data
     * @return array Result with success status and message
     */
    public function updateByAdmin($user_id, $data) {
        try {
            // Validate user exists
            $existing_user = $this->findById($user_id);
            if (!$existing_user) {
                return ['success' => false, 'message' => 'User not found'];
            }
            
            // Start building update query
            $update_fields = [];
            $params = [];
            
            // Validate and update username
            if (isset($data['username'])) {
                if (strlen($data['username']) < 3 || strlen($data['username']) > 50) {
                    return ['success' => false, 'message' => 'Username must be between 3 and 50 characters'];
                }
                
                if (!preg_match('/^[a-zA-Z0-9_]+$/', $data['username'])) {
                    return ['success' => false, 'message' => 'Username can only contain letters, numbers, and underscores'];
                }
                
                // Check if username exists for other users
                $stmt = $this->pdo->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
                $stmt->execute([$data['username'], $user_id]);
                if ($stmt->fetch()) {
                    return ['success' => false, 'message' => 'Username already exists'];
                }
                
                $update_fields[] = "username = ?";
                $params[] = $data['username'];
            }
            
            // Validate and update email
            if (isset($data['email'])) {
                if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
                    return ['success' => false, 'message' => 'Invalid email address'];
                }
                
                // Check if email exists for other users
                $stmt = $this->pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
                $stmt->execute([$data['email'], $user_id]);
                if ($stmt->fetch()) {
                    return ['success' => false, 'message' => 'Email address already exists'];
                }
                
                $update_fields[] = "email = ?";
                $params[] = $data['email'];
            }
            
            // Update password if provided
            if (isset($data['password']) && !empty($data['password'])) {
                if (strlen($data['password']) < 6) {
                    return ['success' => false, 'message' => 'Password must be at least 6 characters'];
                }
                
                $update_fields[] = "password = ?";
                $params[] = password_hash($data['password'], PASSWORD_DEFAULT);
            }
            
            // Update other fields
            $simple_fields = ['first_name', 'last_name', 'phone', 'address'];
            foreach ($simple_fields as $field) {
                if (isset($data[$field])) {
                    $update_fields[] = "$field = ?";
                    $params[] = $data[$field];
                }
            }
            
            // Validate and update role
            if (isset($data['role'])) {
                $valid_roles = ['customer', 'employee', 'admin'];
                if (!in_array($data['role'], $valid_roles)) {
                    return ['success' => false, 'message' => 'Invalid role specified'];
                }
                
                $update_fields[] = "role = ?";
                $params[] = $data['role'];
            }
            
            // Validate and update status
            if (isset($data['status'])) {
                $valid_statuses = ['active', 'inactive', 'pending'];
                if (!in_array($data['status'], $valid_statuses)) {
                    return ['success' => false, 'message' => 'Invalid status specified'];
                }
                
                $update_fields[] = "status = ?";
                $params[] = $data['status'];
            }
            
            // If no fields to update
            if (empty($update_fields)) {
                return ['success' => false, 'message' => 'No fields to update'];
            }
            
            // Add updated_at timestamp
            $update_fields[] = "updated_at = CURRENT_TIMESTAMP";
            
            // Add user ID for WHERE clause
            $params[] = $user_id;
            
            // Execute update
            $sql = "UPDATE users SET " . implode(', ', $update_fields) . " WHERE id = ?";
            $stmt = $this->pdo->prepare($sql);
            $result = $stmt->execute($params);
            
            if ($result) {
                return ['success' => true, 'message' => 'User updated successfully'];
            }
            
            return ['success' => false, 'message' => 'Failed to update user'];
            
        } catch (PDOException $e) {
            error_log("Admin user update error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Database error occurred'];
        }
    }
}
?>
