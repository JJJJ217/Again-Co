<?php
/**
 * Reset Password Page
 * Implements F103 - Password Reset functionality
 * Story 106: Complete password reset process
 */

require_once '../../includes/init.php';

// Redirect if already logged in
if (isLoggedIn()) {
    redirectWithMessage(SITE_URL . '/pages/user/profile.php', 'You are already logged in.', 'info');
}

$errors = [];
$success_message = '';
$form_data = [];
$token = $_GET['token'] ?? '';
$reset_data = null;

// Validate token on page load
if (empty($token)) {
    redirectWithMessage(
        SITE_URL . '/pages/auth/forgot-password.php', 
        'Invalid reset link. Please request a new password reset.', 
        'error'
    );
}

// Check if token is valid
try {
    $reset_data = $db->fetch(
        "SELECT pr.*, u.email, u.name 
         FROM password_resets pr 
         JOIN users u ON pr.user_id = u.user_id 
         WHERE pr.token = ? AND pr.used = 0 AND pr.expires_at > NOW() AND u.is_active = 1",
        [$token]
    );
    
    if (!$reset_data) {
        redirectWithMessage(
            SITE_URL . '/pages/auth/forgot-password.php', 
            'This reset link has expired or is invalid. Please request a new password reset.', 
            'error'
        );
    }
} catch (Exception $e) {
    error_log("Reset token validation error: " . $e->getMessage());
    redirectWithMessage(
        SITE_URL . '/pages/auth/forgot-password.php', 
        'An error occurred. Please request a new password reset.', 
        'error'
    );
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get and sanitize form data
    $form_data = [
        'password' => $_POST['password'] ?? '',
        'confirm_password' => $_POST['confirm_password'] ?? ''
    ];
    
    // Validate password
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
    
    // If validation passes, update password
    if (empty($errors)) {
        try {
            $db->connect()->beginTransaction();
            
            // Hash new password
            $hashed_password = hashPassword($form_data['password']);
            
            // Update user password
            $db->query(
                "UPDATE users SET password = ?, updated_at = NOW() WHERE user_id = ?",
                [$hashed_password, $reset_data['user_id']]
            );
            
            // Mark reset token as used
            $db->query(
                "UPDATE password_resets SET used = 1 WHERE reset_id = ?",
                [$reset_data['reset_id']]
            );
            
            // Invalidate all other reset tokens for this user
            $db->query(
                "UPDATE password_resets SET used = 1 WHERE user_id = ? AND reset_id != ?",
                [$reset_data['user_id'], $reset_data['reset_id']]
            );
            
            $db->connect()->commit();
            
            // Log activity
            logActivity($reset_data['user_id'], 'password_reset_completed', 'Password successfully reset');
            
            // Send confirmation email
            $subject = "Password Reset Successful - Again&Co";
            $message = "
            <html>
            <head>
                <style>
                    body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                    .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                    .header { background-color: #27ae60; color: white; padding: 20px; text-align: center; }
                    .content { padding: 20px; background-color: #f8f9fa; }
                    .footer { text-align: center; color: #666; font-size: 12px; margin-top: 20px; }
                </style>
            </head>
            <body>
                <div class='container'>
                    <div class='header'>
                        <h1>Password Reset Successful</h1>
                    </div>
                    <div class='content'>
                        <h2>Hello " . htmlspecialchars($reset_data['name']) . ",</h2>
                        <p>Your password has been successfully reset for your Again&Co account.</p>
                        <p>You can now log in using your new password.</p>
                        <p>If you did not perform this action, please contact us immediately at " . SITE_EMAIL . "</p>
                        <p>For security reasons, we recommend:</p>
                        <ul>
                            <li>Using a strong, unique password</li>
                            <li>Not sharing your password with anyone</li>
                            <li>Logging out when using shared computers</li>
                        </ul>
                    </div>
                    <div class='footer'>
                        <p>This email was sent from Again&Co</p>
                    </div>
                </div>
            </body>
            </html>";
            
            sendEmail($reset_data['email'], $subject, $message, [
                'Content-Type' => 'text/html; charset=UTF-8'
            ]);
            
            $success_message = "Your password has been successfully reset! You can now log in with your new password.";
            
        } catch (Exception $e) {
            $db->connect()->rollBack();
            $errors['general'] = 'Password reset failed. Please try again.';
            error_log("Password reset completion error: " . $e->getMessage());
        }
    }
}

$page_title = "Reset Password - Again&Co";
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
                    <h1 class="card-title">Reset Your Password</h1>
                    <p>Enter your new password for <?= htmlspecialchars($reset_data['email']) ?></p>
                </div>
                
                <?php if ($success_message): ?>
                    <div class="alert alert-success">
                        <?= htmlspecialchars($success_message) ?>
                    </div>
                    
                    <div class="text-center mt-3">
                        <a href="login.php" class="btn btn-primary">Go to Login</a>
                    </div>
                    
                <?php else: ?>
                
                    <?php if (!empty($errors['general'])): ?>
                        <div class="alert alert-error">
                            <?= htmlspecialchars($errors['general']) ?>
                        </div>
                    <?php endif; ?>
                    
                    <form method="POST" action="" class="needs-validation" novalidate>
                        <div class="form-group">
                            <label for="password" class="form-label">New Password</label>
                            <input type="password" 
                                   id="password" 
                                   name="password" 
                                   class="form-control password <?= isset($errors['password']) ? 'error' : '' ?>"
                                   required
                                   autocomplete="new-password">
                            <small class="form-text">
                                Password must be at least 8 characters long and contain uppercase, lowercase, and numbers.
                            </small>
                            <?php if (isset($errors['password'])): ?>
                                <div class="error-message"><?= htmlspecialchars($errors['password']) ?></div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="form-group">
                            <label for="confirm_password" class="form-label">Confirm New Password</label>
                            <input type="password" 
                                   id="confirm_password" 
                                   name="confirm_password" 
                                   class="form-control confirm-password <?= isset($errors['confirm_password']) ? 'error' : '' ?>"
                                   required
                                   autocomplete="new-password">
                            <?php if (isset($errors['confirm_password'])): ?>
                                <div class="error-message"><?= htmlspecialchars($errors['confirm_password']) ?></div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="form-group">
                            <button type="submit" class="btn btn-primary btn-block">Reset Password</button>
                        </div>
                    </form>
                    
                    <div class="text-center mt-3">
                        <p><a href="login.php">Back to Login</a></p>
                    </div>
                
                <?php endif; ?>
            </div>
            
            <!-- Security Info -->
            <div class="card mt-4" style="max-width: 450px; margin: 0 auto;">
                <div class="card-header">
                    <h3>Password Security Tips</h3>
                </div>
                <ul>
                    <li>Use a mix of uppercase and lowercase letters</li>
                    <li>Include numbers and special characters</li>
                    <li>Make it at least 8 characters long</li>
                    <li>Don't reuse passwords from other accounts</li>
                    <li>Consider using a password manager</li>
                </ul>
            </div>
        </div>
    </main>
    
    <?php include '../../includes/footer.php'; ?>
    <script src="../../assets/js/main.js"></script>
</body>
</html>
