<?php
/**
 * Order Thank You Page
 * Success page after order completion
 */

require_once '../../includes/init.php';

// Require user to be logged in
requireLogin();

$user = getCurrentUser();
$order = null;
$order_id = $_GET['order_id'] ?? null;

// Get order details if order_id is provided
if ($order_id) {
    try {
        $order = $db->fetch(
            "SELECT * FROM orders WHERE order_id = ? AND user_id = ?",
            [$order_id, $user['user_id']]
        );
    } catch (Exception $e) {
        error_log("Thank you page error: " . $e->getMessage());
    }
}

// Check for recent order success
$recent_order = null;
if (isset($_SESSION['order_success'])) {
    $recent_order = $_SESSION['order_success'];
    // Clear after displaying
    unset($_SESSION['order_success']);
}

$page_title = "Thank You - Order Confirmation - Again&Co";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $page_title ?></title>
    <link rel="stylesheet" href="../../assets/css/style.css">
    <style>
        .thank-you-container {
            max-width: 700px;
            margin: 3rem auto;
            text-align: center;
        }
        
        .success-animation {
            font-size: 5rem;
            margin-bottom: 2rem;
            animation: bounce 2s infinite;
        }
        
        @keyframes bounce {
            0%, 20%, 50%, 80%, 100% {
                transform: translateY(0);
            }
            40% {
                transform: translateY(-30px);
            }
            60% {
                transform: translateY(-15px);
            }
        }
        
        .success-title {
            color: #28a745;
            font-size: 2.5rem;
            margin-bottom: 1rem;
            font-weight: 700;
        }
        
        .success-message {
            font-size: 1.2rem;
            color: #6c757d;
            margin-bottom: 2rem;
            line-height: 1.6;
        }
        
        .order-summary {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 2rem;
            margin: 2rem 0;
            border-left: 5px solid #28a745;
        }
        
        .order-detail {
            display: flex;
            justify-content: space-between;
            margin-bottom: 1rem;
            padding: 0.5rem 0;
            border-bottom: 1px solid #dee2e6;
        }
        
        .order-detail:last-child {
            border-bottom: none;
            font-weight: 600;
            font-size: 1.1rem;
        }
        
        .next-steps {
            background: white;
            border: 1px solid #dee2e6;
            border-radius: 10px;
            padding: 2rem;
            margin: 2rem 0;
            text-align: left;
        }
        
        .next-steps h3 {
            color: #28a745;
            margin-bottom: 1rem;
        }
        
        .next-steps ul {
            list-style: none;
            padding: 0;
        }
        
        .next-steps li {
            padding: 0.5rem 0;
            border-bottom: 1px solid #f8f9fa;
        }
        
        .next-steps li:last-child {
            border-bottom: none;
        }
        
        .action-buttons {
            margin-top: 3rem;
        }
        
        .btn {
            margin: 0.5rem;
            padding: 1rem 2rem;
            font-size: 1.1rem;
        }
    </style>
</head>
<body>
    <?php include '../../includes/header.php'; ?>
    
    <main class="main-content">
        <div class="container">
            <!-- Flash Messages -->
            <?php
            $flash = getFlashMessage();
            if ($flash): ?>
                <div class="alert alert-<?= $flash['type'] ?>">
                    <?= htmlspecialchars($flash['message']) ?>
                </div>
            <?php endif; ?>
            
            <div class="thank-you-container">
                <div class="success-animation">🎉</div>
                
                <h1 class="success-title">Order Placed Successfully!</h1>
                
                <div class="success-message">
                    <?php if ($recent_order): ?>
                        <p><strong><?= htmlspecialchars($recent_order['message']) ?></strong></p>
                    <?php endif; ?>
                    <p>Thank you for your purchase! Your order has been confirmed and is now being processed.</p>
                    <p>You will receive an email confirmation shortly with your order details and tracking information.</p>
                </div>
                
                <?php if ($order): ?>
                    <div class="order-summary">
                        <h3>Order Summary</h3>
                        <div class="order-detail">
                            <span>Order Number:</span>
                            <span><strong>#<?= $order['order_id'] ?></strong></span>
                        </div>
                        <div class="order-detail">
                            <span>Order Date:</span>
                            <span><?= date('F j, Y \a\t g:i A', strtotime($order['order_date'])) ?></span>
                        </div>
                        <div class="order-detail">
                            <span>Status:</span>
                            <span><span class="badge badge-<?= $order['status'] ?>"><?= ucfirst($order['status']) ?></span></span>
                        </div>
                        <div class="order-detail">
                            <span>Total Amount:</span>
                            <span><strong><?= formatCurrency($order['total_price']) ?></strong></span>
                        </div>
                    </div>
                <?php endif; ?>
                
                <div class="next-steps">
                    <h3>What Happens Next?</h3>
                    <ul>
                        <li>📧 <strong>Email Confirmation:</strong> You'll receive a detailed order confirmation in your inbox within 5 minutes</li>
                        <li>📦 <strong>Order Processing:</strong> Your order will be processed and prepared for shipping within 1-2 business days</li>
                        <li>🚚 <strong>Shipping Updates:</strong> You'll receive tracking information once your order ships</li>
                        <li>👤 <strong>Track Your Order:</strong> Visit "My Orders" anytime to check your order status</li>
                        <li>💬 <strong>Need Help?:</strong> Contact our customer service team if you have any questions</li>
                    </ul>
                </div>
                
                <div class="action-buttons">
                    <a href="../user/orders.php" class="btn btn-primary">View My Orders</a>
                    <a href="../products/catalog.php" class="btn btn-secondary">Continue Shopping</a>
                    <a href="<?= SITE_URL ?>" class="btn btn-outline">Back to Home</a>
                </div>
            </div>
        </div>
    </main>
    
    <?php include '../../includes/footer.php'; ?>
    
    <!-- Toast Notification -->
    <script>
        // Show toast notification on page load
        window.addEventListener('load', function() {
            <?php if ($recent_order): ?>
            showToast('🎉 Order Successfully Placed!', 'Your order #<?= $recent_order['order_id'] ?> has been confirmed!', 'success');
            <?php endif; ?>
        });
        
        // Toast notification function
        function showToast(title, message, type = 'success') {
            const toast = document.createElement('div');
            toast.style.cssText = `
                position: fixed;
                top: 20px;
                right: 20px;
                background: ${type === 'success' ? '#28a745' : '#dc3545'};
                color: white;
                padding: 1.5rem 2rem;
                border-radius: 10px;
                box-shadow: 0 6px 20px rgba(0,0,0,0.2);
                z-index: 10000;
                min-width: 350px;
                max-width: 500px;
                transform: translateX(600px);
                transition: transform 0.4s ease;
                font-family: Arial, sans-serif;
            `;
            toast.innerHTML = `
                <div style="font-weight: 700; margin-bottom: 0.5rem; font-size: 1.1rem;">${title}</div>
                <div style="font-size: 1rem; opacity: 0.95; line-height: 1.4;">${message}</div>
            `;
            
            document.body.appendChild(toast);
            
            // Animate in
            setTimeout(() => {
                toast.style.transform = 'translateX(0)';
            }, 100);
            
            // Auto hide after 6 seconds
            setTimeout(() => {
                toast.style.transform = 'translateX(600px)';
                setTimeout(() => {
                    if (document.body.contains(toast)) {
                        document.body.removeChild(toast);
                    }
                }, 400);
            }, 6000);
        }
    </script>
</body>
</html>