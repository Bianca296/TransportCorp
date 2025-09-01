<?php
/**
 * Public Track Shipment Page
 * Track orders by tracking number - no login required
 */

// Include configuration (but not middleware for login)
require_once 'config/config.php';

// Initialize services
$database = new Database();
$pdo = $database->getConnection();
$order_model = new Order($pdo);

$error_message = '';
$order = null;
$timeline = [];
$delivery_estimate = null;
$is_guest = true; // Flag to indicate this is guest access

// Check if user is logged in (optional)
$user = null;
if (is_logged_in()) {
    $auth = getAuth();
    $user = $auth->getCurrentUser();
    if ($user) {
        $is_guest = false;
    }
}

// Get tracking identifier from URL or form
$tracking_id = $_GET['tracking'] ?? $_POST['tracking_id'] ?? '';

if ($tracking_id) {
    // Find order using public tracking method (limited data)
    $order = $order_model->findForPublicTracking($tracking_id);
    
    if (!$order) {
        $error_message = 'No shipment found with that tracking number or order number.';
    } else {
        // Generate tracking timeline and delivery estimate
        $timeline = $order_model->generateTrackingTimeline($order);
        $delivery_estimate = $order_model->getDeliveryEstimate($order);
    }
}

// Set page title
$page_title = 'Track Your Shipment';

// Include header
include 'includes/header.php';
?>

