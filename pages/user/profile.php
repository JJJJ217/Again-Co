<?php
/**
 * User Profile Management Page
 * Implements F102 - Profile Management functionality
 * Stories 201-205: Account management features
 */

require_once '../../includes/init.php';

// Require user to be logged in
requireLogin();

$current_user = getCurrentUser();
$errors = [];
$success_message = '';
$form_data = [];

// Get user's current profile data
try {
    $user_profile = $db->fetch(
        "SELECT u.*, p.street_name, p.postcode, p.suburb, p.state, p.phone, p.date_of_birth
         FROM users u 
         LEFT JOIN user_profiles p ON u.user_id = p.user_id 
         WHERE u.user_id = ?",
        [$current_user['user_id']]
    );
    
    if (!$user_profile) {
        redirectWithMessage(SITE_URL . '/pages/auth/login.php', 'Session expired. Please log in again.', 'error');
    }
} catch (Exception $e) {
    error_log("Profile fetch error: " . $e->getMessage());
    redirectWithMessage(SITE_URL, 'An error occurred loading your profile.', 'error');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'update_profile';
    
    switch ($action) {
        case 'update_profile':
            // Get and sanitize form data
            $form_data = [
                'name' => sanitizeInput($_POST['name'] ?? ''),
                'email' => sanitizeInput($_POST['email'] ?? ''),
                'street_name' => sanitizeInput($_POST['street_name'] ?? ''),
                'postcode' => sanitizeInput($_POST['postcode'] ?? ''),
                'suburb' => sanitizeInput($_POST['suburb'] ?? ''),
                'state' => sanitizeInput($_POST['state'] ?? ''),
                'phone' => sanitizeInput($_POST['phone'] ?? ''),
                'date_of_birth' => sanitizeInput($_POST['date_of_birth'] ?? '')
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
            
            // Check if email is already taken by another user
            if (empty($errors['email']) && $form_data['email'] !== $user_profile['email']) {
                try {
                    $existing_email = $db->fetch(
                        "SELECT user_id FROM users WHERE email = ? AND user_id != ?",
                        [$form_data['email'], $current_user['user_id']]
                    );
                    
                    if ($existing_email) {
                        $errors['email'] = 'This email address is already registered to another account';
                    }
                } catch (Exception $e) {
                    $errors['general'] = 'Database error occurred. Please try again.';
                }
            }
            
            // Validate date of birth if provided
            if (!empty($form_data['date_of_birth'])) {
                $dob = DateTime::createFromFormat('Y-m-d', $form_data['date_of_birth']);
                if (!$dob || $dob->format('Y-m-d') !== $form_data['date_of_birth']) {
                    $errors['date_of_birth'] = 'Please enter a valid date';
                } elseif ($dob > new DateTime()) {
                    $errors['date_of_birth'] = 'Date of birth cannot be in the future';
                }
            }
            
            // If no errors, update profile
            if (empty($errors)) {
                try {
                    $db->connect()->beginTransaction();
                    
                    // Update user table
                    $db->query(
                        "UPDATE users SET name = ?, email = ?, updated_at = NOW() WHERE user_id = ?",
                        [$form_data['name'], $form_data['email'], $current_user['user_id']]
                    );
                    
                    // Update or insert profile data
                    $existing_profile = $db->fetch(
                        "SELECT profile_id FROM user_profiles WHERE user_id = ?",
                        [$current_user['user_id']]
                    );
                    
                    if ($existing_profile) {
                        // Update existing profile
                        $db->query(
                            "UPDATE user_profiles SET 
                             street_name = ?, postcode = ?, suburb = ?, state = ?, phone = ?, 
                             date_of_birth = ?, updated_at = NOW() 
                             WHERE user_id = ?",
                            [
                                $form_data['street_name'],
                                $form_data['postcode'],
                                $form_data['suburb'],
                                $form_data['state'],
                                $form_data['phone'],
                                $form_data['date_of_birth'] ?: null,
                                $current_user['user_id']
                            ]
                        );
                    } else {
                        // Insert new profile
                        $db->query(
                            "INSERT INTO user_profiles 
                             (user_id, street_name, postcode, suburb, state, phone, date_of_birth, created_at)
                             VALUES (?, ?, ?, ?, ?, ?, ?, NOW())",
                            [
                                $current_user['user_id'],
                                $form_data['street_name'],
                                $form_data['postcode'],
                                $form_data['suburb'],
                                $form_data['state'],
                                $form_data['phone'],
                                $form_data['date_of_birth'] ?: null
                            ]
                        );
                    }
                    
                    $db->connect()->commit();
                    
                    // Update session data if email or name changed
                    if ($form_data['email'] !== $_SESSION['user_email']) {
                        $_SESSION['user_email'] = $form_data['email'];
                    }
                    if ($form_data['name'] !== $_SESSION['user_name']) {
                        $_SESSION['user_name'] = $form_data['name'];
                    }
                    
                    // Log activity
                    logActivity($current_user['user_id'], 'profile_updated', 'Profile information updated');
                    
                    // Refresh profile data
                    $user_profile = $db->fetch(
                        "SELECT u.*, p.street_name, p.postcode, p.suburb, p.state, p.phone, p.date_of_birth
                         FROM users u 
                         LEFT JOIN user_profiles p ON u.user_id = p.user_id 
                         WHERE u.user_id = ?",
                        [$current_user['user_id']]
                    );
                    
                    $success_message = 'Profile updated successfully!';
                    
                } catch (Exception $e) {
                    $db->connect()->rollBack();
                    $errors['general'] = 'Failed to update profile. Please try again.';
                    error_log("Profile update error: " . $e->getMessage());
                }
            }
            break;
            
        case 'change_password':
            // Handle password change
            $current_password = $_POST['current_password'] ?? '';
            $new_password = $_POST['new_password'] ?? '';
            $confirm_password = $_POST['confirm_password'] ?? '';
            
            // Validate current password
            if (empty($current_password)) {
                $errors['current_password'] = 'Current password is required';
            } elseif (!verifyPassword($current_password, $user_profile['password'])) {
                $errors['current_password'] = 'Current password is incorrect';
            }
            
            // Validate new password
            if (empty($new_password)) {
                $errors['new_password'] = 'New password is required';
            } else {
                $password_errors = validatePassword($new_password);
                if (!empty($password_errors)) {
                    $errors['new_password'] = implode('. ', $password_errors);
                }
            }
            
            if (empty($confirm_password)) {
                $errors['confirm_password'] = 'Please confirm your new password';
            } elseif ($new_password !== $confirm_password) {
                $errors['confirm_password'] = 'Passwords do not match';
            }
            
            // Check if new password is different from current
            if (empty($errors['new_password']) && verifyPassword($new_password, $user_profile['password'])) {
                $errors['new_password'] = 'New password must be different from current password';
            }
            
            // If no errors, update password
            if (empty($errors)) {
                try {
                    $hashed_password = hashPassword($new_password);
                    
                    $db->query(
                        "UPDATE users SET password = ?, updated_at = NOW() WHERE user_id = ?",
                        [$hashed_password, $current_user['user_id']]
                    );
                    
                    // Log activity
                    logActivity($current_user['user_id'], 'password_changed', 'Password updated successfully');
                    
                    $success_message = 'Password changed successfully!';
                    
                } catch (Exception $e) {
                    $errors['general'] = 'Failed to change password. Please try again.';
                    error_log("Password change error: " . $e->getMessage());
                }
            }
            break;
    }
}

$page_title = "My Profile - E-Vinty";
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
            <?php if ($success_message): ?>
                <div class="alert alert-success">
                    <?= htmlspecialchars($success_message) ?>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($errors['general'])): ?>
                <div class="alert alert-error">
                    <?= htmlspecialchars($errors['general']) ?>
                </div>
            <?php endif; ?>
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 2rem;">
                <!-- Profile Information -->
                <div class="card">
                    <div class="card-header">
                        <h2 class="card-title">Profile Information</h2>
                        <p>Update your personal details and contact information</p>
                    </div>
                    
                    <form method="POST" action="" class="needs-validation" novalidate>
                        <input type="hidden" name="action" value="update_profile">
                        
                        <div class="form-group">
                            <label for="name" class="form-label">Full Name *</label>
                            <input type="text" 
                                   id="name" 
                                   name="name" 
                                   class="form-control <?= isset($errors['name']) ? 'error' : '' ?>"
                                   value="<?= htmlspecialchars($user_profile['name']) ?>"
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
                                   value="<?= htmlspecialchars($user_profile['email']) ?>"
                                   required>
                            <?php if (isset($errors['email'])): ?>
                                <div class="error-message"><?= htmlspecialchars($errors['email']) ?></div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="form-group">
                            <label for="date_of_birth" class="form-label">Date of Birth</label>
                            <input type="date" 
                                   id="date_of_birth" 
                                   name="date_of_birth" 
                                   class="form-control <?= isset($errors['date_of_birth']) ? 'error' : '' ?>"
                                   value="<?= htmlspecialchars($user_profile['date_of_birth'] ?? '') ?>">
                            <?php if (isset($errors['date_of_birth'])): ?>
                                <div class="error-message"><?= htmlspecialchars($errors['date_of_birth']) ?></div>
                            <?php endif; ?>
                        </div>
                        
                        <h4 class="mt-4 mb-3">Address Information</h4>
                        
                        <div class="form-group">
                            <label for="street_name" class="form-label">Street Address</label>
                            <input type="text" 
                                   id="street_name" 
                                   name="street_name" 
                                   class="form-control"
                                   value="<?= htmlspecialchars($user_profile['street_name'] ?? '') ?>">
                        </div>
                        
                        <div class="form-group" style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                            <div>
                                <label for="suburb" class="form-label">Suburb</label>
                                <input type="text" 
                                       id="suburb" 
                                       name="suburb" 
                                       class="form-control"
                                       value="<?= htmlspecialchars($user_profile['suburb'] ?? '') ?>">
                            </div>
                            <div>
                                <label for="postcode" class="form-label">Postcode</label>
                                <input type="text" 
                                       id="postcode" 
                                       name="postcode" 
                                       class="form-control"
                                       value="<?= htmlspecialchars($user_profile['postcode'] ?? '') ?>">
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="state" class="form-label">State</label>
                            <select id="state" name="state" class="form-control">
                                <option value="">Select State</option>
                                <option value="NSW" <?= ($user_profile['state'] ?? '') === 'NSW' ? 'selected' : '' ?>>New South Wales</option>
                                <option value="VIC" <?= ($user_profile['state'] ?? '') === 'VIC' ? 'selected' : '' ?>>Victoria</option>
                                <option value="QLD" <?= ($user_profile['state'] ?? '') === 'QLD' ? 'selected' : '' ?>>Queensland</option>
                                <option value="WA" <?= ($user_profile['state'] ?? '') === 'WA' ? 'selected' : '' ?>>Western Australia</option>
                                <option value="SA" <?= ($user_profile['state'] ?? '') === 'SA' ? 'selected' : '' ?>>South Australia</option>
                                <option value="TAS" <?= ($user_profile['state'] ?? '') === 'TAS' ? 'selected' : '' ?>>Tasmania</option>
                                <option value="ACT" <?= ($user_profile['state'] ?? '') === 'ACT' ? 'selected' : '' ?>>Australian Capital Territory</option>
                                <option value="NT" <?= ($user_profile['state'] ?? '') === 'NT' ? 'selected' : '' ?>>Northern Territory</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="phone" class="form-label">Phone Number</label>
                            <input type="tel" 
                                   id="phone" 
                                   name="phone" 
                                   class="form-control phone"
                                   value="<?= htmlspecialchars($user_profile['phone'] ?? '') ?>">
                        </div>
                        
                        <div class="form-group">
                            <button type="submit" class="btn btn-primary">Update Profile</button>
                        </div>
                    </form>
                </div>
                
                <!-- Security Settings -->
                <div class="card">
                    <div class="card-header">
                        <h2 class="card-title">Security Settings</h2>
                        <p>Change your password and manage account security</p>
                    </div>
                    
                    <!-- Account Information -->
                    <div class="account-info mb-4">
                        <h4>Account Details</h4>
                        <table class="table">
                            <tr>
                                <td><strong>Account Type:</strong></td>
                                <td><?= ucfirst($user_profile['role']) ?></td>
                            </tr>
                            <tr>
                                <td><strong>Member Since:</strong></td>
                                <td><?= formatDate($user_profile['created_at'], 'F j, Y') ?></td>
                            </tr>
                            <tr>
                                <td><strong>Last Login:</strong></td>
                                <td><?= $user_profile['last_login'] ? formatDate($user_profile['last_login'], 'F j, Y g:i A') : 'Never' ?></td>
                            </tr>
                            <tr>
                                <td><strong>Account Status:</strong></td>
                                <td>
                                    <span style="color: <?= $user_profile['is_active'] ? '#27ae60' : '#e74c3c' ?>">
                                        <?= $user_profile['is_active'] ? 'Active' : 'Inactive' ?>
                                    </span>
                                </td>
                            </tr>
                        </table>
                    </div>
                    
                    <!-- Change Password Form -->
                    <form method="POST" action="" class="needs-validation" novalidate>
                        <input type="hidden" name="action" value="change_password">
                        
                        <h4 class="mb-3">Change Password</h4>
                        
                        <div class="form-group">
                            <label for="current_password" class="form-label">Current Password *</label>
                            <input type="password" 
                                   id="current_password" 
                                   name="current_password" 
                                   class="form-control <?= isset($errors['current_password']) ? 'error' : '' ?>"
                                   required>
                            <?php if (isset($errors['current_password'])): ?>
                                <div class="error-message"><?= htmlspecialchars($errors['current_password']) ?></div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="form-group">
                            <label for="new_password" class="form-label">New Password *</label>
                            <input type="password" 
                                   id="new_password" 
                                   name="new_password" 
                                   class="form-control password <?= isset($errors['new_password']) ? 'error' : '' ?>"
                                   required>
                            <small class="form-text">
                                Password must be at least 8 characters long and contain uppercase, lowercase, and numbers.
                            </small>
                            <?php if (isset($errors['new_password'])): ?>
                                <div class="error-message"><?= htmlspecialchars($errors['new_password']) ?></div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="form-group">
                            <label for="confirm_password" class="form-label">Confirm New Password *</label>
                            <input type="password" 
                                   id="confirm_password" 
                                   name="confirm_password" 
                                   class="form-control confirm-password <?= isset($errors['confirm_password']) ? 'error' : '' ?>"
                                   required>
                            <?php if (isset($errors['confirm_password'])): ?>
                                <div class="error-message"><?= htmlspecialchars($errors['confirm_password']) ?></div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="form-group">
                            <button type="submit" class="btn btn-secondary">Change Password</button>
                        </div>
                    </form>
                    
                    <!-- Additional Security Options -->
                    <div class="security-options mt-4">
                        <h4>Additional Options</h4>
                        <div class="d-flex gap-2">
                            <a href="../auth/logout.php" class="btn btn-danger">Logout</a>
                            <?php if ($user_profile['role'] === 'customer'): ?>
                                <button class="btn btn-danger" onclick="confirmAccountDeletion()">Delete Account</button>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>
    
    <?php include '../../includes/footer.php'; ?>
    <script src="../../assets/js/main.js"></script>
    <script>
        function confirmAccountDeletion() {
            if (confirm('Are you sure you want to delete your account? This action cannot be undone.')) {
                if (confirm('This will permanently delete all your data, orders, and account information. Are you absolutely sure?')) {
                    // Redirect to account deletion page
                    window.location.href = 'delete-account.php';
                }
            }
        }
    </script>
</body>
</html>
