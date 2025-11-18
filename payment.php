<?php
include 'includes/config.php';
include 'includes/header.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// Check if order ID is provided
if (!isset($_GET['order_id'])) {
    header("Location: cart.php");
    exit;
}

$order_id = $_GET['order_id'];
$user_id = $_SESSION['user_id'];

// Verify order belongs to user
$sql = "SELECT * FROM orders WHERE id = ? AND user_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $order_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 0) {
    echo "<div class='container'><p>Order not found.</p></div>";
    include 'includes/footer.php';
    exit;
}

$order = $result->fetch_assoc();

// Process payment
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // In a real application, you would integrate with a payment gateway here
    // For this example, we'll just update the order status
    
    $sql = "UPDATE orders SET status = 'processing' WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $order_id);
    $stmt->execute();
    
    echo "<div class='container'><h2>Payment Successful!</h2>";
    echo "<p>Thank you for your order. Your order ID is: " . $order_id . "</p>";
    echo "<p>We'll process your order and ship it soon.</p>";
    echo "<a href='index.php' class='btn'>Continue Shopping</a></div>";
    
    include 'includes/footer.php';
    exit;
}
?>

<div class="container">
    <h1>Payment</h1>
    
    <div class="payment-summary">
        <h2>Order Summary</h2>
        <p>Order ID: <?php echo $order_id; ?></p>
        <p>Total Amount: $<?php echo number_format($order['total_amount'], 2); ?></p>
    </div>
    
    <div class="payment-form">
        <h2>Payment Information</h2>
        <form method="POST" action="">
            <div class="form-group">
                <label for="card_number">Card Number</label>
                <input type="text" id="card_number" name="card_number" placeholder="1234 5678 9012 3456" required>
            </div>
            <div class="form-group">
                <label for="expiry_date">Expiry Date</label>
                <input type="text" id="expiry_date" name="expiry_date" placeholder="MM/YY" required>
            </div>
            <div class="form-group">
                <label for="cvv">CVV</label>
                <input type="text" id="cvv" name="cvv" placeholder="123" required>
            </div>
            <div class="form-group">
                <label for="card_name">Name on Card</label>
                <input type="text" id="card_name" name="card_name" required>
            </div>
            <button type="submit" class="btn">Pay $<?php echo number_format($order['total_amount'], 2); ?></button>
        </form>
    </div>
</div>

<?php include 'includes/footer.php'; ?>