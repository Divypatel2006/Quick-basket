<?php
include '../includes/config.php';
include 'admin-header.php';

// Check if user is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: ../index.php");
    exit;
}

// Handle order status update
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_status'])) {
    $order_id = intval($_POST['order_id']);
    $status = $_POST['status'];
    
    $sql = "UPDATE orders SET status = ? WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("si", $status, $order_id);
    
    if ($stmt->execute()) {
        $_SESSION['success'] = "Order status updated successfully!";
    } else {
        $_SESSION['error'] = "Failed to update order status.";
    }
}

// Fetch orders with pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

// Get total orders for pagination
$total_orders = $conn->query("SELECT COUNT(*) as total FROM orders")->fetch_assoc()['total'];
$total_pages = ceil($total_orders / $limit);

$orders = [];
$sql = "SELECT o.*, u.name as customer_name FROM orders o 
        JOIN users u ON o.user_id = u.id 
        ORDER BY o.created_at DESC 
        LIMIT ? OFFSET ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $limit, $offset);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $orders[] = $row;
    }
}

// Get order statistics
$stats = [
    'total' => $total_orders,
    'pending' => $conn->query("SELECT COUNT(*) as total FROM orders WHERE status = 'pending'")->fetch_assoc()['total'],
    'processing' => $conn->query("SELECT COUNT(*) as total FROM orders WHERE status = 'processing'")->fetch_assoc()['total'],
    'shipped' => $conn->query("SELECT COUNT(*) as total FROM orders WHERE status = 'shipped'")->fetch_assoc()['total'],
    'delivered' => $conn->query("SELECT COUNT(*) as total FROM orders WHERE status = 'delivered'")->fetch_assoc()['total']
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
                <li class="nav-item active">
                    <a href="orders.php" class="nav-link">
                        <span class="nav-icon">🛒</span>
                        <span class="nav-text">Orders</span>
                        <?php if ($stats['pending'] > 0): ?>
                            <span class="nav-badge"><?php echo $stats['pending']; ?></span>
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
            <h1>Order Management</h1>
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

            <!-- Order Statistics -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon total">📋</div>
                    <div class="stat-info">
                        <h3><?php echo $stats['total']; ?></h3>
                        <p>Total Orders</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon pending">⏳</div>
                    <div class="stat-info">
                        <h3><?php echo $stats['pending']; ?></h3>
                        <p>Pending</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon processing">🔄</div>
                    <div class="stat-info">
                        <h3><?php echo $stats['processing']; ?></h3>
                        <p>Processing</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon shipped">🚚</div>
                    <div class="stat-info">
                        <h3><?php echo $stats['shipped']; ?></h3>
                        <p>Shipped</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon delivered">✅</div>
                    <div class="stat-info">
                        <h3><?php echo $stats['delivered']; ?></h3>
                        <p>Delivered</p>
                    </div>
                </div>
            </div>

            <!-- Orders Table -->
            <div class="card">
                <div class="card-header">
                    <h3>All Orders</h3>
                    <div class="card-actions">
                        <div class="search-box">
                            <input type="text" id="searchOrders" placeholder="Search orders...">
                            <span class="search-icon">🔍</span>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <?php if (empty($orders)): ?>
                        <div class="empty-state">
                            <div class="empty-icon">📦</div>
                            <h3>No Orders Found</h3>
                            <p>There are no orders in the system yet.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-container">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Order ID</th>
                                        <th>Customer</th>
                                        <th>Amount</th>
                                        <th>Status</th>
                                        <th>Date</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach($orders as $order): ?>
                                    <tr class="order-row" data-order-id="<?php echo $order['id']; ?>">
                                        <td>
                                            <div class="order-id">#<?php echo $order['id']; ?></div>
                                        </td>
                                        <td>
                                            <div class="customer-info">
                                                <strong><?php echo htmlspecialchars($order['customer_name']); ?></strong>
                                                <?php if ($order['shipping_city']): ?>
                                                    <span class="customer-location"><?php echo htmlspecialchars($order['shipping_city']); ?></span>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="amount">$<?php echo number_format($order['total_amount'], 2); ?></div>
                                        </td>
                                        <td>
                                            <form method="POST" action="" class="status-form">
                                                <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                                                <select name="status" class="status-select status-<?php echo $order['status']; ?>" onchange="this.form.submit()">
                                                    <option value="pending" <?php echo $order['status'] == 'pending' ? 'selected' : ''; ?>>Pending</option>
                                                    <option value="processing" <?php echo $order['status'] == 'processing' ? 'selected' : ''; ?>>Processing</option>
                                                    <option value="shipped" <?php echo $order['status'] == 'shipped' ? 'selected' : ''; ?>>Shipped</option>
                                                    <option value="delivered" <?php echo $order['status'] == 'delivered' ? 'selected' : ''; ?>>Delivered</option>
                                                    <option value="cancelled" <?php echo $order['status'] == 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                                                </select>
                                                <input type="hidden" name="update_status" value="1">
                                            </form>
                                        </td>
                                        <td>
                                            <div class="order-date">
                                                <div class="date"><?php echo date('M j, Y', strtotime($order['created_at'])); ?></div>
                                                <div class="time"><?php echo date('g:i A', strtotime($order['created_at'])); ?></div>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="action-buttons">
                                                <a href="order-details.php?id=<?php echo $order['id']; ?>" class="btn btn-primary btn-sm" title="View Details">
                                                    <span class="btn-icon">👁️</span>
                                                    View
                                                </a>
                                                <a href="../invoice.php?id=<?php echo $order['id']; ?>" class="btn btn-secondary btn-sm" title="Download Invoice">
                                                    <span class="btn-icon">📄</span>
                                                    Invoice
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <!-- Pagination -->
                        <?php if ($total_pages > 1): ?>
                        <div class="pagination">
                            <?php if ($page > 1): ?>
                                <a href="?page=<?php echo $page - 1; ?>" class="page-link prev">← Previous</a>
                            <?php endif; ?>
                            
                            <div class="page-numbers">
                                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                    <a href="?page=<?php echo $i; ?>" class="page-link <?php echo $i == $page ? 'active' : ''; ?>">
                                        <?php echo $i; ?>
                                    </a>
                                <?php endfor; ?>
                            </div>
                            
                            <?php if ($page < $total_pages): ?>
                                <a href="?page=<?php echo $page + 1; ?>" class="page-link next">Next →</a>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
/* Enhanced Admin Orders Styles */
.admin-container {
    display: flex;
    min-height: 100vh;
    background: #f8f9fa;
}

/* Sidebar Styles */
.admin-sidebar {
    width: 280px;
    background: linear-gradient(135deg, #2c3e50, #34495e);
    color: white;
    position: fixed;
    height: 100vh;
    overflow-y: auto;
    box-shadow: 2px 0 10px rgba(0,0,0,0.1);
}

.sidebar-header {
    padding: 2rem 1.5rem 1.5rem;
    border-bottom: 1px solid #34495e;
    background: rgba(255,255,255,0.05);
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
    margin-bottom: 0.5rem;
}

.nav-link {
    display: flex;
    align-items: center;
    padding: 1rem 1.5rem;
    color: #bdc3c7;
    text-decoration: none;
    transition: all 0.3s ease;
    position: relative;
    border-left: 4px solid transparent;
}

.nav-link:hover {
    background: rgba(255,255,255,0.1);
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

.nav-badge {
    background: #e74c3c;
    color: white;
    border-radius: 10px;
    padding: 0.2rem 0.6rem;
    font-size: 0.8rem;
    font-weight: bold;
    min-width: 20px;
    text-align: center;
}

/* Main Content */
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
    color: #495057;
    text-decoration: none;
    border-bottom: 1px solid #f8f9fa;
    transition: all 0.3s;
    font-weight: 500;
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
    margin-bottom: 1.5rem;
    border-radius: 8px;
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

.alert-content {
    display: flex;
    align-items: center;
    padding: 1rem;
}

.alert-icon {
    margin-right: 0.75rem;
    font-size: 1.2rem;
}

/* Statistics Grid */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
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
    border: 1px solid #f1f3f4;
}

.stat-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 5px 20px rgba(0,0,0,0.12);
}

.stat-icon {
    font-size: 2.2rem;
    margin-right: 1rem;
    width: 60px;
    height: 60px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 12px;
}

.stat-icon.total { background: #e3f2fd; color: #1976d2; }
.stat-icon.pending { background: #fff3e0; color: #f57c00; }
.stat-icon.processing { background: #e8f5e8; color: #4CAF50; }
.stat-icon.shipped { background: #e3f2fd; color: #2196f3; }
.stat-icon.delivered { background: #e8f5e8; color: #4CAF50; }

.stat-info h3 {
    margin: 0 0 0.25rem 0;
    font-size: 1.8rem;
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
    margin-bottom: 2rem;
    overflow: hidden;
    border: 1px solid #f1f3f4;
}

.card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 1.5rem 2rem;
    border-bottom: 1px solid #e9ecef;
    background: #fafbfc;
}

.card-header h3 {
    margin: 0;
    color: #2c3e50;
    font-size: 1.3rem;
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
    padding: 0.5rem 1rem 0.5rem 2.5rem;
    border: 1px solid #ddd;
    border-radius: 6px;
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
    padding: 0;
}

/* Table Styles */
.table-container {
    overflow-x: auto;
}

.data-table {
    width: 100%;
    border-collapse: collapse;
    background: white;
}

.data-table th,
.data-table td {
    padding: 1rem 1.5rem;
    text-align: left;
    border-bottom: 1px solid #e9ecef;
}

.data-table th {
    background: #f8f9fa;
    font-weight: 600;
    color: #495057;
    font-size: 0.9rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    border-bottom: 2px solid #dee2e6;
}

.data-table tr {
    transition: background 0.3s;
}

.data-table tr:hover {
    background: #f8f9fa;
}

.order-row:hover {
    background: #f8f9fa !important;
}

/* Order ID */
.order-id {
    font-weight: 700;
    color: #2c3e50;
    font-family: 'Courier New', monospace;
}

/* Customer Info */
.customer-info {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
}

.customer-info strong {
    color: #2c3e50;
    font-weight: 600;
}

.customer-location {
    font-size: 0.85rem;
    color: #6c757d;
}

/* Amount */
.amount {
    font-weight: 700;
    color: #4CAF50;
    font-size: 1.1rem;
}

/* Status Form */
.status-form {
    margin: 0;
}

.status-select {
    padding: 0.5rem 0.75rem;
    border: 1px solid #ddd;
    border-radius: 6px;
    font-size: 0.85rem;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.3s;
    min-width: 120px;
}

.status-select:focus {
    outline: none;
    border-color: #4CAF50;
    box-shadow: 0 0 0 2px rgba(76, 175, 80, 0.1);
}

/* Status Colors */
.status-select.status-pending { border-left: 3px solid #ffc107; }
.status-select.status-processing { border-left: 3px solid #17a2b8; }
.status-select.status-shipped { border-left: 3px solid #007bff; }
.status-select.status-delivered { border-left: 3px solid #28a745; }
.status-select.status-cancelled { border-left: 3px solid #dc3545; }

/* Order Date */
.order-date {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
}

.date {
    font-weight: 500;
    color: #2c3e50;
}

.time {
    font-size: 0.85rem;
    color: #6c757d;
}

/* Action Buttons */
.action-buttons {
    display: flex;
    gap: 0.5rem;
}

.btn {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.5rem 1rem;
    border: none;
    border-radius: 6px;
    text-decoration: none;
    font-size: 0.85rem;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.3s;
    text-align: center;
}

.btn-sm {
    padding: 0.4rem 0.8rem;
    font-size: 0.8rem;
}

.btn-primary {
    background: #4CAF50;
    color: white;
}

.btn-primary:hover {
    background: #45a049;
    transform: translateY(-1px);
    box-shadow: 0 2px 8px rgba(76, 175, 80, 0.3);
}

.btn-secondary {
    background: #6c757d;
    color: white;
}

.btn-secondary:hover {
    background: #5a6268;
    transform: translateY(-1px);
    box-shadow: 0 2px 8px rgba(108, 117, 125, 0.3);
}

.btn-icon {
    font-size: 0.9rem;
}

/* Pagination */
.pagination {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 1.5rem 2rem;
    border-top: 1px solid #e9ecef;
    background: #fafbfc;
}

.page-numbers {
    display: flex;
    gap: 0.5rem;
}

.page-link {
    padding: 0.5rem 0.75rem;
    border: 1px solid #dee2e6;
    border-radius: 6px;
    text-decoration: none;
    color: #495057;
    font-weight: 500;
    transition: all 0.3s;
    min-width: 40px;
    text-align: center;
}

.page-link:hover {
    background: #e9ecef;
    border-color: #adb5bd;
}

.page-link.active {
    background: #4CAF50;
    color: white;
    border-color: #4CAF50;
}

.page-link.prev,
.page-link.next {
    background: white;
    color: #495057;
    font-weight: 500;
}

.page-link.prev:hover,
.page-link.next:hover {
    background: #4CAF50;
    color: white;
    border-color: #4CAF50;
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
    margin: 0 0 1rem 0;
    color: #495057;
    font-weight: 600;
}

.empty-state p {
    margin: 0;
    font-size: 1rem;
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
        grid-template-columns: repeat(2, 1fr);
    }
    
    .card-header {
        flex-direction: column;
        gap: 1rem;
        align-items: flex-start;
    }
    
    .search-box input {
        width: 200px;
    }
    
    .action-buttons {
        flex-direction: column;
    }
    
    .pagination {
        flex-direction: column;
        gap: 1rem;
    }
}

@media (max-width: 480px) {
    .admin-content {
        padding: 1rem;
    }
    
    .stats-grid {
        grid-template-columns: 1fr;
    }
    
    .search-box input {
        width: 100%;
    }
    
    .data-table {
        font-size: 0.8rem;
    }
    
    .data-table th,
    .data-table td {
        padding: 0.75rem 0.5rem;
    }
}
</style>

<script>
// Simple search functionality
document.getElementById('searchOrders')?.addEventListener('input', function(e) {
    const searchTerm = e.target.value.toLowerCase();
    const rows = document.querySelectorAll('.order-row');
    
    rows.forEach(row => {
        const text = row.textContent.toLowerCase();
        row.style.display = text.includes(searchTerm) ? '' : 'none';
    });
});

// Add loading state to status forms
document.querySelectorAll('.status-form').forEach(form => {
    form.addEventListener('submit', function() {
        const select = this.querySelector('select');
        select.disabled = true;
        select.style.opacity = '0.7';
    });
});
</script>

<?php include '../includes/footer.php'; ?>