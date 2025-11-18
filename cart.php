<?php
include 'includes/config.php';
include 'includes/header.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// Initialize cart if not exists
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

$cart = $_SESSION['cart'];
$total = 0;
?>

<div class="container">
    <h1>Shopping Cart</h1>
    
    <?php if(empty($cart)): ?>
        <p>Your cart is empty. <a href="products.php">Continue shopping</a></p>
    <?php else: ?>
        <div class="cart-items">
            <?php foreach($cart as $product_id => $item): ?>
                <?php
                // Get product details from database
                $sql = "SELECT * FROM products WHERE id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("i", $product_id);
                $stmt->execute();
                $result = $stmt->get_result();
                $product = $result->fetch_assoc();
                
                if ($product) {
                    $subtotal = $product['price'] * $item['quantity'];
                    $total += $subtotal;
                ?>
                <div class="cart-item">
                    <div class="item-info">
                        <h3><?php echo $product['name']; ?></h3>
                        <p>Price: $<?php echo number_format($product['price'], 2); ?></p>
                    </div>
                    <div class="item-quantity">
                        <form method="POST" action="update-cart.php" style="display: inline;">
                            <input type="hidden" name="product_id" value="<?php echo $product_id; ?>">
                            <input type="number" name="quantity" value="<?php echo $item['quantity']; ?>" min="1" max="<?php echo $product['stock_quantity']; ?>">
                            <button type="submit" class="btn">Update</button>
                        </form>
                    </div>
                    <div class="item-subtotal">
                        $<?php echo number_format($subtotal, 2); ?>
                    </div>
                    <div class="item-remove">
                        <a href="remove-from-cart.php?id=<?php echo $product_id; ?>" class="btn btn-danger">Remove</a>
                    </div>
                </div>
                <?php } ?>
            <?php endforeach; ?>
            
            <div class="cart-total">
                <h3>Total: $<?php echo number_format($total, 2); ?></h3>
            </div>
            
            <div class="cart-actions">
                <a href="products.php" class="btn">Continue Shopping</a>
                <a href="checkout.php" class="btn">Proceed to Checkout</a>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php include 'includes/footer.php'; ?>