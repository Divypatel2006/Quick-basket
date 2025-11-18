<?php
include '../includes/config.php';
include 'admin-header.php';

// Check if user is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: ../index.php");
    exit;
}

// Get user ID from URL
$user_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($user_id == 0) {
    header("Location: users.php");
    exit;
}

// Fetch user details
$user_sql = "SELECT * FROM users WHERE id = ?";
$user_stmt = $conn->prepare($user_sql);
$user_stmt->bind_param("i", $user_id);
$user_stmt->execute();
$user_result = $user_stmt->get_result();

if ($user_result->num_rows == 0) {
    $_SESSION['error'] = "User not found!";
    header("Location: users.php");
    exit;
}

$user = $user_result->fetch_assoc();

// Handle user actions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['update_user'])) {
        $name = trim($_POST['name']);
        $email = trim($_POST['email']);
        $role = $_POST['role'];
        
        // Prevent admin from changing their own role
        if ($user_id == $_SESSION['user_id'] && $role != 'admin') {
            $_SESSION['error'] = "You cannot change your own role!";
        } else {
            // Check if email already exists (excluding current user)
            $email_check_sql = "SELECT id FROM users WHERE email = ? AND id != ?";
            $email_check_stmt = $conn->prepare($email_check_sql);
            $email_check_stmt->bind_param("si", $email, $user_id);
            $email_check_stmt->execute();
            $email_check_result = $email_check_stmt->get_result();
            
            if ($email_check_result->num_rows > 0) {
                $_SESSION['error'] = "Email already exists!";
            } else {
                $update_sql = "UPDATE users SET name = ?, email = ?, role = ? WHERE id = ?";
                $update_stmt = $conn->prepare($update_sql);
                $update_stmt->bind_param("sssi", $name, $email, $role, $user_id);
                
                if ($update_stmt->execute()) {
                    $_SESSION['success'] = "User updated successfully!";
                    // Refresh user data
                    $user_result = $user_stmt->get_result();
                    $user = $user_result->fetch_assoc();
                } else {
                    $_SESSION['error'] = "Failed to update user.";
                }
            }
        }
    }
    
    if (isset($_POST['reset_password'])) {
        $new_password = password_hash('password123', PASSWORD_DEFAULT);
        
        $reset_sql = "UPDATE users SET password = ? WHERE id = ?";
        $reset_stmt = $conn->prepare($reset_sql);
        $reset_stmt->bind_param("si", $new_password, $user_id);
        
        if ($reset_stmt->execute()) {
            $_SESSION['success'] = "Password reset successfully! Default password: password123";
        } else {
            $_SESSION['error'] = "Failed to reset password.";
        }
    }
}

// Fetch user orders
$orders_sql = "SELECT o.*, COUNT(oi.id) as items_count 
               FROM orders o 
               LEFT JOIN order_items oi ON o.id = oi.order_id 
               WHERE o.user_id = ? 
               GROUP BY o.id 
               ORDER BY o.created_at DESC";
$orders_stmt = $conn->prepare($orders_sql);
$orders_stmt->bind_param("i", $user_id);
$orders_stmt->execute();
$orders_result = $orders_stmt->get_result();
$orders = [];

if ($orders_result->num_rows > 0) {
    while ($row = $orders_result->fetch_assoc()) {
        $orders[] = $row;
    }
}

// Fetch user statistics
$stats_sql = "SELECT 
                COUNT(o.id) as total_orders,
                SUM(o.total_amount) as total_spent,
                AVG(o.total_amount) as avg_order_value,
                MAX(o.created_at) as last_order_date
              FROM orders o 
              WHERE o.user_id = ? AND o.status = 'delivered'";
$stats_stmt = $conn->prepare($stats_sql);
$stats_stmt->bind_param("i", $user_id);
$stats_stmt->execute();
$stats_result = $stats_stmt->get_result();
$user_stats = $stats_result->fetch_assoc();

