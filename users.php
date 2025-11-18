<?php
include '../includes/config.php';
include 'admin-header.php';

// Check if user is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: ../index.php");
    exit;
}

// First, let's check if the status column exists
$check_column_sql = "SHOW COLUMNS FROM users LIKE 'status'";
$column_result = $conn->query($check_column_sql);
$status_column_exists = ($column_result->num_rows > 0);

// If status column doesn't exist, create it
if (!$status_column_exists) {
    $alter_sql = "ALTER TABLE users ADD COLUMN status ENUM('active', 'inactive') DEFAULT 'active'";
    if ($conn->query($alter_sql)) {
        // Update all existing users to active status
        $update_sql = "UPDATE users SET status = 'active' WHERE status IS NULL OR status = ''";
        $conn->query($update_sql);
    }
}

// Handle user actions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['update_role'])) {
        $user_id = intval($_POST['user_id']);
        $role = $_POST['role'];
        
        // Prevent admin from changing their own role
        if ($user_id != $_SESSION['user_id']) {
            $sql = "UPDATE users SET role = ? WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("si", $role, $user_id);
            
            if ($stmt->execute()) {
                $_SESSION['success'] = "User role updated successfully!";
            } else {
                $_SESSION['error'] = "Failed to update user role.";
            }
        } else {
            $_SESSION['error'] = "You cannot change your own role!";
        }
        
    } elseif (isset($_POST['delete_user'])) {
        $user_id = intval($_POST['user_id']);
        
        // Prevent admin from deleting themselves
        if ($user_id == $_SESSION['user_id']) {
            $_SESSION['error'] = "You cannot delete your own account!";
        } else {
            // SOFT DELETE - Mark user as inactive instead of permanent deletion
            if ($status_column_exists) {
                $sql = "UPDATE users SET status = 'inactive' WHERE id = ?";
            } else {
                // Fallback: use a different approach if status column doesn't exist
                $_SESSION['error'] = "System error: Cannot deactivate user at this time.";
                header("Location: users.php");
                exit;
            }
            
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $user_id);
            
            if ($stmt->execute()) {
                $_SESSION['success'] = "User deactivated successfully!";
                header("Location: users.php");
                exit;
            } else {
                $_SESSION['error'] = "Failed to deactivate user: " . $stmt->error;
            }
        }
    } elseif (isset($_POST['activate_user'])) {
        $user_id = intval($_POST['user_id']);
        
        // Activate user
        $sql = "UPDATE users SET status = 'active' WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $user_id);
        
        if ($stmt->execute()) {
            $_SESSION['success'] = "User activated successfully!";
            header("Location: users.php");
            exit;
        } else {
            $_SESSION['error'] = "Failed to activate user: " . $stmt->error;
        }
    }
}

// Fetch users with order counts - Handle both cases (with and without status column)
$users = [];
if ($status_column_exists) {
    $sql = "SELECT u.*, COUNT(o.id) as order_count 
            FROM users u 
            LEFT JOIN orders o ON u.id = o.user_id 
            WHERE u.status = 'active' OR u.status IS NULL
            GROUP BY u.id 
            ORDER BY u.created_at DESC";
} else {
    // If status column doesn't exist yet, show all users
    $sql = "SELECT u.*, COUNT(o.id) as order_count 
            FROM users u 
            LEFT JOIN orders o ON u.id = o.user_id 
            GROUP BY u.id 
            ORDER BY u.created_at DESC";
}

$result = $conn->query($sql);

if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $users[] = $row;
    }
}

// Get user statistics
$stats = [
    'total' => count($users),
    'admins' => array_filter($users, function($user) { return $user['role'] == 'admin'; }),
    'customers' => array_filter($users, function($user) { return $user['role'] == 'customer'; }),
    'active_today' => 0
];
?>

