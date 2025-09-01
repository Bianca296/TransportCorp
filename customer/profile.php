<?php
/**
 * Customer Profile Page
 * Account management and personal information
 */

// Include configuration and middleware
require_once '../config/config.php';
require_once '../includes/middleware.php';

// Get current user (allow admin/employee to view for support)
$user = getCurrentUserOrRedirect();

// Initialize Auth service for form processing
$auth = getAuth();
$database = new Database();
$pdo = $database->getConnection();
$user_model = new User($pdo);

$success_message = '';
$error_message = '';
$profile_updated = false;
$password_updated = false;

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || !$auth->verifyCSRFToken($_POST['csrf_token'])) {
        $error_message = 'Invalid request. Please try again.';
    } else {
        $form_type = $_POST['form_type'] ?? '';
        
        if ($form_type === 'update_profile') {
            // Handle profile update (email is not editable)
            $profile_data = [
                'first_name' => trim($_POST['first_name'] ?? ''),
                'last_name' => trim($_POST['last_name'] ?? ''),
                'phone' => trim($_POST['phone'] ?? ''),
                'address' => trim($_POST['address'] ?? '')
            ];
            
            // Validate required fields
            if (empty($profile_data['first_name']) || empty($profile_data['last_name'])) {
                $error_message = 'First name and last name are required.';
            } else {
                // Update profile
                if ($user_model->update($user['id'], $profile_data)) {
                    $success_message = 'Profile updated successfully!';
                    $profile_updated = true;
                    
                    // Refresh user data
                    $user = $user_model->findById($user['id']);
                    
                    // Update session data
                    $_SESSION['full_name'] = $user['first_name'] . ' ' . $user['last_name'];
                } else {
                    $error_message = 'Failed to update profile. Please try again.';
                }
            }
            
        } elseif ($form_type === 'change_password') {
            // Handle password change - get fresh user data to ensure we have current password hash
            $fresh_user = $user_model->findById($user['id']);
            
            if (!$fresh_user) {
                $error_message = 'User account not found. Please log in again.';
            } else {
                $current_password = $_POST['current_password'] ?? '';
                $new_password = $_POST['new_password'] ?? '';
                $confirm_password = $_POST['confirm_password'] ?? '';
                
                if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
                    $error_message = 'All password fields are required.';
                } elseif ($new_password !== $confirm_password) {
                    $error_message = 'New passwords do not match.';
                } else {
                    // Verify current password against fresh user data
                    if (!password_verify($current_password, $fresh_user['password_hash'])) {
                        $error_message = 'Current password is incorrect.';
                    } else {
                        // Validate new password strength
                        $password_validation = $auth->validatePassword($new_password);
                        
                        if (!$password_validation['valid']) {
                            $error_message = implode('<br>', $password_validation['errors']);
                        } else {
                            // Update password
                            if ($user_model->changePassword($user['id'], $new_password)) {
                                $success_message = 'Password changed successfully! Please remember your new password for future logins.';
                                $password_updated = true;
                                
                                // Refresh user data after password change
                                $user = $user_model->findById($user['id']);
                            } else {
                                $error_message = 'Failed to change password. Please try again.';
                            }
                        }
                    }
                }
            }
        }
    }
}

// Set page title
$page_title = 'My Profile';

// Include header
include '../includes/header.php';
?>

