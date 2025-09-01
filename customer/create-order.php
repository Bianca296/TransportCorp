<?php
/**
 * Create Shipping Order Page
 * Customer order creation interface
 */

// Include configuration and middleware
require_once '../config/config.php';
require_once '../includes/middleware.php';

// Get current user (allow admin/employee for support)
$user = getCurrentUserOrRedirect();

// Initialize services
$auth = getAuth();
$database = new Database();
$pdo = $database->getConnection();
$order_model = new Order($pdo);

$success_message = '';
$error_message = '';
$order_created = false;
$order_id = null;
$calculated_cost = 0;

// Get transport type from URL if specified
$selected_transport = $_GET['type'] ?? '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || !$auth->verifyCSRFToken($_POST['csrf_token'])) {
        $error_message = 'Invalid request. Please try again.';
    } else {
        // Collect and validate form data
        $order_data = [
            'pickup_address' => trim($_POST['pickup_address'] ?? ''),
            'delivery_address' => trim($_POST['delivery_address'] ?? ''),
            'package_weight' => floatval($_POST['package_weight'] ?? 0),
            'package_length' => floatval($_POST['package_length'] ?? 0),
            'package_width' => floatval($_POST['package_width'] ?? 0),
            'package_height' => floatval($_POST['package_height'] ?? 0),
            'package_description' => trim($_POST['package_description'] ?? ''),
            'transport_type' => $_POST['transport_type'] ?? '',
            'special_instructions' => trim($_POST['special_instructions'] ?? ''),
            'urgent_delivery' => isset($_POST['urgent_delivery'])
        ];
        
        // Validation
        $validation_errors = [];
        
        if (empty($order_data['pickup_address'])) {
            $validation_errors[] = 'Pickup address is required';
        }
        if (empty($order_data['delivery_address'])) {
            $validation_errors[] = 'Delivery address is required';
        }
        if ($order_data['package_weight'] <= 0) {
            $validation_errors[] = 'Package weight must be greater than 0';
        }
        if ($order_data['package_weight'] > 1000) {
            $validation_errors[] = 'Package weight cannot exceed 1000kg (contact us for larger shipments)';
        }
        if (empty($order_data['package_description'])) {
            $validation_errors[] = 'Package description is required';
        }
        if (!in_array($order_data['transport_type'], ['land', 'air', 'ocean'])) {
            $validation_errors[] = 'Please select a valid transport type';
        }
        
        if (!empty($validation_errors)) {
            $error_message = implode('<br>', $validation_errors);
        } else {
            // Add user_id to order data
            $order_data['user_id'] = $user['id'];
            
            // Create order using Order model
            $result = $order_model->create($order_data);
            
            if ($result['success']) {
                $order_id = $result['order_number'];
                $calculated_cost = $result['total_cost'];
                $success_message = $result['message'] . " Order ID: {$order_id}";
                $order_created = true;
            } else {
                $error_message = $result['message'];
            }
        }
    }
}

// Cost calculation is now handled by the Order model

// Set page title
$page_title = 'Create Shipping Order';

// Include header
include '../includes/header.php';
?>

