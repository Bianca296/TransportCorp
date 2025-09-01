<?php
/**
 * Full Order Edit Form for Employee
 * Allows editing of all order details
 */
?>

<div class="full-edit-container">
    <div class="edit-header">
        <h3>✏️ Edit Order Details</h3>
        <p>Modify order information. Changes to package details or transport type will recalculate costs.</p>
    </div>

    <form method="POST" class="order-edit-form" id="orderEditForm">
        <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
        
        <div class="edit-sections">
            <!-- Shipping Addresses Section -->
            <div class="edit-section">
                <h4>📍 Shipping Addresses</h4>
                <div class="form-grid">
                    <div class="form-group">
                        <label for="pickup_address">Pickup Address</label>
                        <textarea id="pickup_address" 
                                  name="pickup_address" 
                                  rows="4" 
                                  placeholder="Enter full pickup address..."><?php echo htmlspecialchars($order['pickup_address']); ?></textarea>
                    </div>
                    <div class="form-group">
                        <label for="delivery_address">Delivery Address</label>
                        <textarea id="delivery_address" 
                                  name="delivery_address" 
                                  rows="4" 
                                  placeholder="Enter full delivery address..."><?php echo htmlspecialchars($order['delivery_address']); ?></textarea>
                    </div>
                </div>
            </div>

            <!-- Package Details Section -->
            <div class="edit-section">
                <h4>📦 Package Information</h4>
                <div class="form-grid">
                    <div class="form-group">
                        <label for="package_weight">Weight (kg)</label>
                        <input type="number" 
                               id="package_weight" 
                               name="package_weight" 
                               step="0.1" 
                               min="0.1" 
                               max="10000"
                               value="<?php echo $order['package_weight']; ?>"
                               onchange="markForCostRecalculation()">
                    </div>
                    <div class="form-group">
                        <label for="package_length">Length (cm)</label>
                        <input type="number" 
                               id="package_length" 
                               name="package_length" 
                               step="0.1" 
                               min="1" 
                               max="500"
                               value="<?php echo $order['package_length']; ?>"
                               onchange="markForCostRecalculation()">
                    </div>
                    <div class="form-group">
                        <label for="package_width">Width (cm)</label>
                        <input type="number" 
                               id="package_width" 
                               name="package_width" 
                               step="0.1" 
                               min="1" 
                               max="500"
                               value="<?php echo $order['package_width']; ?>"
                               onchange="markForCostRecalculation()">
                    </div>
                    <div class="form-group">
                        <label for="package_height">Height (cm)</label>
                        <input type="number" 
                               id="package_height" 
                               name="package_height" 
                               step="0.1" 
                               min="1" 
                               max="500"
                               value="<?php echo $order['package_height']; ?>"
                               onchange="markForCostRecalculation()">
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="package_description">Package Description</label>
                    <textarea id="package_description" 
                              name="package_description" 
                              rows="3" 
                              placeholder="Describe the package contents..."><?php echo htmlspecialchars($order['package_description']); ?></textarea>
                </div>
            </div>

            <!-- Shipping Options Section -->
            <div class="edit-section">
                <h4>🚚 Shipping Options</h4>
                <div class="form-grid">
                    <div class="form-group">
                        <label for="transport_type">Transport Type</label>
                        <select id="transport_type" name="transport_type" onchange="markForCostRecalculation()">
                            <option value="land" <?php echo $order['transport_type'] === 'land' ? 'selected' : ''; ?>>
                                🚚 Land Transport
                            </option>
                            <option value="air" <?php echo $order['transport_type'] === 'air' ? 'selected' : ''; ?>>
                                ✈️ Air Transport
                            </option>
                            <option value="ocean" <?php echo $order['transport_type'] === 'ocean' ? 'selected' : ''; ?>>
                                🚢 Ocean Transport
                            </option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label class="checkbox-label">
                            <input type="checkbox" 
                                   id="urgent_delivery" 
                                   name="urgent_delivery" 
                                   <?php echo $order['urgent_delivery'] ? 'checked' : ''; ?>
                                   onchange="markForCostRecalculation()">
                            <span class="checkmark"></span>
                            ⚡ Urgent Delivery (+50% cost)
                        </label>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="special_instructions">Special Instructions</label>
                    <textarea id="special_instructions" 
                              name="special_instructions" 
                              rows="3" 
                              placeholder="Any special handling requirements..."><?php echo htmlspecialchars($order['special_instructions']); ?></textarea>
                </div>
            </div>

            <!-- Cost Recalculation Warning -->
            <div id="costWarning" class="cost-warning" style="display: none;">
                <div class="warning-content">
                    <span class="warning-icon">⚠️</span>
                    <div class="warning-text">
                        <strong>Cost Recalculation Required</strong>
                        <p>You've modified package details or transport options. The order cost will be automatically recalculated when you save.</p>
                    </div>
                </div>
            </div>

            <!-- Current Cost Display -->
            <div class="current-cost-section">
                <h4>💰 Current Cost Breakdown</h4>
                <div class="cost-display">
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

        <!-- Form Actions -->
        <div class="edit-actions">
            <button type="submit" name="update_order_details" class="btn btn-primary">
                💾 Save Changes
            </button>
            
            <button type="button" onclick="resetForm()" class="btn btn-secondary">
                🔄 Reset Changes
            </button>
            
            <button type="button" onclick="hideEditForm()" class="btn btn-outline">
                ❌ Cancel Editing
            </button>
            
            <?php if (in_array($order['status'], ['pending', 'confirmed'])): ?>
                <button type="button" 
                        onclick="confirmDelete(<?php echo $order['id']; ?>, '<?php echo htmlspecialchars($order['order_number']); ?>')" 
                        class="btn btn-danger">
                    🗑️ Delete Order
                </button>
            <?php endif; ?>
        </div>
    </form>
