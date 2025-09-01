<?php
/**
 * Orders List View for Employee
 * Display orders with filters and pagination
 */
?>

<!-- Order Statistics -->
<div class="dashboard-stats">
    <div class="stat-card">
        <span class="stat-number"><?php echo $all_orders['total']; ?></span>
        <span class="stat-label">Total Orders</span>
    </div>
    <div class="stat-card">
        <span class="stat-number"><?php echo count(array_filter($all_orders['data'], fn($o) => $o['status'] === 'pending')); ?></span>
        <span class="stat-label">Pending</span>
    </div>
    <div class="stat-card">
        <span class="stat-number"><?php echo count(array_filter($all_orders['data'], fn($o) => $o['status'] === 'confirmed')); ?></span>
        <span class="stat-label">In Progress</span>
    </div>
    <div class="stat-card">
        <span class="stat-number"><?php echo count(array_filter($all_orders['data'], fn($o) => $o['status'] === 'delivered')); ?></span>
        <span class="stat-label">Delivered</span>
    </div>
</div>

<!-- Search and Filters -->
<div class="users-filters">
    <form method="GET" class="filters-form">
        <div class="filters-row">
            <div class="filter-group">
                <label for="search">🔍 Search Orders</label>
                <input type="text" 
                       id="search" 
                       name="search" 
                       value="<?php echo htmlspecialchars($filters['search']); ?>" 
                       placeholder="Order number, customer name, tracking...">
            </div>
            
            <div class="filter-group">
                <label for="status">📊 Status</label>
                <select id="status" name="status">
                    <option value="">All Statuses</option>
                    <option value="pending" <?php echo $filters['status'] === 'pending' ? 'selected' : ''; ?>>Pending</option>
                    <option value="confirmed" <?php echo $filters['status'] === 'confirmed' ? 'selected' : ''; ?>>Confirmed</option>
                    <option value="processing" <?php echo $filters['status'] === 'processing' ? 'selected' : ''; ?>>Processing</option>
                    <option value="shipped" <?php echo $filters['status'] === 'shipped' ? 'selected' : ''; ?>>Shipped</option>
                    <option value="delivered" <?php echo $filters['status'] === 'delivered' ? 'selected' : ''; ?>>Delivered</option>
                    <option value="cancelled" <?php echo $filters['status'] === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                </select>
            </div>
            
            <div class="filter-group">
                <label for="transport_type">🚚 Transport</label>
                <select id="transport_type" name="transport_type">
                    <option value="">All Types</option>
                    <option value="land" <?php echo $filters['transport_type'] === 'land' ? 'selected' : ''; ?>>Land</option>
                    <option value="air" <?php echo $filters['transport_type'] === 'air' ? 'selected' : ''; ?>>Air</option>
                    <option value="ocean" <?php echo $filters['transport_type'] === 'ocean' ? 'selected' : ''; ?>>Ocean</option>
                </select>
            </div>
            
            <div class="filter-actions">
                <button type="submit" class="btn btn-primary">Apply Filters</button>
                <a href="orders.php" class="btn btn-secondary">Reset</a>
            </div>
        </div>
    </form>
</div>

<!-- Orders Table -->
<div class="dashboard-section">
    <h3>📦 All Orders (<?php echo $all_orders['total']; ?>)</h3>
    <?php if (!empty($all_orders['data'])): ?>
        <div class="table-responsive">
            <table class="dashboard-table">
                <thead>
                    <tr>
                        <th>Order Details</th>
                        <th>Customer</th>
                        <th>Transport</th>
                        <th>Status</th>
                        <th>Total</th>
                        <th>Created</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($all_orders['data'] as $order): ?>
                        <tr>
                            <td>
                                <strong><?php echo htmlspecialchars($order['order_number']); ?></strong><br>
                                <?php if ($order['tracking_number']): ?>
                                    <small>Track: <?php echo htmlspecialchars($order['tracking_number']); ?></small>
                                <?php else: ?>
                                    <small class="text-muted">No tracking yet</small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <strong><?php echo htmlspecialchars($order['customer_name']); ?></strong><br>
                                <small><?php echo htmlspecialchars($order['customer_email']); ?></small>
                            </td>
                            <td>
                                <span class="transport-badge transport-<?php echo $order['transport_type']; ?>">
                                    <?php echo Order::getTransportIcon($order['transport_type']); ?>
                                    <?php echo Order::getTransportLabel($order['transport_type']); ?>
                                </span>
                            </td>
                            <td>
                                <span class="status-badge status-<?php echo $order['status']; ?>">
                                    <?php echo Order::getStatusLabel($order['status']); ?>
                                </span>
                            </td>
                            <td>
                                <strong>$<?php echo number_format($order['total_cost'], 2); ?></strong>
                            </td>
                            <td>
                                <?php echo date('M j, Y', strtotime($order['created_at'])); ?><br>
                                <small><?php echo date('g:i A', strtotime($order['created_at'])); ?></small>
                            </td>
                            <td class="table-actions">
                                <a href="view-order.php?id=<?php echo $order['id']; ?>" class="btn-small btn-primary">View</a>
                                
                                <?php if (in_array($order['status'], ['pending', 'confirmed'])): ?>
                                    <a href="orders.php?action=edit&id=<?php echo $order['id']; ?>" class="btn-small btn-success">Process</a>
                                <?php endif; ?>
                                
                                <?php if ($order['status'] === 'pending'): ?>
                                    <button onclick="confirmConfirm(<?php echo $order['id']; ?>, '<?php echo htmlspecialchars($order['order_number']); ?>')" 
                                            class="btn-small btn-success">Quick Confirm</button>
                                <?php endif; ?>
                                
                                <?php if (in_array($order['status'], ['pending', 'confirmed'])): ?>
                                    <button onclick="confirmDelete(<?php echo $order['id']; ?>, '<?php echo htmlspecialchars($order['order_number']); ?>')" 
                                            class="btn-small btn-danger">Delete</button>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <?php if ($all_orders['pages'] > 1): ?>
            <div class="pagination">
                <?php 
                $query_params = $_GET;
                for ($i = 1; $i <= $all_orders['pages']; $i++): 
                    $query_params['page'] = $i;
                    $url = 'orders.php?' . http_build_query($query_params);
                ?>
                    <a href="<?php echo $url; ?>" 
                       class="pagination-link <?php echo $i === $page ? 'active' : ''; ?>">
                        <?php echo $i; ?>
                    </a>
                <?php endfor; ?>
            </div>
        <?php endif; ?>
    <?php else: ?>
        <div class="empty-state">
            <div class="empty-icon">📦</div>
            <h3>No orders found</h3>
            <p>No orders match your current search criteria.</p>
            <a href="orders.php?action=create" class="btn btn-primary">Create First Order</a>
        </div>
    <?php endif; ?>
</div>