<div class="container">
    <div class="order-container">
        <!-- Page Header -->
        <div class="order-header">
            <h1>📦 Create Shipping Order</h1>
            <p>Fill out the details below to create your shipping order</p>
            
            <?php if ($user['role'] !== 'customer'): ?>
                <div class="staff-notice">
                    <p><strong>Staff View:</strong> Creating order for customer interface.</p>
                </div>
            <?php endif; ?>
        </div>

        <?php if (!empty($error_message)): ?>
            <div class="alert alert-error">
                <?php echo $error_message; ?>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($success_message)): ?>
            <div class="alert alert-success">
                <?php echo $success_message; ?>
                
                <?php if ($order_created): ?>
                    <div class="order-summary">
                        <h4>Order Summary:</h4>
                        <p><strong>Order ID:</strong> <?php echo htmlspecialchars($order_id); ?></p>
                        <p><strong>Total Cost:</strong> $<?php echo number_format($calculated_cost, 2); ?></p>
                        <p><strong>Transport Type:</strong> <?php echo ucfirst($_POST['transport_type'] ?? ''); ?></p>
                        <div class="order-actions">
                            <a href="dashboard.php" class="btn btn-primary">Back to Dashboard</a>
                            <a href="create-order.php" class="btn btn-secondary">Create Another Order</a>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <?php if (!$order_created): ?>
        <form method="POST" action="" class="order-form" id="orderForm">
            <input type="hidden" name="csrf_token" value="<?php echo $auth->generateCSRFToken(); ?>">
            
            <!-- Addresses Section -->
            <div class="form-section">
                <h3>📍 Pickup & Delivery Addresses</h3>
                
                <div class="form-group">
                    <label for="pickup_address">Pickup Address *</label>
                    <textarea 
                        id="pickup_address" 
                        name="pickup_address" 
                        class="form-control" 
                        rows="3"
                        required
                        placeholder="Enter pickup address"
                    ><?php echo htmlspecialchars($user['address'] ?? ''); ?></textarea>
                    <small class="form-text">Default: Your profile address. You can modify if needed.</small>
                </div>
                
                <div class="form-group">
                    <label for="delivery_address">Delivery Address *</label>
                    <textarea 
                        id="delivery_address" 
                        name="delivery_address" 
                        class="form-control" 
                        rows="3"
                        required
                        placeholder="Enter delivery address"
                    ><?php echo isset($_POST['delivery_address']) ? htmlspecialchars($_POST['delivery_address']) : ''; ?></textarea>
                </div>
            </div>

            <!-- Package Details Section -->
            <div class="form-section">
                <h3>📋 Package Details</h3>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="package_weight">Weight (kg) *</label>
                        <input 
                            type="number" 
                            id="package_weight" 
                            name="package_weight" 
                            class="form-control" 
                            step="0.1"
                            min="0.1"
                            max="1000"
                            required
                            value="<?php echo isset($_POST['package_weight']) ? htmlspecialchars($_POST['package_weight']) : ''; ?>"
                            placeholder="0.0"
                        >
                    </div>
                    
                    <div class="form-group">
                        <label for="package_description">Package Description *</label>
                        <input 
                            type="text" 
                            id="package_description" 
                            name="package_description" 
                            class="form-control" 
                            required
                            value="<?php echo isset($_POST['package_description']) ? htmlspecialchars($_POST['package_description']) : ''; ?>"
                            placeholder="What are you shipping?"
                        >
                    </div>
                </div>
                
                <div class="dimensions-group">
                    <label>Package Dimensions (cm)</label>
                    <div class="form-row">
                        <div class="form-group">
                            <input 
                                type="number" 
                                id="package_length" 
                                name="package_length" 
                                class="form-control" 
                                step="0.1"
                                min="1"
                                value="<?php echo isset($_POST['package_length']) ? htmlspecialchars($_POST['package_length']) : ''; ?>"
                                placeholder="Length"
                            >
                        </div>
                        <div class="form-group">
                            <input 
                                type="number" 
                                id="package_width" 
                                name="package_width" 
                                class="form-control" 
                                step="0.1"
                                min="1"
                                value="<?php echo isset($_POST['package_width']) ? htmlspecialchars($_POST['package_width']) : ''; ?>"
                                placeholder="Width"
                            >
                        </div>
                        <div class="form-group">
                            <input 
                                type="number" 
                                id="package_height" 
                                name="package_height" 
                                class="form-control" 
                                step="0.1"
                                min="1"
                                value="<?php echo isset($_POST['package_height']) ? htmlspecialchars($_POST['package_height']) : ''; ?>"
                                placeholder="Height"
                            >
                        </div>
                    </div>
                    <small class="form-text">Dimensions help us provide accurate quotes (optional for now)</small>
                </div>
            </div>

            <!-- Transport Options Section -->
            <div class="form-section">
                <h3>🚚 Transport Options</h3>
                
                <div class="transport-options">
                    <div class="transport-option">
                        <input 
                            type="radio" 
                            id="transport_land" 
                            name="transport_type" 
                            value="land"
                            <?php echo ($selected_transport === 'land' || (isset($_POST['transport_type']) && $_POST['transport_type'] === 'land')) ? 'checked' : ''; ?>
                            required
                        >
                        <label for="transport_land" class="transport-label">
                            <div class="transport-icon">🚛</div>
                            <div class="transport-info">
                                <h4>Land Transport</h4>
                                <p>Ground shipping via truck or train</p>
                                <div class="transport-details">
                                    <span class="cost-range">$5-20/kg</span>
                                    <span class="delivery-time">3-7 days</span>
                                    <span class="min-charge">Min: $25</span>
                                </div>
                            </div>
                        </label>
                    </div>
                    
                    <div class="transport-option">
                        <input 
                            type="radio" 
                            id="transport_air" 
                            name="transport_type" 
                            value="air"
                            <?php echo ($selected_transport === 'air' || (isset($_POST['transport_type']) && $_POST['transport_type'] === 'air')) ? 'checked' : ''; ?>
                            required
                        >
                        <label for="transport_air" class="transport-label">
                            <div class="transport-icon">✈️</div>
                            <div class="transport-info">
                                <h4>Air Transport</h4>
                                <p>Fast air freight shipping</p>
                                <div class="transport-details">
                                    <span class="cost-range">$15-50/kg</span>
                                    <span class="delivery-time">1-3 days</span>
                                    <span class="min-charge">Min: $75</span>
                                </div>
                            </div>
                        </label>
                    </div>
                    
                    <div class="transport-option">
                        <input 
                            type="radio" 
                            id="transport_ocean" 
                            name="transport_type" 
                            value="ocean"
                            <?php echo ($selected_transport === 'ocean' || (isset($_POST['transport_type']) && $_POST['transport_type'] === 'ocean')) ? 'checked' : ''; ?>
                            required
                        >
                        <label for="transport_ocean" class="transport-label">
                            <div class="transport-icon">🚢</div>
                            <div class="transport-info">
                                <h4>Ocean Transport</h4>
                                <p>Cost-effective sea freight</p>
                                <div class="transport-details">
                                    <span class="cost-range">$2-10/kg</span>
                                    <span class="delivery-time">14-30 days</span>
                                    <span class="min-charge">Min: $15</span>
                                </div>
                            </div>
                        </label>
                    </div>
                </div>
            </div>

            <!-- Additional Options -->
            <div class="form-section">
                <h3>⚙️ Additional Options</h3>
                
                <div class="form-group">
                    <div class="form-check">
                        <input 
                            type="checkbox" 
                            id="urgent_delivery" 
                            name="urgent_delivery" 
                            class="form-check-input"
                            <?php echo isset($_POST['urgent_delivery']) ? 'checked' : ''; ?>
                        >
                        <label for="urgent_delivery" class="form-check-label">
                            <strong>Urgent Delivery</strong> (+50% cost for priority handling)
                        </label>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="special_instructions">Special Instructions</label>
                    <textarea 
                        id="special_instructions" 
                        name="special_instructions" 
                        class="form-control" 
                        rows="3"
                        placeholder="Any special handling instructions or notes"
                    ><?php echo isset($_POST['special_instructions']) ? htmlspecialchars($_POST['special_instructions']) : ''; ?></textarea>
                </div>
            </div>

            <!-- Cost Preview -->
            <div class="cost-preview" id="costPreview" style="display: none;">
                <h4>💰 Shipping Cost Estimate</h4>
                <div id="costBreakdown"></div>
                <div class="cost-actions">
                    <p class="cost-note">Review the cost above. If you're satisfied, click "Confirm & Create Order" to proceed.</p>
                    <button type="button" id="modifyOrder" class="btn btn-secondary">Modify Order</button>
                </div>
            </div>

            <!-- Submit Button -->
            <div class="form-actions">
                <button type="button" id="calculateCost" class="btn btn-primary">Calculate Cost</button>
                <button type="submit" class="btn btn-success" id="createOrderBtn" style="display: none;">Confirm & Create Order</button>
                <a href="dashboard.php" class="btn btn-outline">Cancel</a>
            </div>
        </form>
        <?php endif; ?>
    </div>
