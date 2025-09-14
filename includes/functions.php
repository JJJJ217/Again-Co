<?php
/**
 * Common utility functions for Again&Co application
 */

/**
 * Sanitize input data
 */
function sanitizeInput($data) {
    if (is_array($data)) {
        return array_map('sanitizeInput', $data);
    }
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}

/**
 * Validate email address
 */
function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Validate password strength
 */
function validatePassword($password) {
    $errors = [];
    
    if (strlen($password) < PASSWORD_MIN_LENGTH) {
        $errors[] = "Password must be at least " . PASSWORD_MIN_LENGTH . " characters long";
    }
    
    if (!preg_match('/[A-Z]/', $password)) {
        $errors[] = "Password must contain at least one uppercase letter";
    }
    
    if (!preg_match('/[a-z]/', $password)) {
        $errors[] = "Password must contain at least one lowercase letter";
    }
    
    if (!preg_match('/[0-9]/', $password)) {
        $errors[] = "Password must contain at least one number";
    }
    
    return $errors;
}

/**
 * Hash password securely
 */
function hashPassword($password) {
    return password_hash($password, PASSWORD_DEFAULT);
}

/**
 * Verify password against hash
 */
function verifyPassword($password, $hash) {
    return password_verify($password, $hash);
}

/**
 * Generate random token
 */
function generateToken($length = 32) {
    return bin2hex(random_bytes($length));
}

/**
 * Format currency
 */
function formatCurrency($amount) {
    return '$' . number_format($amount, 2);
}

/**
 * Format date
 */
function formatDate($date, $format = 'Y-m-d H:i:s') {
    if (is_string($date)) {
        $date = new DateTime($date);
    }
    return $date->format($format);
}

/**
 * Redirect with message
 */
function redirectWithMessage($url, $message, $type = 'info') {
    $_SESSION['flash_message'] = $message;
    $_SESSION['flash_type'] = $type;
    header('Location: ' . $url);
    exit();
}

/**
 * Get and clear flash message
 */
function getFlashMessage() {
    if (isset($_SESSION['flash_message'])) {
        $message = $_SESSION['flash_message'];
        $type = $_SESSION['flash_type'] ?? 'info';
        unset($_SESSION['flash_message'], $_SESSION['flash_type']);
        return ['message' => $message, 'type' => $type];
    }
    return null;
}

/**
 * Set flash message
 */
function setFlashMessage($message, $type = 'info') {
    $_SESSION['flash_message'] = $message;
    $_SESSION['flash_type'] = $type;
}

/**
 * Safely output HTML by handling null values
 */
function safeHtml($value, $default = '') {
    return htmlspecialchars($value ?? $default, ENT_QUOTES, 'UTF-8');
}

/**
 * Generate breadcrumb navigation
 */
function generateBreadcrumb($items) {
    $html = '<nav aria-label="breadcrumb"><ol class="breadcrumb">';
    
    $total = count($items);
    $current = 0;
    
    foreach ($items as $text => $url) {
        $current++;
        $isLast = ($current === $total);
        
        if ($isLast) {
            $html .= '<li class="breadcrumb-item active" aria-current="page">' . $text . '</li>';
        } else {
            $html .= '<li class="breadcrumb-item"><a href="' . $url . '">' . $text . '</a></li>';
        }
    }
    
    $html .= '</ol></nav>';
    return $html;
}

/**
 * Create pagination links
 */
