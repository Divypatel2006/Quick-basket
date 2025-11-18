<?php
include '../includes/config.php'; // Fixed path
include 'admin-header.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php"); // Fixed path
    exit;
}

if (!isset($_GET['id'])) {
    header("Location: orders.php");
    exit;
}

$order_id = $_GET['id'];
$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['role'];

// Fetch order details
if ($user_role == 'admin') {
    // Admin can view any order
    $sql = "SELECT o.*, u.name as customer_name, u.email as customer_email 
            FROM orders o 
            JOIN users u ON o.user_id = u.id 
            WHERE o.id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $order_id);
} else {
    // Regular users can only view their own orders
    $sql = "SELECT o.*, u.name as customer_name, u.email as customer_email 
            FROM orders o 
            JOIN users u ON o.user_id = u.id 
            WHERE o.id = ? AND o.user_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $order_id, $user_id);
}

$stmt->execute();
$result = $stmt->get_result();
$order = $result->fetch_assoc();

if (!$order) {
    echo "<div class='container'><div class='alert alert-error'>Order not found or you don't have permission to view this order.</div></div>";
    include 'admin-footer.php'; // Fixed to use admin footer
    exit;
}

// Fetch order items
$sql = "SELECT oi.*, p.name as product_name, p.image, p.description as product_description
        FROM order_items oi 
        JOIN products p ON oi.product_id = p.id 
        WHERE oi.order_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $order_id);
$stmt->execute();
$items_result = $stmt->get_result();
$order_items = [];

if ($items_result->num_rows > 0) {
    while ($row = $items_result->fetch_assoc()) {
        $order_items[] = $row;
    }
}

// Calculate subtotal from items
$subtotal = 0;
foreach ($order_items as $item) {
    $subtotal += $item['price'] * $item['quantity'];
}

$tax_rate = 0.1; // 10% tax
$tax_amount = $subtotal * $tax_rate;
$shipping_cost = 0.00; // Free shipping
$final_total = $subtotal + $tax_amount + $shipping_cost;
?>