<div class="admin-container">
    <!-- Enhanced Sidebar -->
    <div class="admin-sidebar">
        <div class="sidebar-header">
            <h3>🍎 Quick Basket</h3>
            <p>Admin Panel</p>
        </div>
        <nav class="sidebar-nav">
            <ul>
                <li class="nav-item">
                    <a href="index.php" class="nav-link">
                        <span class="nav-icon">📊</span>
                        <span class="nav-text">Dashboard</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="products.php" class="nav-link">
                        <span class="nav-icon">📦</span>
                        <span class="nav-text">Products</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="orders.php" class="nav-link">
                        <span class="nav-icon">🛒</span>
                        <span class="nav-text">Orders</span>
                    </a>
                </li>
                <li class="nav-item active">
                    <a href="users.php" class="nav-link">
                        <span class="nav-icon">👥</span>
                        <span class="nav-text">Users</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="../index.php" class="nav-link">
                        <span class="nav-icon">🏠</span>
                        <span class="nav-text">Back to Site</span>
                    </a>
                </li>
            </ul>
        </nav>
    </div>
    
    <div class="admin-main">
        <div class="admin-header">
            <h1>User Management</h1>
            <div class="admin-user">
                <span>Welcome, <?php echo $_SESSION['user_name']; ?></span>
                <div class="user-dropdown">
                    <a href="../profile.php">Profile</a>
                    <a href="../logout.php">Logout</a>
                </div>
            </div>
        </div>

        <div class="admin-content">
            <!-- Notifications -->
            <?php if (isset($_SESSION['success'])): ?>
                <div class="alert alert-success">
                    <div class="alert-content">
                        <span class="alert-icon">✅</span>
                        <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
                    </div>
                </div>
            <?php endif; ?>

            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert alert-error">
                    <div class="alert-content">
                        <span class="alert-icon">❌</span>
                        <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
                    </div>
                </div>
            <?php endif; ?>

            <!-- System Notice -->
            <?php if (!$status_column_exists): ?>
                <div class="alert alert-warning">
                    <div class="alert-content">
                        <span class="alert-icon">⚠️</span>
                        System is setting up user management features. Some features may be limited temporarily.
                    </div>
                </div>
            <?php endif; ?>

            <!-- User Statistics -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon total">👥</div>
                    <div class="stat-info">
                        <h3><?php echo $stats['total']; ?></h3>
                        <p>Total Users</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon admins">👑</div>
                    <div class="stat-info">
                        <h3><?php echo count($stats['admins']); ?></h3>
                        <p>Administrators</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon customers">🛒</div>
                    <div class="stat-info">
                        <h3><?php echo count($stats['customers']); ?></h3>
                        <p>Customers</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon active">⚡</div>
                    <div class="stat-info">
                        <h3><?php echo $stats['active_today']; ?></h3>
                        <p>Active Today</p>
                    </div>
                </div>
            </div>

            <!-- Users Table -->
            <div class="card">
                <div class="card-header">
                    <h3><?php echo $status_column_exists ? 'Active Users' : 'All Users'; ?> (<?php echo count($users); ?>)</h3>
                    <div class="card-actions">
                        <div class="search-box">
                            <input type="text" id="searchUsers" placeholder="Search users...">
                            <span class="search-icon">🔍</span>
                        </div>
                        <?php if ($status_column_exists): ?>
                            <a href="inactive-users.php" class="btn btn-secondary">
                                <span class="btn-icon">📋</span>
                                View Inactive Users
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="card-body">
                    <?php if (empty($users)): ?>
                        <div class="empty-state">
                            <div class="empty-icon">👥</div>
                            <h3>No Users Found</h3>
                            <p>There are no users in the system yet.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-container">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>User ID</th>
                                        <th>User Info</th>
                                        <th>Role</th>
                                        <th>Orders</th>
                                        <th>Registered</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach($users as $user): ?>
                                    <tr class="user-row" data-user-id="<?php echo $user['id']; ?>">
                                        <td>
                                            <div class="user-id">#<?php echo $user['id']; ?></div>
                                        </td>
                                        <td>
                                            <div class="user-info">
                                                <div class="user-avatar">
                                                    <?php echo strtoupper(substr($user['name'], 0, 1)); ?>
                                                </div>
                                                <div class="user-details">
                                                    <strong><?php echo htmlspecialchars($user['name']); ?></strong>
                                                    <span class="user-email"><?php echo htmlspecialchars($user['email']); ?></span>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <form method="POST" action="" class="role-form">
                                                <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                <select name="role" class="role-select role-<?php echo $user['role']; ?>" 
                                                    <?php echo $user['id'] == $_SESSION['user_id'] ? 'disabled' : 'onchange="this.form.submit()"'; ?>>
                                                    <option value="customer" <?php echo $user['role'] == 'customer' ? 'selected' : ''; ?>>Customer</option>
                                                    <option value="admin" <?php echo $user['role'] == 'admin' ? 'selected' : ''; ?>>Admin</option>
                                                </select>
                                                <input type="hidden" name="update_role" value="1">
                                                <?php if ($user['id'] == $_SESSION['user_id']): ?>
                                                    <div class="current-user-tooltip">Current User</div>
                                                <?php endif; ?>
                                            </form>
                                        </td>
                                        <td>
                                            <div class="order-count">
                                                <span class="count-badge"><?php echo $user['order_count']; ?></span>
                                                <span class="count-label">order(s)</span>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="registration-date">
                                                <div class="date"><?php echo date('M j, Y', strtotime($user['created_at'])); ?></div>
                                                <div class="time-ago"><?php echo time_elapsed_string($user['created_at']); ?></div>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="action-buttons">
                                                <a href="user-details.php?id=<?php echo $user['id']; ?>" class="btn btn-primary btn-sm" title="View Details">
                                                    <span class="btn-icon">👁️</span>
                                                    View
                                                </a>
                                                <?php if ($user['id'] != $_SESSION['user_id']): ?>
                                                    <?php if ($status_column_exists): ?>
                                                        <form method="POST" action="" style="display: inline;">
                                                            <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                            <button type="submit" name="delete_user" 
                                                                    class="btn btn-warning btn-sm"
                                                                    onclick="return confirmDeactivate(<?php echo $user['id']; ?>, <?php echo $user['order_count']; ?>)"
                                                                    title="Deactivate User">
                                                                <span class="btn-icon">🚫</span>
                                                                Deactivate
                                                            </button>
                                                        </form>
                                                    <?php else: ?>
                                                        <!-- Show disabled button if status column doesn't exist -->
                                                        <button class="btn btn-warning btn-sm" disabled title="Feature not available">
                                                            <span class="btn-icon">🚫</span>
                                                            Deactivate
                                                        </button>
                                                    <?php endif; ?>
                                                <?php else: ?>
                                                    <span class="current-user-label">You</span>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
