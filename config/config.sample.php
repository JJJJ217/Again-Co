<?php
/**
 * Sample Configuration File
 * Copy this file to config.php and update with your actual values
 */

// Database Configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'your_db_username');
define('DB_PASS', 'your_db_password');
define('DB_NAME', 'evinty_db');

// Site Configuration
define('SITE_URL', 'http://localhost/vinty-draft-webpage');
define('SITE_NAME', 'Again&Co');
define('SITE_EMAIL', 'admin@yourdomain.com');

// Security Configuration
define('SECRET_KEY', 'your-secret-key-here-change-this');
define('CSRF_TOKEN_EXPIRY', 3600); // 1 hour
define('SESSION_LIFETIME', 86400); // 24 hours

// File Upload Configuration
define('UPLOAD_MAX_SIZE', 5 * 1024 * 1024); // 5MB
define('UPLOAD_DIR', __DIR__ . '/../assets/uploads/');
define('ALLOWED_IMAGE_TYPES', ['jpg', 'jpeg', 'png', 'gif', 'webp']);

// Email Configuration (for password reset)
define('SMTP_HOST', 'your-smtp-host');
define('SMTP_PORT', 587);
define('SMTP_USERNAME', 'your-smtp-username');
define('SMTP_PASSWORD', 'your-smtp-password');
define('SMTP_ENCRYPTION', 'tls');

// Pagination
define('PRODUCTS_PER_PAGE', 12);
define('ORDERS_PER_PAGE', 20);

// Environment
define('ENVIRONMENT', 'development'); // 'development' or 'production'
define('DEBUG_MODE', true);

// Payment Configuration (for future implementation)
define('STRIPE_PUBLIC_KEY', 'your-stripe-public-key');
define('STRIPE_SECRET_KEY', 'your-stripe-secret-key');
define('PAYPAL_CLIENT_ID', 'your-paypal-client-id');
define('PAYPAL_CLIENT_SECRET', 'your-paypal-client-secret');

// Cache Configuration
define('CACHE_ENABLED', false);
define('CACHE_LIFETIME', 3600);

// Social Media Links
define('FACEBOOK_URL', 'https://facebook.com/yourpage');
define('TWITTER_URL', 'https://twitter.com/yourhandle');
define('INSTAGRAM_URL', 'https://instagram.com/yourhandle');

// Admin Configuration
define('ADMIN_EMAIL', 'admin@yourdomain.com');
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOGIN_LOCKOUT_TIME', 900); // 15 minutes
?>