<div class="admin-container">
    <div class="admin-main">
        <div class="admin-header">
            <div class="header-left">
                <a href="orders.php" class="back-btn">
                    <span class="back-icon">←</span>
                    Back to Orders
                </a>
                <h1>Order Details</h1>
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

            <!-- Order Status Banner -->
            <div class="status-banner status-<?php echo $order['status']; ?>">
                <div class="status-content">
                    <div class="status-info">
                        <h3>Order #<?php echo $order['id']; ?></h3>
                        <p>Placed on <?php echo date('F j, Y \a\t g:i A', strtotime($order['created_at'])); ?></p>
                    </div>
                    <div class="status-badge">
                        <span class="status-text"><?php echo ucfirst($order['status']); ?></span>
                    </div>
                </div>
            </div>

            <div class="order-details-layout">
                <!-- Left Column - Order Summary -->
                <div class="order-summary-column">
                    <!-- Order Items -->
                    <div class="order-section">
                        <h2>Order Items</h2>
                        <div class="order-items-list">
                            <?php foreach ($order_items as $item): ?>
                                <div class="order-item-card">
                                    <div class="item-image">
                                        <?php if ($item['image']): ?>
                                            <img src="../images/products/<?php echo $item['image']; ?>" 
                                                 alt="<?php echo htmlspecialchars($item['product_name']); ?>">
                                        <?php else: ?>
                                            <div class="no-image">No Image</div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="item-details">
                                        <h4><?php echo htmlspecialchars($item['product_name']); ?></h4>
                                        <p class="item-description"><?php echo htmlspecialchars(substr($item['product_description'], 0, 100)); ?>...</p>
                                        <div class="item-meta">
                                            <span class="item-price">$<?php echo number_format($item['price'], 2); ?></span>
                                            <span class="item-quantity">Qty: <?php echo $item['quantity']; ?></span>
                                        </div>
                                    </div>
                                    <div class="item-total">
                                        $<?php echo number_format($item['price'] * $item['quantity'], 2); ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Order Totals -->
                    <div class="order-section">
                        <h2>Order Summary</h2>
                        <div class="order-totals">
                            <div class="total-row">
                                <span>Subtotal:</span>
                                <span>$<?php echo number_format($subtotal, 2); ?></span>
                            </div>
                            <div class="total-row">
                                <span>Shipping:</span>
                                <span>$<?php echo number_format($shipping_cost, 2); ?></span>
                            </div>
                            <div class="total-row">
                                <span>Tax (10%):</span>
                                <span>$<?php echo number_format($tax_amount, 2); ?></span>
                            </div>
                            <div class="total-row grand-total">
                                <span><strong>Total:</strong></span>
                                <span><strong>$<?php echo number_format($final_total, 2); ?></strong></span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Right Column - Order Information -->
                <div class="order-info-column">
                    <!-- Customer Information -->
                    <div class="info-card">
                        <h3>Customer Information</h3>
                        <div class="info-content">
                            <div class="info-item">
                                <label>Name:</label>
                                <span><?php echo htmlspecialchars($order['customer_name']); ?></span>
                            </div>
                            <div class="info-item">
                                <label>Email:</label>
                                <span><?php echo htmlspecialchars($order['customer_email']); ?></span>
                            </div>
                            <div class="info-item">
                                <label>Order Date:</label>
                                <span><?php echo date('F j, Y \a\t g:i A', strtotime($order['created_at'])); ?></span>
                            </div>
                            <div class="info-item">
                                <label>Order Total:</label>
                                <span><strong>$<?php echo number_format($order['total_amount'], 2); ?></strong></span>
                            </div>
                        </div>
                    </div>

                    <!-- Order Actions -->
                    <div class="info-card">
                        <h3>Order Actions</h3>
                        <div class="action-buttons-vertical">
                            <button onclick="window.print()" class="btn btn-secondary">
                                <span class="btn-icon">🖨️</span>
                                Print Invoice
                            </button>
                            <?php if ($order['status'] == 'pending' || $order['status'] == 'processing'): ?>
                                <button onclick="contactSupport()" class="btn btn-secondary">
                                    <span class="btn-icon">📞</span>
                                    Contact Support
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Admin Actions -->
                    <?php if ($user_role == 'admin'): ?>
                    <div class="info-card admin-actions">
                        <h3>Admin Actions</h3>
                        <form method="POST" action="update-order-status.php" class="status-update-form">
                            <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                            <div class="form-group">
                                <label for="status">Update Status:</label>
                                <select name="status" id="status" class="status-select">
                                    <option value="pending" <?php echo $order['status'] == 'pending' ? 'selected' : ''; ?>>Pending</option>
                                    <option value="processing" <?php echo $order['status'] == 'processing' ? 'selected' : ''; ?>>Processing</option>
                                    <option value="shipped" <?php echo $order['status'] == 'shipped' ? 'selected' : ''; ?>>Shipped</option>
                                    <option value="delivered" <?php echo $order['status'] == 'delivered' ? 'selected' : ''; ?>>Delivered</option>
                                    <option value="cancelled" <?php echo $order['status'] == 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                                </select>
                            </div>
                            <button type="submit" class="btn btn-primary">
                                <span class="btn-icon">🔄</span>
                                Update Status
                            </button>
                        </form>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Order Timeline -->
            <div class="order-section">
                <h2>Order Timeline</h2>
                <div class="order-timeline">
                    <div class="timeline-item <?php echo in_array($order['status'], ['pending', 'processing', 'shipped', 'delivered']) ? 'completed' : ''; ?>">
                        <div class="timeline-marker"></div>
                        <div class="timeline-content">
                            <h4>Order Placed</h4>
                            <p><?php echo date('M j, Y g:i A', strtotime($order['created_at'])); ?></p>
                        </div>
                    </div>
                    <div class="timeline-item <?php echo in_array($order['status'], ['processing', 'shipped', 'delivered']) ? 'completed' : ''; ?>">
                        <div class="timeline-marker"></div>
                        <div class="timeline-content">
                            <h4>Order Confirmed</h4>
                            <p>We've received your order</p>
                        </div>
                    </div>
                    <div class="timeline-item <?php echo in_array($order['status'], ['shipped', 'delivered']) ? 'completed' : ''; ?>">
                        <div class="timeline-marker"></div>
                        <div class="timeline-content">
                            <h4>Shipped</h4>
                            <p>Your order is on the way</p>
                        </div>
                    </div>
                    <div class="timeline-item <?php echo $order['status'] == 'delivered' ? 'completed' : ''; ?>">
                        <div class="timeline-marker"></div>
                        <div class="timeline-content">
                            <h4>Delivered</h4>
                            <p>Order delivered successfully</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
