<?php
use PHPUnit\Framework\TestCase;

/**
 * Core Functions Test
 * Tests the fundamental utility functions used throughout the application
 */
final class FunctionsTest extends TestCase
{
    protected function tearDown(): void
    {
        reset_session_flash();
    }

    public function test_safe_html_handles_null_and_escapes(): void
    {
        $this->assertSame('', safeHtml(null));
        $this->assertSame('&lt;b&gt;', safeHtml('<b>'));
        $this->assertSame('Default', safeHtml(null, 'Default'));
        $this->assertSame('O&#039;Hara', safeHtml("O'Hara"));
        $this->assertSame('&quot;Quote&quot;', safeHtml('"Quote"'));
    }

    public function test_flash_message_roundtrip(): void
    {
        setFlashMessage('Order placed successfully!', 'success');
        $this->assertSame('Order placed successfully!', getFlashMessage());
        // After read once it is cleared
        $this->assertNull(getFlashMessage());
    }

    public function test_flash_message_with_different_types(): void
    {
        setFlashMessage('Error occurred', 'error');
        $this->assertSame('Error occurred', getFlashMessage());
        $this->assertNull(getFlashMessage()); // Should be cleared
        
        setFlashMessage('Warning message', 'warning');
        $this->assertSame('Warning message', getFlashMessage());
    }

    public function test_format_currency_exists_and_formats(): void
    {
        if (!function_exists('formatCurrency')) {
            $this->markTestSkipped('formatCurrency not defined in this project.');
        }
        $this->assertSame('$9.99', formatCurrency(9.99));
        $this->assertSame('$107.18', formatCurrency(107.18));
        $this->assertSame('$0.00', formatCurrency(0));
    }

    public function test_get_cart_count_with_no_user(): void
    {
        // Test cart count when no user is logged in
        unset($_SESSION['user_id']);
        $this->assertSame(0, getCartCount());
    }

    public function test_get_cart_count_with_user(): void
    {
        // Mock a user session
        $_SESSION['user_id'] = 1;
        $count = getCartCount();
        $this->assertIsInt($count);
        $this->assertGreaterThanOrEqual(0, $count);
    }

    public function test_sanitize_input_function(): void
    {
        if (!function_exists('sanitizeInput')) {
            $this->markTestSkipped('sanitizeInput not defined in this project.');
        }
        
        $this->assertSame('test', sanitizeInput('  test  '));
        $this->assertSame('hello world', sanitizeInput('hello world'));
        $this->assertSame('', sanitizeInput(''));
    }
}