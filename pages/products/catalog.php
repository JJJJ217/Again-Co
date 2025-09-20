<?php
/**
 * Product Catalog Page
 * Implements F104 - Product Filtering
 * Stories 301-307: Product browsing, filtering, and search
 */

require_once '../../includes/init.php';

// Get filter parameters
$filters = [
    'category' => sanitizeInput($_GET['category'] ?? ''),
    'brand' => sanitizeInput($_GET['brand'] ?? ''),
    'condition' => sanitizeInput($_GET['condition'] ?? ''),
    'min_price' => floatval($_GET['min_price'] ?? 0),
    'max_price' => floatval($_GET['max_price'] ?? 0),
    'search' => sanitizeInput($_GET['search'] ?? $_GET['q'] ?? ''), // Handle both 'search' and 'q' parameters
    'sort' => sanitizeInput($_GET['sort'] ?? 'newest'),
    'page' => intval($_GET['page'] ?? 1)
];

// Pagination settings
$products_per_page = PRODUCTS_PER_PAGE;
$offset = ($filters['page'] - 1) * $products_per_page;

// Build WHERE clause for filtering
$where_conditions = ['p.is_active = 1'];
$params = [];

if (!empty($filters['category'])) {
    $where_conditions[] = 'p.category = ?';
    $params[] = $filters['category'];
}

if (!empty($filters['brand'])) {
    $where_conditions[] = 'p.brand = ?';
    $params[] = $filters['brand'];
}

if (!empty($filters['condition'])) {
    $where_conditions[] = 'p.condition_type = ?';
    $params[] = $filters['condition'];
}

if ($filters['min_price'] > 0) {
    $where_conditions[] = 'p.price >= ?';
    $params[] = $filters['min_price'];
}

if ($filters['max_price'] > 0) {
    $where_conditions[] = 'p.price <= ?';
    $params[] = $filters['max_price'];
}

if (!empty($filters['search'])) {
    $where_conditions[] = '(p.product_name LIKE ? OR p.description LIKE ?)';
    $search_term = '%' . $filters['search'] . '%';
    $params[] = $search_term;
    $params[] = $search_term;
}

$where_clause = implode(' AND ', $where_conditions);

// Build ORDER BY clause
$order_by = 'p.created_at DESC'; // Default: newest first
switch ($filters['sort']) {
    case 'price_low':
        $order_by = 'p.price ASC';
        break;
    case 'price_high':
        $order_by = 'p.price DESC';
        break;
    case 'name':
        $order_by = 'p.product_name ASC';
        break;
    case 'oldest':
        $order_by = 'p.created_at ASC';
        break;
    case 'newest':
    default:
        $order_by = 'p.created_at DESC';
        break;
}

try {
    // Get total count for pagination
    $count_sql = "SELECT COUNT(*) as total FROM products p WHERE $where_clause";
    $total_result = $db->fetch($count_sql, $params);
    $total_products = $total_result['total'];
    $total_pages = ceil($total_products / $products_per_page);
    
    // Get products for current page
    $products_sql = "
        SELECT p.*, 
               CASE WHEN p.stock > 0 THEN 1 ELSE 0 END as in_stock
        FROM products p 
        WHERE $where_clause 
        ORDER BY $order_by 
        LIMIT $products_per_page OFFSET $offset
    ";
    
    $products = $db->fetchAll($products_sql, $params);
    
    // Get available filter options
    $categories = $db->fetchAll("SELECT DISTINCT category FROM products WHERE is_active = 1 AND category IS NOT NULL ORDER BY category");
    $brands = $db->fetchAll("SELECT DISTINCT brand FROM products WHERE is_active = 1 AND brand IS NOT NULL ORDER BY brand");
    $conditions = $db->fetchAll("SELECT DISTINCT condition_type FROM products WHERE is_active = 1 ORDER BY condition_type");
    
    // Get price range
    $price_range = $db->fetch("SELECT MIN(price) as min_price, MAX(price) as max_price FROM products WHERE is_active = 1");
    
} catch (Exception $e) {
    error_log("Product catalog error: " . $e->getMessage());
    $products = [];
    $total_products = 0;
    $total_pages = 0;
    $categories = [];
    $brands = [];
    $conditions = [];
    $price_range = ['min_price' => 0, 'max_price' => 1000];
}

