<?php
include 'includes/config.php';
include 'admin-header.php';

if (!isset($_GET['order_id'])) {
    header("Location: index.php");
    exit;
}

$order_id = $_GET['order_id'];
$user_id = $_SESSION['user_id'];

$sql = "SELECT o.*, u.name as customer_name FROM orders o 
        JOIN users u ON o.user_id = u.id 
        WHERE o.id = ? AND o.user_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $order_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();
$order = $result->fetch_assoc();

if (!$order) {
    echo "<div class='container'><p>Order not found.</p></div>";
    include 'includes/footer.php';
    exit;
}
?>

<div class="container">
    <div class="order-confirmation">
        <div class="confirmation-header">
            <h1>🎉 Order Confirmed!</h1>
            <p>Thank you for your purchase. Your order has been received.</p>
        </div>
        
        <div class="order-details">
            <h2>Order Details</h2>
            <div class="detail-grid">
                <div class="detail-item">
                    <strong>Order ID:</strong>
                    <span>#<?php echo $order['id']; ?></span>
                </div>
                <div class="detail-item">
                    <strong>Order Date:</strong>
                    <span><?php echo date('F j, Y g:i A', strtotime($order['created_at'])); ?></span>
                </div>
                <div class="detail-item">
                    <strong>Total Amount:</strong>
                    <span>$<?php echo number_format($order['total_amount'], 2); ?></span>
                </div>
                <div class="detail-item">
                    <strong>Payment Method:</strong>
                    <span><?php echo ucwords(str_replace('_', ' ', $order['payment_method'])); ?></span>
                </div>
                <div class="detail-item">
                    <strong>Status:</strong>
                    <span class="status-<?php echo $order['status']; ?>"><?php echo ucfirst($order['status']); ?></span>
                </div>
            </div>
        </div>
        
        <div class="shipping-info">
            <h2>Shipping Information</h2>
            <div class="shipping-details">
                <p><strong><?php echo htmlspecialchars($order['shipping_name']); ?></strong></p>
                <p><?php echo htmlspecialchars($order['shipping_address']); ?></p>
                <p><?php echo htmlspecialchars($order['shipping_city']) . ', ' . htmlspecialchars($order['shipping_zip_code']); ?></p>
                <p>Phone: <?php echo htmlspecialchars($order['shipping_phone']); ?></p>
            </div>
        </div>
        
        <div class="confirmation-actions">
            <a href="products.php" class="btn btn-primary">Continue Shopping</a>
            <a href="orders.php" class="btn btn-secondary">View My Orders</a>
        </div>
    </div>
</div>

<style>
.order-confirmation {
    max-width: 800px;
    margin: 0 auto;
    background: white;
    padding: 2rem;
    border-radius: 10px;
    box-shadow: 0 5px 15px rgba(0,0,0,0.1);
}

.confirmation-header {
    text-align: center;
    margin-bottom: 2rem;
    padding-bottom: 1rem;
    border-bottom: 2px solid #4CAF50;
}

.confirmation-header h1 {
    color: #4CAF50;
    margin-bottom: 0.5rem;
}

.order-details, .shipping-info {
    margin-bottom: 2rem;
}

.detail-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1rem;
    margin-top: 1rem;
}

.detail-item {
    display: flex;
    justify-content: space-between;
    padding: 0.75rem;
    background: #f8f9fa;
    border-radius: 5px;
}

.status-pending { color: #ffc107; font-weight: bold; }
.status-processing { color: #17a2b8; font-weight: bold; }
.status-shipped { color: #007bff; font-weight: bold; }
.status-delivered { color: #28a745; font-weight: bold; }

.shipping-details {
    background: #f8f9fa;
    padding: 1rem;
    border-radius: 5px;
    margin-top: 1rem;
}

.confirmation-actions {
    display: flex;
    gap: 1rem;
    justify-content: center;
    flex-wrap: wrap;
}

.btn {
    padding: 0.75rem 1.5rem;
    text-decoration: none;
    border-radius: 5px;
    font-weight: 500;
    transition: all 0.3s;
}

.btn-primary {
    background: #4CAF50;
    color: white;
}

.btn-primary:hover {
    background: #45a049;
    transform: translateY(-2px);
}

.btn-secondary {
    background: #6c757d;
    color: white;
}

.btn-secondary:hover {
    background: #5a6268;
}
</style>

<?php include 'includes/footer.php'; ?>