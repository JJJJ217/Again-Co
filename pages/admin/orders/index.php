<?php
/**
 * Order Management
 * Staff and Admin can view and manage orders
 */

require_once '../../../includes/init.php';

// Require admin/staff access
requireLogin();
requireRole(['admin', 'staff']);

$user = getCurrentUser();
$page_title = "Order Management - Again&Co";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $page_title ?></title>
    <link rel="stylesheet" href="../../../assets/css/style.css">
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
        
        .coming-soon {
            text-align: center;
            padding: 4rem 2rem;
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .coming-soon h2 {
            color: #2c3e50;
            margin-bottom: 1rem;
        }
        
        .coming-soon p {
            color: #7f8c8d;
            margin-bottom: 2rem;
        }
        
        .btn {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 5px;
            font-weight: 600;
            text-decoration: none;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .btn-primary {
            background: #3498db;
            color: white;
        }
        
        .btn-primary:hover {
            background: #2980b9;
        }
    </style>
</head>
<body>
    <?php include '../../../includes/header.php'; ?>
    
    <div class="admin-layout">
        <!-- Admin Sidebar -->
        <aside class="admin-sidebar">
            <nav>
                <ul class="admin-nav">
                    <li class="admin-nav-item">
                        <a href="../index.php" class="admin-nav-link">
                            📊 Dashboard
                        </a>
                    </li>
                    <li class="admin-nav-item">
                        <a href="../products/index.php" class="admin-nav-link">
                            📦 Products
                        </a>
                    </li>
                    <li class="admin-nav-item">
                        <a href="index.php" class="admin-nav-link active">
                            🛒 Orders
                        </a>
                    </li>
                    <?php if ($user['role'] === 'admin'): ?>
                        <li class="admin-nav-item">
                            <a href="../users/index.php" class="admin-nav-link">
                                👥 Users
                            </a>
                        </li>
                    <?php endif; ?>
                </ul>
            </nav>
        </aside>
        
        <!-- Main Content -->
        <main class="admin-content">
            <div class="coming-soon">
                <h2>🛒 Order Management</h2>
                <p>Order management functionality is coming soon! This feature will allow you to:</p>
                <ul style="text-align: left; max-width: 500px; margin: 0 auto 2rem;">
                    <li>View all customer orders</li>
                    <li>Update order status</li>
                    <li>Process refunds and cancellations</li>
                    <li>Generate shipping labels</li>
                    <li>View order analytics</li>
                </ul>
                <a href="../index.php" class="btn btn-primary">← Back to Dashboard</a>
            </div>
        </main>
    </div>
    
    <?php include '../../../includes/footer.php'; ?>
</body>
</html>