/* Order Details Page Styles */
.order-details-page {
    max-width: 1200px;
    margin: 0 auto;
}

.status-banner {
    border-radius: 12px;
    padding: 1.5rem;
    margin-bottom: 2rem;
    border-left: 4px solid;
}

.status-banner.pending {
    background: #fff3cd;
    border-left-color: #ffc107;
    color: #856404;
}

.status-banner.processing {
    background: #cce7ff;
    border-left-color: #17a2b8;
    color: #004085;
}

.status-banner.shipped {
    background: #d1ecf1;
    border-left-color: #007bff;
    color: #0c5460;
}

.status-banner.delivered {
    background: #d4edda;
    border-left-color: #28a745;
    color: #155724;
}

.status-banner.cancelled {
    background: #f8d7da;
    border-left-color: #dc3545;
    color: #721c24;
}

.status-content {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.status-badge {
    background: white;
    padding: 0.5rem 1rem;
    border-radius: 20px;
    font-weight: 600;
    font-size: 0.9rem;
}

/* Layout */
.order-details-layout {
    display: grid;
    grid-template-columns: 2fr 1fr;
    gap: 2rem;
    margin-bottom: 3rem;
}

.order-section {
    background: white;
    padding: 2rem;
    border-radius: 12px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.08);
    margin-bottom: 2rem;
    border: 1px solid #e9ecef;
}

.order-section h2 {
    margin-bottom: 1.5rem;
    color: #2c3e50;
    border-bottom: 2px solid #4CAF50;
    padding-bottom: 0.5rem;
}

/* Order Items */
.order-items-list {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.order-item-card {
    display: flex;
    gap: 1rem;
    padding: 1.5rem;
    border: 1px solid #e9ecef;
    border-radius: 8px;
    transition: all 0.3s ease;
}

.order-item-card:hover {
    border-color: #4CAF50;
    box-shadow: 0 2px 8px rgba(76, 175, 80, 0.1);
}

.item-image {
    flex-shrink: 0;
}

.item-image img {
    width: 80px;
    height: 80px;
    object-fit: cover;
    border-radius: 8px;
    border: 1px solid #e9ecef;
}

.no-image {
    width: 80px;
    height: 80px;
    background: #f8f9fa;
    border: 1px dashed #e9ecef;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #6c757d;
    font-size: 0.8rem;
}

.item-details {
    flex: 1;
    min-width: 0;
}

.item-details h4 {
    margin: 0 0 0.5rem 0;
    color: #2c3e50;
}

.item-description {
    color: #6c757d;
    font-size: 0.9rem;
    margin-bottom: 0.5rem;
    line-height: 1.4;
}

.item-meta {
    display: flex;
    gap: 1rem;
    font-size: 0.9rem;
}

.item-price {
    font-weight: 600;
    color: #4CAF50;
}

.item-quantity {
    color: #6c757d;
}

.item-total {
    font-weight: 700;
    font-size: 1.1rem;
    color: #2c3e50;
    align-self: center;
}

/* Order Totals */
.order-totals {
    border-top: 1px solid #e9ecef;
    padding-top: 1rem;
}

.total-row {
    display: flex;
    justify-content: space-between;
    padding: 0.75rem 0;
    border-bottom: 1px solid #e9ecef;
}

.total-row:last-child {
    border-bottom: none;
}

.grand-total {
    font-size: 1.2rem;
    font-weight: 700;
    color: #2c3e50;
    border-top: 2px solid #e9ecef;
    margin-top: 0.5rem;
    padding-top: 1rem;
}

/* Info Cards */
.info-card {
    background: white;
    padding: 1.5rem;
    border-radius: 12px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.08);
    margin-bottom: 1.5rem;
    border: 1px solid #e9ecef;
}