// Calculate days since registration
$registered_date = new DateTime($user['created_at']);
$current_date = new DateTime();
$days_registered = $current_date->diff($registered_date)->days;
?>

<div class="admin-container">
    <!-- Sidebar -->
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
            <div class="header-left">
                <a href="users.php" class="back-btn">
                    <span class="back-icon">←</span>
                    Back to Users
                </a>
                <h1>User Details</h1>
            </div>
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

            <div class="user-details-layout">
                <!-- Left Column - User Info & Actions -->
                <div class="left-column">
                    <!-- User Profile Card -->
                    <div class="card profile-card">
                        <div class="card-header">
                            <h3>User Profile</h3>
                            <span class="user-badge role-<?php echo $user['role']; ?>">
                                <?php echo ucfirst($user['role']); ?>
                            </span>
                        </div>
                        <div class="card-body">
                            <div class="profile-header">
                                <div class="profile-avatar">
                                    <?php echo strtoupper(substr($user['name'], 0, 1)); ?>
                                </div>
                                <div class="profile-info">
                                    <h2><?php echo htmlspecialchars($user['name']); ?></h2>
                                    <p class="user-email"><?php echo htmlspecialchars($user['email']); ?></p>
                                    <p class="user-meta">User ID: #<?php echo $user['id']; ?></p>
                                </div>
                            </div>
                            
                            <div class="profile-stats">
                                <div class="stat-item">
                                    <span class="stat-value"><?php echo $user_stats['total_orders'] ?? 0; ?></span>
                                    <span class="stat-label">Total Orders</span>
                                </div>
                                <div class="stat-item">
                                    <span class="stat-value">$<?php echo number_format($user_stats['total_spent'] ?? 0, 2); ?></span>
                                    <span class="stat-label">Total Spent</span>
                                </div>
                                <div class="stat-item">
                                    <span class="stat-value"><?php echo $days_registered; ?></span>
                                    <span class="stat-label">Days Registered</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Quick Actions -->
                    <div class="card actions-card">
                        <div class="card-header">
                            <h3>Quick Actions</h3>
                        </div>
                        <div class="card-body">
                            <form method="POST" class="action-form">
                                <button type="submit" name="reset_password" class="btn btn-warning btn-block" 
                                        onclick="return confirm('Reset password to default (password123)?')">
                                    <span class="btn-icon">🔑</span>
                                    Reset Password
                                </button>
                            </form>
                            <?php if ($user['id'] != $_SESSION['user_id']): ?>
                                <form method="POST" class="action-form">
                                    <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                    <button type="submit" name="delete_user" class="btn btn-danger btn-block"
                                            onclick="return confirm('Are you sure you want to delete this user? This action cannot be undone.')">
                                        <span class="btn-icon">🗑️</span>
                                        Delete User
                                    </button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Right Column - User Details & Orders -->
                <div class="right-column">
                    <!-- Edit User Form -->
                    <div class="card edit-form-card">
                        <div class="card-header">
                            <h3>Edit User Information</h3>
                        </div>
                        <div class="card-body">
                            <form method="POST" class="user-edit-form">
                                <div class="form-group">
                                    <label for="name">Full Name</label>
                                    <input type="text" id="name" name="name" value="<?php echo htmlspecialchars($user['name']); ?>" 
                                           class="form-control" required>
                                </div>
                                
                                <div class="form-group">
                                    <label for="email">Email Address</label>
                                    <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" 
                                           class="form-control" required>
                                </div>
                                
                                <div class="form-group">
                                    <label for="role">User Role</label>
                                    <select id="role" name="role" class="form-control" 
                                        <?php echo $user['id'] == $_SESSION['user_id'] ? 'disabled' : ''; ?>>
                                        <option value="customer" <?php echo $user['role'] == 'customer' ? 'selected' : ''; ?>>Customer</option>
                                        <option value="admin" <?php echo $user['role'] == 'admin' ? 'selected' : ''; ?>>Administrator</option>
                                    </select>
                                    <?php if ($user['id'] == $_SESSION['user_id']): ?>
                                        <small class="form-text">You cannot change your own role</small>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="form-actions">
                                    <button type="submit" name="update_user" class="btn btn-primary">
                                        <span class="btn-icon">💾</span>
                                        Update User
                                    </button>
                                    <a href="users.php" class="btn btn-secondary">
                                        <span class="btn-icon">↶</span>
                                        Cancel
                                    </a>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- User Orders -->
                    <div class="card orders-card">
                        <div class="card-header">
                            <h3>Order History (<?php echo count($orders); ?>)</h3>
                        </div>
                        <div class="card-body">
                            <?php if (empty($orders)): ?>
                                <div class="empty-state">
                                    <div class="empty-icon">🛒</div>
                                    <h3>No Orders Found</h3>
                                    <p>This user hasn't placed any orders yet.</p>
                                </div>
                            <?php else: ?>
                                <div class="orders-list">
                                    <?php foreach($orders as $order): ?>
                                        <div class="order-item">
                                            <div class="order-header">
                                                <div class="order-info">
                                                    <h4>Order #<?php echo $order['id']; ?></h4>
                                                    <span class="order-date"><?php echo date('M j, Y \a\t g:i A', strtotime($order['created_at'])); ?></span>
                                                </div>
                                                <div class="order-meta">
                                                    <span class="order-amount">$<?php echo number_format($order['total_amount'], 2); ?></span>
                                                    <span class="order-status status-<?php echo $order['status']; ?>">
                                                        <?php echo ucfirst($order['status']); ?>
                                                    </span>
                                                </div>
                                            </div>
                                            <div class="order-details">
                                                <span class="items-count"><?php echo $order['items_count']; ?> items</span>
                                                <a href="order-details.php?id=<?php echo $order['id']; ?>" class="view-order-btn">
                                                    View Details
                                                </a>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- User Statistics -->
                    <div class="card stats-card">
                        <div class="card-header">
                            <h3>User Statistics</h3>
                        </div>
                        <div class="card-body">
                            <div class="stats-grid-mini">
                                <div class="stat-mini">
                                    <div class="stat-icon-mini total">📦</div>
                                    <div class="stat-info-mini">
                                        <h4><?php echo $user_stats['total_orders'] ?? 0; ?></h4>
                                        <p>Total Orders</p>
                                    </div>
                                </div>
                                <div class="stat-mini">
                                    <div class="stat-icon-mini revenue">💰</div>
                                    <div class="stat-info-mini">
                                        <h4>$<?php echo number_format($user_stats['total_spent'] ?? 0, 2); ?></h4>
                                        <p>Total Spent</p>
                                    </div>
                                </div>
                                <div class="stat-mini">
                                    <div class="stat-icon-mini average">📊</div>
                                    <div class="stat-info-mini">
                                        <h4>$<?php echo number_format($user_stats['avg_order_value'] ?? 0, 2); ?></h4>
                                        <p>Avg. Order</p>
                                    </div>
                                </div>
                                <div class="stat-mini">
                                    <div class="stat-icon-mini calendar">📅</div>
                                    <div class="stat-info-mini">
                                        <h4><?php echo $user_stats['last_order_date'] ? date('M j', strtotime($user_stats['last_order_date'])) : 'N/A'; ?></h4>
                                        <p>Last Order</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
