<?php
/**
 * Product Search Redirect
 * Redirects to unified catalog page for consistent search experience
 */

// Redirect old search URLs to the catalog page
$search_query = $_GET['q'] ?? '';
$category = $_GET['category'] ?? '';
$sort = $_GET['sort'] ?? '';
$page = $_GET['page'] ?? '';

// Build redirect URL
$redirect_url = 'catalog.php?';
$params = [];

if (!empty($search_query)) {
    $params['search'] = $search_query;
}
if (!empty($category)) {
    $params['category'] = $category;
}
if (!empty($sort)) {
    $params['sort'] = $sort;
}
if (!empty($page)) {
    $params['page'] = $page;
}

$redirect_url .= http_build_query($params);

// Redirect with 301 (permanent redirect) to maintain SEO
header("Location: $redirect_url", true, 301);
exit;
?>
        
        // Build sort clause
        $sort_clause = "p.created_at DESC";
        switch ($sort) {
            case 'price_low':
                $sort_clause = "p.price ASC";
                break;
            case 'price_high':
                $sort_clause = "p.price DESC";
                break;
            case 'name':
                $sort_clause = "p.product_name ASC";
                break;
            case 'newest':
                $sort_clause = "p.created_at DESC";
                break;
            case 'popularity':
                $sort_clause = "p.views DESC";
                break;
            case 'relevance':
            default:
                if (!empty($search_query)) {
                    // Basic relevance scoring
                    $sort_clause = "
                        CASE 
                            WHEN p.product_name LIKE ? THEN 1
                            WHEN p.category LIKE ? THEN 2
                            WHEN p.brand LIKE ? THEN 3
                            ELSE 4
                        END, p.created_at DESC";
                    array_unshift($params, "%{$search_query}%", "%{$search_query}%", "%{$search_query}%");
                }
                break;
        }
        
        // Get search results
        $search_query_sql = "
            SELECT p.*, 
                   COALESCE(AVG(r.rating), 0) as avg_rating,
                   COUNT(r.review_id) as review_count
            FROM products p
            LEFT JOIN reviews r ON p.product_id = r.product_id
            WHERE {$where_clause}
            GROUP BY p.product_id
            ORDER BY {$sort_clause}
            LIMIT {$per_page} OFFSET {$offset}";
        
        $search_results = $db->fetchAll($search_query_sql, $params);
        
        // Get available filters based on search results
        $filters_query = "
            SELECT 
                p.category,
                p.brand,
                p.condition_rating,
                MIN(p.price) as min_price,
                MAX(p.price) as max_price
            FROM products p
            WHERE {$where_clause}
            GROUP BY p.category, p.brand, p.condition_rating
            ORDER BY p.category, p.brand";
        
        $filter_results = $db->fetchAll($filters_query, $params);
        
        // Organize filters
        foreach ($filter_results as $filter) {
            if (!in_array($filter['category'], $filters['categories'] ?? [])) {
                $filters['categories'][] = $filter['category'];
            }
            if (!in_array($filter['brand'], $filters['brands'] ?? [])) {
                $filters['brands'][] = $filter['brand'];
            }
            if (!in_array($filter['condition_rating'], $filters['conditions'] ?? [])) {
                $filters['conditions'][] = $filter['condition_rating'];
            }
        }
        
        if ($filter_results) {
            $filters['price_range'] = [
                'min' => min(array_column($filter_results, 'min_price')),
                'max' => max(array_column($filter_results, 'max_price'))
            ];
        }
        
    } catch (Exception $e) {
        error_log("Search error: " . $e->getMessage());
        $search_results = [];
        $total_results = 0;
    }
}

