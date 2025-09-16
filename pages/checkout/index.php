<?php
/**
 * Checkout Page
 * Implements F109 - Shipping & Payment functionality
 * Multi-step checkout process
 */

require_once '../../includes/init.php';

// Require user to be logged in
requireLogin();

$user = getCurrentUser();

// Get user profile for checkout form
$user_profile = [];
try {
    $profile_data = $db->fetch(
        "SELECT up.*, u.name, u.email 
         FROM user_profiles up 
         JOIN users u ON up.user_id = u.user_id 
         WHERE up.user_id = ?",
        [$user['user_id']]
    );
    
    // If profile exists, use it as base, otherwise start with empty
    if ($profile_data) {
        $user_profile = $profile_data;
    } else {
        $user_profile = [
            'phone' => '',
            'name' => $user['name'] ?? '',
            'email' => $user['email'] ?? ''
        ];
    }
    
    // Always create first_name and last_name fields
    $user_profile['first_name'] = '';
    $user_profile['last_name'] = '';
    $user_profile['phone'] = $user_profile['phone'] ?? '';
    
    // Try to split name into first/last if available
    if (!empty($user_profile['name'])) {
        $name_parts = explode(' ', trim($user_profile['name']), 2);
        $user_profile['first_name'] = $name_parts[0] ?? '';
        $user_profile['last_name'] = $name_parts[1] ?? '';
    }
    
} catch (Exception $e) {
    error_log("Error fetching user profile: " . $e->getMessage());
    $user_profile = [
        'first_name' => '',
        'last_name' => '',
        'phone' => '',
        'name' => $user['name'] ?? '',
        'email' => $user['email'] ?? ''
    ];
}

$step = $_GET['step'] ?? 'shipping';
$allowed_steps = ['shipping', 'payment', 'review', 'confirmation'];

if (!in_array($step, $allowed_steps)) {
    $step = 'shipping';
}

// Initialize checkout session if not exists
if (!isset($_SESSION['checkout'])) {
    $_SESSION['checkout'] = [
        'shipping_address' => [],
        'billing_address' => [],
        'shipping_method' => 'standard',
        'payment_method' => 'credit_card'
    ];
}

// Get cart items
$cart_items = [];
$cart_total = 0;
$cart_count = 0;

try {
    $cart_items = $db->fetchAll(
        "SELECT c.*, p.product_name, p.price, p.image_url, p.stock,
                (c.quantity * p.price) as item_total
         FROM shopping_cart c
         JOIN products p ON c.product_id = p.product_id
         WHERE c.user_id = ? AND p.is_active = 1
         ORDER BY c.added_at DESC",
        [$user['user_id']]
    );
    
    foreach ($cart_items as $item) {
        $cart_total += $item['item_total'];
        $cart_count += $item['quantity'];
    }
    
} catch (Exception $e) {
    error_log("Checkout cart error: " . $e->getMessage());
}

// Redirect if cart is empty
if (empty($cart_items)) {
    setFlashMessage('Your cart is empty', 'warning');
    header('Location: ../user/cart.php');
    exit;
}

