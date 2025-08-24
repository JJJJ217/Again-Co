<?php
/**
 * Forgot Password Page
 * Implements F103 - Password Reset functionality
 * Story 106: Request password reset
 */

require_once '../../includes/init.php';

// Redirect if already logged in
if (isLoggedIn()) {
    redirectWithMessage(SITE_URL . '/pages/user/profile.php', 'You are already logged in.', 'info');
}

$errors = [];
$success_message = '';
$form_data = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get and sanitize form data
    $form_data = [
        'email' => sanitizeInput($_POST['email'] ?? '')
    ];
    
    // Validate email
    if (empty($form_data['email'])) {
        $errors['email'] = 'Email is required';
    } elseif (!validateEmail($form_data['email'])) {
        $errors['email'] = 'Please enter a valid email address';
    }
    
    // If validation passes, process reset request
    if (empty($errors)) {
        try {
            // Check if user exists
            $user = $db->fetch(
                "SELECT user_id, name, email FROM users WHERE email = ? AND is_active = 1",
                [$form_data['email']]
            );
            
            if ($user) {
                // Generate reset token
                $reset_token = generateToken(32);
                $expires_at = date('Y-m-d H:i:s', time() + 3600); // 1 hour expiry
                
                // Store reset token in database
                $db->query(
                    "INSERT INTO password_resets (user_id, token, expires_at, created_at) 
                     VALUES (?, ?, ?, NOW())",
                    [$user['user_id'], $reset_token, $expires_at]
                );
                
                // Create reset link
                $reset_link = SITE_URL . "/pages/auth/reset-password.php?token=" . $reset_token;
                
                // Email content
                $subject = "Password Reset Request - E-Vinty";
                $message = "
                <html>
                <head>
                    <style>
                        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                        .header { background-color: #2c3e50; color: white; padding: 20px; text-align: center; }
                        .content { padding: 20px; background-color: #f8f9fa; }
                        .button { display: inline-block; padding: 12px 30px; background-color: #3498db; color: white; text-decoration: none; border-radius: 5px; margin: 20px 0; }
                        .footer { text-align: center; color: #666; font-size: 12px; margin-top: 20px; }
                    </style>
                </head>
                <body>
                    <div class='container'>
                        <div class='header'>
                            <h1>E-Vinty Password Reset</h1>
                        </div>
                        <div class='content'>
                            <h2>Hello " . htmlspecialchars($user['name']) . ",</h2>
                            <p>We received a request to reset your password for your E-Vinty account.</p>
                            <p>Click the button below to reset your password:</p>
                            <p><a href='" . $reset_link . "' class='button'>Reset My Password</a></p>
                            <p>If the button doesn't work, copy and paste this link into your browser:</p>
                            <p>" . $reset_link . "</p>
                            <p><strong>This link will expire in 1 hour.</strong></p>
                            <p>If you did not request this password reset, please ignore this email. Your password will remain unchanged.</p>
                        </div>
                        <div class='footer'>
                            <p>This email was sent from E-Vinty - Again&Co<br>
                            If you have any questions, please contact us at " . SITE_EMAIL . "</p>
                        </div>
                    </div>
                </body>
                </html>";
                
                // Send email
                $email_sent = sendEmail($user['email'], $subject, $message, [
                    'Content-Type' => 'text/html; charset=UTF-8'
                ]);
                
                if ($email_sent) {
                    // Log activity
                    logActivity($user['user_id'], 'password_reset_requested', 'Reset token generated');
                    
                    $success_message = "A password reset link has been sent to your email address. Please check your inbox and follow the instructions to reset your password.";
                } else {
                    $errors['general'] = "Failed to send reset email. Please try again or contact support.";
                }
            } else {
                // For security, show success message even if email doesn't exist
                $success_message = "If an account with that email address exists, we have sent you a password reset link.";
            }
            
        } catch (Exception $e) {
            $errors['general'] = 'Password reset request failed. Please try again.';
            error_log("Password reset error: " . $e->getMessage());
        }
    }
}

$page_title = "Forgot Password - E-Vinty";
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
                    <p>Enter your email address and we'll send you a link to reset your password</p>
                </div>
                
                <?php if ($success_message): ?>
                    <div class="alert alert-success">
                        <?= htmlspecialchars($success_message) ?>
                    </div>
                    
                    <div class="text-center mt-3">
                        <a href="login.php" class="btn btn-primary">Back to Login</a>
                    </div>
                    
                <?php else: ?>
                
                    <?php if (!empty($errors['general'])): ?>
                        <div class="alert alert-error">
                            <?= htmlspecialchars($errors['general']) ?>
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
                                   placeholder="Enter your email address"
                                   required
                                   autocomplete="email">
                            <?php if (isset($errors['email'])): ?>
                                <div class="error-message"><?= htmlspecialchars($errors['email']) ?></div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="form-group">
                            <button type="submit" class="btn btn-primary btn-block">Send Reset Link</button>
                        </div>
                    </form>
                    
                    <div class="text-center mt-3">
                        <p><a href="login.php">Back to Login</a></p>
                        <p>Don't have an account? <a href="register.php">Create one here</a></p>
                    </div>
                
                <?php endif; ?>
            </div>
            
            <!-- Instructions -->
            <div class="card mt-4" style="max-width: 450px; margin: 0 auto;">
                <div class="card-header">
                    <h3>What happens next?</h3>
                </div>
                <ol>
                    <li>Check your email inbox (and spam folder)</li>
                    <li>Click the reset link in the email</li>
                    <li>Enter your new password</li>
                    <li>Log in with your new password</li>
                </ol>
                <p><small>The reset link will expire in 1 hour for security reasons.</small></p>
            </div>
        </div>
    </main>
    
    <?php include '../../includes/footer.php'; ?>
    <script src="../../assets/js/main.js"></script>
</body>
</html>