.info-card h3 {
    margin-bottom: 1rem;
    color: #2c3e50;
    border-bottom: 1px solid #e9ecef;
    padding-bottom: 0.5rem;
}

.info-content {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.info-item {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    padding: 0.5rem 0;
}

.info-item label {
    font-weight: 600;
    color: #2c3e50;
    min-width: 120px;
}

.info-item span {
    text-align: right;
    color: #6c757d;
}

/* Action Buttons */
.action-buttons-vertical {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
}

.action-buttons-vertical .btn {
    width: 100%;
    justify-content: center;
}

/* Admin Actions */
.admin-actions {
    border-left: 4px solid #4CAF50;
}

.status-update-form {
    margin-top: 1rem;
}

.status-select {
    width: 100%;
    padding: 0.75rem;
    border: 1px solid #e9ecef;
    border-radius: 8px;
    font-size: 0.9rem;
    margin-bottom: 1rem;
}

/* Order Timeline */
.order-timeline {
    position: relative;
    padding: 2rem 0;
}

.order-timeline::before {
    content: '';
    position: absolute;
    left: 30px;
    top: 0;
    bottom: 0;
    width: 2px;
    background: #e9ecef;
}

.timeline-item {
    display: flex;
    align-items: flex-start;
    margin-bottom: 2rem;
    position: relative;
}

.timeline-marker {
    width: 60px;
    height: 60px;
    border-radius: 50%;
    background: #f8f9fa;
    border: 2px solid #e9ecef;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-right: 1.5rem;
    flex-shrink: 0;
    position: relative;
    z-index: 2;
}

.timeline-item.completed .timeline-marker {
    background: #4CAF50;
    border-color: #4CAF50;
    color: white;
}

.timeline-item.completed .timeline-marker::after {
    content: '✓';
    font-weight: bold;
    font-size: 1.2rem;
}

.timeline-content {
    flex: 1;
    padding-top: 0.5rem;
}

.timeline-content h4 {
    margin: 0 0 0.25rem 0;
    color: #2c3e50;
}

.timeline-content p {
    margin: 0;
    color: #6c757d;
    font-size: 0.9rem;
}

/* Responsive Design */
@media (max-width: 968px) {
    .order-details-layout {
        grid-template-columns: 1fr;
        gap: 1.5rem;
    }
    
    .status-content {
        flex-direction: column;
        gap: 1rem;
        align-items: flex-start;
    }
}

@media (max-width: 768px) {
    .order-item-card {
        flex-direction: column;
        text-align: center;
    }
    
    .item-meta {
        justify-content: center;
    }
    
    .info-item {
        flex-direction: column;
        align-items: flex-start;
        gap: 0.25rem;
    }
    
    .info-item span {
        text-align: left;
    }
    
    .order-timeline::before {
        left: 25px;
    }
    
    .timeline-marker {
        width: 50px;
        height: 50px;
        margin-right: 1rem;
    }
}

@media (max-width: 480px) {
    .order-section {
        padding: 1.5rem;
    }
    
    .order-item-card {
        padding: 1rem;
    }
    
    .item-image img {
        width: 60px;
        height: 60px;
    }
}
</style>

<script>
function contactSupport() {
    alert('Please contact our support team at support@quickbasket.com or call +1 (555) 123-4567 for assistance with your order.');
}

// Auto-update timeline based on order status
document.addEventListener('DOMContentLoaded', function() {
    const status = '<?php echo $order['status']; ?>';
    const timelineItems = document.querySelectorAll('.timeline-item');
    
    // Add current status highlight
    timelineItems.forEach(item => {
        if (item.classList.contains('completed')) {
            item.classList.add('current');
        }
    });
});

// Print functionality
function printOrder() {
    window.print();
}
</script>

<?php include 'admin-footer.php'; ?>