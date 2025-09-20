<?php
/**
 * User Registration Page
 * Implements F101 - Registration functionality
 * Story 101: Staff/Admin registration with email
 */

require_once '../../includes/init.php';

// Redirect if already logged in
if (isLoggedIn()) {
    redirectWithMessage(SITE_URL . '/pages/user/profile.php', 'You are already logged in.', 'info');
}

$errors = [];
$form_data = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get and sanitize form data
    $form_data = [
        'name' => sanitizeInput($_POST['name'] ?? ''),
        'email' => sanitizeInput($_POST['email'] ?? ''),
        'password' => $_POST['password'] ?? '',
        'confirm_password' => $_POST['confirm_password'] ?? '',
        'role' => sanitizeInput($_POST['role'] ?? 'customer'),
        'street_name' => sanitizeInput($_POST['street_name'] ?? ''),
        'postcode' => sanitizeInput($_POST['postcode'] ?? ''),
        'suburb' => sanitizeInput($_POST['suburb'] ?? ''),
        'state' => sanitizeInput($_POST['state'] ?? ''),
        'phone' => sanitizeInput($_POST['phone'] ?? ''),
        'terms_accepted' => isset($_POST['terms_accepted'])
    ];
    
    // Validate required fields
    if (empty($form_data['name'])) {
        $errors['name'] = 'Name is required';
    } elseif (strlen($form_data['name']) < 2) {
        $errors['name'] = 'Name must be at least 2 characters long';
    }
    
    if (empty($form_data['email'])) {
        $errors['email'] = 'Email is required';
    } elseif (!validateEmail($form_data['email'])) {
        $errors['email'] = 'Please enter a valid email address';
    }
    
    if (empty($form_data['password'])) {
        $errors['password'] = 'Password is required';
    } else {
        $password_errors = validatePassword($form_data['password']);
        if (!empty($password_errors)) {
            $errors['password'] = implode('. ', $password_errors);
        }
    }
    
    if (empty($form_data['confirm_password'])) {
        $errors['confirm_password'] = 'Please confirm your password';
    } elseif ($form_data['password'] !== $form_data['confirm_password']) {
        $errors['confirm_password'] = 'Passwords do not match';
    }
    
    if (!in_array($form_data['role'], ['customer', 'staff', 'admin'])) {
        $errors['role'] = 'Invalid role selected';
    }
    
    if (!$form_data['terms_accepted']) {
        $errors['terms_accepted'] = 'You must accept the terms and conditions';
    }
    
    // Check if email already exists
    if (empty($errors['email'])) {
        try {
            $existing_user = $db->fetch(
                "SELECT user_id FROM users WHERE email = ?",
                [$form_data['email']]
            );
            
            if ($existing_user) {
                $errors['email'] = 'An account with this email address already exists';
            }
        } catch (Exception $e) {
            $errors['general'] = 'Database error occurred. Please try again.';
            error_log("Registration email check error: " . $e->getMessage());
        }
    }
    
    // If no errors, create the user account
    if (empty($errors)) {
        try {
            $db->connect()->beginTransaction();
            
            // Hash password
            $hashed_password = hashPassword($form_data['password']);
            
            // Insert user record
            $db->query(
                "INSERT INTO users (name, email, password, role, is_active, email_verified, created_at) 
                 VALUES (?, ?, ?, ?, 1, 0, NOW())",
                [
                    $form_data['name'],
                    $form_data['email'],
                    $hashed_password,
                    $form_data['role']
                ]
            );
            
            $user_id = $db->lastInsertId();
            
            // Insert user profile if address information provided
            if (!empty($form_data['street_name']) || !empty($form_data['phone'])) {
                $db->query(
                    "INSERT INTO user_profiles (user_id, street_name, postcode, suburb, state, phone, created_at)
                     VALUES (?, ?, ?, ?, ?, ?, NOW())",
                    [
                        $user_id,
                        $form_data['street_name'],
                        $form_data['postcode'],
                        $form_data['suburb'],
                        $form_data['state'],
                        $form_data['phone']
                    ]
                );
            }
            
            $db->connect()->commit();
            
            // Log activity
            logActivity($user_id, 'user_registered', "Role: {$form_data['role']}");
            
            // Auto-login the user
            $user_data = [
                'user_id' => $user_id,
                'name' => $form_data['name'],
                'email' => $form_data['email'],
                'role' => $form_data['role']
            ];
            
            loginUser($user_data);
            
            // Redirect to appropriate dashboard
            $redirect_url = SITE_URL;
            if ($form_data['role'] === 'admin' || $form_data['role'] === 'staff') {
                $redirect_url = SITE_URL . '/pages/admin/dashboard.php';
            } else {
                $redirect_url = SITE_URL . '/pages/user/profile.php';
            }
            
            redirectWithMessage($redirect_url, 'Account created successfully! Welcome to Again&Co.', 'success');
            
        } catch (Exception $e) {
            $db->connect()->rollBack();
            $errors['general'] = 'Registration failed. Please try again.';
            error_log("Registration error: " . $e->getMessage());
        }
    }
}

