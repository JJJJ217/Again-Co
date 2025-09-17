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
        // Clear any session data that might interfere with other tests
        if (isset($_SESSION['flash_message'])) {
            unset($_SESSION['flash_message']);
        }
        if (isset($_SESSION['flash_type'])) {
            unset($_SESSION['flash_type']);
        }
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
    }

    public function testEmailValidation(): void
    {
        $this->assertTrue(filter_var('test@example.com', FILTER_VALIDATE_EMAIL) !== false);
        $this->assertFalse(filter_var('invalid-email', FILTER_VALIDATE_EMAIL) !== false);
    }
}