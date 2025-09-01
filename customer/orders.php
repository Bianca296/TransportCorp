<?php
/**
 * Customer Orders Listing Page
 * Display all orders with filtering, search, and pagination
 */

// Include configuration and middleware
require_once '../config/config.php';
require_once '../includes/middleware.php';

// Get current user (allow admin/employee for support)
$user = getCurrentUserOrRedirect();

// For non-customers, ensure they have permission to view this
if ($user['role'] !== 'customer' && $user['role'] !== 'admin' && $user['role'] !== 'employee') {
    accessDenied();
}

// Initialize services
$database = new Database();
$pdo = $database->getConnection();
$order_model = new Order($pdo);

// Get filters from URL parameters
$filters = [
    'status' => $_GET['status'] ?? '',
    'transport_type' => $_GET['transport_type'] ?? '',
    'search' => $_GET['search'] ?? '',
    'date_from' => $_GET['date_from'] ?? '',
    'date_to' => $_GET['date_to'] ?? ''
];

// Get pagination parameters
$page = max(1, intval($_GET['page'] ?? 1));
$per_page = 12; // Orders per page

// Get orders with filters and pagination
$result = $order_model->findOrdersWithFilters($user['id'], $filters, $page, $per_page);
$orders = $result['orders'];
$pagination = $result['pagination'];

// Get filter counts for status badges
$status_counts = [];
$all_statuses = ['pending', 'confirmed', 'processing', 'in_transit', 'delivered', 'cancelled'];
foreach ($all_statuses as $status) {
    $status_filter = array_merge($filters, ['status' => $status]);
    $status_result = $order_model->findOrdersWithFilters($user['id'], $status_filter, 1, 1);
    $status_counts[$status] = $status_result['pagination']['total_records'];
}

// Calculate total orders count
$total_filter = $filters;
$total_filter['status'] = '';
$total_result = $order_model->findOrdersWithFilters($user['id'], $total_filter, 1, 1);
$total_orders = $total_result['pagination']['total_records'];

// Set page title
$page_title = 'My Orders';

// Include header
include '../includes/header.php';
?>

