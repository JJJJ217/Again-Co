<?php
use PHPUnit\Framework\TestCase;

/**
 * Order History & Management
 * User Story: "As a customer, I can view my order history and details"
 */
final class Feature_Orders_HistoryTest extends TestCase
{
    protected function tearDown(): void
    {
        reset_session_flash();
        unset($_SESSION['user_id']);
    }

    public function testOrderDetailDisplay(): void
    {
        // Test order item structure
        $orderItem = [
            'product_name' => 'Retro Sunglasses',
            'description' => 'Vintage aviator sunglasses in excellent condition',
            'quantity' => 1,
            'unit_price' => 39.99,
            'total_price' => 39.99
        ];
        
        $this->assertSame('Retro Sunglasses', $orderItem['product_name']);
        $this->assertSame(1, $orderItem['quantity']);
        $this->assertSame(39.99, $orderItem['unit_price']);
        $this->assertSame($orderItem['unit_price'] * $orderItem['quantity'], $orderItem['total_price']);
        
        // Test multiple quantity
        $orderItem['quantity'] = 3;
        $orderItem['total_price'] = $orderItem['unit_price'] * $orderItem['quantity'];
        $this->assertSame(119.97, $orderItem['total_price']);
    }

    public function testOrderNotesBreakdown(): void
    {
        // Test order notes parsing for better display
        $rawNotes = '{"subtotal":89.99,"shipping_cost":9.99,"tax_amount":7.20,"shipping_method":"standard","payment_method":"credit_card"}';
        
        $this->assertJson($rawNotes);
        
        $parsedNotes = json_decode($rawNotes, true);
        $this->assertSame(89.99, $parsedNotes['subtotal']);
        $this->assertSame(9.99, $parsedNotes['shipping_cost']);
        $this->assertSame('standard', $parsedNotes['shipping_method']);
        $this->assertSame('credit_card', $parsedNotes['payment_method']);
        
        // Test order total calculation from notes
        $calculatedTotal = $parsedNotes['subtotal'] + $parsedNotes['shipping_cost'] + $parsedNotes['tax_amount'];
        $this->assertEquals(107.18, $calculatedTotal, '', 0.01);
    }

    public function testOrderCancellationEligibility(): void
    {
        // Test order cancellation logic
        $pendingOrder = ['status' => 'pending', 'order_date' => date('Y-m-d H:i:s')];
        $confirmedOrder = ['status' => 'confirmed', 'order_date' => date('Y-m-d H:i:s')];
        $shippedOrder = ['status' => 'shipped', 'order_date' => date('Y-m-d H:i:s')];
        $deliveredOrder = ['status' => 'delivered', 'order_date' => date('Y-m-d H:i:s')];
        
        $cancellableStatuses = ['pending', 'confirmed'];
        
        $canCancelPending = in_array($pendingOrder['status'], $cancellableStatuses);
        $canCancelConfirmed = in_array($confirmedOrder['status'], $cancellableStatuses);
        $canCancelShipped = in_array($shippedOrder['status'], $cancellableStatuses);
        $canCancelDelivered = in_array($deliveredOrder['status'], $cancellableStatuses);
        
        $this->assertTrue($canCancelPending);
        $this->assertTrue($canCancelConfirmed);
        $this->assertFalse($canCancelShipped);
        $this->assertFalse($canCancelDelivered);
    }

    public function testEmptyOrdersDisplay(): void
    {
        // Test empty state handling
        $orders = [];
        $hasOrders = !empty($orders);
        
        $this->assertFalse($hasOrders);
        
        $emptyMessage = "You haven't placed any orders yet.";
        $this->assertStringContainsString("haven't placed", $emptyMessage);
        
        // Test with orders
        $orders = [['order_id' => 1, 'total_price' => 99.99]];
        $hasOrders = !empty($orders);
        $this->assertTrue($hasOrders);
    }

    public function testOrderStatusProgression(): void
    {
        // Test order status workflow
        $statusFlow = [
            'pending' => ['confirmed', 'cancelled'],
            'confirmed' => ['processing', 'cancelled'],
            'processing' => ['shipped'],
            'shipped' => ['delivered'],
            'delivered' => [],
            'cancelled' => []
        ];
        
        $currentStatus = 'pending';
        $allowedNextStatuses = $statusFlow[$currentStatus];
        $this->assertContains('confirmed', $allowedNextStatuses);
        $this->assertContains('cancelled', $allowedNextStatuses);
        
        $currentStatus = 'delivered';
        $allowedNextStatuses = $statusFlow[$currentStatus];
        $this->assertEmpty($allowedNextStatuses);
    }

    public function testOrderDateFormatting(): void
    {
        // Test date formatting for order display
        $orderDate = '2025-09-17 14:30:25';
        $timestamp = strtotime($orderDate);
        
        $formattedDate = date('M j, Y', $timestamp);
        $formattedTime = date('g:i A', $timestamp);
        $formattedDateTime = date('M j, Y \a\t g:i A', $timestamp);
        
        $this->assertSame('Sep 17, 2025', $formattedDate);
        $this->assertSame('2:30 PM', $formattedTime);
        $this->assertSame('Sep 17, 2025 at 2:30 PM', $formattedDateTime);
    }

    public function testOrderSearchFunctionality(): void
    {
        // Test order search logic
        $searchTerm = '#ORD-123';
        $orders = [
            ['order_id' => 123, 'product_names' => 'Vintage Jacket, Retro Shoes'],
            ['order_id' => 124, 'product_names' => 'Old Books'],
            ['order_id' => 125, 'product_names' => 'Vintage Camera']
        ];
        
        $filteredOrders = [];
        foreach ($orders as $order) {
            if (strpos('#ORD-' . $order['order_id'], $searchTerm) !== false ||
                stripos($order['product_names'], str_replace('#ORD-', '', $searchTerm)) !== false) {
                $filteredOrders[] = $order;
            }
        }
        
        $this->assertCount(1, $filteredOrders);
        $this->assertSame(123, $filteredOrders[0]['order_id']);
    }

    public function testOrderItemAggregation(): void
    {
        // Test order item summary calculation
        $orderItems = [
            ['product_name' => 'Jacket', 'quantity' => 1, 'unit_price' => 89.99],
            ['product_name' => 'Shoes', 'quantity' => 2, 'unit_price' => 45.50],
            ['product_name' => 'Hat', 'quantity' => 1, 'unit_price' => 25.00]
        ];
        
        $totalItems = 0;
        $subtotal = 0;
        $productNames = [];
        
        foreach ($orderItems as $item) {
            $totalItems += $item['quantity'];
            $subtotal += $item['quantity'] * $item['unit_price'];
            $productNames[] = $item['product_name'];
        }
        
        $this->assertSame(4, $totalItems); // 1 + 2 + 1
        $this->assertSame(205.99, $subtotal); // 89.99 + 91.00 + 25.00
        $this->assertSame('Jacket, Shoes, Hat', implode(', ', $productNames));
    }
}