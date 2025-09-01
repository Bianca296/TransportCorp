<?php
/**
 * View Order Details Page
 * Display detailed order information with cancellation option
 */

// Include configuration and middleware
require_once '../config/config.php';
require_once '../includes/middleware.php';

// Get current user (allow admin/employee for support)
$user = getCurrentUserOrRedirect();

// Initialize services
$database = new Database();
$pdo = $database->getConnection();
$order_model = new Order($pdo);

$error_message = '';
$success_message = '';
$order = null;

// Get order ID from URL
$order_id = $_GET['id'] ?? null;

if (!$order_id || !is_numeric($order_id)) {
    $error_message = 'Invalid order ID provided.';
} else {
    // Get order details
    $order = $order_model->findById($order_id);
    
    if (!$order) {
        $error_message = 'Order not found.';
    } else {
        // Security check: customers can only view their own orders
        // Admin and employees can view any order for support purposes
        if ($user['role'] === 'customer' && $order['user_id'] != $user['id']) {
            $error_message = 'You are not authorized to view this order.';
            $order = null;
        }
    }
}

// Handle order cancellation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancel_order']) && $order) {
    // Security checks
    if ($user['role'] === 'customer' && $order['user_id'] != $user['id']) {
        $error_message = 'You are not authorized to cancel this order.';
    } elseif ($order['status'] !== 'pending') {
        $error_message = 'Only pending orders can be cancelled.';
    } else {
        // Cancel the order
        $cancel_result = $order_model->cancelOrder($order_id);
        
        if ($cancel_result) {
            $success_message = 'Order has been successfully cancelled.';
            // Refresh order data
            $order = $order_model->findById($order_id);
        } else {
            $error_message = 'Failed to cancel order. Please try again.';
        }
    }
}

// Set page title
$page_title = $order ? 'Order ' . $order['order_number'] : 'View Order';

// Include header
include '../includes/header.php';
?>

