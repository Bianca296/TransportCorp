<?php
/**
 * Admin User Management
 * Create, edit, view, and manage users
 */

// Include configuration and middleware
require_once '../config/config.php';
require_once '../includes/middleware.php';

// Require admin access
$user = dashboardAccessControl('admin');

// Initialize services
$database = new Database();
$pdo = $database->getConnection();
$user_model = new User($pdo);

$success_message = '';
$error_message = '';
$action = $_GET['action'] ?? 'list';
$edit_user = null;

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['create_user'])) {
        // Create new user
        $user_data = [
            'username' => $_POST['username'] ?? '',
            'email' => $_POST['email'] ?? '',
            'password' => $_POST['password'] ?? '',
            'first_name' => $_POST['first_name'] ?? '',
            'last_name' => $_POST['last_name'] ?? '',
            'phone' => $_POST['phone'] ?? '',
            'address' => $_POST['address'] ?? '',
            'role' => $_POST['role'] ?? 'customer',
            'status' => $_POST['status'] ?? 'active'
        ];
        
        $result = $user_model->createByAdmin($user_data);
        if ($result['success']) {
            $success_message = $result['message'];
            $action = 'list'; // Redirect to list view
        } else {
            $error_message = $result['message'];
        }
    } elseif (isset($_POST['update_user'])) {
        // Update existing user
        $user_id = intval($_POST['user_id']);
        $user_data = [
            'username' => $_POST['username'] ?? '',
            'email' => $_POST['email'] ?? '',
            'first_name' => $_POST['first_name'] ?? '',
            'last_name' => $_POST['last_name'] ?? '',
            'phone' => $_POST['phone'] ?? '',
            'address' => $_POST['address'] ?? '',
            'role' => $_POST['role'] ?? 'customer',
            'status' => $_POST['status'] ?? 'active'
        ];
        
        // Handle password update (optional)
        if (!empty($_POST['new_password'])) {
            $user_data['password'] = $_POST['new_password'];
        }
        
        $result = $user_model->updateByAdmin($user_id, $user_data);
        if ($result['success']) {
            $success_message = $result['message'];
            $action = 'list'; // Redirect to list view
        } else {
            $error_message = $result['message'];
            $edit_user = $user_model->findById($user_id); // Keep form data
        }
    } elseif (isset($_POST['delete_user'])) {
        // Delete user (soft delete - set status to inactive)
        $user_id = intval($_POST['user_id']);
        
        // Prevent admin from deleting themselves
        if ($user_id === $user['id']) {
            $error_message = 'You cannot delete your own account.';
        } else {
            $result = $user_model->updateByAdmin($user_id, ['status' => 'inactive']);
            if ($result['success']) {
                $success_message = 'User has been deactivated successfully.';
            } else {
                $error_message = 'Failed to deactivate user.';
            }
        }
        $action = 'list';
    } elseif (isset($_POST['activate_user'])) {
        // Activate user (set status to active)
        $user_id = intval($_POST['user_id']);
        
        $result = $user_model->updateByAdmin($user_id, ['status' => 'active']);
        if ($result['success']) {
            $success_message = 'User has been activated successfully.';
        } else {
            $error_message = 'Failed to activate user.';
        }
        $action = 'list';
    }
}

// Handle different actions
if ($action === 'edit' && isset($_GET['id'])) {
    $edit_user = $user_model->findById(intval($_GET['id']));
    if (!$edit_user) {
        $error_message = 'User not found.';
        $action = 'list';
    }
}

// Get all users for list view
$search_term = $_GET['search'] ?? '';
$role_filter = $_GET['role'] ?? '';
$status_filter = $_GET['status'] ?? '';

$filters = [];
if ($search_term) {
    $filters['search'] = $search_term;
}
if ($role_filter) {
    $filters['role'] = $role_filter;
}
if ($status_filter) {
    $filters['status'] = $status_filter;
}

try {
    $all_users = $user_model->getAllUsers($filters);
} catch (Exception $e) {
    error_log("User list error: " . $e->getMessage());
    $error_message = 'Failed to load users.';
    $all_users = [];
}

