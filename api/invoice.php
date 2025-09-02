<?php
/**
 * Invoice Download API
 */

require_once '../config/config.php';
require_once '../includes/middleware.php';
require_once '../classes/Invoice.php';

// Require user to be logged in
$user = getCurrentUserOrRedirect();

// Get order ID from request
$order_id = $_GET['order_id'] ?? null;
$action = $_GET['action'] ?? 'download'; // download or preview

if (!$order_id || !is_numeric($order_id)) {
    http_response_code(400);
    die('Invalid order ID');
}

try {
    // Initialize database connection
    $database = new Database();
    $pdo = $database->getConnection();
    
    // Get order details
    $order_model = new Order($pdo);
    $order = $order_model->findById($order_id);
    
    if (!$order) {
        http_response_code(404);
        die('Order not found');
    }
    
    // Prevent invoice generation for cancelled orders
    if ($order['status'] === 'cancelled') {
        http_response_code(400);
        die('Invoice not available for cancelled orders');
    }
    
    // Security check: Ensure user can access this order
    if ($user['role'] === 'customer' && $order['user_id'] != $user['id']) {
        http_response_code(403);
        die('Access denied');
    }
    
    // For employees/admin, they can access any order
    if (!in_array($user['role'], ['admin', 'employee', 'customer'])) {
        http_response_code(403);
        die('Access denied');
    }
    
    // Get customer details
    $user_model = new User($pdo);
    $customer = $user_model->findById($order['user_id']);
    
    if (!$customer) {
        http_response_code(404);
        die('Customer not found');
    }
    
    // Generate invoice
    $invoice = new Invoice();
    $invoice->generateInvoice($order, $customer);
    
    // Output based on action
    if ($action === 'preview') {
        $invoice->preview();
    } else {
        $invoice->download();
    }
    
} catch (Exception $e) {
    error_log('Invoice generation error: ' . $e->getMessage());
    http_response_code(500);
    die('Error generating invoice');
}
