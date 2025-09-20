<?php
/**
 * User Login Page
 * Implements F101 - Login functionality
 * Story 102: Login with email and password
 * Story 103: Role-based system recognition
 */

require_once '../../includes/init.php';

// Redirect if already logged in
if (isLoggedIn()) {
    $user = getCurrentUser();
    $redirect_url = SITE_URL;
    
    if ($user['role'] === 'admin' || $user['role'] === 'staff') {
        $redirect_url = SITE_URL . '/pages/admin/dashboard.php';
    } else {
        $redirect_url = SITE_URL . '/pages/user/profile.php';
    }
    
    redirectWithMessage($redirect_url, 'You are already logged in.', 'info');
}

$errors = [];
$form_data = [];
$login_attempts_exceeded = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get and sanitize form data
    $form_data = [
        'email' => sanitizeInput($_POST['email'] ?? ''),
        'password' => $_POST['password'] ?? '',
        'remember_me' => isset($_POST['remember_me'])
    ];
    
    // Validate required fields
    if (empty($form_data['email'])) {
        $errors['email'] = 'Email is required';
    } elseif (!validateEmail($form_data['email'])) {
        $errors['email'] = 'Please enter a valid email address';
    }
    
    if (empty($form_data['password'])) {
        $errors['password'] = 'Password is required';
    }
    
    // If basic validation passes, attempt login
    if (empty($errors)) {
        try {
            // Get user by email
            $user = $db->fetch(
                "SELECT u.*, p.street_name, p.postcode, p.suburb, p.state, p.phone 
                 FROM users u 
                 LEFT JOIN user_profiles p ON u.user_id = p.user_id 
                 WHERE u.email = ? AND u.is_active = 1",
                [$form_data['email']]
            );
            
            if ($user) {
                // Check if account is locked
                if (isAccountLocked($user['user_id'])) {
                    $errors['general'] = 'Account temporarily locked due to too many failed login attempts. Please try again later.';
                    $login_attempts_exceeded = true;
                } else {
                    // Verify password
                    if (verifyPassword($form_data['password'], $user['password'])) {
                        // Login successful
                        
                        // Store user data for session
                        $user_data = [
                            'user_id' => $user['user_id'],
                            'name' => $user['name'],
                            'email' => $user['email'],
                            'role' => $user['role']
                        ];
                        
                        // Create session
                        loginUser($user_data);
                        
                        // Log successful login
                        logActivity($user['user_id'], 'user_login', 'Successful login');
                        
                        // Handle "remember me" functionality
                        if ($form_data['remember_me']) {
                            // Set longer session lifetime
                            ini_set('session.gc_maxlifetime', 30 * 24 * 60 * 60); // 30 days
                            session_set_cookie_params(30 * 24 * 60 * 60); // 30 days
                        }
                        
                        // Redirect based on user role
                        $redirect_url = SITE_URL;
                        $message = 'Welcome back to Again&Co!';
                        
                        // Check for redirect parameter
                        if (isset($_GET['redirect']) && !empty($_GET['redirect'])) {
                            $redirect_url = SITE_URL . '/' . ltrim($_GET['redirect'], '/');
                        } else {
                            // Default redirects based on role
                            switch ($user['role']) {
                                case 'admin':
                                    $redirect_url = SITE_URL . '/pages/admin/dashboard.php';
                                    $message = 'Welcome back, Administrator!';
                                    break;
                                case 'staff':
                                    $redirect_url = SITE_URL . '/pages/admin/dashboard.php';
                                    $message = 'Welcome back, Staff Member!';
                                    break;
                                case 'customer':
                                default:
                                    $redirect_url = SITE_URL . '/pages/user/profile.php';
                                    break;
                            }
                        }
                        
                        redirectWithMessage($redirect_url, $message, 'success');
                        
                    } else {
                        // Invalid password
                        recordFailedLogin($form_data['email']);
                        $errors['general'] = 'Invalid email or password';
                        
                        // Log failed login attempt
                        if ($user) {
                            logActivity($user['user_id'], 'login_failed', 'Invalid password');
                        }
                    }
                }
            } else {
                // User not found
                $errors['general'] = 'Invalid email or password';
                
                // Still record the failed attempt for security
                recordFailedLogin($form_data['email']);
            }
            
        } catch (Exception $e) {
            $errors['general'] = 'Login failed. Please try again.';
            error_log("Login error: " . $e->getMessage());
        }
    }
}