function createPagination($current_page, $total_pages, $base_url) {
    if ($total_pages <= 1) {
        return '';
    }
    
    $html = '<nav aria-label="Page navigation"><ul class="pagination justify-content-center">';
    
    // Previous button
    if ($current_page > 1) {
        $html .= '<li class="page-item"><a class="page-link" href="' . $base_url . '&page=' . ($current_page - 1) . '">Previous</a></li>';
    } else {
        $html .= '<li class="page-item disabled"><span class="page-link">Previous</span></li>';
    }
    
    // Page numbers
    $start = max(1, $current_page - 2);
    $end = min($total_pages, $current_page + 2);
    
    if ($start > 1) {
        $html .= '<li class="page-item"><a class="page-link" href="' . $base_url . '&page=1">1</a></li>';
        if ($start > 2) {
            $html .= '<li class="page-item disabled"><span class="page-link">...</span></li>';
        }
    }
    
    for ($i = $start; $i <= $end; $i++) {
        if ($i == $current_page) {
            $html .= '<li class="page-item active"><span class="page-link">' . $i . '</span></li>';
        } else {
            $html .= '<li class="page-item"><a class="page-link" href="' . $base_url . '&page=' . $i . '">' . $i . '</a></li>';
        }
    }
    
    if ($end < $total_pages) {
        if ($end < $total_pages - 1) {
            $html .= '<li class="page-item disabled"><span class="page-link">...</span></li>';
        }
        $html .= '<li class="page-item"><a class="page-link" href="' . $base_url . '&page=' . $total_pages . '">' . $total_pages . '</a></li>';
    }
    
    // Next button
    if ($current_page < $total_pages) {
        $html .= '<li class="page-item"><a class="page-link" href="' . $base_url . '&page=' . ($current_page + 1) . '">Next</a></li>';
    } else {
        $html .= '<li class="page-item disabled"><span class="page-link">Next</span></li>';
    }
    
    $html .= '</ul></nav>';
    return $html;
}

/**
 * Log activity
 */
function logActivity($user_id, $action, $details = '') {
    try {
        $db = new Database();
        $db->query(
            "INSERT INTO activity_logs (user_id, action, details, ip_address, user_agent, created_at) VALUES (?, ?, ?, ?, ?, NOW())",
            [$user_id, $action, $details, $_SERVER['REMOTE_ADDR'] ?? '', $_SERVER['HTTP_USER_AGENT'] ?? '']
        );
    } catch (Exception $e) {
        error_log("Failed to log activity: " . $e->getMessage());
    }
}

/**
 * Upload file securely
 */
function uploadFile($file, $allowed_types, $upload_dir, $max_size = null) {
    if (!isset($file['error']) || is_array($file['error'])) {
        throw new Exception('Invalid file upload');
    }
    
    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('File upload failed');
    }
    
    $max_size = $max_size ?? UPLOAD_MAX_SIZE;
    if ($file['size'] > $max_size) {
        throw new Exception('File size exceeds maximum allowed size');
    }
    
    $file_info = pathinfo($file['name']);
    $extension = strtolower($file_info['extension']);
    
    if (!in_array($extension, $allowed_types)) {
        throw new Exception('File type not allowed');
    }
    
    $filename = uniqid() . '.' . $extension;
    $destination = $upload_dir . '/' . $filename;
    
    if (!move_uploaded_file($file['tmp_name'], $destination)) {
        throw new Exception('Failed to move uploaded file');
    }
    
    return $filename;
}

/**
 * Send email (basic implementation)
 */
function sendEmail($to, $subject, $message, $headers = []) {
    $default_headers = [
        'From' => SITE_EMAIL,
        'Reply-To' => SITE_EMAIL,
        'Content-Type' => 'text/html; charset=UTF-8',
        'X-Mailer' => 'PHP/' . phpversion()
    ];
    
    $headers = array_merge($default_headers, $headers);
    $header_string = '';
    
    foreach ($headers as $key => $value) {
        $header_string .= $key . ': ' . $value . "\r\n";
    }
    
    return mail($to, $subject, $message, $header_string);
}

/**
 * Generate star rating HTML
 */
function generateStarRating($rating, $max_stars = 5) {
    $full_stars = floor($rating);
    $half_star = ($rating - $full_stars) >= 0.5;
    $empty_stars = $max_stars - $full_stars - ($half_star ? 1 : 0);
    
    $html = '';
    
    // Full stars
    for ($i = 0; $i < $full_stars; $i++) {
        $html .= '<span class="star filled">★</span>';
    }
    
    // Half star
    if ($half_star) {
        $html .= '<span class="star half">☆</span>';
    }
    
    // Empty stars
    for ($i = 0; $i < $empty_stars; $i++) {
        $html .= '<span class="star empty">☆</span>';
    }
    
    return $html;
}

/**
 * Calculate shipping cost based on items and address
 */
