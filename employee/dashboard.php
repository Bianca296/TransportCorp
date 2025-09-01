<?php
/**
 * Employee Dashboard
 */

// Configuration
require_once '../config/config.php';
require_once '../includes/middleware.php';

// Require employee access (includes admin)
requireEmployee();
$user = getCurrentUserOrRedirect();

// Get database connection for stats
$database = new Database();
$pdo = $database->getConnection();

// Initialize Order model for statistics
$order_model = new Order($pdo);

// Get employee statistics
$stats = [];

try {
    // Order statistics
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM orders");
    $stmt->execute();
    $stats['total_orders'] = $stmt->fetchColumn();
    
    $stmt = $pdo->prepare("SELECT COUNT(*) as pending FROM orders WHERE status = 'pending'");
    $stmt->execute();
    $stats['pending_orders'] = $stmt->fetchColumn();
    
    $stmt = $pdo->prepare("SELECT COUNT(*) as confirmed FROM orders WHERE status = 'confirmed'");
    $stmt->execute();
    $stats['confirmed_orders'] = $stmt->fetchColumn();
    
    $stmt = $pdo->prepare("SELECT COUNT(*) as today FROM orders WHERE DATE(created_at) = CURDATE()");
    $stmt->execute();
    $stats['orders_today'] = $stmt->fetchColumn();
    
    // Customer statistics
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM users WHERE role = 'customer'");
    $stmt->execute();
    $stats['total_customers'] = $stmt->fetchColumn();
    
    $stmt = $pdo->prepare("SELECT COUNT(*) as active FROM users WHERE role = 'customer' AND status = 'active'");
    $stmt->execute();
    $stats['active_customers'] = $stmt->fetchColumn();
    
    // Transport type distribution
    $stmt = $pdo->prepare("SELECT transport_type, COUNT(*) as count FROM orders GROUP BY transport_type");
    $stmt->execute();
    $transport_stats = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    
    // Recent orders (last 10)
    $stmt = $pdo->prepare("
        SELECT o.*, u.first_name, u.last_name, u.email 
        FROM orders o 
        JOIN users u ON o.user_id = u.id 
        ORDER BY o.created_at DESC 
        LIMIT 10
    ");
    $stmt->execute();
    $recent_orders = $stmt->fetchAll();
    
    // Recent customers
    $stmt = $pdo->prepare("SELECT id, username, email, first_name, last_name, created_at, last_login FROM users WHERE role = 'customer' ORDER BY created_at DESC LIMIT 8");
    $stmt->execute();
    $recent_customers = $stmt->fetchAll();
    
} catch (PDOException $e) {
    error_log("Employee dashboard stats error: " . $e->getMessage());
    $stats = [
        'total_orders' => 'N/A',
        'pending_orders' => 'N/A',
        'confirmed_orders' => 'N/A',
        'orders_today' => 'N/A',
        'total_customers' => 'N/A',
        'active_customers' => 'N/A'
    ];
    $transport_stats = [];
    $recent_orders = [];
    $recent_customers = [];
}

// Set page title
$page_title = 'Employee Dashboard';

// Include header
include '../includes/header.php';
?>

<div class="container">
    <div class="dashboard-container">
        <!-- Dashboard Header -->
        <div class="dashboard-header">
            <div>
                <h1>👷‍♀️ Employee Dashboard</h1>
                <p>Welcome back, <?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?>!</p>
                <small>Role: <?php echo ucfirst($user['role']); ?></small>
            </div>
            <div class="dashboard-actions">
                <a href="orders.php" class="btn btn-primary">Manage Orders</a>
                <a href="customers.php" class="btn btn-secondary">Customer Support</a>
            </div>
        </div>

        <!-- Work Statistics -->
        <div class="dashboard-stats">
            <div class="stat-card">
                <span class="stat-number"><?php echo $stats['total_orders']; ?></span>
                <span class="stat-label">Total Orders</span>
            </div>
            <div class="stat-card">
                <span class="stat-number"><?php echo $stats['pending_orders']; ?></span>
                <span class="stat-label">Pending Orders</span>
            </div>
            <div class="stat-card">
                <span class="stat-number"><?php echo $stats['confirmed_orders']; ?></span>
                <span class="stat-label">In Progress</span>
            </div>
            <div class="stat-card">
                <span class="stat-number"><?php echo $stats['orders_today']; ?></span>
                <span class="stat-label">Orders Today</span>
            </div>
        </div>

        <div class="dashboard-content">
            <div class="dashboard-section">
                <h3>🚀 Quick Actions</h3>
                <div class="quick-actions">
                    <a href="orders.php?action=create" class="action-card">
                        <h4>📦 Create Order</h4>
                        <p>Process new shipping order</p>
                    </a>
                    <a href="orders.php?status=pending" class="action-card">
                        <h4>⏳ Pending Orders</h4>
                        <p>Review pending shipments</p>
                    </a>
                    <a href="tracking.php" class="action-card">
                        <h4>🗺️ Track Shipments</h4>
                        <p>Monitor active deliveries</p>
                    </a>
                    <a href="customers.php" class="action-card">
                        <h4>🎧 Customer Support</h4>
                        <p>Handle customer inquiries</p>
                    </a>
                </div>
            </div>

            <div class="dashboard-section">
                <h3>📊 Transport Overview</h3>
                <div class="overview-grid">
                    <div class="overview-item">
                        <h4>🚚 Land Transport</h4>
                        <p><strong><?php echo $transport_stats['land'] ?? 0; ?></strong> orders</p>
                        <small>Ground shipping & delivery</small>
                    </div>
                    <div class="overview-item">
                        <h4>✈️ Air Transport</h4>
                        <p><strong><?php echo $transport_stats['air'] ?? 0; ?></strong> orders</p>
                        <small>Express air freight</small>
                    </div>
                    <div class="overview-item">
                        <h4>🚢 Ocean Transport</h4>
                        <p><strong><?php echo $transport_stats['ocean'] ?? 0; ?></strong> orders</p>
                        <small>International sea cargo</small>
                    </div>
                </div>
            </div>

            <div class="dashboard-section">
                <h3>📦 Recent Orders</h3>
                <?php if (!empty($recent_orders)): ?>
                    <div class="table-responsive">
                        <table class="dashboard-table">
                            <thead>
                                <tr>
                                    <th>Order #</th>
                                    <th>Customer</th>
                                    <th>Transport</th>
                                    <th>Status</th>
                                    <th>Total</th>
                                    <th>Created</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recent_orders as $order): ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo htmlspecialchars($order['order_number']); ?></strong><br>
                                            <?php if ($order['tracking_number']): ?>
                                                <small>Track: <?php echo htmlspecialchars($order['tracking_number']); ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <strong><?php echo htmlspecialchars($order['first_name'] . ' ' . $order['last_name']); ?></strong><br>
                                            <small><?php echo htmlspecialchars($order['email']); ?></small>
                                        </td>
                                        <td>
                                            <span class="transport-badge transport-<?php echo $order['transport_type']; ?>">
                                                <?php echo Order::getTransportIcon($order['transport_type']); ?>
                                                <?php echo Order::getTransportLabel($order['transport_type']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="status-badge status-<?php echo $order['status']; ?>">
                                                <?php echo Order::getStatusLabel($order['status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <strong>$<?php echo number_format($order['total_cost'], 2); ?></strong>
                                        </td>
                                        <td>
                                            <?php echo date('M j, Y', strtotime($order['created_at'])); ?><br>
                                            <small><?php echo date('g:i A', strtotime($order['created_at'])); ?></small>
                                        </td>
                                        <td class="table-actions">
                                            <a href="view-order.php?id=<?php echo $order['id']; ?>" class="btn-small btn-primary">View</a>
                                            <?php if ($order['status'] === 'pending'): ?>
                                                <a href="orders.php?action=edit&id=<?php echo $order['id']; ?>" class="btn-small btn-success">Process</a>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <p class="text-center">
                        <a href="orders.php" class="btn btn-outline">View All Orders</a>
                    </p>
                <?php else: ?>
                    <div class="empty-state">
                        <div class="empty-icon">📦</div>
                        <h3>No orders yet</h3>
                        <p>Orders will appear here once customers start placing them.</p>
                        <a href="orders.php?action=create" class="btn btn-primary">Create First Order</a>
                    </div>
                <?php endif; ?>
            </div>

            <div class="dashboard-section">
                <h3>👥 Recent Customers</h3>
                <?php if (!empty($recent_customers)): ?>
                    <div class="table-responsive">
                        <table class="dashboard-table">
                            <thead>
                                <tr>
                                    <th>Customer</th>
                                    <th>Email</th>
                                    <th>Joined</th>
                                    <th>Last Activity</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recent_customers as $customer): ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo htmlspecialchars($customer['first_name'] . ' ' . $customer['last_name']); ?></strong><br>
                                            <small>@<?php echo htmlspecialchars($customer['username']); ?></small>
                                        </td>
                                        <td><?php echo htmlspecialchars($customer['email']); ?></td>
                                        <td><?php echo date('M j, Y', strtotime($customer['created_at'])); ?></td>
                                        <td><?php echo $customer['last_login'] ? date('M j, Y', strtotime($customer['last_login'])) : 'Never'; ?></td>
                                        <td>
                                            <a href="customers.php?action=view&id=<?php echo $customer['id']; ?>" class="btn-small">View</a>
                                            <a href="orders.php?customer_id=<?php echo $customer['id']; ?>" class="btn-small">Orders</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <p class="text-center">
                        <a href="customers.php" class="btn btn-outline">View All Customers</a>
                    </p>
                <?php else: ?>
                    <p>No customers found.</p>
                <?php endif; ?>
            </div>

            <div class="dashboard-section">
                <h3>📝 Quick Notes</h3>
                <div class="notes-section">
                    <p><strong>System Status:</strong> All transport systems operational</p>
                    <p><strong>Priority:</strong> Order management system coming soon</p>
                    <p><strong>Training:</strong> New employee onboarding available</p>
                    
                    <?php if ($user['role'] === 'admin'): ?>
                        <p class="admin-note">
                            <strong>Admin Access:</strong> You have administrative privileges. 
                            <a href="../admin/dashboard.php">Switch to Admin Dashboard</a>
                        </p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
// Include footer
include '../includes/footer.php';
?>
