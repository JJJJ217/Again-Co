<?php
/**
 * Shopping Cart API
 * Implements F110 - Shopping Cart functionality
 * AJAX endpoints for cart operations
 */

require_once '../includes/init.php';

// Ensure this is an AJAX request
if (!isset($_SERVER['HTTP_X_REQUESTED_WITH']) || $_SERVER['HTTP_X_REQUESTED_WITH'] !== 'XMLHttpRequest') {
    http_response_code(400);
    exit('Invalid request');
}

// Require login for cart operations
if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Please log in to manage your cart']);
    exit;
}

$user = getCurrentUser();
$response = ['success' => false, 'message' => 'Invalid request'];

try {
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? '';
    
    switch ($action) {
        case 'add':
            $product_id = intval($input['product_id'] ?? 0);
            $quantity = intval($input['quantity'] ?? 1);
            
            if ($product_id <= 0 || $quantity <= 0) {
                $response['message'] = 'Invalid product or quantity';
                break;
            }
            
            // Check if product exists and is available
            $product = $db->fetch(
                "SELECT product_id, product_name, price, stock FROM products WHERE product_id = ? AND is_active = 1",
                [$product_id]
            );
            
            if (!$product) {
                $response['message'] = 'Product not found';
                break;
            }
            
            if ($product['stock'] < $quantity) {
                $response['message'] = 'Not enough stock available';
                break;
            }
            
            // Check if item already in cart
            $existing_item = $db->fetch(
                "SELECT cart_id, quantity FROM shopping_cart WHERE user_id = ? AND product_id = ?",
                [$user['user_id'], $product_id]
            );
            
            if ($existing_item) {
                // Update existing item
                $new_quantity = $existing_item['quantity'] + $quantity;
                
                if ($new_quantity > $product['stock']) {
                    $response['message'] = 'Cannot add more items than available in stock';
                    break;
                }
                
                $db->query(
                    "UPDATE shopping_cart SET quantity = ? WHERE cart_id = ?",
                    [$new_quantity, $existing_item['cart_id']]
                );
            } else {
                // Add new item
                $db->query(
                    "INSERT INTO shopping_cart (user_id, product_id, quantity, added_at) VALUES (?, ?, ?, NOW())",
                    [$user['user_id'], $product_id, $quantity]
                );
            }
            
            // Log activity
            logActivity($user['user_id'], 'cart_add', "Added {$quantity}x {$product['product_name']} to cart");
            
            $response['success'] = true;
            $response['message'] = 'Item added to cart successfully';
            break;
            
        case 'update':
            $product_id = intval($input['product_id'] ?? 0);
            $quantity = intval($input['quantity'] ?? 1);
            
            if ($product_id <= 0 || $quantity < 0) {
                $response['message'] = 'Invalid product or quantity';
                break;
            }
            
            if ($quantity == 0) {
                // Remove item if quantity is 0
                $db->query(
                    "DELETE FROM shopping_cart WHERE user_id = ? AND product_id = ?",
                    [$user['user_id'], $product_id]
                );
                
                logActivity($user['user_id'], 'cart_remove', "Removed product {$product_id} from cart");
            } else {
                // Check stock availability
                $product = $db->fetch(
                    "SELECT stock FROM products WHERE product_id = ? AND is_active = 1",
                    [$product_id]
                );
                
                if (!$product || $product['stock'] < $quantity) {
                    $response['message'] = 'Not enough stock available';
                    break;
                }
                
                // Update quantity
                $db->query(
                    "UPDATE shopping_cart SET quantity = ? WHERE user_id = ? AND product_id = ?",
                    [$quantity, $user['user_id'], $product_id]
                );
                
                logActivity($user['user_id'], 'cart_update', "Updated product {$product_id} quantity to {$quantity}");
            }
            
            $response['success'] = true;
            $response['message'] = 'Cart updated successfully';
            break;
            
        case 'remove':
            $product_id = intval($input['product_id'] ?? 0);
            
            if ($product_id <= 0) {
                $response['message'] = 'Invalid product ID';
                break;
            }
            
            $db->query(
                "DELETE FROM shopping_cart WHERE user_id = ? AND product_id = ?",
                [$user['user_id'], $product_id]
            );
            
            logActivity($user['user_id'], 'cart_remove', "Removed product {$product_id} from cart");
            
            $response['success'] = true;
            $response['message'] = 'Item removed from cart';
            break;
            
        case 'clear':
            $db->query(
                "DELETE FROM shopping_cart WHERE user_id = ?",
                [$user['user_id']]
            );
            
            logActivity($user['user_id'], 'cart_clear', "Cleared shopping cart");
            
            $response['success'] = true;
            $response['message'] = 'Cart cleared successfully';
            break;
            
        case 'get':
            // Return current cart contents
            $cart_items = $db->fetchAll(
                "SELECT c.*, p.product_name, p.price, p.image_url, p.stock,
                        (c.quantity * p.price) as total_price
                 FROM shopping_cart c
                 JOIN products p ON c.product_id = p.product_id
                 WHERE c.user_id = ? AND p.is_active = 1
                 ORDER BY c.added_at DESC",
                [$user['user_id']]
            );
            
            $response['success'] = true;
            $response['cart_items'] = $cart_items;
            break;
            
        default:
            $response['message'] = 'Unknown action';
            break;
    }
    
    // Always include updated cart summary in successful responses
    if ($response['success'] && in_array($action, ['add', 'update', 'remove', 'clear'])) {
        $cart_summary = $db->fetch(
            "SELECT 
                COUNT(*) as item_count,
                SUM(c.quantity) as total_quantity,
                SUM(c.quantity * p.price) as total_amount
             FROM shopping_cart c
             JOIN products p ON c.product_id = p.product_id
             WHERE c.user_id = ? AND p.is_active = 1",
            [$user['user_id']]
        );
        
        $response['cart'] = [
            'item_count' => intval($cart_summary['item_count'] ?? 0),
            'total_quantity' => intval($cart_summary['total_quantity'] ?? 0),
            'total' => formatCurrency($cart_summary['total_amount'] ?? 0)
        ];
    }
    
} catch (Exception $e) {
    error_log("Cart API error: " . $e->getMessage());
    $response = [
        'success' => false,
        'message' => 'An error occurred processing your request'
    ];
}

header('Content-Type: application/json');
echo json_encode($response);
?>
