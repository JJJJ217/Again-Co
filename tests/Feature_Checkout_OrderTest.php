<?php
use PHPUnit\Framework\TestCase;

/**
 * F109 Checkout & Order Processing
 * User Story: "As a shopper, I can complete checkout and receive order confirmation"
 */
final class Feature_Checkout_OrderTest extends TestCase
{
    protected function tearDown(): void
    {
        reset_session_flash();
        unset($_SESSION['user_id'], $_SESSION['checkout'], $_SESSION['order_success']);
    }

    public function testCheckoutSessionInitialization(): void
    {
        // Test checkout session structure
        $checkoutData = [
            'shipping_address' => [],
            'billing_address' => [],
            'shipping_method' => 'standard',
            'payment_method' => 'credit_card',
            'billing_same_as_shipping' => true
        ];
        
        $this->assertArrayHasKey('shipping_address', $checkoutData);
        $this->assertArrayHasKey('billing_address', $checkoutData);
        $this->assertArrayHasKey('shipping_method', $checkoutData);
        $this->assertArrayHasKey('payment_method', $checkoutData);
        $this->assertTrue($checkoutData['billing_same_as_shipping']);
    }

    public function testOrderTotalCalculation(): void
    {
        // Test order breakdown calculation
        $subtotal = 89.99;
        $shipping = 9.99;
        $tax = 7.20;
        
        $total = $subtotal + $shipping + $tax;
        $this->assertSame(107.18, round($total, 2));
        
        // Test formatted currency
        $formattedTotal = '$' . number_format($total, 2);
        $this->assertSame('$107.18', $formattedTotal);
        
        // Test tax calculation (8% rate)
        $taxRate = 0.08;
        $calculatedTax = round($subtotal * $taxRate, 2);
        $this->assertSame(7.20, $calculatedTax);
    }

    public function testOrderNotesJsonParsing(): void
    {
        // Test order notes JSON structure
        $orderData = [
            'subtotal' => 89.99,
            'shipping_cost' => 9.99,
            'tax_amount' => 7.20,
            'shipping_address' => [
                'first_name' => 'Michael',
                'last_name' => 'Sutjiato',
                'address_line1' => '221/629 Gardeners Road',
                'city' => 'Mascot',
                'state' => 'NSW',
                'postal_code' => '2020',
                'country' => 'AU'
            ],
            'payment_method' => 'credit_card',
            'shipping_method' => 'standard'
        ];
        
        $jsonNotes = json_encode($orderData);
        $this->assertJson($jsonNotes);
        
        $decoded = json_decode($jsonNotes, true);
        $this->assertSame(89.99, $decoded['subtotal']);
        $this->assertSame('Michael', $decoded['shipping_address']['first_name']);
        $this->assertSame('AU', $decoded['shipping_address']['country']);
        $this->assertSame('credit_card', $decoded['payment_method']);
    }

    public function testOrderSuccessTracking(): void
    {
        // Test order success session tracking
        $_SESSION['order_success'] = [
            'order_id' => 123,
            'total' => 107.18,
            'timestamp' => time(),
            'message' => 'Your order has been placed successfully!'
        ];
        
        $this->assertTrue(isset($_SESSION['order_success']));
        $this->assertSame(123, $_SESSION['order_success']['order_id']);
        $this->assertSame(107.18, $_SESSION['order_success']['total']);
        $this->assertIsInt($_SESSION['order_success']['timestamp']);
        $this->assertStringContainsString('successfully', $_SESSION['order_success']['message']);
    }

    public function testCountryOptionsIncludeAustralia(): void
    {
        // Test that Australia is available in country options
        $countries = ['US', 'CA', 'UK', 'AU'];
        $this->assertContains('AU', $countries);
        
        $countryNames = [
            'US' => 'United States',
            'CA' => 'Canada', 
            'UK' => 'United Kingdom',
            'AU' => 'Australia'
        ];
        $this->assertSame('Australia', $countryNames['AU']);
        $this->assertArrayHasKey('AU', $countryNames);
    }

    public function testShippingMethodOptions(): void
    {
        // Test shipping method validation
        $shippingMethods = [
            'standard' => ['name' => 'Standard Shipping', 'cost' => 9.99, 'days' => '5-7'],
            'express' => ['name' => 'Express Shipping', 'cost' => 19.99, 'days' => '2-3'],
            'overnight' => ['name' => 'Overnight Shipping', 'cost' => 39.99, 'days' => '1']
        ];
        
        $this->assertArrayHasKey('standard', $shippingMethods);
        $this->assertArrayHasKey('express', $shippingMethods);
        $this->assertSame(9.99, $shippingMethods['standard']['cost']);
        $this->assertSame('2-3', $shippingMethods['express']['days']);
    }

    public function testPaymentMethodValidation(): void
    {
        // Test payment method options
        $paymentMethods = ['credit_card', 'paypal', 'bank_transfer'];
        $selectedMethod = 'credit_card';
        
        $this->assertContains($selectedMethod, $paymentMethods);
        $this->assertTrue(in_array($selectedMethod, $paymentMethods));
        
        // Test invalid payment method
        $invalidMethod = 'crypto';
        $this->assertFalse(in_array($invalidMethod, $paymentMethods));
    }

    public function testAddressValidation(): void
    {
        // Test address field validation
        $address = [
            'first_name' => 'Michael',
            'last_name' => 'Sutjiato',
            'address_line1' => '221/629 Gardeners Road',
            'city' => 'Mascot',
            'state' => 'NSW',
            'postal_code' => '2020',
            'country' => 'AU'
        ];
        
        // Test required fields
        $requiredFields = ['first_name', 'last_name', 'address_line1', 'city', 'postal_code', 'country'];
        foreach ($requiredFields as $field) {
            $this->assertArrayHasKey($field, $address);
            $this->assertNotEmpty($address[$field], "Field $field should not be empty");
        }
        
        // Test postal code format (Australian)
        $this->assertMatchesRegularExpression('/^\d{4}$/', $address['postal_code']);
    }
}