<div class="tracking-container">
    <div class="tracking-header">
        <div class="header-content">
            <?php if (!$is_guest): ?>
                <div class="breadcrumb">
                    <a href="customer/dashboard.php">Dashboard</a>
                    <span class="separator">›</span>
                    <span class="current">Track Shipment</span>
                </div>
            <?php endif; ?>
            
            <h1>📍 Track Your Shipment</h1>
            <p>Enter your tracking number or order number to see the latest updates</p>
            
            <?php if ($is_guest): ?>
                <div class="guest-notice">
                    <small>
                        💡 <a href="auth/login.php">Login</a> or <a href="auth/register.php">create an account</a> 
                        to access full order details and manage your shipments.
                    </small>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="tracking-content">
        <!-- Tracking Search Form -->
        <div class="tracking-search">
            <form method="POST" class="search-form">
                <div class="search-input-group">
                    <input type="text" 
                           name="tracking_id" 
                           value="<?php echo htmlspecialchars($tracking_id); ?>" 
                           placeholder="Enter tracking number or order number (e.g., TRK123456 or ORD-2024-1234)"
                           required>
                    <button type="submit" class="btn btn-primary">
                        🔍 Track Package
                    </button>
                </div>
                <div class="search-examples">
                    <small>Examples: TRK123456, ORD-2024-1234</small>
                </div>
            </form>
        </div>

        <?php if ($error_message): ?>
            <!-- Error Message -->
            <div class="tracking-error">
                <div class="error-icon">📦</div>
                <h3>Shipment Not Found</h3>
                <p><?php echo htmlspecialchars($error_message); ?></p>
                <div class="error-actions">
                    <?php if ($is_guest): ?>
                        <a href="auth/login.php" class="btn btn-primary">Login to Your Account</a>
                        <a href="mailto:support@transportcompany.com" class="btn btn-outline">Contact Support</a>
                    <?php else: ?>
                        <a href="customer/orders.php" class="btn btn-secondary">View My Orders</a>
                        <a href="mailto:support@transportcompany.com" class="btn btn-outline">Contact Support</a>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($order): ?>
            <!-- Tracking Results -->
            <div class="tracking-results">
                <!-- Package Summary -->
                <div class="package-summary">
                    <div class="summary-header">
                        <h2>📦 Package Details</h2>
                        <div class="package-status">
                            <span class="status-badge status-<?php echo $order['status']; ?>">
                                <?php echo Order::getStatusLabel($order['status']); ?>
                            </span>
                        </div>
                    </div>
                    
                    <div class="summary-content">
                        <div class="summary-grid">
                            <div class="summary-item">
                                <span class="label">Order Number:</span>
                                <span class="value"><?php echo htmlspecialchars($order['order_number']); ?></span>
                            </div>
                            <?php if ($order['tracking_number']): ?>
                            <div class="summary-item">
                                <span class="label">Tracking Number:</span>
                                <span class="value tracking-number"><?php echo htmlspecialchars($order['tracking_number']); ?></span>
                            </div>
                            <?php endif; ?>
                            <div class="summary-item">
                                <span class="label">Transport Type:</span>
                                <span class="value"><?php echo Order::getTransportLabel($order['transport_type']); ?></span>
                            </div>
                            <div class="summary-item">
                                <span class="label">Package Weight:</span>
                                <span class="value"><?php echo $order['package_weight']; ?> kg</span>
                            </div>
                            <?php if ($order['urgent_delivery']): ?>
                            <div class="summary-item">
                                <span class="label">Service Level:</span>
                                <span class="value urgent">🚨 Urgent Delivery</span>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Delivery Estimate -->
                <?php if ($delivery_estimate): ?>
                <div class="delivery-estimate">
                    <div class="estimate-icon">📅</div>
                    <div class="estimate-content">
                        <?php if ($delivery_estimate['status'] === 'delivered'): ?>
                            <h3>✅ Package Delivered</h3>
                            <p>Your package has been successfully delivered.</p>
                        <?php elseif ($delivery_estimate['status'] === 'cancelled'): ?>
                            <h3>❌ Order Cancelled</h3>
                            <p>This order has been cancelled.</p>
                        <?php else: ?>
                            <h3>Estimated Delivery</h3>
                            <div class="estimate-date">
                                <?php echo $delivery_estimate['date']; ?> (<?php echo $delivery_estimate['day_name']; ?>)
                            </div>
                            <div class="estimate-details">
                                <?php echo $delivery_estimate['message']; ?>
                                <?php if ($delivery_estimate['days_remaining'] > 0): ?>
                                    • <?php echo $delivery_estimate['days_remaining']; ?> days remaining
                                <?php else: ?>
                                    • Expected today
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Tracking Timeline -->
                <?php if (!empty($timeline)): ?>
                <div class="tracking-timeline">
                    <h3>📋 Tracking Timeline</h3>
                    <div class="timeline">
                        <?php foreach ($timeline as $index => $event): ?>
                            <div class="timeline-item <?php echo $event['has_happened'] ? 'completed' : ($event['is_current'] ? 'current' : 'future'); ?>">
                                <div class="timeline-marker <?php echo isset($event['is_cancelled']) && $event['is_cancelled'] ? 'cancelled' : ''; ?>">
                                    <span class="timeline-icon"><?php echo $event['icon']; ?></span>
                                </div>
                                <div class="timeline-content">
                                    <div class="timeline-header">
                                        <h4><?php echo $event['label']; ?></h4>
                                        <?php if ($event['has_happened'] || $event['is_current']): ?>
                                            <div class="timeline-time">
                                                <?php echo $event['date']; ?> at <?php echo $event['time']; ?>
                                            </div>
                                        <?php else: ?>
                                            <div class="timeline-time estimated">
                                                Estimated: <?php echo $event['date']; ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="timeline-location">
                                        📍 <?php echo $event['location']; ?>
                                    </div>
                                </div>
                                <?php if ($index < count($timeline) - 1): ?>
                                    <div class="timeline-connector"></div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Package Journey (Limited for guests) -->
                <div class="package-journey">
                    <h3>📍 Package Journey</h3>
                    <div class="journey-content">
                        <?php if (isset($order['pickup_address']) && isset($order['delivery_address'])): ?>
                        <div class="journey-addresses">
                            <div class="address-block">
                                <h4>📦 From</h4>
                                <p><?php echo htmlspecialchars($order['pickup_address']); ?></p>
                            </div>
                            <div class="journey-arrow">→</div>
                            <div class="address-block">
                                <h4>🏠 To</h4>
                                <p><?php echo htmlspecialchars($order['delivery_address']); ?></p>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <?php if ($order['package_description']): ?>
                        <div class="package-details">
                            <h4>📋 Package Description</h4>
                            <p><?php echo nl2br(htmlspecialchars($order['package_description'])); ?></p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Actions (Different for guests vs logged-in users) -->
                <div class="tracking-actions">
                    <?php if (!$is_guest): ?>
                        <!-- Logged-in user actions -->
                        <a href="customer/view-order.php?id=<?php echo $order['id']; ?>" class="btn btn-primary">
                            📄 View Full Order Details
                        </a>
                        <a href="customer/orders.php" class="btn btn-secondary">
                            📦 View All My Orders
                        </a>
                    <?php else: ?>
                        <!-- Guest actions -->
                        <a href="auth/login.php" class="btn btn-primary">
                            🔐 Login for Full Details
                        </a>
                        <a href="auth/register.php" class="btn btn-secondary">
                            ➕ Create Account
                        </a>
                    <?php endif; ?>
                    
                    <a href="mailto:support@transportcompany.com?subject=Tracking Help - <?php echo htmlspecialchars($order['order_number']); ?>" 
                       class="btn btn-outline">
                        💬 Contact Support
                    </a>
                </div>

                <?php if ($is_guest && !empty($order)): ?>
                <!-- Guest Benefits CTA -->
                <div class="guest-benefits">
                    <h4>🎯 Get More with an Account</h4>
                    <div class="benefits-grid">
                        <div class="benefit">
                            <span class="benefit-icon">📱</span>
                            <span class="benefit-text">Real-time notifications</span>
                        </div>
                        <div class="benefit">
                            <span class="benefit-icon">📋</span>
                            <span class="benefit-text">Order history</span>
                        </div>
                        <div class="benefit">
                            <span class="benefit-icon">💰</span>
                            <span class="benefit-text">Cost details</span>
                        </div>
                        <div class="benefit">
                            <span class="benefit-icon">⚙️</span>
                            <span class="benefit-text">Delivery preferences</span>
                        </div>
                    </div>
                    <div class="benefits-cta">
                        <a href="auth/register.php" class="btn btn-primary">Create Free Account</a>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        <?php elseif (empty($tracking_id)): ?>
            <!-- Welcome State -->
            <div class="tracking-welcome">
                <div class="welcome-icon">📦</div>
                <h3>Track Any Package</h3>
                <p>Enter a tracking number or order number above to see real-time updates on any shipment.</p>
                
                <div class="quick-links">
                    <h4>Quick Links</h4>
                    <div class="quick-link-buttons">
                        <?php if (!$is_guest): ?>
                            <a href="customer/orders.php" class="quick-link">
                                <span class="quick-link-icon">📋</span>
                                <span class="quick-link-text">My Orders</span>
                            </a>
                            <a href="customer/create-order.php" class="quick-link">
                                <span class="quick-link-icon">➕</span>
                                <span class="quick-link-text">Create Order</span>
                            </a>
                        <?php else: ?>
                            <a href="auth/login.php" class="quick-link">
                                <span class="quick-link-icon">🔐</span>
                                <span class="quick-link-text">Login</span>
                            </a>
                            <a href="auth/register.php" class="quick-link">
                                <span class="quick-link-icon">➕</span>
                                <span class="quick-link-text">Create Account</span>
                            </a>
                        <?php endif; ?>
                        <a href="mailto:support@transportcompany.com" class="quick-link">
                            <span class="quick-link-icon">💬</span>
                            <span class="quick-link-text">Support</span>
                        </a>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
