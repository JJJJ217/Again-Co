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
        clear_user_session();
    }

    public function testBasicStringOperations(): void
    {
        $this->assertSame('test', trim('  test  '));
        $this->assertSame('TEST', strtoupper('test'));
        $this->assertSame('Hello World', 'Hello' . ' ' . 'World');
    }

    public function testBasicArrayOperations(): void
    {
        $array = ['a', 'b', 'c'];
        $this->assertCount(3, $array);
        $this->assertContains('b', $array);
        $this->assertSame('a', $array[0]);
    }

    public function testBasicMathOperations(): void
    {
        $this->assertSame(4, 2 + 2);
        $this->assertSame(10, 5 * 2);
        $this->assertSame(2.5, 5 / 2);
    }

    public function testHtmlEscaping(): void
    {
        $input = '<script>alert("xss")</script>';
        $expected = '&lt;script&gt;alert(&quot;xss&quot;)&lt;/script&gt;';
        $this->assertSame($expected, htmlspecialchars($input, ENT_QUOTES, 'UTF-8'));
        
        // Test safeHtml function
        $this->assertSame('', safeHtml(null));
        $this->assertSame('&lt;b&gt;', safeHtml('<b>'));
        $this->assertSame('Default', safeHtml(null, 'Default'));
    }

    public function testEmailValidation(): void
    {
        $this->assertTrue(filter_var('test@example.com', FILTER_VALIDATE_EMAIL) !== false);
        $this->assertFalse(filter_var('invalid-email', FILTER_VALIDATE_EMAIL) !== false);
    }

    public function testFlashMessages(): void
    {
        // Clear any existing session data
        $_SESSION = [];
        
        setFlashMessage('Test message', 'success');
        $message = getFlashMessage();
        
        // Handle both string and array formats
        if (is_array($message)) {
            $this->assertSame('Test message', $message['message']);
            $this->assertSame('success', $message['type']);
        } else {
            $this->assertSame('Test message', $message);
        }
        
        $this->assertNull(getFlashMessage()); // Should be cleared after read
    }

    public function testSanitizeInput(): void
    {
        $this->assertSame('test', sanitizeInput('  test  '));
        $this->assertSame('hello world', sanitizeInput('hello world'));
        $this->assertSame('', sanitizeInput(''));
    }

    public function testFormatCurrency(): void
    {
        $this->assertSame('$9.99', formatCurrency(9.99));
        $this->assertSame('$107.18', formatCurrency(107.18));
        $this->assertSame('$0.00', formatCurrency(0));
    }
}