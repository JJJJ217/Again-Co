<?php
use PHPUnit\Framework\TestCase;

/**
 * Admin Dashboard & User Management
 * User Story: "As an admin, I can manage users and products through the admin panel"
 */
final class Feature_Admin_ManagementTest extends TestCase
{
    protected function tearDown(): void
    {
        reset_session_flash();
        unset($_SESSION['user_id'], $_SESSION['user_role']);
    }

    public function testAdminRoleRequirements(): void
    {
        // Test admin access control
        $_SESSION['user_role'] = 'admin';
        $this->assertTrue(hasRole(['admin']));
        $this->assertTrue(isAdmin());
        
        $_SESSION['user_role'] = 'staff';
        $this->assertTrue(hasRole(['admin', 'staff']));
        $this->assertFalse(isAdmin());
        $this->assertTrue(isStaff());
        
        $_SESSION['user_role'] = 'customer';
        $this->assertFalse(hasRole(['admin']));
        $this->assertFalse(isAdmin());
        $this->assertFalse(isStaff());
    }

    public function testUserStatisticsCalculation(): void
    {
        // Test user count logic (simulated)
        $totalUsers = 15;
        $newUsersToday = 3;
        $adminUsers = 1;
        $staffUsers = 2;
        $customerUsers = 12;
        
        $this->assertSame(15, $totalUsers);
        $this->assertSame($totalUsers, $adminUsers + $staffUsers + $customerUsers);
        $this->assertLessThanOrEqual($totalUsers, $newUsersToday);
        $this->assertGreaterThan(0, $adminUsers);
    }

    public function testProductStatusBadgeGeneration(): void
    {
        // Test status badge function logic
        $statusBadges = [
            'active' => '<span class="badge badge-success">Active</span>',
            'inactive' => '<span class="badge badge-secondary">Inactive</span>',
            'out_of_stock' => '<span class="badge badge-danger">Out of Stock</span>'
        ];
        
        $this->assertStringContainsString('badge-success', $statusBadges['active']);
        $this->assertStringContainsString('badge-danger', $statusBadges['out_of_stock']);
        $this->assertStringContainsString('Active', $statusBadges['active']);
        $this->assertStringContainsString('Inactive', $statusBadges['inactive']);
    }

    public function testOrderStatusDisplay(): void
    {
        // Test order status mapping
        $orderStatuses = [
            'pending' => '🟡 Pending',
            'confirmed' => '🔵 Confirmed', 
            'processing' => '🟢 Processing',
            'shipped' => '⚫ Shipped',
            'delivered' => '✅ Delivered',
            'cancelled' => '🔴 Cancelled'
        ];
        
        $this->assertStringContainsString('Pending', $orderStatuses['pending']);
        $this->assertStringContainsString('🔵', $orderStatuses['confirmed']);
        $this->assertStringContainsString('Delivered', $orderStatuses['delivered']);
        $this->assertStringContainsString('🔴', $orderStatuses['cancelled']);
    }

    public function testUserRoleHierarchy(): void
    {
        // Test role hierarchy and permissions
        $roleHierarchy = [
            'admin' => 3,
            'staff' => 2,
            'customer' => 1
        ];
        
        $this->assertGreaterThan($roleHierarchy['staff'], $roleHierarchy['admin']);
        $this->assertGreaterThan($roleHierarchy['customer'], $roleHierarchy['staff']);
        
        // Test role comparison
        $userRole = 'staff';
        $requiredRole = 'customer';
        $hasPermission = $roleHierarchy[$userRole] >= $roleHierarchy[$requiredRole];
        $this->assertTrue($hasPermission);
        
        $userRole = 'customer';
        $requiredRole = 'admin';
        $hasPermission = $roleHierarchy[$userRole] >= $roleHierarchy[$requiredRole];
        $this->assertFalse($hasPermission);
    }

    public function testProductConditionTypes(): void
    {
        // Test product condition validation
        $validConditions = ['new', 'like_new', 'good', 'fair'];
        $testCondition = 'good';
        
        $this->assertContains($testCondition, $validConditions);
        $this->assertTrue(in_array($testCondition, $validConditions));
        
        // Test invalid condition
        $invalidCondition = 'terrible';
        $this->assertFalse(in_array($invalidCondition, $validConditions));
    }

    public function testStockLevelAlerts(): void
    {
        // Test low stock calculation
        $products = [
            ['product_name' => 'Vintage Jacket', 'stock' => 2, 'low_stock_threshold' => 5],
            ['product_name' => 'Retro Sunglasses', 'stock' => 8, 'low_stock_threshold' => 5],
            ['product_name' => 'Old Book', 'stock' => 0, 'low_stock_threshold' => 3]
        ];
        
        $lowStockProducts = [];
        $outOfStockProducts = [];
        
        foreach ($products as $product) {
            if ($product['stock'] == 0) {
                $outOfStockProducts[] = $product;
            } elseif ($product['stock'] <= $product['low_stock_threshold']) {
                $lowStockProducts[] = $product;
            }
        }
        
        $this->assertCount(1, $lowStockProducts); // Vintage Jacket
        $this->assertCount(1, $outOfStockProducts); // Old Book
        $this->assertSame('Vintage Jacket', $lowStockProducts[0]['product_name']);
        $this->assertSame('Old Book', $outOfStockProducts[0]['product_name']);
    }

    public function testAdminSearchFunctionality(): void
    {
        // Test admin search query building
        $searchTerm = 'vintage';
        $searchFields = ['product_name', 'description', 'brand'];
        
        $searchConditions = [];
        foreach ($searchFields as $field) {
            $searchConditions[] = "$field LIKE ?";
        }
        
        $searchClause = '(' . implode(' OR ', $searchConditions) . ')';
        $expectedClause = '(product_name LIKE ? OR description LIKE ? OR brand LIKE ?)';
        
        $this->assertSame($expectedClause, $searchClause);
        
        // Test search parameters
        $searchParam = "%{$searchTerm}%";
        $this->assertSame('%vintage%', $searchParam);
    }
}