// Calculate shipping
$shipping_cost = calculateShipping($cart_items, $_SESSION['checkout']['shipping_address'] ?? []);
$tax_rate = 0.08; // 8% tax rate
$tax_amount = $cart_total * $tax_rate;
$order_total = $cart_total + $shipping_cost + $tax_amount;

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'save_shipping':
            $_SESSION['checkout']['shipping_address'] = [
                'first_name' => sanitizeInput($_POST['first_name']),
                'last_name' => sanitizeInput($_POST['last_name']),
                'address_line1' => sanitizeInput($_POST['address_line1']),
                'address_line2' => sanitizeInput($_POST['address_line2']),
                'city' => sanitizeInput($_POST['city']),
                'state' => sanitizeInput($_POST['state']),
                'postal_code' => sanitizeInput($_POST['postal_code']),
                'country' => sanitizeInput($_POST['country']),
                'phone' => sanitizeInput($_POST['phone'])
            ];
            $_SESSION['checkout']['shipping_method'] = sanitizeInput($_POST['shipping_method']);
            
            // Recalculate shipping with new address
            $shipping_cost = calculateShipping($cart_items, $_SESSION['checkout']['shipping_address']);
            $order_total = $cart_total + $shipping_cost + $tax_amount;
            
            header('Location: ?step=payment');
            exit;
            
        case 'save_payment':
            $_SESSION['checkout']['payment_method'] = sanitizeInput($_POST['payment_method']);
            $_SESSION['checkout']['billing_same_as_shipping'] = isset($_POST['billing_same_as_shipping']);
            
            if (!$_SESSION['checkout']['billing_same_as_shipping']) {
                $_SESSION['checkout']['billing_address'] = [
                    'first_name' => sanitizeInput($_POST['billing_first_name']),
                    'last_name' => sanitizeInput($_POST['billing_last_name']),
                    'address_line1' => sanitizeInput($_POST['billing_address_line1']),
                    'address_line2' => sanitizeInput($_POST['billing_address_line2']),
                    'city' => sanitizeInput($_POST['billing_city']),
                    'state' => sanitizeInput($_POST['billing_state']),
                    'postal_code' => sanitizeInput($_POST['billing_postal_code']),
                    'country' => sanitizeInput($_POST['billing_country'])
                ];
            }
            
            header('Location: ?step=review');
            exit;
            
        case 'place_order':
            // Validate order
            if (empty($_SESSION['checkout']['shipping_address']) || 
                empty($_SESSION['checkout']['payment_method'])) {
                setFlashMessage('Please complete all checkout steps', 'error');
                header('Location: ?step=shipping');
                exit;
            }
            
            // Create order
            try {
                $db->beginTransaction();
                
                // Insert order
                $order_id = $db->insert('orders', [
                    'user_id' => $user['user_id'],
                    'total_price' => $order_total,
                    'status' => 'pending',
                    'notes' => json_encode([
                        'subtotal' => $cart_total,
                        'shipping_cost' => $shipping_cost,
                        'tax_amount' => $tax_amount,
                        'shipping_address' => $_SESSION['checkout']['shipping_address'],
                        'billing_address' => $_SESSION['checkout']['billing_address'] ?? $_SESSION['checkout']['shipping_address'],
                        'payment_method' => $_SESSION['checkout']['payment_method'],
                        'shipping_method' => $_SESSION['checkout']['shipping_method']
                    ])
                ]);
                
                // Insert order items
                foreach ($cart_items as $item) {
                    $db->insert('order_items', [
                        'order_id' => $order_id,
                        'product_id' => $item['product_id'],
                        'quantity' => $item['quantity'],
                        'unit_price' => $item['price'],
                        'total_price' => $item['item_total']
                    ]);
                    
                    // Update product stock
                    $db->query(
                        "UPDATE products SET stock = stock - ? WHERE product_id = ?",
                        [$item['quantity'], $item['product_id']]
                    );
                }
                
                // Clear cart
                $db->query("DELETE FROM shopping_cart WHERE user_id = ?", [$user['user_id']]);
                
                // Process payment (simulated)
                $payment_result = processPayment($_SESSION['checkout']['payment_method'], $order_total);
                
                if ($payment_result['success']) {
                    $db->query(
                        "UPDATE orders SET status = 'confirmed' WHERE order_id = ?",
                        [$order_id]
                    );
                    
                    $db->commit();
                    
                    // Set success flash message
                    setFlashMessage('🎉 Order placed successfully! Thank you for your purchase.', 'success');
                    
                    // Also set a session variable for additional confirmation
                    $_SESSION['order_success'] = [
                        'message' => 'Your order #' . $order_id . ' has been placed successfully!',
                        'order_id' => $order_id,
                        'timestamp' => time()
                    ];
                    
                    // Debug: Log the redirect
                    error_log("Order placed successfully. Redirecting to thank you page. Order ID: " . $order_id);
                    
                    // Clear checkout session
                    unset($_SESSION['checkout']);
                    
                    header('Location: thank-you.php?order_id=' . $order_id);
                    exit;
                } else {
                    $db->rollback();
                    setFlashMessage('Payment failed: ' . $payment_result['message'], 'error');
                }
                
            } catch (Exception $e) {
                $db->rollback();
                error_log("Order creation error: " . $e->getMessage());
                setFlashMessage('Failed to create order. Please try again.', 'error');
            }
            break;
    }
}

// Get order for confirmation step
$order = null;
if ($step === 'confirmation' && isset($_GET['order_id'])) {
    try {
        $order = $db->fetch(
            "SELECT * FROM orders WHERE order_id = ? AND user_id = ?",
            [$_GET['order_id'], $user['user_id']]
        );
    } catch (Exception $e) {
        error_log("Order fetch error: " . $e->getMessage());
    }
}

