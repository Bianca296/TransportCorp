<?php
/**
 * Order Handler for Employee Operations
 */

class OrderHandler {
    private $pdo;
    private $order_model;
    private $user_model;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
        $this->order_model = new Order($pdo);
        $this->user_model = new User($pdo);
    }
    
    /**
     * Handle order status update
     * @param array $data POST data
     * @return array Result with success status and message
     */
    public function handleStatusUpdate($data) {
        try {
            $order_id = intval($data['order_id']);
            $new_status = $data['status'] ?? '';
            $tracking_number = $data['tracking_number'] ?? null;
            
            // Validate status
            $valid_statuses = ['pending', 'confirmed', 'processing', 'shipped', 'in_transit', 'delivered', 'cancelled'];
            if (!in_array($new_status, $valid_statuses)) {
                return ['success' => false, 'message' => 'Invalid status provided.'];
            }
            
            // Auto-generate tracking number if confirming and none provided
            if ($new_status === 'confirmed' && empty($tracking_number)) {
                $tracking_number = $this->order_model->generateOrderNumber();
            }
            
            $result = $this->order_model->updateStatus($order_id, $new_status, $tracking_number);
            
            if ($result) {
                $status_message = $this->getStatusUpdateMessage($new_status);
                return ['success' => true, 'message' => $status_message];
            } else {
                return ['success' => false, 'message' => 'Failed to update order status.'];
            }
            
        } catch (Exception $e) {
            error_log("Status update error: " . $e->getMessage());
            return ['success' => false, 'message' => 'An error occurred while updating the order.'];
        }
    }
    
    /**
     * Handle order creation by employee
     * @param array $data POST data
     * @return array Result with success status and message
     */
    public function handleOrderCreation($data) {
        try {
            $customer_id = intval($data['customer_id']);
            
            // Validate customer exists and is active
            $customer = $this->user_model->findById($customer_id);
            if (!$customer || $customer['role'] !== 'customer') {
                return ['success' => false, 'message' => 'Invalid customer selected.'];
            }
            
            if ($customer['status'] !== 'active') {
                return ['success' => false, 'message' => 'Customer account is not active.'];
            }
            
            // Prepare order data
            $order_data = [
                'user_id' => $customer_id,
                'pickup_address' => trim($data['pickup_address'] ?? ''),
                'delivery_address' => trim($data['delivery_address'] ?? ''),
                'package_weight' => floatval($data['package_weight'] ?? 0),
                'package_length' => floatval($data['package_length'] ?? 0),
                'package_width' => floatval($data['package_width'] ?? 0),
                'package_height' => floatval($data['package_height'] ?? 0),
                'package_description' => trim($data['package_description'] ?? ''),
                'transport_type' => $data['transport_type'] ?? 'land',
                'urgent_delivery' => isset($data['urgent_delivery']) ? 1 : 0,
                'special_instructions' => trim($data['special_instructions'] ?? '')
            ];
            
            // Validate required fields
            $required_fields = ['pickup_address', 'delivery_address', 'package_description'];
            foreach ($required_fields as $field) {
                if (empty($order_data[$field])) {
                    return ['success' => false, 'message' => "Field '$field' is required."];
                }
            }
            
            // Validate numeric fields
            if ($order_data['package_weight'] <= 0) {
                return ['success' => false, 'message' => 'Package weight must be greater than 0.'];
            }
            
            if ($order_data['package_length'] <= 0 || $order_data['package_width'] <= 0 || $order_data['package_height'] <= 0) {
                return ['success' => false, 'message' => 'Package dimensions must be greater than 0.'];
            }
            
            // Create the order
            $result = $this->order_model->create($order_data);
            
            if ($result['success']) {
                return [
                    'success' => true, 
                    'message' => 'Order created successfully for customer.',
                    'order_id' => $result['order_id']
                ];
            } else {
                return ['success' => false, 'message' => $result['message']];
            }
            
        } catch (Exception $e) {
            error_log("Order creation error: " . $e->getMessage());
            return ['success' => false, 'message' => 'An error occurred while creating the order.'];
        }
    }
    
    /**
     * Handle order confirmation
     * @param array $data POST data
     * @return array Result with success status and message
     */
    public function handleOrderConfirmation($data) {
        try {
            $order_id = intval($data['order_id']);
            
            // Get order details
            $order = $this->order_model->findById($order_id);
            if (!$order) {
                return ['success' => false, 'message' => 'Order not found.'];
            }
            
            if ($order['status'] !== 'pending') {
                return ['success' => false, 'message' => 'Only pending orders can be confirmed.'];
            }
            
            // Generate tracking number
            $tracking_number = $this->order_model->generateOrderNumber();
            
            $result = $this->order_model->updateStatus($order_id, 'confirmed', $tracking_number);
            
            if ($result) {
                return [
                    'success' => true, 
                    'message' => 'Order confirmed and tracking number assigned: ' . $tracking_number
                ];
            } else {
                return ['success' => false, 'message' => 'Failed to confirm order.'];
            }
            
        } catch (Exception $e) {
            error_log("Order confirmation error: " . $e->getMessage());
            return ['success' => false, 'message' => 'An error occurred while confirming the order.'];
        }
    }
    
    /**
     * Handle order detail editing
     * @param array $data POST data
     * @return array Result with success status and message
     */
    public function handleOrderEdit($data) {
        try {
            $order_id = intval($data['order_id']);
            
            // Get existing order
            $order = $this->order_model->findById($order_id);
            if (!$order) {
                return ['success' => false, 'message' => 'Order not found.'];
            }
            
            // Check if order can be edited
            $editable_statuses = ['pending', 'confirmed'];
            if (!in_array($order['status'], $editable_statuses)) {
                return [
                    'success' => false, 
                    'message' => 'Only pending and confirmed orders can be edited. Current status: ' . Order::getStatusLabel($order['status'])
                ];
            }
            
            // Validate and prepare update data
            $update_data = [];
            $update_fields = [];
            $params = [];
            
            // Update addresses
            if (isset($data['pickup_address']) && trim($data['pickup_address']) !== '') {
                $update_fields[] = "pickup_address = ?";
                $params[] = trim($data['pickup_address']);
            }
            
            if (isset($data['delivery_address']) && trim($data['delivery_address']) !== '') {
                $update_fields[] = "delivery_address = ?";
                $params[] = trim($data['delivery_address']);
            }
            
            // Update package details
            if (isset($data['package_weight']) && floatval($data['package_weight']) > 0) {
                $update_fields[] = "package_weight = ?";
                $params[] = floatval($data['package_weight']);
            }
            
            if (isset($data['package_length']) && floatval($data['package_length']) > 0) {
                $update_fields[] = "package_length = ?";
                $params[] = floatval($data['package_length']);
            }
            
            if (isset($data['package_width']) && floatval($data['package_width']) > 0) {
                $update_fields[] = "package_width = ?";
                $params[] = floatval($data['package_width']);
            }
            
            if (isset($data['package_height']) && floatval($data['package_height']) > 0) {
                $update_fields[] = "package_height = ?";
                $params[] = floatval($data['package_height']);
            }
            
            if (isset($data['package_description']) && trim($data['package_description']) !== '') {
                $update_fields[] = "package_description = ?";
                $params[] = trim($data['package_description']);
            }
            
            // Update transport type
            if (isset($data['transport_type']) && in_array($data['transport_type'], ['land', 'air', 'ocean'])) {
                $update_fields[] = "transport_type = ?";
                $params[] = $data['transport_type'];
            }
            
            // Update urgent delivery
            if (isset($data['urgent_delivery'])) {
                $update_fields[] = "urgent_delivery = ?";
                $params[] = isset($data['urgent_delivery']) ? 1 : 0;
            }
            
            // Update special instructions
            if (isset($data['special_instructions'])) {
                $update_fields[] = "special_instructions = ?";
                $params[] = trim($data['special_instructions']);
            }
            
            // If no fields to update
            if (empty($update_fields)) {
                return ['success' => false, 'message' => 'No valid fields provided for update.'];
            }
            
            // If package dimensions or weight changed, recalculate costs
            $recalculate_cost = false;
            $cost_affecting_fields = ['package_weight', 'package_length', 'package_width', 'package_height', 'transport_type', 'urgent_delivery'];
            foreach ($cost_affecting_fields as $field) {
                if (isset($data[$field])) {
                    $recalculate_cost = true;
                    break;
                }
            }
            
            if ($recalculate_cost) {
                // Get updated order data for cost calculation
                $updated_order_data = array_merge($order, $data);
                $cost_calculation = $this->order_model->calculateShippingCost($updated_order_data);
                
                $update_fields[] = "base_cost = ?";
                $params[] = $cost_calculation['base_cost'];
                
                $update_fields[] = "urgent_surcharge = ?";
                $params[] = $cost_calculation['urgent_surcharge'];
                
                $update_fields[] = "total_cost = ?";
                $params[] = $cost_calculation['total_cost'];
            }
            
            // Add updated timestamp
            $update_fields[] = "updated_at = CURRENT_TIMESTAMP";
            
            // Add order ID for WHERE clause
            $params[] = $order_id;
            
            // Execute update
            $sql = "UPDATE orders SET " . implode(', ', $update_fields) . " WHERE id = ?";
            $stmt = $this->pdo->prepare($sql);
            $result = $stmt->execute($params);
            
            if ($result) {
                $message = 'Order details updated successfully.';
                if ($recalculate_cost) {
                    $message .= ' Costs have been recalculated.';
                }
                
                // Log the action
                $this->logEmployeeAction($this->getCurrentEmployeeId(), $order_id, 'order_edit', 'Order details updated');
                
                return ['success' => true, 'message' => $message];
            } else {
                return ['success' => false, 'message' => 'Failed to update order details.'];
            }
            
        } catch (Exception $e) {
            error_log("Order edit error: " . $e->getMessage());
            return ['success' => false, 'message' => 'An error occurred while updating the order.'];
        }
    }
    
    /**
     * Handle order deletion
     * @param array $data POST data
     * @return array Result with success status and message
     */
    public function handleOrderDeletion($data) {
        try {
            $order_id = intval($data['order_id']);
            
            // Get order details
            $order = $this->order_model->findById($order_id);
            if (!$order) {
                return ['success' => false, 'message' => 'Order not found.'];
            }
            
            // Check if order can be deleted (only pending and confirmed orders)
            $deletable_statuses = ['pending', 'confirmed'];
            if (!in_array($order['status'], $deletable_statuses)) {
                return [
                    'success' => false, 
                    'message' => 'Only pending and confirmed orders can be deleted. Current status: ' . Order::getStatusLabel($order['status'])
                ];
            }
            
            // Soft delete by setting status to cancelled with special flag
            $result = $this->deleteOrder($order_id);
            
            if ($result) {
                return [
                    'success' => true, 
                    'message' => 'Order #' . $order['order_number'] . ' has been deleted successfully.'
                ];
            } else {
                return ['success' => false, 'message' => 'Failed to delete order.'];
            }
            
        } catch (Exception $e) {
            error_log("Order deletion error: " . $e->getMessage());
            return ['success' => false, 'message' => 'An error occurred while deleting the order.'];
        }
    }
    
    /**
     * Get all orders with filters for employee view
     * @param array $filters Filter parameters
     * @param int $page Page number
     * @param int $per_page Items per page
     * @return array Orders data with pagination
     */
    public function getOrdersWithFilters($filters, $page = 1, $per_page = 15) {
        try {
            return $this->order_model->findAllOrdersWithFilters($filters, $page, $per_page);
        } catch (Exception $e) {
            error_log("Get orders error: " . $e->getMessage());
            return ['data' => [], 'total' => 0, 'pages' => 0, 'current_page' => 1, 'per_page' => $per_page];
        }
    }
    
    /**
     * Get order by ID for employee view
     * @param int $order_id Order ID
     * @return array|false Order data or false if not found
     */
    public function getOrderById($order_id) {
        try {
            return $this->order_model->findById($order_id);
        } catch (Exception $e) {
            error_log("Get order by ID error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get all active customers for order creation
     * @return array Customer list
     */
    public function getActiveCustomers() {
        try {
            return $this->user_model->getAllUsers(['role' => 'customer', 'status' => 'active']);
        } catch (Exception $e) {
            error_log("Get customers error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get customer by ID
     * @param int $customer_id Customer ID
     * @return array|false Customer data or false if not found
     */
    public function getCustomerById($customer_id) {
        try {
            $customer = $this->user_model->findById($customer_id);
            return ($customer && $customer['role'] === 'customer') ? $customer : false;
        } catch (Exception $e) {
            error_log("Get customer by ID error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get order statistics for dashboard
     * @return array Statistics
     */
    public function getOrderStatistics() {
        try {
            $stats = [];
            
            // Get basic counts
            $stmt = $this->pdo->prepare("SELECT COUNT(*) as total FROM orders");
            $stmt->execute();
            $stats['total_orders'] = $stmt->fetchColumn();
            
            $stmt = $this->pdo->prepare("SELECT COUNT(*) as pending FROM orders WHERE status = 'pending'");
            $stmt->execute();
            $stats['pending_orders'] = $stmt->fetchColumn();
            
            $stmt = $this->pdo->prepare("SELECT COUNT(*) as confirmed FROM orders WHERE status = 'confirmed'");
            $stmt->execute();
            $stats['confirmed_orders'] = $stmt->fetchColumn();
            
            $stmt = $this->pdo->prepare("SELECT COUNT(*) as today FROM orders WHERE DATE(created_at) = CURDATE()");
            $stmt->execute();
            $stats['orders_today'] = $stmt->fetchColumn();
            
            return $stats;
            
        } catch (Exception $e) {
            error_log("Get order statistics error: " . $e->getMessage());
            return [
                'total_orders' => 0,
                'pending_orders' => 0,
                'confirmed_orders' => 0,
                'orders_today' => 0
            ];
        }
    }
    
    /**
     * Validate order access for employee
     * @param int $order_id Order ID
     * @param int $employee_id Employee ID
     * @return bool Whether employee can access this order
     */
    public function canAccessOrder($order_id, $employee_id) {
        // Employees can access all orders
        return $this->order_model->findById($order_id) !== false;
    }
    
    /**
     * Delete order (soft delete)
     * @param int $order_id Order ID
     * @return bool Success
     */
    private function deleteOrder($order_id) {
        try {
            // Soft delete by updating status to 'deleted'
            $sql = "UPDATE orders SET status = 'deleted', updated_at = CURRENT_TIMESTAMP WHERE id = ?";
            $stmt = $this->pdo->prepare($sql);
            return $stmt->execute([$order_id]);
            
        } catch (PDOException $e) {
            error_log("Delete order error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get appropriate message for status update
     * @param string $status New status
     * @return string Message
     */
    private function getStatusUpdateMessage($status) {
        $messages = [
            'pending' => 'Order set to pending status.',
            'confirmed' => 'Order confirmed and tracking number assigned.',
            'processing' => 'Order is now being processed.',
            'shipped' => 'Order has been shipped.',
            'in_transit' => 'Order is now in transit.',
            'delivered' => 'Order has been delivered.',
            'cancelled' => 'Order has been cancelled.'
        ];
        
        return $messages[$status] ?? 'Order status updated successfully.';
    }
    
    /**
     * Get current employee ID (simple implementation)
     * @return int Employee ID
     */
    private function getCurrentEmployeeId() {
        // Simple implementation - in a full system you'd get this from session
        if (isset($_SESSION['user_id'])) {
            return $_SESSION['user_id'];
        }
        return 0; // Default for logging purposes
    }
    
    /**
     * Log employee action for audit trail
     * @param int $employee_id Employee ID
     * @param int $order_id Order ID
     * @param string $action Action performed
     * @param string $details Additional details
     */
    public function logEmployeeAction($employee_id, $order_id, $action, $details = '') {
        try {
            $sql = "INSERT INTO employee_actions (employee_id, order_id, action, details, created_at) VALUES (?, ?, ?, ?, CURRENT_TIMESTAMP)";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$employee_id, $order_id, $action, $details]);
        } catch (PDOException $e) {
            error_log("Log employee action error: " . $e->getMessage());
            // Don't throw error, just log it
        }
    }
}
?>