$total_pages = ceil($total_results / $per_page);
$page_title = !empty($search_query) ? "Search: " . htmlspecialchars($search_query) . " - Again&Co" : "Search - Again&Co";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $page_title ?></title>
    <link rel="stylesheet" href="../../assets/css/style.css">
    <style>
        .search-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 3rem 0 2rem;
            margin-bottom: 2rem;
        }
        
        .search-form {
            max-width: 600px;
            margin: 0 auto 2rem;
            position: relative;
        }
        
        .search-input-group {
            display: flex;
            gap: 0.5rem;
        }
        
        .search-input {
            flex: 1;
            padding: 1rem 1.5rem;
            border: none;
            border-radius: 50px;
            font-size: 1.125rem;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        
        .search-btn {
            padding: 1rem 2rem;
            background: #27ae60;
            color: white;
            border: none;
            border-radius: 50px;
            font-weight: 600;
            cursor: pointer;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        
        .search-btn:hover {
            background: #229954;
            transform: translateY(-2px);
        }
        
        .search-stats {
            text-align: center;
            font-size: 1.125rem;
            opacity: 0.9;
        }
        
        .search-content {
            display: grid;
            grid-template-columns: 250px 1fr;
            gap: 2rem;
            align-items: start;
        }
        
        .search-filters {
            background: white;
            border-radius: 10px;
            padding: 1.5rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            position: sticky;
            top: 2rem;
        }
        
        .filter-section {
            margin-bottom: 2rem;
        }
        
        .filter-section:last-child {
            margin-bottom: 0;
        }
        
        .filter-title {
            font-weight: 600;
            margin-bottom: 1rem;
            color: #2c3e50;
            border-bottom: 2px solid #ecf0f1;
            padding-bottom: 0.5rem;
        }
        
        .filter-options {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }
        
        .filter-option {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .filter-option input[type="checkbox"] {
            margin: 0;
        }
        
        .search-results {
            display: flex;
            flex-direction: column;
            gap: 2rem;
        }
        
        .search-toolbar {
            display: flex;
            justify-content: between;
            align-items: center;
            background: white;
            padding: 1rem 1.5rem;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .sort-controls {
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        
        .sort-select {
            padding: 0.5rem 1rem;
            border: 1px solid #ddd;
            border-radius: 5px;
        }
        
        .results-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 1.5rem;
        }
        
        .no-results {
            text-align: center;
            padding: 4rem 2rem;
            color: #7f8c8d;
        }
        
        .no-results-icon {
            font-size: 4rem;
            margin-bottom: 1rem;
        }
        
        .search-suggestions {
            margin-top: 2rem;
        }
        
        .suggestion-links {
            display: flex;
            flex-wrap: wrap;
            gap: 1rem;
            margin-top: 1rem;
        }
        
        .suggestion-link {
            background: #f8f9fa;
            padding: 0.5rem 1rem;
            border-radius: 20px;
            text-decoration: none;
            color: #495057;
            font-size: 0.875rem;
        }
        
        .suggestion-link:hover {
            background: #e9ecef;
        }
        
        @media (max-width: 768px) {
            .search-content {
                grid-template-columns: 1fr;
            }
            
            .search-filters {
                position: relative;
                top: auto;
            }
            
            .search-input-group {
                flex-direction: column;
            }
            
            .search-toolbar {
                flex-direction: column;
                gap: 1rem;
                align-items: stretch;
            }
            
            .results-grid {
                grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            }
        }
    </style>
</head>
<body>
    <?php include '../../includes/header.php'; ?>
    
    <!-- Search Header -->
    <div class="search-header">
        <div class="container">
            <form class="search-form" method="GET">
                <div class="search-input-group">
                    <input type="text" 
                           name="q" 
                           class="search-input" 
                           placeholder="Search for vintage treasures..." 
                           value="<?= htmlspecialchars($search_query) ?>"
                           autofocus>
                    <button type="submit" class="search-btn">
                        🔍 Search
                    </button>
                </div>
                
                <!-- Hidden inputs to preserve other filters -->
                <?php if (!empty($category)): ?>
                    <input type="hidden" name="category" value="<?= htmlspecialchars($category) ?>">
                <?php endif; ?>
                <?php if (!empty($sort) && $sort !== 'relevance'): ?>
                    <input type="hidden" name="sort" value="<?= htmlspecialchars($sort) ?>">
                <?php endif; ?>
            </form>
            
            <?php if (!empty($search_query) || !empty($category)): ?>
                <div class="search-stats">
                    <?php if ($total_results > 0): ?>
                        Found <?= number_format($total_results) ?> 
                        result<?= $total_results === 1 ? '' : 's' ?>
                        <?php if (!empty($search_query)): ?>
                            for "<?= htmlspecialchars($search_query) ?>"
                        <?php endif; ?>
                        <?php if (!empty($category)): ?>
                            in <?= htmlspecialchars($category) ?>
                        <?php endif; ?>
                    <?php else: ?>
                        No results found
                        <?php if (!empty($search_query)): ?>
                            for "<?= htmlspecialchars($search_query) ?>"
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <main class="main-content">
        <div class="container">
            <?php if (!empty($search_query) || !empty($category)): ?>
                <div class="search-content">
                    <!-- Search Filters -->
                    <aside class="search-filters">
                        <h3>Refine Results</h3>
                        
                        <?php if (!empty($filters['categories'])): ?>
                            <div class="filter-section">
                                <div class="filter-title">Categories</div>
                                <div class="filter-options">
                                    <?php foreach ($filters['categories'] as $cat): ?>
                                        <label class="filter-option">
                                            <input type="checkbox" 
                                                   name="categories[]" 
                                                   value="<?= htmlspecialchars($cat) ?>"
                                                   <?= $category === $cat ? 'checked' : '' ?>>
                                            <span><?= htmlspecialchars($cat) ?></span>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($filters['brands'])): ?>
                            <div class="filter-section">
                                <div class="filter-title">Brands</div>
                                <div class="filter-options">
                                    <?php foreach ($filters['brands'] as $brand): ?>
                                        <label class="filter-option">
                                            <input type="checkbox" 
                                                   name="brands[]" 
                                                   value="<?= htmlspecialchars($brand) ?>">
                                            <span><?= htmlspecialchars($brand) ?></span>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($filters['conditions'])): ?>
                            <div class="filter-section">
                                <div class="filter-title">Condition</div>
                                <div class="filter-options">
                                    <?php foreach ($filters['conditions'] as $condition): ?>
                                        <label class="filter-option">
                                            <input type="checkbox" 
                                                   name="conditions[]" 
                                                   value="<?= htmlspecialchars($condition) ?>">
                                            <span><?= getConditionText($condition) ?></span>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($filters['price_range'])): ?>
                            <div class="filter-section">
                                <div class="filter-title">Price Range</div>
                                <div class="price-range">
                                    <input type="range" 
                                           name="min_price" 
                                           min="<?= $filters['price_range']['min'] ?>"
                                           max="<?= $filters['price_range']['max'] ?>"
                                           value="<?= $filters['price_range']['min'] ?>">
                                    <input type="range" 
                                           name="max_price" 
                                           min="<?= $filters['price_range']['min'] ?>"
                                           max="<?= $filters['price_range']['max'] ?>"
                                           value="<?= $filters['price_range']['max'] ?>">
                                    <div class="price-range-display">
                                        <?= formatCurrency($filters['price_range']['min']) ?> - 
                                        <?= formatCurrency($filters['price_range']['max']) ?>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <button type="button" class="btn btn-secondary btn-block" onclick="clearFilters()">
                            Clear Filters
                        </button>
                    </aside>
                    
                    <!-- Search Results -->
                    <div class="search-results">
                        <?php if ($total_results > 0): ?>
                            <!-- Search Toolbar -->
                            <div class="search-toolbar">
                                <div class="results-info">
                                    Showing <?= $offset + 1 ?>-<?= min($offset + $per_page, $total_results) ?> 
                                    of <?= number_format($total_results) ?> results
                                </div>
                                
                                <div class="sort-controls">
                                    <label for="sort">Sort by:</label>
                                    <select name="sort" id="sort" class="sort-select" onchange="updateSort()">
                                        <option value="relevance" <?= $sort === 'relevance' ? 'selected' : '' ?>>
                                            Relevance
                                        </option>
                                        <option value="newest" <?= $sort === 'newest' ? 'selected' : '' ?>>
                                            Newest First
                                        </option>
                                        <option value="price_low" <?= $sort === 'price_low' ? 'selected' : '' ?>>
                                            Price: Low to High
                                        </option>
                                        <option value="price_high" <?= $sort === 'price_high' ? 'selected' : '' ?>>
                                            Price: High to Low
                                        </option>
                                        <option value="name" <?= $sort === 'name' ? 'selected' : '' ?>>
                                            Name A-Z
                                        </option>
                                        <option value="popularity" <?= $sort === 'popularity' ? 'selected' : '' ?>>
                                            Most Popular
                                        </option>
                                    </select>
                                </div>
                            </div>
                            
                            <!-- Results Grid -->
                            <div class="results-grid">
                                <?php foreach ($search_results as $product): ?>
                                    <div class="product-card">
                                        <div class="product-image">
                                            <a href="../products/detail.php?id=<?= $product['product_id'] ?>">
                                                <?php if ($product['image_url']): ?>
                                                    <img src="<?= htmlspecialchars($product['image_url']) ?>" 
                                                         alt="<?= htmlspecialchars($product['product_name']) ?>">
                                                <?php else: ?>
                                                    <div class="no-image">📷</div>
                                                <?php endif; ?>
                                            </a>
                                            
                                            <?php if ($product['stock'] <= 5): ?>
                                                <div class="stock-badge low-stock">Only <?= $product['stock'] ?> left!</div>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <div class="product-info">
                                            <div class="product-meta">
                                                <span class="product-category"><?= htmlspecialchars($product['category']) ?></span>
                                                <span class="product-condition"><?= getConditionText($product['condition_rating']) ?></span>
                                            </div>
                                            
                                            <h3 class="product-title">
                                                <a href="../products/detail.php?id=<?= $product['product_id'] ?>">
                                                    <?= htmlspecialchars($product['product_name']) ?>
                                                </a>
                                            </h3>
                                            
                                            <p class="product-description">
                                                <?= htmlspecialchars(substr($product['description'], 0, 100)) ?>...
                                            </p>
                                            
                                            <div class="product-rating">
                                                <?= generateStarRating($product['avg_rating']) ?>
                                                <span class="rating-count">(<?= $product['review_count'] ?>)</span>
                                            </div>
                                            
                                            <div class="product-footer">
                                                <div class="product-price">
                                                    <?= formatCurrency($product['price']) ?>
                                                </div>
                                                
                                                <button class="btn btn-primary add-to-cart" 
                                                        data-product-id="<?= $product['product_id'] ?>">
                                                    Add to Cart
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            
                            <!-- Pagination -->
                            <?php if ($total_pages > 1): ?>
                                <div class="pagination-container">
                                    <?= generatePagination($page, $total_pages, $_GET) ?>
                                </div>
                            <?php endif; ?>
                            
                        <?php else: ?>
                            <!-- No Results -->
                            <div class="no-results">
                                <div class="no-results-icon">🔍</div>
                                <h2>No results found</h2>
                                <?php if (!empty($search_query)): ?>
                                    <p>We couldn't find any products matching "<?= htmlspecialchars($search_query) ?>"</p>
                                <?php else: ?>
                                    <p>Try adjusting your search criteria</p>
                                <?php endif; ?>
                                
                                <div class="search-suggestions">
                                    <h3>Search Suggestions:</h3>
                                    <ul>
                                        <li>Check your spelling</li>
                                        <li>Try different keywords</li>
                                        <li>Use more general terms</li>
                                        <li>Browse our categories instead</li>
                                    </ul>
                                    
                                    <div class="suggestion-links">
                                        <a href="?q=vintage" class="suggestion-link">vintage</a>
                                        <a href="?q=retro" class="suggestion-link">retro</a>
                                        <a href="?category=Clothing" class="suggestion-link">clothing</a>
                                        <a href="?category=Accessories" class="suggestion-link">accessories</a>
                                        <a href="?category=Music" class="suggestion-link">music</a>
                                        <a href="../products/catalog.php" class="suggestion-link">view all products</a>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
            <?php else: ?>
                <!-- Search Landing Page -->
                <div class="search-landing">
                    <div class="text-center">
                        <h2>What vintage treasure are you looking for?</h2>
                        <p>Search through our curated collection of vintage and retro items</p>
                        
                        <div class="popular-searches mt-4">
                            <h3>Popular Searches</h3>
                            <div class="suggestion-links">
                                <a href="?q=vintage+jacket" class="suggestion-link">vintage jacket</a>
                                <a href="?q=retro+dress" class="suggestion-link">retro dress</a>
                                <a href="?q=vinyl+records" class="suggestion-link">vinyl records</a>
                                <a href="?q=antique+jewelry" class="suggestion-link">antique jewelry</a>
                                <a href="?q=vintage+handbag" class="suggestion-link">vintage handbag</a>
                                <a href="?q=retro+furniture" class="suggestion-link">retro furniture</a>
                            </div>
                        </div>
                        
                        <div class="category-shortcuts mt-4">
                            <h3>Browse by Category</h3>
                            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; margin-top: 1rem;">
                                <a href="?category=Clothing" class="btn btn-secondary">
                                    👔 Vintage Clothing
                                </a>
                                <a href="?category=Accessories" class="btn btn-secondary">
                                    👜 Accessories
                                </a>
                                <a href="?category=Music" class="btn btn-secondary">
                                    🎵 Music & Records
                                </a>
                                <a href="?category=Home" class="btn btn-secondary">
                                    🏠 Home & Decor
                                </a>
                                <a href="?category=Electronics" class="btn btn-secondary">
                                    📻 Vintage Electronics
                                </a>
                                <a href="?category=Books" class="btn btn-secondary">
                                    📚 Books & Media
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </main>
    
    <?php include '../../includes/footer.php'; ?>
    
    <script src="../../assets/js/main.js"></script>
    <script>
        function updateSort() {
            const sort = document.getElementById('sort').value;
            const url = new URL(window.location);
            url.searchParams.set('sort', sort);
            url.searchParams.set('page', '1'); // Reset to first page
            window.location.href = url.toString();
        }
        
        function clearFilters() {
            const url = new URL(window.location);
            const q = url.searchParams.get('q');
            
            // Keep only the search query
            url.search = '';
            if (q) {
                url.searchParams.set('q', q);
            }
            
            window.location.href = url.toString();
        }
        
        // Handle filter changes
        document.addEventListener('change', function(event) {
            if (event.target.type === 'checkbox' && event.target.name.includes('[]')) {
                // Handle multi-select filters
                const url = new URL(window.location);
                const paramName = event.target.name.replace('[]', '');
                
                if (event.target.checked) {
                    url.searchParams.append(paramName, event.target.value);
                } else {
                    const values = url.searchParams.getAll(paramName);
                    url.searchParams.delete(paramName);
                    values.forEach(value => {
                        if (value !== event.target.value) {
                            url.searchParams.append(paramName, value);
                        }
                    });
                }
                
                url.searchParams.set('page', '1'); // Reset to first page
                window.location.href = url.toString();
            }
        });
        
        // Add to cart functionality
        document.addEventListener('click', function(event) {
            if (event.target.classList.contains('add-to-cart')) {
                const productId = event.target.dataset.productId;
                EVinty.addToCart(productId, 1);
            }
        });
    </script>
</body>
</html>
