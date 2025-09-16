<?php
/**
 * Admin Dashboard
 * Main administrative interface
 */

require_once '../../includes/init.php';

// Require admin access
requireLogin();
requireRole(['admin', 'staff']);

$user = getCurrentUser();

// Get dashboard statistics
$stats = [];

try {
    // User statistics
    $stats['total_users'] = $db->fetch("SELECT COUNT(*) as count FROM users")['count'];
    $stats['new_users_today'] = $db->fetch(
        "SELECT COUNT(*) as count FROM users WHERE DATE(created_at) = CURDATE()"
    )['count'];
    
    // Product statistics
    $stats['total_products'] = $db->fetch("SELECT COUNT(*) as count FROM products WHERE is_active = 1")['count'];
    $stats['low_stock_products'] = $db->fetch(
        "SELECT COUNT(*) as count FROM products WHERE stock <= 5 AND is_active = 1"
    )['count'];
    
    // Order statistics
    $stats['total_orders'] = $db->fetch("SELECT COUNT(*) as count FROM orders")['count'];
    $stats['pending_orders'] = $db->fetch(
        "SELECT COUNT(*) as count FROM orders WHERE status = 'pending'"
    )['count'];
    $stats['todays_orders'] = $db->fetch(
        "SELECT COUNT(*) as count FROM orders WHERE DATE(created_at) = CURDATE()"
    )['count'];
    
    // Revenue statistics
    $stats['total_revenue'] = $db->fetch(
        "SELECT COALESCE(SUM(total_price), 0) as total FROM orders WHERE status = 'confirmed'"
    )['total'];
    $stats['todays_revenue'] = $db->fetch(
        "SELECT COALESCE(SUM(total_price), 0) as total FROM orders 
         WHERE status = 'confirmed' AND DATE(created_at) = CURDATE()"
    )['total'];
    
    // Recent orders
    $recent_orders = $db->fetchAll(
        "SELECT o.*, u.name, u.email 
         FROM orders o 
         JOIN users u ON o.user_id = u.user_id 
         ORDER BY o.created_at DESC 
         LIMIT 10"
    );
    
    // Low stock products
    $low_stock_products = $db->fetchAll(
        "SELECT product_id, product_name, stock, category 
         FROM products 
         WHERE stock <= 5 AND is_active = 1 
         ORDER BY stock ASC 
         LIMIT 10"
    );
    
} catch (Exception $e) {
    error_log("Admin dashboard error: " . $e->getMessage());
    $stats = array_fill_keys(['total_users', 'new_users_today', 'total_products', 'low_stock_products', 
                             'total_orders', 'pending_orders', 'todays_orders', 'total_revenue', 'todays_revenue'], 0);
    $recent_orders = [];
    $low_stock_products = [];
}

