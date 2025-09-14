<?php
/**
 * User Logout
 * Implements F101 - Logout functionality
 * Story 104: Log out of account
 */

require_once '../../includes/init.php';

// Check if user is logged in
if (isLoggedIn()) {
    $user = getCurrentUser();
    
    // Log the logout activity
    logActivity($user['user_id'], 'user_logout', 'User logged out');
    
    // Destroy session and logout
    logoutUser();
    
    // Redirect to login page with success message
    redirectWithMessage(
        SITE_URL . '/pages/auth/login.php?logged_out=1', 
        'You have been successfully logged out.', 
        'success'
    );
} else {
    // Not logged in, redirect to home
    redirectWithMessage(SITE_URL, 'You are not logged in.', 'info');
}
?>
