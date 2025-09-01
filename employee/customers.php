<?php
/**
 * Employee Customer Management
 * View and assist customers
 */

// Include configuration and middleware
require_once '../config/config.php';
require_once '../includes/middleware.php';

// Require employee access
requireEmployee();
$user = getCurrentUserOrRedirect();

// Initialize services
$database = new Database();
$pdo = $database->getConnection();
$user_model = new User($pdo);
$order_model = new Order($pdo);

$success_message = '';
$error_message = '';
$action = $_GET['action'] ?? 'list';
$view_customer = null;
$customer_orders = [];

// Handle customer view
if ($action === 'view' && isset($_GET['id'])) {
    $customer_id = intval($_GET['id']);
    $view_customer = $user_model->findById($customer_id);
    
    if (!$view_customer || $view_customer['role'] !== 'customer') {
        $error_message = 'Customer not found.';
        $action = 'list';
    } else {
        // Get customer orders
        try {
            $customer_orders = $order_model->findByUserId($customer_id);
            
            // Get customer statistics
            $customer_stats = $order_model->getUserStats($customer_id);
            
        } catch (Exception $e) {
            error_log("Customer orders error: " . $e->getMessage());
            $customer_orders = [];
            $customer_stats = [];
        }
    }
}

// Get filters from query parameters
$search_term = $_GET['search'] ?? '';
$status_filter = $_GET['status'] ?? '';