<div class="container">
    <div class="dashboard-container">
        <!-- Page Header -->
        <div class="profile-header">
            <h1>👤 My Profile</h1>
            <p>Manage your account information and settings</p>
            
            <?php if ($user['role'] !== 'customer'): ?>
                <div class="staff-notice">
                    <p><strong>Staff View:</strong> You are viewing the customer profile interface.</p>
                    <a href="../<?php echo $user['role']; ?>/dashboard.php" class="btn btn-small">Back to <?php echo ucfirst($user['role']); ?> Dashboard</a>
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
            </div>
        <?php endif; ?>

        <div class="profile-content">
            <div class="profile-sections">
                <!-- Personal Information Section -->
                <div class="profile-section">
                    <h3>📝 Personal Information</h3>
                    
                    <form method="POST" action="" class="profile-form">
                        <input type="hidden" name="csrf_token" value="<?php echo $auth->generateCSRFToken(); ?>">
                        <input type="hidden" name="form_type" value="update_profile">
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="first_name">First Name *</label>
                                <input 
                                    type="text" 
                                    id="first_name" 
                                    name="first_name" 
                                    class="form-control" 
                                    required 
                                    value="<?php echo htmlspecialchars($user['first_name']); ?>"
                                >
                            </div>
                            
                            <div class="form-group">
                                <label for="last_name">Last Name *</label>
                                <input 
                                    type="text" 
                                    id="last_name" 
                                    name="last_name" 
                                    class="form-control" 
                                    required 
                                    value="<?php echo htmlspecialchars($user['last_name']); ?>"
                                >
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="email">Email Address</label>
                            <input 
                                type="email" 
                                id="email" 
                                name="email" 
                                class="form-control" 
                                readonly
                                value="<?php echo htmlspecialchars($user['email']); ?>"
                                style="background-color: #f8f9fa; cursor: not-allowed;"
                            >
                            <small class="form-text">
                                Email address cannot be changed. Contact support if you need to update your email.
                            </small>
                        </div>
                        
                        <div class="form-group">
                            <label for="phone">Phone Number</label>
                            <input 
                                type="tel" 
                                id="phone" 
                                name="phone" 
                                class="form-control" 
                                value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>"
                                placeholder="Enter your phone number"
                            >
                        </div>
                        
                        <div class="form-group">
                            <label for="address">Address</label>
                            <textarea 
                                id="address" 
                                name="address" 
                                class="form-control" 
                                rows="3"
                                placeholder="Enter your address"
                            ><?php echo htmlspecialchars($user['address'] ?? ''); ?></textarea>
                        </div>
                        
                        <button type="submit" class="btn btn-primary">
                            Update Profile
                        </button>
                    </form>
                </div>

                <!-- Password Change Section -->
                <div class="profile-section">
                    <h3>🔒 Change Password</h3>
                    
                    <form method="POST" action="" class="profile-form">
                        <input type="hidden" name="csrf_token" value="<?php echo $auth->generateCSRFToken(); ?>">
                        <input type="hidden" name="form_type" value="change_password">
                        
                        <div class="form-group">
                            <label for="current_password">Current Password *</label>
                            <input 
                                type="password" 
                                id="current_password" 
                                name="current_password" 
                                class="form-control" 
                                required
                                placeholder="Enter your current password"
                            >
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="new_password">New Password *</label>
                                <input 
                                    type="password" 
                                    id="new_password" 
                                    name="new_password" 
                                    class="form-control" 
                                    required
                                    placeholder="Enter new password"
                                >
                                <small class="form-text">
                                    Must be 8+ characters with uppercase, lowercase, and number.
                                </small>
                            </div>
                            
                            <div class="form-group">
                                <label for="confirm_password">Confirm New Password *</label>
                                <input 
                                    type="password" 
                                    id="confirm_password" 
                                    name="confirm_password" 
                                    class="form-control" 
                                    required
                                    placeholder="Confirm new password"
                                >
                            </div>
                        </div>
                        
                        <button type="submit" class="btn btn-primary">
                            Change Password
                        </button>
                    </form>
                </div>

                <!-- Account Information Section -->
                <div class="profile-section">
                    <h3>ℹ️ Account Information</h3>
                    
                    <div class="account-details">
                        <div class="info-grid">
                            <div class="info-item">
                                <strong>Username:</strong>
                                <span><?php echo htmlspecialchars($user['username']); ?></span>
                            </div>
                            <div class="info-item">
                                <strong>Account Type:</strong>
                                <span class="role-badge role-<?php echo $user['role']; ?>">
                                    <?php echo ucfirst($user['role']); ?>
                                </span>
                            </div>
                            <div class="info-item">
                                <strong>Account Status:</strong>
                                <span class="status-badge status-<?php echo $user['status']; ?>">
                                    <?php echo ucfirst($user['status']); ?>
                                </span>
                            </div>
                            <div class="info-item">
                                <strong>Member Since:</strong>
                                <span><?php echo date('F j, Y', strtotime($user['created_at'])); ?></span>
                            </div>
                            <div class="info-item">
                                <strong>Last Login:</strong>
                                <span><?php echo $user['last_login'] ? date('F j, Y g:i A', strtotime($user['last_login'])) : 'Never'; ?></span>
                            </div>
                            <div class="info-item">
                                <strong>Email Verified:</strong>
                                <span class="<?php echo $user['email_verified'] ? 'text-success' : 'text-warning'; ?>">
                                    <?php echo $user['email_verified'] ? '✅ Verified' : '⚠️ Not Verified'; ?>
                                </span>
                            </div>
                        </div>
                        
                        <?php if (!$user['email_verified']): ?>
                            <div class="verification-notice">
                                <p><strong>Email Verification Required:</strong></p>
                                <p>Please verify your email address to access all features.</p>
                                <a href="#" class="btn btn-outline">Send Verification Email</a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Quick Links Section -->
                <div class="profile-section">
                    <h3>🚀 Quick Links</h3>
                    
                    <div class="quick-links">
                        <a href="dashboard.php" class="quick-link">
                            <span class="link-icon">🏠</span>
                            <span class="link-text">Dashboard</span>
                        </a>
                        <a href="create-order.php" class="quick-link">
                            <span class="link-icon">📦</span>
                            <span class="link-text">Create Order</span>
                        </a>
                        <a href="track-shipment.php" class="quick-link">
                            <span class="link-icon">📍</span>
                            <span class="link-text">Track Shipment</span>
                        </a>
                        <a href="../auth/logout.php" class="quick-link">
                            <span class="link-icon">🚪</span>
                            <span class="link-text">Logout</span>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
// Include footer
include '../includes/footer.php';
?>
