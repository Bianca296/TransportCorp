<nav class="main-nav">
    <ul class="nav-list">
        <li><a href="<?php echo SITE_URL; ?>" class="nav-link">Home</a></li>
        <li><a href="<?php echo SITE_URL; ?>/services.php" class="nav-link">Services</a></li>
        <!-- <li><a href="<?php echo SITE_URL; ?>/order.php" class="nav-link">Create Order</a></li> -->
        <li>
            <?php if (is_logged_in()): ?>
                <?php 
                $nav_auth = getAuth();
                $nav_user = $nav_auth->getCurrentUser();
                ?>
                <?php if ($nav_user && $nav_user['role'] === 'customer'): ?>
                    <a href="<?php echo SITE_URL; ?>/customer/track-shipment.php" class="nav-link">Track Shipment</a>
                <?php else: ?>
                    <a href="<?php echo SITE_URL; ?>/track.php" class="nav-link">Track Shipment</a>
                <?php endif; ?>
            <?php else: ?>
                <a href="<?php echo SITE_URL; ?>/track.php" class="nav-link">Track Shipment</a>
            <?php endif; ?>
        </li>
        <li><a href="<?php echo SITE_URL; ?>/contact.php" class="nav-link">Contact</a></li>
        <?php if (is_logged_in()): ?>
            <?php 
            $auth = getAuth();
            $user = $auth->getCurrentUser();
            ?>
            <?php if ($user && $user['role'] === 'admin'): ?>
                <li><a href="<?php echo SITE_URL; ?>/admin/dashboard.php" class="nav-link">Admin</a></li>
            <?php elseif ($user && $user['role'] === 'employee'): ?>
                <li><a href="<?php echo SITE_URL; ?>/employee/dashboard.php" class="nav-link">Employee</a></li>
            <?php else: ?>
                <li><a href="<?php echo SITE_URL; ?>/customer/dashboard.php" class="nav-link">Dashboard</a></li>
            <?php endif; ?>
            <li><a href="<?php echo SITE_URL; ?>/auth/logout.php" class="nav-link">Logout (<?php echo htmlspecialchars($user['first_name'] ?? 'User'); ?>)</a></li>
        <?php else: ?>
            <li><a href="<?php echo SITE_URL; ?>/auth/login.php" class="nav-link">Login</a></li>
            <li><a href="<?php echo SITE_URL; ?>/auth/register.php" class="nav-link">Register</a></li>
        <?php endif; ?>
    </ul>
</nav>
