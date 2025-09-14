<?php
/**
 * Shopping Cart Page
 * Implements F110 - Shopping Cart functionality
 * Display and manage cart items
 */

require_once '../../includes/init.php';

// Require user to be logged in
requireLogin();

$user = getCurrentUser();
$cart_items = [];
$cart_total = 0;
$cart_count = 0;

try {
    // Get cart items
    $cart_items = $db->fetchAll(
        "SELECT c.*, p.product_name, p.price, p.image_url, p.stock, p.category,
                (c.quantity * p.price) as item_total
         FROM shopping_cart c
         JOIN products p ON c.product_id = p.product_id
         WHERE c.user_id = ? AND p.is_active = 1
         ORDER BY c.added_at DESC",
        [$user['user_id']]
    );
    
    // Calculate totals
    foreach ($cart_items as $item) {
        $cart_total += $item['item_total'];
        $cart_count += $item['quantity'];
    }
    
} catch (Exception $e) {
    error_log("Cart page error: " . $e->getMessage());
    $cart_items = [];
}

$page_title = "Shopping Cart - Again&Co";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $page_title ?></title>
    <link rel="stylesheet" href="../../assets/css/style.css">
    <style>
        .cart-container {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 2rem;
        }
        
        .cart-items {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }
        
        .cart-item {
            background: white;
            border-radius: 10px;
            padding: 1.5rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            display: grid;
            grid-template-columns: 100px 1fr auto auto auto;
            gap: 1rem;
            align-items: center;
        }
        
        .cart-item-image {
            width: 100px;
            height: 100px;
            background-color: #f8f9fa;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
        }
        
        .cart-item-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .cart-item-details {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }
        
        .cart-item-title {
            font-weight: 600;
            font-size: 1.125rem;
            color: #2c3e50;
        }
        
        .cart-item-title a {
            color: inherit;
            text-decoration: none;
        }
        
        .cart-item-title a:hover {
            color: #3498db;
        }
        
        .cart-item-meta {
            color: #7f8c8d;
            font-size: 0.875rem;
        }
        
        .cart-item-price {
            font-weight: bold;
            color: #27ae60;
            font-size: 1.125rem;
        }
        
        .quantity-controls {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .quantity-btn {
            width: 35px;
            height: 35px;
            border: 2px solid #e0e0e0;
            background: white;
            border-radius: 5px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
        }
        
        .quantity-btn:hover {
            background-color: #f8f9fa;
            border-color: #3498db;
        }
        
        .quantity-input {
            width: 60px;
            text-align: center;
            border: 2px solid #e0e0e0;
            border-radius: 5px;
            padding: 0.25rem;
        }
        
        .item-total {
            font-weight: bold;
            font-size: 1.25rem;
            color: #2c3e50;
        }
        
        .remove-item {
            background: none;
            border: none;
            color: #e74c3c;
            cursor: pointer;
            font-size: 1.5rem;
            padding: 0.5rem;
            border-radius: 5px;
        }
        
        .remove-item:hover {
            background-color: #f8d7da;
        }
        
        .cart-summary {
            background: white;
            border-radius: 10px;
            padding: 2rem;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            height: fit-content;
            position: sticky;
            top: 2rem;
        }
        
        .summary-line {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.75rem 0;
            border-bottom: 1px solid #ecf0f1;
        }
        
        .summary-line:last-child {
            border-bottom: none;
            font-weight: bold;
            font-size: 1.25rem;
            color: #2c3e50;
        }
        
        .empty-cart {
            text-align: center;
            padding: 4rem 2rem;
            color: #7f8c8d;
        }
        
        .empty-cart-icon {
            font-size: 4rem;
            margin-bottom: 1rem;
        }
        
        .continue-shopping {
            margin-top: 2rem;
        }
        
        @media (max-width: 768px) {
            .cart-container {
                grid-template-columns: 1fr;
            }
            
            .cart-item {
                grid-template-columns: 80px 1fr;
                grid-template-areas: 
                    "image details"
                    "controls controls"
                    "total remove";
                gap: 1rem;
            }
            
            .cart-item-image {
                grid-area: image;
                width: 80px;
                height: 80px;
            }
            
            .cart-item-details {
                grid-area: details;
            }
            
            .quantity-controls {
                grid-area: controls;
                justify-self: start;
            }
            
            .item-total {
                grid-area: total;
                justify-self: start;
            }
            
            .remove-item {
                grid-area: remove;
                justify-self: end;
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
                    <?= htmlspecialchars($flash['message']) ?>
                </div>
            <?php endif; ?>
            
            <div class="card">
                <div class="card-header">
                    <h1 class="card-title">Shopping Cart</h1>
                    <p>Review your items before checkout</p>
                </div>
                
                <?php if (!empty($cart_items)): ?>
                    <div class="cart-container">
                        <!-- Cart Items -->
                        <div class="cart-items-section">
                            <div class="cart-items">
                                <?php foreach ($cart_items as $item): ?>
                                    <div class="cart-item" data-product-id="<?= $item['product_id'] ?>">
                                        <!-- Product Image -->
                                        <div class="cart-item-image">
                                            <?php if ($item['image_url']): ?>
                                                <img src="<?= htmlspecialchars($item['image_url']) ?>" 
                                                     alt="<?= htmlspecialchars($item['product_name']) ?>">
                                            <?php else: ?>
                                                📷
                                            <?php endif; ?>
                                        </div>
                                        
                                        <!-- Product Details -->
                                        <div class="cart-item-details">
                                            <div class="cart-item-title">
                                                <a href="../products/detail.php?id=<?= $item['product_id'] ?>">
                                                    <?= htmlspecialchars($item['product_name']) ?>
                                                </a>
                                            </div>
                                            <div class="cart-item-meta">
                                                <?= htmlspecialchars($item['category']) ?> • 
                                                Unit Price: <?= formatCurrency($item['price']) ?>
                                            </div>
                                            <div class="cart-item-price">
                                                <?= formatCurrency($item['price']) ?> each
                                            </div>
                                        </div>
                                        
                                        <!-- Quantity Controls -->
                                        <div class="quantity-controls">
                                            <button type="button" 
                                                    class="quantity-btn quantity-decrease"
                                                    data-product-id="<?= $item['product_id'] ?>">−</button>
                                            <input type="number" 
                                                   class="quantity-input" 
                                                   value="<?= $item['quantity'] ?>"
                                                   min="1" 
                                                   max="<?= $item['stock'] ?>"
                                                   data-product-id="<?= $item['product_id'] ?>">
                                            <button type="button" 
                                                    class="quantity-btn quantity-increase"
                                                    data-product-id="<?= $item['product_id'] ?>">+</button>
                                        </div>
                                        
                                        <!-- Item Total -->
                                        <div class="item-total">
                                            <?= formatCurrency($item['item_total']) ?>
                                        </div>
                                        
                                        <!-- Remove Button -->
                                        <button type="button" 
                                                class="remove-item"
                                                data-product-id="<?= $item['product_id'] ?>"
                                                title="Remove item">
                                            ×
                                        </button>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            
                            <!-- Cart Actions -->
                            <div class="cart-actions mt-3">
                                <button type="button" class="btn btn-secondary" id="clear-cart">
                                    Clear Cart
                                </button>
                                <a href="../products/catalog.php" class="btn btn-secondary">
                                    Continue Shopping
                                </a>
                            </div>
                        </div>
                        
                        <!-- Cart Summary -->
                        <div class="cart-summary">
                            <h3>Order Summary</h3>
                            
                            <div class="summary-line">
                                <span>Items (<?= $cart_count ?>):</span>
                                <span><?= formatCurrency($cart_total) ?></span>
                            </div>
                            
                            <div class="summary-line">
                                <span>Shipping:</span>
                                <span>Calculated at checkout</span>
                            </div>
                            
                            <div class="summary-line">
                                <span>Tax:</span>
                                <span>Calculated at checkout</span>
                            </div>
                            
                            <div class="summary-line">
                                <span>Total:</span>
                                <span><?= formatCurrency($cart_total) ?></span>
                            </div>
                            
                            <div class="checkout-actions mt-3">
                                <a href="../checkout/index.php" class="btn btn-primary btn-block">
                                    Proceed to Checkout
                                </a>
                                
                                <div class="payment-methods mt-2">
                                    <small class="text-muted">We accept:</small>
                                    <div class="payment-icons">
                                        💳 💰 🏦
                                    </div>
                                </div>
                            </div>
                            
                            <div class="security-info mt-3">
                                <small>
                                    🔒 Secure checkout with SSL encryption<br>
                                    📱 Mobile-friendly payment options<br>
                                    🚚 Fast and reliable shipping
                                </small>
                            </div>
                        </div>
                    </div>
                    
                <?php else: ?>
                    <!-- Empty Cart -->
                    <div class="empty-cart">
                        <div class="empty-cart-icon">🛒</div>
                        <h2>Your cart is empty</h2>
                        <p>Looks like you haven't added any vintage treasures to your cart yet.</p>
                        
                        <div class="continue-shopping">
                            <a href="../products/catalog.php" class="btn btn-primary">
                                Start Shopping
                            </a>
                        </div>
                        
                        <!-- Suggested Categories -->
                        <div class="suggested-categories mt-4">
                            <h3>Popular Categories</h3>
                            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; margin-top: 1rem;">
                                <a href="../products/catalog.php?category=Clothing" class="btn btn-secondary">
                                    👔 Vintage Clothing
                                </a>
                                <a href="../products/catalog.php?category=Accessories" class="btn btn-secondary">
                                    👜 Accessories
                                </a>
                                <a href="../products/catalog.php?category=Music" class="btn btn-secondary">
                                    🎵 Music & Records
                                </a>
                                <a href="../products/catalog.php?category=Home" class="btn btn-secondary">
                                    🏠 Home & Decor
                                </a>
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
        // Update cart item quantity
        function updateCartQuantity(productId, quantity) {
            fetch('../../api/cart.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({
                    action: 'update',
                    product_id: productId,
                    quantity: quantity
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Reload page to update totals
                    window.location.reload();
                } else {
                    EVinty.showMessage(data.message || 'Failed to update cart', 'error');
                }
            })
            .catch(error => {
                console.error('Cart update error:', error);
                EVinty.showMessage('Network error occurred', 'error');
            });
        }
        
        // Remove item from cart
        function removeCartItem(productId) {
            if (!confirm('Are you sure you want to remove this item from your cart?')) {
                return;
            }
            
            fetch('../../api/cart.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({
                    action: 'remove',
                    product_id: productId
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Remove the item from DOM
                    const cartItem = document.querySelector(`[data-product-id="${productId}"]`);
                    if (cartItem) {
                        cartItem.remove();
                    }
                    
                    // Reload page to update totals
                    window.location.reload();
                } else {
                    EVinty.showMessage(data.message || 'Failed to remove item', 'error');
                }
            })
            .catch(error => {
                console.error('Cart remove error:', error);
                EVinty.showMessage('Network error occurred', 'error');
            });
        }
        
        // Clear entire cart
        function clearCart() {
            if (!confirm('Are you sure you want to clear your entire cart?')) {
                return;
            }
            
            fetch('../../api/cart.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({
                    action: 'clear'
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    window.location.reload();
                } else {
                    EVinty.showMessage(data.message || 'Failed to clear cart', 'error');
                }
            })
            .catch(error => {
                console.error('Cart clear error:', error);
                EVinty.showMessage('Network error occurred', 'error');
            });
        }
        
        // Event listeners
        document.addEventListener('click', function(event) {
            const productId = event.target.dataset.productId;
            
            if (event.target.classList.contains('quantity-decrease')) {
                const input = event.target.nextElementSibling;
                const currentValue = parseInt(input.value);
                if (currentValue > 1) {
                    const newValue = currentValue - 1;
                    input.value = newValue;
                    updateCartQuantity(productId, newValue);
                }
            }
            
            if (event.target.classList.contains('quantity-increase')) {
                const input = event.target.previousElementSibling;
                const currentValue = parseInt(input.value);
                const maxValue = parseInt(input.getAttribute('max'));
                if (currentValue < maxValue) {
                    const newValue = currentValue + 1;
                    input.value = newValue;
                    updateCartQuantity(productId, newValue);
                }
            }
            
            if (event.target.classList.contains('remove-item')) {
                removeCartItem(productId);
            }
            
            if (event.target.id === 'clear-cart') {
                clearCart();
            }
        });
        
        // Handle quantity input changes
        document.addEventListener('change', function(event) {
            if (event.target.classList.contains('quantity-input')) {
                const productId = event.target.dataset.productId;
                const quantity = parseInt(event.target.value);
                const min = parseInt(event.target.getAttribute('min'));
                const max = parseInt(event.target.getAttribute('max'));
                
                if (quantity < min) {
                    event.target.value = min;
                    updateCartQuantity(productId, min);
                } else if (quantity > max) {
                    event.target.value = max;
                    updateCartQuantity(productId, max);
                } else {
                    updateCartQuantity(productId, quantity);
                }
            }
        });
    </script>
</body>
</html>
