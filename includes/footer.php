<?php
/**
 * Site Footer
 * Links and company information
 */
?>

<footer class="footer">
    <div class="container">
        <div class="footer-content">
            <div class="footer-section">
                <h3>Again&Co</h3>
                <p>Your destination for quality vintage items. Discover unique pieces with character and style.</p>
                <p>&copy; <?= date('Y') ?> Again&Co. All rights reserved.</p>
            </div>
            
            <div class="footer-section">
                <h3>Quick Links</h3>
                <a href="<?= SITE_URL ?>">Home</a>
                <a href="<?= SITE_URL ?>/pages/products/catalog.php">All Products</a>
                <a href="<?= SITE_URL ?>/pages/about.php">About Us</a>
                <a href="<?= SITE_URL ?>/pages/contact.php">Contact</a>
                <?php if (!isLoggedIn()): ?>
                    <a href="<?= SITE_URL ?>/pages/auth/register.php">Join Us</a>
                <?php endif; ?>
            </div>
            
            <div class="footer-section">
                <h3>Categories</h3>
                <a href="<?= SITE_URL ?>/pages/products/catalog.php?category=Clothing">Vintage Clothing</a>
                <a href="<?= SITE_URL ?>/pages/products/catalog.php?category=Accessories">Accessories</a>
                <a href="<?= SITE_URL ?>/pages/products/catalog.php?category=Music">Music & Records</a>
                <a href="<?= SITE_URL ?>/pages/products/catalog.php?category=Home">Home & Decor</a>
            </div>
            
            <div class="footer-section">
                <h3>Customer Service</h3>
                <?php if (isLoggedIn()): ?>
                    <a href="<?= SITE_URL ?>/pages/user/profile.php">My Account</a>
                    <a href="<?= SITE_URL ?>/pages/user/orders.php">Order History</a>
                    <a href="<?= SITE_URL ?>/pages/user/cart.php">Shopping Cart</a>
                <?php endif; ?>
                <a href="<?= SITE_URL ?>/pages/help.php">Help & FAQ</a>
                <a href="<?= SITE_URL ?>/pages/shipping.php">Shipping Info</a>
                <a href="<?= SITE_URL ?>/pages/returns.php">Returns Policy</a>
            </div>
        </div>
        
        <div class="footer-bottom">
            <p>
                Made with ❤️ for vintage lovers | 
                <a href="<?= SITE_URL ?>/pages/privacy.php">Privacy Policy</a> | 
                <a href="<?= SITE_URL ?>/pages/terms.php">Terms of Service</a>
            </p>
        </div>
    </div>
</footer>
