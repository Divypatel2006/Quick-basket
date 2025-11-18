<?php
include '../includes/config.php';
include 'admin-header.php';

// Check if user is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: ../login.php");
    exit;
}

// Set default date range (last 30 days)
$end_date = date('Y-m-d');
$start_date = date('Y-m-d', strtotime('-30 days'));

// Handle date filter
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['date_range'])) {
    $start_date = $_POST['start_date'];
    $end_date = $_POST['end_date'];
}

// Get sales statistics
$sales_stats = [
    'total_revenue' => 0,
    'total_orders' => 0,
    'average_order_value' => 0,
    'total_customers' => 0
];

try {
    // Total Revenue
    $revenue_sql = "SELECT SUM(total_amount) as total FROM orders WHERE status = 'delivered' AND created_at BETWEEN ? AND ?";
    $stmt = $conn->prepare($revenue_sql);
    $stmt->bind_param("ss", $start_date, $end_date);
    $stmt->execute();
    $revenue_result = $stmt->get_result();
    $sales_stats['total_revenue'] = $revenue_result->fetch_assoc()['total'] ?? 0;

    // Total Orders
    $orders_sql = "SELECT COUNT(*) as total FROM orders WHERE created_at BETWEEN ? AND ?";
    $stmt = $conn->prepare($orders_sql);
    $stmt->bind_param("ss", $start_date, $end_date);
    $stmt->execute();
    $orders_result = $stmt->get_result();
    $sales_stats['total_orders'] = $orders_result->fetch_assoc()['total'] ?? 0;

    // Average Order Value
    if ($sales_stats['total_orders'] > 0) {
        $sales_stats['average_order_value'] = $sales_stats['total_revenue'] / $sales_stats['total_orders'];
    }

    // Total Customers
    $customers_sql = "SELECT COUNT(DISTINCT user_id) as total FROM orders WHERE created_at BETWEEN ? AND ?";
    $stmt = $conn->prepare($customers_sql);
    $stmt->bind_param("ss", $start_date, $end_date);
    $stmt->execute();
    $customers_result = $stmt->get_result();
    $sales_stats['total_customers'] = $customers_result->fetch_assoc()['total'] ?? 0;

} catch (Exception $e) {
    error_log("Error fetching sales stats: " . $e->getMessage());
}

// Get top selling products
$top_products = [];
try {
    $top_products_sql = "
        SELECT p.name, p.price, SUM(oi.quantity) as total_sold, SUM(oi.quantity * oi.price) as revenue
        FROM order_items oi 
        JOIN products p ON oi.product_id = p.id 
        JOIN orders o ON oi.order_id = o.id 
        WHERE o.created_at BETWEEN ? AND ? 
        GROUP BY p.id 
        ORDER BY total_sold DESC 
        LIMIT 10
    ";
    $stmt = $conn->prepare($top_products_sql);
    $stmt->bind_param("ss", $start_date, $end_date);
    $stmt->execute();
    $top_products_result = $stmt->get_result();
    
    if ($top_products_result->num_rows > 0) {
        while($row = $top_products_result->fetch_assoc()) {
            $top_products[] = $row;
        }
    }
} catch (Exception $e) {
    error_log("Error fetching top products: " . $e->getMessage());
}

// Get sales by category
$category_sales = [];
try {
    $category_sales_sql = "
        SELECT c.name, COUNT(oi.id) as items_sold, SUM(oi.quantity * oi.price) as revenue
        FROM order_items oi 
        JOIN products p ON oi.product_id = p.id 
        JOIN categories c ON p.category_id = c.id 
        JOIN orders o ON oi.order_id = o.id 
        WHERE o.created_at BETWEEN ? AND ? 
        GROUP BY c.id 
        ORDER BY revenue DESC
    ";
    $stmt = $conn->prepare($category_sales_sql);
    $stmt->bind_param("ss", $start_date, $end_date);
    $stmt->execute();
    $category_sales_result = $stmt->get_result();
    
    if ($category_sales_result->num_rows > 0) {
        while($row = $category_sales_result->fetch_assoc()) {
            $category_sales[] = $row;
        }
    }
} catch (Exception $e) {
    error_log("Error fetching category sales: " . $e->getMessage());
}

