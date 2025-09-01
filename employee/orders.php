<?php
/**
 * Employee Order Management
 * View, process, and manage shipping orders
 */

// Include configuration and middleware
require_once '../config/config.php';
require_once '../includes/middleware.php';

// Require employee access
requireEmployee();
$user = getCurrentUserOrRedirect();

// Initialize handler
$database = new Database();
$pdo = $database->getConnection();
$order_handler = new OrderHandler($pdo);

$success_message = '';
$error_message = '';
$action = $_GET['action'] ?? 'list';
$edit_order = null;

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $result = ['success' => false, 'message' => 'Unknown action'];
    
    if (isset($_POST['update_order_status'])) {
        $result = $order_handler->handleStatusUpdate($_POST);
        
    } elseif (isset($_POST['create_order'])) {
        $result = $order_handler->handleOrderCreation($_POST);
        
    } elseif (isset($_POST['confirm_order'])) {
        $result = $order_handler->handleOrderConfirmation($_POST);
        
    } elseif (isset($_POST['delete_order'])) {
        $result = $order_handler->handleOrderDeletion($_POST);
    }
    
    // Handle result
    if ($result['success']) {
        $success_message = $result['message'];
        $action = 'list'; // Redirect to list view
    } else {
        $error_message = $result['message'];
    }
}

// Handle different actions
if ($action === 'edit' && isset($_GET['id'])) {
    $edit_order = $order_handler->getOrderById(intval($_GET['id']));
    if (!$edit_order) {
        $error_message = 'Order not found.';
        $action = 'list';
    }
}

// Get filters from query parameters
$filters = [
    'search' => $_GET['search'] ?? '',
    'status' => $_GET['status'] ?? '',
    'transport_type' => $_GET['transport_type'] ?? '',
    'date_from' => $_GET['date_from'] ?? '',
    'date_to' => $_GET['date_to'] ?? ''
];

// Get orders with filters and pagination
$page = max(1, intval($_GET['page'] ?? 1));
$per_page = 15;

$all_orders = $order_handler->getOrdersWithFilters($filters, $page, $per_page);
$all_customers = $order_handler->getActiveCustomers();

// Set page title
$page_title = match($action) {
    'create' => 'Create Order',
    'edit' => 'Process Order',
    default => 'Order Management'
};

// Include header
include '../includes/header.php';
?>

<div class="container">
    <div class="dashboard-container">
        <!-- Page Header -->
        <div class="dashboard-header">
            <div>
                <h1>📦 Order Management</h1>
                <p>
                    <?php if ($action === 'create'): ?>
                        Create a new shipping order for a customer
                    <?php elseif ($action === 'edit'): ?>
                        Process order: <?php echo htmlspecialchars($edit_order['order_number']); ?>
                    <?php else: ?>
                        Manage and process shipping orders
                    <?php endif; ?>
                </p>
            </div>
            <div class="dashboard-actions">
                <?php if ($action === 'list'): ?>
                    <a href="orders.php?action=create" class="btn btn-primary">➕ Create Order</a>
                    <a href="dashboard.php" class="btn btn-secondary">← Dashboard</a>
                <?php else: ?>
                    <a href="orders.php" class="btn btn-secondary">← Back to Orders</a>
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
            <?php include '../includes/employee/orders_list.php'; ?>
            
        <?php elseif ($action === 'edit' && $edit_order): ?>
            <?php include '../includes/employee/order_edit.php'; ?>
            
        <?php elseif ($action === 'create'): ?>
            <?php include '../includes/employee/order_create.php'; ?>
            
        <?php endif; ?>
    </div>
</div>

<!-- Hidden Delete Form -->
<form id="deleteForm" method="POST" style="display: none;">
    <input type="hidden" name="order_id" id="deleteOrderId">
    <input type="hidden" name="delete_order" value="1">
</form>

<script>
function confirmDelete(orderId, orderNumber) {
    if (confirm(`Are you sure you want to delete order "${orderNumber}"? This action cannot be undone.`)) {
        document.getElementById('deleteOrderId').value = orderId;
        document.getElementById('deleteForm').submit();
    }
}

function confirmConfirm(orderId, orderNumber) {
    if (confirm(`Confirm order "${orderNumber}" and assign tracking number?`)) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="order_id" value="${orderId}">
            <input type="hidden" name="confirm_order" value="1">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}
</script>

<?php include '../includes/footer.php'; ?>