<?php
/**
 * Add New Product
 * Staff/Admin can add products to inventory
 */

require_once '../../../includes/init.php';

// Require admin/staff access
requireLogin();
requireRole(['admin', 'staff']);

$user = getCurrentUser();
$message = '';
$error = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $product_name = trim($_POST['product_name']);
        $description = trim($_POST['description']);
        $price = (float)$_POST['price'];
        $stock = (int)$_POST['stock'];
        $category = trim($_POST['category']);
        $brand = trim($_POST['brand']);
        $condition_type = $_POST['condition_type'];
        $image_url = trim($_POST['image_url']);
        $release_date = $_POST['release_date'] ?: null;
        
        // Validation
        if (empty($product_name)) {
            throw new Exception("Product name is required");
        }
        
        if ($price <= 0) {
            throw new Exception("Price must be greater than 0");
        }
        
        if ($stock < 0) {
            throw new Exception("Stock cannot be negative");
        }
        
                // Insert product
                $db->query(
                    "INSERT INTO products (product_name, description, price, stock, category, brand, condition_type, image_url, release_date, is_active) 
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1)",
                    [$product_name, $description, $price, $stock, $category, $brand, $condition_type, $image_url, $release_date]
                );        $message = "Product added successfully!";
        
        // Redirect to product list after success
        header("Location: index.php?message=" . urlencode($message));
        exit;
        
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Get existing categories for dropdown
$categories = $db->fetchAll("SELECT DISTINCT category FROM products WHERE category IS NOT NULL ORDER BY category");

