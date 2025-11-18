<?php
include '../includes/config.php';
include 'admin-header.php';

// Check if user is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: ../login.php");
    exit;
}

// Get dashboard statistics
$stats = [];

// Total products
$result = $conn->query("SELECT COUNT(*) as total FROM products");
$stats['total_products'] = $result->fetch_assoc()['total'];

// Total orders
$result = $conn->query("SELECT COUNT(*) as total FROM orders");
$stats['total_orders'] = $result->fetch_assoc()['total'];

// Total users
$result = $conn->query("SELECT COUNT(*) as total FROM users");
$stats['total_users'] = $result->fetch_assoc()['total'];

// Total revenue
$result = $conn->query("SELECT SUM(total_amount) as total FROM orders WHERE status = 'delivered'");
$stats['total_revenue'] = $result->fetch_assoc()['total'] ?: 0;

// Pending orders
$result = $conn->query("SELECT COUNT(*) as total FROM orders WHERE status = 'pending'");
$stats['pending_orders'] = $result->fetch_assoc()['total'];

// Low stock products
$result = $conn->query("SELECT COUNT(*) as total FROM products WHERE stock_quantity < 10");
$stats['low_stock'] = $result->fetch_assoc()['total'];

// Recent orders
$recent_orders = [];
$result = $conn->query("SELECT o.*, u.name as customer_name FROM orders o JOIN users u ON o.user_id = u.id ORDER BY o.created_at DESC LIMIT 5");
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $recent_orders[] = $row;
    }
}