// Get statistics for different roles and statuses
$user_stats = [];
try {
    // Count by role
    $stmt = $pdo->prepare("SELECT role, COUNT(*) as count FROM users GROUP BY role");
    $stmt->execute();
    $role_counts = $stmt->fetchAll();
    foreach ($role_counts as $role_count) {
        $user_stats['role_' . $role_count['role']] = $role_count['count'];
    }
    
    // Count by status
    $stmt = $pdo->prepare("SELECT status, COUNT(*) as count FROM users GROUP BY status");
    $stmt->execute();
    $status_counts = $stmt->fetchAll();
    foreach ($status_counts as $status_count) {
        $user_stats['status_' . $status_count['status']] = $status_count['count'];
    }
    
} catch (PDOException $e) {
    error_log("User stats error: " . $e->getMessage());
}

// Set page title
$page_title = match($action) {
    'create' => 'Create User',
    'edit' => 'Edit User',
    default => 'Manage Users'
};

// Include header
include '../includes/header.php';
?>

<div class="container">
    <div class="dashboard-container">
        <!-- Page Header -->
        <div class="dashboard-header">
            <div>
                <h1>👥 User Management</h1>
                <p>
                    <?php if ($action === 'create'): ?>
                        Create a new user account
                    <?php elseif ($action === 'edit'): ?>
                        Edit user: <?php echo htmlspecialchars($edit_user['first_name'] . ' ' . $edit_user['last_name']); ?>
                    <?php else: ?>
                        Manage system users and their permissions
                    <?php endif; ?>
                </p>
            </div>
            <div class="dashboard-actions">
                <?php if ($action === 'list'): ?>
                    <a href="users.php?action=create" class="btn btn-primary">➕ Add New User</a>
                    <a href="dashboard.php" class="btn btn-secondary">← Dashboard</a>
                <?php else: ?>
                    <a href="users.php" class="btn btn-secondary">← Back to Users</a>
                <?php endif; ?>
            </div>
        </div>

        <!-- Success/Error Messages -->
        <?php if ($success_message): ?>
            <div class="alert alert-success">
                <span class="alert-icon">✅</span>
                <?php echo htmlspecialchars($success_message); ?>
            </div>
        <?php endif; ?>

        <?php if ($error_message): ?>
            <div class="alert alert-error">
                <span class="alert-icon">⚠️</span>
                <?php echo htmlspecialchars($error_message); ?>
            </div>
        <?php endif; ?>

        <?php if ($action === 'list'): ?>
            <!-- User Statistics -->
            <div class="dashboard-stats">
                <div class="stat-card">
                    <span class="stat-number"><?php echo count($all_users); ?></span>
                    <span class="stat-label">Total Users</span>
                </div>
                <div class="stat-card">
                    <span class="stat-number"><?php echo $user_stats['status_active'] ?? 0; ?></span>
                    <span class="stat-label">Active Users</span>
                </div>
                <div class="stat-card">
                    <span class="stat-number"><?php echo $user_stats['role_customer'] ?? 0; ?></span>
                    <span class="stat-label">Customers</span>
                </div>
                <div class="stat-card">
                    <span class="stat-number"><?php echo ($user_stats['role_admin'] ?? 0) + ($user_stats['role_employee'] ?? 0); ?></span>
                    <span class="stat-label">Staff</span>
                </div>
            </div>

            <!-- Search and Filters -->
            <div class="users-filters">
                <form method="GET" class="filters-form">
                    <div class="filters-row">
                        <div class="filter-group">
                            <label for="search">🔍 Search Users</label>
                            <input type="text" 
                                   id="search" 
                                   name="search" 
                                   value="<?php echo htmlspecialchars($search_term); ?>" 
                                   placeholder="Username, email, or name...">
                        </div>
                        
                        <div class="filter-group">
                            <label for="role">👤 Role</label>
                            <select id="role" name="role">
                                <option value="">All Roles</option>
                                <option value="admin" <?php echo $role_filter === 'admin' ? 'selected' : ''; ?>>Admin</option>
                                <option value="employee" <?php echo $role_filter === 'employee' ? 'selected' : ''; ?>>Employee</option>
                                <option value="customer" <?php echo $role_filter === 'customer' ? 'selected' : ''; ?>>Customer</option>
                            </select>
                        </div>
                        
                        <div class="filter-group">
                            <label for="status">📊 Status</label>
                            <select id="status" name="status">
                                <option value="">All Statuses</option>
                                <option value="active" <?php echo $status_filter === 'active' ? 'selected' : ''; ?>>Active</option>
                                <option value="inactive" <?php echo $status_filter === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                                <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                            </select>
                        </div>
                        
                        <div class="filter-actions">
                            <button type="submit" class="btn btn-primary">Apply Filters</button>
                            <a href="users.php" class="btn btn-secondary">Reset</a>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Users Table -->
            <div class="dashboard-section">
                <h3>👥 All Users (<?php echo count($all_users); ?>)</h3>
                <?php if (!empty($all_users)): ?>
                    <div class="table-responsive">
                        <table class="dashboard-table">
                            <thead>
                                <tr>
                                    <th>User</th>
                                    <th>Email</th>
                                    <th>Role</th>
                                    <th>Status</th>
                                    <th>Joined</th>
                                    <th>Last Login</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($all_users as $list_user): ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo htmlspecialchars($list_user['first_name'] . ' ' . $list_user['last_name']); ?></strong><br>
                                            <small>@<?php echo htmlspecialchars($list_user['username']); ?></small>
                                        </td>
                                        <td><?php echo htmlspecialchars($list_user['email']); ?></td>
                                        <td>
                                            <span class="role-badge role-<?php echo $list_user['role']; ?>">
                                                <?php echo ucfirst($list_user['role']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="status-badge status-<?php echo $list_user['status']; ?>">
                                                <?php echo ucfirst($list_user['status']); ?>
                                            </span>
                                        </td>
                                        <td><?php echo date('M j, Y', strtotime($list_user['created_at'])); ?></td>
                                        <td><?php echo $list_user['last_login'] ? date('M j, Y', strtotime($list_user['last_login'])) : 'Never'; ?></td>
                                        <td class="table-actions">
                                            <a href="users.php?action=edit&id=<?php echo $list_user['id']; ?>" 
                                               class="btn-small btn-primary">Edit</a>
                                            <?php if ($list_user['id'] !== $user['id']): ?>
                                                <?php if ($list_user['status'] === 'active'): ?>
                                                    <button onclick="confirmDeactivate(<?php echo $list_user['id']; ?>, '<?php echo htmlspecialchars($list_user['username']); ?>')" 
                                                            class="btn-small btn-danger">Deactivate</button>
                                                <?php else: ?>
                                                    <button onclick="confirmActivate(<?php echo $list_user['id']; ?>, '<?php echo htmlspecialchars($list_user['username']); ?>')" 
                                                            class="btn-small btn-success">Activate</button>
                                                <?php endif; ?>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <div class="empty-icon">👤</div>
                        <h3>No users found</h3>
                        <p>No users match your current search criteria.</p>
                        <a href="users.php?action=create" class="btn btn-primary">Create First User</a>
                    </div>
                <?php endif; ?>
            </div>

        <?php elseif ($action === 'create' || $action === 'edit'): ?>
            <!-- User Form -->
            <div class="user-form-container">
                <form method="POST" class="user-form">
                    <?php if ($action === 'edit'): ?>
                        <input type="hidden" name="user_id" value="<?php echo $edit_user['id']; ?>">
                    <?php endif; ?>

                    <div class="form-sections">
                        <!-- Personal Information -->
                        <div class="form-section">
                            <h3>👤 Personal Information</h3>
                            <div class="form-grid">
                                <div class="form-group">
                                    <label for="first_name">First Name *</label>
                                    <input type="text" 
                                           id="first_name" 
                                           name="first_name" 
                                           value="<?php echo htmlspecialchars($edit_user['first_name'] ?? ''); ?>" 
                                           required>
                                </div>
                                
                                <div class="form-group">
                                    <label for="last_name">Last Name *</label>
                                    <input type="text" 
                                           id="last_name" 
                                           name="last_name" 
                                           value="<?php echo htmlspecialchars($edit_user['last_name'] ?? ''); ?>" 
                                           required>
                                </div>
                                
                                <div class="form-group">
                                    <label for="phone">Phone Number</label>
                                    <input type="tel" 
                                           id="phone" 
                                           name="phone" 
                                           value="<?php echo htmlspecialchars($edit_user['phone'] ?? ''); ?>">
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label for="address">Address</label>
                                <textarea id="address" 
                                          name="address" 
                                          rows="3" 
                                          placeholder="Full address..."><?php echo htmlspecialchars($edit_user['address'] ?? ''); ?></textarea>
                            </div>
                        </div>

                        <!-- Account Information -->
                        <div class="form-section">
                            <h3>🔐 Account Information</h3>
                            <div class="form-grid">
                                <div class="form-group">
                                    <label for="username">Username *</label>
                                    <input type="text" 
                                           id="username" 
                                           name="username" 
                                           value="<?php echo htmlspecialchars($edit_user['username'] ?? ''); ?>" 
                                           required>
                                </div>
                                
                                <div class="form-group">
                                    <label for="email">Email Address *</label>
                                    <input type="email" 
                                           id="email" 
                                           name="email" 
                                           value="<?php echo htmlspecialchars($edit_user['email'] ?? ''); ?>" 
                                           required>
                                </div>
                            </div>
                            
                            <div class="form-grid">
                                <?php if ($action === 'create'): ?>
                                    <div class="form-group">
                                        <label for="password">Password *</label>
                                        <input type="password" 
                                               id="password" 
                                               name="password" 
                                               required>
                                        <small class="form-help">Minimum 8 characters with letters and numbers</small>
                                    </div>
                                <?php else: ?>
                                    <div class="form-group">
                                        <label for="new_password">New Password</label>
                                        <input type="password" 
                                               id="new_password" 
                                               name="new_password">
                                        <small class="form-help">Leave blank to keep current password</small>
                                    </div>
                                <?php endif; ?>
                                
                                <div class="form-group">
                                    <label for="role">User Role *</label>
                                    <select id="role" name="role" required>
                                        <option value="customer" <?php echo ($edit_user['role'] ?? '') === 'customer' ? 'selected' : ''; ?>>Customer</option>
                                        <option value="employee" <?php echo ($edit_user['role'] ?? '') === 'employee' ? 'selected' : ''; ?>>Employee</option>
                                        <option value="admin" <?php echo ($edit_user['role'] ?? '') === 'admin' ? 'selected' : ''; ?>>Admin</option>
                                    </select>
                                </div>
                                
                                <div class="form-group">
                                    <label for="status">Account Status *</label>
                                    <select id="status" name="status" required>
                                        <option value="active" <?php echo ($edit_user['status'] ?? 'active') === 'active' ? 'selected' : ''; ?>>Active</option>
                                        <option value="inactive" <?php echo ($edit_user['status'] ?? '') === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                                        <option value="pending" <?php echo ($edit_user['status'] ?? '') === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Form Actions -->
                    <div class="form-actions">
                        <?php if ($action === 'create'): ?>
                            <button type="submit" name="create_user" class="btn btn-primary">
                                ➕ Create User
                            </button>
                        <?php else: ?>
                            <button type="submit" name="update_user" class="btn btn-primary">
                                💾 Update User
                            </button>
                        <?php endif; ?>
                        
                        <a href="users.php" class="btn btn-secondary">Cancel</a>
                        
                        <?php if ($action === 'edit' && $edit_user['id'] !== $user['id']): ?>
                            <?php if ($edit_user['status'] === 'active'): ?>
                                <button type="button" 
                                        onclick="confirmDeactivate(<?php echo $edit_user['id']; ?>, '<?php echo htmlspecialchars($edit_user['username']); ?>')" 
                                        class="btn btn-danger">
                                    🚫 Deactivate User
                                </button>
                            <?php else: ?>
                                <button type="button" 
                                        onclick="confirmActivate(<?php echo $edit_user['id']; ?>, '<?php echo htmlspecialchars($edit_user['username']); ?>')" 
                                        class="btn btn-success">
                                    ✅ Activate User
                                </button>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Hidden Forms for User Actions -->
<form id="deleteForm" method="POST" style="display: none;">
    <input type="hidden" name="user_id" id="deleteUserId">
    <input type="hidden" name="delete_user" value="1">
</form>

<form id="activateForm" method="POST" style="display: none;">
    <input type="hidden" name="user_id" id="activateUserId">
    <input type="hidden" name="activate_user" value="1">
</form>

<script>
function confirmDeactivate(userId, username) {
    if (confirm(`Are you sure you want to deactivate user "${username}"? This will prevent them from logging in.`)) {
        document.getElementById('deleteUserId').value = userId;
        document.getElementById('deleteForm').submit();
    }
}

function confirmActivate(userId, username) {
    if (confirm(`Are you sure you want to activate user "${username}"? This will allow them to log in again.`)) {
        document.getElementById('activateUserId').value = userId;
        document.getElementById('activateForm').submit();
    }
}
</script>

<?php include '../includes/footer.php'; ?>