$page_title = "Add Product - Again&Co";
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
        
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
        }
        
        .page-title {
            margin: 0;
            color: #2c3e50;
        }
        
        .form-container {
            background: white;
            padding: 2rem;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            max-width: 800px;
        }
        
        .form-grid {
            display: grid;
            gap: 1.5rem;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1.5rem;
        }
        
        .form-group {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }
        
        .form-group.full-width {
            grid-column: 1 / -1;
        }
        
        .form-group label {
            font-weight: 600;
            color: #2c3e50;
        }
        
        .form-control {
            padding: 0.75rem;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 0.875rem;
            transition: border-color 0.3s ease;
        }
        
        .form-control:focus {
            outline: none;
            border-color: #3498db;
            box-shadow: 0 0 0 2px rgba(52, 152, 219, 0.2);
        }
        
        .form-control.textarea {
            resize: vertical;
            min-height: 100px;
        }
        
        .btn {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 5px;
            font-weight: 600;
            text-decoration: none;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .btn-primary {
            background: #3498db;
            color: white;
        }
        
        .btn-primary:hover {
            background: #2980b9;
        }
        
        .btn-secondary {
            background: #95a5a6;
            color: white;
        }
        
        .btn-secondary:hover {
            background: #7f8c8d;
        }
        
        .btn-success {
            background: #27ae60;
            color: white;
        }
        
        .btn-success:hover {
            background: #229954;
        }
        
        .alert {
            padding: 1rem;
            border-radius: 5px;
            margin-bottom: 1.5rem;
        }
        
        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .alert-danger {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .form-actions {
            display: flex;
            gap: 1rem;
            justify-content: flex-end;
            margin-top: 2rem;
            padding-top: 2rem;
            border-top: 1px solid #ecf0f1;
        }
        
        .price-input {
            position: relative;
        }
        
        .price-input::before {
            content: '$';
            position: absolute;
            left: 0.75rem;
            top: 50%;
            transform: translateY(-50%);
            color: #7f8c8d;
            font-weight: 600;
        }
        
        .price-input input {
            padding-left: 2rem;
        }
        
        @media (max-width: 768px) {
            .admin-layout {
                grid-template-columns: 1fr;
            }
            
            .admin-sidebar {
                display: none;
            }
            
            .form-row {
                grid-template-columns: 1fr;
            }
            
            .form-actions {
                flex-direction: column;
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
                    <?php if ($user['role'] === 'admin'): ?>
                        <li class="admin-nav-item">
                            <a href="../users/index.php" class="admin-nav-link">
                                👥 Users
                            </a>
                        </li>
                    <?php endif; ?>
                </ul>
            </nav>
        </aside>
        
        <!-- Main Content -->
        <main class="admin-content">
            <div class="page-header">
                <h1 class="page-title">Add New Product</h1>
                <a href="index.php" class="btn btn-secondary">← Back to Products</a>
            </div>
            
            <?php if ($message): ?>
                <div class="alert alert-success"><?= htmlspecialchars($message) ?></div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
            
            <div class="form-container">
                <form method="POST" class="form-grid">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="product_name">Product Name *</label>
                            <input type="text" id="product_name" name="product_name" required 
                                   value="<?= htmlspecialchars($_POST['product_name'] ?? '') ?>" 
                                   class="form-control" placeholder="Enter product name">
                        </div>
                        
                        <div class="form-group">
                            <label for="brand">Brand</label>
                            <input type="text" id="brand" name="brand" 
                                   value="<?= htmlspecialchars($_POST['brand'] ?? '') ?>" 
                                   class="form-control" placeholder="Enter brand name">
                        </div>
                    </div>
                    
                    <div class="form-group full-width">
                        <label for="description">Description</label>
                        <textarea id="description" name="description" class="form-control textarea" 
                                  placeholder="Enter product description"><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="price">Price *</label>
                            <div class="price-input">
                                <input type="number" id="price" name="price" step="0.01" min="0" required 
                                       value="<?= htmlspecialchars($_POST['price'] ?? '') ?>" 
                                       class="form-control" placeholder="0.00">
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="stock">Stock Quantity *</label>
                            <input type="number" id="stock" name="stock" min="0" required 
                                   value="<?= htmlspecialchars($_POST['stock'] ?? '') ?>" 
                                   class="form-control" placeholder="0">
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="category">Category</label>
                            <input type="text" id="category" name="category" 
                                   value="<?= htmlspecialchars($_POST['category'] ?? '') ?>" 
                                   class="form-control" placeholder="Enter category" list="categories">
                            <datalist id="categories">
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?= htmlspecialchars($cat['category']) ?>">
                                <?php endforeach; ?>
                            </datalist>
                        </div>
                        
                        <div class="form-group">
                            <label for="condition_type">Condition</label>
                            <select id="condition_type" name="condition_type" class="form-control">
                                <option value="new" <?= ($_POST['condition_type'] ?? '') === 'new' ? 'selected' : '' ?>>New</option>
                                <option value="like_new" <?= ($_POST['condition_type'] ?? '') === 'like_new' ? 'selected' : '' ?>>Like New</option>
                                <option value="good" <?= ($_POST['condition_type'] ?? 'good') === 'good' ? 'selected' : '' ?>>Good</option>
                                <option value="fair" <?= ($_POST['condition_type'] ?? '') === 'fair' ? 'selected' : '' ?>>Fair</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="image_url">Image URL</label>
                            <input type="url" id="image_url" name="image_url" 
                                   value="<?= htmlspecialchars($_POST['image_url'] ?? '') ?>" 
                                   class="form-control" placeholder="https://example.com/image.jpg">
                        </div>
                        
                        <div class="form-group">
                            <label for="release_date">Release Date</label>
                            <input type="date" id="release_date" name="release_date" 
                                   value="<?= htmlspecialchars($_POST['release_date'] ?? '') ?>" 
                                   class="form-control">
                        </div>
                    </div>
                    
                    <div class="form-actions">
                        <a href="index.php" class="btn btn-secondary">Cancel</a>
                        <button type="submit" class="btn btn-success">📦 Add Product</button>
                    </div>
                </form>
            </div>
        </main>
    </div>
    
    <?php include '../../../includes/footer.php'; ?>
</body>
</html>