<?php

// Start output buffering
ob_start();

// Include configuration
require_once __DIR__ . '/../config/config.php';

// Include database class
require_once __DIR__ . '/../config/database.php';

// Include utility functions
require_once __DIR__ . '/functions.php';

// Include session management
require_once __DIR__ . '/session.php';

// Check session timeout
checkSessionTimeout();

// Set timezone
date_default_timezone_set('Australia/Sydney');

// Initialize database connection
try {
    $db = new Database();
    // Ensure database exists
    $db->createDatabaseIfNotExists();
    // Test connection
    $db->connect();
} catch (Exception $e) {
    // In production, log this error and show a user-friendly message
    die('Database connection failed. Please try again later.');
}
?>
