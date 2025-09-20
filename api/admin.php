<?php
/**
 * Admin API
 * Handles administrative operations for product management
 * Implements F107 - Inventory Control backend operations
 */

require_once '../includes/init.php';

// Set content type
header('Content-Type: application/json');

// Require admin access for all operations
if (!isLoggedIn() || !in_array(getCurrentUser()['role'] ?? '', ['admin', 'staff'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

$user = getCurrentUser();

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Handle POST requests (JSON body)
        $input = json_decode(file_get_contents('php://input'), true);
        $action = $input['action'] ?? $_POST['action'] ?? '';
        
        switch ($action) {
            case 'update_stock':
                $product_id = (int)($input['product_id'] ?? 0);
                $stock = (int)($input['stock'] ?? 0);
                
                if ($product_id <= 0) {
                    throw new Exception('Invalid product ID');
                }
                
                if ($stock < 0) {
                    throw new Exception('Stock cannot be negative');
                }
                
                $updated = $db->query(
                    "UPDATE products SET stock = ?, updated_at = NOW() WHERE product_id = ?",
                    [$stock, $product_id]
                );
                
                if ($updated) {
                    // Log the stock change
                    $db->insert('admin_logs', [
                        'user_id' => $user['user_id'],
                        'action' => 'stock_update',
                        'details' => json_encode([
                            'product_id' => $product_id,
                            'new_stock' => $stock
                        ]),
                        'ip_address' => $_SERVER['REMOTE_ADDR'],
                        'created_at' => date('Y-m-d H:i:s')
                    ]);
                    
                    echo json_encode(['success' => true, 'message' => 'Stock updated successfully']);
                } else {
                    throw new Exception('Failed to update stock');
                }
                break;
                
            case 'toggle_product':
                $product_id = (int)($input['product_id'] ?? 0);
                
                if ($product_id <= 0) {
                    throw new Exception('Invalid product ID');
                }
                
                // Get current status
                $product = $db->fetch(
                    "SELECT is_active FROM products WHERE product_id = ?",
                    [$product_id]
                );
                
                if (!$product) {
                    throw new Exception('Product not found');
                }
                
                $new_status = $product['is_active'] ? 0 : 1;
                
                $updated = $db->query(
                    "UPDATE products SET is_active = ?, updated_at = NOW() WHERE product_id = ?",
                    [$new_status, $product_id]
                );
                
                if ($updated) {
                    // Log the status change
                    $db->insert('admin_logs', [
                        'user_id' => $user['user_id'],
                        'action' => 'product_toggle',
                        'details' => json_encode([
                            'product_id' => $product_id,
                            'new_status' => $new_status ? 'active' : 'inactive'
                        ]),
                        'ip_address' => $_SERVER['REMOTE_ADDR'],
                        'created_at' => date('Y-m-d H:i:s')
                    ]);
                    
                    echo json_encode([
                        'success' => true, 
                        'message' => 'Product status updated',
                        'new_status' => $new_status
                    ]);
                } else {
                    throw new Exception('Failed to update product status');
                }
                break;
                
            case 'delete_product':
                $product_id = (int)($input['product_id'] ?? 0);
                
                if ($product_id <= 0) {
                    throw new Exception('Invalid product ID');
                }
                
                // Check if product has orders
                $order_count = $db->fetch(
                    "SELECT COUNT(*) as count FROM order_items WHERE product_id = ?",
                    [$product_id]
                )['count'];
                
                if ($order_count > 0) {
                    // Don't delete products with orders, just deactivate
                    $db->query(
                        "UPDATE products SET is_active = 0, updated_at = NOW() WHERE product_id = ?",
                        [$product_id]
                    );
                    
                    echo json_encode([
                        'success' => true, 
                        'message' => 'Product deactivated (has existing orders)'
                    ]);
                } else {
                    // Safe to delete
                    $deleted = $db->query(
                        "DELETE FROM products WHERE product_id = ?",
                        [$product_id]
                    );
                    
                    if ($deleted) {
                        // Log the deletion
                        $db->insert('admin_logs', [
                            'user_id' => $user['user_id'],
                            'action' => 'product_delete',
                            'details' => json_encode(['product_id' => $product_id]),
                            'ip_address' => $_SERVER['REMOTE_ADDR'],
                            'created_at' => date('Y-m-d H:i:s')
                        ]);
                        
                        echo json_encode(['success' => true, 'message' => 'Product deleted successfully']);
                    } else {
                        throw new Exception('Failed to delete product');
                    }
                }
                break;
                
            case 'bulk_action':
                $bulk_action = $input['bulk_action'] ?? '';
                $product_ids = $input['product_ids'] ?? [];
                
                if (empty($bulk_action) || empty($product_ids)) {
                    throw new Exception('Invalid bulk action parameters');
                }
                
                $placeholders = str_repeat('?,', count($product_ids) - 1) . '?';
                $success_count = 0;
                
                switch ($bulk_action) {
                    case 'activate':
                        $updated = $db->query(
                            "UPDATE products SET is_active = 1, updated_at = NOW() WHERE product_id IN ({$placeholders})",
                            $product_ids
                        );
                        $success_count = $db->rowCount();
                        break;
                        
                    case 'deactivate':
                        $updated = $db->query(
                            "UPDATE products SET is_active = 0, updated_at = NOW() WHERE product_id IN ({$placeholders})",
                            $product_ids
                        );
                        $success_count = $db->rowCount();
                        break;
                        
                    case 'delete':
                        // Only delete products without orders
                        $safe_to_delete = $db->fetchAll(
                            "SELECT p.product_id FROM products p 
                             LEFT JOIN order_items oi ON p.product_id = oi.product_id 
                             WHERE p.product_id IN ({$placeholders}) 
                             AND oi.product_id IS NULL",
                            $product_ids
                        );
                        
                        if (!empty($safe_to_delete)) {
                            $safe_ids = array_column($safe_to_delete, 'product_id');
                            $safe_placeholders = str_repeat('?,', count($safe_ids) - 1) . '?';
                            
                            $deleted = $db->query(
                                "DELETE FROM products WHERE product_id IN ({$safe_placeholders})",
                                $safe_ids
                            );
                            $success_count = $db->rowCount();
                        }
                        
                        // Deactivate products with orders
                        $has_orders = array_diff($product_ids, array_column($safe_to_delete, 'product_id'));
                        if (!empty($has_orders)) {
                            $order_placeholders = str_repeat('?,', count($has_orders) - 1) . '?';
                            $db->query(
                                "UPDATE products SET is_active = 0, updated_at = NOW() WHERE product_id IN ({$order_placeholders})",
                                $has_orders
                            );
                        }
                        break;
                        
                    default:
                        throw new Exception('Invalid bulk action');
                }
                
                // Log bulk action
                $db->insert('admin_logs', [
                    'user_id' => $user['user_id'],
                    'action' => 'bulk_action',
                    'details' => json_encode([
                        'action' => $bulk_action,
                        'product_ids' => $product_ids,
                        'success_count' => $success_count
                    ]),
                    'ip_address' => $_SERVER['REMOTE_ADDR'],
                    'created_at' => date('Y-m-d H:i:s')
                ]);
                
                echo json_encode([
                    'success' => true, 
                    'message' => "Bulk action completed for {$success_count} products"
                ]);
                break;
                
            case 'import_products':
                if (!isset($_FILES['csv_file']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
                    throw new Exception('No valid CSV file uploaded');
                }
                
                $csv_file = $_FILES['csv_file']['tmp_name'];
                $imported_count = 0;
                $errors = [];
                
                if (($handle = fopen($csv_file, 'r')) !== FALSE) {
                    $header = fgetcsv($handle); // Skip header row
                    
                    while (($data = fgetcsv($handle)) !== FALSE) {
                        try {
                            if (count($data) < 6) {
                                throw new Exception('Insufficient columns in CSV row');
                            }
                            
                            $product_data = [
                                'product_name' => trim($data[0]),
                                'description' => trim($data[1]),
                                'price' => (float)$data[2],
                                'stock' => (int)$data[3],
                                'category' => trim($data[4]),
                                'brand' => trim($data[5]),
                                'condition_rating' => isset($data[6]) ? (int)$data[6] : 4,
                                'image_url' => isset($data[7]) ? trim($data[7]) : null,
                                'is_active' => 1,
                                'created_at' => date('Y-m-d H:i:s'),
                                'updated_at' => date('Y-m-d H:i:s')
                            ];
                            
                            $product_id = $db->insert('products', $product_data);
                            
                            if ($product_id) {
                                $imported_count++;
                            }
                            
                        } catch (Exception $e) {
                            $errors[] = "Row " . ($imported_count + count($errors) + 2) . ": " . $e->getMessage();
                        }
                    }
                    
                    fclose($handle);
                }
                
                // Log import
                $db->insert('admin_logs', [
                    'user_id' => $user['user_id'],
                    'action' => 'import_products',
                    'details' => json_encode([
                        'imported_count' => $imported_count,
                        'errors' => $errors
                    ]),
                    'ip_address' => $_SERVER['REMOTE_ADDR'],
                    'created_at' => date('Y-m-d H:i:s')
                ]);
                
                echo json_encode([
                    'success' => true,
                    'message' => "Imported {$imported_count} products successfully",
                    'errors' => $errors
                ]);
                break;
                
            default:
                throw new Exception('Unknown action');
        }
        
    } elseif ($_SERVER['REQUEST_METHOD'] === 'GET') {
        // Handle GET requests
        $action = $_GET['action'] ?? '';
        
        switch ($action) {
            case 'export_products':
                $products = $db->fetchAll(
                    "SELECT product_name, description, price, stock, category, brand, 
                            condition_rating, image_url, is_active, created_at
                     FROM products 
                     ORDER BY created_at DESC"
                );
                
                // Set headers for CSV download
                header('Content-Type: text/csv');
                header('Content-Disposition: attachment; filename="products_export_' . date('Y-m-d') . '.csv"');
                
                $output = fopen('php://output', 'w');
                
                // Write header
                fputcsv($output, [
                    'Product Name', 'Description', 'Price', 'Stock', 'Category', 
                    'Brand', 'Condition', 'Image URL', 'Active', 'Created Date'
                ]);
                
                // Write data
                foreach ($products as $product) {
                    fputcsv($output, [
                        $product['product_name'],
                        $product['description'],
                        $product['price'],
                        $product['stock'],
                        $product['category'],
                        $product['brand'],
                        $product['condition_rating'],
                        $product['image_url'],
                        $product['is_active'] ? 'Yes' : 'No',
                        $product['created_at']
                    ]);
                }
                
                fclose($output);
                
                // Log export
                $db->insert('admin_logs', [
                    'user_id' => $user['user_id'],
                    'action' => 'export_products',
                    'details' => json_encode(['product_count' => count($products)]),
                    'ip_address' => $_SERVER['REMOTE_ADDR'],
                    'created_at' => date('Y-m-d H:i:s')
                ]);
                
                exit; // Don't send JSON response for file download
                
            default:
                throw new Exception('Unknown action');
        }
    } else {
        throw new Exception('Method not allowed');
    }
    
} catch (Exception $e) {
    error_log("Admin API error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