/* Your existing CSS styles remain the same */
/* Admin Panel Base Styles */
.admin-container {
    display: flex;
    min-height: 100vh;
    background: #f8f9fa;
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
}

/* Sidebar Styles */
.admin-sidebar {
    width: 280px;
    background: linear-gradient(135deg, #2c3e50 0%, #34495e 100%);
    color: white;
    position: fixed;
    height: 100vh;
    overflow-y: auto;
    box-shadow: 2px 0 10px rgba(0,0,0,0.1);
}

.sidebar-header {
    padding: 2rem 1.5rem 1.5rem;
    border-bottom: 1px solid #34495e;
    background: rgba(0,0,0,0.1);
}

.sidebar-header h3 {
    margin: 0 0 0.5rem 0;
    font-size: 1.5rem;
    color: #4CAF50;
    font-weight: 700;
}

.sidebar-header p {
    margin: 0;
    color: #bdc3c7;
    font-size: 0.9rem;
}

.sidebar-nav {
    padding: 1rem 0;
}

.nav-item {
    margin-bottom: 0.25rem;
}

.nav-link {
    display: flex;
    align-items: center;
    padding: 1rem 1.5rem;
    color: #bdc3c7;
    text-decoration: none;
    transition: all 0.3s ease;
    position: relative;
    border-left: 3px solid transparent;
}

.nav-link:hover {
    background: rgba(255,255,255,0.05);
    color: white;
    border-left-color: #4CAF50;
}

.nav-item.active .nav-link {
    background: rgba(76, 175, 80, 0.1);
    color: white;
    border-left-color: #4CAF50;
}

.nav-icon {
    font-size: 1.2rem;
    margin-right: 1rem;
    width: 20px;
    text-align: center;
}

.nav-text {
    flex: 1;
    font-weight: 500;
}

/* Main Content Styles */
.admin-main {
    flex: 1;
    margin-left: 280px;
    min-height: 100vh;
}

.admin-header {
    background: white;
    padding: 1.5rem 2rem;
    border-bottom: 1px solid #e9ecef;
    display: flex;
    justify-content: space-between;
    align-items: center;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.admin-header h1 {
    margin: 0;
    color: #2c3e50;
    font-size: 1.8rem;
    font-weight: 700;
}

.admin-user {
    position: relative;
    cursor: pointer;
    padding: 0.5rem 1rem;
    border-radius: 8px;
    transition: background 0.3s;
    background: #f8f9fa;
    border: 1px solid #e9ecef;
}

.admin-user:hover {
    background: #e9ecef;
}

.admin-user:hover .user-dropdown {
    display: block;
}

.user-dropdown {
    display: none;
    position: absolute;
    top: 100%;
    right: 0;
    background: white;
    min-width: 150px;
    box-shadow: 0 4px 15px rgba(0,0,0,0.1);
    border-radius: 8px;
    overflow: hidden;
    z-index: 1000;
    border: 1px solid #e9ecef;
}

.user-dropdown a {
    display: block;
    padding: 0.75rem 1rem;
    color: #333;
    text-decoration: none;
    border-bottom: 1px solid #eee;
    transition: background 0.3s;
}

.user-dropdown a:hover {
    background: #4CAF50;
    color: white;
}

/* Admin Content */
.admin-content {
    padding: 2rem;
}

/* Alert Styles */
.alert {
    padding: 1rem;
    border-radius: 8px;
    margin-bottom: 1.5rem;
    border: 1px solid transparent;
}

.alert-success {
    background: #d4edda;
    border-color: #c3e6cb;
    color: #155724;
}

.alert-error {
    background: #f8d7da;
    border-color: #f5c6cb;
    color: #721c24;
}

.alert-warning {
    background: #fff3cd;
    border-color: #ffeaa7;
    color: #856404;
}

.alert-content {
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.alert-icon {
    font-size: 1.2rem;
}

/* Statistics Grid */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 1.5rem;
    margin-bottom: 2rem;
}

.stat-card {
    background: white;
    padding: 1.5rem;
    border-radius: 12px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.08);
    display: flex;
    align-items: center;
    transition: all 0.3s ease;
    border: 1px solid #e9ecef;
}

.stat-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 5px 20px rgba(0,0,0,0.15);
}

