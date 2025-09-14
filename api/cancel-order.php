<?php
header('Content-Type: application/json');
require_once '../includes/init.php';

// Check if user is logged in
if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

if (!$input || !isset($input['order_id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Order ID is required']);
    exit;
}

$order_id = (int)$input['order_id'];
$user = getCurrentUser();
$user_id = $user['user_id'];

try {
    // Check if order exists and belongs to the user
    $order = $db->fetch(
        "SELECT * FROM orders WHERE order_id = ? AND user_id = ?",
        [$order_id, $user_id]
    );

    if (!$order) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Order not found']);
        exit;
    }

    // Check if order can be cancelled (only pending orders can be cancelled)
    if ($order['status'] !== 'pending') {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Only pending orders can be cancelled']);
        exit;
    }

    // Update order status to cancelled
    $updated = $db->query(
        "UPDATE orders SET status = 'cancelled', updated_at = CURRENT_TIMESTAMP WHERE order_id = ?",
        [$order_id]
    );

    if ($updated) {
        echo json_encode(['success' => true, 'message' => 'Order cancelled successfully']);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to cancel order']);
    }

} catch (Exception $e) {
    error_log("Error cancelling order: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Internal server error']);
}
?>