<?php
/**
 * Employee Order View & Edit Page
 * View and edit complete order details
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
$order_handler = new OrderHandler($pdo);

$error_message = '';
$success_message = '';
$order = null;
$customer = null;

// Get order ID from URL
$order_id = $_GET['id'] ?? null;

if (!$order_id || !is_numeric($order_id)) {
    $error_message = 'Invalid order ID provided.';
} else {
    // Get order details
    $order = $order_handler->getOrderById($order_id);
    
    if (!$order) {
        $error_message = 'Order not found.';
    } else {
        // Get customer details
        $customer = $order_handler->getCustomerById($order['user_id']);
        
        // Get order timeline for tracking display (using instance methods)
        $order_model = new Order($pdo);
        $timeline = $order_model->generateTrackingTimeline($order);
        $delivery_estimate = $order_model->getDeliveryEstimate($order);
    }
}

// Handle form submission for order editing
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $order) {
    $result = ['success' => false, 'message' => 'Unknown action'];
    
    if (isset($_POST['update_order_details'])) {
        $result = $order_handler->handleOrderEdit($_POST);
        
    } elseif (isset($_POST['update_order_status'])) {
        $result = $order_handler->handleStatusUpdate($_POST);
        
    } elseif (isset($_POST['delete_order'])) {
        $result = $order_handler->handleOrderDeletion($_POST);
        
        // If order was deleted, redirect to orders list
        if ($result['success']) {
            header('Location: orders.php?message=' . urlencode($result['message']));
            exit;
        }
    }
    
    // Handle result
    if ($result['success']) {
        $success_message = $result['message'];
        // Refresh order data
        $order = $order_handler->getOrderById($order_id);
    } else {
        $error_message = $result['message'];
    }
}

// Set page title
$page_title = $order ? 'View Order #' . $order['order_number'] : 'Order Details';

// Include header
include '../includes/header.php';
?>

<div class="container">
    <div class="dashboard-container">
        <!-- Page Header -->
        <div class="dashboard-header">
            <div>
                <h1>📦 Order Details</h1>
                <?php if ($order): ?>
                    <p>Order #<?php echo htmlspecialchars($order['order_number']); ?> - Employee View</p>
                <?php else: ?>
                    <p>Order information not available</p>
                <?php endif; ?>
            </div>
            <div class="dashboard-actions">
                <a href="orders.php" class="btn btn-secondary">← Back to Orders</a>
                <?php if ($order && in_array($order['status'], ['pending', 'confirmed'])): ?>
                    <a href="#edit-section" class="btn btn-primary">✏️ Edit Order</a>
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

        <?php if ($order && $customer): ?>
            <!-- Order Overview -->
            <div class="order-overview">
                <div class="order-header">
                    <div class="order-info">
                        <h2>Order #<?php echo htmlspecialchars($order['order_number']); ?></h2>
                        <span class="order-status status-<?php echo $order['status']; ?>">
                            <?php echo Order::getStatusLabel($order['status']); ?>
                        </span>
                    </div>
                    <div class="order-meta">
                        <div class="meta-item">
                            <span class="label">Customer:</span>
                            <span class="value">
                                <strong><?php echo htmlspecialchars($customer['first_name'] . ' ' . $customer['last_name']); ?></strong>
                                <br><small><?php echo htmlspecialchars($customer['email']); ?></small>
                            </span>
                        </div>
                        <div class="meta-item">
                            <span class="label">Total Cost:</span>
                            <span class="value"><strong>$<?php echo number_format($order['total_cost'], 2); ?></strong></span>
                        </div>
                        <div class="meta-item">
                            <span class="label">Transport Type:</span>
                            <span class="value">
                                <span class="transport-badge transport-<?php echo $order['transport_type']; ?>">
                                    <?php echo Order::getTransportIcon($order['transport_type']); ?>
                                    <?php echo Order::getTransportLabel($order['transport_type']); ?>
                                </span>
                            </span>
                        </div>
                        <div class="meta-item">
                            <span class="label">Created:</span>
                            <span class="value"><?php echo date('M j, Y g:i A', strtotime($order['created_at'])); ?></span>
                        </div>
                        <?php if ($order['tracking_number']): ?>
                            <div class="meta-item">
                                <span class="label">Tracking:</span>
                                <span class="value"><strong><?php echo htmlspecialchars($order['tracking_number']); ?></strong></span>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Order Details Sections -->
            <div class="order-sections">
                <!-- Addresses Section -->
                <div class="order-section">
                    <h3>📍 Shipping Information</h3>
                    <div class="addresses-grid">
                        <div class="address-card pickup">
                            <h4>📤 Pickup Address</h4>
                            <p><?php echo nl2br(htmlspecialchars($order['pickup_address'])); ?></p>
                        </div>
                        <div class="address-card delivery">
                            <h4>📥 Delivery Address</h4>
                            <p><?php echo nl2br(htmlspecialchars($order['delivery_address'])); ?></p>
                        </div>
                    </div>
                </div>

                <!-- Package Details Section -->
                <div class="order-section">
                    <h3>📦 Package Information</h3>
                    <div class="package-grid">
                        <div class="package-details">
                            <div class="detail-row">
                                <span class="label">Description:</span>
                                <span class="value"><?php echo htmlspecialchars($order['package_description']); ?></span>
                            </div>
                            <div class="detail-row">
                                <span class="label">Weight:</span>
                                <span class="value"><?php echo $order['package_weight']; ?> kg</span>
                            </div>
                            <div class="detail-row">
                                <span class="label">Dimensions:</span>
                                <span class="value"><?php echo $order['package_length']; ?> × <?php echo $order['package_width']; ?> × <?php echo $order['package_height']; ?> cm</span>
                            </div>
                            <?php if ($order['urgent_delivery']): ?>
                                <div class="detail-row">
                                    <span class="label">Priority:</span>
                                    <span class="value urgent">⚡ Urgent Delivery</span>
                                </div>
                            <?php endif; ?>
                            <?php if ($order['special_instructions']): ?>
                                <div class="detail-row">
                                    <span class="label">Special Instructions:</span>
                                    <span class="value"><?php echo nl2br(htmlspecialchars($order['special_instructions'])); ?></span>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="cost-breakdown">
                            <h4>💰 Cost Breakdown</h4>
                            <div class="cost-item">
                                <span class="cost-label">Base Cost:</span>
                                <span class="cost-value">$<?php echo number_format($order['base_cost'], 2); ?></span>
                            </div>
                            <?php if ($order['urgent_surcharge'] > 0): ?>
                                <div class="cost-item">
                                    <span class="cost-label">Urgent Surcharge:</span>
                                    <span class="cost-value">$<?php echo number_format($order['urgent_surcharge'], 2); ?></span>
                                </div>
                            <?php endif; ?>
                            <div class="cost-item total">
                                <span class="cost-label">Total Cost:</span>
                                <span class="cost-value">$<?php echo number_format($order['total_cost'], 2); ?></span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Tracking Timeline Section -->
                <div class="order-section">
                    <h3>🗺️ Order Timeline</h3>
                    <div class="tracking-timeline">
                        <?php foreach ($timeline as $event): ?>
                            <div class="timeline-item <?php echo $event['status']; ?> <?php echo $event['has_happened'] ? 'completed' : 'pending'; ?>">
                                <div class="timeline-marker">
                                    <span class="timeline-icon"><?php echo $event['icon']; ?></span>
                                </div>
                                <div class="timeline-content">
                                    <div class="timeline-title"><?php echo htmlspecialchars($event['label']); ?></div>
                                    <div class="timeline-location"><?php echo htmlspecialchars($event['location']); ?></div>
                                    <?php if ($event['has_happened'] && isset($event['date'])): ?>
                                        <div class="timeline-date"><?php echo $event['date'] . ' ' . $event['time']; ?></div>
                                    <?php elseif (!$event['has_happened'] && $event['status'] === 'delivered'): ?>
                                        <div class="timeline-estimate">
                                            Estimated: <?php echo date('M j, Y', strtotime($delivery_estimate)); ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="quick-actions-section">
                <h3>⚡ Quick Actions</h3>
                <div class="quick-actions">
                    <?php if ($order['status'] === 'pending'): ?>
                        <button onclick="quickConfirmOrder(<?php echo $order['id']; ?>, '<?php echo htmlspecialchars($order['order_number']); ?>')" 
                                class="action-btn confirm">
                            ✅ Quick Confirm
                        </button>
                    <?php endif; ?>
                    
                    <?php if (in_array($order['status'], ['pending', 'confirmed'])): ?>
                        <button onclick="scrollToEdit()" class="action-btn edit">
                            ✏️ Edit Details
                        </button>
                    <?php endif; ?>
                    
                    <?php if (in_array($order['status'], ['confirmed', 'processing'])): ?>
                        <button onclick="markAsShipped(<?php echo $order['id']; ?>)" class="action-btn ship">
                            🚚 Mark as Shipped
                        </button>
                    <?php endif; ?>
                    
                    <?php if (in_array($order['status'], ['pending', 'confirmed'])): ?>
                        <button onclick="confirmDelete(<?php echo $order['id']; ?>, '<?php echo htmlspecialchars($order['order_number']); ?>')" 
                                class="action-btn delete">
                            🗑️ Delete Order
                        </button>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Edit Section (only for eligible orders) -->
            <?php if (in_array($order['status'], ['pending', 'confirmed'])): ?>
                <div id="edit-section" class="edit-section">
                    <?php include '../includes/employee/order_full_edit.php'; ?>
                </div>
            <?php endif; ?>

        <?php else: ?>
            <!-- Error State -->
            <div class="empty-state">
                <div class="empty-icon">❌</div>
                <h3>Order Not Found</h3>
                <p>The requested order could not be found or you don't have permission to view it.</p>
                <a href="orders.php" class="btn btn-primary">Back to Orders</a>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Hidden Forms -->
<form id="quickConfirmForm" method="POST" style="display: none;">
    <input type="hidden" name="order_id" value="<?php echo $order['id'] ?? ''; ?>">
    <input type="hidden" name="update_order_status" value="1">
    <input type="hidden" name="status" value="confirmed">
</form>

<form id="markShippedForm" method="POST" style="display: none;">
    <input type="hidden" name="order_id" value="<?php echo $order['id'] ?? ''; ?>">
    <input type="hidden" name="update_order_status" value="1">
    <input type="hidden" name="status" value="shipped">
</form>

<form id="deleteForm" method="POST" style="display: none;">
    <input type="hidden" name="order_id" value="<?php echo $order['id'] ?? ''; ?>">
    <input type="hidden" name="delete_order" value="1">
</form>

<script>
function quickConfirmOrder(orderId, orderNumber) {
    if (confirm(`Confirm order "${orderNumber}" and assign tracking number?`)) {
        document.getElementById('quickConfirmForm').submit();
    }
}

function markAsShipped(orderId) {
    if (confirm('Mark this order as shipped?')) {
        document.getElementById('markShippedForm').submit();
    }
}

function confirmDelete(orderId, orderNumber) {
    if (confirm(`Are you sure you want to delete order "${orderNumber}"? This action cannot be undone.`)) {
        document.getElementById('deleteForm').submit();
    }
}

function scrollToEdit() {
    document.getElementById('edit-section').scrollIntoView({ 
        behavior: 'smooth',
        block: 'start'
    });
}
</script>

<?php include '../includes/footer.php'; ?>