.stat-icon {
    font-size: 2.5rem;
    margin-right: 1rem;
    width: 70px;
    height: 70px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 12px;
}

.stat-icon.total { 
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
}
.stat-icon.admins { 
    background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
    color: white;
}
.stat-icon.customers { 
    background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
    color: white;
}
.stat-icon.active { 
    background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);
    color: white;
}

.stat-info h3 {
    margin: 0 0 0.5rem 0;
    font-size: 2rem;
    color: #2c3e50;
    font-weight: 700;
}

.stat-info p {
    margin: 0;
    color: #6c757d;
    font-weight: 500;
    font-size: 0.9rem;
}

/* Card Styles */
.card {
    background: white;
    border-radius: 12px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.08);
    border: 1px solid #e9ecef;
    overflow: hidden;
}

.card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 1.5rem;
    border-bottom: 1px solid #e9ecef;
    background: #f8f9fa;
}

.card-header h3 {
    margin: 0;
    color: #2c3e50;
    font-weight: 600;
}

.card-actions {
    display: flex;
    align-items: center;
    gap: 1rem;
}

.search-box {
    position: relative;
    display: flex;
    align-items: center;
}

.search-box input {
    padding: 0.75rem 1rem 0.75rem 2.5rem;
    border: 1px solid #ddd;
    border-radius: 8px;
    font-size: 0.9rem;
    width: 250px;
    transition: all 0.3s;
}

.search-box input:focus {
    outline: none;
    border-color: #4CAF50;
    box-shadow: 0 0 0 2px rgba(76, 175, 80, 0.1);
}

.search-icon {
    position: absolute;
    left: 0.75rem;
    color: #6c757d;
}

.card-body {
    padding: 1.5rem;
}

/* Table Styles */
.table-container {
    overflow-x: auto;
}

.data-table {
    width: 100%;
    border-collapse: collapse;
}

