<?php
/**
 * Test script to check products in database
 */

require_once '../../../includes/init.php';

echo "<h1>Database Test</h1>";

try {
    echo "<h2>Connection Test</h2>";
    $test = $db->fetch("SELECT 1 as test");
    echo "Database connection: OK<br>";
    
    echo "<h2>Products Count</h2>";
    $count = $db->fetch("SELECT COUNT(*) as count FROM products");
    echo "Total products: " . $count['count'] . "<br>";
    
    if ($count['count'] > 0) {
        echo "<h2>Sample Products</h2>";
        $products = $db->fetchAll("SELECT product_id, product_name, price, stock, is_active, created_at FROM products LIMIT 5");
        echo "<table border='1'>";
        echo "<tr><th>ID</th><th>Name</th><th>Price</th><th>Stock</th><th>Active</th><th>Created</th></tr>";
        foreach ($products as $product) {
            echo "<tr>";
            echo "<td>" . $product['product_id'] . "</td>";
            echo "<td>" . htmlspecialchars($product['product_name']) . "</td>";
            echo "<td>$" . number_format($product['price'], 2) . "</td>";
            echo "<td>" . $product['stock'] . "</td>";
            echo "<td>" . ($product['is_active'] ? 'Yes' : 'No') . "</td>";
            echo "<td>" . $product['created_at'] . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p>No products found in database. Let's add one for testing:</p>";
        
        $db->query(
            "INSERT INTO products (product_name, description, price, stock, category, is_active) VALUES (?, ?, ?, ?, ?, ?)",
            ['Test Product', 'A test product for debugging', 29.99, 10, 'Electronics', 1]
        );
        
        echo "<p>Test product added!</p>";
        
        $new_count = $db->fetch("SELECT COUNT(*) as count FROM products");
        echo "New total products: " . $new_count['count'] . "<br>";
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>Error: " . $e->getMessage() . "</p>";
    echo "<p>Stack trace: " . $e->getTraceAsString() . "</p>";
}
?>