<?php
require_once '../../includes/init.php';

// Check if user is logged in
if (!isLoggedIn()) {
    header('Location: ../auth/login.php');
    exit;
}

$user = getCurrentUser();
$user_id = $user['user_id'];

// Get specific order details if order_id is provided
$order_detail = null;
$order_id = isset($_GET['order_id']) ? (int)$_GET['order_id'] : null;

if ($order_id) {
    // Get specific order details
    $order_query = "
        SELECT o.*, 
               COALESCE(up.street_name, '') as street_name,
               COALESCE(up.suburb, '') as suburb,
               COALESCE(up.state, '') as state,
               COALESCE(up.postcode, '') as postcode
        FROM orders o
        LEFT JOIN user_profiles up ON o.user_id = up.user_id
        WHERE o.order_id = ? AND o.user_id = ?
    ";
    
    $order_detail = $db->fetch($order_query, [$order_id, $user_id]);
    
    if ($order_detail) {
        // Get order items
        $items_query = "
            SELECT oi.*, p.product_name as name, p.description, p.image_url
            FROM order_items oi
            JOIN products p ON oi.product_id = p.product_id
            WHERE oi.order_id = ?
            ORDER BY oi.order_item_id
        ";
        $order_detail['items'] = $db->fetchAll($items_query, [$order_id]);
    }
}

// Get all orders for this user
$orders_query = "
    SELECT o.*, COUNT(oi.order_item_id) as item_count
    FROM orders o
    LEFT JOIN order_items oi ON o.order_id = oi.order_id
    WHERE o.user_id = ?
    GROUP BY o.order_id
    ORDER BY o.order_date DESC
";

$orders = $db->fetchAll($orders_query, [$user_id]);

// Helper function to format order status
function getStatusBadge($status) {
    $badges = [
        'pending' => 'badge-warning',
        'confirmed' => 'badge-info',
        'processing' => 'badge-primary',
        'shipped' => 'badge-secondary',
        'delivered' => 'badge-success',
        'cancelled' => 'badge-danger'
    ];
    
    $class = $badges[$status] ?? 'badge-secondary';
    return "<span class='order-status-badge {$class}'>" . ucfirst($status) . "</span>";
}

// Helper function to format order date
function formatOrderDate($date) {
    return date('M j, Y g:i A', strtotime($date));
}

