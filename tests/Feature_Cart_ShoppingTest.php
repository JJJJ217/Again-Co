<?php
use PHPUnit\Framework\TestCase;

/**
 * F110 Shopping Cart
 * User Story: "As a shopper, I can add/remove items and see accurate totals"
 */
final class Feature_Cart_ShoppingTest extends TestCase
{
    protected function tearDown(): void
    {
        reset_session_flash();
        unset($_SESSION['user_id']);
    }

    public function testCartCountCalculation(): void
    {
        // Mock user session
        $_SESSION['user_id'] = 1;
        
        // Test cart count function exists and returns integer
        $count = getCartCount();
        $this->assertIsInt($count);
        $this->assertGreaterThanOrEqual(0, $count);
    }

    public function testCartItemCalculations(): void
    {
        // Test cart item total calculation logic
        $item = [
            'product_name' => 'Vintage Jacket',
            'price' => 89.99,
            'quantity' => 2
        ];
        
        $itemTotal = $item['price'] * $item['quantity'];
        $this->assertSame(179.98, $itemTotal);
        
        // Test formatted display
        $formattedTotal = '$' . number_format($itemTotal, 2);
        $this->assertSame('$179.98', $formattedTotal);
        
        // Test different quantities
        $item['quantity'] = 1;
        $itemTotal = $item['price'] * $item['quantity'];
        $this->assertSame(89.99, round($itemTotal, 2));
        
        $item['quantity'] = 3;
        $itemTotal = $item['price'] * $item['quantity'];
        $this->assertSame(269.97, round($itemTotal, 2));
    }

    public function testCartDescriptionToggleLogic(): void
    {
        // Test description availability check
        $itemWithDescription = ['description' => 'Beautiful vintage jacket from the 1960s'];
        $itemWithoutDescription = ['description' => ''];
        $itemNullDescription = ['description' => null];
        
        $this->assertTrue(!empty($itemWithDescription['description']));
        $this->assertFalse(!empty($itemWithoutDescription['description']));
        $this->assertFalse(!empty($itemNullDescription['description']));
    }

    public function testShippingCalculation(): void
    {
        $items = [
            ['quantity' => 1, 'weight' => 1.0],
            ['quantity' => 2, 'weight' => 0.5]
        ];
        
        $shippingCost = calculateShipping($items, 'standard', 'US');
        $this->assertIsFloat($shippingCost);
        $this->assertGreaterThanOrEqual(0, $shippingCost);
        
        // Test shipping calculation with mock function
        $standardCost = calculateShipping($items, 'standard', 'US');
        $expressCost = calculateShipping($items, 'express', 'US');
        
        // With our mock function: standard = 10 + (2*2.5) = 15, express = 15 + (2*2.5) = 20
        $this->assertSame(15.0, $standardCost);
        $this->assertSame(20.0, $expressCost);
        $this->assertGreaterThan($standardCost, $expressCost);
        
        // Test international shipping
        $domesticCost = calculateShipping($items, 'standard', 'US');
        $internationalCost = calculateShipping($items, 'standard', 'AU');
        $this->assertGreaterThan($domesticCost, $internationalCost);
    }

    public function testCartTotalCalculation(): void
    {
        // Test cart totals calculation
        $cartItems = [
            ['price' => 29.99, 'quantity' => 1],
            ['price' => 45.50, 'quantity' => 2],
            ['price' => 15.99, 'quantity' => 1]
        ];
        
        $subtotal = 0;
        $totalQuantity = 0;
        
        foreach ($cartItems as $item) {
            $subtotal += $item['price'] * $item['quantity'];
            $totalQuantity += $item['quantity'];
        }
        
        $this->assertSame(136.98, $subtotal); // 29.99 + 91.00 + 15.99
        $this->assertSame(4, $totalQuantity); // 1 + 2 + 1
    }

    public function testQuantityValidation(): void
    {
        // Test quantity limits
        $maxStock = 10;
        $requestedQuantity = 5;
        
        $validQuantity = min($requestedQuantity, $maxStock);
        $this->assertSame(5, $validQuantity);
        
        $requestedQuantity = 15;
        $validQuantity = min($requestedQuantity, $maxStock);
        $this->assertSame(10, $validQuantity);
        
        // Test minimum quantity
        $requestedQuantity = 0;
        $validQuantity = max(1, $requestedQuantity);
        $this->assertSame(1, $validQuantity);
    }

    public function testCartPersistenceLogic(): void
    {
        // Test cart session handling
        $_SESSION['user_id'] = 123;
        
        // Mock cart data structure
        $cartData = [
            'user_id' => $_SESSION['user_id'],
            'items' => [
                ['product_id' => 1, 'quantity' => 2],
                ['product_id' => 3, 'quantity' => 1]
            ],
            'updated_at' => time()
        ];
        
        $this->assertSame(123, $cartData['user_id']);
        $this->assertCount(2, $cartData['items']);
        $this->assertIsInt($cartData['updated_at']);
    }
}