// Get order status distribution
$order_status = [];
try {
    $status_sql = "
        SELECT status, COUNT(*) as count 
        FROM orders 
        WHERE created_at BETWEEN ? AND ? 
        GROUP BY status 
        ORDER BY count DESC
    ";
    $stmt = $conn->prepare($status_sql);
    $stmt->bind_param("ss", $start_date, $end_date);
    $stmt->execute();
    $status_result = $stmt->get_result();
    
    if ($status_result->num_rows > 0) {
        while($row = $status_result->fetch_assoc()) {
            $order_status[] = $row;
        }
    }
} catch (Exception $e) {
    error_log("Error fetching order status: " . $e->getMessage());
}

// Get daily sales data for chart
$daily_sales = [];
try {
    $daily_sales_sql = "
        SELECT DATE(created_at) as date, SUM(total_amount) as revenue, COUNT(*) as orders
        FROM orders 
        WHERE created_at BETWEEN ? AND ? AND status = 'delivered'
        GROUP BY DATE(created_at) 
        ORDER BY date
    ";
    $stmt = $conn->prepare($daily_sales_sql);
    $stmt->bind_param("ss", $start_date, $end_date);
    $stmt->execute();
    $daily_sales_result = $stmt->get_result();
    
    if ($daily_sales_result->num_rows > 0) {
        while($row = $daily_sales_result->fetch_assoc()) {
            $daily_sales[] = $row;
        }
    }
} catch (Exception $e) {
    error_log("Error fetching daily sales: " . $e->getMessage());
}

// Get low stock products
$low_stock_products = [];
try {
    $low_stock_sql = "SELECT name, stock_quantity, price FROM products WHERE stock_quantity > 0 AND stock_quantity < 10 ORDER BY stock_quantity ASC LIMIT 10";
    $low_stock_result = $conn->query($low_stock_sql);
    
    if ($low_stock_result->num_rows > 0) {
        while($row = $low_stock_result->fetch_assoc()) {
            $low_stock_products[] = $row;
        }
    }
} catch (Exception $e) {
    error_log("Error fetching low stock products: " . $e->getMessage());
}

// Get customer statistics
$customer_stats = [
    'total_customers' => 0,
    'new_customers' => 0,
    'returning_customers' => 0
];

