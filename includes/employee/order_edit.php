<?php
/**
 * Order Edit View for Employee
 * Process and update order status
 */

// Get customer details
$customer = $order_handler->getCustomerById($edit_order['user_id']);
?>

<!-- Order Processing Form -->
<div class="user-form-container">
    <div class="order-details-header">
        <h3>📦 Order Details</h3>
        <div class="order-summary">
            <div class="order-info">
                <strong>Order #<?php echo htmlspecialchars($edit_order['order_number']); ?></strong>
                <span class="status-badge status-<?php echo $edit_order['status']; ?>">
                    <?php echo Order::getStatusLabel($edit_order['status']); ?>
                </span>
                <?php if ($edit_order['tracking_number']): ?>
                    <p><small>Tracking: <?php echo htmlspecialchars($edit_order['tracking_number']); ?></small></p>
                <?php endif; ?>
            </div>
            <div class="order-meta">
                <p><strong>Customer:</strong> <?php echo htmlspecialchars($customer['first_name'] . ' ' . $customer['last_name']); ?></p>
                <p><strong>Email:</strong> <?php echo htmlspecialchars($customer['email']); ?></p>
                <p><strong>Total:</strong> $<?php echo number_format($edit_order['total_cost'], 2); ?></p>
                <p><strong>Transport:</strong> <?php echo Order::getTransportLabel($edit_order['transport_type']); ?></p>
                <p><strong>Created:</strong> <?php echo date('M j, Y g:i A', strtotime($edit_order['created_at'])); ?></p>
            </div>
        </div>
    </div>

    <!-- Order Details Section -->
    <div class="form-sections">
        <div class="form-section">
            <h3>📋 Order Information</h3>
            <div class="order-details-grid">
                <div class="detail-group">
                    <h4>📍 Addresses</h4>
                    <div class="address-block">
                        <strong>Pickup:</strong>
                        <p><?php echo nl2br(htmlspecialchars($edit_order['pickup_address'])); ?></p>
                    </div>
                    <div class="address-block">
                        <strong>Delivery:</strong>
                        <p><?php echo nl2br(htmlspecialchars($edit_order['delivery_address'])); ?></p>
                    </div>
                </div>
                
                <div class="detail-group">
                    <h4>📦 Package Details</h4>
                    <p><strong>Weight:</strong> <?php echo $edit_order['package_weight']; ?> kg</p>
                    <p><strong>Dimensions:</strong> <?php echo $edit_order['package_length']; ?> × <?php echo $edit_order['package_width']; ?> × <?php echo $edit_order['package_height']; ?> cm</p>
                    <p><strong>Description:</strong> <?php echo htmlspecialchars($edit_order['package_description']); ?></p>
                    <?php if ($edit_order['urgent_delivery']): ?>
                        <p><strong>⚡ Urgent Delivery:</strong> Yes</p>
                    <?php endif; ?>
                    <?php if ($edit_order['special_instructions']): ?>
                        <p><strong>Special Instructions:</strong> <?php echo nl2br(htmlspecialchars($edit_order['special_instructions'])); ?></p>
                    <?php endif; ?>
                </div>
                
                <div class="detail-group">
                    <h4>💰 Cost Breakdown</h4>
                    <p><strong>Base Cost:</strong> $<?php echo number_format($edit_order['base_cost'], 2); ?></p>
                    <?php if ($edit_order['urgent_surcharge'] > 0): ?>
                        <p><strong>Urgent Surcharge:</strong> $<?php echo number_format($edit_order['urgent_surcharge'], 2); ?></p>
                    <?php endif; ?>
                    <p><strong>Total Cost:</strong> $<?php echo number_format($edit_order['total_cost'], 2); ?></p>
                </div>
            </div>
        </div>
    </div>

    <!-- Status Update Form -->
    <form method="POST" class="user-form">
        <input type="hidden" name="order_id" value="<?php echo $edit_order['id']; ?>">

        <div class="form-sections">
            <div class="form-section">
                <h3>🚚 Update Status</h3>
                <div class="form-grid">
                    <div class="form-group">
                        <label for="status">New Status *</label>
                        <select id="status" name="status" required>
                            <option value="pending" <?php echo $edit_order['status'] === 'pending' ? 'selected' : ''; ?>>Pending</option>
                            <option value="confirmed" <?php echo $edit_order['status'] === 'confirmed' ? 'selected' : ''; ?>>Confirmed</option>
                            <option value="processing" <?php echo $edit_order['status'] === 'processing' ? 'selected' : ''; ?>>Processing</option>
                            <option value="shipped" <?php echo $edit_order['status'] === 'shipped' ? 'selected' : ''; ?>>Shipped</option>
                            <option value="in_transit" <?php echo $edit_order['status'] === 'in_transit' ? 'selected' : ''; ?>>In Transit</option>
                            <option value="delivered" <?php echo $edit_order['status'] === 'delivered' ? 'selected' : ''; ?>>Delivered</option>
                            <option value="cancelled" <?php echo $edit_order['status'] === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="tracking_number">Tracking Number</label>
                        <input type="text" 
                               id="tracking_number" 
                               name="tracking_number" 
                               value="<?php echo htmlspecialchars($edit_order['tracking_number'] ?? ''); ?>"
                               placeholder="Enter tracking number">
                        <small class="form-help">Leave blank to auto-generate when confirming</small>
                    </div>
                </div>
            </div>
        </div>

        <div class="form-actions">
            <button type="submit" name="update_order_status" class="btn btn-primary">
                💾 Update Status
            </button>
            
            <?php if ($edit_order['status'] === 'pending'): ?>
                <button type="submit" name="confirm_order" class="btn btn-success">
                    ✅ Quick Confirm & Process
                </button>
            <?php endif; ?>
            
            <a href="orders.php" class="btn btn-secondary">Cancel</a>
            
            <?php if (in_array($edit_order['status'], ['pending', 'confirmed'])): ?>
                <button type="button" 
                        onclick="confirmDelete(<?php echo $edit_order['id']; ?>, '<?php echo htmlspecialchars($edit_order['order_number']); ?>')" 
                        class="btn btn-danger">
                    🗑️ Delete Order
                </button>
            <?php endif; ?>
        </div>
    </form>
</div>

<style>
.order-details-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 2rem;
    margin-top: 1rem;
}

.detail-group {
    background: white;
    padding: 1.5rem;
    border-radius: 8px;
    border: 1px solid var(--border-color);
}

.detail-group h4 {
    margin: 0 0 1rem 0;
    color: var(--primary);
    font-size: 1.1rem;
}

.address-block {
    margin-bottom: 1rem;
    padding: 1rem;
    background: #f8f9fa;
    border-radius: 6px;
    border-left: 4px solid var(--primary);
}

.address-block:last-child {
    margin-bottom: 0;
}

.address-block strong {
    display: block;
    margin-bottom: 0.5rem;
    color: var(--text-dark);
}

.address-block p {
    margin: 0;
    line-height: 1.4;
}

@media (max-width: 768px) {
    .order-details-grid {
        grid-template-columns: 1fr;
        gap: 1rem;
    }
    
    .detail-group {
        padding: 1rem;
    }
}
</style>