</div>

<script>
let originalFormData = {};
let costRecalculationNeeded = false;

// Store original form data on page load
document.addEventListener('DOMContentLoaded', function() {
    storeOriginalFormData();
});

function storeOriginalFormData() {
    const form = document.getElementById('orderEditForm');
    const formData = new FormData(form);
    originalFormData = {};
    
    for (let [key, value] of formData.entries()) {
        originalFormData[key] = value;
    }
}

function markForCostRecalculation() {
    costRecalculationNeeded = true;
    document.getElementById('costWarning').style.display = 'block';
}

function resetForm() {
    if (confirm('Are you sure you want to reset all changes?')) {
        // Reset all form fields to original values
        for (let [key, value] of Object.entries(originalFormData)) {
            const element = document.querySelector(`[name="${key}"]`);
            if (element) {
                if (element.type === 'checkbox') {
                    element.checked = value === 'on';
                } else {
                    element.value = value;
                }
            }
        }
        
        // Hide cost warning
        document.getElementById('costWarning').style.display = 'none';
        costRecalculationNeeded = false;
    }
}

function hideEditForm() {
    if (confirm('Are you sure you want to cancel editing? Any unsaved changes will be lost.')) {
        document.getElementById('edit-section').style.display = 'none';
    }
}

// Form validation before submission
document.getElementById('orderEditForm').addEventListener('submit', function(e) {
    // Validate required fields
    const requiredFields = ['pickup_address', 'delivery_address', 'package_description'];
    let isValid = true;
    
    requiredFields.forEach(function(fieldName) {
        const field = document.querySelector(`[name="${fieldName}"]`);
        if (!field.value.trim()) {
            field.style.borderColor = '#e74c3c';
            isValid = false;
        } else {
            field.style.borderColor = '';
        }
    });
    
    // Validate numeric fields
    const numericFields = ['package_weight', 'package_length', 'package_width', 'package_height'];
    numericFields.forEach(function(fieldName) {
        const field = document.querySelector(`[name="${fieldName}"]`);
        if (!field.value || parseFloat(field.value) <= 0) {
            field.style.borderColor = '#e74c3c';
            isValid = false;
        } else {
            field.style.borderColor = '';
        }
    });
    
    if (!isValid) {
        e.preventDefault();
        alert('Please fill in all required fields with valid values.');
        return false;
    }
    
    if (costRecalculationNeeded) {
        if (!confirm('Package details or transport options have changed. The order cost will be recalculated. Do you want to continue?')) {
            e.preventDefault();
            return false;
        }
    }
});
</script>

<style>
.full-edit-container {
    background: white;
    border-radius: 12px;
    padding: 2rem;
    margin-top: 2rem;
    border: 2px solid var(--primary);
    box-shadow: 0 4px 12px rgba(52, 152, 219, 0.1);
}

.edit-header {
    margin-bottom: 2rem;
    padding-bottom: 1rem;
    border-bottom: 2px solid var(--border-color);
}

.edit-header h3 {
    margin: 0 0 0.5rem 0;
    color: var(--primary);
}

.edit-header p {
    margin: 0;
    color: var(--text-muted);
    font-size: 0.9rem;
}

.edit-sections {
    display: flex;
    flex-direction: column;
    gap: 2rem;
}

.edit-section {
    background: #f8f9fa;
    padding: 1.5rem;
    border-radius: 8px;
    border: 1px solid var(--border-color);
}

.edit-section h4 {
    margin: 0 0 1rem 0;
    color: var(--text-dark);
    font-size: 1.1rem;
}

.cost-warning {
    background: linear-gradient(135deg, #f39c12, #e67e22);
    color: white;
    padding: 1rem;
    border-radius: 8px;
    animation: slideIn 0.3s ease;
}

.warning-content {
    display: flex;
    align-items: flex-start;
    gap: 1rem;
}

.warning-icon {
    font-size: 1.5rem;
}

.warning-text strong {
    display: block;
    margin-bottom: 0.5rem;
}

.warning-text p {
    margin: 0;
    opacity: 0.9;
}

.current-cost-section {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 1.5rem;
    border-radius: 8px;
}

.current-cost-section h4 {
    margin: 0 0 1rem 0;
    color: white;
}

.cost-display {
    background: rgba(255, 255, 255, 0.1);
    padding: 1rem;
    border-radius: 6px;
}

.cost-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0.5rem 0;
    border-bottom: 1px solid rgba(255, 255, 255, 0.2);
}

.cost-item:last-child {
    border-bottom: none;
}

.cost-item.total {
    font-weight: bold;
    font-size: 1.1rem;
    border-top: 2px solid rgba(255, 255, 255, 0.3);
    margin-top: 0.5rem;
    padding-top: 1rem;
}

.edit-actions {
    display: flex;
    gap: 1rem;
    justify-content: flex-start;
    align-items: center;
    margin-top: 2rem;
    padding-top: 2rem;
    border-top: 2px solid var(--border-color);
}

@keyframes slideIn {
    from {
        opacity: 0;
        transform: translateY(-10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

@media (max-width: 768px) {
    .full-edit-container {
        padding: 1rem;
        margin-left: -1rem;
        margin-right: -1rem;
        border-radius: 0;
    }
    
    .edit-actions {
        flex-direction: column;
        align-items: stretch;
    }
    
    .edit-actions .btn {
        width: 100%;
        text-align: center;
    }
}
</style>
