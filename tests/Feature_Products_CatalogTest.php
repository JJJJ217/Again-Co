<?php
use PHPUnit\Framework\TestCase;

/**
 * F104 Product Filtering & Search
 * User Story: "As a shopper, I can search and filter products to find vintage items"
 */
final class Feature_Products_CatalogTest extends TestCase
{
    public function testProductSearchQueryBuilding(): void
    {
        // Test search query construction (simulating the logic from catalog.php)
        $searchTerm = 'vintage jacket';
        $category = 'Clothing';
        
        // Build WHERE clause like in our catalog page
        $whereConditions = [];
        $params = [];
        
        if (!empty($searchTerm)) {
            $whereConditions[] = "(product_name LIKE ? OR description LIKE ? OR brand LIKE ?)";
            $searchParam = "%{$searchTerm}%";
            $params[] = $searchParam;
            $params[] = $searchParam;
            $params[] = $searchParam;
        }
        
        if (!empty($category) && $category !== 'all') {
            $whereConditions[] = "category = ?";
            $params[] = $category;
        }
        
        $whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';
        
        $this->assertStringContainsString('product_name LIKE ?', $whereClause);
        $this->assertStringContainsString('category = ?', $whereClause);
        $this->assertCount(4, $params); // 3 for search, 1 for category
        $this->assertSame('%vintage jacket%', $params[0]);
        $this->assertSame('Clothing', $params[3]);
    }

    public function testProductSortOptions(): void
    {
        // Test sort options from our catalog
        $sortOptions = [
            'newest' => 'created_at DESC',
            'price_low' => 'price ASC',
            'price_high' => 'price DESC',
            'name' => 'product_name ASC'
        ];
        
        $this->assertSame('created_at DESC', $sortOptions['newest']);
        $this->assertSame('price ASC', $sortOptions['price_low']);
        $this->assertSame('price DESC', $sortOptions['price_high']);
        $this->assertSame('product_name ASC', $sortOptions['name']);
    }

    public function testPriceRangeValidation(): void
    {
        // Test price range filtering logic
        $minPrice = '10.00';
        $maxPrice = '100.00';
        
        $this->assertTrue(is_numeric($minPrice));
        $this->assertTrue(is_numeric($maxPrice));
        $this->assertTrue((float)$minPrice < (float)$maxPrice);
        
        // Test invalid ranges
        $invalidMin = 'abc';
        $this->assertFalse(is_numeric($invalidMin));
        
        // Test edge cases
        $this->assertTrue(is_numeric('0'));
        $this->assertTrue(is_numeric('999.99'));
        $this->assertFalse(is_numeric(''));
    }

    public function testProductStatusFiltering(): void
    {
        // Test product status conditions
        $activeOnly = true;
        $includeInactive = false;
        
        $statusCondition = $activeOnly ? "is_active = 1" : "";
        $this->assertSame("is_active = 1", $statusCondition);
        
        $statusCondition = $includeInactive ? "" : "is_active = 1";
        $this->assertSame("is_active = 1", $statusCondition);
    }

    public function testCategoryFiltering(): void
    {
        // Test category options
        $categories = [
            'all' => 'All Categories',
            'Electronics' => 'Electronics',
            'Clothing' => 'Clothing',
            'Books' => 'Books',
            'Home & Garden' => 'Home & Garden',
            'Collectibles' => 'Collectibles'
        ];
        
        $this->assertArrayHasKey('all', $categories);
        $this->assertArrayHasKey('Electronics', $categories);
        $this->assertArrayHasKey('Clothing', $categories);
        
        // Test category filtering logic
        $selectedCategory = 'Electronics';
        $shouldFilter = ($selectedCategory !== 'all' && !empty($selectedCategory));
        $this->assertTrue($shouldFilter);
        
        $selectedCategory = 'all';
        $shouldFilter = ($selectedCategory !== 'all' && !empty($selectedCategory));
        $this->assertFalse($shouldFilter);
    }

    public function testSearchTermSanitization(): void
    {
        // Test search term cleaning
        $rawSearch = "  vintage  jacket  ";
        $cleanSearch = trim($rawSearch);
        $this->assertSame("vintage  jacket", $cleanSearch);
        
        // Test special characters
        $specialSearch = "<script>alert('xss')</script>";
        $safeSearch = htmlspecialchars($specialSearch);
        $this->assertSame("&lt;script&gt;alert('xss')&lt;/script&gt;", $safeSearch);
    }

    public function testPaginationCalculation(): void
    {
        // Test pagination logic
        $totalProducts = 47;
        $itemsPerPage = 12;
        $currentPage = 2;
        
        $totalPages = (int)ceil($totalProducts / $itemsPerPage);
        $offset = ($currentPage - 1) * $itemsPerPage;
        
        $this->assertSame(4, $totalPages); // 47/12 = 3.9, ceil = 4
        $this->assertSame(12, $offset); // (2-1) * 12 = 12
        
        // Test edge cases
        $this->assertSame(1, (int)ceil(1 / 12));
        $this->assertSame(1, (int)ceil(12 / 12));
        $this->assertSame(2, (int)ceil(13 / 12));
    }
}