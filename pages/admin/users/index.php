<?php
/**
 * Admin User Management
 * Features 800-805: Manage user accounts
 */

require_once '../../../includes/init.php';

// Require admin access only
requireLogin();
requireRole(['admin']);

$user = getCurrentUser();

// Handle user actions
$action = $_GET['action'] ?? '';
$user_id = $_GET['id'] ?? 0;
$message = '';
$error = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    try {
        switch ($action) {
            case 'create_user':
                $name = trim($_POST['name']);
                $email = trim($_POST['email']);
                $password = $_POST['password'];
                $role = $_POST['role'];
                
                // Validation
                if (empty($name) || empty($email) || empty($password)) {
                    throw new Exception("All fields are required");
                }
                
                if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    throw new Exception("Invalid email format");
                }
                
                if (strlen($password) < 6) {
                    throw new Exception("Password must be at least 6 characters");
                }
                
                // Check if email exists
                $existing = $db->fetch("SELECT user_id FROM users WHERE email = ?", [$email]);
                if ($existing) {
                    throw new Exception("Email already exists");
                }
                
                // Create user
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $db->query(
                    "INSERT INTO users (name, email, password, role, is_active, email_verified) VALUES (?, ?, ?, ?, 1, 1)",
                    [$name, $email, $hashed_password, $role]
                );
                
                $message = "User created successfully";
                break;
                
            case 'update_user':
                $update_user_id = $_POST['user_id'];
                $name = trim($_POST['name']);
                $email = trim($_POST['email']);
                $role = $_POST['role'];
                $is_active = isset($_POST['is_active']) ? 1 : 0;
                
                // Validation
                if (empty($name) || empty($email)) {
                    throw new Exception("Name and email are required");
                }
                
                if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    throw new Exception("Invalid email format");
                }
                
                // Check if email exists for other users
                $existing = $db->fetch("SELECT user_id FROM users WHERE email = ? AND user_id != ?", [$email, $update_user_id]);
                if ($existing) {
                    throw new Exception("Email already exists for another user");
                }
                
                // Update user
                $db->query(
                    "UPDATE users SET name = ?, email = ?, role = ?, is_active = ? WHERE user_id = ?",
                    [$name, $email, $role, $is_active, $update_user_id]
                );
                
                // Update password if provided
                if (!empty($_POST['password'])) {
                    $password = $_POST['password'];
                    if (strlen($password) < 6) {
                        throw new Exception("Password must be at least 6 characters");
                    }
                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                    $db->query(
                        "UPDATE users SET password = ? WHERE user_id = ?",
                        [$hashed_password, $update_user_id]
                    );
                }
                
                $message = "User updated successfully";
                break;
                
            case 'delete_user':
                $delete_user_id = $_POST['user_id'];
                
                // Can't delete self
                if ($delete_user_id == $user['user_id']) {
                    throw new Exception("Cannot delete your own account");
                }
                
                // Delete user
                $db->query("DELETE FROM users WHERE user_id = ?", [$delete_user_id]);
                
                $message = "User deleted successfully";
                break;
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Handle single user actions
if ($action && $user_id) {
    try {
        switch ($action) {
            case 'toggle_status':
                $current_user = $db->fetch("SELECT is_active FROM users WHERE user_id = ?", [$user_id]);
                if ($current_user) {
                    $new_status = $current_user['is_active'] ? 0 : 1;
                    $db->query("UPDATE users SET is_active = ? WHERE user_id = ?", [$new_status, $user_id]);
                    $message = "User status updated successfully";
                }
                break;
                
            case 'reset_password':
                // Generate temporary password
                $temp_password = bin2hex(random_bytes(8));
                $hashed_password = password_hash($temp_password, PASSWORD_DEFAULT);
                $db->query("UPDATE users SET password = ? WHERE user_id = ?", [$hashed_password, $user_id]);
                $message = "Password reset. Temporary password: " . $temp_password;
                break;
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Get users list with filters
$search = $_GET['search'] ?? '';
$role_filter = $_GET['role'] ?? '';
$status_filter = $_GET['status'] ?? '';
$page = (int)($_GET['page'] ?? 1);
$per_page = 20;
$offset = ($page - 1) * $per_page;

$where_conditions = [];
$params = [];

if (!empty($search)) {
    $where_conditions[] = "(name LIKE ? OR email LIKE ?)";
    $search_term = "%{$search}%";
    $params = array_merge($params, [$search_term, $search_term]);
}

if (!empty($role_filter)) {
    $where_conditions[] = "role = ?";
    $params[] = $role_filter;
}

if ($status_filter !== '') {
    $where_conditions[] = "is_active = ?";
    $params[] = (int)$status_filter;
}

$where_clause = $where_conditions ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Get total count
$total_users = $db->fetch("SELECT COUNT(*) as count FROM users {$where_clause}", $params)['count'];

// Get users
$users = $db->fetchAll(
    "SELECT u.*, up.phone, up.suburb, up.state,
            (SELECT COUNT(*) FROM orders WHERE user_id = u.user_id) as order_count,
            (SELECT COALESCE(SUM(total_price), 0) FROM orders WHERE user_id = u.user_id AND status = 'confirmed') as total_spent
     FROM users u 
     LEFT JOIN user_profiles up ON u.user_id = up.user_id 
     {$where_clause}
     ORDER BY u.created_at DESC 
     LIMIT {$per_page} OFFSET {$offset}",
    $params
);

$total_pages = ceil($total_users / $per_page);
$page_title = "User Management - Again&Co";
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
        
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
        }
        
        .page-title {
            margin: 0;
            color: #2c3e50;
        }
        
        .filters-section {
            background: white;
            padding: 1.5rem;
            border-radius: 10px;
            margin-bottom: 2rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .filters-grid {
            display: grid;
            grid-template-columns: 2fr 1fr 1fr auto;
            gap: 1rem;
            align-items: end;
        }
        
        .form-group {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }
        
        .form-group label {
            font-weight: 600;
            color: #2c3e50;
        }
        
        .form-control {
            padding: 0.75rem;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 0.875rem;
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
        
        .btn-success {
            background: #27ae60;
            color: white;
        }
        
        .btn-success:hover {
            background: #229954;
        }
        
        .btn-warning {
            background: #f39c12;
            color: white;
        }
        
        .btn-warning:hover {
            background: #e67e22;
        }
        
        .btn-danger {
            background: #e74c3c;
            color: white;
        }
        
        .btn-danger:hover {
            background: #c0392b;
        }
        
        .btn-sm {
            padding: 0.5rem 1rem;
            font-size: 0.75rem;
        }
        
        .alert {
            padding: 1rem;
            border-radius: 5px;
            margin-bottom: 1.5rem;
        }
        
        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .alert-danger {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .users-table {
            background: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .table th,
        .table td {
            padding: 1rem;
            text-align: left;
            border-bottom: 1px solid #ecf0f1;
        }
        
        .table th {
            background: #f8f9fa;
            font-weight: 600;
            color: #2c3e50;
        }
        
        .table tbody tr:hover {
            background: #f8f9fa;
        }
        
        .user-info {
            display: flex;
            flex-direction: column;
            gap: 0.25rem;
        }
        
        .user-name {
            font-weight: 600;
            color: #2c3e50;
        }
        
        .user-email {
            color: #7f8c8d;
            font-size: 0.875rem;
        }
        
        .role-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 15px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .role-badge.admin {
            background: #e8f5e8;
            color: #27ae60;
        }
        
        .role-badge.staff {
            background: #e8f4fd;
            color: #3498db;
        }
        
        .role-badge.customer {
            background: #fef9e7;
            color: #f39c12;
        }
        
        .status-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 15px;
            font-size: 0.75rem;
            font-weight: 600;
        }
        
        .status-badge.active {
            background: #d4edda;
            color: #155724;
        }
        
        .status-badge.inactive {
            background: #f8d7da;
            color: #721c24;
        }
        
        .actions {
            display: flex;
            gap: 0.5rem;
        }
        
        .pagination {
            display: flex;
            justify-content: center;
            gap: 0.5rem;
            margin-top: 2rem;
        }
        
        .pagination a,
        .pagination span {
            padding: 0.5rem 1rem;
            border: 1px solid #ddd;
            color: #3498db;
            text-decoration: none;
            border-radius: 5px;
        }
        
        .pagination a:hover {
            background: #3498db;
            color: white;
        }
        
        .pagination .current {
            background: #3498db;
            color: white;
        }
        
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
        }
        
        .modal-content {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: white;
            padding: 2rem;
            border-radius: 10px;
            width: 90%;
            max-width: 500px;
        }
        
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
        }
        
        .modal-title {
            margin: 0;
            color: #2c3e50;
        }
        
        .close {
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            color: #7f8c8d;
        }
        
        .close:hover {
            color: #2c3e50;
        }
        
        .form-grid {
            display: grid;
            gap: 1rem;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
        }
        
        @media (max-width: 768px) {
            .admin-layout {
                grid-template-columns: 1fr;
            }
            
            .admin-sidebar {
                display: none;
            }
            
            .filters-grid {
                grid-template-columns: 1fr;
            }
            
            .form-row {
                grid-template-columns: 1fr;
            }
            
            .table {
                font-size: 0.875rem;
            }
            
            .actions {
                flex-direction: column;
            }
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
                        <a href="../orders/index.php" class="admin-nav-link">
                            🛒 Orders
                        </a>
                    </li>
                    <li class="admin-nav-item">
                        <a href="index.php" class="admin-nav-link active">
                            👥 Users
                        </a>
                    </li>
                </ul>
            </nav>
        </aside>
        
        <!-- Main Content -->
        <main class="admin-content">
            <div class="page-header">
                <h1 class="page-title">User Management</h1>
                <button onclick="openCreateModal()" class="btn btn-success">
                    ➕ Add User
                </button>
            </div>
            
            <?php if ($message): ?>
                <div class="alert alert-success"><?= htmlspecialchars($message) ?></div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
            
            <!-- Filters -->
            <div class="filters-section">
                <form method="GET" class="filters-grid">
                    <div class="form-group">
                        <label for="search">Search Users</label>
                        <input type="text" id="search" name="search" value="<?= htmlspecialchars($search) ?>" 
                               placeholder="Search by name or email..." class="form-control">
                    </div>
                    
                    <div class="form-group">
                        <label for="role">Role</label>
                        <select id="role" name="role" class="form-control">
                            <option value="">All Roles</option>
                            <option value="customer" <?= $role_filter === 'customer' ? 'selected' : '' ?>>Customer</option>
                            <option value="staff" <?= $role_filter === 'staff' ? 'selected' : '' ?>>Staff</option>
                            <option value="admin" <?= $role_filter === 'admin' ? 'selected' : '' ?>>Admin</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="status">Status</label>
                        <select id="status" name="status" class="form-control">
                            <option value="">All Status</option>
                            <option value="1" <?= $status_filter === '1' ? 'selected' : '' ?>>Active</option>
                            <option value="0" <?= $status_filter === '0' ? 'selected' : '' ?>>Inactive</option>
                        </select>
                    </div>
                    
                    <button type="submit" class="btn btn-primary">🔍 Filter</button>
                </form>
            </div>
            
            <!-- Users Table -->
            <div class="users-table">
                <table class="table">
                    <thead>
                        <tr>
                            <th>User</th>
                            <th>Role</th>
                            <th>Status</th>
                            <th>Orders</th>
                            <th>Total Spent</th>
                            <th>Joined</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $user_row): ?>
                            <tr>
                                <td>
                                    <div class="user-info">
                                        <div class="user-name"><?= htmlspecialchars($user_row['name']) ?></div>
                                        <div class="user-email"><?= htmlspecialchars($user_row['email']) ?></div>
                                        <?php if ($user_row['phone']): ?>
                                            <div class="user-email"><?= htmlspecialchars($user_row['phone']) ?></div>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td>
                                    <span class="role-badge <?= $user_row['role'] ?>">
                                        <?= ucfirst($user_row['role']) ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="status-badge <?= $user_row['is_active'] ? 'active' : 'inactive' ?>">
                                        <?= $user_row['is_active'] ? 'Active' : 'Inactive' ?>
                                    </span>
                                </td>
                                <td><?= $user_row['order_count'] ?></td>
                                <td>$<?= number_format($user_row['total_spent'], 2) ?></td>
                                <td><?= date('M j, Y', strtotime($user_row['created_at'])) ?></td>
                                <td>
                                    <div class="actions">
                                        <button onclick="viewUser(<?= $user_row['user_id'] ?>)" class="btn btn-primary btn-sm">
                                            👁️ View
                                        </button>
                                        <button onclick="editUser(<?= $user_row['user_id'] ?>)" class="btn btn-warning btn-sm">
                                            ✏️ Edit
                                        </button>
                                        <?php if ($user_row['user_id'] !== $user['user_id']): ?>
                                            <button onclick="deleteUser(<?= $user_row['user_id'] ?>)" class="btn btn-danger btn-sm">
                                                🗑️ Delete
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        
                        <?php if (empty($users)): ?>
                            <tr>
                                <td colspan="7" style="text-align: center; padding: 2rem; color: #7f8c8d;">
                                    No users found
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
                <div class="pagination">
                    <?php
                    $query_params = $_GET;
                    for ($i = 1; $i <= $total_pages; $i++):
                        $query_params['page'] = $i;
                        $url = '?' . http_build_query($query_params);
                    ?>
                        <?php if ($i == $page): ?>
                            <span class="current"><?= $i ?></span>
                        <?php else: ?>
                            <a href="<?= $url ?>"><?= $i ?></a>
                        <?php endif; ?>
                    <?php endfor; ?>
                </div>
            <?php endif; ?>
        </main>
    </div>
    
    <!-- Create User Modal -->
    <div id="createModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title">Create New User</h2>
                <button class="close" onclick="closeModal('createModal')">&times;</button>
            </div>
            <form method="POST" class="form-grid">
                <input type="hidden" name="action" value="create_user">
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="create_name">Name</label>
                        <input type="text" id="create_name" name="name" required class="form-control">
                    </div>
                    
                    <div class="form-group">
                        <label for="create_email">Email</label>
                        <input type="email" id="create_email" name="email" required class="form-control">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="create_password">Password</label>
                        <input type="password" id="create_password" name="password" required class="form-control" minlength="6">
                    </div>
                    
                    <div class="form-group">
                        <label for="create_role">Role</label>
                        <select id="create_role" name="role" required class="form-control">
                            <option value="customer">Customer</option>
                            <option value="staff">Staff</option>
                            <option value="admin">Admin</option>
                        </select>
                    </div>
                </div>
                
                <div style="display: flex; gap: 1rem; justify-content: flex-end; margin-top: 1.5rem;">
                    <button type="button" onclick="closeModal('createModal')" class="btn btn-secondary">Cancel</button>
                    <button type="submit" class="btn btn-success">Create User</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Edit User Modal -->
    <div id="editModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title">Edit User</h2>
                <button class="close" onclick="closeModal('editModal')">&times;</button>
            </div>
            <form method="POST" class="form-grid">
                <input type="hidden" name="action" value="update_user">
                <input type="hidden" name="user_id" id="edit_user_id">
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="edit_name">Name</label>
                        <input type="text" id="edit_name" name="name" required class="form-control">
                    </div>
                    
                    <div class="form-group">
                        <label for="edit_email">Email</label>
                        <input type="email" id="edit_email" name="email" required class="form-control">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="edit_password">New Password (leave empty to keep current)</label>
                        <input type="password" id="edit_password" name="password" class="form-control" minlength="6">
                    </div>
                    
                    <div class="form-group">
                        <label for="edit_role">Role</label>
                        <select id="edit_role" name="role" required class="form-control">
                            <option value="customer">Customer</option>
                            <option value="staff">Staff</option>
                            <option value="admin">Admin</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-group">
                    <label>
                        <input type="checkbox" id="edit_is_active" name="is_active"> Active
                    </label>
                </div>
                
                <div style="display: flex; gap: 1rem; justify-content: flex-end; margin-top: 1.5rem;">
                    <button type="button" onclick="closeModal('editModal')" class="btn btn-secondary">Cancel</button>
                    <button type="submit" class="btn btn-warning">Update User</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- View User Modal -->
    <div id="viewModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title">User Details</h2>
                <button class="close" onclick="closeModal('viewModal')">&times;</button>
            </div>
            <div id="viewUserContent">
                <!-- Content will be loaded dynamically -->
            </div>
        </div>
    </div>
    
    <script>
        function openCreateModal() {
            document.getElementById('createModal').style.display = 'block';
        }
        
        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }
        
        function editUser(userId) {
            // Fetch user data and populate edit form
            fetch(`get_user.php?id=${userId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const user = data.user;
                        document.getElementById('edit_user_id').value = user.user_id;
                        document.getElementById('edit_name').value = user.name;
                        document.getElementById('edit_email').value = user.email;
                        document.getElementById('edit_role').value = user.role;
                        document.getElementById('edit_is_active').checked = user.is_active == 1;
                        
                        document.getElementById('editModal').style.display = 'block';
                    } else {
                        alert('Error loading user data');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error loading user data');
                });
        }
        
        function viewUser(userId) {
            // Fetch user details and display
            fetch(`get_user.php?id=${userId}&detailed=1`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const user = data.user;
                        const profile = data.profile || {};
                        const orders = data.orders || [];
                        
                        let content = `
                            <div class="user-details">
                                <h3>${user.name}</h3>
                                <p><strong>Email:</strong> ${user.email}</p>
                                <p><strong>Role:</strong> <span class="role-badge ${user.role}">${user.role}</span></p>
                                <p><strong>Status:</strong> <span class="status-badge ${user.is_active ? 'active' : 'inactive'}">${user.is_active ? 'Active' : 'Inactive'}</span></p>
                                <p><strong>Joined:</strong> ${new Date(user.created_at).toLocaleDateString()}</p>
                                <p><strong>Last Login:</strong> ${user.last_login ? new Date(user.last_login).toLocaleDateString() : 'Never'}</p>
                                
                                ${profile.phone ? `<p><strong>Phone:</strong> ${profile.phone}</p>` : ''}
                                ${profile.suburb ? `<p><strong>Address:</strong> ${profile.suburb}, ${profile.state} ${profile.postcode || ''}</p>` : ''}
                                
                                <h4>Order History (${orders.length} orders)</h4>
                                ${orders.length > 0 ? orders.map(order => `
                                    <div style="border: 1px solid #ddd; padding: 1rem; margin: 0.5rem 0; border-radius: 5px;">
                                        <strong>Order #${order.order_id}</strong> - $${parseFloat(order.total_price).toFixed(2)}<br>
                                        <small>${new Date(order.order_date).toLocaleDateString()} - ${order.status}</small>
                                    </div>
                                `).join('') : '<p>No orders yet</p>'}
                            </div>
                        `;
                        
                        document.getElementById('viewUserContent').innerHTML = content;
                        document.getElementById('viewModal').style.display = 'block';
                    } else {
                        alert('Error loading user data');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error loading user data');
                });
        }
        
        function deleteUser(userId) {
            if (confirm('Are you sure you want to delete this user? This action cannot be undone.')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="delete_user">
                    <input type="hidden" name="user_id" value="${userId}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }
        
        // Close modals when clicking outside
        window.onclick = function(event) {
            const modals = document.querySelectorAll('.modal');
            modals.forEach(modal => {
                if (event.target == modal) {
                    modal.style.display = 'none';
                }
            });
        }
    </script>
    
    <?php include '../../../includes/footer.php'; ?>
</body>
</html>