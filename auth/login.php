<?php
/**
 * Login Page
 * User authentication form and processing
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
$success_message = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || !$auth->verifyCSRFToken($_POST['csrf_token'])) {
        $error_message = 'Invalid request. Please try again.';
    } else {
        $login = $_POST['login'] ?? '';
        $password = $_POST['password'] ?? '';
        $remember_me = isset($_POST['remember_me']);
        
        $result = $auth->login($login, $password, $remember_me);
        
        if ($result['success']) {
            redirect($result['redirect']);
        } else {
            $error_message = $result['message'];
        }
    }
}

// Check for success messages from registration
if (isset($_SESSION['success_message'])) {
    $success_message = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}

// Set page title
$page_title = 'Login';

// Include header
include '../includes/header.php';
?>

<div class="container">
    <div class="auth-container">
        <div class="auth-form">
            <h2>Login to Your Account</h2>
            <p class="auth-subtitle">Access your TransportCorp dashboard</p>
            
            <?php if (!empty($error_message)): ?>
                <div class="alert alert-error">
                    <?php echo htmlspecialchars($error_message); ?>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($success_message)): ?>
                <div class="alert alert-success">
                    <?php echo htmlspecialchars($success_message); ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="" class="login-form">
                <input type="hidden" name="csrf_token" value="<?php echo $auth->generateCSRFToken(); ?>">
                
                <div class="form-group">
                    <label for="login">Username or Email</label>
                    <input 
                        type="text" 
                        id="login" 
                        name="login" 
                        class="form-control" 
                        required 
                        value="<?php echo isset($_POST['login']) ? htmlspecialchars($_POST['login']) : ''; ?>"
                        placeholder="Enter your username or email"
                    >
                </div>
                
                <div class="form-group">
                    <label for="password">Password</label>
                    <input 
                        type="password" 
                        id="password" 
                        name="password" 
                        class="form-control" 
                        required 
                        placeholder="Enter your password"
                    >
                </div>
                
                <div class="form-group form-check">
                    <input 
                        type="checkbox" 
                        id="remember_me" 
                        name="remember_me" 
                        class="form-check-input"
                    >
                    <label for="remember_me" class="form-check-label">
                        Remember me for 30 days
                    </label>
                </div>
                
                <button type="submit" class="btn btn-primary btn-full">
                    Login
                </button>
            </form>
            
            <div class="auth-links">
                <p>Don't have an account? <a href="register.php">Register here</a></p>
                <p><a href="forgot-password.php">Forgot your password?</a></p>
            </div>
            
            <div class="demo-accounts">
                <h4>Demo Accounts for Testing:</h4>
                <div class="demo-grid">
                    <div class="demo-account">
                        <strong>Admin:</strong><br>
                        admin@transportcorp.com<br>
                        <small>Password: password</small>
                    </div>
                    <div class="demo-account">
                        <strong>Employee:</strong><br>
                        employee@transportcorp.com<br>
                        <small>Password: password</small>
                    </div>
                    <div class="demo-account">
                        <strong>Customer:</strong><br>
                        customer@example.com<br>
                        <small>Password: password</small>
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
