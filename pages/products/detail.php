<?php
/**
 * Product Detail Page
 * Implements F106 - Product catalogs
 * Story 304: View product details with photos and descriptions
 */

require_once '../../includes/init.php';

$product_id = intval($_GET['id'] ?? 0);
$product = null;
$related_products = [];

if ($product_id <= 0) {
    redirectWithMessage(SITE_URL . '/pages/products/catalog.php', 'Invalid product ID.', 'error');
}

try {
    // Get product details
    $product = $db->fetch(
        "SELECT p.*, 
                CASE WHEN p.stock > 0 THEN 1 ELSE 0 END as in_stock
         FROM products p 
         WHERE p.product_id = ? AND p.is_active = 1",
        [$product_id]
    );
    
    if (!$product) {
        redirectWithMessage(SITE_URL . '/pages/products/catalog.php', 'Product not found.', 'error');
    }
    
    // Get related products (same category, excluding current product)
    $related_products = $db->fetchAll(
        "SELECT p.*, 
                CASE WHEN p.stock > 0 THEN 1 ELSE 0 END as in_stock
         FROM products p 
         WHERE p.category = ? AND p.product_id != ? AND p.is_active = 1 
         ORDER BY RAND() 
         LIMIT 4",
        [$product['category'], $product_id]
    );
    
} catch (Exception $e) {
    error_log("Product detail error: " . $e->getMessage());
    redirectWithMessage(SITE_URL . '/pages/products/catalog.php', 'Error loading product details.', 'error');
}