/* User Details Specific Styles */
.user-details-layout {
    display: grid;
    grid-template-columns: 350px 1fr;
    gap: 2rem;
}

.left-column {
    display: flex;
    flex-direction: column;
    gap: 1.5rem;
}

.right-column {
    display: flex;
    flex-direction: column;
    gap: 1.5rem;
}

/* Back Button */
.header-left {
    display: flex;
    align-items: center;
    gap: 1rem;
}

.back-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.5rem 1rem;
    background: #f8f9fa;
    color: #2c3e50;
    text-decoration: none;
    border-radius: 6px;
    border: 1px solid #e9ecef;
    transition: all 0.3s;
    font-weight: 500;
}

.back-btn:hover {
    background: #e9ecef;
    transform: translateX(-2px);
}

.back-icon {
    font-weight: bold;
}

/* Profile Card */
.profile-card .card-body {
    padding: 2rem;
}

.profile-header {
    display: flex;
    align-items: center;
    gap: 1.5rem;
    margin-bottom: 2rem;
}

.profile-avatar {
    width: 80px;
    height: 80px;
    background: linear-gradient(135deg, #4CAF50, #45a049);
    color: white;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 2rem;
    font-weight: bold;
    box-shadow: 0 4px 15px rgba(76, 175, 80, 0.3);
}

.profile-info h2 {
    margin: 0 0 0.5rem 0;
    color: #2c3e50;
    font-size: 1.5rem;
}

.user-email {
    margin: 0 0 0.5rem 0;
    color: #6c757d;
    font-size: 1rem;
}

.user-meta {
    margin: 0;
    color: #8a94a6;
    font-size: 0.9rem;
}

.user-badge {
    padding: 0.4rem 1rem;
    border-radius: 20px;
    font-size: 0.8rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.role-admin {
    background: rgba(76, 175, 80, 0.1);
    color: #4CAF50;
    border: 1px solid #4CAF50;
}

.role-customer {
    background: rgba(52, 152, 219, 0.1);
    color: #3498db;
    border: 1px solid #3498db;
}

.profile-stats {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 1rem;
    padding-top: 1.5rem;
    border-top: 1px solid #e9ecef;
}

.stat-item {
    text-align: center;
    padding: 1rem;
    background: #f8f9fa;
    border-radius: 8px;
}

.stat-value {
    display: block;
    font-size: 1.5rem;
    font-weight: 700;
    color: #2c3e50;
    margin-bottom: 0.25rem;
}

.stat-label {
    font-size: 0.8rem;
    color: #6c757d;
    font-weight: 500;
}

/* Actions Card */
.actions-card .card-body {
    padding: 1.5rem;
}

.action-form {
    margin-bottom: 1rem;
}

.action-form:last-child {
    margin-bottom: 0;
}

.btn-block {
    width: 100%;
    justify-content: center;
}

.btn-warning {
    background: linear-gradient(135deg, #ffc107, #e0a800);
    color: #212529;
}

.btn-warning:hover {
    background: linear-gradient(135deg, #e0a800, #d39e00);
}

/* Edit Form */
.user-edit-form {
    display: flex;
    flex-direction: column;
    gap: 1.5rem;
}

.form-group {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.form-group label {
    font-weight: 600;
    color: #2c3e50;
    font-size: 0.9rem;
}

.form-control {
    padding: 0.75rem 1rem;
    border: 1px solid #ddd;
    border-radius: 8px;
    font-size: 1rem;
    transition: all 0.3s;
    background: white;
}

.form-control:focus {
    outline: none;
    border-color: #4CAF50;
    box-shadow: 0 0 0 2px rgba(76, 175, 80, 0.1);
}

.form-text {
    font-size: 0.8rem;
    color: #6c757d;
    margin-top: 0.25rem;
}

.form-actions {
    display: flex;
    gap: 1rem;
    margin-top: 1rem;
}

.btn-secondary {
    background: #6c757d;
    color: white;
}

.btn-secondary:hover {
    background: #5a6268;
}

/* Orders List */
.orders-list {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.order-item {
    padding: 1.5rem;
    border: 1px solid #e9ecef;
    border-radius: 8px;
    transition: all 0.3s;
}

.order-item:hover {
    border-color: #4CAF50;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}

.order-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 1rem;
}

.order-info h4 {
    margin: 0 0 0.25rem 0;
    color: #2c3e50;
    font-size: 1.1rem;
}

.order-date {
    font-size: 0.85rem;
    color: #6c757d;
}

.order-meta {
    display: flex;
    flex-direction: column;
    align-items: flex-end;
    gap: 0.5rem;
}

.order-amount {
    font-size: 1.2rem;
    font-weight: 700;
    color: #4CAF50;
}

.order-status {
    padding: 0.3rem 0.75rem;
    border-radius: 15px;
    font-size: 0.75rem;
    font-weight: 600;
    text-transform: uppercase;
}

.status-pending { background: #fff3cd; color: #856404; }
.status-processing { background: #cce7ff; color: #004085; }
.status-shipped { background: #d1ecf1; color: #0c5460; }
.status-delivered { background: #d4edda; color: #155724; }
.status-cancelled { background: #f8d7da; color: #721c24; }

.order-details {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding-top: 1rem;
    border-top: 1px solid #e9ecef;
}

.items-count {
    color: #6c757d;
    font-size: 0.9rem;
}

.view-order-btn {
    color: #4CAF50;
    text-decoration: none;
    font-weight: 500;
    font-size: 0.9rem;
    transition: color 0.3s;
}

.view-order-btn:hover {
    color: #45a049;
    text-decoration: underline;
}

/* Mini Stats */
.stats-grid-mini {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 1rem;
}

.stat-mini {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 1rem;
    background: #f8f9fa;
    border-radius: 8px;
    border: 1px solid #e9ecef;
}

.stat-icon-mini {
    width: 50px;
    height: 50px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 8px;
    font-size: 1.5rem;
}

.stat-icon-mini.total { background: rgba(76, 175, 80, 0.1); color: #4CAF50; }
.stat-icon-mini.revenue { background: rgba(52, 152, 219, 0.1); color: #3498db; }
.stat-icon-mini.average { background: rgba(155, 89, 182, 0.1); color: #9b59b6; }
.stat-icon-mini.calendar { background: rgba(241, 196, 15, 0.1); color: #f1c40f; }

.stat-info-mini h4 {
    margin: 0 0 0.25rem 0;
    color: #2c3e50;
    font-size: 1.1rem;
}

.stat-info-mini p {
    margin: 0;
    color: #6c757d;
    font-size: 0.8rem;
    font-weight: 500;
}

/* Responsive Design */
@media (max-width: 1024px) {
    .user-details-layout {
        grid-template-columns: 1fr;
    }
    
    .left-column {
        order: 2;
    }
    
    .right-column {
        order: 1;
    }
}

@media (max-width: 768px) {
    .profile-header {
        flex-direction: column;
        text-align: center;
    }
    
    .profile-stats {
        grid-template-columns: 1fr;
    }
    
    .stats-grid-mini {
        grid-template-columns: 1fr;
    }
    
    .order-header {
        flex-direction: column;
        gap: 1rem;
    }
    
    .order-meta {
        align-items: flex-start;
    }
    
    .form-actions {
        flex-direction: column;
    }
}
</style>

<script>
// JavaScript for enhanced user interactions
document.addEventListener('DOMContentLoaded', function() {
    // Auto-save form changes
    const form = document.querySelector('.user-edit-form');
    const inputs = form.querySelectorAll('input, select');
    
    inputs.forEach(input => {
        input.addEventListener('change', function() {
            // Add visual feedback for changes
            this.style.borderColor = '#4CAF50';
            this.style.boxShadow = '0 0 0 2px rgba(76, 175, 80, 0.2)';
            
            setTimeout(() => {
                this.style.borderColor = '';
                this.style.boxShadow = '';
            }, 1000);
        });
    });
    
    // Enhanced delete confirmation
    const deleteButtons = document.querySelectorAll('button[name="delete_user"]');
    deleteButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            if (!confirm('Are you absolutely sure you want to delete this user?\n\nThis action cannot be undone and will permanently remove all user data.')) {
                e.preventDefault();
            }
        });
    });
});
</script>

<?php include 'admin-footer.php'; ?>