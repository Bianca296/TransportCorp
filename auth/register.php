<?php
/**
 * Registration Page
 * User account creation form and processing
 */

// Include configuration
require_once '../config/config.php';

// Initialize Auth service
$database = new Database();
$pdo = $database->getConnection();
$auth = new Auth($pdo);

// Redirect if already logged in
if ($auth->isLoggedIn()) {
    $user = $auth->getCurrentUser();
    redirect($auth->getRedirectUrl($user['role']));
}

$error_message = '';
$form_data = [];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || !$auth->verifyCSRFToken($_POST['csrf_token'])) {
        $error_message = 'Invalid request. Please try again.';
    } else {
        // Collect form data
        $form_data = [
            'username' => $_POST['username'] ?? '',
            'email' => $_POST['email'] ?? '',
            'first_name' => $_POST['first_name'] ?? '',
            'last_name' => $_POST['last_name'] ?? '',
            'phone' => $_POST['phone'] ?? '',
            'address' => $_POST['address'] ?? '',
            'password' => $_POST['password'] ?? '',
            'role' => 'customer' // Default role for public registration
        ];
        
        // Validate password confirmation
        $password_confirm = $_POST['password_confirm'] ?? '';
        
        if ($form_data['password'] !== $password_confirm) {
            $error_message = 'Passwords do not match.';
        } else {
            // Validate password strength
            $password_validation = $auth->validatePassword($form_data['password']);
            
            if (!$password_validation['valid']) {
                $error_message = implode('<br>', $password_validation['errors']);
            } else {
                // Attempt registration
                $result = $auth->register($form_data);
                
                if ($result['success']) {
                    $_SESSION['success_message'] = $result['message'] . ' You can now log in.';
                    redirect('login.php');
                } else {
                    $error_message = $result['message'];
                }
            }
        }
    }
}

// Set page title
$page_title = 'Register';

// Include header
include '../includes/header.php';
?>

<div class="container">
    <div class="auth-container">
        <div class="auth-form">
            <h2>Create Your Account</h2>
            <p class="auth-subtitle">Join TransportCorp for reliable shipping services</p>
            
            <?php if (!empty($error_message)): ?>
                <div class="alert alert-error">
                    <?php echo $error_message; ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="" class="register-form">
                <input type="hidden" name="csrf_token" value="<?php echo $auth->generateCSRFToken(); ?>">
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="first_name">First Name *</label>
                        <input 
                            type="text" 
                            id="first_name" 
                            name="first_name" 
                            class="form-control" 
                            required 
                            value="<?php echo htmlspecialchars($form_data['first_name'] ?? ''); ?>"
                            placeholder="Enter your first name"
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
                            value="<?php echo htmlspecialchars($form_data['last_name'] ?? ''); ?>"
                            placeholder="Enter your last name"
                        >
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="username">Username *</label>
                    <input 
                        type="text" 
                        id="username" 
                        name="username" 
                        class="form-control" 
                        required 
                        value="<?php echo htmlspecialchars($form_data['username'] ?? ''); ?>"
                        placeholder="Choose a unique username"
                    >
                </div>
                
                <div class="form-group">
                    <label for="email">Email Address *</label>
                    <input 
                        type="email" 
                        id="email" 
                        name="email" 
                        class="form-control" 
                        required 
                        value="<?php echo htmlspecialchars($form_data['email'] ?? ''); ?>"
                        placeholder="Enter your email address"
                    >
                </div>
                
                <div class="form-group">
                    <label for="phone">Phone Number</label>
                    <input 
                        type="tel" 
                        id="phone" 
                        name="phone" 
                        class="form-control" 
                        value="<?php echo htmlspecialchars($form_data['phone'] ?? ''); ?>"
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
                    ><?php echo htmlspecialchars($form_data['address'] ?? ''); ?></textarea>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="password">Password *</label>
                        <input 
                            type="password" 
                            id="password" 
                            name="password" 
                            class="form-control" 
                            required 
                            placeholder="Create a strong password"
                        >
                        <small class="form-text">
                            Password must be at least 8 characters with uppercase, lowercase, and number.
                        </small>
                    </div>
                    
                    <div class="form-group">
                        <label for="password_confirm">Confirm Password *</label>
                        <input 
                            type="password" 
                            id="password_confirm" 
                            name="password_confirm" 
                            class="form-control" 
                            required 
                            placeholder="Confirm your password"
                        >
                    </div>
                </div>
                
                <div class="form-group form-check">
                    <input 
                        type="checkbox" 
                        id="terms" 
                        name="terms" 
                        class="form-check-input" 
                        required
                    >
                    <label for="terms" class="form-check-label">
                        I agree to the <a href="#" target="_blank">Terms of Service</a> and 
                        <a href="#" target="_blank">Privacy Policy</a>
                    </label>
                </div>
                
                <button type="submit" class="btn btn-primary btn-full">
                    Create Account
                </button>
            </form>
            
            <div class="auth-links">
                <p>Already have an account? <a href="login.php">Login here</a></p>
            </div>
        </div>
    </div>
</div>

<?php
// Include footer
include '../includes/footer.php';
?>
