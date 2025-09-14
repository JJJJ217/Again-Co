<?php
/**
 * Site Header
 * Navigation and user authentication status
 */

$current_user = getCurrentUser();
?>

<header class="header">
    <div class="container">
        <div class="header-top">
            <a href="<?= SITE_URL ?>" class="logo">
                Again&Co
            </a>
            
            <div class="user-menu">
                <?php if (isLoggedIn()): ?>
                    <span>Welcome, <?= htmlspecialchars($current_user['name']) ?></span>
                    
                    <?php if (hasRole('admin')): ?>
                        <a href="<?= SITE_URL ?>/pages/admin/dashboard.php">Admin Panel</a>
                    <?php elseif (hasRole('staff')): ?>
                        <a href="<?= SITE_URL ?>/pages/admin/dashboard.php">Staff Panel</a>
                    <?php endif; ?>
                    
                    <a href="<?= SITE_URL ?>/pages/user/profile.php">My Account</a>
                    <a href="<?= SITE_URL ?>/pages/user/orders.php">My Orders</a>
                    <a href="<?= SITE_URL ?>/pages/user/cart.php" class="cart-link">
                        Cart <span class="cart-count"><?= getCartCount() ?></span>
                    </a>
                    <a href="<?= SITE_URL ?>/pages/auth/logout.php">Logout</a>
                <?php else: ?>
                    <a href="<?= SITE_URL ?>/pages/auth/login.php">Login</a>
                    <a href="<?= SITE_URL ?>/pages/auth/register.php">Register</a>
                <?php endif; ?>
            </div>
        </div>
        
        <nav class="main-nav">
            <ul class="nav-list">
                <li><a href="<?= SITE_URL ?>" <?= basename($_SERVER['PHP_SELF']) === 'index.php' ? 'class="active"' : '' ?>>Home</a></li>
                <li><a href="<?= SITE_URL ?>/pages/products/catalog.php">Products</a></li>
                <li><a href="<?= SITE_URL ?>/pages/products/catalog.php?category=Clothing">Clothing</a></li>
                <li><a href="<?= SITE_URL ?>/pages/products/catalog.php?category=Accessories">Accessories</a></li>
                <li><a href="<?= SITE_URL ?>/pages/products/catalog.php?category=Music">Music</a></li>
                <li><a href="<?= SITE_URL ?>/pages/about.php">About</a></li>
                <li><a href="<?= SITE_URL ?>/pages/contact.php">Contact</a></li>
            </ul>
        </nav>
        
        <!-- Search Bar -->
        <div class="search-container mt-2">
            <form action="<?= SITE_URL ?>/pages/products/catalog.php" method="GET" class="search-form">
                <div class="search-input-group">
                    <input type="text" 
                           name="search" 
                           id="search-input"
                           class="form-control" 
                           placeholder="Search for vintage items..." 
                           value="<?= isset($_GET['search']) ? htmlspecialchars($_GET['search']) : '' ?>">
                    <button type="submit" class="btn btn-primary">Search</button>
                </div>
            </form>
            <div id="search-results" class="search-results" style="display: none;"></div>
        </div>
    </div>
</header>