// Check for logout message
$logout_message = '';
if (isset($_GET['logged_out'])) {
    $logout_message = 'You have been successfully logged out.';
}

// Check for session timeout
$timeout_message = '';
if (isset($_GET['timeout'])) {
    $timeout_message = 'Your session has expired. Please log in again.';
}

$page_title = "Login - Again&Co";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $page_title ?></title>
    <link rel="stylesheet" href="../../assets/css/style.css">
</head>
<body>
    <?php include '../../includes/header.php'; ?>
    
    <main class="main-content">
        <div class="container">
            <div class="card" style="max-width: 450px; margin: 0 auto;">
                <div class="card-header">
                    <h1 class="card-title">Login to Your Account</h1>
                    <p>Welcome back to Again&Co</p>
                </div>
                
                <?php if ($logout_message): ?>
                    <div class="alert alert-success">
                        <?= htmlspecialchars($logout_message) ?>
                    </div>
                <?php endif; ?>
                
                <?php if ($timeout_message): ?>
                    <div class="alert alert-warning">
                        <?= htmlspecialchars($timeout_message) ?>
                    </div>
                <?php endif; ?>
                
                <?php if (!empty($errors['general'])): ?>
                    <div class="alert alert-error">
                        <?= htmlspecialchars($errors['general']) ?>
                        <?php if ($login_attempts_exceeded): ?>
                            <br><small>For security reasons, this account has been temporarily locked.</small>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
                
                <form method="POST" action="" class="needs-validation" novalidate>
                    <div class="form-group">
                        <label for="email" class="form-label">Email Address</label>
                        <input type="email" 
                               id="email" 
                               name="email" 
                               class="form-control email <?= isset($errors['email']) ? 'error' : '' ?>"
                               value="<?= htmlspecialchars($form_data['email'] ?? '') ?>"
                               required
                               autocomplete="email">
                        <?php if (isset($errors['email'])): ?>
                            <div class="error-message"><?= htmlspecialchars($errors['email']) ?></div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="form-group">
                        <label for="password" class="form-label">Password</label>
                        <input type="password" 
                               id="password" 
                               name="password" 
                               class="form-control <?= isset($errors['password']) ? 'error' : '' ?>"
                               required
                               autocomplete="current-password">
                        <?php if (isset($errors['password'])): ?>
                            <div class="error-message"><?= htmlspecialchars($errors['password']) ?></div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-check">
                            <input type="checkbox" 
                                   name="remember_me" 
                                   class="form-check-input"
                                   <?= ($form_data['remember_me'] ?? false) ? 'checked' : '' ?>>
                            <span class="form-check-label">Remember me for 30 days</span>
                        </label>
                    </div>
                    
                    <div class="form-group">
                        <button type="submit" class="btn btn-primary btn-block">Login</button>
                    </div>
                    
                    <div class="text-center">
                        <a href="forgot-password.php">Forgot your password?</a>
                    </div>
                    
                    <hr class="mt-4 mb-3">
                    
                    <div class="text-center">
                        <p>Don't have an account yet?</p>
                        <a href="register.php" class="btn btn-secondary">Create Account</a>
                    </div>
                </form>
                
                <!-- Demo Accounts Info -->
                <div class="demo-accounts mt-4" style="background-color: #f8f9fa; padding: 1rem; border-radius: 5px;">
                    <h4>Demo Accounts</h4>
                    <p><small>For testing purposes, you can use these accounts:</small></p>
                    <ul style="font-size: 0.875rem;">
                        <li><strong>Admin:</strong> admin@evinty.com / admin123</li>
                        <li><strong>Staff:</strong> Create a staff account via registration</li>
                        <li><strong>Customer:</strong> Create a customer account via registration</li>
                    </ul>
                </div>
            </div>
        </div>
    </main>
    
    <?php include '../../includes/footer.php'; ?>
    <script src="../../assets/js/main.js"></script>
</body>
</html>
