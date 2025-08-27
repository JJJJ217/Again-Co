<?php

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Check if user is logged in
 */
function isLoggedIn() {
    return isset($_SESSION['user_id']) && isset($_SESSION['user_role']);
}

/**
 * Check if user has specific role
 */
function hasRole($required_role) {
    if (!isLoggedIn()) {
        return false;
    }
    
    $user_role = $_SESSION['user_role'];
    
    // Admin has access to everything
    if ($user_role === 'admin') {
        return true;
    }
    
    // Staff has access to staff and customer areas
    if ($user_role === 'staff' && in_array($required_role, ['staff', 'customer'])) {
        return true;
    }
    
    // Customer only has access to customer area
    return $user_role === $required_role;
}

/**
 * Require login - redirect if not logged in
 */
function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: ' . SITE_URL . '/pages/auth/login.php');
        exit();
    }
}

/**
 * Require specific role - redirect if insufficient permissions
 */
function requireRole($required_role) {
    requireLogin();
    
    if (!hasRole($required_role)) {
        header('Location: ' . SITE_URL . '/pages/error/403.php');
        exit();
    }
}

/**
 * Login user and create session
 */
function loginUser($user_data) {
    $_SESSION['user_id'] = $user_data['user_id'];
    $_SESSION['user_name'] = $user_data['name'];
    $_SESSION['user_email'] = $user_data['email'];
    $_SESSION['user_role'] = $user_data['role'];
    $_SESSION['login_time'] = time();
    
    // Regenerate session ID for security
    session_regenerate_id(true);
    
    // Update last login time in database
    try {
        $db = new Database();
        $db->query(
            "UPDATE users SET last_login = NOW(), login_attempts = 0, locked_until = NULL WHERE user_id = ?",
            [$user_data['user_id']]
        );
    } catch (Exception $e) {
        // Log error but don't break login process
        error_log("Failed to update last login: " . $e->getMessage());
    }
}

/**
 * Logout user and destroy session
 */
function logoutUser() {
    // Unset all session variables
    $_SESSION = array();
    
    // Destroy session cookie
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    
    // Destroy session
    session_destroy();
}

/**
 * Check session timeout
 */
function checkSessionTimeout() {
    if (isLoggedIn() && isset($_SESSION['login_time'])) {
        if (time() - $_SESSION['login_time'] > SESSION_LIFETIME) {
            logoutUser();
            header('Location: ' . SITE_URL . '/pages/auth/login.php?timeout=1');
            exit();
        }
    }
}

/**
 * Get current user data
 */
function getCurrentUser() {
    if (!isLoggedIn()) {
        return null;
    }
    
    return [
        'user_id' => $_SESSION['user_id'],
        'name' => $_SESSION['user_name'],
        'email' => $_SESSION['user_email'],
        'role' => $_SESSION['user_role']
    ];
}

/**
 * Generate CSRF token
 */
function generateCSRFToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Verify CSRF token
 */
function verifyCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Check account lockout
 */
function isAccountLocked($user_id) {
    try {
        $db = new Database();
        $user = $db->fetch(
            "SELECT login_attempts, locked_until FROM users WHERE user_id = ?",
            [$user_id]
        );
        
        if ($user) {
            // Check if account is temporarily locked
            if ($user['locked_until'] && strtotime($user['locked_until']) > time()) {
                return true;
            }
            
            // Check login attempts
            if ($user['login_attempts'] >= MAX_LOGIN_ATTEMPTS) {
                return true;
            }
        }
        
        return false;
    } catch (Exception $e) {
        return false;
    }
}

/**
 * Record failed login attempt
 */
function recordFailedLogin($email) {
    try {
        $db = new Database();
        
        // Increment login attempts
        $db->query(
            "UPDATE users SET login_attempts = login_attempts + 1 WHERE email = ?",
            [$email]
        );
        
        // Check if we need to lock the account
        $user = $db->fetch(
            "SELECT user_id, login_attempts FROM users WHERE email = ?",
            [$email]
        );
        
        if ($user && $user['login_attempts'] >= MAX_LOGIN_ATTEMPTS) {
            // Lock account for 30 minutes
            $lock_until = date('Y-m-d H:i:s', time() + 1800);
            $db->query(
                "UPDATE users SET locked_until = ? WHERE user_id = ?",
                [$lock_until, $user['user_id']]
            );
        }
    } catch (Exception $e) {
        error_log("Failed to record login attempt: " . $e->getMessage());
    }
}
?>
