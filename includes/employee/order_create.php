<?php
/**
 * Order Create View for Employee
 * Create new orders for customers
 */
?>

<!-- Create Order Form -->
<div class="user-form-container">
    <form method="POST" class="user-form">
        <div class="form-sections">
            <!-- Customer Selection -->
            <div class="form-section">
                <h3>👤 Customer Information</h3>
                <div class="form-group">
                    <label for="customer_id">Select Customer *</label>
                    <select id="customer_id" name="customer_id" required>
                        <option value="">Choose a customer...</option>
                        <?php foreach ($all_customers as $customer): ?>
                            <option value="<?php echo $customer['id']; ?>" 
                                    <?php echo (isset($_GET['customer_id']) && $_GET['customer_id'] == $customer['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($customer['first_name'] . ' ' . $customer['last_name'] . ' (' . $customer['email'] . ')'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <small class="form-help">Search by typing the customer's name or email</small>
                </div>
            </div>

            <!-- Address Information -->
            <div class="form-section">
                <h3>📍 Shipping Information</h3>
                <div class="form-grid">
                    <div class="form-group">
                        <label for="pickup_address">Pickup Address *</label>
                        <textarea id="pickup_address" 
                                  name="pickup_address" 
                                  rows="3" 
                                  required 
                                  placeholder="Full pickup address including street, city, state, zip code"></textarea>
                    </div>
                    <div class="form-group">
                        <label for="delivery_address">Delivery Address *</label>
                        <textarea id="delivery_address" 
                                  name="delivery_address" 
                                  rows="3" 
                                  required 
                                  placeholder="Full delivery address including street, city, state, zip code"></textarea>
                    </div>
                </div>
            </div>

            <!-- Package Information -->
            <div class="form-section">
                <h3>📦 Package Details</h3>
                <div class="form-grid">
                    <div class="form-group">
                        <label for="package_weight">Weight (kg) *</label>
                        <input type="number" 
                               id="package_weight" 
                               name="package_weight" 
                               step="0.1" 
                               min="0.1" 
                               max="10000"
                               required 
                               placeholder="0.0">
                        <small class="form-help">Weight in kilograms</small>
                    </div>
                    <div class="form-group">
                        <label for="package_length">Length (cm) *</label>
                        <input type="number" 
                               id="package_length" 
                               name="package_length" 
                               step="0.1" 
                               min="1" 
                               max="500"
                               required 
                               placeholder="0.0">
                    </div>
                    <div class="form-group">
                        <label for="package_width">Width (cm) *</label>
                        <input type="number" 
                               id="package_width" 
                               name="package_width" 
                               step="0.1" 
                               min="1" 
                               max="500"
                               required 
                               placeholder="0.0">
                    </div>
                    <div class="form-group">
                        <label for="package_height">Height (cm) *</label>
                        <input type="number" 
                               id="package_height" 
                               name="package_height" 
                               step="0.1" 
                               min="1" 
                               max="500"
                               required 
                               placeholder="0.0">
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="package_description">Package Description *</label>
                    <textarea id="package_description" 
                              name="package_description" 
                              rows="3" 
                              required 
                              placeholder="Describe the contents of the package..."></textarea>
                    <small class="form-help">Be specific about the contents for proper handling</small>
                </div>
            </div>

            <!-- Shipping Options -->
            <div class="form-section">
                <h3>🚚 Shipping Options</h3>
                <div class="form-grid">
                    <div class="form-group">
                        <label for="transport_type">Transport Type *</label>
                        <select id="transport_type" name="transport_type" required>
                            <option value="land">🚚 Land Transport (Ground shipping)</option>
                            <option value="air">✈️ Air Transport (Fast delivery)</option>
                            <option value="ocean">🚢 Ocean Transport (International shipping)</option>
                        </select>
                        <small class="form-help">Choose based on urgency and destination</small>
                    </div>
                    
                    <div class="form-group">
                        <label class="checkbox-label">
                            <input type="checkbox" id="urgent_delivery" name="urgent_delivery">
                            <span class="checkmark"></span>
                            ⚡ Urgent Delivery (+50% cost)
                        </label>
                        <small class="form-help">Priority handling and faster delivery</small>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="special_instructions">Special Instructions</label>
                    <textarea id="special_instructions" 
                              name="special_instructions" 
                              rows="3" 
                              placeholder="Any special handling requirements, delivery instructions, or notes..."></textarea>
                    <small class="form-help">Include any special handling requirements</small>
                </div>
            </div>
        </div>

        <!-- Cost Preview Section -->
        <div class="cost-preview-section" id="costPreview" style="display: none;">
            <div class="cost-preview-header">
                <h3>💰 Cost Estimate</h3>
                <div class="cost-details" id="costDetails">
                    <!-- Cost breakdown will be inserted here -->
                </div>
            </div>
        </div>

        <div class="form-actions">
            <button type="button" onclick="calculateCost()" class="btn btn-secondary" id="calculateBtn">
                🧮 Calculate Cost
            </button>
            
            <button type="submit" name="create_order" class="btn btn-primary" id="createBtn" disabled>
                ➕ Create Order
            </button>
            
            <a href="orders.php" class="btn btn-secondary">Cancel</a>
        </div>
    </form>
</div>

<script>
// Make customer select searchable
document.getElementById('customer_id').addEventListener('input', function() {
    // Simple search implementation
    const searchTerm = this.value.toLowerCase();
    const options = this.querySelectorAll('option');
    
    options.forEach(option => {
        if (option.value === '') return; // Skip placeholder
        
        const text = option.textContent.toLowerCase();
        option.style.display = text.includes(searchTerm) ? '' : 'none';
    });
});

// Cost calculation
async function calculateCost() {
    const formData = new FormData();
    
    // Get form values
    formData.append('package_weight', document.getElementById('package_weight').value);
    formData.append('package_length', document.getElementById('package_length').value);
    formData.append('package_width', document.getElementById('package_width').value);
    formData.append('package_height', document.getElementById('package_height').value);
    formData.append('transport_type', document.getElementById('transport_type').value);
    formData.append('urgent_delivery', document.getElementById('urgent_delivery').checked ? '1' : '0');
    
    // Validate required fields
    const requiredFields = ['package_weight', 'package_length', 'package_width', 'package_height'];
    let isValid = true;
    
    requiredFields.forEach(field => {
        const value = document.getElementById(field).value;
        if (!value || parseFloat(value) <= 0) {
            isValid = false;
            document.getElementById(field).style.borderColor = '#e74c3c';
        } else {
            document.getElementById(field).style.borderColor = '';
        }
    });
    
    if (!isValid) {
        alert('Please fill in all package dimensions with valid values greater than 0.');
        return;
    }
    
    // Show loading
    const calculateBtn = document.getElementById('calculateBtn');
    const originalText = calculateBtn.textContent;
    calculateBtn.textContent = '⏳ Calculating...';
    calculateBtn.disabled = true;
    
    try {
        const response = await fetch('../api/estimate-cost.php', {
            method: 'POST',
            body: formData
        });
        
        const result = await response.json();
        
        if (result.success) {
            showCostPreview(result.cost_breakdown);
            document.getElementById('createBtn').disabled = false;
        } else {
            alert('Error calculating cost: ' + result.message);
        }
    } catch (error) {
        alert('Error calculating cost. Please try again.');
        console.error('Cost calculation error:', error);
    } finally {
        calculateBtn.textContent = originalText;
        calculateBtn.disabled = false;
    }
}

function showCostPreview(costBreakdown) {
    const costPreview = document.getElementById('costPreview');
    const costDetails = document.getElementById('costDetails');
    
    let html = '<div class="cost-breakdown">';
    html += `<div class="cost-item">`;
    html += `<span class="cost-label">Base Cost:</span>`;
    html += `<span class="cost-value">$${costBreakdown.base_cost.toFixed(2)}</span>`;
    html += `</div>`;
    
    if (costBreakdown.urgent_surcharge > 0) {
        html += `<div class="cost-item">`;
        html += `<span class="cost-label">Urgent Surcharge (50%):</span>`;
        html += `<span class="cost-value">$${costBreakdown.urgent_surcharge.toFixed(2)}</span>`;
        html += `</div>`;
    }
    
    html += `<div class="cost-item cost-total">`;
    html += `<span class="cost-label">Total Cost:</span>`;
    html += `<span class="cost-value">$${costBreakdown.total_cost.toFixed(2)}</span>`;
    html += `</div>`;
    html += '</div>';
    
    costDetails.innerHTML = html;
    costPreview.style.display = 'block';
}

// Auto-calculate when transport type or urgent delivery changes
document.getElementById('transport_type').addEventListener('change', function() {
    if (document.getElementById('package_weight').value) {
        calculateCost();
    }
});

document.getElementById('urgent_delivery').addEventListener('change', function() {
    if (document.getElementById('package_weight').value) {
        calculateCost();
    }
});
</script>

<style>
.cost-preview-section {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 2rem;
    border-radius: 12px;
    margin: 2rem 0;
}

.cost-preview-header h3 {
    margin: 0 0 1rem 0;
    color: white;
}

.cost-breakdown {
    background: rgba(255, 255, 255, 0.1);
    padding: 1rem;
    border-radius: 8px;
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

.cost-total {
    font-weight: bold;
    font-size: 1.1rem;
    border-top: 2px solid rgba(255, 255, 255, 0.3);
    margin-top: 0.5rem;
    padding-top: 1rem;
}

.cost-label {
    font-weight: 500;
}

.cost-value {
    font-weight: bold;
}

/* Searchable select styling */
#customer_id {
    position: relative;
}

@media (max-width: 768px) {
    .cost-preview-section {
        margin: 1rem -1rem;
        border-radius: 0;
    }
}
</style>
