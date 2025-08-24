<?php
/**
 * E-Vinty Homepage
 * Main landing page for the e-commerce website
 */

require_once 'includes/init.php';

// Get featured products
try {
    $featured_products = $db->fetchAll(
        "SELECT * FROM products WHERE is_active = 1 ORDER BY created_at DESC LIMIT 8"
    );
} catch (Exception $e) {
    $featured_products = [];
    error_log("Failed to fetch featured products: " . $e->getMessage());
}

$page_title = "Welcome to E-Vinty - Again&Co Vintage Collection";
$page_description = "Discover unique vintage items at E-Vinty. Quality pre-owned fashion, accessories, and collectibles from Again&Co.";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $page_title ?></title>
    <meta name="description" content="<?= $page_description ?>">
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="icon" type="image/x-icon" href="assets/images/favicon.ico">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <main class="main-content">
        <div class="container">
            <?php
            $flash = getFlashMessage();
            if ($flash): ?>
                <div class="alert alert-<?= $flash['type'] ?>">
                    <?= htmlspecialchars($flash['message']) ?>
                </div>
            <?php endif; ?>
            
            <!-- Hero Section -->
            <section class="hero-section">
                <div class="card">
                    <div class="hero-content text-center">
                        <h1 class="hero-title">Welcome to E-Vinty</h1>
                        <p class="hero-subtitle">Discover unique vintage treasures from Again&Co</p>
                        <p class="hero-description">
                            Quality pre-owned fashion, accessories, and collectibles. 
                            Each piece tells a story and brings vintage charm to your collection.
                        </p>
                        <div class="hero-actions mt-3">
                            <a href="pages/products/catalog.php" class="btn btn-primary">Shop Now</a>
                            <?php if (!isLoggedIn()): ?>
                                <a href="pages/auth/register.php" class="btn btn-secondary">Join Today</a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </section>
            
            <!-- Featured Products -->
            <section class="featured-products mt-4">
                <div class="card">
                    <div class="card-header">
                        <h2 class="card-title">Featured Products</h2>
                        <p>Handpicked vintage items from our collection</p>
                    </div>
                    
                    <?php if (!empty($featured_products)): ?>
                        <div class="product-grid">
                            <?php foreach ($featured_products as $product): ?>
                                <div class="product-card">
                                    <div class="product-image">
                                        <?php if ($product['image_url']): ?>
                                            <img src="<?= htmlspecialchars($product['image_url']) ?>" 
                                                 alt="<?= htmlspecialchars($product['product_name']) ?>"
                                                 loading="lazy">
                                        <?php else: ?>
                                            📷
                                        <?php endif; ?>
                                    </div>
                                    <div class="product-info">
                                        <h3 class="product-title">
                                            <?= htmlspecialchars($product['product_name']) ?>
                                        </h3>
                                        <div class="product-price">
                                            <?= formatCurrency($product['price']) ?>
                                        </div>
                                        <div class="product-meta">
                                            <span class="product-category"><?= htmlspecialchars($product['category']) ?></span>
                                            <span class="product-condition"><?= ucfirst($product['condition_type']) ?></span>
                                        </div>
                                        <p class="product-description">
                                            <?= htmlspecialchars(substr($product['description'], 0, 100)) ?>
                                            <?= strlen($product['description']) > 100 ? '...' : '' ?>
                                        </p>
                                        <div class="product-actions mt-2">
                                            <a href="pages/products/detail.php?id=<?= $product['product_id'] ?>" 
                                               class="btn btn-primary">View Details</a>
                                            <?php if (isLoggedIn()): ?>
                                                <button class="btn btn-secondary add-to-cart" 
                                                        data-product-id="<?= $product['product_id'] ?>">
                                                    Add to Cart
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="text-center mt-3">
                            <p>No products available at the moment. Please check back soon!</p>
                        </div>
                    <?php endif; ?>
                    
                    <div class="text-center mt-3">
                        <a href="pages/products/catalog.php" class="btn btn-primary">View All Products</a>
                    </div>
                </div>
            </section>
            
            <!-- Categories -->
            <section class="categories mt-4">
                <div class="card">
                    <div class="card-header">
                        <h2 class="card-title">Shop by Category</h2>
                        <p>Browse our curated vintage collections</p>
                    </div>
                    
                    <div class="category-grid">
                        <div class="category-card">
                            <div class="category-icon">👔</div>
                            <h3>Clothing</h3>
                            <p>Vintage jackets, shirts, and more</p>
                            <a href="pages/products/catalog.php?category=Clothing" class="btn btn-secondary">Browse</a>
                        </div>
                        
                        <div class="category-card">
                            <div class="category-icon">👜</div>
                            <h3>Accessories</h3>
                            <p>Bags, jewelry, and unique finds</p>
                            <a href="pages/products/catalog.php?category=Accessories" class="btn btn-secondary">Browse</a>
                        </div>
                        
                        <div class="category-card">
                            <div class="category-icon">🎵</div>
                            <h3>Music</h3>
                            <p>Vinyl records and music memorabilia</p>
                            <a href="pages/products/catalog.php?category=Music" class="btn btn-secondary">Browse</a>
                        </div>
                        
                        <div class="category-card">
                            <div class="category-icon">🏠</div>
                            <h3>Home & Decor</h3>
                            <p>Vintage furniture and decor items</p>
                            <a href="pages/products/catalog.php?category=Home" class="btn btn-secondary">Browse</a>
                        </div>
                    </div>
                </div>
            </section>
            
            <!-- About Section -->
            <section class="about-section mt-4">
                <div class="card">
                    <div class="about-content">
                        <div class="about-text">
                            <h2>About Again&Co</h2>
                            <p>
                                At Again&Co, we believe that every vintage item has a story to tell. 
                                Our carefully curated collection features unique pieces that combine 
                                quality craftsmanship with timeless style.
                            </p>
                            <p>
                                Whether you're looking for a statement piece or adding to your 
                                vintage collection, we have something special waiting for you.
                            </p>
                            <div class="about-features mt-3">
                                <div class="feature">
                                    <span class="feature-icon">✅</span>
                                    <span>Quality Guaranteed</span>
                                </div>
                                <div class="feature">
                                    <span class="feature-icon">🚚</span>
                                    <span>Fast Shipping</span>
                                </div>
                                <div class="feature">
                                    <span class="feature-icon">💳</span>
                                    <span>Secure Payment</span>
                                </div>
                                <div class="feature">
                                    <span class="feature-icon">🔄</span>
                                    <span>Easy Returns</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>
        </div>
    </main>
    
    <?php include 'includes/footer.php'; ?>
    
    <script src="assets/js/main.js"></script>
    <script>
        // Add to cart functionality
        document.addEventListener('click', function(event) {
            if (event.target.classList.contains('add-to-cart')) {
                event.preventDefault();
                const productId = event.target.dataset.productId;
                
                fetch('api/cart.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: JSON.stringify({
                        action: 'add',
                        product_id: productId,
                        quantity: 1
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        EVinty.showMessage('Product added to cart!', 'success');
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
        });
    </script>
</body>
</html>