$page_title = "Register - Again&Co";
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
            <div class="card" style="max-width: 600px; margin: 0 auto;">
                <div class="card-header">
                    <h1 class="card-title">Create Your Account</h1>
                    <p>Join Again&Co and start shopping for unique vintage items</p>
                </div>
                
                <?php if (!empty($errors['general'])): ?>
                    <div class="alert alert-error">
                        <?= htmlspecialchars($errors['general']) ?>
                    </div>
                <?php endif; ?>
                
                <form method="POST" action="" class="needs-validation" novalidate>
                    <!-- Personal Information -->
                    <h3 class="mb-3">Personal Information</h3>
                    
                    <div class="form-group">
                        <label for="name" class="form-label">Full Name *</label>
                        <input type="text" 
                               id="name" 
                               name="name" 
                               class="form-control <?= isset($errors['name']) ? 'error' : '' ?>"
                               value="<?= htmlspecialchars($form_data['name'] ?? '') ?>"
                               required>
                        <?php if (isset($errors['name'])): ?>
                            <div class="error-message"><?= htmlspecialchars($errors['name']) ?></div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="form-group">
                        <label for="email" class="form-label">Email Address *</label>
                        <input type="email" 
                               id="email" 
                               name="email" 
                               class="form-control email <?= isset($errors['email']) ? 'error' : '' ?>"
                               value="<?= htmlspecialchars($form_data['email'] ?? '') ?>"
                               required>
                        <?php if (isset($errors['email'])): ?>
                            <div class="error-message"><?= htmlspecialchars($errors['email']) ?></div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="form-group">
                        <label for="role" class="form-label">Account Type *</label>
                        <select id="role" 
                                name="role" 
                                class="form-control <?= isset($errors['role']) ? 'error' : '' ?>"
                                required>
                            <option value="customer" <?= ($form_data['role'] ?? 'customer') === 'customer' ? 'selected' : '' ?>>
                                Customer - Shop for vintage items
                            </option>
                            <option value="staff" <?= ($form_data['role'] ?? '') === 'staff' ? 'selected' : '' ?>>
                                Staff - Manage inventory and orders
                            </option>
                            <option value="admin" <?= ($form_data['role'] ?? '') === 'admin' ? 'selected' : '' ?>>
                                Administrator - Full system access
                            </option>
                        </select>
                        <?php if (isset($errors['role'])): ?>
                            <div class="error-message"><?= htmlspecialchars($errors['role']) ?></div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Password Section -->
                    <h3 class="mb-3 mt-4">Password</h3>
                    
                    <div class="form-group">
                        <label for="password" class="form-label">Password *</label>
                        <input type="password" 
                               id="password" 
                               name="password" 
                               class="form-control password <?= isset($errors['password']) ? 'error' : '' ?>"
                               required>
                        <small class="form-text">
                            Password must be at least 8 characters long and contain uppercase, lowercase, and numbers.
                        </small>
                        <?php if (isset($errors['password'])): ?>
                            <div class="error-message"><?= htmlspecialchars($errors['password']) ?></div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="form-group">
                        <label for="confirm_password" class="form-label">Confirm Password *</label>
                        <input type="password" 
                               id="confirm_password" 
                               name="confirm_password" 
                               class="form-control confirm-password <?= isset($errors['confirm_password']) ? 'error' : '' ?>"
                               required>
                        <?php if (isset($errors['confirm_password'])): ?>
                            <div class="error-message"><?= htmlspecialchars($errors['confirm_password']) ?></div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Address Information (Optional) -->
                    <h3 class="mb-3 mt-4">Address Information <small>(Optional)</small></h3>
                    
                    <div class="form-group">
                        <label for="street_name" class="form-label">Street Address</label>
                        <input type="text" 
                               id="street_name" 
                               name="street_name" 
                               class="form-control"
                               value="<?= htmlspecialchars($form_data['street_name'] ?? '') ?>">
                    </div>
                    
                    <div class="form-group" style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                        <div>
                            <label for="suburb" class="form-label">Suburb</label>
                            <input type="text" 
                                   id="suburb" 
                                   name="suburb" 
                                   class="form-control"
                                   value="<?= htmlspecialchars($form_data['suburb'] ?? '') ?>">
                        </div>
                        <div>
                            <label for="postcode" class="form-label">Postcode</label>
                            <input type="text" 
                                   id="postcode" 
                                   name="postcode" 
                                   class="form-control"
                                   value="<?= htmlspecialchars($form_data['postcode'] ?? '') ?>">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="state" class="form-label">State</label>
                        <select id="state" name="state" class="form-control">
                            <option value="">Select State</option>
                            <option value="NSW" <?= ($form_data['state'] ?? '') === 'NSW' ? 'selected' : '' ?>>New South Wales</option>
                            <option value="VIC" <?= ($form_data['state'] ?? '') === 'VIC' ? 'selected' : '' ?>>Victoria</option>
                            <option value="QLD" <?= ($form_data['state'] ?? '') === 'QLD' ? 'selected' : '' ?>>Queensland</option>
                            <option value="WA" <?= ($form_data['state'] ?? '') === 'WA' ? 'selected' : '' ?>>Western Australia</option>
                            <option value="SA" <?= ($form_data['state'] ?? '') === 'SA' ? 'selected' : '' ?>>South Australia</option>
                            <option value="TAS" <?= ($form_data['state'] ?? '') === 'TAS' ? 'selected' : '' ?>>Tasmania</option>
                            <option value="ACT" <?= ($form_data['state'] ?? '') === 'ACT' ? 'selected' : '' ?>>Australian Capital Territory</option>
                            <option value="NT" <?= ($form_data['state'] ?? '') === 'NT' ? 'selected' : '' ?>>Northern Territory</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="phone" class="form-label">Phone Number</label>
                        <input type="tel" 
                               id="phone" 
                               name="phone" 
                               class="form-control phone"
                               value="<?= htmlspecialchars($form_data['phone'] ?? '') ?>">
                    </div>
                    
                    <!-- Terms and Conditions -->
                    <div class="form-group mt-4">
                        <label class="form-check">
                            <input type="checkbox" 
                                   name="terms_accepted" 
                                   class="form-check-input"
                                   <?= ($form_data['terms_accepted'] ?? false) ? 'checked' : '' ?>
                                   required>
                            <span class="form-check-label">
                                I agree to the <a href="../../pages/terms.php" target="_blank">Terms and Conditions</a> 
                                and <a href="../../pages/privacy.php" target="_blank">Privacy Policy</a> *
                            </span>
                        </label>
                        <?php if (isset($errors['terms_accepted'])): ?>
                            <div class="error-message"><?= htmlspecialchars($errors['terms_accepted']) ?></div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="form-group">
                        <button type="submit" class="btn btn-primary btn-block">Create Account</button>
                    </div>
                    
                    <div class="text-center mt-3">
                        <p>Already have an account? <a href="login.php">Login here</a></p>
                    </div>
                </form>
            </div>
        </div>
    </main>
    
    <?php include '../../includes/footer.php'; ?>
    <script src="../../assets/js/main.js"></script>
</body>
</html>