try {
    // Total customers
    $total_customers_sql = "SELECT COUNT(*) as total FROM users WHERE role = 'customer'";
    $total_customers_result = $conn->query($total_customers_sql);
    $customer_stats['total_customers'] = $total_customers_result->fetch_assoc()['total'] ?? 0;

    // New customers in date range
    $new_customers_sql = "SELECT COUNT(*) as total FROM users WHERE role = 'customer' AND created_at BETWEEN ? AND ?";
    $stmt = $conn->prepare($new_customers_sql);
    $stmt->bind_param("ss", $start_date, $end_date);
    $stmt->execute();
    $new_customers_result = $stmt->get_result();
    $customer_stats['new_customers'] = $new_customers_result->fetch_assoc()['total'] ?? 0;

} catch (Exception $e) {
    error_log("Error fetching customer stats: " . $e->getMessage());
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
                    <a href="categories.php" class="nav-link">
                        <span class="nav-icon">🏷️</span>
                        <span class="nav-text">Categories</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="orders.php" class="nav-link">
                        <span class="nav-icon">🛒</span>
                        <span class="nav-text">Orders</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="users.php" class="nav-link">
                        <span class="nav-icon">👥</span>
                        <span class="nav-text">Users</span>
                    </a>
                </li>
                <li class="nav-item active">
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
            </ul>
        </nav>
    </div>

    <!-- Main Content -->
    <div class="admin-main">
        <div class="admin-header">
            <h1>Analytics & Reports</h1>
            <div class="admin-user">
                <span>Welcome, <?php echo $_SESSION['user_name']; ?></span>
                <div class="user-dropdown">
                    <a href="../profile.php">Profile</a>
                    <a href="../logout.php">Logout</a>
                </div>
            </div>
        </div>

        <div class="admin-content">
            <!-- Date Range Filter -->
            <div class="card">
                <div class="card-header">
                    <h3>Date Range Filter</h3>
                </div>
                <div class="card-body">
                    <form method="POST" action="" class="date-filter-form">
                        <div class="form-row">
                            <div class="form-group">
                                <label for="start_date">Start Date</label>
                                <input type="date" id="start_date" name="start_date" value="<?php echo $start_date; ?>" required>
                            </div>
                            <div class="form-group">
                                <label for="end_date">End Date</label>
                                <input type="date" id="end_date" name="end_date" value="<?php echo $end_date; ?>" required>
                            </div>
                            <div class="form-group">
                                <label>&nbsp;</label>
                                <button type="submit" name="date_range" class="btn btn-primary">
                                    <span class="btn-icon">🔍</span>
                                    Apply Filter
                                </button>
                                <button type="button" onclick="resetDateFilter()" class="btn btn-secondary">
                                    <span class="btn-icon">🔄</span>
                                    Reset
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Sales Overview -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon revenue">💰</div>
                    <div class="stat-info">
                        <h3>$<?php echo number_format($sales_stats['total_revenue'], 2); ?></h3>
                        <p>Total Revenue</p>
                        <small>From <?php echo $sales_stats['total_orders']; ?> orders</small>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon orders">🛒</div>
                    <div class="stat-info">
                        <h3><?php echo $sales_stats['total_orders']; ?></h3>
                        <p>Total Orders</p>
                        <small><?php echo $sales_stats['total_customers']; ?> unique customers</small>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon average">📊</div>
                    <div class="stat-info">
                        <h3>$<?php echo number_format($sales_stats['average_order_value'], 2); ?></h3>
                        <p>Average Order Value</p>
                        <small>Per order</small>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon customers">👥</div>
                    <div class="stat-info">
                        <h3><?php echo $customer_stats['total_customers']; ?></h3>
                        <p>Total Customers</p>
                        <small><?php echo $customer_stats['new_customers']; ?> new this period</small>
                    </div>
                </div>
            </div>

            <div class="reports-layout">
                <!-- Left Column -->
                <div class="left-column">
                    <!-- Top Selling Products -->
                    <div class="card">
                        <div class="card-header">
                            <h3>Top Selling Products</h3>
                        </div>
                        <div class="card-body">
                            <?php if (empty($top_products)): ?>
                                <div class="empty-state">
                                    <div class="empty-icon">📦</div>
                                    <h3>No Sales Data</h3>
                                    <p>No products sold in the selected period.</p>
                                </div>
                            <?php else: ?>
                                <div class="products-list">
                                    <?php foreach($top_products as $index => $product): ?>
                                    <div class="product-item">
                                        <div class="product-rank">#<?php echo $index + 1; ?></div>
                                        <div class="product-info">
                                            <h4><?php echo htmlspecialchars($product['name']); ?></h4>
                                            <div class="product-stats">
                                                <span class="sold"><?php echo $product['total_sold']; ?> sold</span>
                                                <span class="revenue">$<?php echo number_format($product['revenue'], 2); ?> revenue</span>
                                            </div>
                                        </div>
                                        <div class="product-price">
                                            $<?php echo number_format($product['price'], 2); ?>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Sales by Category -->
                    <div class="card">
                        <div class="card-header">
                            <h3>Sales by Category</h3>
                        </div>
                        <div class="card-body">
                            <?php if (empty($category_sales)): ?>
                                <div class="empty-state">
                                    <div class="empty-icon">🏷️</div>
                                    <h3>No Category Data</h3>
                                    <p>No sales data by category in the selected period.</p>
                                </div>
                            <?php else: ?>
                                <div class="category-sales">
                                    <?php foreach($category_sales as $category): ?>
                                    <div class="category-item">
                                        <div class="category-name"><?php echo htmlspecialchars($category['name']); ?></div>
                                        <div class="category-stats">
                                            <span class="items"><?php echo $category['items_sold']; ?> items</span>
                                            <span class="revenue">$<?php echo number_format($category['revenue'], 2); ?></span>
                                        </div>
                                        <div class="progress-bar">
                                            <div class="progress-fill" style="width: <?php echo min(100, ($category['revenue'] / max(1, $sales_stats['total_revenue'])) * 100); ?>%"></div>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Right Column -->
                <div class="right-column">
                    <!-- Order Status Distribution -->
                    <div class="card">
                        <div class="card-header">
                            <h3>Order Status Distribution</h3>
                        </div>
                        <div class="card-body">
                            <?php if (empty($order_status)): ?>
                                <div class="empty-state">
                                    <div class="empty-icon">🛒</div>
                                    <h3>No Orders</h3>
                                    <p>No orders in the selected period.</p>
                                </div>
                            <?php else: ?>
                                <div class="status-distribution">
                                    <?php 
                                    $total_orders = array_sum(array_column($order_status, 'count'));
                                    foreach($order_status as $status): 
                                        $percentage = ($status['count'] / $total_orders) * 100;
                                    ?>
                                    <div class="status-item">
                                        <div class="status-info">
                                            <span class="status-badge status-<?php echo $status['status']; ?>">
                                                <?php echo ucfirst($status['status']); ?>
                                            </span>
                                            <span class="status-count"><?php echo $status['count']; ?> orders</span>
                                        </div>
                                        <div class="status-percentage"><?php echo number_format($percentage, 1); ?>%</div>
                                        <div class="progress-bar">
                                            <div class="progress-fill" style="width: <?php echo $percentage; ?>%"></div>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Low Stock Alert -->
                    <div class="card">
                        <div class="card-header">
                            <h3>Low Stock Alert</h3>
                            <span class="badge badge-warning"><?php echo count($low_stock_products); ?> items</span>
                        </div>
                        <div class="card-body">
                            <?php if (empty($low_stock_products)): ?>
                                <div class="empty-state">
                                    <div class="empty-icon">✅</div>
                                    <h3>All Good!</h3>
                                    <p>No low stock products.</p>
                                </div>
                            <?php else: ?>
                                <div class="low-stock-list">
                                    <?php foreach($low_stock_products as $product): ?>
                                    <div class="stock-item">
                                        <div class="product-name"><?php echo htmlspecialchars($product['name']); ?></div>
                                        <div class="stock-info">
                                            <span class="stock-quantity low"><?php echo $product['stock_quantity']; ?> left</span>
                                            <span class="product-price">$<?php echo number_format($product['price'], 2); ?></span>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                                <div class="card-footer">
                                    <a href="products.php?filter=low_stock" class="btn btn-warning btn-sm">
                                        <span class="btn-icon">⚠️</span>
                                        View All Low Stock
                                    </a>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Quick Export -->
                    <div class="card">
                        <div class="card-header">
                            <h3>Export Reports</h3>
                        </div>
                        <div class="card-body">
                            <div class="export-actions">
                                <button onclick="exportReport('sales')" class="btn btn-secondary btn-block">
                                    <span class="btn-icon">📊</span>
                                    Export Sales Report
                                </button>
                                <button onclick="exportReport('products')" class="btn btn-secondary btn-block">
                                    <span class="btn-icon">📦</span>
                                    Export Products Report
                                </button>
                                <button onclick="exportReport('customers')" class="btn btn-secondary btn-block">
                                    <span class="btn-icon">👥</span>
                                    Export Customers Report
                                </button>
                                <button onclick="printReport()" class="btn btn-primary btn-block">
                                    <span class="btn-icon">🖨️</span>
                                    Print Report
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Sales Chart Section -->
            <div class="card">
                <div class="card-header">
                    <h3>Sales Trend</h3>
                </div>
                <div class="card-body">
                    <?php if (empty($daily_sales)): ?>
                        <div class="empty-state">
                            <div class="empty-icon">📈</div>
                            <h3>No Sales Data</h3>
                            <p>No sales data available for the chart.</p>
                        </div>
                    <?php else: ?>
                        <div class="sales-chart">
                            <div class="chart-container">
                                <canvas id="salesChart" width="400" height="200"></canvas>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
/* Reports Specific Styles */
.reports-layout {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 2rem;
    margin-bottom: 2rem;
}

.left-column, .right-column {
    display: flex;
    flex-direction: column;
    gap: 1.5rem;
}

/* Date Filter */
.date-filter-form .form-row {
    display: grid;
    grid-template-columns: 1fr 1fr auto;
    gap: 1rem;
    align-items: end;
}

/* Statistics Icons */
.stat-icon.revenue { background: linear-gradient(135deg, #4CAF50, #45a049); color: white; }
.stat-icon.average { background: linear-gradient(135deg, #2196f3, #1976d2); color: white; }
.stat-icon.customers { background: linear-gradient(135deg, #9c27b0, #7b1fa2); color: white; }

.stat-info small {
    display: block;
    color: #6c757d;
    font-size: 0.8rem;
    margin-top: 0.25rem;
}

/* Products List */
.products-list {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.product-item {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 1rem;
    background: #f8f9fa;
    border-radius: 8px;
    border: 1px solid #e9ecef;
}

.product-rank {
    background: #4CAF50;
    color: white;
    width: 30px;
    height: 30px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: bold;
    font-size: 0.8rem;
}

.product-info {
    flex: 1;
}

.product-info h4 {
    margin: 0 0 0.25rem 0;
    color: #2c3e50;
    font-size: 0.95rem;
}

.product-stats {
    display: flex;
    gap: 1rem;
    font-size: 0.8rem;
}

.product-stats .sold {
    color: #4CAF50;
    font-weight: 500;
}

.product-stats .revenue {
    color: #6c757d;
}

.product-price {
    font-weight: bold;
    color: #4CAF50;
    font-size: 1.1rem;
}

/* Category Sales */
.category-sales {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.category-item {
    padding: 0.75rem;
    border: 1px solid #e9ecef;
    border-radius: 8px;
}

.category-name {
    font-weight: 600;
    color: #2c3e50;
    margin-bottom: 0.5rem;
}

.category-stats {
    display: flex;
    justify-content: space-between;
    margin-bottom: 0.5rem;
    font-size: 0.85rem;
}

.category-stats .items {
    color: #6c757d;
}

.category-stats .revenue {
    color: #4CAF50;
    font-weight: 600;
}

/* Progress Bars */
.progress-bar {
    width: 100%;
    height: 6px;
    background: #e9ecef;
    border-radius: 3px;
    overflow: hidden;
}

.progress-fill {
    height: 100%;
    background: linear-gradient(135deg, #4CAF50, #45a049);
    border-radius: 3px;
    transition: width 0.3s ease;
}

/* Status Distribution */
.status-distribution {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.status-item {
    padding: 0.75rem;
    border: 1px solid #e9ecef;
    border-radius: 8px;
}

.status-info {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 0.5rem;
}

.status-badge {
    padding: 0.3rem 0.75rem;
    border-radius: 12px;
    font-size: 0.75rem;
    font-weight: 600;
    text-transform: uppercase;
}

.status-pending { background: #fff3cd; color: #856404; }
.status-processing { background: #cce7ff; color: #004085; }
.status-shipped { background: #d1ecf1; color: #0c5460; }
.status-delivered { background: #d4edda; color: #155724; }
.status-cancelled { background: #f8d7da; color: #721c24; }

.status-count {
    color: #6c757d;
    font-size: 0.85rem;
}

.status-percentage {
    text-align: right;
    font-weight: 600;
    color: #2c3e50;
    margin-bottom: 0.25rem;
}

/* Low Stock */
.low-stock-list {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
}

.stock-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0.75rem;
    background: #fff3cd;
    border: 1px solid #ffeaa7;
    border-radius: 6px;
}

.product-name {
    font-weight: 500;
    color: #2c3e50;
}

.stock-info {
    display: flex;
    gap: 1rem;
    align-items: center;
}

.stock-quantity.low {
    background: #dc3545;
    color: white;
    padding: 0.25rem 0.5rem;
    border-radius: 12px;
    font-size: 0.75rem;
    font-weight: 600;
}

.product-price {
    color: #6c757d;
    font-size: 0.9rem;
}

.card-footer {
    padding-top: 1rem;
    border-top: 1px solid #e9ecef;
    margin-top: 1rem;
}

/* Export Actions */
.export-actions {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
}

/* Sales Chart */
.sales-chart {
    padding: 1rem 0;
}

.chart-container {
    height: 300px;
    position: relative;
}

/* Badges */
.badge {
    padding: 0.25rem 0.5rem;
    border-radius: 12px;
    font-size: 0.75rem;
    font-weight: 600;
}

.badge-warning {
    background: #ffc107;
    color: #212529;
}

/* Responsive Design */
@media (max-width: 1024px) {
    .reports-layout {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 768px) {
    .date-filter-form .form-row {
        grid-template-columns: 1fr;
    }
    
    .product-item {
        flex-direction: column;
        align-items: flex-start;
        text-align: left;
    }
    
    .product-stats {
        flex-direction: column;
        gap: 0.25rem;
    }
    
    .status-info {
        flex-direction: column;
        align-items: flex-start;
        gap: 0.5rem;
    }
    
    .stock-item {
        flex-direction: column;
        align-items: flex-start;
        gap: 0.5rem;
    }
}

@media (max-width: 480px) {
    .category-stats {
        flex-direction: column;
        gap: 0.25rem;
    }
}
</style>

<script>
// Reset date filter
function resetDateFilter() {
    const endDate = new Date().toISOString().split('T')[0];
    const startDate = new Date();
    startDate.setDate(startDate.getDate() - 30);
    
    document.getElementById('start_date').value = startDate.toISOString().split('T')[0];
    document.getElementById('end_date').value = endDate;
    document.querySelector('.date-filter-form').submit();
}

// Export reports
function exportReport(type) {
    const startDate = document.getElementById('start_date').value;
    const endDate = document.getElementById('end_date').value;
    
    // In a real implementation, this would generate and download a CSV/PDF
    alert(`Exporting ${type} report from ${startDate} to ${endDate}\n\nThis would generate a downloadable file in a real implementation.`);
    
    // Example of actual implementation:
    // window.location.href = `export.php?type=${type}&start_date=${startDate}&end_date=${endDate}`;
}

// Print report
function printReport() {
    window.print();
}

// Sales Chart (using Chart.js - you'll need to include Chart.js library)
document.addEventListener('DOMContentLoaded', function() {
    const salesChart = document.getElementById('salesChart');
    if (salesChart) {
        const ctx = salesChart.getContext('2d');
        
        // Sample chart data - in real implementation, use PHP data
        const chartData = {
            labels: <?php echo json_encode(array_column($daily_sales, 'date')); ?>,
            datasets: [{
                label: 'Daily Revenue',
                data: <?php echo json_encode(array_column($daily_sales, 'revenue')); ?>,
                borderColor: '#4CAF50',
                backgroundColor: 'rgba(76, 175, 80, 0.1)',
                borderWidth: 2,
                fill: true
            }]
        };
        
        // Create chart
        new Chart(ctx, {
            type: 'line',
            data: chartData,
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return '$' + value;
                            }
                        }
                    }
                },
                plugins: {
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return 'Revenue: $' + context.parsed.y.toFixed(2);
                            }
                        }
                    }
                }
            }
        });
    }
});

// Add loading states
document.querySelectorAll('form').forEach(form => {
    form.addEventListener('submit', function() {
        const submitBtn = this.querySelector('button[type="submit"]');
        if (submitBtn) {
            submitBtn.innerHTML = '<span class="btn-loading"></span> Loading...';
            submitBtn.disabled = true;
        }
    });
});
</script>

<?php include 'admin-footer.php'; ?>