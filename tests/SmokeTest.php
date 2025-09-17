<?php
use PHPUnit\Framework\TestCase;

/**
 * Basic Smoke Test
 * Verifies that the testing environment is set up correctly
 */
final class SmokeTest extends TestCase
{
    public function test_php_version(): void
    {
        $this->assertGreaterThanOrEqual('8.0', PHP_VERSION);
    }

    public function test_testing_constant_defined(): void
    {
        $this->assertTrue(defined('TESTING'));
        $this->assertTrue(TESTING);
    }

    public function test_session_available(): void
    {
        $this->assertTrue(session_status() !== PHP_SESSION_DISABLED);
    }

    public function test_basic_math(): void
    {
        $this->assertSame(4, 2 + 2);
        $this->assertSame(10.5, 5.25 * 2);
    }

    public function test_string_operations(): void
    {
        $this->assertSame('Hello World', 'Hello' . ' ' . 'World');
        $this->assertSame('hello', strtolower('HELLO'));
    }
}