</div>

<script>
// Cost calculation and order flow management
document.addEventListener('DOMContentLoaded', function() {
    const calculateBtn = document.getElementById('calculateCost');
    const createOrderBtn = document.getElementById('createOrderBtn');
    const modifyBtn = document.getElementById('modifyOrder');
    const costPreview = document.getElementById('costPreview');
    const orderForm = document.getElementById('orderForm');
    
    // Calculate cost button
    calculateBtn.addEventListener('click', function() {
        if (validateOrderForm()) {
            calculateCost();
        }
    });
    
    // Modify order button (hide cost preview and show calculate button again)
    modifyBtn.addEventListener('click', function() {
        costPreview.style.display = 'none';
        createOrderBtn.style.display = 'none';
        calculateBtn.style.display = 'inline-block';
        
        // Scroll back to top of form
        orderForm.scrollIntoView({ behavior: 'smooth' });
    });
    
    // Form validation before cost calculation
    function validateOrderForm() {
        const weight = parseFloat(document.getElementById('package_weight').value) || 0;
        const transportType = document.querySelector('input[name="transport_type"]:checked')?.value;
        const pickupAddress = document.getElementById('pickup_address').value.trim();
        const deliveryAddress = document.getElementById('delivery_address').value.trim();
        const description = document.getElementById('package_description').value.trim();
        
        const errors = [];
        
        if (!pickupAddress) errors.push('Pickup address is required');
        if (!deliveryAddress) errors.push('Delivery address is required');
        if (weight <= 0) errors.push('Package weight must be greater than 0');
        if (weight > 1000) errors.push('Package weight cannot exceed 1000kg');
        if (!description) errors.push('Package description is required');
        if (!transportType) errors.push('Please select a transport type');
        
        if (errors.length > 0) {
            alert('Please fix the following errors:\n\n' + errors.join('\n'));
            return false;
        }
        
        return true;
    }
    
    // Cost calculation function
    function calculateCost() {
        const weight = parseFloat(document.getElementById('package_weight').value) || 0;
        const transportType = document.querySelector('input[name="transport_type"]:checked')?.value;
        const urgent = document.getElementById('urgent_delivery').checked;
        
        // Cost calculation (matches PHP logic)
        const rates = {
            land: 12,
            air: 30, 
            ocean: 6
        };
        
        const minimums = {
            land: 25,
            air: 75,
            ocean: 15
        };
        
        const transportNames = {
            land: 'Land Transport (Truck/Train)',
            air: 'Air Transport (Flight)',
            ocean: 'Ocean Transport (Ship)'
        };
        
        const deliveryTimes = {
            land: '3-7 business days',
            air: '1-3 business days',
            ocean: '14-30 business days'
        };
        
        // Add variation (±20%) - simplified for demo
        const variation = (Math.random() - 0.5) * 0.4; // -20% to +20%
        const adjustedRate = rates[transportType] * (1 + variation);
        
        let calculatedCost = weight * adjustedRate;
        let baseCost = Math.max(calculatedCost, minimums[transportType]);
        
        const urgentSurcharge = urgent ? baseCost * 0.5 : 0;
        const total = baseCost + urgentSurcharge;
        
        // Determine if minimum charge applies
        const isMinimumCharge = calculatedCost < minimums[transportType];
        
        // Display cost breakdown
        let costDetailsHtml = '';
        
        if (isMinimumCharge) {
            costDetailsHtml = `
                <div class="cost-line">
                    <span>Calculated cost (${weight}kg × $${adjustedRate.toFixed(2)}/kg):</span>
                    <span>$${calculatedCost.toFixed(2)}</span>
                </div>
                <div class="cost-line minimum-charge-line">
                    <span>Minimum charge for ${transportType} transport:</span>
                    <span>$${minimums[transportType].toFixed(2)}</span>
                </div>
                <div class="cost-line applied-cost-line">
                    <span><strong>Applied cost (higher of the two):</strong></span>
                    <span><strong>$${baseCost.toFixed(2)}</strong></span>
                </div>
            `;
        } else {
            costDetailsHtml = `
                <div class="cost-line">
                    <span>Shipping cost (${weight}kg × $${adjustedRate.toFixed(2)}/kg):</span>
                    <span>$${baseCost.toFixed(2)}</span>
                </div>
            `;
        }
        
        // Display cost breakdown
        document.getElementById('costBreakdown').innerHTML = `
            <div class="cost-summary">
                <div class="cost-header">
                    <h5>${transportNames[transportType]}</h5>
                    <p>Estimated delivery: ${deliveryTimes[transportType]}</p>
                </div>
                
                <div class="cost-details">
                    ${costDetailsHtml}
                    ${urgent ? `
                        <div class="cost-line urgent-line">
                            <span>Urgent delivery surcharge (50%):</span>
                            <span>$${urgentSurcharge.toFixed(2)}</span>
                        </div>
                    ` : ''}
                    <div class="cost-total">
                        <span>Total Cost:</span>
                        <span>$${total.toFixed(2)}</span>
                    </div>
                </div>
                
                <div class="cost-info">
                    <p><strong>💡 What's included:</strong></p>
                    <ul>
                        <li>✅ Door-to-door pickup and delivery</li>
                        <li>✅ Package insurance up to $100</li>
                        <li>✅ Real-time tracking</li>
                        <li>✅ Customer support</li>
                        ${urgent ? '<li>✅ Priority processing and handling</li>' : ''}
                    </ul>
                </div>
            </div>
        `;
        
        // Show cost preview and create order button
        costPreview.style.display = 'block';
        calculateBtn.style.display = 'none';
        createOrderBtn.style.display = 'inline-block';
        
        // Scroll to cost preview
        costPreview.scrollIntoView({ behavior: 'smooth' });
    }
    
    // Prevent form submission if cost hasn't been calculated
    orderForm.addEventListener('submit', function(e) {
        if (createOrderBtn.style.display === 'none') {
            e.preventDefault();
            alert('Please calculate the cost first by clicking "Calculate Cost"');
            return false;
        }
        
        // Show confirmation dialog
        const total = document.querySelector('.cost-total span:last-child')?.textContent || 'Unknown';
        if (!confirm(`Confirm order creation?\n\nTotal cost: ${total}\n\nClick OK to proceed or Cancel to review.`)) {
            e.preventDefault();
            return false;
        }
    });
});
</script>

<?php
// Include footer
include '../includes/footer.php';
?>
