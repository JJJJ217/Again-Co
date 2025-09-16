<?php
/**
 * Delete Product
 * Staff/Admin can remove products from inventory
 */

require_once '../../../includes/init.php';

// Require admin/staff access
requireLogin();
requireRole(['admin', 'staff']);

$user = getCurrentUser();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['product_id'])) {
    header("Location: index.php");
    exit;
}

$product_id = (int)$_POST['product_id'];

try {
    // Get product info first
    $product = $db->fetch("SELECT product_name FROM products WHERE product_id = ?", [$product_id]);
    
    if (!$product) {
        throw new Exception("Product not found");
    }
    
    // Check if product has orders
    $order_count = $db->fetch("SELECT COUNT(*) as count FROM order_items WHERE product_id = ?", [$product_id])['count'];
    
    if ($order_count > 0) {
        // Instead of deleting, mark as inactive if it has orders
        $db->query("UPDATE products SET is_active = 0 WHERE product_id = ?", [$product_id]);
        $message = "Product '{$product['product_name']}' has been deactivated because it has existing orders. It will no longer be visible to customers.";
    } else {
        // Safe to delete completely
        $db->query("DELETE FROM products WHERE product_id = ?", [$product_id]);
        $message = "Product '{$product['product_name']}' has been deleted successfully.";
    }
    
    header("Location: index.php?message=" . urlencode($message));
    exit;
    
} catch (Exception $e) {
    $error = $e->getMessage();
    header("Location: index.php?error=" . urlencode($error));
    exit;
}
?>