// Recent users
$recent_users = [];
$result = $conn->query("SELECT * FROM users ORDER BY created_at DESC LIMIT 5");
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $recent_users[] = $row;
    }
}
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
                <li class="nav-item active">
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
                    <a href="categories.php" class="nav-link">
                        <span class="nav-icon">🏷️</span>
                        <span class="nav-text">Categories</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="orders.php" class="nav-link">
                        <span class="nav-icon">🛒</span>
                        <span class="nav-text">Orders</span>
                        <?php if ($stats['pending_orders'] > 0): ?>
                            <span class="nav-badge"><?php echo $stats['pending_orders']; ?></span>
                        <?php endif; ?>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="users.php" class="nav-link">
                        <span class="nav-icon">👥</span>
                        <span class="nav-text">Users</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="reports.php" class="nav-link">
                        <span class="nav-icon">📈</span>
                        <span class="nav-text">Reports</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="settings.php" class="nav-link">
                        <span class="nav-icon">⚙️</span>
                        <span class="nav-text">Settings</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="../logout.php" class="nav-link">
                        <span class="nav-icon">🚪</span>
                        <span class="nav-text">Logout</span>
                    </a>
                </li>
            </ul>
        </nav>
    </div>

    <!-- Main Content -->
    <div class="admin-main">
        <div class="admin-header">
            <h1>Dashboard Overview</h1>
            <div class="admin-user">
                <span>Welcome, <?php echo $_SESSION['user_name']; ?></span>
                <div class="user-dropdown">
                    <a href="../profile.php">Profile</a>
                    <a href="../logout.php">Logout</a>
                </div>
            </div>
        </div>

        <div class="admin-content">
            <!-- Statistics Cards -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon revenue">💰</div>
                    <div class="stat-info">
                        <h3>$<?php echo number_format($stats['total_revenue'], 2); ?></h3>
                        <p>Total Revenue</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon orders">🛒</div>
                    <div class="stat-info">
                        <h3><?php echo $stats['total_orders']; ?></h3>
                        <p>Total Orders</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon products">📦</div>
                    <div class="stat-info">
                        <h3><?php echo $stats['total_products']; ?></h3>
                        <p>Total Products</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon users">👥</div>
                    <div class="stat-info">
                        <h3><?php echo $stats['total_users']; ?></h3>
                        <p>Total Users</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon pending">⏳</div>
                    <div class="stat-info">
                        <h3><?php echo $stats['pending_orders']; ?></h3>
                        <p>Pending Orders</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon warning">⚠️</div>
                    <div class="stat-info">
                        <h3><?php echo $stats['low_stock']; ?></h3>
                        <p>Low Stock Items</p>
                    </div>
                </div>
            </div>

            <!-- Recent Activity Section -->
            <div class="activity-grid">
                <!-- Recent Orders -->
                <div class="activity-card">
                    <div class="card-header">
                        <h3>Recent Orders</h3>
                        <a href="orders.php" class="view-all">View All</a>
                    </div>
                    <div class="card-content">
                        <?php if (!empty($recent_orders)): ?>
                            <div class="orders-list">
                                <?php foreach ($recent_orders as $order): ?>
                                    <div class="order-item">
                                        <div class="order-info">
                                            <h4>Order #<?php echo $order['id']; ?></h4>
                                            <p><?php echo $order['customer_name']; ?></p>
                                        </div>
                                        <div class="order-meta">
                                            <span class="amount">$<?php echo number_format($order['total_amount'], 2); ?></span>
                                            <span class="status status-<?php echo $order['status']; ?>">
                                                <?php echo ucfirst($order['status']); ?>
                                            </span>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <p class="no-data">No recent orders</p>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Recent Users -->
                <div class="activity-card">
                    <div class="card-header">
                        <h3>Recent Users</h3>
                        <a href="users.php" class="view-all">View All</a>
                    </div>
                    <div class="card-content">
                        <?php if (!empty($recent_users)): ?>
                            <div class="users-list">
                                <?php foreach ($recent_users as $user): ?>
                                    <div class="user-item">
                                        <div class="user-avatar">
                                            <?php echo strtoupper(substr($user['name'], 0, 1)); ?>
                                        </div>
                                        <div class="user-info">
                                            <h4><?php echo htmlspecialchars($user['name']); ?></h4>
                                            <p><?php echo htmlspecialchars($user['email']); ?></p>
                                        </div>
                                        <div class="user-role">
                                            <span class="role-badge role-<?php echo $user['role']; ?>">
                                                <?php echo ucfirst($user['role']); ?>
                                            </span>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <p class="no-data">No recent users</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="quick-actions">
                <h3>Quick Actions</h3>
                <div class="actions-grid">
                    <a href="products.php?action=add" class="action-card">
                        <div class="action-icon">➕</div>
                        <span>Add Product</span>
                    </a>
                    <a href="categories.php?action=add" class="action-card">
                        <div class="action-icon">🏷️</div>
                        <span>Add Category</span>
                    </a>
                    <a href="orders.php" class="action-card">
                        <div class="action-icon">📋</div>
                        <span>Manage Orders</span>
                    </a>
                    <a href="reports.php" class="action-card">
                        <div class="action-icon">📊</div>
                        <span>View Reports</span>
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
/* Admin Panel Styles */
.admin-container {
    display: flex;
    min-height: 100vh;
    background: #f8f9fa;
}

/* Sidebar Styles */
.admin-sidebar {
    width: 280px;
    background: #2c3e50;
    color: white;
    position: fixed;
    height: 100vh;
    overflow-y: auto;
}

.sidebar-header {
    padding: 2rem 1.5rem 1.5rem;
    border-bottom: 1px solid #34495e;
}

.sidebar-header h3 {
    margin: 0 0 0.5rem 0;
    font-size: 1.5rem;
    color: #4CAF50;
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
    margin-bottom: 0.5rem;
}

.nav-link {
    display: flex;
    align-items: center;
    padding: 1rem 1.5rem;
    color: #bdc3c7;
    text-decoration: none;
    transition: all 0.3s;
    position: relative;
}

.nav-link:hover, .nav-item.active .nav-link {
    background: #34495e;
    color: white;
    border-left: 4px solid #4CAF50;
}

.nav-icon {
    font-size: 1.2rem;
    margin-right: 1rem;
    width: 20px;
    text-align: center;
}

.nav-text {
    flex: 1;
}

.nav-badge {
    background: #e74c3c;
    color: white;
    border-radius: 10px;
    padding: 0.2rem 0.6rem;
    font-size: 0.8rem;
    font-weight: bold;
}

/* Main Content Styles */
.admin-main {
    flex: 1;
    margin-left: 280px;
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
}