$page_title = "My Orders - Again&Co";
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
    <div style="max-width: 800px; margin: 2rem auto;">
        <?php
        $flash = getFlashMessage();
        if ($flash): ?>
            <div class="alert alert-<?= $flash['type'] ?>">
                <?= safeHtml($flash['message']) ?>
            </div>
        <?php endif; ?>

            <?php if ($order_detail): ?>
                <!-- Order Detail View -->
                <div class="card">
                    <div class="card-header">
                        <div style="display: flex; justify-content: space-between; align-items: center;">
                            <h1 class="card-title">Order #<?= $order_detail['order_id'] ?></h1>
                            <a href="orders.php" class="btn btn-secondary">
                                ← Back to Orders
                            </a>
                        </div>
                    </div>
                    <div class="card-body">
                        <!-- Order Summary -->
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 2rem; margin-bottom: 2rem;">
                            <div>
                                <h3>Order Information</h3>
                                <p><strong>Order Date:</strong> <?= formatOrderDate($order_detail['order_date']) ?></p>
                                <p><strong>Status:</strong> <?= getStatusBadge($order_detail['status']) ?></p>
                                <p><strong>Total:</strong> $<?= number_format($order_detail['total_price'], 2) ?></p>
                            </div>
                            <div>
                                <h3>Shipping Address</h3>
                                <p>
                                    <?= safeHtml($user['name']) ?><br>
                                    <?php if (!empty($order_detail['street_name'])): ?>
                                        <?= safeHtml($order_detail['street_name']) ?><br>
                                        <?= safeHtml($order_detail['suburb']) ?>, <?= safeHtml($order_detail['state']) ?> <?= safeHtml($order_detail['postcode']) ?>
                                    <?php else: ?>
                                        <em>Address not available</em>
                                    <?php endif; ?>
                                </p>
                            </div>
                        </div>

                        <?php if (!empty($order_detail['notes'])): ?>
                            <?php 
                            // Try to parse order notes as JSON to extract meaningful information
                            $notes_data = json_decode($order_detail['notes'], true);
                            if ($notes_data && is_array($notes_data)): ?>
                                <div style="margin-bottom: 2rem;">
                                    <h3>Order Summary</h3>
                                    <div style="background-color: #f8f9fa; padding: 1rem; border-radius: 8px;">
                                        <?php if (isset($notes_data['subtotal'])): ?>
                                            <p><strong>Subtotal:</strong> $<?= number_format((float)$notes_data['subtotal'], 2) ?></p>
                                        <?php endif; ?>
                                        
                                        <?php if (isset($notes_data['shipping_cost']) && $notes_data['shipping_cost'] > 0): ?>
                                            <p><strong>Shipping:</strong> $<?= number_format((float)$notes_data['shipping_cost'], 2) ?></p>
                                        <?php endif; ?>
                                        
                                        <?php if (isset($notes_data['tax_amount']) && $notes_data['tax_amount'] > 0): ?>
                                            <p><strong>Tax:</strong> $<?= number_format((float)$notes_data['tax_amount'], 2) ?></p>
                                        <?php endif; ?>
                                        
                                        <?php if (isset($notes_data['shipping_method'])): ?>
                                            <p><strong>Shipping Method:</strong> <?= ucfirst(str_replace('_', ' ', $notes_data['shipping_method'])) ?></p>
                                        <?php endif; ?>
                                        
                                        <?php if (isset($notes_data['payment_method'])): ?>
                                            <p><strong>Payment Method:</strong> <?= ucfirst(str_replace('_', ' ', $notes_data['payment_method'])) ?></p>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php else: ?>
                                <!-- Only show notes if they're not JSON data -->
                                <?php if (!json_decode($order_detail['notes']) && substr(trim($order_detail['notes']), 0, 1) !== '{'): ?>
                                    <div style="margin-bottom: 2rem;">
                                        <h3>Order Notes</h3>
                                        <p style="color: #666;"><?= safeHtml($order_detail['notes']) ?></p>
                                    </div>
                                <?php endif; ?>
                            <?php endif; ?>
                        <?php endif; ?>

                        <!-- Order Items -->
                        <h3>Order Items</h3>
                        <div class="orders-table">
                            <table style="width: 100%; border-collapse: collapse;">
                                <thead>
                                    <tr style="background-color: #f8f9fa;">
                                        <th style="padding: 1rem; text-align: left; border-bottom: 2px solid #dee2e6;">Product</th>
                                        <th style="padding: 1rem; text-align: left; border-bottom: 2px solid #dee2e6;">Quantity</th>
                                        <th style="padding: 1rem; text-align: left; border-bottom: 2px solid #dee2e6;">Unit Price</th>
                                        <th style="padding: 1rem; text-align: left; border-bottom: 2px solid #dee2e6;">Total</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($order_detail['items'] as $item): ?>
                                        <tr style="border-bottom: 1px solid #dee2e6;">
                                            <td style="padding: 1rem;">
                                                <div style="display: flex; align-items: center;">
                                                    <?php if ($item['image_url']): ?>
                                                        <img src="../../<?= safeHtml($item['image_url']) ?>" 
                                                             alt="<?= safeHtml($item['name']) ?>" 
                                                             style="width: 50px; height: 50px; object-fit: cover; border-radius: 4px; margin-right: 1rem;">
                                                    <?php endif; ?>
                                                    <div>
                                                        <strong><?= safeHtml($item['name']) ?></strong>
                                                        <?php if ($item['description']): ?>
                                                            <br><small style="color: #666;"><?= safeHtml(substr($item['description'], 0, 100)) ?><?= strlen($item['description']) > 100 ? '...' : '' ?></small>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </td>
                                            <td style="padding: 1rem;"><?= $item['quantity'] ?></td>
                                            <td style="padding: 1rem;">$<?= number_format($item['unit_price'], 2) ?></td>
                                            <td style="padding: 1rem;">$<?= number_format($item['total_price'], 2) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                                <tfoot>
                                    <?php 
                                    // Calculate items subtotal
                                    $items_subtotal = 0;
                                    foreach ($order_detail['items'] as $item) {
                                        $items_subtotal += $item['total_price'];
                                    }
                                    
                                    // Try to get breakdown from notes
                                    $notes_data = json_decode($order_detail['notes'], true);
                                    $has_breakdown = $notes_data && is_array($notes_data);
                                    ?>
                                    
                                    <?php if ($has_breakdown): ?>
                                        <tr style="border-top: 1px solid #dee2e6;">
                                            <th colspan="3" style="padding: 0.5rem 1rem; text-align: right; border-top: 1px solid #dee2e6;">Items Subtotal:</th>
                                            <th style="padding: 0.5rem 1rem; border-top: 1px solid #dee2e6;">$<?= number_format($items_subtotal, 2) ?></th>
                                        </tr>
                                        
                                        <?php if (isset($notes_data['shipping_cost']) && $notes_data['shipping_cost'] > 0): ?>
                                            <tr>
                                                <th colspan="3" style="padding: 0.5rem 1rem; text-align: right;">Shipping:</th>
                                                <th style="padding: 0.5rem 1rem;">$<?= number_format((float)$notes_data['shipping_cost'], 2) ?></th>
                                            </tr>
                                        <?php endif; ?>
                                        
                                        <?php if (isset($notes_data['tax_amount']) && $notes_data['tax_amount'] > 0): ?>
                                            <tr>
                                                <th colspan="3" style="padding: 0.5rem 1rem; text-align: right;">Tax:</th>
                                                <th style="padding: 0.5rem 1rem;">$<?= number_format((float)$notes_data['tax_amount'], 2) ?></th>
                                            </tr>
                                        <?php endif; ?>
                                        
                                        <tr style="background-color: #f8f9fa; font-weight: bold; border-top: 2px solid #dee2e6;">
                                            <th colspan="3" style="padding: 1rem; text-align: right; border-top: 2px solid #dee2e6;">Order Total:</th>
                                            <th style="padding: 1rem; border-top: 2px solid #dee2e6;">$<?= number_format($order_detail['total_price'], 2) ?></th>
                                        </tr>
                                    <?php else: ?>
                                        <tr style="background-color: #f8f9fa; font-weight: bold; border-top: 2px solid #dee2e6;">
                                            <th colspan="3" style="padding: 1rem; text-align: right; border-top: 2px solid #dee2e6;">Order Total:</th>
                                            <th style="padding: 1rem; border-top: 2px solid #dee2e6;">$<?= number_format($order_detail['total_price'], 2) ?></th>
                                        </tr>
                                    <?php endif; ?>
                                </tfoot>
                            </table>
                        </div>

                        <!-- Order Actions -->
                        <div style="margin-top: 2rem;">
                            <?php if ($order_detail['status'] === 'pending'): ?>
                                <button class="btn btn-danger" onclick="cancelOrder(<?= $order_detail['order_id'] ?>)">
                                    ✗ Cancel Order
                                </button>
                            <?php endif; ?>
                            
                            <?php if (in_array($order_detail['status'], ['confirmed', 'processing', 'shipped'])): ?>
                                <span style="color: #666;">
                                    ℹ Contact customer support if you need to make changes to this order.
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

            <?php else: ?>
                <!-- Orders List View -->
                <div class="card">
                    <div class="card-header">
                        <h1 class="card-title">My Orders</h1>
                        <p style="color: #666; margin: 0;">View your order history and track current orders</p>
                    </div>
                    <div class="card-body">
                        <?php if (empty($orders)): ?>
                            <div class="order-empty-state">
                                <div style="font-size: 3rem; margin-bottom: 1rem;">🛍️</div>
                                <h3>No Orders Yet</h3>
                                <p style="color: #666;">You haven't placed any orders yet. Start shopping to see your orders here!</p>
                                <a href="../products/catalog.php" class="btn btn-primary">
                                    🛒 Start Shopping
                                </a>
                            </div>
                        <?php else: ?>
                            <div class="orders-table">
                                <table style="width: 100%; border-collapse: collapse;">
                                    <thead>
                                        <tr style="background-color: #f8f9fa;">
                                            <th style="padding: 1rem; text-align: left; border-bottom: 2px solid #dee2e6;">Order #</th>
                                            <th style="padding: 1rem; text-align: left; border-bottom: 2px solid #dee2e6;">Date</th>
                                            <th style="padding: 1rem; text-align: left; border-bottom: 2px solid #dee2e6;">Items</th>
                                            <th style="padding: 1rem; text-align: left; border-bottom: 2px solid #dee2e6;">Status</th>
                                            <th style="padding: 1rem; text-align: left; border-bottom: 2px solid #dee2e6;">Total</th>
                                            <th style="padding: 1rem; text-align: left; border-bottom: 2px solid #dee2e6;">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($orders as $order): ?>
                                            <tr style="border-bottom: 1px solid #dee2e6;">
                                                <td style="padding: 1rem;">
                                                    <strong>#<?= $order['order_id'] ?></strong>
                                                </td>
                                                <td style="padding: 1rem;">
                                                    <?= formatOrderDate($order['order_date']) ?>
                                                </td>
                                                <td style="padding: 1rem;">
                                                    <span class="badge-light" style="padding: 0.25rem 0.5rem; background-color: #f8f9fa; border: 1px solid #dee2e6; border-radius: 4px; font-size: 0.875rem;"><?= $order['item_count'] ?> item<?= $order['item_count'] != 1 ? 's' : '' ?></span>
                                                </td>
                                                <td style="padding: 1rem;">
                                                    <?= getStatusBadge($order['status']) ?>
                                                </td>
                                                <td style="padding: 1rem;">
                                                    <strong>$<?= number_format($order['total_price'], 2) ?></strong>
                                                </td>
                                                <td style="padding: 1rem;">
                                                    <a href="orders.php?order_id=<?= $order['order_id'] ?>" class="btn btn-primary" style="font-size: 0.875rem; padding: 0.375rem 0.75rem;">
                                                        👁 View Details
                                                    </a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

<script>
function cancelOrder(orderId) {
    if (confirm('Are you sure you want to cancel this order?')) {
        // You can implement order cancellation logic here
        fetch('../../api/cancel-order.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ order_id: orderId })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                location.reload();
            } else {
                alert('Error cancelling order: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('An error occurred while cancelling the order.');
        });
    }
}
</script>

</main>

<?php include '../../includes/footer.php'; ?>
</body>
</html>