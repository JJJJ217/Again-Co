<?php
/**
 * Admin Product Management
 * Implements F107 - Inventory Control functionality
 * Manage product inventory, stock levels, and product details
 */

require_once '../../../includes/init.php';

// Require admin access
requireLogin();
requireRole(['admin', 'staff']);

$user = getCurrentUser();

// Handle filtering and sorting
$filter = $_GET['filter'] ?? '';
$sort = $_GET['sort'] ?? 'newest';
$search = $_GET['search'] ?? '';
$category = $_GET['category'] ?? '';
$page = (int)($_GET['page'] ?? 1);
$per_page = 20;
$offset = ($page - 1) * $per_page;

$products = [];
$total_products = 0;

try {
    // Build WHERE clause
    $where_conditions = [];
    $params = [];
    
    if (!empty($search)) {
        $where_conditions[] = "(product_name LIKE ? OR description LIKE ? OR brand LIKE ?)";
        $search_term = "%{$search}%";
        $params = array_merge($params, [$search_term, $search_term, $search_term]);
    }
    
    if (!empty($category)) {
        $where_conditions[] = "category = ?";
        $params[] = $category;
    }
    
    switch ($filter) {
        case 'low_stock':
            $where_conditions[] = "stock <= 5 AND is_active = 1";
            break;
        case 'out_of_stock':
            $where_conditions[] = "stock = 0 AND is_active = 1";
            break;
        case 'inactive':
            $where_conditions[] = "is_active = 0";
            break;
        case 'active':
            $where_conditions[] = "is_active = 1";
            break;
        case 'all':
        default:
            // Show all products by default
            break;
    }
    
    $where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";
    
    // Get total count for pagination
    $count_query = "SELECT COUNT(*) as total FROM products {$where_clause}";
    $count_result = $db->fetch($count_query, $params);
    $total_products = $count_result['total'];
    
    // Build sort clause
    $sort_clause = "created_at DESC";
    switch ($sort) {
        case 'name':
            $sort_clause = "product_name ASC";
            break;
        case 'price_low':
            $sort_clause = "price ASC";
            break;
        case 'price_high':
            $sort_clause = "price DESC";
            break;
        case 'stock_low':
            $sort_clause = "stock ASC";
            break;
        case 'stock_high':
            $sort_clause = "stock DESC";
            break;
        case 'oldest':
            $sort_clause = "created_at ASC";
            break;
        case 'newest':
        default:
            $sort_clause = "created_at DESC";
            break;
    }
    
    // Get products - simplified query for testing
    try {
        // Use the filtered query with corrected column names
        $products_query = "SELECT * FROM products {$where_clause} ORDER BY {$sort_clause} LIMIT {$per_page} OFFSET {$offset}";
        $products = $db->fetchAll($products_query, $params);
        
    } catch (Exception $e) {
        error_log("Error in products query: " . $e->getMessage());
        $products = [];
    }
    
    // Get categories for filter dropdown
    $categories = $db->fetchAll(
        "SELECT DISTINCT category FROM products WHERE category IS NOT NULL ORDER BY category"
    );
    
} catch (Exception $e) {
    error_log("Admin products error: " . $e->getMessage());
    $products = [];
    $categories = [];
}