// Get all customers with filters
try {
    $filters = ['role' => 'customer'];
    if ($search_term) {
        $filters['search'] = $search_term;
    }
    if ($status_filter) {
        $filters['status'] = $status_filter;
    }
    
    $all_customers = $user_model->getAllUsers($filters);
    
    // Get customer statistics
    $customer_count_stats = [];
    $stmt = $pdo->prepare("SELECT status, COUNT(*) as count FROM users WHERE role = 'customer' GROUP BY status");
    $stmt->execute();
    while ($row = $stmt->fetch()) {
        $customer_count_stats[$row['status']] = $row['count'];
    }
    
    // Get recent orders for overview
    $stmt = $pdo->prepare("
        SELECT o.*, u.first_name, u.last_name, u.email 
        FROM orders o 
        JOIN users u ON o.user_id = u.id 
        WHERE u.role = 'customer'
        ORDER BY o.created_at DESC 
        LIMIT 5
    ");
    $stmt->execute();
    $recent_customer_orders = $stmt->fetchAll();
    
} catch (Exception $e) {
    error_log("Customer management error: " . $e->getMessage());
    $error_message = 'Failed to load customers.';
    $all_customers = [];
    $customer_count_stats = [];
    $recent_customer_orders = [];
}

// Set page title
$page_title = match($action) {
    'view' => 'Customer Details',
    default => 'Customer Management'
};

// Include header
include '../includes/header.php';
?>

<div class="container">
    <div class="dashboard-container">
        <!-- Page Header -->
        <div class="dashboard-header">
            <div>
                <h1>👥 Customer Management</h1>
                <p>
                    <?php if ($action === 'view'): ?>
                        Customer details and order history
                    <?php else: ?>
                        View and assist customers with their orders
                    <?php endif; ?>
                </p>
            </div>
            <div class="dashboard-actions">
                <?php if ($action === 'list'): ?>
                    <a href="orders.php?action=create" class="btn btn-primary">➕ Create Order</a>
                    <a href="dashboard.php" class="btn btn-secondary">← Dashboard</a>
                <?php else: ?>
                    <a href="customers.php" class="btn btn-secondary">← Back to Customers</a>
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
            <!-- Customer Statistics -->
            <div class="dashboard-stats">
                <div class="stat-card">
                    <span class="stat-number"><?php echo count($all_customers); ?></span>
                    <span class="stat-label">Total Customers</span>
                </div>
                <div class="stat-card">
                    <span class="stat-number"><?php echo $customer_count_stats['active'] ?? 0; ?></span>
                    <span class="stat-label">Active</span>
                </div>
                <div class="stat-card">
                    <span class="stat-number"><?php echo $customer_count_stats['pending'] ?? 0; ?></span>
                    <span class="stat-label">Pending</span>
                </div>
                <div class="stat-card">
                    <span class="stat-number"><?php echo $customer_count_stats['inactive'] ?? 0; ?></span>
                    <span class="stat-label">Inactive</span>
                </div>
            </div>

            <!-- Search and Filters -->
            <div class="users-filters">
                <form method="GET" class="filters-form">
                    <div class="filters-row">
                        <div class="filter-group">
                            <label for="search">🔍 Search Customers</label>
                            <input type="text" 
                                   id="search" 
                                   name="search" 
                                   value="<?php echo htmlspecialchars($search_term); ?>" 
                                   placeholder="Name, email, or username...">
                        </div>
                        
                        <div class="filter-group">
                            <label for="status">📊 Status</label>
                            <select id="status" name="status">
                                <option value="">All Statuses</option>
                                <option value="active" <?php echo $status_filter === 'active' ? 'selected' : ''; ?>>Active</option>
                                <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                <option value="inactive" <?php echo $status_filter === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                            </select>
                        </div>
                        
                        <div class="filter-actions">
                            <button type="submit" class="btn btn-primary">Apply Filters</button>
                            <a href="customers.php" class="btn btn-secondary">Reset</a>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Customers Table -->
            <div class="dashboard-section">
                <h3>👥 All Customers (<?php echo count($all_customers); ?>)</h3>
                <?php if (!empty($all_customers)): ?>
                    <div class="table-responsive">
                        <table class="dashboard-table">
                            <thead>
                                <tr>
                                    <th>Customer</th>
                                    <th>Email</th>
                                    <th>Status</th>
                                    <th>Joined</th>
                                    <th>Last Login</th>
                                    <th>Orders</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($all_customers as $customer): ?>
                                    <?php
                                    // Get order count for this customer
                                    try {
                                        $stmt = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE user_id = ?");
                                        $stmt->execute([$customer['id']]);
                                        $order_count = $stmt->fetchColumn();
                                    } catch (Exception $e) {
                                        $order_count = 0;
                                    }
                                    ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo htmlspecialchars($customer['first_name'] . ' ' . $customer['last_name']); ?></strong><br>
                                            <small>@<?php echo htmlspecialchars($customer['username']); ?></small>
                                        </td>
                                        <td><?php echo htmlspecialchars($customer['email']); ?></td>
                                        <td>
                                            <span class="status-badge status-<?php echo $customer['status']; ?>">
                                                <?php echo ucfirst($customer['status']); ?>
                                            </span>
                                        </td>
                                        <td><?php echo date('M j, Y', strtotime($customer['created_at'])); ?></td>
                                        <td><?php echo $customer['last_login'] ? date('M j, Y', strtotime($customer['last_login'])) : 'Never'; ?></td>
                                        <td>
                                            <strong><?php echo $order_count; ?></strong> orders
                                        </td>
                                        <td class="table-actions">
                                            <a href="customers.php?action=view&id=<?php echo $customer['id']; ?>" class="btn-small btn-primary">View</a>
                                            <a href="orders.php?customer_id=<?php echo $customer['id']; ?>" class="btn-small btn-secondary">Orders</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <div class="empty-icon">👥</div>
                        <h3>No customers found</h3>
                        <p>No customers match your current search criteria.</p>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Recent Customer Orders -->
            <div class="dashboard-section">
                <h3>📦 Recent Customer Orders</h3>
                <?php if (!empty($recent_customer_orders)): ?>
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
                                <?php foreach ($recent_customer_orders as $order): ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo htmlspecialchars($order['order_number']); ?></strong>
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
                                            <a href="customers.php?action=view&id=<?php echo $order['user_id']; ?>" class="btn-small btn-secondary">Customer</a>
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
                        <h3>No recent orders</h3>
                        <p>Customer orders will appear here once they start placing them.</p>
                    </div>
                <?php endif; ?>
            </div>

        <?php elseif ($action === 'view' && $view_customer): ?>
            <!-- Customer Details View -->
            <div class="user-form-container">
                <!-- Customer Header -->
                <div class="order-details-header">
                    <h3>👤 Customer Profile</h3>
                    <div class="order-summary">
                        <div class="order-info">
                            <strong><?php echo htmlspecialchars($view_customer['first_name'] . ' ' . $view_customer['last_name']); ?></strong>
                            <span class="status-badge status-<?php echo $view_customer['status']; ?>">
                                <?php echo ucfirst($view_customer['status']); ?>
                            </span>
                            <p>@<?php echo htmlspecialchars($view_customer['username']); ?></p>
                        </div>
                        <div class="order-meta">
                            <p><strong>Email:</strong> <?php echo htmlspecialchars($view_customer['email']); ?></p>
                            <p><strong>Phone:</strong> <?php echo htmlspecialchars($view_customer['phone'] ?? 'Not provided'); ?></p>
                            <p><strong>Joined:</strong> <?php echo date('M j, Y', strtotime($view_customer['created_at'])); ?></p>
                            <p><strong>Last Login:</strong> <?php echo $view_customer['last_login'] ? date('M j, Y g:i A', strtotime($view_customer['last_login'])) : 'Never'; ?></p>
                        </div>
                    </div>
                </div>

                <!-- Customer Statistics -->
                <div class="dashboard-stats">
                    <div class="stat-card">
                        <span class="stat-number"><?php echo $customer_stats['total_orders'] ?? 0; ?></span>
                        <span class="stat-label">Total Orders</span>
                    </div>
                    <div class="stat-card">
                        <span class="stat-number"><?php echo $customer_stats['pending_orders'] ?? 0; ?></span>
                        <span class="stat-label">Pending</span>
                    </div>
                    <div class="stat-card">
                        <span class="stat-number"><?php echo $customer_stats['delivered_orders'] ?? 0; ?></span>
                        <span class="stat-label">Delivered</span>
                    </div>
                    <div class="stat-card">
                        <span class="stat-number">$<?php echo number_format($customer_stats['total_spent'] ?? 0, 2); ?></span>
                        <span class="stat-label">Total Spent</span>
                    </div>
                </div>

                <!-- Customer Address -->
                <?php if ($view_customer['address']): ?>
                    <div class="dashboard-section">
                        <h3>📍 Customer Address</h3>
                        <div class="address-card">
                            <p><?php echo nl2br(htmlspecialchars($view_customer['address'])); ?></p>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Customer Orders -->
                <div class="dashboard-section">
                    <h3>📦 Order History (<?php echo count($customer_orders); ?>)</h3>
                    <?php if (!empty($customer_orders)): ?>
                        <div class="table-responsive">
                            <table class="dashboard-table">
                                <thead>
                                    <tr>
                                        <th>Order #</th>
                                        <th>Transport</th>
                                        <th>Status</th>
                                        <th>Total</th>
                                        <th>Created</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($customer_orders as $order): ?>
                                        <tr>
                                            <td>
                                                <strong><?php echo htmlspecialchars($order['order_number']); ?></strong><br>
                                                <?php if ($order['tracking_number']): ?>
                                                    <small>Track: <?php echo htmlspecialchars($order['tracking_number']); ?></small>
                                                <?php endif; ?>
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
                                            <?php if (in_array($order['status'], ['pending', 'confirmed'])): ?>
                                                <a href="orders.php?action=edit&id=<?php echo $order['id']; ?>" class="btn-small btn-success">Process</a>
                                            <?php endif; ?>
                                        </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <div class="text-center" style="margin-top: 2rem;">
                            <a href="orders.php?action=create&customer_id=<?php echo $view_customer['id']; ?>" class="btn btn-primary">
                                ➕ Create New Order for Customer
                            </a>
                        </div>
                    <?php else: ?>
                        <div class="empty-state">
                            <div class="empty-icon">📦</div>
                            <h3>No orders yet</h3>
                            <p>This customer hasn't placed any orders yet.</p>
                            <a href="orders.php?action=create&customer_id=<?php echo $view_customer['id']; ?>" class="btn btn-primary">Create First Order</a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
