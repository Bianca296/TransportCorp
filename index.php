<?php
/**
 * Homepage
 */

// Configuration
require_once 'config/config.php';

// Set page title
$page_title = 'Home';

// Include header
include 'includes/header.php';
?>

<div class="container">
    <?php
    // Display session messages
    if (isset($_SESSION['success_message'])):
    ?>
        <div class="alert alert-success">
            <?php echo htmlspecialchars($_SESSION['success_message']); ?>
        </div>
    <?php
        unset($_SESSION['success_message']);
    endif;
    
    if (isset($_SESSION['error_message'])):
    ?>
        <div class="alert alert-error">
            <?php echo htmlspecialchars($_SESSION['error_message']); ?>
        </div>
    <?php
        unset($_SESSION['error_message']);
    endif;
    ?>
    
    <!-- Hero Section -->
    <section class="hero">
        <div class="hero-content">
            <h2>Professional Transport Solutions</h2>
            <p>Reliable shipping services across land, air, and ocean. From local deliveries to international freight, we've got you covered with our comprehensive transport network.</p>
            <?php if (is_logged_in()): ?>
                <a href="<?php echo SITE_URL; ?>/order.php" class="btn">Create Shipping Order</a>
                <a href="<?php echo SITE_URL; ?>/services.php" class="btn btn-secondary">Our Services</a>
            <?php else: ?>
                <a href="<?php echo SITE_URL; ?>/auth/register.php" class="btn">Get Started Today</a>
                <a href="<?php echo SITE_URL; ?>/auth/login.php" class="btn btn-secondary">Login</a>
            <?php endif; ?>
        </div>
    </section>

    <!-- Quick Track Section -->
    <section class="quick-track">
        <div class="track-box">
            <h2>📍 Track Your Package</h2>
            <p>Get real-time updates on your shipment status</p>
            <form action="<?php 
                if (is_logged_in()) {
                    $homepage_auth = getAuth();
                    $homepage_user = $homepage_auth->getCurrentUser();
                    if ($homepage_user && $homepage_user['role'] === 'customer') {
                        echo 'customer/track-shipment.php';
                    } else {
                        echo 'track.php';
                    }
                } else {
                    echo 'track.php';
                }
            ?>" method="GET" class="track-form">
                <div class="track-input-group">
                    <input type="text" 
                           name="tracking" 
                           placeholder="Enter tracking number or order number..."
                           required>
                    <button type="submit" class="btn btn-primary">Track Now</button>
                </div>
                <small class="track-example">Example: TRK123456 or ORD-2024-1234</small>
            </form>
        </div>
    </section>

    <!-- Services Overview -->
    <section class="services-overview">
        <div class="text-center mb-2">
            <h2>Our Transport Services</h2>
            <p>Choose from our comprehensive range of shipping solutions</p>
        </div>
        
        <div class="services-grid">
            <div class="service-card">
                <div class="service-icon">🚛</div>
                <h3>Land Transport</h3>
                <p>Fast and reliable ground shipping for domestic and cross-border deliveries. From small packages to full truckloads.</p>
            </div>
            
            <div class="service-card">
                <div class="service-icon">✈️</div>
                <h3>Air Shipping</h3>
                <p>Express air freight for time-sensitive shipments. Global reach with expedited delivery options.</p>
            </div>
            
            <div class="service-card">
                <div class="service-icon">🚢</div>
                <h3>Ocean Freight</h3>
                <p>Cost-effective sea transport for large volume shipments. Full container and LCL options available.</p>
            </div>
        </div>
    </section>

    <!-- Quick Stats -->
    <section class="stats-section mt-2">
        <div class="text-center">
            <h3>Why Choose <?php echo SITE_NAME; ?>?</h3>
            <div class="services-grid">
                <div class="service-card">
                    <h4>10+ Years</h4>
                    <p>Experience in transport industry</p>
                </div>
                <div class="service-card">
                    <h4>Global Network</h4>
                    <p>Worldwide shipping coverage</p>
                </div>
                <div class="service-card">
                    <h4>24/7 Support</h4>
                    <p>Round-the-clock customer service</p>
                </div>
            </div>
        </div>
    </section>
</div>

<?php
// Include footer
include 'includes/footer.php';
?>