<div class="order-view-container">
    <div class="order-view-header">
        <div class="header-content">
            <div class="breadcrumb">
                <a href="dashboard.php">Dashboard</a>
                <span class="separator">›</span>
                <span class="current">Order Details</span>
            </div>
            
            <?php if ($order): ?>
                <div class="order-title">
                    <h1>Order <?php echo htmlspecialchars($order['order_number']); ?></h1>
                    <span class="order-status status-<?php echo $order['status']; ?>">
                        <?php echo Order::getStatusLabel($order['status']); ?>
                    </span>
                </div>
            <?php else: ?>
                <h1>Order Details</h1>
            <?php endif; ?>
        </div>
    </div>

    <div class="order-view-content">
        <?php if ($error_message): ?>
            <div class="alert alert-error">
                <span class="alert-icon">⚠️</span>
                <?php echo htmlspecialchars($error_message); ?>
            </div>
            
            <div class="error-actions">
                <a href="dashboard.php" class="btn btn-primary">← Back to Dashboard</a>
            </div>
        <?php endif; ?>

        <?php if ($success_message): ?>
            <div class="alert alert-success">
                <span class="alert-icon">✅</span>
                <?php echo htmlspecialchars($success_message); ?>
            </div>
        <?php endif; ?>

        <?php if ($order): ?>
            <div class="order-details-grid">
                <!-- Order Information -->
                <div class="order-section">
                    <div class="section-header">
                        <h3>📦 Order Information</h3>
                    </div>
                    <div class="section-content">
                        <div class="detail-row">
                            <span class="label">Order Number:</span>
                            <span class="value"><?php echo htmlspecialchars($order['order_number']); ?></span>
                        </div>
                        <div class="detail-row">
                            <span class="label">Status:</span>
                            <span class="value">
                                <span class="status-badge status-<?php echo $order['status']; ?>">
                                    <?php echo Order::getStatusLabel($order['status']); ?>
                                </span>
                            </span>
                        </div>
                        <div class="detail-row">
                            <span class="label">Created:</span>
                            <span class="value"><?php echo date('F j, Y \a\t g:i A', strtotime($order['created_at'])); ?></span>
                        </div>
                        <?php if ($order['updated_at'] && $order['updated_at'] !== $order['created_at']): ?>
                        <div class="detail-row">
                            <span class="label">Last Updated:</span>
                            <span class="value"><?php echo date('F j, Y \a\t g:i A', strtotime($order['updated_at'])); ?></span>
                        </div>
                        <?php endif; ?>
                        <?php if ($order['tracking_number']): ?>
                        <div class="detail-row">
                            <span class="label">Tracking Number:</span>
                            <span class="value tracking-number"><?php echo htmlspecialchars($order['tracking_number']); ?></span>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Shipping Details -->
                <div class="order-section">
                    <div class="section-header">
                        <h3>🚚 Shipping Details</h3>
                    </div>
                    <div class="section-content">
                        <div class="detail-row">
                            <span class="label">Transport Type:</span>
                            <span class="value"><?php echo Order::getTransportLabel($order['transport_type']); ?></span>
                        </div>
                        <div class="detail-row">
                            <span class="label">Urgent Delivery:</span>
                            <span class="value">
                                <?php if ($order['urgent_delivery']): ?>
                                    <span class="urgent-badge">🚨 Yes</span>
                                <?php else: ?>
                                    <span class="standard-badge">📅 Standard</span>
                                <?php endif; ?>
                            </span>
                        </div>
                        <div class="address-section">
                            <div class="address-block">
                                <h4>📍 Pickup Address</h4>
                                <p><?php echo nl2br(htmlspecialchars($order['pickup_address'])); ?></p>
                            </div>
                            <div class="address-block">
                                <h4>🏠 Delivery Address</h4>
                                <p><?php echo nl2br(htmlspecialchars($order['delivery_address'])); ?></p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Package Information -->
                <div class="order-section">
                    <div class="section-header">
                        <h3>📋 Package Information</h3>
                    </div>
                    <div class="section-content">
                        <div class="detail-row">
                            <span class="label">Weight:</span>
                            <span class="value"><?php echo $order['package_weight']; ?> kg</span>
                        </div>
                        <?php if ($order['package_length'] || $order['package_width'] || $order['package_height']): ?>
                        <div class="detail-row">
                            <span class="label">Dimensions:</span>
                            <span class="value">
                                <?php 
                                $dimensions = [];
                                if ($order['package_length']) $dimensions[] = $order['package_length'] . 'cm (L)';
                                if ($order['package_width']) $dimensions[] = $order['package_width'] . 'cm (W)';
                                if ($order['package_height']) $dimensions[] = $order['package_height'] . 'cm (H)';
                                echo implode(' × ', $dimensions);
                                ?>
                            </span>
                        </div>
                        <?php endif; ?>
                        <div class="detail-row">
                            <span class="label">Description:</span>
                            <span class="value"><?php echo nl2br(htmlspecialchars($order['package_description'])); ?></span>
                        </div>
                        <?php if ($order['special_instructions']): ?>
                        <div class="detail-row">
                            <span class="label">Special Instructions:</span>
                            <span class="value"><?php echo nl2br(htmlspecialchars($order['special_instructions'])); ?></span>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Cost Breakdown -->
                <div class="order-section">
                    <div class="section-header">
                        <h3>💰 Cost Breakdown</h3>
                    </div>
                    <div class="section-content">
                        <div class="cost-breakdown">
                            <div class="cost-row">
                                <span class="cost-label">Base Shipping Cost:</span>
                                <span class="cost-value">$<?php echo number_format($order['base_cost'], 2); ?></span>
                            </div>
                            <?php if ($order['urgent_surcharge'] > 0): ?>
                            <div class="cost-row">
                                <span class="cost-label">Urgent Delivery Surcharge:</span>
                                <span class="cost-value">$<?php echo number_format($order['urgent_surcharge'], 2); ?></span>
                            </div>
                            <?php endif; ?>
                            <div class="cost-row total-row">
                                <span class="cost-label"><strong>Total Cost:</strong></span>
                                <span class="cost-value total-cost"><strong>$<?php echo number_format($order['total_cost'], 2); ?></strong></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Action Buttons -->
            <div class="order-actions">
                <div class="action-buttons">
                    <a href="dashboard.php" class="btn btn-secondary">← Back to Dashboard</a>
                    
                    <?php if ($order['status'] === 'pending' && ($user['role'] === 'customer' && $order['user_id'] == $user['id']) || $user['role'] !== 'customer'): ?>
                        <button type="button" class="btn btn-danger" onclick="confirmCancelOrder()">
                            🗑️ Cancel Order
                        </button>
                    <?php endif; ?>
                    
                    <?php if ($order['tracking_number']): ?>
                        <a href="#" class="btn btn-primary" onclick="trackPackage('<?php echo htmlspecialchars($order['tracking_number']); ?>')">
                            📍 Track Package
                        </a>
                    <?php endif; ?>
                </div>

                <?php if ($order['status'] === 'pending'): ?>
                    <div class="order-note">
                        <p><strong>Note:</strong> Your order is pending confirmation. You can cancel it at any time before it's confirmed.</p>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Hidden Cancel Form -->
            <form id="cancelForm" method="POST" style="display: none;">
                <input type="hidden" name="cancel_order" value="1">
            </form>
        <?php endif; ?>
    </div>
</div>

<script>
function confirmCancelOrder() {
    if (confirm('Are you sure you want to cancel this order? This action cannot be undone.')) {
        document.getElementById('cancelForm').submit();
    }
}

function trackPackage(trackingNumber) {
    // Placeholder for tracking functionality
    alert('Tracking Number: ' + trackingNumber + '\n\nTracking functionality will be implemented in the future.');
}
</script>

<?php include '../includes/footer.php'; ?>
