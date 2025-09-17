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
    function calculateShipping(array $items, string $method = 'standard', string $country = 'US'): float {
        $totalWeight = 0;
        foreach ($items as $item) {
            $weight = $item['weight'] ?? 1.0;
            $quantity = $item['quantity'] ?? 1;
            $totalWeight += $quantity * $weight;
        }
        
        // Base costs for different methods
        $baseCost = ($method === 'express') ? 15.00 : 10.00;
        $weightCost = $totalWeight * 2.50;
        $countrySurcharge = in_array($country, ['AU', 'UK']) ? 5.00 : 0.00;
        
        return $baseCost + $weightCost + $countrySurcharge;
    }
}

if (!function_exists('formatCurrency')) {
    function formatCurrency(float $amount): string {
        return '$' . number_format($amount, 2);
    }
}

// Mock functions for testing - these override any existing functions
function hasRole($roles): bool {
    if (!isset($_SESSION['user_role'])) {
        return false;
    }
    
    if (is_array($roles)) {
        return in_array($_SESSION['user_role'], $roles);
    }
    
    return $_SESSION['user_role'] === $roles;
}

function isAdmin(): bool {
    return isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin';
}

function isStaff(): bool {
    return isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'staff';
}

if (!function_exists('safeHtml')) {
    function safeHtml($value, $default = ''): string {
        if ($value === null || $value === '') {
            return $default;
        }
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('setFlashMessage')) {
    function setFlashMessage(string $message, string $type = 'info'): void {
        $_SESSION['flash_message'] = $message;
        $_SESSION['flash_type'] = $type;
    }
}

if (!function_exists('getFlashMessage')) {
    function getFlashMessage(): ?string {
        if (isset($_SESSION['flash_message'])) {
            $message = $_SESSION['flash_message'];
            unset($_SESSION['flash_message'], $_SESSION['flash_type']);
            return $message;
        }
        return null;
    }
}

if (!function_exists('sanitizeInput')) {
    function sanitizeInput($data): string {
        if (is_array($data)) {
            return '';
        }
        return trim(htmlspecialchars($data, ENT_QUOTES, 'UTF-8'));
    }
}

// Initialize test environment
echo "Test environment initialized for Again&Co E-commerce\n";