<div class="orders-container">
    <div class="orders-header">
        <div class="header-content">
            <div class="breadcrumb">
                <a href="dashboard.php">Dashboard</a>
                <span class="separator">›</span>
                <span class="current">My Orders</span>
            </div>
            
            <div class="page-title-section">
                <h1>📦 My Orders</h1>
                <div class="orders-summary">
                    <span class="total-count"><?php echo $pagination['total_records']; ?> orders found</span>
                    <?php if (!empty(array_filter($filters))): ?>
                        <span class="filter-indicator">
                            (filtered from <?php echo $total_orders; ?> total)
                            <a href="orders.php" class="clear-filters">Clear filters</a>
                        </span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="orders-content">
        <!-- Filters and Search -->
        <div class="orders-filters">
            <form method="GET" class="filters-form">
                <div class="filters-row">
                    <!-- Search -->
                    <div class="filter-group">
                        <label for="search">🔍 Search</label>
                        <input type="text" 
                               id="search" 
                               name="search" 
                               value="<?php echo htmlspecialchars($filters['search']); ?>" 
                               placeholder="Order number, description, address...">
                    </div>
                    
                    <!-- Status Filter -->
                    <div class="filter-group">
                        <label for="status">📊 Status</label>
                        <select id="status" name="status">
                            <option value="">All Statuses</option>
                            <?php foreach ($all_statuses as $status): ?>
                                <option value="<?php echo $status; ?>" 
                                        <?php echo $filters['status'] === $status ? 'selected' : ''; ?>>
                                    <?php echo Order::getStatusLabel($status); ?>
                                    (<?php echo $status_counts[$status]; ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <!-- Transport Type Filter -->
                    <div class="filter-group">
                        <label for="transport_type">🚚 Transport</label>
                        <select id="transport_type" name="transport_type">
                            <option value="">All Transport Types</option>
                            <option value="land" <?php echo $filters['transport_type'] === 'land' ? 'selected' : ''; ?>>
                                🚛 Land Transport
                            </option>
                            <option value="air" <?php echo $filters['transport_type'] === 'air' ? 'selected' : ''; ?>>
                                ✈️ Air Transport
                            </option>
                            <option value="ocean" <?php echo $filters['transport_type'] === 'ocean' ? 'selected' : ''; ?>>
                                🚢 Ocean Transport
                            </option>
                        </select>
                    </div>
                </div>
                
                <div class="filters-row">
                    <!-- Date Range -->
                    <div class="filter-group">
                        <label for="date_from">📅 From Date</label>
                        <input type="date" 
                               id="date_from" 
                               name="date_from" 
                               value="<?php echo htmlspecialchars($filters['date_from']); ?>">
                    </div>
                    
                    <div class="filter-group">
                        <label for="date_to">📅 To Date</label>
                        <input type="date" 
                               id="date_to" 
                               name="date_to" 
                               value="<?php echo htmlspecialchars($filters['date_to']); ?>">
                    </div>
                    
                    <!-- Filter Actions -->
                    <div class="filter-actions">
                        <button type="submit" class="btn btn-primary">Apply Filters</button>
                        <a href="orders.php" class="btn btn-secondary">Reset</a>
                    </div>
                </div>
            </form>
        </div>

        <!-- Quick Status Filters -->
        <div class="status-quick-filters">
            <a href="orders.php" class="status-filter <?php echo empty($filters['status']) ? 'active' : ''; ?>">
                All Orders (<?php echo $total_orders; ?>)
            </a>
            <?php foreach (['pending', 'confirmed', 'processing', 'in_transit', 'delivered'] as $status): ?>
                <?php if ($status_counts[$status] > 0): ?>
                    <a href="orders.php?status=<?php echo $status; ?>" 
                       class="status-filter status-<?php echo $status; ?> <?php echo $filters['status'] === $status ? 'active' : ''; ?>">
                        <?php echo Order::getStatusLabel($status); ?> (<?php echo $status_counts[$status]; ?>)
                    </a>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>

        <!-- Orders List -->
        <?php if (!empty($orders)): ?>
            <div class="orders-grid">
                <?php foreach ($orders as $order): ?>
                    <div class="order-card">
                        <div class="order-card-header">
                            <div class="order-number">
                                <strong><?php echo htmlspecialchars($order['order_number']); ?></strong>
                            </div>
                            <div class="order-status">
                                <span class="status-badge status-<?php echo $order['status']; ?>">
                                    <?php echo Order::getStatusLabel($order['status']); ?>
                                </span>
                            </div>
                        </div>
                        
                        <div class="order-card-content">
                            <div class="order-info">
                                <div class="info-row">
                                    <span class="label">Transport:</span>
                                    <span class="value"><?php echo Order::getTransportLabel($order['transport_type']); ?></span>
                                </div>
                                <div class="info-row">
                                    <span class="label">Weight:</span>
                                    <span class="value"><?php echo $order['package_weight']; ?>kg</span>
                                </div>
                                <div class="info-row">
                                    <span class="label">Total Cost:</span>
                                    <span class="value cost">$<?php echo number_format($order['total_cost'], 2); ?></span>
                                </div>
                                <div class="info-row">
                                    <span class="label">Created:</span>
                                    <span class="value"><?php echo date('M j, Y', strtotime($order['created_at'])); ?></span>
                                </div>
                                <?php if ($order['tracking_number']): ?>
                                <div class="info-row">
                                    <span class="label">Tracking:</span>
                                    <span class="value tracking"><?php echo htmlspecialchars($order['tracking_number']); ?></span>
                                </div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="order-description">
                                <strong>Package:</strong> 
                                <?php echo htmlspecialchars(substr($order['package_description'], 0, 80)); ?>
                                <?php if (strlen($order['package_description']) > 80): ?>...<?php endif; ?>
                            </div>
                            
                            <div class="order-addresses">
                                <div class="address-summary">
                                    <span class="from">📍 <?php echo htmlspecialchars(substr($order['pickup_address'], 0, 30)); ?>...</span>
                                    <span class="arrow">→</span>
                                    <span class="to">🏠 <?php echo htmlspecialchars(substr($order['delivery_address'], 0, 30)); ?>...</span>
                                </div>
                            </div>
                        </div>
                        
                        <div class="order-card-actions">
                            <a href="view-order.php?id=<?php echo $order['id']; ?>" class="btn btn-primary btn-sm">
                                View Details
                            </a>
                            <?php if ($order['status'] === 'pending'): ?>
                                <button onclick="cancelOrder(<?php echo $order['id']; ?>, '<?php echo htmlspecialchars($order['order_number']); ?>')" 
                                        class="btn btn-danger btn-sm">
                                    Cancel
                                </button>
                            <?php endif; ?>
                            <?php if ($order['tracking_number']): ?>
                                <button onclick="trackPackage('<?php echo htmlspecialchars($order['tracking_number']); ?>')" 
                                        class="btn btn-outline btn-sm">
                                    Track
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Pagination -->
            <?php if ($pagination['total_pages'] > 1): ?>
                <div class="pagination-container">
                    <div class="pagination">
                        <?php if ($pagination['has_previous']): ?>
                            <a href="<?php echo buildPaginationUrl($pagination['previous_page'], $filters); ?>" 
                               class="pagination-btn">
                                ← Previous
                            </a>
                        <?php endif; ?>
                        
                        <div class="pagination-info">
                            Page <?php echo $pagination['current_page']; ?> of <?php echo $pagination['total_pages']; ?>
                            (<?php echo $pagination['total_records']; ?> total orders)
                        </div>
                        
                        <?php if ($pagination['has_next']): ?>
                            <a href="<?php echo buildPaginationUrl($pagination['next_page'], $filters); ?>" 
                               class="pagination-btn">
                                Next →
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
            
        <?php else: ?>
            <!-- No Orders Found -->
            <div class="no-orders">
                <div class="no-orders-icon">📦</div>
                <h3>No orders found</h3>
                <?php if (!empty(array_filter($filters))): ?>
                    <p>No orders match your current filters. Try adjusting your search criteria.</p>
                    <a href="orders.php" class="btn btn-secondary">Clear Filters</a>
                <?php else: ?>
                    <p>You haven't created any orders yet. Ready to ship something?</p>
                    <a href="create-order.php" class="btn btn-primary">Create Your First Order</a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
function cancelOrder(orderId, orderNumber) {
    if (confirm(`Are you sure you want to cancel order ${orderNumber}? This action cannot be undone.`)) {
        // Create a form and submit it
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = 'view-order.php?id=' + orderId;
        
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'cancel_order';
        input.value = '1';
        
        form.appendChild(input);
        document.body.appendChild(form);
        form.submit();
    }
}

function trackPackage(trackingNumber) {
    alert('Tracking Number: ' + trackingNumber + '\n\nTracking functionality will be implemented in the future.');
}
</script>

<?php
/**
 * Helper function to build pagination URLs with current filters
 */
function buildPaginationUrl($page, $filters) {
    $params = array_merge($filters, ['page' => $page]);
    $params = array_filter($params); // Remove empty values
    return 'orders.php?' . http_build_query($params);
}

include '../includes/footer.php'; 
?>