$page_title = "Product Catalog - Again&Co";
if (!empty($filters['category'])) {
    $page_title = ucfirst($filters['category']) . " - Again&Co";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $page_title ?></title>
    <link rel="stylesheet" href="../../assets/css/style.css">
    <style>
        .filters-container {
            background: white;
            padding: 1.5rem;
            border-radius: 10px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            margin-bottom: 2rem;
        }
        
        .filter-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 1rem;
        }
        
        .filter-group {
            display: flex;
            flex-direction: column;
        }
        
        .filter-label {
            font-weight: 600;
            margin-bottom: 0.5rem;
            color: #34495e;
        }
        
        .price-inputs {
            display: grid;
            grid-template-columns: 1fr auto 1fr;
            gap: 0.5rem;
            align-items: center;
        }
        
        .results-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
            flex-wrap: wrap;
            gap: 1rem;
        }
        
        .sort-controls {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .product-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 2rem;
        }
        
        .no-results {
            text-align: center;
            padding: 3rem;
            color: #7f8c8d;
        }
        
        .active-filters {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
            margin-top: 1rem;
        }
        
        .filter-tag {
            background-color: #3498db;
            color: white;
            padding: 0.25rem 0.75rem;
            border-radius: 15px;
            font-size: 0.875rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .filter-tag button {
            background: none;
            border: none;
            color: white;
            cursor: pointer;
            font-size: 1rem;
        }

        .category-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .category-card {
            background: white;
            padding: 1.5rem;
            border-radius: 10px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            text-align: center;
            transition: transform 0.3s, box-shadow 0.3s;
        }

        .category-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
        }

        .category-icon {
            font-size: 3rem;
            margin-bottom: 1rem;
        }
    </style>
