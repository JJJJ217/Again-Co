<?php
/**
 * PHPUnit Bootstrap File
 * Sets up the testing environment for Again&Co E-commerce tests
 */

define('TESTING', true);

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Set project root as current working directory so relative includes work
chdir(dirname(__DIR__));

// Load core application files with error handling
$functionsFile = __DIR__ . '/../includes/functions.php';
$sessionFile = __DIR__ . '/../includes/session.php';

if (file_exists($functionsFile)) {
    require_once $functionsFile;
} else {
    echo "Warning: functions.php not found at: $functionsFile\n";
}

if (file_exists($sessionFile)) {
    require_once $sessionFile;
} else {
    echo "Warning: session.php not found at: $sessionFile\n";
}

// Test helper functions
function reset_session_flash(): void {
    unset($_SESSION['flash_message'], $_SESSION['flash_type']);
}

function mock_user_session(int $userId, string $role = 'customer'): void {
    $_SESSION['user_id'] = $userId;
    $_SESSION['user_role'] = $role;
}

function clear_user_session(): void {
    unset($_SESSION['user_id'], $_SESSION['user_role']);
}

// Mock database functions for testing if needed
if (!function_exists('getCartCount')) {
    function getCartCount(): int {
        return isset($_SESSION['user_id']) ? 0 : 0;
    }
}

if (!function_exists('calculateShipping')) {
    function calculateShipping(array $items, string $method, string $country): float {
        $totalWeight = 0;
        foreach ($items as $item) {
            $weight = $item['weight'] ?? 1.0;
            $totalWeight += $item['quantity'] * $weight;
        }
        
        $baseCost = $method === 'express' ? 15.00 : 10.00;
        $countrySurcharge = in_array($country, ['AU', 'UK']) ? 5.00 : 0.00;
        return $baseCost + ($totalWeight * 2.50) + $countrySurcharge;
    }
}

if (!function_exists('formatCurrency')) {
    function formatCurrency(float $amount): string {
        return '$' . number_format($amount, 2);
    }
}

// Initialize test environment
echo "Test environment initialized for Again&Co E-commerce\n";