<?php
/**
 * Order Model
 */

class Order {
    private $pdo;
    private $table = 'orders';
    
    // Order properties
    public $id;
    public $user_id;
    public $order_number;
    public $pickup_address;
    public $delivery_address;
    public $package_weight;
    public $package_length;
    public $package_width;
    public $package_height;
    public $package_description;
    public $transport_type;
    public $urgent_delivery;
    public $special_instructions;
    public $base_cost;
    public $urgent_surcharge;
    public $total_cost;
    public $status;
    public $tracking_number;
    public $created_at;
    public $updated_at;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    /**
     * Create a new order
     * @param array $data Order data
     * @return array Result with success status and message/order_id
     */
    public function create($data) {
        try {
            // Validate required fields
            $required = ['user_id', 'pickup_address', 'delivery_address', 'package_weight', 
                        'package_description', 'transport_type'];
            foreach ($required as $field) {
                if (empty($data[$field])) {
                    return ['success' => false, 'message' => "Field '$field' is required"];
                }
            }
            
            // Validate transport type
            $valid_transports = ['land', 'air', 'ocean'];
            if (!in_array($data['transport_type'], $valid_transports)) {
                return ['success' => false, 'message' => 'Invalid transport type'];
            }
            
            // Validate weight
            if ($data['package_weight'] <= 0 || $data['package_weight'] > 1000) {
                return ['success' => false, 'message' => 'Package weight must be between 0.1 and 1000 kg'];
            }
            
            // Calculate costs
            $cost_data = $this->calculateShippingCost($data);
            
            // Generate unique order number
            $order_number = $this->generateOrderNumber();
            
            // Prepare SQL statement
            $sql = "INSERT INTO {$this->table} (
                user_id, order_number, pickup_address, delivery_address,
                package_weight, package_length, package_width, package_height, package_description,
                transport_type, urgent_delivery, special_instructions,
                base_cost, urgent_surcharge, total_cost
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            
            $stmt = $this->pdo->prepare($sql);
            $result = $stmt->execute([
                $data['user_id'],
                $order_number,
                $data['pickup_address'],
                $data['delivery_address'],
                $data['package_weight'],
                $data['package_length'] ?? null,
                $data['package_width'] ?? null,
                $data['package_height'] ?? null,
                $data['package_description'],
                $data['transport_type'],
                isset($data['urgent_delivery']) ? 1 : 0,
                $data['special_instructions'] ?? null,
                $cost_data['base'],
                $cost_data['urgent_surcharge'],
                $cost_data['total']
            ]);
            
            if ($result) {
                $this->id = $this->pdo->lastInsertId();
                return [
                    'success' => true, 
                    'message' => 'Order created successfully!',
                    'order_id' => $this->id,
                    'order_number' => $order_number,
                    'total_cost' => $cost_data['total']
                ];
            }
            
            return ['success' => false, 'message' => 'Failed to create order'];
            
        } catch (PDOException $e) {
            error_log("Order creation error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Database error occurred'];
        }
    }
    
    /**
     * Find order by ID
     * @param int $id Order ID
     * @return array|false Order data or false
     */
    public function findById($id) {
        try {
            $sql = "SELECT * FROM {$this->table} WHERE id = ?";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$id]);
            $order = $stmt->fetch();
            
            if ($order) {
                $this->setOrderProperties($order);
                return $order;
            }
            
            return false;
            
        } catch (PDOException $e) {
            error_log("Find order error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Find orders by user ID
     * @param int $user_id User ID
     * @param int $limit Limit number of results
     * @param string $status Filter by status (optional)
     * @return array Orders array
     */
    public function findByUserId($user_id, $limit = null, $status = null) {
        try {
            $sql = "SELECT * FROM {$this->table} WHERE user_id = ?";
            $params = [$user_id];
            
            if ($status) {
                $sql .= " AND status = ?";
                $params[] = $status;
            }
            
            $sql .= " ORDER BY created_at DESC";
            
            // Don't bind LIMIT as a parameter - build it directly into SQL
            if ($limit) {
                $sql .= " LIMIT " . intval($limit);
            }
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll();
            
        } catch (PDOException $e) {
            error_log("Find orders by user error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Find all orders with advanced filtering for employee/admin view
     * @param array $filters Filtering options
     * @param int $page Page number (1-based)
     * @param int $per_page Orders per page
     * @return array Result with orders and pagination info
     */
    public function findAllOrdersWithFilters($filters = [], $page = 1, $per_page = 10) {
        try {
            $where_conditions = [];
            $params = [];
            
            // Status filter
            if (!empty($filters['status'])) {
                $where_conditions[] = "o.status = ?";
                $params[] = $filters['status'];
            }
            
            // Transport type filter
            if (!empty($filters['transport_type'])) {
                $where_conditions[] = "o.transport_type = ?";
                $params[] = $filters['transport_type'];
            }
            
            // Search in order number, package description, addresses, customer name
            if (!empty($filters['search'])) {
                $search_term = '%' . $filters['search'] . '%';
                $where_conditions[] = "(o.order_number LIKE ? OR o.tracking_number LIKE ? OR o.package_description LIKE ? OR o.pickup_address LIKE ? OR o.delivery_address LIKE ? OR u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ?)";
                $params = array_merge($params, [$search_term, $search_term, $search_term, $search_term, $search_term, $search_term, $search_term, $search_term]);
            }
            
            // Date range filter
            if (!empty($filters['date_from'])) {
                $where_conditions[] = "DATE(o.created_at) >= ?";
                $params[] = $filters['date_from'];
            }
            if (!empty($filters['date_to'])) {
                $where_conditions[] = "DATE(o.created_at) <= ?";
                $params[] = $filters['date_to'];
            }
            
            // Build WHERE clause
            $where_clause = !empty($where_conditions) ? ' WHERE ' . implode(' AND ', $where_conditions) : '';
            
            // Count total records for pagination
            $count_sql = "SELECT COUNT(*) FROM {$this->table} o JOIN users u ON o.user_id = u.id" . $where_clause;
            $count_stmt = $this->pdo->prepare($count_sql);
            $count_stmt->execute($params);
            $total_records = $count_stmt->fetchColumn();
            
            // Calculate pagination
            $total_pages = ceil($total_records / $per_page);
            $offset = ($page - 1) * $per_page;
            
            // Get orders with customer details
            $orders_sql = "SELECT o.*, u.first_name, u.last_name, u.email, 
                          CONCAT(u.first_name, ' ', u.last_name) as customer_name,
                          u.email as customer_email 
                          FROM {$this->table} o 
                          JOIN users u ON o.user_id = u.id" . 
                          $where_clause . 
                          " ORDER BY o.created_at DESC LIMIT " . intval($per_page) . " OFFSET " . intval($offset);
            
            $orders_stmt = $this->pdo->prepare($orders_sql);
            $orders_stmt->execute($params);
            $orders = $orders_stmt->fetchAll();
            
            return [
                'data' => $orders,
                'total' => $total_records,
                'pages' => $total_pages,
                'current_page' => $page,
                'per_page' => $per_page
            ];
            
        } catch (PDOException $e) {
            error_log("Find all orders with filters error: " . $e->getMessage());
            return [
                'data' => [],
                'total' => 0,
                'pages' => 0,
                'current_page' => 1,
                'per_page' => $per_page
            ];
        }
    }

    /**
     * Find orders with advanced filtering and pagination
     * @param int $user_id User ID
     * @param array $filters Filtering options
     * @param int $page Page number (1-based)
     * @param int $per_page Orders per page
     * @return array Result with orders and pagination info
     */
    public function findOrdersWithFilters($user_id, $filters = [], $page = 1, $per_page = 10) {
        try {
            $where_conditions = ["user_id = ?"];
            $params = [$user_id];
            
            // Status filter
            if (!empty($filters['status'])) {
                $where_conditions[] = "status = ?";
                $params[] = $filters['status'];
            }
            
            // Transport type filter
            if (!empty($filters['transport_type'])) {
                $where_conditions[] = "transport_type = ?";
                $params[] = $filters['transport_type'];
            }
            
            // Search in order number, package description, addresses
            if (!empty($filters['search'])) {
                $search_term = '%' . $filters['search'] . '%';
                $where_conditions[] = "(order_number LIKE ? OR package_description LIKE ? OR pickup_address LIKE ? OR delivery_address LIKE ?)";
                $params = array_merge($params, [$search_term, $search_term, $search_term, $search_term]);
            }
            
            // Date range filter
            if (!empty($filters['date_from'])) {
                $where_conditions[] = "DATE(created_at) >= ?";
                $params[] = $filters['date_from'];
            }
            if (!empty($filters['date_to'])) {
                $where_conditions[] = "DATE(created_at) <= ?";
                $params[] = $filters['date_to'];
            }
            
            // Build WHERE clause
            $where_clause = implode(' AND ', $where_conditions);
            
            // Count total records for pagination
            $count_sql = "SELECT COUNT(*) FROM {$this->table} WHERE " . $where_clause;
            $count_stmt = $this->pdo->prepare($count_sql);
            $count_stmt->execute($params);
            $total_records = $count_stmt->fetchColumn();
            
            // Calculate pagination
            $total_pages = ceil($total_records / $per_page);
            $offset = ($page - 1) * $per_page;
            
            // Get orders with pagination
            $orders_sql = "SELECT * FROM {$this->table} WHERE " . $where_clause . " ORDER BY created_at DESC LIMIT " . intval($per_page) . " OFFSET " . intval($offset);
            $orders_stmt = $this->pdo->prepare($orders_sql);
            $orders_stmt->execute($params);
            $orders = $orders_stmt->fetchAll();
            
            return [
                'orders' => $orders,
                'pagination' => [
                    'current_page' => $page,
                    'per_page' => $per_page,
                    'total_records' => $total_records,
                    'total_pages' => $total_pages,
                    'has_previous' => $page > 1,
                    'has_next' => $page < $total_pages,
                    'previous_page' => max(1, $page - 1),
                    'next_page' => min($total_pages, $page + 1)
                ]
            ];
            
        } catch (PDOException $e) {
            error_log("Find orders with filters error: " . $e->getMessage());
            return [
                'orders' => [],
                'pagination' => [
                    'current_page' => 1,
                    'per_page' => $per_page,
                    'total_records' => 0,
                    'total_pages' => 0,
                    'has_previous' => false,
                    'has_next' => false,
                    'previous_page' => 1,
                    'next_page' => 1
                ]
            ];
        }
    }
    
    /**
     * Update order status
     * @param int $id Order ID
     * @param string $status New status
     * @param string $tracking_number Optional tracking number
     * @return bool Success
     */
    public function updateStatus($id, $status, $tracking_number = null) {
        try {
            $valid_statuses = ['pending', 'confirmed', 'processing', 'in_transit', 'delivered', 'cancelled'];
            if (!in_array($status, $valid_statuses)) {
                return false;
            }
            
            $sql = "UPDATE {$this->table} SET status = ?";
            $params = [$status];
            
            if ($tracking_number) {
                $sql .= ", tracking_number = ?";
                $params[] = $tracking_number;
            }
            
            $sql .= " WHERE id = ?";
            $params[] = $id;
            
            $stmt = $this->pdo->prepare($sql);
            return $stmt->execute($params);
            
        } catch (PDOException $e) {
            error_log("Update order status error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Cancel an order (only if pending)
     * @param int $id Order ID
     * @return bool Success
     */
    public function cancelOrder($id) {
        try {
            // First, check if order exists and is in pending status
            $order = $this->findById($id);
            if (!$order) {
                error_log("Cancel order error: Order not found - ID: $id");
                return false;
            }
            
            if ($order['status'] !== 'pending') {
                error_log("Cancel order error: Order not in pending status - ID: $id, Status: " . $order['status']);
                return false;
            }
            
            // Update status to cancelled
            $sql = "UPDATE {$this->table} SET status = 'cancelled', updated_at = CURRENT_TIMESTAMP WHERE id = ?";
            $stmt = $this->pdo->prepare($sql);
            $result = $stmt->execute([$id]);
            
            if ($result) {
                error_log("Order cancelled successfully - ID: $id");
            }
            
            return $result;
            
        } catch (PDOException $e) {
            error_log("Cancel order error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get order statistics for a user
     * @param int $user_id User ID
     * @return array Statistics
     */
    public function getUserStats($user_id) {
        try {
            $stats = [];
            
            // Total orders
            $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM {$this->table} WHERE user_id = ?");
            $stmt->execute([$user_id]);
            $stats['total_orders'] = $stmt->fetchColumn();
            
            // Active shipments
            $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM {$this->table} WHERE user_id = ? AND status IN ('confirmed', 'processing', 'in_transit')");
            $stmt->execute([$user_id]);
            $stats['active_shipments'] = $stmt->fetchColumn();
            
            // Last order date
            $stmt = $this->pdo->prepare("SELECT created_at FROM {$this->table} WHERE user_id = ? ORDER BY created_at DESC LIMIT 1");
            $stmt->execute([$user_id]);
            $last_order = $stmt->fetchColumn();
            $stats['last_order'] = $last_order ? date('M j, Y', strtotime($last_order)) : 'N/A';
            
            return $stats;
            
        } catch (PDOException $e) {
            error_log("Get user stats error: " . $e->getMessage());
            return ['total_orders' => 0, 'active_shipments' => 0, 'last_order' => 'N/A'];
        }
    }
    
    /**
     * Calculate shipping cost based on order data
     * @param array $order_data Order data
     * @return array Cost breakdown
     */
    public function calculateShippingCost($order_data) {
        $weight = $order_data['package_weight'];
        $transport = $order_data['transport_type'];
        $urgent = isset($order_data['urgent_delivery']) && $order_data['urgent_delivery'];
        
        // Base rates per kg by transport type
        $rates = [
            'land' => ['base' => 12],
            'air' => ['base' => 30],
            'ocean' => ['base' => 6]
        ];
        
        $rate = $rates[$transport]['base'];
        
        // Add some variation (±20%)
        $variation = rand(-20, 20) / 100;
        $adjusted_rate = $rate * (1 + $variation);
        
        // Calculate cost based on weight
        $calculated_cost = $weight * $adjusted_rate;
        
        // Minimum charges by transport type
        $minimum_charges = ['land' => 25, 'air' => 75, 'ocean' => 15];
        $minimum_charge = $minimum_charges[$transport];
        
        // Base cost is the higher of calculated cost or minimum charge
        $base_cost = max($calculated_cost, $minimum_charge);
        
        // Urgent delivery surcharge (50%)
        $urgent_surcharge = $urgent ? $base_cost * 0.5 : 0;
        
        // Total cost
        $total = $base_cost + $urgent_surcharge;
        
        return [
            'calculated' => round($calculated_cost, 2),
            'minimum' => $minimum_charge,
            'base' => round($base_cost, 2),
            'urgent_surcharge' => round($urgent_surcharge, 2),
            'total' => round($total, 2),
            'is_minimum_applied' => $calculated_cost < $minimum_charge,
            'rate_used' => round($adjusted_rate, 2)
        ];
    }
    
    /**
     * Get cost estimation without creating order
     * @param array $data Order data for estimation
     * @return array Cost breakdown
     */
    public function estimateCost($data) {
        // Validate basic requirements for estimation
        if (empty($data['package_weight']) || empty($data['transport_type'])) {
            return ['error' => 'Weight and transport type required for estimation'];
        }
        
        return $this->calculateShippingCost($data);
    }
    
    // Helper methods
    
    /**
     * Generate unique order number
     * @return string Order number
     */
    private function generateOrderNumber() {
        do {
            $order_number = 'ORD-' . date('Y') . '-' . str_pad(rand(1000, 9999), 4, '0', STR_PAD_LEFT);
            
            // Check if order number already exists
            $stmt = $this->pdo->prepare("SELECT id FROM {$this->table} WHERE order_number = ?");
            $stmt->execute([$order_number]);
            $exists = $stmt->fetch();
            
        } while ($exists);
        
        return $order_number;
    }
    
    /**
     * Set order properties from database result
     * @param array $order Order data
     */
    private function setOrderProperties($order) {
        $this->id = $order['id'];
        $this->user_id = $order['user_id'];
        $this->order_number = $order['order_number'];
        $this->pickup_address = $order['pickup_address'];
        $this->delivery_address = $order['delivery_address'];
        $this->package_weight = $order['package_weight'];
        $this->package_length = $order['package_length'];
        $this->package_width = $order['package_width'];
        $this->package_height = $order['package_height'];
        $this->package_description = $order['package_description'];
        $this->transport_type = $order['transport_type'];
        $this->urgent_delivery = $order['urgent_delivery'];
        $this->special_instructions = $order['special_instructions'];
        $this->base_cost = $order['base_cost'];
        $this->urgent_surcharge = $order['urgent_surcharge'];
        $this->total_cost = $order['total_cost'];
        $this->status = $order['status'];
        $this->tracking_number = $order['tracking_number'];
        $this->created_at = $order['created_at'];
        $this->updated_at = $order['updated_at'];
    }
    
    /**
     * Get human-readable status
     * @param string $status Status code
     * @return string Human-readable status
     */
    public static function getStatusLabel($status) {
        $labels = [
            'pending' => 'Pending Confirmation',
            'confirmed' => 'Confirmed',
            'processing' => 'Processing',
            'in_transit' => 'In Transit',
            'delivered' => 'Delivered',
            'cancelled' => 'Cancelled'
        ];
        
        return $labels[$status] ?? ucfirst($status);
    }
    
    /**
     * Get transport type display name
     * @param string $type Transport type
     * @return string Display name
     */
    public static function getTransportLabel($type) {
        $labels = [
            'land' => 'Land Transport',
            'air' => 'Air Transport',
            'ocean' => 'Ocean Transport'
        ];
        
        return $labels[$type] ?? ucfirst($type);
    }
    
    /**
     * Find order by tracking number or order number
     * @param string $identifier Tracking number or order number
     * @return array|false Order data or false
     */
    public function findByTrackingOrOrder($identifier) {
        try {
            // Try tracking number first
            $sql = "SELECT * FROM {$this->table} WHERE tracking_number = ?";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$identifier]);
            $order = $stmt->fetch();
            
            if ($order) {
                return $order;
            }
            
            // Try order number
            $sql = "SELECT * FROM {$this->table} WHERE order_number = ?";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$identifier]);
            return $stmt->fetch();
            
        } catch (PDOException $e) {
            error_log("Find by tracking/order error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Find order for public tracking (limited data exposure)
     * @param string $identifier Tracking number or order number
     * @return array|false Filtered order data or false
     */
    public function findForPublicTracking($identifier) {
        try {
            $order = $this->findByTrackingOrOrder($identifier);
            
            if (!$order) {
                return false;
            }
            
            // Return filtered order data for public access
            // Hide sensitive customer information
            return [
                'id' => $order['id'],
                'order_number' => $order['order_number'],
                'tracking_number' => $order['tracking_number'],
                'transport_type' => $order['transport_type'],
                'package_weight' => $order['package_weight'],
                'package_length' => $order['package_length'],
                'package_width' => $order['package_width'],
                'package_height' => $order['package_height'],
                'package_description' => $order['package_description'],
                'urgent_delivery' => $order['urgent_delivery'],
                'status' => $order['status'],
                'created_at' => $order['created_at'],
                'updated_at' => $order['updated_at'],
                // Hide sensitive customer data
                'pickup_address' => $this->sanitizeAddressForPublic($order['pickup_address']),
                'delivery_address' => $this->sanitizeAddressForPublic($order['delivery_address']),
                // Don't expose: user_id, special_instructions, cost details
                'is_public_view' => true
            ];
            
        } catch (PDOException $e) {
            error_log("Find for public tracking error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Sanitize address for public display (hide full details)
     * @param string $address Full address
     * @return string Sanitized address
     */
    private function sanitizeAddressForPublic($address) {
        if (!$address) return '';
        
        $lines = explode("\n", $address);
        $city_line = '';
        
        // Look for city/state/country in address lines
        foreach ($lines as $line) {
            $line = trim($line);
            // Simple heuristic: lines with commas often contain city, state
            if (strpos($line, ',') !== false) {
                $city_line = $line;
                break;
            }
        }
        
        // If we found a city line, return just city/state
        if ($city_line) {
            return $city_line;
        }
        
        // Fallback: return last line (often city/country)
        return trim(end($lines));
    }
    
    /**
     * Generate mock tracking timeline based on current status
     * @param array $order Order data
     * @return array Timeline events
     */
    public function generateTrackingTimeline($order) {
        if (!$order) return [];
        
        $timeline = [];
        $base_time = strtotime($order['created_at']);
        $current_status = $order['status'];
        
        // Define status progression and timing
        $status_progression = [
            'pending' => ['label' => 'Order Received', 'icon' => '📝', 'location' => 'Order Processing Center'],
            'confirmed' => ['label' => 'Order Confirmed', 'icon' => '✅', 'location' => 'Order Processing Center'],
            'processing' => ['label' => 'Package Prepared', 'icon' => '📦', 'location' => 'Fulfillment Center'],
            'in_transit' => ['label' => 'In Transit', 'icon' => $this->getTransportIcon($order['transport_type']), 'location' => 'Transport Hub'],
            'delivered' => ['label' => 'Delivered', 'icon' => '🏠', 'location' => 'Customer Address']
        ];
        
        // Special handling for cancelled orders
        if ($current_status === 'cancelled') {
            $status_progression['cancelled'] = ['label' => 'Order Cancelled', 'icon' => '❌', 'location' => 'Order Processing Center'];
        }
        
        // Time intervals between statuses (in hours)
        $time_intervals = [
            'confirmed' => rand(2, 6),    // 2-6 hours after order
            'processing' => rand(8, 24),   // 8-24 hours after confirmed
            'in_transit' => rand(4, 12),   // 4-12 hours after processing
            'delivered' => $this->getDeliveryTime($order['transport_type']), // Transport-specific
            'cancelled' => rand(1, 48)     // Could be cancelled anytime
        ];
        
        $current_time = $base_time;
        
        foreach ($status_progression as $status => $details) {
            $event_time = $current_time;
            
            // Determine if this event has happened
            $has_happened = $this->hasStatusHappened($status, $current_status);
            $is_current = ($status === $current_status);
            
            // For cancelled orders, only show progression up to cancellation
            if ($current_status === 'cancelled') {
                // Skip future normal statuses that won't happen
                if (!$has_happened && !$is_current && $status !== 'cancelled') {
                    continue;
                }
            }
            
            $timeline[] = [
                'status' => $status,
                'label' => $details['label'],
                'icon' => $details['icon'],
                'location' => $details['location'],
                'timestamp' => $event_time,
                'date' => date('M j, Y', $event_time),
                'time' => date('g:i A', $event_time),
                'has_happened' => $has_happened,
                'is_current' => $is_current,
                'is_future' => !$has_happened && !$is_current,
                'is_cancelled' => $status === 'cancelled'
            ];
            
            // Add time for next status
            if (isset($time_intervals[$status])) {
                $current_time += $time_intervals[$status] * 3600;
            }
            
            // Stop at current status (don't show future events)
            if ($status === $current_status) {
                break;
            }
        }
        
        return $timeline;
    }
    
    /**
     * Get transport icon for timeline
     * @param string $transport_type Transport type
     * @return string Icon
     */
    public static function getTransportIcon($transport_type) {
        $icons = [
            'land' => '🚛',
            'air' => '✈️',
            'ocean' => '🚢'
        ];
        return $icons[$transport_type] ?? '🚚';
    }
    
    /**
     * Get delivery time based on transport type (in hours)
     * @param string $transport_type Transport type
     * @return int Hours until delivery
     */
    private function getDeliveryTime($transport_type) {
        $delivery_times = [
            'land' => rand(24, 72),    // 1-3 days
            'air' => rand(12, 24),     // 12-24 hours
            'ocean' => rand(120, 240)  // 5-10 days
        ];
        return $delivery_times[$transport_type] ?? 48;
    }
    
    /**
     * Check if a status has happened based on current status
     * @param string $check_status Status to check
     * @param string $current_status Current order status
     * @return bool Has happened
     */
    private function hasStatusHappened($check_status, $current_status) {
        $status_order = ['pending', 'confirmed', 'processing', 'in_transit', 'delivered'];
        
        // Special case for cancelled orders
        if ($current_status === 'cancelled') {
            // Cancelled orders could have progressed to different points before cancellation
            // For simplicity, assume cancellation happened after confirmation
            // In real system, this would come from status history
            return in_array($check_status, ['pending', 'confirmed']);
        }
        
        $check_index = array_search($check_status, $status_order);
        $current_index = array_search($current_status, $status_order);
        
        return $check_index !== false && $current_index !== false && $check_index < $current_index;
    }
    
    /**
     * Get estimated delivery date
     * @param array $order Order data
     * @return array Delivery estimate info
     */
    public function getDeliveryEstimate($order) {
        if (!$order) return null;
        
        if ($order['status'] === 'delivered') {
            return [
                'status' => 'delivered',
                'message' => 'Package has been delivered',
                'date' => null,
                'is_estimate' => false
            ];
        }
        
        if ($order['status'] === 'cancelled') {
            return [
                'status' => 'cancelled',
                'message' => 'Order has been cancelled',
                'date' => null,
                'is_estimate' => false
            ];
        }
        
        // Calculate estimated delivery based on transport type and current status
        $base_time = strtotime($order['created_at']);
        $delivery_days = [
            'land' => $order['urgent_delivery'] ? rand(1, 2) : rand(2, 5),
            'air' => $order['urgent_delivery'] ? 1 : rand(1, 3),
            'ocean' => $order['urgent_delivery'] ? rand(7, 10) : rand(10, 15)
        ];
        
        $days = $delivery_days[$order['transport_type']] ?? 3;
        $estimated_delivery = $base_time + ($days * 24 * 3600);
        
        // Adjust based on current status
        $status_adjustments = [
            'pending' => 0,
            'confirmed' => -0.5,
            'processing' => -1,
            'in_transit' => -1.5
        ];
        
        if (isset($status_adjustments[$order['status']])) {
            $estimated_delivery += $status_adjustments[$order['status']] * 24 * 3600;
        }
        
        return [
            'status' => 'estimated',
            'message' => $order['urgent_delivery'] ? 'Urgent delivery' : 'Standard delivery',
            'date' => date('M j, Y', $estimated_delivery),
            'day_name' => date('l', $estimated_delivery),
            'is_estimate' => true,
            'days_remaining' => max(0, ceil(($estimated_delivery - time()) / (24 * 3600)))
        ];
    }
}
?>