$page_title = htmlspecialchars($product['product_name']) . " - Again&Co";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $page_title ?></title>
    <meta name="description" content="<?= htmlspecialchars(substr($product['description'], 0, 160)) ?>">
    <link rel="stylesheet" href="../../assets/css/style.css">
    <style>
        .product-detail {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 3rem;
            margin-bottom: 3rem;
        }
        
        .product-image-section {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }
        
        .main-product-image {
            width: 100%;
            aspect-ratio: 1;
            background-color: #f8f9fa;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 4rem;
            color: #bdc3c7;
            overflow: hidden;
        }
        
        .main-product-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .product-info-section {
            display: flex;
            flex-direction: column;
            gap: 1.5rem;
        }
        
        .product-title {
            font-size: 2rem;
            font-weight: bold;
            color: #2c3e50;
            margin: 0;
        }
        
        .product-price {
            font-size: 2.5rem;
            font-weight: bold;
            color: #27ae60;
        }
        
        .product-meta {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1rem;
            padding: 1.5rem;
            background-color: #f8f9fa;
            border-radius: 10px;
        }
        
        .meta-item {
            display: flex;
            flex-direction: column;
        }
        
        .meta-label {
            font-weight: 600;
            color: #7f8c8d;
            font-size: 0.875rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .meta-value {
            color: #2c3e50;
            font-weight: 500;
            margin-top: 0.25rem;
        }
        
        .stock-status {
            padding: 0.5rem 1rem;
            border-radius: 25px;
            font-weight: 600;
            text-align: center;
            display: inline-block;
            width: fit-content;
        }
        
        .in-stock {
            background-color: #d4edda;
            color: #155724;
        }
        
        .out-of-stock {
            background-color: #f8d7da;
            color: #721c24;
        }
        
        .quantity-section {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin: 1rem 0;
        }
        
        .quantity-control {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .quantity-btn {
            width: 40px;
            height: 40px;
            border: 2px solid #e0e0e0;
            background: white;
            border-radius: 5px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
            font-weight: bold;
        }
        
        .quantity-btn:hover {
            background-color: #f8f9fa;
            border-color: #3498db;
        }
        
        .quantity-input {
            width: 80px;
            text-align: center;
            border: 2px solid #e0e0e0;
            border-radius: 5px;
            padding: 0.5rem;
            font-size: 1.125rem;
        }
        
        .add-to-cart-section {
            display: flex;
            gap: 1rem;
            margin-top: 1rem;
        }
        
        .product-description {
            line-height: 1.8;
            color: #34495e;
        }
        
        .related-products {
            margin-top: 4rem;
        }
        
        .breadcrumb {
            margin-bottom: 2rem;
            font-size: 0.875rem;
            color: #7f8c8d;
        }
        
        .breadcrumb a {
            color: #3498db;
            text-decoration: none;
        }
        
        .breadcrumb a:hover {
            text-decoration: underline;
        }
        
        @media (max-width: 768px) {
            .product-detail {
                grid-template-columns: 1fr;
                gap: 2rem;
            }
            
            .product-meta {
                grid-template-columns: 1fr;
            }
            
            .add-to-cart-section {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <?php include '../../includes/header.php'; ?>
    
    <main class="main-content">
        <div class="container">
            <!-- Breadcrumb -->
            <div class="breadcrumb">
                <a href="<?= SITE_URL ?>">Home</a> > 
                <a href="catalog.php">Products</a> > 
                <a href="catalog.php?category=<?= urlencode($product['category']) ?>"><?= htmlspecialchars($product['category']) ?></a> > 
                <?= htmlspecialchars($product['product_name']) ?>
            </div>
            
            <?php
            $flash = getFlashMessage();
            if ($flash): ?>
                <div class="alert alert-<?= $flash['type'] ?>">
                    <?= htmlspecialchars($flash['message']) ?>
                </div>
            <?php endif; ?>
            
            <div class="card">
                <div class="product-detail">
                    <!-- Product Images -->
                    <div class="product-image-section">
                        <div class="main-product-image">
                            <?php if ($product['image_url']): ?>
                                <img src="<?= htmlspecialchars($product['image_url']) ?>" 
                                     alt="<?= htmlspecialchars($product['product_name']) ?>">
                            <?php else: ?>
                                📷
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Product Information -->
                    <div class="product-info-section">
                        <h1 class="product-title"><?= htmlspecialchars($product['product_name']) ?></h1>
                        
                        <div class="product-price">
                            <?= formatCurrency($product['price']) ?>
                        </div>
                        
                        <!-- Stock Status -->
                        <div class="stock-status <?= $product['in_stock'] ? 'in-stock' : 'out-of-stock' ?>">
                            <?php if ($product['in_stock']): ?>
                                ✓ In Stock (<?= $product['stock'] ?> available)
                            <?php else: ?>
                                ✗ Out of Stock
                            <?php endif; ?>
                        </div>
                        
                        <!-- Product Meta Information -->
                        <div class="product-meta">
                            <div class="meta-item">
                                <span class="meta-label">Brand</span>
                                <span class="meta-value"><?= htmlspecialchars($product['brand']) ?></span>
                            </div>
                            <div class="meta-item">
                                <span class="meta-label">Category</span>
                                <span class="meta-value"><?= htmlspecialchars($product['category']) ?></span>
                            </div>
                            <div class="meta-item">
                                <span class="meta-label">Condition</span>
                                <span class="meta-value"><?= ucfirst(str_replace('_', ' ', $product['condition_type'])) ?></span>
                            </div>
                            <div class="meta-item">
                                <span class="meta-label">Added</span>
                                <span class="meta-value"><?= formatDate($product['created_at'], 'M j, Y') ?></span>
                            </div>
                        </div>
                        
                        <!-- Add to Cart Section -->
                        <?php if (isLoggedIn() && $product['in_stock']): ?>
                            <div class="quantity-section">
                                <label class="filter-label">Quantity:</label>
                                <div class="quantity-control">
                                    <button type="button" class="quantity-btn quantity-decrease">−</button>
                                    <input type="number" 
                                           id="quantity" 
                                           class="quantity-input" 
                                           value="1" 
                                           min="1" 
                                           max="<?= $product['stock'] ?>">
                                    <button type="button" class="quantity-btn quantity-increase">+</button>
                                </div>
                            </div>
                            
                            <div class="add-to-cart-section">
                                <button class="btn btn-primary btn-large add-to-cart" 
                                        data-product-id="<?= $product['product_id'] ?>">
                                    Add to Cart
                                </button>
                                <button class="btn btn-secondary add-to-wishlist" 
                                        data-product-id="<?= $product['product_id'] ?>">
                                    ♡ Save for Later
                                </button>
                            </div>
                        <?php elseif (!isLoggedIn()): ?>
                            <div class="login-prompt">
                                <p>Please <a href="../auth/login.php?redirect=<?= urlencode($_SERVER['REQUEST_URI']) ?>">log in</a> to add items to your cart.</p>
                                <a href="../auth/login.php?redirect=<?= urlencode($_SERVER['REQUEST_URI']) ?>" class="btn btn-primary">
                                    Log In to Purchase
                                </a>
                            </div>
                        <?php else: ?>
                            <div class="out-of-stock-message">
                                <p>This item is currently out of stock. Check back soon!</p>
                                <button class="btn btn-secondary" disabled>Notify When Available</button>
                            </div>
                        <?php endif; ?>
                        
                        <!-- Product Description -->
                        <div class="product-description">
                            <h3>Description</h3>
                            <p><?= nl2br(htmlspecialchars($product['description'])) ?></p>
                        </div>
                        
                        <!-- Additional Product Details -->
                        <div class="additional-details">
                            <h3>Product Details</h3>
                            <ul>
                                <li><strong>Product ID:</strong> #<?= $product['product_id'] ?></li>
                                <li><strong>Brand:</strong> <?= htmlspecialchars($product['brand']) ?></li>
                                <li><strong>Condition:</strong> <?= ucfirst(str_replace('_', ' ', $product['condition_type'])) ?></li>
                                <li><strong>Category:</strong> <?= htmlspecialchars($product['category']) ?></li>
                                <?php if ($product['release_date']): ?>
                                    <li><strong>Original Release:</strong> <?= formatDate($product['release_date'], 'Y') ?></li>
                                <?php endif; ?>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Related Products -->
            <?php if (!empty($related_products)): ?>
                <div class="related-products">
                    <div class="card">
                        <div class="card-header">
                            <h2 class="card-title">You might also like</h2>
                            <p>More items from the <?= htmlspecialchars($product['category']) ?> category</p>
                        </div>
                        
                        <div class="product-grid">
                            <?php foreach ($related_products as $related): ?>
                                <div class="product-card">
                                    <div class="product-image">
                                        <?php if ($related['image_url']): ?>
                                            <img src="<?= htmlspecialchars($related['image_url']) ?>" 
                                                 alt="<?= htmlspecialchars($related['product_name']) ?>"
                                                 loading="lazy">
                                        <?php else: ?>
                                            📷
                                        <?php endif; ?>
                                    </div>
                                    
                                    <div class="product-info">
                                        <h3 class="product-title">
                                            <a href="detail.php?id=<?= $related['product_id'] ?>">
                                                <?= htmlspecialchars($related['product_name']) ?>
                                            </a>
                                        </h3>
                                        
                                        <div class="product-price">
                                            <?= formatCurrency($related['price']) ?>
                                        </div>
                                        
                                        <div class="product-meta">
                                            <span class="product-condition"><?= ucfirst(str_replace('_', ' ', $related['condition_type'])) ?></span>
                                        </div>
                                        
                                        <div class="product-actions mt-2">
                                            <a href="detail.php?id=<?= $related['product_id'] ?>" 
                                               class="btn btn-primary">View Details</a>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <div class="text-center mt-3">
                            <a href="catalog.php?category=<?= urlencode($product['category']) ?>" 
                               class="btn btn-secondary">
                                View All <?= htmlspecialchars($product['category']) ?> Items
                            </a>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </main>
    
    <?php include '../../includes/footer.php'; ?>
    
    <script src="../../assets/js/main.js"></script>
    <script>
        // Quantity controls
        document.addEventListener('click', function(event) {
            const quantityInput = document.getElementById('quantity');
            const maxQuantity = parseInt(quantityInput.getAttribute('max'));
            
            if (event.target.classList.contains('quantity-decrease')) {
                const currentValue = parseInt(quantityInput.value);
                if (currentValue > 1) {
                    quantityInput.value = currentValue - 1;
                }
            }
            
            if (event.target.classList.contains('quantity-increase')) {
                const currentValue = parseInt(quantityInput.value);
                if (currentValue < maxQuantity) {
                    quantityInput.value = currentValue + 1;
                }
            }
            
            // Add to cart with quantity
            if (event.target.classList.contains('add-to-cart')) {
                event.preventDefault();
                const productId = event.target.dataset.productId;
                const quantity = parseInt(quantityInput ? quantityInput.value : 1);
                
                fetch('../../api/cart.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: JSON.stringify({
                        action: 'add',
                        product_id: productId,
                        quantity: quantity
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        EVinty.showMessage(`Added ${quantity} item(s) to cart!`, 'success');
                        EVinty.updateCartDisplay(data.cart);
                    } else {
                        EVinty.showMessage(data.message || 'Failed to add product to cart', 'error');
                    }
                })
                .catch(error => {
                    console.error('Add to cart error:', error);
                    EVinty.showMessage('Network error occurred', 'error');
                });
            }
            
            // Wishlist functionality (placeholder)
            if (event.target.classList.contains('add-to-wishlist')) {
                event.preventDefault();
                EVinty.showMessage('Wishlist feature coming soon!', 'info');
            }
        });
        
        // Validate quantity input
        document.getElementById('quantity')?.addEventListener('input', function(event) {
            const value = parseInt(event.target.value);
            const min = parseInt(event.target.getAttribute('min'));
            const max = parseInt(event.target.getAttribute('max'));
            
            if (value < min) {
                event.target.value = min;
            } else if (value > max) {
                event.target.value = max;
            }
        });
    </script>
</body>
</html>