$page_title = "Admin Dashboard - Again&Co";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $page_title ?></title>
    <link rel="stylesheet" href="../../assets/css/style.css">
    <style>
        .admin-layout {
            display: grid;
            grid-template-columns: 250px 1fr;
            min-height: calc(100vh - 160px);
        }
        
        .admin-sidebar {
            background: #2c3e50;
            color: white;
            padding: 2rem 0;
        }
        
        .admin-nav {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        
        .admin-nav-item {
            margin-bottom: 0.5rem;
        }
        
        .admin-nav-link {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.75rem 1.5rem;
            color: #bdc3c7;
            text-decoration: none;
            transition: all 0.3s ease;
        }
        
        .admin-nav-link:hover,
        .admin-nav-link.active {
            background: #34495e;
            color: white;
        }
        
        .admin-content {
            padding: 2rem;
            background: #f8f9fa;
        }
        
        .dashboard-header {
            margin-bottom: 2rem;
        }
        
        .dashboard-header h1 {
            margin: 0 0 0.5rem 0;
            color: #2c3e50;
        }
        
        .dashboard-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .stat-card {
            background: white;
            border-radius: 10px;
            padding: 1.5rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        
        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            color: white;
        }
        
        .stat-icon.users { background: #3498db; }
        .stat-icon.products { background: #e74c3c; }
        .stat-icon.orders { background: #f39c12; }
        .stat-icon.revenue { background: #27ae60; }
        
        .stat-info h3 {
            margin: 0 0 0.25rem 0;
            font-size: 2rem;
            color: #2c3e50;
        }
        
        .stat-info p {
            margin: 0;
            color: #7f8c8d;
            font-size: 0.875rem;
        }
        
        .dashboard-sections {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 2rem;
        }
        
        .dashboard-section {
            background: white;
            border-radius: 10px;
            padding: 1.5rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
            padding-bottom: 0.5rem;
            border-bottom: 2px solid #ecf0f1;
        }
        
        .section-title {
            margin: 0;
            color: #2c3e50;
        }
        
        .section-link {
            color: #3498db;
            text-decoration: none;
            font-size: 0.875rem;
        }
        
        .section-link:hover {
            text-decoration: underline;
        }
        
        .order-list {
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
        }
        
        .order-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.75rem;
            background: #f8f9fa;
            border-radius: 5px;
        }
        
        .order-info {
            display: flex;
            flex-direction: column;
            gap: 0.25rem;
        }
        
        .order-id {
            font-weight: 600;
            color: #2c3e50;
        }
        
        .order-customer {
            color: #7f8c8d;
            font-size: 0.875rem;
        }
        
        .order-status {
            padding: 0.25rem 0.75rem;
            border-radius: 15px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .order-status.pending {
            background: #fef9e7;
            color: #f39c12;
        }
        
        .order-status.confirmed {
            background: #eafaf1;
            color: #27ae60;
        }
        
        .product-list {
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
        }
        
        .product-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.75rem;
            background: #f8f9fa;
            border-radius: 5px;
        }
        
        .product-info {
            display: flex;
            flex-direction: column;
            gap: 0.25rem;
        }
        
        .product-name {
            font-weight: 600;
            color: #2c3e50;
        }
        
        .product-category {
            color: #7f8c8d;
            font-size: 0.875rem;
        }
        
        .stock-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 15px;
            font-size: 0.75rem;
            font-weight: 600;
        }
        
        .stock-badge.critical {
            background: #fadbd8;
            color: #e74c3c;
        }
        
        .stock-badge.low {
            background: #fef9e7;
            color: #f39c12;
        }
        
        .quick-actions {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 1rem;
            margin-top: 2rem;
        }
        
        .quick-action {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 0.5rem;
            padding: 1rem;
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            text-decoration: none;
            color: #2c3e50;
            transition: transform 0.3s ease;
        }
        
        .quick-action:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 20px rgba(0,0,0,0.15);
        }
        
        .quick-action-icon {
            font-size: 2rem;
        }
        
        @media (max-width: 768px) {
            .admin-layout {
                grid-template-columns: 1fr;
            }
            
            .admin-sidebar {
                display: none;
            }
            
            .dashboard-sections {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <?php include '../../includes/header.php'; ?>
    
    <div class="admin-layout">
        <!-- Admin Sidebar -->
        <aside class="admin-sidebar">
            <nav>
                <ul class="admin-nav">
                    <li class="admin-nav-item">
                        <a href="index.php" class="admin-nav-link active">
                            📊 Dashboard
                        </a>
                    </li>
                    <li class="admin-nav-item">
                        <a href="products/index.php" class="admin-nav-link">
                            📦 Products
                        </a>
                    </li>
                    <li class="admin-nav-item">
                        <a href="orders/index.php" class="admin-nav-link">
                            🛒 Orders
                        </a>
                    </li>
                    <li class="admin-nav-item">
                        <a href="users/index.php" class="admin-nav-link">
                            👥 Users
                        </a>
                    </li>
                    <li class="admin-nav-item">
                        <a href="reports/index.php" class="admin-nav-link">
                            📈 Reports
                        </a>
                    </li>
                    <li class="admin-nav-item">
                        <a href="settings/index.php" class="admin-nav-link">
                            ⚙️ Settings
                        </a>
                    </li>
                </ul>
            </nav>
        </aside>
        
        <!-- Admin Content -->
        <main class="admin-content">
            <div class="dashboard-header">
                    <h1>Admin Dashboard - Again&Co</h1>
                <p>Welcome back, <?= htmlspecialchars($user['name'] ?? 'Admin') ?>! Here's what's happening with your store.</p>
            </div>
            
            <!-- Dashboard Statistics -->
            <div class="dashboard-stats">
                <div class="stat-card">
                    <div class="stat-icon users">👥</div>
                    <div class="stat-info">
                        <h3><?= number_format($stats['total_users']) ?></h3>
                        <p>Total Users (+<?= $stats['new_users_today'] ?> today)</p>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon products">📦</div>
                    <div class="stat-info">
                        <h3><?= number_format($stats['total_products']) ?></h3>
                        <p>Products (<?= $stats['low_stock_products'] ?> low stock)</p>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon orders">🛒</div>
                    <div class="stat-info">
                        <h3><?= number_format($stats['total_orders']) ?></h3>
                        <p>Total Orders (<?= $stats['pending_orders'] ?> pending)</p>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon revenue">💰</div>
                    <div class="stat-info">
                        <h3><?= formatCurrency($stats['total_revenue']) ?></h3>
                        <p>Revenue (<?= formatCurrency($stats['todays_revenue']) ?> today)</p>
                    </div>
                </div>
            </div>
            
            <!-- Dashboard Sections -->
            <div class="dashboard-sections">
                <!-- Recent Orders -->
                <div class="dashboard-section">
                    <div class="section-header">
                        <h2 class="section-title">Recent Orders</h2>
                        <a href="orders/index.php" class="section-link">View All</a>
                    </div>
                    
                    <div class="order-list">
                        <?php if (!empty($recent_orders)): ?>
                            <?php foreach (array_slice($recent_orders, 0, 5) as $order): ?>
                                <div class="order-item">
                                    <div class="order-info">
                                        <div class="order-id">#<?= str_pad($order['order_id'], 6, '0', STR_PAD_LEFT) ?></div>
                                        <div class="order-customer">
                                            <?= htmlspecialchars($order['name'] ?? 'Unknown Customer') ?>
                                        </div>
                                    </div>
                                    <div>
                                        <div class="order-status <?= $order['status'] ?>">
                                            <?= ucfirst($order['status']) ?>
                                        </div>
                                        <div style="font-size: 0.875rem; color: #7f8c8d; margin-top: 0.25rem;">
                                            $<?= number_format($order['total_price'], 2) ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p class="text-muted">No recent orders</p>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Low Stock Products -->
                <div class="dashboard-section">
                    <div class="section-header">
                        <h2 class="section-title">Low Stock Alert</h2>
                        <a href="products/index.php?filter=low_stock" class="section-link">View All</a>
                    </div>
                    
                    <div class="product-list">
                        <?php if (!empty($low_stock_products)): ?>
                            <?php foreach ($low_stock_products as $product): ?>
                                <div class="product-item">
                                    <div class="product-info">
                                        <div class="product-name"><?= htmlspecialchars($product['product_name']) ?></div>
                                        <div class="product-category"><?= htmlspecialchars($product['category']) ?></div>
                                    </div>
                                    <div class="stock-badge <?= $product['stock'] <= 2 ? 'critical' : 'low' ?>">
                                        <?= $product['stock'] ?> left
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p class="text-muted">All products are well stocked! 🎉</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- Quick Actions -->
            <div class="quick-actions">
                <a href="products/add.php" class="quick-action">
                    <div class="quick-action-icon">➕</div>
                    <div>Add Product</div>
                </a>
                
                <a href="orders/index.php?status=pending" class="quick-action">
                    <div class="quick-action-icon">📋</div>
                    <div>Process Orders</div>
                </a>
                
                <a href="users/index.php" class="quick-action">
                    <div class="quick-action-icon">👤</div>
                    <div>Manage Users</div>
                </a>
                
                <a href="reports/index.php" class="quick-action">
                    <div class="quick-action-icon">📊</div>
                    <div>View Reports</div>
                </a>
            </div>
        </main>
    </div>
    
    <?php include '../../includes/footer.php'; ?>
    
    <script src="../../assets/js/main.js"></script>
    <script>
        // Auto-refresh dashboard every 5 minutes
        setTimeout(() => {
            window.location.reload();
        }, 300000);
        
        // Add active state to current nav item
        document.addEventListener('DOMContentLoaded', function() {
            const currentPath = window.location.pathname;
            const navLinks = document.querySelectorAll('.admin-nav-link');
            
            navLinks.forEach(link => {
                if (link.getAttribute('href') === currentPath.split('/').slice(-1)[0]) {
                    link.classList.add('active');
                } else {
                    link.classList.remove('active');
                }
            });
        });
    </script>
</body>
</html>