</head>
<body>
    <?php include '../../includes/header.php'; ?>
    
    <main class="main-content">
        <div class="container">
            <?php
            $flash = getFlashMessage();
            if ($flash): ?>
                <div class="alert alert-<?= $flash['type'] ?>">
                    <?= htmlspecialchars($flash['message']) ?>
                </div>
            <?php endif; ?>

            <div class="card">
                <div class="card-header">
                    <h1 class="card-title">
                        <?php if (!empty($filters['category'])): ?>
                            <?= ucfirst($filters['category']) ?> Collection
                        <?php elseif (!empty($filters['search'])): ?>
                            Search Results for "<?= htmlspecialchars($filters['search']) ?>"
                        <?php else: ?>
                            All Products
                        <?php endif; ?>
                    </h1>
                    <p>Discover unique vintage items from Again&Co</p>
                </div>

                <!-- Product Filters -->
                <div class="filters-container">
                    <form method="GET" action="" id="filter-form">
                        <div class="filter-row">
                            <div class="filter-group">
                                <label class="filter-label">Search</label>
                                <input type="text" 
                                       name="search" 
                                       class="form-control" 
                                       placeholder="Search products..."
                                       value="<?= htmlspecialchars($filters['search']) ?>">
                            </div>
                            
                            <div class="filter-group">
                                <label class="filter-label">Category</label>
                                <select name="category" class="form-control">
                                    <option value="">All Categories</option>
                                    <?php foreach ($categories as $category): ?>
                                        <option value="<?= htmlspecialchars($category['category']) ?>"
                                                <?= $filters['category'] === $category['category'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($category['category']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="filter-group">
                                <label class="filter-label">Brand</label>
                                <select name="brand" class="form-control">
                                    <option value="">All Brands</option>
                                    <?php foreach ($brands as $brand): ?>
                                        <option value="<?= htmlspecialchars($brand['brand']) ?>"
                                                <?= $filters['brand'] === $brand['brand'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($brand['brand']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="filter-group">
                                <label class="filter-label">Condition</label>
                                <select name="condition" class="form-control">
                                    <option value="">All Conditions</option>
                                    <?php foreach ($conditions as $condition): ?>
                                        <option value="<?= htmlspecialchars($condition['condition_type']) ?>"
                                                <?= $filters['condition'] === $condition['condition_type'] ? 'selected' : '' ?>>
                                            <?= ucfirst(str_replace('_', ' ', $condition['condition_type'])) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        
                        <div class="filter-row">
                            <div class="filter-group">
                                <label class="filter-label">Price Range</label>
                                <div class="price-inputs">
                                    <input type="number" 
                                           name="min_price" 
                                           class="form-control" 
                                           placeholder="Min $"
                                           min="0"
                                           step="0.01"
                                           value="<?= $filters['min_price'] > 0 ? $filters['min_price'] : '' ?>">
                                    <span>to</span>
                                    <input type="number" 
                                           name="max_price" 
                                           class="form-control" 
                                           placeholder="Max $"
                                           min="0"
                                           step="0.01"
                                           value="<?= $filters['max_price'] > 0 ? $filters['max_price'] : '' ?>">
                                </div>
                            </div>
                            
                            <div class="filter-group">
                                <label class="filter-label">Sort By</label>
                                <select name="sort" class="form-control">
                                    <option value="newest" <?= $filters['sort'] === 'newest' ? 'selected' : '' ?>>Newest First</option>
                                    <option value="oldest" <?= $filters['sort'] === 'oldest' ? 'selected' : '' ?>>Oldest First</option>
                                    <option value="price_low" <?= $filters['sort'] === 'price_low' ? 'selected' : '' ?>>Price: Low to High</option>
                                    <option value="price_high" <?= $filters['sort'] === 'price_high' ? 'selected' : '' ?>>Price: High to Low</option>
                                    <option value="name" <?= $filters['sort'] === 'name' ? 'selected' : '' ?>>Name A-Z</option>
                                </select>
                            </div>
                            
                            <div class="filter-group">
                                <label class="filter-label">&nbsp;</label>
                                <div style="display: flex; gap: 0.5rem;">
                                    <button type="submit" class="btn btn-primary">Apply Filters</button>
                                    <a href="catalog.php" class="btn btn-secondary">Clear All</a>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Active Filters Display -->
                        <?php if (!empty($filters['category']) || !empty($filters['brand']) || !empty($filters['condition']) || 
                                  $filters['min_price'] > 0 || $filters['max_price'] > 0 || !empty($filters['search'])): ?>
                            <div class="active-filters">
                                <span style="font-weight: 600;">Active Filters:</span>
                                
                                <?php if (!empty($filters['search'])): ?>
                                    <div class="filter-tag">
                                        Search: "<?= htmlspecialchars($filters['search']) ?>"
                                        <button type="button" onclick="removeFilter('search')">×</button>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if (!empty($filters['category'])): ?>
                                    <div class="filter-tag">
                                        Category: <?= htmlspecialchars($filters['category']) ?>
                                        <button type="button" onclick="removeFilter('category')">×</button>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if (!empty($filters['brand'])): ?>
                                    <div class="filter-tag">
                                        Brand: <?= htmlspecialchars($filters['brand']) ?>
                                        <button type="button" onclick="removeFilter('brand')">×</button>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if (!empty($filters['condition'])): ?>
                                    <div class="filter-tag">
                                        Condition: <?= ucfirst(str_replace('_', ' ', $filters['condition'])) ?>
                                        <button type="button" onclick="removeFilter('condition')">×</button>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if ($filters['min_price'] > 0 || $filters['max_price'] > 0): ?>
                                    <div class="filter-tag">
                                        Price: $<?= $filters['min_price'] > 0 ? number_format($filters['min_price'], 2) : '0' ?> - 
                                        $<?= $filters['max_price'] > 0 ? number_format($filters['max_price'], 2) : '∞' ?>
                                        <button type="button" onclick="removePriceFilter()">×</button>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </form>
                </div>

                <!-- Results Header -->
                <div class="results-header">
                    <div>
                        <strong><?= number_format($total_products) ?></strong> 
                        product<?= $total_products !== 1 ? 's' : '' ?> found
                        <?php if ($total_pages > 1): ?>
                            (Page <?= $filters['page'] ?> of <?= $total_pages ?>)
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Products Grid -->
                <?php if (!empty($products)): ?>
                    <div class="product-grid">
                        <?php foreach ($products as $product): ?>
                            <div class="product-card">
                                <div class="product-image">
                                    <?php if ($product['image_url']): ?>
                                        <img src="<?= htmlspecialchars($product['image_url']) ?>" 
                                             alt="<?= htmlspecialchars($product['product_name']) ?>"
                                             loading="lazy">
                                    <?php else: ?>
                                        📷
                                    <?php endif; ?>
                                    
                                    <?php if (!$product['in_stock']): ?>
                                        <div class="out-of-stock-overlay">Out of Stock</div>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="product-info">
                                    <h3 class="product-title">
                                        <a href="detail.php?id=<?= $product['product_id'] ?>">
                                            <?= htmlspecialchars($product['product_name']) ?>
                                        </a>
                                    </h3>
                                    
                                    <div class="product-price">
                                        <?= formatCurrency($product['price']) ?>
                                    </div>
                                    
                                    <div class="product-meta">
                                        <span class="product-category"><?= htmlspecialchars($product['category']) ?></span>
                                        <span class="product-brand"><?= htmlspecialchars($product['brand']) ?></span>
                                        <span class="product-condition"><?= ucfirst(str_replace('_', ' ', $product['condition_type'])) ?></span>
                                    </div>
                                    
                                    <p class="product-description">
                                        <?= htmlspecialchars(substr($product['description'], 0, 100)) ?>
                                        <?= strlen($product['description']) > 100 ? '...' : '' ?>
                                    </p>
                                    
                                    <div class="product-actions mt-2">
                                        <a href="detail.php?id=<?= $product['product_id'] ?>" 
                                           class="btn btn-primary">View Details</a>
                                        
                                        <?php if (isLoggedIn() && $product['in_stock']): ?>
                                            <button class="btn btn-secondary add-to-cart" 
                                                    data-product-id="<?= $product['product_id'] ?>">
                                                Add to Cart
                                            </button>
                                        <?php elseif (!$product['in_stock']): ?>
                                            <button class="btn btn-secondary" disabled>
                                                Out of Stock
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <!-- Pagination -->
                    <?php if ($total_pages > 1): ?>
                        <div class="pagination-container mt-4">
                            <?php
                            $current_url = $_SERVER['REQUEST_URI'];
                            $url_parts = parse_url($current_url);
                            parse_str($url_parts['query'] ?? '', $url_params);
                            unset($url_params['page']);
                            $base_url = $url_parts['path'] . '?' . http_build_query($url_params);
                            
                            echo createPagination($filters['page'], $total_pages, $base_url);
                            ?>
                        </div>
                    <?php endif; ?>
                    
                <?php else: ?>
                    <div class="no-results">
                        <h3>No products found</h3>
                        <p>Try adjusting your filters or <a href="catalog.php">browse all products</a></p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>
    
    <?php include '../../includes/footer.php'; ?>
    
    <script src="../../assets/js/main.js"></script>
    <script>
        // Auto-submit form when filters change
        document.addEventListener('change', function(event) {
            if (event.target.closest('#filter-form')) {
                // Don't auto-submit for text inputs, only selects
                if (event.target.tagName === 'SELECT') {
                    document.getElementById('filter-form').submit();
                }
            }
        });
        
        // Remove individual filters
        function removeFilter(filterName) {
            const form = document.getElementById('filter-form');
            const input = form.querySelector(`[name="${filterName}"]`);
            if (input) {
                input.value = '';
                form.submit();
            }
        }
        
        function removePriceFilter() {
            const form = document.getElementById('filter-form');
            const minPrice = form.querySelector('[name="min_price"]');
            const maxPrice = form.querySelector('[name="max_price"]');
            if (minPrice) minPrice.value = '';
            if (maxPrice) maxPrice.value = '';
            form.submit();
        }
        
        // Add to cart functionality
        document.addEventListener('click', function(event) {
            if (event.target.classList.contains('add-to-cart')) {
                event.preventDefault();
                const productId = event.target.dataset.productId;
                
                fetch('../../api/cart.php', {
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
