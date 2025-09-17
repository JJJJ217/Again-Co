<?php
use PHPUnit\Framework\TestCase;

/**
 * Basic Test to verify PHPUnit setup
 * This test should always pass if PHPUnit is working correctly
 */
final class BasicTest extends TestCase
{
    public function testPhpUnitSetup(): void
    {
        $this->assertTrue(true, 'PHPUnit is working');
    }

    public function testPhpVersion(): void
    {
        $this->assertGreaterThanOrEqual('8.0', PHP_VERSION);
    }

    public function testSessionSupport(): void
    {
        $this->assertTrue(function_exists('session_start'));
    }

    public function testJsonSupport(): void
    {
        $this->assertTrue(function_exists('json_encode'));
        $this->assertTrue(function_exists('json_decode'));
    }
}