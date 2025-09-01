<?php
/**
 * Cost Estimation API Endpoint
 * Returns shipping cost estimates for AJAX requests
 */

// Include configuration
require_once '../config/config.php';

// Set JSON response header
header('Content-Type: application/json');

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

try {
    // Get POST data
    $input = json_decode(file_get_contents('php://input'), true);
    
    // Validate required fields
    if (empty($input['package_weight']) || empty($input['transport_type'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Weight and transport type are required']);
        exit;
    }
    
    // Initialize Order model
    $database = new Database();
    $pdo = $database->getConnection();
    $order_model = new Order($pdo);
    
    // Prepare data for estimation
    $estimation_data = [
        'package_weight' => floatval($input['package_weight']),
        'transport_type' => $input['transport_type'],
        'urgent_delivery' => isset($input['urgent_delivery']) && $input['urgent_delivery']
    ];
    
    // Get cost estimation
    $cost_estimate = $order_model->estimateCost($estimation_data);
    
    if (isset($cost_estimate['error'])) {
        http_response_code(400);
        echo json_encode($cost_estimate);
        exit;
    }
    
    // Return cost breakdown
    echo json_encode([
        'success' => true,
        'cost' => $cost_estimate
    ]);
    
} catch (Exception $e) {
    error_log("Cost estimation API error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error']);
}
?>