$total_pages = ceil($total_products / $per_page);
$page_title = "Product Management - Again&Co";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $page_title ?></title>
    <link rel="stylesheet" href="../../../assets/css/style.css">
    <style>
        .admin-layout {
            display: grid;
            grid-template-columns: 250px 1fr;
            min-height: calc(100vh - 160px);
        }
        
        .admin-sidebar {
            background: #2c3e50;
            color: white;
            padding: 2rem 0;
        }
        
        .admin-nav {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        
        .admin-nav-item {
            margin-bottom: 0.5rem;
        }
        
        .admin-nav-link {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.75rem 1.5rem;
            color: #bdc3c7;
            text-decoration: none;
            transition: all 0.3s ease;
        }
        
        .admin-nav-link:hover,
        .admin-nav-link.active {
            background: #34495e;
            color: white;
        }
        
        .admin-content {
            padding: 2rem;
            background: #f8f9fa;
        }
        
        .products-header {
            display: flex;
            justify-content: between;
            align-items: center;
            margin-bottom: 2rem;
        }
        
        .products-filters {
            background: white;
            border-radius: 10px;
            padding: 1.5rem;
            margin-bottom: 2rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .filters-row {
            display: grid;
            grid-template-columns: 1fr 200px 200px 200px auto;
            gap: 1rem;
            align-items: end;
        }
        
        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }
        
        .filter-group label {
            font-weight: 600;
            color: #2c3e50;
            font-size: 0.875rem;
        }
        
        .products-toolbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: white;
            border-radius: 10px;
            padding: 1rem 1.5rem;
            margin-bottom: 1rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .toolbar-info {
            color: #7f8c8d;
        }
        
        .toolbar-actions {
            display: flex;
            gap: 1rem;
        }
        
        .products-table {
            background: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .products-table table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .products-table th,
        .products-table td {
            padding: 1rem;
            text-align: left;
            border-bottom: 1px solid #ecf0f1;
        }
        
        .products-table th {
            background: #f8f9fa;
            font-weight: 600;
            color: #2c3e50;
        }
        
        .product-image {
            width: 60px;
            height: 60px;
            border-radius: 5px;
            overflow: hidden;
            background: #f8f9fa;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .product-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .product-info {
            display: flex;
            flex-direction: column;
            gap: 0.25rem;
        }
        
        .product-name {
            font-weight: 600;
            color: #2c3e50;
        }
        
        .product-meta {
            color: #7f8c8d;
            font-size: 0.875rem;
        }
        
        .stock-level {
            padding: 0.25rem 0.75rem;
            border-radius: 15px;
            font-size: 0.75rem;
            font-weight: 600;
            text-align: center;
        }
        
        .stock-level.good {
            background: #eafaf1;
            color: #27ae60;
        }
        
        .stock-level.low {
            background: #fef9e7;
            color: #f39c12;
        }
        
        .stock-level.critical {
            background: #fadbd8;
            color: #e74c3c;
        }
        
        .stock-level.out {
            background: #ebedef;
            color: #85929e;
        }
        
        .product-status {
            padding: 0.25rem 0.75rem;
            border-radius: 15px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .product-status.active {
            background: #eafaf1;
            color: #27ae60;
        }
        
        .product-status.inactive {
            background: #ebedef;
            color: #85929e;
        }
        
        .product-actions {
            display: flex;
            gap: 0.5rem;
        }
        
        .action-btn {
            padding: 0.5rem;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 0.875rem;
            text-decoration: none;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .action-btn.edit {
            background: #3498db;
            color: white;
        }
        
        .action-btn.delete {
            background: #e74c3c;
            color: white;
        }
        
        .action-btn.toggle {
            background: #f39c12;
            color: white;
        }
        
        .action-btn:hover {
            opacity: 0.8;
        }
        
        .bulk-actions {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 1rem 1.5rem;
            background: #f8f9fa;
            border-top: 1px solid #ecf0f1;
        }
        
        .bulk-actions select {
            min-width: 150px;
        }
        
        .pagination-container {
            margin-top: 2rem;
            display: flex;
            justify-content: center;
        }
        
        .no-products {
            text-align: center;
            padding: 4rem 2rem;
            color: #7f8c8d;
        }
        
        .quick-stock-edit {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .quick-stock-input {
            width: 60px;
            padding: 0.25rem;
            border: 1px solid #ddd;
            border-radius: 3px;
            text-align: center;
        }
        
        .quick-stock-btn {
            padding: 0.25rem 0.5rem;
            background: #27ae60;
            color: white;
            border: none;
            border-radius: 3px;
            cursor: pointer;
            font-size: 0.75rem;
        }
        
        @media (max-width: 768px) {
            .admin-layout {
                grid-template-columns: 1fr;
            }
            
            .admin-sidebar {
                display: none;
            }
            
            .filters-row {
                grid-template-columns: 1fr;
            }
            
            .products-table {
                overflow-x: auto;
            }
            
            .toolbar-actions {
                flex-direction: column;
                gap: 0.5rem;
            }
        }
    </style>
</head>
<body>
    <?php include '../../../includes/header.php'; ?>
    
    <div class="admin-layout">
        <!-- Admin Sidebar -->
        <aside class="admin-sidebar">
            <nav>
                <ul class="admin-nav">
                    <li class="admin-nav-item">
                        <a href="../index.php" class="admin-nav-link">
                            📊 Dashboard
                        </a>
                    </li>
                    <li class="admin-nav-item">
                        <a href="index.php" class="admin-nav-link active">
                            📦 Products
                        </a>
                    </li>
                    <li class="admin-nav-item">
                        <a href="../orders/index.php" class="admin-nav-link">
                            🛒 Orders
                        </a>
                    </li>
                    <li class="admin-nav-item">
                        <a href="../users/index.php" class="admin-nav-link">
                            👥 Users
                        </a>
                    </li>
                    <li class="admin-nav-item">
                        <a href="../reports/index.php" class="admin-nav-link">
                            📈 Reports
                        </a>
                    </li>
                    <li class="admin-nav-item">
                        <a href="../settings/index.php" class="admin-nav-link">
                            ⚙️ Settings
                        </a>
                    </li>
                </ul>
            </nav>
        </aside>
        
        <!-- Admin Content -->
        <main class="admin-content">
            <?php if (isset($_GET['message'])): ?>
                <div class="alert alert-success" style="margin-bottom: 1.5rem;">
                    <?= htmlspecialchars($_GET['message']) ?>
                </div>
            <?php endif; ?>
            
            <?php if (isset($_GET['error'])): ?>
                <div class="alert alert-error" style="margin-bottom: 1.5rem;">
                    <?= htmlspecialchars($_GET['error']) ?>
                </div>
            <?php endif; ?>
            
            <div class="products-header">
                <div>
                    <h1>Product Management</h1>
                    <p>Manage your inventory, stock levels, and product details</p>
                </div>
                <a href="add.php" class="btn btn-primary">
                    ➕ Add New Product
                </a>
            </div>
            
            <!-- Filters -->
            <div class="products-filters">
                <form method="GET" action="">
                    <div class="filters-row">
                        <div class="filter-group">
                            <label for="search">Search Products</label>
                            <input type="text" 
                                   id="search" 
                                   name="search" 
                                   placeholder="Search by name, description, or brand..."
                                   value="<?= htmlspecialchars($search) ?>">
                        </div>
                        
                        <div class="filter-group">
                            <label for="category">Category</label>
                            <select id="category" name="category">
                                <option value="">All Categories</option>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?= htmlspecialchars($cat['category']) ?>"
                                            <?= $category === $cat['category'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($cat['category']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="filter-group">
                            <label for="filter">Filter</label>
                            <select id="filter" name="filter">
                                <option value="" <?= $filter === '' ? 'selected' : '' ?>>All Products</option>
                                <option value="active" <?= $filter === 'active' ? 'selected' : '' ?>>Active Products</option>
                                <option value="inactive" <?= $filter === 'inactive' ? 'selected' : '' ?>>Inactive Products</option>
                                <option value="low_stock" <?= $filter === 'low_stock' ? 'selected' : '' ?>>Low Stock</option>
                                <option value="out_of_stock" <?= $filter === 'out_of_stock' ? 'selected' : '' ?>>Out of Stock</option>
                            </select>
                        </div>
                        
                        <div class="filter-group">
                            <label for="sort">Sort By</label>
                            <select id="sort" name="sort">
                                <option value="newest" <?= $sort === 'newest' ? 'selected' : '' ?>>Newest First</option>
                                <option value="oldest" <?= $sort === 'oldest' ? 'selected' : '' ?>>Oldest First</option>
                                <option value="name" <?= $sort === 'name' ? 'selected' : '' ?>>Name A-Z</option>
                                <option value="price_low" <?= $sort === 'price_low' ? 'selected' : '' ?>>Price: Low to High</option>
                                <option value="price_high" <?= $sort === 'price_high' ? 'selected' : '' ?>>Price: High to Low</option>
                                <option value="stock_low" <?= $sort === 'stock_low' ? 'selected' : '' ?>>Stock: Low to High</option>
                                <option value="stock_high" <?= $sort === 'stock_high' ? 'selected' : '' ?>>Stock: High to Low</option>
                            </select>
                        </div>
                        
                        <div class="filter-group">
                            <button type="submit" class="btn btn-secondary">Apply</button>
                        </div>
                    </div>
                </form>
            </div>
            
            <!-- Toolbar -->
            <div class="products-toolbar">
                <div class="toolbar-info">
                    Showing <?= $offset + 1 ?>-<?= min($offset + $per_page, $total_products) ?> 
                    of <?= number_format($total_products) ?> products
                </div>
                
                <div class="toolbar-actions">
                    <button type="button" class="btn btn-secondary" onclick="exportProducts()">
                        📊 Export CSV
                    </button>
                    <button type="button" class="btn btn-secondary" onclick="importProducts()">
                        📥 Import CSV
                    </button>
                </div>
            </div>
            
            <!-- Products Table -->
            
            <?php if (!empty($products)): ?>
                <div class="products-table">
                    <form id="bulk-form">
                        <table>
                            <thead>
                                <tr>
                                    <th>
                                        <input type="checkbox" id="select-all">
                                    </th>
                                    <th>Image</th>
                                    <th>Product</th>
                                    <th>Category</th>
                                    <th>Price</th>
                                    <th>Stock</th>
                                    <th>Status</th>
                                    <th>Created</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($products as $product): ?>
                                    <tr>
                                        <td>
                                            <input type="checkbox" 
                                                   name="selected_products[]" 
                                                   value="<?= $product['product_id'] ?>"
                                                   class="product-checkbox">
                                        </td>
                                        <td>
                                            <div class="product-image">
                                                <?php if ($product['image_url']): ?>
                                                    <img src="<?= htmlspecialchars($product['image_url']) ?>" 
                                                         alt="<?= htmlspecialchars($product['product_name']) ?>">
                                                <?php else: ?>
                                                    📷
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="product-info">
                                                <div class="product-name">
                                                    <a href="../../products/detail.php?id=<?= $product['product_id'] ?>" target="_blank">
                                                        <?= htmlspecialchars($product['product_name']) ?>
                                                    </a>
                                                </div>
                                                <div class="product-meta">
                                                    <?= htmlspecialchars($product['brand']) ?> • 
                                                    <?= ucfirst($product['condition_type'] ?? 'good') ?>
                                                </div>
                                            </div>
                                        </td>
                                        <td><?= htmlspecialchars($product['category']) ?></td>
                                        <td><?= formatCurrency($product['price']) ?></td>
                                        <td>
                                            <div class="quick-stock-edit">
                                                <input type="number" 
                                                       class="quick-stock-input" 
                                                       value="<?= $product['stock'] ?>"
                                                       min="0"
                                                       data-product-id="<?= $product['product_id'] ?>">
                                                <button type="button" 
                                                        class="quick-stock-btn"
                                                        onclick="updateStock(<?= $product['product_id'] ?>)">
                                                    ✓
                                                </button>
                                            </div>
                                            <div class="stock-level <?= 
                                                $product['stock'] == 0 ? 'out' : 
                                                ($product['stock'] <= 2 ? 'critical' : 
                                                ($product['stock'] <= 5 ? 'low' : 'good')) 
                                            ?>">
                                                <?php if ($product['stock'] == 0): ?>
                                                    Out of Stock
                                                <?php elseif ($product['stock'] <= 2): ?>
                                                    Critical
                                                <?php elseif ($product['stock'] <= 5): ?>
                                                    Low Stock
                                                <?php else: ?>
                                                    In Stock
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="product-status <?= $product['is_active'] ? 'active' : 'inactive' ?>">
                                                <?= $product['is_active'] ? 'Active' : 'Inactive' ?>
                                            </span>
                                        </td>
                                        <td><?= date('M j, Y', strtotime($product['created_at'])) ?></td>
                                        <td>
                                            <div class="product-actions">
                                                <a href="edit.php?id=<?= $product['product_id'] ?>" 
                                                   class="action-btn edit" 
                                                   title="Edit Product">
                                                    ✏️
                                                </a>
                                                <button type="button" 
                                                        class="action-btn toggle" 
                                                        title="<?= $product['is_active'] ? 'Deactivate' : 'Activate' ?> Product"
                                                        onclick="toggleProduct(<?= $product['product_id'] ?>)">
                                                    <?= $product['is_active'] ? '⏸️' : '▶️' ?>
                                                </button>
                                                <button type="button" 
                                                        class="action-btn delete" 
                                                        title="Delete Product"
                                                        onclick="deleteProduct(<?= $product['product_id'] ?>)">
                                                    🗑️
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        
                        <!-- Bulk Actions -->
                        <div class="bulk-actions">
                            <span>With selected:</span>
                            <select id="bulk-action">
                                <option value="">Choose action...</option>
                                <option value="activate">Activate</option>
                                <option value="deactivate">Deactivate</option>
                                <option value="delete">Delete</option>
                                <option value="export">Export</option>
                            </select>
                            <button type="button" class="btn btn-secondary" onclick="applyBulkAction()">
                                Apply
                            </button>
                        </div>
                    </form>
                </div>
                
                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                    <div class="pagination-container">
                        <?= generatePagination($page, $total_pages, $_GET) ?>
                    </div>
                <?php endif; ?>
                
            <?php else: ?>
                <!-- No Products -->
                <div class="no-products">
                    <div style="font-size: 4rem; margin-bottom: 1rem;">📦</div>
                    <h2>No products found</h2>
                    <p>No products match your current filters.</p>
                    <a href="add.php" class="btn btn-primary">Add Your First Product</a>
                </div>
            <?php endif; ?>
        </main>
    </div>
    
    <?php include '../../../includes/footer.php'; ?>
    
    <script src="../../../assets/js/main.js"></script>
    <script>
        // Select all checkbox functionality
        document.getElementById('select-all').addEventListener('change', function() {
            const checkboxes = document.querySelectorAll('.product-checkbox');
            checkboxes.forEach(checkbox => {
                checkbox.checked = this.checked;
            });
        });
        
        // Update individual stock
        function updateStock(productId) {
            const input = document.querySelector(`input[data-product-id="${productId}"]`);
            const newStock = parseInt(input.value);
            
            if (isNaN(newStock) || newStock < 0) {
                alert('Please enter a valid stock quantity');
                return;
            }
            
            fetch('../../../api/admin.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({
                    action: 'update_stock',
                    product_id: productId,
                    stock: newStock
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    EVinty.showMessage('Stock updated successfully', 'success');
                    setTimeout(() => window.location.reload(), 1000);
                } else {
                    EVinty.showMessage(data.message || 'Failed to update stock', 'error');
                }
            })
            .catch(error => {
                console.error('Stock update error:', error);
                EVinty.showMessage('Network error occurred', 'error');
            });
        }
        
        // Toggle product status
        function toggleProduct(productId) {
            fetch('../../../api/admin.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({
                    action: 'toggle_product',
                    product_id: productId
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    EVinty.showMessage('Product status updated', 'success');
                    setTimeout(() => window.location.reload(), 1000);
                } else {
                    EVinty.showMessage(data.message || 'Failed to update product', 'error');
                }
            })
            .catch(error => {
                console.error('Toggle product error:', error);
                EVinty.showMessage('Network error occurred', 'error');
            });
        }
        
        // Delete product
        function deleteProduct(productId) {
            if (!confirm('Are you sure you want to delete this product? This action cannot be undone.')) {
                return;
            }
            
            fetch('../../../api/admin.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({
                    action: 'delete_product',
                    product_id: productId
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    EVinty.showMessage('Product deleted successfully', 'success');
                    setTimeout(() => window.location.reload(), 1000);
                } else {
                    EVinty.showMessage(data.message || 'Failed to delete product', 'error');
                }
            })
            .catch(error => {
                console.error('Delete product error:', error);
                EVinty.showMessage('Network error occurred', 'error');
            });
        }
        
        // Apply bulk actions
        function applyBulkAction() {
            const action = document.getElementById('bulk-action').value;
            const selected = Array.from(document.querySelectorAll('.product-checkbox:checked'))
                .map(cb => cb.value);
            
            if (!action) {
                alert('Please select an action');
                return;
            }
            
            if (selected.length === 0) {
                alert('Please select at least one product');
                return;
            }
            
            if (action === 'delete' && !confirm(`Are you sure you want to delete ${selected.length} product(s)?`)) {
                return;
            }
            
            fetch('../../../api/admin.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({
                    action: 'bulk_action',
                    bulk_action: action,
                    product_ids: selected
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    EVinty.showMessage(`Bulk action completed for ${selected.length} product(s)`, 'success');
                    setTimeout(() => window.location.reload(), 1000);
                } else {
                    EVinty.showMessage(data.message || 'Failed to complete bulk action', 'error');
                }
            })
            .catch(error => {
                console.error('Bulk action error:', error);
                EVinty.showMessage('Network error occurred', 'error');
            });
        }
        
        // Export/Import functions
        function exportProducts() {
            window.location.href = '../../../api/admin.php?action=export_products';
        }
        
        function importProducts() {
            // Create file input for CSV import
            const input = document.createElement('input');
            input.type = 'file';
            input.accept = '.csv';
            input.onchange = function(event) {
                const file = event.target.files[0];
                if (file) {
                    const formData = new FormData();
                    formData.append('csv_file', file);
                    formData.append('action', 'import_products');
                    
                    fetch('../../../api/admin.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            EVinty.showMessage('Products imported successfully', 'success');
                            setTimeout(() => window.location.reload(), 1000);
                        } else {
                            EVinty.showMessage(data.message || 'Failed to import products', 'error');
                        }
                    })
                    .catch(error => {
                        console.error('Import error:', error);
                        EVinty.showMessage('Network error occurred', 'error');
                    });
                }
            };
            input.click();
        }
    </script>
</body>
</html>