function calculateShipping($cart_items, $address = []) {
    $total_weight = 0;
    $base_cost = 9.99; // Standard shipping base cost
    
    foreach ($cart_items as $item) {
        $weight = $item['weight'] ?? 1.0; // Default 1 lb if no weight specified
        $total_weight += $weight * $item['quantity'];
    }
    
    // Weight-based pricing
    if ($total_weight > 10) {
        $base_cost += ($total_weight - 10) * 2; // $2 per lb over 10 lbs
    }
    
    // International shipping
    if (!empty($address['country']) && $address['country'] !== 'US') {
        $base_cost += 15; // Additional $15 for international
    }
    
    return $base_cost;
}

/**
 * Simulate payment processing
 */
function processPayment($payment_method, $amount) {
    // Simulate payment processing
    // In a real application, this would integrate with payment gateways
    
    $success_rate = 0.95; // 95% success rate for simulation
    
    if (rand(1, 100) <= ($success_rate * 100)) {
        return [
            'success' => true,
            'transaction_id' => 'txn_' . uniqid(),
            'message' => 'Payment processed successfully'
        ];
    } else {
        return [
            'success' => false,
            'message' => 'Payment declined. Please try a different payment method.'
        ];
    }
}

/**
 * Generate pagination links
 */
function generatePagination($current_page, $total_pages, $params = []) {
    if ($total_pages <= 1) return '';
    
    $html = '<div class="pagination">';
    
    // Previous button
    if ($current_page > 1) {
        $prev_params = array_merge($params, ['page' => $current_page - 1]);
        $prev_url = '?' . http_build_query($prev_params);
        $html .= '<a href="' . $prev_url . '" class="pagination-btn">‹ Previous</a>';
    }
    
    // Page numbers
    $start = max(1, $current_page - 2);
    $end = min($total_pages, $current_page + 2);
    
    if ($start > 1) {
        $first_params = array_merge($params, ['page' => 1]);
        $first_url = '?' . http_build_query($first_params);
        $html .= '<a href="' . $first_url . '" class="pagination-btn">1</a>';
        if ($start > 2) {
            $html .= '<span class="pagination-ellipsis">...</span>';
        }
    }
    
    for ($i = $start; $i <= $end; $i++) {
        if ($i == $current_page) {
            $html .= '<span class="pagination-btn active">' . $i . '</span>';
        } else {
            $page_params = array_merge($params, ['page' => $i]);
            $page_url = '?' . http_build_query($page_params);
            $html .= '<a href="' . $page_url . '" class="pagination-btn">' . $i . '</a>';
        }
    }
    
    if ($end < $total_pages) {
        if ($end < $total_pages - 1) {
            $html .= '<span class="pagination-ellipsis">...</span>';
        }
        $last_params = array_merge($params, ['page' => $total_pages]);
        $last_url = '?' . http_build_query($last_params);
        $html .= '<a href="' . $last_url . '" class="pagination-btn">' . $total_pages . '</a>';
    }
    
    // Next button
    if ($current_page < $total_pages) {
        $next_params = array_merge($params, ['page' => $current_page + 1]);
        $next_url = '?' . http_build_query($next_params);
        $html .= '<a href="' . $next_url . '" class="pagination-btn">Next ›</a>';
    }
    
    $html .= '</div>';
    return $html;
}

/**
 * Get condition text from rating
 */
function getConditionText($rating) {
    switch ($rating) {
        case 5: return 'Mint';
        case 4: return 'Excellent';
        case 3: return 'Good';
        case 2: return 'Fair';
        case 1: return 'Poor';
        default: return 'Unrated';
    }
}

/**
 * Get cart item count for current user
 */
function getCartCount() {
    global $db;
    
    if (!isLoggedIn()) {
        return 0;
    }
    
    $user = getCurrentUser();
    if (!$user) {
        return 0;
    }
    
    try {
        $result = $db->fetch(
            "SELECT COALESCE(SUM(quantity), 0) as total_count 
             FROM shopping_cart 
             WHERE user_id = ?",
            [$user['user_id']]
        );
        
        return (int)$result['total_count'];
    } catch (Exception $e) {
        error_log("Error getting cart count: " . $e->getMessage());
        return 0;
    }
}
?>