.data-table th {
    background: #f8f9fa;
    padding: 1rem;
    text-align: left;
    font-weight: 600;
    color: #2c3e50;
    border-bottom: 2px solid #e9ecef;
    font-size: 0.9rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.data-table td {
    padding: 1rem;
    border-bottom: 1px solid #e9ecef;
    vertical-align: middle;
}

.data-table tr:hover {
    background: #f8f9fa;
}

/* User Info Styles */
.user-info {
    display: flex;
    align-items: center;
    gap: 1rem;
}

.user-avatar {
    width: 50px;
    height: 50px;
    background: linear-gradient(135deg, #4CAF50, #45a049);
    color: white;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: bold;
    font-size: 1.2rem;
    box-shadow: 0 2px 8px rgba(76, 175, 80, 0.3);
}

.user-details {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
}

.user-details strong {
    color: #2c3e50;
    font-weight: 600;
    font-size: 0.95rem;
}

.user-email {
    font-size: 0.85rem;
    color: #6c757d;
}

/* User ID */
.user-id {
    font-weight: 700;
    color: #2c3e50;
    font-family: 'Courier New', monospace;
    background: #f8f9fa;
    padding: 0.4rem 0.75rem;
    border-radius: 6px;
    display: inline-block;
    border: 1px solid #e9ecef;
    font-size: 0.85rem;
}

/* Role Form Styles */
.role-form {
    margin: 0;
    position: relative;
}

.role-select {
    padding: 0.6rem 0.75rem;
    border: 1px solid #ddd;
    border-radius: 8px;
    font-size: 0.85rem;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.3s;
    min-width: 120px;
    background: white;
}

.role-select:focus {
    outline: none;
    border-color: #4CAF50;
    box-shadow: 0 0 0 2px rgba(76, 175, 80, 0.1);
}

.role-select:disabled {
    background: #f8f9fa;
    cursor: not-allowed;
    opacity: 0.7;
}

.role-select.role-admin { 
    border-left: 4px solid #4CAF50;
    background: rgba(76, 175, 80, 0.05);
}
.role-select.role-customer { 
    border-left: 4px solid #3498db;
    background: rgba(52, 152, 219, 0.05);
}

.current-user-tooltip {
    font-size: 0.75rem;
    color: #6c757d;
    margin-top: 0.25rem;
    font-style: italic;
}

/* Order Count */
.order-count {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.count-badge {
    background: linear-gradient(135deg, #4CAF50, #45a049);
    color: white;
    border-radius: 20px;
    padding: 0.3rem 0.75rem;
    font-size: 0.8rem;
    font-weight: 600;
    min-width: 25px;
    text-align: center;
    box-shadow: 0 2px 4px rgba(76, 175, 80, 0.2);
}

.count-label {
    font-size: 0.85rem;
    color: #6c757d;
    font-weight: 500;
}

/* Registration Date */
.registration-date {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
}

.date {
    font-weight: 600;
    color: #2c3e50;
    font-size: 0.9rem;
}

.time-ago {
    font-size: 0.8rem;
    color: #6c757d;
    font-style: italic;
}

/* Action Buttons */
.action-buttons {
    display: flex;
    gap: 0.5rem;
    align-items: center;
}

.btn {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.6rem 1rem;
    border: none;
    border-radius: 6px;
    font-size: 0.85rem;
    font-weight: 500;
    text-decoration: none;
    cursor: pointer;
    transition: all 0.3s ease;
    font-family: inherit;
}

.btn-sm {
    padding: 0.5rem 0.75rem;
    font-size: 0.8rem;
}

.btn-primary {
    background: linear-gradient(135deg, #3498db, #2980b9);
    color: white;
}

.btn-primary:hover {
    background: linear-gradient(135deg, #2980b9, #1f618d);
    transform: translateY(-1px);
    box-shadow: 0 2px 8px rgba(52, 152, 219, 0.3);
}

.btn-warning {
    background: linear-gradient(135deg, #f39c12, #e67e22);
    color: white;
}

.btn-warning:hover {
    background: linear-gradient(135deg, #e67e22, #d35400);
    transform: translateY(-1px);
    box-shadow: 0 2px 8px rgba(243, 156, 18, 0.3);
}

.btn-secondary {
    background: linear-gradient(135deg, #95a5a6, #7f8c8d);
    color: white;
}

.btn-secondary:hover {
    background: linear-gradient(135deg, #7f8c8d, #6c7b7d);
    transform: translateY(-1px);
    box-shadow: 0 2px 8px rgba(149, 165, 166, 0.3);
}

.btn-danger {
    background: linear-gradient(135deg, #e74c3c, #c0392b);
    color: white;
}

.btn-danger:hover {
    background: linear-gradient(135deg, #c0392b, #a93226);
    transform: translateY(-1px);
    box-shadow: 0 2px 8px rgba(231, 76, 60, 0.3);
}

.btn:disabled {
    background: #6c757d;
    cursor: not-allowed;
    opacity: 0.6;
    transform: none;
    box-shadow: none;
}

.btn-icon {
    font-size: 0.9rem;
}

.current-user-label {
    background: #f8f9fa;
    color: #6c757d;
    padding: 0.5rem 0.75rem;
    border-radius: 6px;
    font-size: 0.8rem;
    font-weight: 500;
    border: 1px solid #e9ecef;
}

/* Empty State */
.empty-state {
    text-align: center;
    padding: 3rem 2rem;
    color: #6c757d;
}

.empty-icon {
    font-size: 4rem;
    margin-bottom: 1rem;
    opacity: 0.5;
}

.empty-state h3 {
    margin: 0 0 0.5rem 0;
    color: #2c3e50;
    font-weight: 600;
}

.empty-state p {
    margin: 0;
    font-size: 0.95rem;
}

/* Responsive Design */
@media (max-width: 1024px) {
    .admin-sidebar {
        width: 250px;
    }
    .admin-main {
        margin-left: 250px;
    }
}

@media (max-width: 768px) {
    .admin-container {
        flex-direction: column;
    }
    
    .admin-sidebar {
        width: 100%;
        height: auto;
        position: relative;
    }
    
    .admin-main {
        margin-left: 0;
    }
    
    .stats-grid {
        grid-template-columns: 1fr 1fr;
    }
    
    .admin-header {
        flex-direction: column;
        gap: 1rem;
        align-items: flex-start;
    }
    
    .search-box input {
        width: 200px;
    }
}

@media (max-width: 480px) {
    .admin-content {
        padding: 1rem;
    }
    
    .stats-grid {
        grid-template-columns: 1fr;
    }
    
    .card-header {
        flex-direction: column;
        gap: 1rem;
        align-items: flex-start;
    }
    
    .search-box input {
        width: 100%;
    }
    
    .action-buttons {
        flex-direction: column;
        align-items: flex-start;
    }
    
    .user-info {
        flex-direction: column;
        align-items: flex-start;
        gap: 0.5rem;
    }
}
</style>

<script>
function confirmDeactivate(userId, orderCount) {
    return confirm(`Are you sure you want to deactivate user #${userId}?\n\nThis user has ${orderCount} order(s) and will be moved to inactive users.\n\nThey can be reactivated later if needed.`);
}

// Add search functionality
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('searchUsers');
    if (searchInput) {
        searchInput.addEventListener('input', function() {
            const searchTerm = this.value.toLowerCase();
            const rows = document.querySelectorAll('.user-row');
            
            rows.forEach(row => {
                const userName = row.querySelector('.user-details strong').textContent.toLowerCase();
                const userEmail = row.querySelector('.user-email').textContent.toLowerCase();
                const userId = row.querySelector('.user-id').textContent.toLowerCase();
                
                if (userName.includes(searchTerm) || userEmail.includes(searchTerm) || userId.includes(searchTerm)) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        });
    }
});
</script>

<?php 
// PHP function for time elapsed
function time_elapsed_string($datetime, $full = false) {
    $now = new DateTime;
    $ago = new DateTime($datetime);
    $diff = $now->diff($ago);

    $diff->w = floor($diff->d / 7);
    $diff->d -= $diff->w * 7;

    $string = array(
        'y' => 'year',
        'm' => 'month',
        'w' => 'week',
        'd' => 'day',
        'h' => 'hour',
        'i' => 'minute',
        's' => 'second',
    );
    
    foreach ($string as $k => &$v) {
        if ($diff->$k) {
            $v = $diff->$k . ' ' . $v . ($diff->$k > 1 ? 's' : '');
        } else {
            unset($string[$k]);
        }
    }

    if (!$full) $string = array_slice($string, 0, 1);
    return $string ? implode(', ', $string) . ' ago' : 'just now';
}

include 'admin-footer.php'; 
?>