.admin-user {
    position: relative;
    cursor: pointer;
    padding: 0.5rem 1rem;
    border-radius: 5px;
    transition: background 0.3s;
}

.admin-user:hover {
    background: #f8f9fa;
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
    border-radius: 5px;
    overflow: hidden;
    z-index: 1000;
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
    background: #f8f9fa;
    color: #4CAF50;
}

/* Admin Content */
.admin-content {
    padding: 2rem;
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
    border-radius: 10px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    display: flex;
    align-items: center;
    transition: transform 0.3s, box-shadow 0.3s;
}

.stat-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 5px 20px rgba(0,0,0,0.15);
}

.stat-icon {
    font-size: 2.5rem;
    margin-right: 1rem;
    width: 60px;
    height: 60px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 10px;
}

.stat-icon.revenue { background: #e8f5e8; }
.stat-icon.orders { background: #e3f2fd; }
.stat-icon.products { background: #fff3e0; }
.stat-icon.users { background: #f3e5f5; }
.stat-icon.pending { background: #fff8e1; }
.stat-icon.warning { background: #ffebee; }

.stat-info h3 {
    margin: 0 0 0.5rem 0;
    font-size: 1.8rem;
    color: #2c3e50;
}

.stat-info p {
    margin: 0;
    color: #7f8c8d;
    font-weight: 500;
}

/* Activity Grid */
.activity-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 2rem;
    margin-bottom: 2rem;
}

.activity-card {
    background: white;
    border-radius: 10px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    overflow: hidden;
}

.card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 1.5rem;
    border-bottom: 1px solid #e9ecef;
}

.card-header h3 {
    margin: 0;
    color: #2c3e50;
}

.view-all {
    color: #4CAF50;
    text-decoration: none;
    font-weight: 500;
}

.view-all:hover {
    text-decoration: underline;
}

.card-content {
    padding: 1.5rem;
}

/* Orders List */
.orders-list, .users-list {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.order-item, .user-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 1rem;
    border-radius: 8px;
    background: #f8f9fa;
    transition: background 0.3s;
}

.order-item:hover, .user-item:hover {
    background: #e9ecef;
}

.order-info h4, .user-info h4 {
    margin: 0 0 0.25rem 0;
    color: #2c3e50;
}

.order-info p, .user-info p {
    margin: 0;
    color: #7f8c8d;
    font-size: 0.9rem;
}

.order-meta {
    display: flex;
    flex-direction: column;
    align-items: flex-end;
    gap: 0.5rem;
}

.amount {
    font-weight: bold;
    color: #4CAF50;
}

/* User Item */
.user-item {
    align-items: center;
}

.user-avatar {
    width: 40px;
    height: 40px;
    background: #4CAF50;
    color: white;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: bold;
    margin-right: 1rem;
}

.user-info {
    flex: 1;
}

.role-badge {
    padding: 0.25rem 0.75rem;
    border-radius: 15px;
    font-size: 0.8rem;
    font-weight: 500;
}

.role-admin { background: #4CAF50; color: white; }
.role-customer { background: #3498db; color: white; }

/* Status Badges */
.status {
    padding: 0.25rem 0.75rem;
    border-radius: 15px;
    font-size: 0.8rem;
    font-weight: 500;
}

.status-pending { background: #fff3cd; color: #856404; }
.status-processing { background: #cce7ff; color: #004085; }
.status-shipped { background: #d1ecf1; color: #0c5460; }
.status-delivered { background: #d4edda; color: #155724; }
.status-cancelled { background: #f8d7da; color: #721c24; }

/* Quick Actions */
.quick-actions {
    background: white;
    padding: 2rem;
    border-radius: 10px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
}

.quick-actions h3 {
    margin: 0 0 1.5rem 0;
    color: #2c3e50;
}

.actions-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1.5rem;
}

.action-card {
    display: flex;
    flex-direction: column;
    align-items: center;
    padding: 2rem 1rem;
    background: #f8f9fa;
    border-radius: 10px;
    text-decoration: none;
    color: #2c3e50;
    transition: all 0.3s;
    border: 2px solid transparent;
}

.action-card:hover {
    background: #4CAF50;
    color: white;
    transform: translateY(-3px);
    box-shadow: 0 5px 15px rgba(76, 175, 80, 0.3);
    border-color: #4CAF50;
}

.action-icon {
    font-size: 2.5rem;
    margin-bottom: 1rem;
}

.action-card span {
    font-weight: 500;
    text-align: center;
}

.no-data {
    text-align: center;
    color: #7f8c8d;
    font-style: italic;
    padding: 2rem;
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
    
    .activity-grid {
        grid-template-columns: 1fr;
    }
    
    .stats-grid {
        grid-template-columns: 1fr 1fr;
    }
}

@media (max-width: 480px) {
    .admin-content {
        padding: 1rem;
    }
    
    .stats-grid {
        grid-template-columns: 1fr;
    }
    
    .admin-header {
        flex-direction: column;
        gap: 1rem;
        align-items: flex-start;
    }
}
</style>

<?php include '../includes/footer.php'; ?>
<?php
include 'admin-header.php'; // Use admin header instead

// Your stats code here...
?>

<div class="admin-container">
    <!-- Your admin panel content -->
</div>

<?php include 'admin-footer.php'; ?>
<?php
include 'admin-header.php'; // Use admin header instead of main website header

// Get dashboard statistics
$stats = [];

// Total products
$result = $conn->query("SELECT COUNT(*) as total FROM products");
$stats['total_products'] = $result->fetch_assoc()['total'];

// Total orders
$result = $conn->query("SELECT COUNT(*) as total FROM orders");
$stats['total_orders'] = $result->fetch_assoc()['total'];

// Total users
$result = $conn->query("SELECT COUNT(*) as total FROM users");
$stats['total_users'] = $result->fetch_assoc()['total'];

// Total revenue
$result = $conn->query("SELECT SUM(total_amount) as total FROM orders WHERE status = 'delivered'");
$stats['total_revenue'] = $result->fetch_assoc()['total'] ?: 0;

// Pending orders
$result = $conn->query("SELECT COUNT(*) as total FROM orders WHERE status = 'pending'");
$stats['pending_orders'] = $result->fetch_assoc()['total'];

// Low stock products
$result = $conn->query("SELECT COUNT(*) as total FROM products WHERE stock_quantity < 10");
$stats['low_stock'] = $result->fetch_assoc()['total'];

// Recent orders
$recent_orders = [];
$result = $conn->query("SELECT o.*, u.name as customer_name FROM orders o JOIN users u ON o.user_id = u.id ORDER BY o.created_at DESC LIMIT 5");
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $recent_orders[] = $row;
    }
}

// Recent users
$recent_users = [];
$result = $conn->query("SELECT * FROM users ORDER BY created_at DESC LIMIT 5");
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $recent_users[] = $row;
    }
}
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
                <li class="nav-item active">
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
                    <a href="categories.php" class="nav-link">
                        <span class="nav-icon">🏷️</span>
                        <span class="nav-text">Categories</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="orders.php" class="nav-link">
                        <span class="nav-icon">🛒</span>
                        <span class="nav-text">Orders</span>
                        <?php if ($stats['pending_orders'] > 0): ?>
                            <span class="nav-badge"><?php echo $stats['pending_orders']; ?></span>
                        <?php endif; ?>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="users.php" class="nav-link">
                        <span class="nav-icon">👥</span>
                        <span class="nav-text">Users</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="reports.php" class="nav-link">
                        <span class="nav-icon">📈</span>
                        <span class="nav-text">Reports</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="../index.php" class="nav-link">
                        <span class="nav-icon">🏠</span>
                        <span class="nav-text">Back to Site</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="../logout.php" class="nav-link">
                        <span class="nav-icon">🚪</span>
                        <span class="nav-text">Logout</span>
                    </a>
                </li>
            </ul>
        </nav>
    </div>

    <!-- Main Content -->
    <div class="admin-main">
        <div class="admin-header">
            <h1>Dashboard Overview</h1>
            <div class="admin-user">
                <span>Welcome, <?php echo $_SESSION['user_name']; ?></span>
                <div class="user-dropdown">
                    <a href="../profile.php">Profile</a>
                    <a href="../logout.php">Logout</a>
                </div>
            </div>
        </div>

        <div class="admin-content">
            <!-- Statistics Cards -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon revenue">💰</div>
                    <div class="stat-info">
                        <h3>$<?php echo number_format($stats['total_revenue'], 2); ?></h3>
                        <p>Total Revenue</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon orders">🛒</div>
                    <div class="stat-info">
                        <h3><?php echo $stats['total_orders']; ?></h3>
                        <p>Total Orders</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon products">📦</div>
                    <div class="stat-info">
                        <h3><?php echo $stats['total_products']; ?></h3>
                        <p>Total Products</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon users">👥</div>
                    <div class="stat-info">
                        <h3><?php echo $stats['total_users']; ?></h3>
                        <p>Total Users</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon pending">⏳</div>
                    <div class="stat-info">
                        <h3><?php echo $stats['pending_orders']; ?></h3>
                        <p>Pending Orders</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon warning">⚠️</div>
                    <div class="stat-info">
                        <h3><?php echo $stats['low_stock']; ?></h3>
                        <p>Low Stock Items</p>
                    </div>
                </div>
            </div>

            <!-- Recent Activity Section -->
            <div class="activity-grid">
                <!-- Recent Orders -->
                <div class="activity-card">
                    <div class="card-header">
                        <h3>Recent Orders</h3>
                        <a href="orders.php" class="view-all">View All</a>
                    </div>
                    <div class="card-content">
                        <?php if (!empty($recent_orders)): ?>
                            <div class="orders-list">
                                <?php foreach ($recent_orders as $order): ?>
                                    <div class="order-item">
                                        <div class="order-info">
                                            <h4>Order #<?php echo $order['id']; ?></h4>
                                            <p><?php echo $order['customer_name']; ?></p>
                                        </div>
                                        <div class="order-meta">
                                            <span class="amount">$<?php echo number_format($order['total_amount'], 2); ?></span>
                                            <span class="status status-<?php echo $order['status']; ?>">
                                                <?php echo ucfirst($order['status']); ?>
                                            </span>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <p class="no-data">No recent orders</p>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Recent Users -->
                <div class="activity-card">
                    <div class="card-header">
                        <h3>Recent Users</h3>
                        <a href="users.php" class="view-all">View All</a>
                    </div>
                    <div class="card-content">
                        <?php if (!empty($recent_users)): ?>
                            <div class="users-list">
                                <?php foreach ($recent_users as $user): ?>
                                    <div class="user-item">
                                        <div class="user-avatar">
                                            <?php echo strtoupper(substr($user['name'], 0, 1)); ?>
                                        </div>
                                        <div class="user-info">
                                            <h4><?php echo htmlspecialchars($user['name']); ?></h4>
                                            <p><?php echo htmlspecialchars($user['email']); ?></p>
                                        </div>
                                        <div class="user-role">
                                            <span class="role-badge role-<?php echo $user['role']; ?>">
                                                <?php echo ucfirst($user['role']); ?>
                                            </span>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <p class="no-data">No recent users</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="quick-actions">
                <h3>Quick Actions</h3>
                <div class="actions-grid">
                    <a href="products.php?action=add" class="action-card">
                        <div class="action-icon">➕</div>
                        <span>Add Product</span>
                    </a>
                    <a href="categories.php?action=add" class="action-card">
                        <div class="action-icon">🏷️</div>
                        <span>Add Category</span>
                    </a>
                    <a href="orders.php" class="action-card">
                        <div class="action-icon">📋</div>
                        <span>Manage Orders</span>
                    </a>
                    <a href="reports.php" class="action-card">
                        <div class="action-icon">📊</div>
                        <span>View Reports</span>
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
/* Your existing CSS styles - paste all of them here */
/* Admin Panel Styles */
.admin-container {
    display: flex;
    min-height: 100vh;
    background: #f8f9fa;
}

/* ... [PASTE ALL YOUR EXISTING CSS STYLES HERE] ... */
</style>

<?php include 'admin-footer.php'; ?>