<?php
/**
 * Customer Dashboard
 */

// Configuration
require_once '../config/config.php';
require_once '../includes/middleware.php';

// Require customer access (or admin/employee who can view customer interface)
$user = getCurrentUserOrRedirect();

// For non-customers, ensure they have permission to view this
if ($user['role'] !== 'customer' && $user['role'] !== 'admin' && $user['role'] !== 'employee') {
    accessDenied();
}

// Initialize services
$database = new Database();
$pdo = $database->getConnection();
$order_model = new Order($pdo);

// Get customer statistics
$stats = [];

try {
    // Account age
    $stats['account_age'] = ceil((time() - strtotime($user['created_at'])) / (24 * 60 * 60));
    
    // Get order statistics using Order model
    $order_stats = $order_model->getUserStats($user['id']);
    $stats = array_merge($stats, $order_stats);
    
} catch (Exception $e) {
    error_log("Customer dashboard stats error: " . $e->getMessage());
    $stats = [
        'account_age' => 'N/A',
        'total_orders' => 'N/A',
        'active_shipments' => 'N/A',
        'last_order' => 'N/A'
    ];
}

// Set page title
$page_title = 'Customer Dashboard';

// Include header
include '../includes/header.php';
?>

<div class="container">
    <div class="dashboard-container">
        <!-- Dashboard Header -->
        <div class="dashboard-header">
            <div>
                <h1>🏠 My Dashboard</h1>
                <p>Welcome back, <?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?>!</p>
                <small>Account: <?php echo htmlspecialchars($user['email']); ?></small>
            </div>
            <div class="dashboard-actions">
                <a href="create-order.php" class="btn btn-primary">Create Order</a>
                <a href="profile.php" class="btn btn-secondary">My Profile</a>
            </div>
        </div>

        <!-- Account Statistics -->
        <div class="dashboard-stats">
            <div class="stat-card">
                <span class="stat-number"><?php echo $stats['account_age']; ?></span>
                <span class="stat-label">Days with us</span>
            </div>
            <div class="stat-card">
                <span class="stat-number"><?php echo $stats['total_orders']; ?></span>
                <span class="stat-label">Total Orders</span>
            </div>
            <div class="stat-card">
                <span class="stat-number"><?php echo $stats['active_shipments']; ?></span>
                <span class="stat-label">Active Shipments</span>
            </div>
            <div class="stat-card">
                <span class="stat-number"><?php echo ucfirst($user['status']); ?></span>
                <span class="stat-label">Account Status</span>
            </div>
        </div>

        <div class="dashboard-content">
            <div class="dashboard-section">
                <h3>🚀 Quick Actions</h3>
                <div class="quick-actions">
                    <a href="create-order.php?type=land" class="action-card">
                        <h4>🚚 Land Transport</h4>
                        <p>Ship by truck or train</p>
                    </a>
                    <a href="create-order.php?type=air" class="action-card">
                        <h4>✈️ Air Transport</h4>
                        <p>Fast air shipping</p>
                    </a>
                    <a href="create-order.php?type=ocean" class="action-card">
                        <h4>🚢 Ocean Transport</h4>
                        <p>Cost-effective sea freight</p>
                    </a>
                    <a href="track-shipment.php" class="action-card">
                        <h4>📍 Track Shipment</h4>
                        <p>Monitor your packages</p>
                    </a>
                </div>
            </div>

            <div class="dashboard-section">
                <h3>📦 Recent Orders</h3>
                <?php
                // Get recent orders using Order model
                $recent_orders = $order_model->findByUserId($user['id'], 5);
                
                if (!empty($recent_orders)): ?>
                    <div class="orders-list">
                        <?php foreach ($recent_orders as $order): ?>
                            <div class="order-item">
                                <div class="order-header">
                                    <span class="order-number"><?php echo htmlspecialchars($order['order_number']); ?></span>
                                    <span class="order-status status-<?php echo $order['status']; ?>">
                                        <?php echo Order::getStatusLabel($order['status']); ?>
                                    </span>
                                </div>
                                <div class="order-details">
                                    <p><strong>Transport:</strong> <?php echo Order::getTransportLabel($order['transport_type']); ?></p>
                                    <p><strong>Weight:</strong> <?php echo $order['package_weight']; ?>kg</p>
                                    <p><strong>Cost:</strong> $<?php echo number_format($order['total_cost'], 2); ?></p>
                                    <p><strong>Created:</strong> <?php echo date('M j, Y', strtotime($order['created_at'])); ?></p>
                                </div>
                                <div class="order-actions-mini">
                                    <a href="view-order.php?id=<?php echo $order['id']; ?>" class="btn-small">View Details</a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <p class="text-center" style="margin-top: 1rem;">
                        <a href="orders.php" class="btn btn-outline">View All Orders</a>
                    </p>
                <?php else: ?>
                    <div class="empty-state">
                        <div class="empty-icon">📦</div>
                        <h4>No orders yet</h4>
                        <p>Ready to ship something? Create your first order to get started with our reliable transport services.</p>
                        <a href="create-order.php" class="btn btn-primary">Create First Order</a>
                    </div>
                <?php endif; ?>
            </div>

            <div class="dashboard-section">
                <h3>🎯 Transport Services</h3>
                <div class="services-overview">
                    <div class="service-item">
                        <div class="service-icon">🚚</div>
                        <div class="service-info">
                            <h4>Land Transport</h4>
                            <p>Reliable ground shipping for domestic and cross-border deliveries. Perfect for time-sensitive shipments within the region.</p>
                            <ul>
                                <li>✓ Door-to-door delivery</li>
                                <li>✓ Real-time tracking</li>
                                <li>✓ Express options available</li>
                            </ul>
                        </div>
                    </div>
                    
                    <div class="service-item">
                        <div class="service-icon">✈️</div>
                        <div class="service-info">
                            <h4>Air Transport</h4>
                            <p>Fast international shipping with global reach. Ideal for urgent deliveries and high-value items.</p>
                            <ul>
                                <li>✓ Global destinations</li>
                                <li>✓ 1-3 day delivery</li>
                                <li>✓ Secure handling</li>
                            </ul>
                        </div>
                    </div>
                    
                    <div class="service-item">
                        <div class="service-icon">🚢</div>
                        <div class="service-info">
                            <h4>Ocean Transport</h4>
                            <p>Cost-effective solution for large shipments. Best for non-urgent, bulk cargo transportation.</p>
                            <ul>
                                <li>✓ Lowest cost option</li>
                                <li>✓ Large capacity</li>
                                <li>✓ Worldwide ports</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>

            <div class="dashboard-section">
                <h3>👤 Account Information</h3>
                <div class="account-info">
                    <div class="info-grid">
                        <div class="info-item">
                            <strong>Name:</strong>
                            <span><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></span>
                        </div>
                        <div class="info-item">
                            <strong>Email:</strong>
                            <span><?php echo htmlspecialchars($user['email']); ?></span>
                        </div>
                        <div class="info-item">
                            <strong>Username:</strong>
                            <span><?php echo htmlspecialchars($user['username']); ?></span>
                        </div>
                        <div class="info-item">
                            <strong>Member Since:</strong>
                            <span><?php echo date('F j, Y', strtotime($user['created_at'])); ?></span>
                        </div>
                        <?php if (!empty($user['phone'])): ?>
                        <div class="info-item">
                            <strong>Phone:</strong>
                            <span><?php echo htmlspecialchars($user['phone']); ?></span>
                        </div>
                        <?php endif; ?>
                        <?php if (!empty($user['address'])): ?>
                        <div class="info-item">
                            <strong>Address:</strong>
                            <span><?php echo htmlspecialchars($user['address']); ?></span>
                        </div>
                        <?php endif; ?>
                    </div>
                    <p class="text-center" style="margin-top: 1rem;">
                        <a href="profile.php" class="btn btn-outline">Update Profile</a>
                    </p>
                </div>
            </div>

            <?php if ($user['role'] !== 'customer'): ?>
            <div class="dashboard-section">
                <h3>🔧 Staff Access</h3>
                <div class="staff-access">
                    <p><strong>You are viewing the customer interface.</strong></p>
                    <p>Return to your staff dashboard:</p>
                    <?php if ($user['role'] === 'admin'): ?>
                        <a href="../admin/dashboard.php" class="btn btn-primary">Admin Dashboard</a>
                    <?php elseif ($user['role'] === 'employee'): ?>
                        <a href="../employee/dashboard.php" class="btn btn-primary">Employee Dashboard</a>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php
// Include footer
include '../includes/footer.php';
?>