$page_title = "Checkout - Again&Co";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $page_title ?></title>
    <link rel="stylesheet" href="../../assets/css/style.css">
    <style>
        .checkout-container {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 2rem;
            max-width: 1200px;
            margin: 0 auto;
        }
        
        .checkout-steps {
            display: flex;
            justify-content: space-between;
            margin-bottom: 2rem;
            background: white;
            border-radius: 10px;
            padding: 1rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .step {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.75rem 1.5rem;
            border-radius: 25px;
            font-weight: 600;
            text-decoration: none;
            color: #7f8c8d;
            background: #f8f9fa;
            position: relative;
        }
        
        .step.active {
            background: #3498db;
            color: white;
        }
        
        .step.completed {
            background: #27ae60;
            color: white;
        }
        
        .step-number {
            width: 24px;
            height: 24px;
            border-radius: 50%;
            background: rgba(255,255,255,0.2);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.875rem;
        }
        
        .checkout-form {
            background: white;
            border-radius: 10px;
            padding: 2rem;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        
        .form-section {
            margin-bottom: 2rem;
        }
        
        .form-section:last-child {
            margin-bottom: 0;
        }
        
        .section-title {
            font-size: 1.25rem;
            font-weight: 600;
            margin-bottom: 1rem;
            color: #2c3e50;
            border-bottom: 2px solid #ecf0f1;
            padding-bottom: 0.5rem;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
            margin-bottom: 1rem;
        }
        
        .form-row.single {
            grid-template-columns: 1fr;
        }
        
        .shipping-methods {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }
        
        .shipping-option {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 1rem;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .shipping-option:hover {
            border-color: #3498db;
        }
        
        .shipping-option.selected {
            border-color: #3498db;
            background-color: #f8fafe;
        }
        
        .shipping-details {
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        
        .shipping-info h4 {
            margin: 0 0 0.25rem 0;
            color: #2c3e50;
        }
        
        .shipping-info p {
            margin: 0;
            color: #7f8c8d;
            font-size: 0.875rem;
        }
        
        .shipping-price {
            font-weight: bold;
            color: #27ae60;
        }
        
        .payment-methods {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }
        
        .payment-option {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 1rem;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            cursor: pointer;
        }
        
        .payment-option:hover {
            border-color: #3498db;
        }
        
        .payment-option.selected {
            border-color: #3498db;
            background-color: #f8fafe;
        }
        
        .payment-icon {
            font-size: 2rem;
        }
        
        .order-summary {
            background: white;
            border-radius: 10px;
            padding: 2rem;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            height: fit-content;
            position: sticky;
            top: 2rem;
        }
        
        .summary-item {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 1rem 0;
            border-bottom: 1px solid #ecf0f1;
        }
        
        .summary-item:last-child {
            border-bottom: none;
        }
        
        .summary-image {
            width: 60px;
            height: 60px;
            background: #f8f9fa;
            border-radius: 5px;
            overflow: hidden;
        }
        
        .summary-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .summary-details {
            flex: 1;
        }
        
        .summary-name {
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 0.25rem;
        }
        
        .summary-meta {
            color: #7f8c8d;
            font-size: 0.875rem;
        }
        
        .summary-price {
            font-weight: bold;
            color: #27ae60;
        }
        
        .summary-totals {
            margin-top: 1.5rem;
            padding-top: 1.5rem;
            border-top: 2px solid #ecf0f1;
        }
        
        .summary-line {
            display: flex;
            justify-content: space-between;
            margin-bottom: 0.75rem;
        }
        
        .summary-line.total {
            font-weight: bold;
            font-size: 1.25rem;
            color: #2c3e50;
            border-top: 1px solid #ecf0f1;
            padding-top: 0.75rem;
            margin-top: 0.75rem;
            margin-bottom: 0;
        }
        
        .checkout-actions {
            margin-top: 2rem;
            display: flex;
            justify-content: space-between;
            gap: 1rem;
        }
        
        .confirmation-content {
            text-align: center;
            padding: 3rem 2rem;
        }
        
        .confirmation-icon {
            font-size: 4rem;
            margin-bottom: 1rem;
        }
        
        .order-details {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 1.5rem;
            margin: 2rem 0;
            text-align: left;
        }
        
        @media (max-width: 768px) {
            .checkout-container {
                grid-template-columns: 1fr;
            }
            
            .checkout-steps {
                overflow-x: auto;
                white-space: nowrap;
            }
            
            .form-row {
                grid-template-columns: 1fr;
            }
            
            .checkout-actions {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <?php include '../../includes/header.php'; ?>
    
    <main class="main-content">
        <div class="container">
            <?php
            $flash = getFlashMessage();
            if ($flash): ?>
                <div class="alert alert-<?= $flash['type'] ?>">
                    <?= safeHtml($flash['message']) ?>
                </div>
            <?php endif; ?>
            
            <div class="card">
                <div class="card-header">
                    <h1 class="card-title">Checkout</h1>
                    <p>Complete your order securely</p>
                </div>
                
                <!-- Progress Steps -->
                <div class="checkout-steps">
                    <a href="?step=shipping" class="step <?= $step === 'shipping' ? 'active' : '' ?> <?= in_array($step, ['payment', 'review', 'confirmation']) ? 'completed' : '' ?>">
                        <span class="step-number">1</span>
                        <span>Shipping</span>
                    </a>
                    <a href="?step=payment" class="step <?= $step === 'payment' ? 'active' : '' ?> <?= in_array($step, ['review', 'confirmation']) ? 'completed' : '' ?>">
                        <span class="step-number">2</span>
                        <span>Payment</span>
                    </a>
                    <a href="?step=review" class="step <?= $step === 'review' ? 'active' : '' ?> <?= $step === 'confirmation' ? 'completed' : '' ?>">
                        <span class="step-number">3</span>
                        <span>Review</span>
                    </a>
                    <div class="step <?= $step === 'confirmation' ? 'active' : '' ?>">
                        <span class="step-number">4</span>
                        <span>Confirmation</span>
                    </div>
                </div>
                
                <?php if ($step !== 'confirmation'): ?>
                    <div class="checkout-container">
                        <!-- Checkout Form -->
                        <div class="checkout-form">
                            <?php if ($step === 'shipping'): ?>
                                <!-- Shipping Information -->
                                <form method="POST">
                                    <input type="hidden" name="action" value="save_shipping">
                                    
                                    <div class="form-section">
                                        <h2 class="section-title">Shipping Address</h2>
                                        
                                        <div class="form-row">
                                            <div class="form-group">
                                                <label for="first_name">First Name *</label>
                                                <input type="text" id="first_name" name="first_name" required
                                                       value="<?= safeHtml($_SESSION['checkout']['shipping_address']['first_name'] ?? $user_profile['first_name']) ?>">
                                            </div>
                                            <div class="form-group">
                                                <label for="last_name">Last Name *</label>
                                                <input type="text" id="last_name" name="last_name" required
                                                       value="<?= safeHtml($_SESSION['checkout']['shipping_address']['last_name'] ?? $user_profile['last_name']) ?>">
                                            </div>
                                        </div>
                                        
                                        <div class="form-row single">
                                            <div class="form-group">
                                                <label for="address_line1">Address Line 1 *</label>
                                                <input type="text" id="address_line1" name="address_line1" required
                                                       value="<?= safeHtml($_SESSION['checkout']['shipping_address']['address_line1'] ?? '') ?>">
                                            </div>
                                        </div>
                                        
                                        <div class="form-row single">
                                            <div class="form-group">
                                                <label for="address_line2">Address Line 2</label>
                                                <input type="text" id="address_line2" name="address_line2"
                                                       value="<?= safeHtml($_SESSION['checkout']['shipping_address']['address_line2'] ?? '') ?>">
                                            </div>
                                        </div>
                                        
                                        <div class="form-row">
                                            <div class="form-group">
                                                <label for="city">City *</label>
                                                <input type="text" id="city" name="city" required
                                                       value="<?= safeHtml($_SESSION['checkout']['shipping_address']['city'] ?? '') ?>">
                                            </div>
                                            <div class="form-group">
                                                <label for="state">State/Province *</label>
                                                <input type="text" id="state" name="state" required
                                                       value="<?= safeHtml($_SESSION['checkout']['shipping_address']['state'] ?? '') ?>">
                                            </div>
                                        </div>
                                        
                                        <div class="form-row">
                                            <div class="form-group">
                                                <label for="postal_code">Postal Code *</label>
                                                <input type="text" id="postal_code" name="postal_code" required
                                                       value="<?= safeHtml($_SESSION['checkout']['shipping_address']['postal_code'] ?? '') ?>">
                                            </div>
                                            <div class="form-group">
                                                <label for="country">Country *</label>
                                                <select id="country" name="country" required>
                                                    <option value="US" <?= ($_SESSION['checkout']['shipping_address']['country'] ?? '') === 'US' ? 'selected' : '' ?>>United States</option>
                                                    <option value="CA" <?= ($_SESSION['checkout']['shipping_address']['country'] ?? '') === 'CA' ? 'selected' : '' ?>>Canada</option>
                                                    <option value="UK" <?= ($_SESSION['checkout']['shipping_address']['country'] ?? '') === 'UK' ? 'selected' : '' ?>>United Kingdom</option>
                                                    <option value="AU" <?= ($_SESSION['checkout']['shipping_address']['country'] ?? '') === 'AU' ? 'selected' : '' ?>>Australia</option>
                                                </select>
                                            </div>
                                        </div>
                                        
                                        <div class="form-row single">
                                            <div class="form-group">
                                                <label for="phone">Phone Number</label>
                                                <input type="tel" id="phone" name="phone"
                                                       value="<?= safeHtml($_SESSION['checkout']['shipping_address']['phone'] ?? $user_profile['phone']) ?>">
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="form-section">
                                        <h2 class="section-title">Shipping Method</h2>
                                        
                                        <div class="shipping-methods">
                                            <label class="shipping-option <?= ($_SESSION['checkout']['shipping_method'] ?? 'standard') === 'standard' ? 'selected' : '' ?>">
                                                <input type="radio" name="shipping_method" value="standard" 
                                                       <?= ($_SESSION['checkout']['shipping_method'] ?? 'standard') === 'standard' ? 'checked' : '' ?>>
                                                <div class="shipping-details">
                                                    <div>📦</div>
                                                    <div class="shipping-info">
                                                        <h4>Standard Shipping</h4>
                                                        <p>5-7 business days</p>
                                                    </div>
                                                </div>
                                                <div class="shipping-price">$9.99</div>
                                            </label>
                                            
                                            <label class="shipping-option <?= ($_SESSION['checkout']['shipping_method'] ?? '') === 'express' ? 'selected' : '' ?>">
                                                <input type="radio" name="shipping_method" value="express"
                                                       <?= ($_SESSION['checkout']['shipping_method'] ?? '') === 'express' ? 'checked' : '' ?>>
                                                <div class="shipping-details">
                                                    <div>🚚</div>
                                                    <div class="shipping-info">
                                                        <h4>Express Shipping</h4>
                                                        <p>2-3 business days</p>
                                                    </div>
                                                </div>
                                                <div class="shipping-price">$19.99</div>
                                            </label>
                                            
                                            <label class="shipping-option <?= ($_SESSION['checkout']['shipping_method'] ?? '') === 'overnight' ? 'selected' : '' ?>">
                                                <input type="radio" name="shipping_method" value="overnight"
                                                       <?= ($_SESSION['checkout']['shipping_method'] ?? '') === 'overnight' ? 'checked' : '' ?>>
                                                <div class="shipping-details">
                                                    <div>✈️</div>
                                                    <div class="shipping-info">
                                                        <h4>Overnight Shipping</h4>
                                                        <p>Next business day</p>
                                                    </div>
                                                </div>
                                                <div class="shipping-price">$39.99</div>
                                            </label>
                                        </div>
                                    </div>
                                    
                                    <div class="checkout-actions">
                                        <a href="../user/cart.php" class="btn btn-secondary">
                                            ← Back to Cart
                                        </a>
                                        <button type="submit" class="btn btn-primary">
                                            Continue to Payment →
                                        </button>
                                    </div>
                                </form>
                                
                            <?php elseif ($step === 'payment'): ?>
                                <!-- Payment Information -->
                                <form method="POST">
                                    <input type="hidden" name="action" value="save_payment">
                                    
                                    <div class="form-section">
                                        <h2 class="section-title">Payment Method</h2>
                                        
                                        <div class="payment-methods">
                                            <label class="payment-option <?= ($_SESSION['checkout']['payment_method'] ?? 'credit_card') === 'credit_card' ? 'selected' : '' ?>">
                                                <input type="radio" name="payment_method" value="credit_card"
                                                       <?= ($_SESSION['checkout']['payment_method'] ?? 'credit_card') === 'credit_card' ? 'checked' : '' ?>>
                                                <div class="payment-icon">💳</div>
                                                <div>
                                                    <h4>Credit/Debit Card</h4>
                                                    <p>Visa, Mastercard, American Express</p>
                                                </div>
                                            </label>
                                            
                                            <label class="payment-option <?= ($_SESSION['checkout']['payment_method'] ?? '') === 'paypal' ? 'selected' : '' ?>">
                                                <input type="radio" name="payment_method" value="paypal"
                                                       <?= ($_SESSION['checkout']['payment_method'] ?? '') === 'paypal' ? 'checked' : '' ?>>
                                                <div class="payment-icon">🅿️</div>
                                                <div>
                                                    <h4>PayPal</h4>
                                                    <p>Pay with your PayPal account</p>
                                                </div>
                                            </label>
                                            
                                            <label class="payment-option <?= ($_SESSION['checkout']['payment_method'] ?? '') === 'apple_pay' ? 'selected' : '' ?>">
                                                <input type="radio" name="payment_method" value="apple_pay"
                                                       <?= ($_SESSION['checkout']['payment_method'] ?? '') === 'apple_pay' ? 'checked' : '' ?>>
                                                <div class="payment-icon">🍎</div>
                                                <div>
                                                    <h4>Apple Pay</h4>
                                                    <p>Touch ID or Face ID</p>
                                                </div>
                                            </label>
                                        </div>
                                    </div>
                                    
                                    <div class="form-section">
                                        <h2 class="section-title">Billing Address</h2>
                                        
                                        <div class="form-group">
                                            <label class="checkbox-label">
                                                <input type="checkbox" name="billing_same_as_shipping" 
                                                       <?= ($_SESSION['checkout']['billing_same_as_shipping'] ?? true) ? 'checked' : '' ?>>
                                                Same as shipping address
                                            </label>
                                        </div>
                                        
                                        <div id="billing-address" style="display: <?= ($_SESSION['checkout']['billing_same_as_shipping'] ?? true) ? 'none' : 'block' ?>;">
                                            <div class="form-row">
                                                <div class="form-group">
                                                    <label for="billing_first_name">First Name *</label>
                                                    <input type="text" id="billing_first_name" name="billing_first_name"
                                                           value="<?= safeHtml($_SESSION['checkout']['billing_address']['first_name'] ?? '') ?>">
                                                </div>
                                                <div class="form-group">
                                                    <label for="billing_last_name">Last Name *</label>
                                                    <input type="text" id="billing_last_name" name="billing_last_name"
                                                           value="<?= safeHtml($_SESSION['checkout']['billing_address']['last_name'] ?? '') ?>">
                                                </div>
                                            </div>
                                            
                                            <!-- More billing fields... -->
                                        </div>
                                    </div>
                                    
                                    <div class="checkout-actions">
                                        <a href="?step=shipping" class="btn btn-secondary">
                                            ← Back to Shipping
                                        </a>
                                        <button type="submit" class="btn btn-primary">
                                            Review Order →
                                        </button>
                                    </div>
                                </form>
                                
                            <?php elseif ($step === 'review'): ?>
                                <!-- Order Review -->
                                <div class="form-section">
                                    <h2 class="section-title">Order Review</h2>
                                    
                                    <div class="review-section">
                                        <h3>Shipping Address</h3>
                                        <div class="address-display">
                                            <?= safeHtml(($_SESSION['checkout']['shipping_address']['first_name'] ?? '') . ' ' . ($_SESSION['checkout']['shipping_address']['last_name'] ?? '')) ?><br>
                                            <?= safeHtml($_SESSION['checkout']['shipping_address']['address_line1'] ?? '') ?><br>
                                            <?php if (!empty($_SESSION['checkout']['shipping_address']['address_line2'])): ?>
                                                <?= safeHtml($_SESSION['checkout']['shipping_address']['address_line2']) ?><br>
                                            <?php endif; ?>
                                            <?= safeHtml(($_SESSION['checkout']['shipping_address']['city'] ?? '') . ', ' . ($_SESSION['checkout']['shipping_address']['state'] ?? '') . ' ' . ($_SESSION['checkout']['shipping_address']['postal_code'] ?? '')) ?><br>
                                            <?= safeHtml($_SESSION['checkout']['shipping_address']['country'] ?? '') ?>
                                        </div>
                                        <a href="?step=shipping" class="edit-link">Edit</a>
                                    </div>
                                    
                                    <div class="review-section">
                                        <h3>Payment Method</h3>
                                        <div class="payment-display">
                                            <?= safeHtml(ucwords(str_replace('_', ' ', $_SESSION['checkout']['payment_method'] ?? 'Credit Card'))) ?>
                                        </div>
                                        <a href="?step=payment" class="edit-link">Edit</a>
                                    </div>
                                </div>
                                
                                <form method="POST">
                                    <input type="hidden" name="action" value="place_order">
                                    
                                    <div class="checkout-actions">
                                        <a href="?step=payment" class="btn btn-secondary">
                                            ← Back to Payment
                                        </a>
                                        <button type="submit" class="btn btn-primary">
                                            Place Order
                                        </button>
                                    </div>
                                </form>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Order Summary -->
                        <div class="order-summary">
                            <h3>Order Summary</h3>
                            
                            <?php foreach ($cart_items as $item): ?>
                                <div class="summary-item">
                                    <div class="summary-image">
                                        <?php if ($item['image_url']): ?>
                                            <img src="<?= safeHtml($item['image_url']) ?>" 
                                                 alt="<?= safeHtml($item['product_name']) ?>">
                                        <?php else: ?>
                                            📷
                                        <?php endif; ?>
                                    </div>
                                    <div class="summary-details">
                                        <div class="summary-name"><?= safeHtml($item['product_name']) ?></div>
                                        <div class="summary-meta">Qty: <?= $item['quantity'] ?></div>
                                    </div>
                                    <div class="summary-price"><?= formatCurrency($item['item_total']) ?></div>
                                </div>
                            <?php endforeach; ?>
                            
                            <div class="summary-totals">
                                <div class="summary-line">
                                    <span>Subtotal:</span>
                                    <span><?= formatCurrency($cart_total) ?></span>
                                </div>
                                <div class="summary-line">
                                    <span>Shipping:</span>
                                    <span><?= formatCurrency($shipping_cost) ?></span>
                                </div>
                                <div class="summary-line">
                                    <span>Tax:</span>
                                    <span><?= formatCurrency($tax_amount) ?></span>
                                </div>
                                <div class="summary-line total">
                                    <span>Total:</span>
                                    <span><?= formatCurrency($order_total) ?></span>
                                </div>
                            </div>
                            
                            <div class="security-info mt-3">
                                <small>
                                    🔒 Secure 256-bit SSL encryption<br>
                                    📱 PCI DSS compliant payments<br>
                                    🛡️ Fraud protection guaranteed
                                </small>
                            </div>
                        </div>
                    </div>
                    
                <?php else: ?>
                    <!-- Order Confirmation -->
                    <div class="confirmation-content">
                        <div class="confirmation-icon">✅</div>
                        <h2>Order Confirmed!</h2>
                        <p>Thank you for your purchase. Your order has been successfully placed.</p>
                        
                        <?php if ($order): ?>
                            <div class="order-details">
                                <h3>Order Details</h3>
                                <p><strong>Order Number:</strong> #<?= str_pad($order['order_id'], 6, '0', STR_PAD_LEFT) ?></p>
                                <p><strong>Order Date:</strong> <?= date('F j, Y', strtotime($order['created_at'])) ?></p>
                                <p><strong>Total Amount:</strong> <?= formatCurrency($order['total_amount']) ?></p>
                                <p><strong>Payment Method:</strong> <?= ucwords(str_replace('_', ' ', $order['payment_method'])) ?></p>
                            </div>
                            
                            <div class="confirmation-actions">
                                <a href="../../index.php" class="btn btn-primary">Continue Shopping</a>
                                <a href="../user/orders.php" class="btn btn-secondary">View Order History</a>
                            </div>
                        <?php endif; ?>
                        
                        <div class="next-steps mt-4">
                            <h3>What's Next?</h3>
                            <ul style="text-align: left; max-width: 400px; margin: 0 auto;">
                                <li>📧 You'll receive an order confirmation email shortly</li>
                                <li>📦 Your items will be carefully packaged</li>
                                <li>🚚 You'll get tracking information when shipped</li>
                                <li>📱 Track your order status in your account</li>
                            </ul>
                        </div>
                    </div>
                <?php endif; ?>
                
                <?php if ($step === 'confirmation' && $order): ?>
                    <!-- Success Alert Banner -->
                    <?php if (isset($_SESSION['order_success'])): ?>
                        <div class="alert alert-success" style="background: linear-gradient(135deg, #28a745, #20c997); border: none; color: white; padding: 1.5rem; border-radius: 10px; margin-bottom: 2rem; text-align: center; font-size: 1.2rem; font-weight: 600; box-shadow: 0 4px 15px rgba(40, 167, 69, 0.3);">
                            <div style="font-size: 2rem; margin-bottom: 0.5rem;">🎉</div>
                            <div><?= htmlspecialchars($_SESSION['order_success']['message']) ?></div>
                            <div style="font-size: 0.9rem; margin-top: 0.5rem; opacity: 0.9;">
                                Your order is now being processed and you'll receive updates via email.
                            </div>
                        </div>
                        <?php 
                        // Clear the success message after displaying it
                        unset($_SESSION['order_success']); 
                        ?>
                    <?php else: ?>
                        <div class="alert alert-success" style="background: #d4edda; border: 1px solid #c3e6cb; color: #155724; padding: 1rem; border-radius: 8px; margin-bottom: 2rem; text-align: center; font-size: 1.1rem; font-weight: 600;">
                            🎉 Your order has been confirmed!
                        </div>
                    <?php endif; ?>
                    
                    <!-- Order Confirmation -->
                    <div class="confirmation-container">
                        <div class="confirmation-content">
                            <div class="success-icon">
                                ✅
                            </div>
                            
                            <h1 class="confirmation-title">Order Placed Successfully!</h1>
                            
                            <div class="confirmation-message">
                                <p>Thank you for your purchase! Your order has been successfully placed and is being processed.</p>
                                <div class="order-details">
                                    <h3>Order Details</h3>
                                    <div class="detail-item">
                                        <span class="label">Order Number:</span>
                                        <span class="value">#<?= $order['order_id'] ?></span>
                                    </div>
                                    <div class="detail-item">
                                        <span class="label">Total Amount:</span>
                                        <span class="value"><?= formatCurrency($order['total_price']) ?></span>
                                    </div>
                                    <div class="detail-item">
                                        <span class="label">Status:</span>
                                        <span class="value"><?= ucfirst($order['status']) ?></span>
                                    </div>
                                    <div class="detail-item">
                                        <span class="label">Order Date:</span>
                                        <span class="value"><?= date('F j, Y \a\t g:i A', strtotime($order['order_date'])) ?></span>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="next-steps">
                                <h3>What's Next?</h3>
                                <ul>
                                    <li>📧 You will receive an email confirmation shortly</li>
                                    <li>📦 Your order will be processed within 1-2 business days</li>
                                    <li>🚚 You'll receive shipping updates via email</li>
                                    <li>👤 Track your order anytime in your account</li>
                                </ul>
                            </div>
                            
                            <div class="confirmation-actions">
                                <a href="../user/orders.php" class="btn btn-primary">View My Orders</a>
                                <a href="../products/catalog.php" class="btn btn-secondary">Continue Shopping</a>
                            </div>
                        </div>
                    </div>
                    
                    <style>
                        .confirmation-container {
                            max-width: 600px;
                            margin: 2rem auto;
                            padding: 2rem;
                            text-align: center;
                        }
                        
                        .success-icon {
                            font-size: 4rem;
                            margin-bottom: 1rem;
                        }
                        
                        .confirmation-title {
                            color: #28a745;
                            margin-bottom: 1rem;
                            font-size: 2rem;
                        }
                        
                        .confirmation-message {
                            margin-bottom: 2rem;
                        }
                        
                        .order-details {
                            background: #f8f9fa;
                            padding: 1.5rem;
                            border-radius: 8px;
                            margin: 1.5rem 0;
                            text-align: left;
                        }
                        
                        .order-details h3 {
                            margin-bottom: 1rem;
                            color: #495057;
                        }
                        
                        .detail-item {
                            display: flex;
                            justify-content: space-between;
                            padding: 0.5rem 0;
                            border-bottom: 1px solid #dee2e6;
                        }
                        
                        .detail-item:last-child {
                            border-bottom: none;
                        }
                        
                        .detail-item .label {
                            font-weight: 600;
                            color: #6c757d;
                        }
                        
                        .detail-item .value {
                            color: #495057;
                        }
                        
                        .next-steps {
                            background: #e7f3ff;
                            padding: 1.5rem;
                            border-radius: 8px;
                            margin: 1.5rem 0;
                            text-align: left;
                        }
                        
                        .next-steps h3 {
                            color: #0066cc;
                            margin-bottom: 1rem;
                        }
                        
                        .next-steps ul {
                            list-style: none;
                            padding: 0;
                        }
                        
                        .next-steps li {
                            padding: 0.5rem 0;
                            color: #495057;
                        }
                        
                        .confirmation-actions {
                            margin-top: 2rem;
                        }
                        
                        .confirmation-actions .btn {
                            margin: 0 0.5rem;
                            padding: 0.75rem 1.5rem;
                            text-decoration: none;
                            border-radius: 5px;
                            display: inline-block;
                        }
                        
                        .confirmation-actions .btn-primary {
                            background-color: #007bff;
                            color: white;
                        }
                        
                        .confirmation-actions .btn-secondary {
                            background-color: #6c757d;
                            color: white;
                        }
                        
                        .confirmation-actions .btn:hover {
                            opacity: 0.9;
                        }
                    </style>
                <?php elseif ($step === 'confirmation'): ?>
                    <!-- Order Not Found -->
                    <div class="confirmation-container">
                        <div class="confirmation-content">
                            <div class="error-icon">
                                ❌
                            </div>
                            <h2>Order Not Found</h2>
                            <p>We couldn't find your order details. This might happen if:</p>
                            <ul style="text-align: left; max-width: 400px; margin: 1rem auto;">
                                <li>The order was placed from a different account</li>
                                <li>There was an issue during the checkout process</li>
                                <li>The order ID is invalid</li>
                            </ul>
                            <div class="confirmation-actions">
                                <a href="../user/orders.php" class="btn btn-primary">View My Orders</a>
                                <a href="../products/catalog.php" class="btn btn-secondary">Continue Shopping</a>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>
    
    <?php include '../../includes/footer.php'; ?>
    
    <script src="../../assets/js/main.js"></script>
    <script>
        // Toggle billing address visibility
        document.addEventListener('change', function(event) {
            if (event.target.name === 'billing_same_as_shipping') {
                const billingAddress = document.getElementById('billing-address');
                if (billingAddress) {
                    billingAddress.style.display = event.target.checked ? 'none' : 'block';
                }
            }
            
            // Update shipping/payment option styling
            if (event.target.type === 'radio') {
                const container = event.target.closest('.shipping-methods, .payment-methods');
                if (container) {
                    container.querySelectorAll('.shipping-option, .payment-option').forEach(option => {
                        option.classList.remove('selected');
                    });
                    event.target.closest('.shipping-option, .payment-option').classList.add('selected');
                }
            }
        });
        
        // Form validation
        document.addEventListener('submit', function(event) {
            const form = event.target;
            const requiredFields = form.querySelectorAll('[required]');
            
            for (let field of requiredFields) {
                if (!field.value.trim()) {
                    event.preventDefault();
                    field.focus();
                    EVinty.showMessage('Please fill in all required fields', 'error');
                    return;
                }
            }
        });
        
        // Show toast notification for order success
        <?php if (isset($_SESSION['order_success']) && $step === 'confirmation'): ?>
        window.addEventListener('load', function() {
            showToast('🎉 Order Placed Successfully!', 'Your order #<?= $_SESSION['order_success']['order_id'] ?> has been confirmed!', 'success');
        });
        <?php endif; ?>
        
        // Toast notification function
        function showToast(title, message, type = 'success') {
            const toast = document.createElement('div');
            toast.className = `toast toast-${type}`;
            toast.style.cssText = `
                position: fixed;
                top: 20px;
                right: 20px;
                background: ${type === 'success' ? '#28a745' : '#dc3545'};
                color: white;
                padding: 1rem 1.5rem;
                border-radius: 8px;
                box-shadow: 0 4px 12px rgba(0,0,0,0.15);
                z-index: 10000;
                min-width: 300px;
                transform: translateX(400px);
                transition: transform 0.3s ease;
            `;
            toast.innerHTML = `
                <div style="font-weight: 600; margin-bottom: 0.5rem;">${title}</div>
                <div style="font-size: 0.9rem; opacity: 0.9;">${message}</div>
            `;
            
            document.body.appendChild(toast);
            
            // Animate in
            setTimeout(() => {
                toast.style.transform = 'translateX(0)';
            }, 100);
            
            // Auto hide after 5 seconds
            setTimeout(() => {
                toast.style.transform = 'translateX(400px)';
                setTimeout(() => {
                    document.body.removeChild(toast);
                }, 300);
            }, 5000);
        }
    </script>
</body>
</html>
