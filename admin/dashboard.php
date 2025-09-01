<?php
/**
 * Admin Dashboard
 */

// Configuration
require_once '../config/config.php';
require_once '../includes/middleware.php';

// Require admin access
$user = dashboardAccessControl('admin');

// Get database connection for stats
$database = new Database();
$pdo = $database->getConnection();

// Get system statistics
$stats = [];

try {
    // Total users
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM users");
    $stmt->execute();
    $stats['total_users'] = $stmt->fetchColumn();
    
    // Active users
    $stmt = $pdo->prepare("SELECT COUNT(*) as active FROM users WHERE status = 'active'");
    $stmt->execute();
    $stats['active_users'] = $stmt->fetchColumn();
    
    // New users this month
    $stmt = $pdo->prepare("SELECT COUNT(*) as new_users FROM users WHERE created_at >= DATE_SUB(NOW(), INTERVAL 1 MONTH)");
    $stmt->execute();
    $stats['new_users_month'] = $stmt->fetchColumn();
    
    // Recent login attempts
    $stmt = $pdo->prepare("SELECT COUNT(*) as attempts FROM login_attempts WHERE attempted_at >= DATE_SUB(NOW(), INTERVAL 1 DAY)");
    $stmt->execute();
    $stats['login_attempts_day'] = $stmt->fetchColumn();
    
    // Get recent users
    $stmt = $pdo->prepare("SELECT id, username, email, first_name, last_name, role, status, created_at, last_login FROM users ORDER BY created_at DESC LIMIT 10");
    $stmt->execute();
    $recent_users = $stmt->fetchAll();
    
    // Get user role distribution
    $stmt = $pdo->prepare("SELECT role, COUNT(*) as count FROM users GROUP BY role");
    $stmt->execute();
    $role_distribution = $stmt->fetchAll();
    
} catch (PDOException $e) {
    error_log("Dashboard stats error: " . $e->getMessage());
    $stats = [
        'total_users' => 'N/A',
        'active_users' => 'N/A',
        'new_users_month' => 'N/A',
        'login_attempts_day' => 'N/A'
    ];
    $recent_users = [];
    $role_distribution = [];
}

// Set page title
$page_title = 'Admin Dashboard';

// Include header
include '../includes/header.php';
?>

<div class="container">
    <div class="dashboard-container">
        <!-- Dashboard Header -->
        <div class="dashboard-header">
            <div>
                <h1>👨‍💼 Admin Dashboard</h1>
                <p>Welcome back, <?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?>!</p>
            </div>
            <div class="dashboard-actions">
                <a href="users.php" class="btn btn-primary">Manage Users</a>
                <a href="settings.php" class="btn btn-secondary">System Settings</a>
            </div>
        </div>

        <!-- System Statistics -->
        <div class="dashboard-stats">
            <div class="stat-card">
                <span class="stat-number"><?php echo $stats['total_users']; ?></span>
                <span class="stat-label">Total Users</span>
            </div>
            <div class="stat-card">
                <span class="stat-number"><?php echo $stats['active_users']; ?></span>
                <span class="stat-label">Active Users</span>
            </div>
            <div class="stat-card">
                <span class="stat-number"><?php echo $stats['new_users_month']; ?></span>
                <span class="stat-label">New This Month</span>
            </div>
            <div class="stat-card">
                <span class="stat-number"><?php echo $stats['login_attempts_day']; ?></span>
                <span class="stat-label">Login Attempts (24h)</span>
            </div>
        </div>

        <div class="dashboard-content">
            <div class="dashboard-section">
                <h3>🔧 Quick Actions</h3>
                <div class="quick-actions">
                    <a href="users.php?action=create" class="action-card">
                        <h4>➕ Add New User</h4>
                        <p>Create a new user account</p>
                    </a>
                    <a href="../database/migrate.php" class="action-card">
                        <h4>🗄️ Database Migrations</h4>
                        <p>Manage database schema</p>
                    </a>
                    <a href="reports.php" class="action-card">
                        <h4>📊 System Reports</h4>
                        <p>View system analytics</p>
                    </a>
                    <a href="backup.php" class="action-card">
                        <h4>💾 Backup System</h4>
                        <p>Create system backup</p>
                    </a>
                </div>
            </div>

            <div class="dashboard-section">
                <h3>👥 Recent Users</h3>
                <?php if (!empty($recent_users)): ?>
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
                                <?php foreach ($recent_users as $recent_user): ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo htmlspecialchars($recent_user['first_name'] . ' ' . $recent_user['last_name']); ?></strong><br>
                                            <small>@<?php echo htmlspecialchars($recent_user['username']); ?></small>
                                        </td>
                                        <td><?php echo htmlspecialchars($recent_user['email']); ?></td>
                                        <td>
                                            <span class="role-badge role-<?php echo $recent_user['role']; ?>">
                                                <?php echo ucfirst($recent_user['role']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="status-badge status-<?php echo $recent_user['status']; ?>">
                                                <?php echo ucfirst($recent_user['status']); ?>
                                            </span>
                                        </td>
                                        <td><?php echo date('M j, Y', strtotime($recent_user['created_at'])); ?></td>
                                        <td><?php echo $recent_user['last_login'] ? date('M j, Y', strtotime($recent_user['last_login'])) : 'Never'; ?></td>
                                        <td>
                                            <a href="users.php?action=edit&id=<?php echo $recent_user['id']; ?>" class="btn-small">Edit</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <p class="text-center">
                        <a href="users.php" class="btn btn-outline">View All Users</a>
                    </p>
                <?php else: ?>
                    <p>No users found.</p>
                <?php endif; ?>
            </div>

            <div class="dashboard-section">
                <h3>📈 User Distribution</h3>
                <?php if (!empty($role_distribution)): ?>
                    <div class="role-distribution">
                        <?php foreach ($role_distribution as $role_data): ?>
                            <div class="role-stat">
                                <div class="role-info">
                                    <span class="role-name"><?php echo ucfirst($role_data['role']); ?>s</span>
                                    <span class="role-count"><?php echo $role_data['count']; ?></span>
                                </div>
                                <div class="role-bar">
                                    <?php $percentage = ($role_data['count'] / $stats['total_users']) * 100; ?>
                                    <div class="role-progress role-<?php echo $role_data['role']; ?>" 
                                         style="width: <?php echo $percentage; ?>%"></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p>No role data available.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php
// Include footer
include '../includes/footer.php';
?>
