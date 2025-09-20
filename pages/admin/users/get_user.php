<?php
/**
 * Get User Data API
 * Used by admin user management to fetch user details
 */

require_once '../../../includes/init.php';

// Set JSON header
header('Content-Type: application/json');

// Require admin access
try {
    requireLogin();
    requireRole(['admin']);
} catch (Exception $e) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Access denied']);
    exit;
}

$user_id = $_GET['id'] ?? 0;
$detailed = isset($_GET['detailed']);

if (!$user_id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'User ID required']);
    exit;
}

try {
    // Get user data
    $user = $db->fetch(
        "SELECT user_id, name, email, role, is_active, email_verified, created_at, updated_at, last_login, login_attempts 
         FROM users 
         WHERE user_id = ?",
        [$user_id]
    );
    
    if (!$user) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'User not found']);
        exit;
    }
    
    $response = [
        'success' => true,
        'user' => $user
    ];
    
    // If detailed view requested, include additional information
    if ($detailed) {
        // Get user profile
        $profile = $db->fetch(
            "SELECT * FROM user_profiles WHERE user_id = ?",
            [$user_id]
        );
        
        // Get recent orders
        $orders = $db->fetchAll(
            "SELECT order_id, order_date, total_price, status 
             FROM orders 
             WHERE user_id = ? 
             ORDER BY order_date DESC 
             LIMIT 10",
            [$user_id]
        );
        
        $response['profile'] = $profile;
        $response['orders'] = $orders;
    }
    
    echo json_encode($response);
    
} catch (Exception $e) {
    error_log("Get user API error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Server error']);
}
?>