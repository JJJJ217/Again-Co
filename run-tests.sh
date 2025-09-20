#!/bin/bash
# Simple test runner for debugging

echo "=== Again&Co Test Runner ==="
echo "PHP Version: $(php -v | head -n 1)"
echo "Current Directory: $(pwd)"
echo ""

echo "=== Checking PHP Extensions ==="
php -m | grep -E "(xml|mbstring|curl|zip|json)"
echo ""

echo "=== Validating Composer ==="
composer validate
echo ""

echo "=== Installing Dependencies ==="
composer install --no-interaction
echo ""

echo "=== Checking PHPUnit ==="
vendor/bin/phpunit --version
echo ""

echo "=== Running Smoke Test ==="
vendor/bin/phpunit tests/SmokeTest.php
echo ""

echo "=== Running All Tests ==="
vendor/bin/phpunit --testdox