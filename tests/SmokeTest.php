<?php
use PHPUnit\Framework\TestCase;

/**
 * Basic Smoke Test
 * Verifies that the testing environment is set up correctly
 */
final class SmokeTest extends TestCase
{
    public function testPhpVersion(): void
    {
        $this->assertGreaterThanOrEqual('8.0', PHP_VERSION);
    }

    public function testTestingConstantDefined(): void
    {
        $this->assertTrue(defined('TESTING'));
        $this->assertTrue(TESTING);
    }

    public function testBasicMath(): void
    {
        $this->assertSame(4, 2 + 2);
        $this->assertSame(10.5, 5.25 * 2);
    }

    public function testStringOperations(): void
    {
        $this->assertSame('Hello World', 'Hello' . ' ' . 'World');
        $this->assertSame('hello', strtolower('HELLO'));
    }

    public function testArrayOperations(): void
    {
        $array = ['a', 'b', 'c'];
        $this->assertCount(3, $array);
        $this->assertContains('b', $array);
    }
}