<?php
include 'includes/config.php';
include 'includes/header.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// Check if cart is empty
if (!isset($_SESSION['cart']) || empty($_SESSION['cart'])) {
    header("Location: cart.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$cart = $_SESSION['cart'];
$total = 0;

// Calculate total and check stock
foreach ($cart as $product_id => $item) {
    $sql = "SELECT name, price, stock_quantity FROM products WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $product_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $product = $result->fetch_assoc();
    
    if ($product) {
        // Check if requested quantity exceeds stock
        if ($item['quantity'] > $product['stock_quantity']) {
            $_SESSION['error'] = "Sorry, we don't have enough stock for " . $product['name'] . ". Available: " . $product['stock_quantity'];
            header("Location: cart.php");
            exit;
        }
        $total += $product['price'] * $item['quantity'];
    }
}

// Calculate tax and final total
$tax_rate = 0.1; // 10% tax
$tax_amount = $total * $tax_rate;
$shipping_cost = 0.00; // Free shipping
$final_total = $total + $tax_amount + $shipping_cost;

// Process checkout
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $full_name = trim($_POST['full_name']);
    $address = trim($_POST['address']);
    $city = trim($_POST['city']);
    $zip_code = trim($_POST['zip_code']);
    $phone = trim($_POST['phone']);
    $payment_method = $_POST['payment_method'];
    
    // Validate required fields
    if (empty($full_name) || empty($address) || empty($city) || empty($zip_code) || empty($phone) || empty($payment_method)) {
        $error = "Please fill in all required fields.";
    } else {
        // Start transaction
        $conn->begin_transaction();
        
        try {
            // Create order with shipping information
            $sql = "INSERT INTO orders (user_id, total_amount, shipping_name, shipping_address, shipping_city, shipping_zip_code, shipping_phone, payment_method) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("idssssss", $user_id, $final_total, $full_name, $address, $city, $zip_code, $phone, $payment_method);
            
            if (!$stmt->execute()) {
                throw new Exception("Failed to create order: " . $stmt->error);
            }
            
            $order_id = $stmt->insert_id;
            
            // Add order items and update stock
            foreach ($cart as $product_id => $item) {
                $sql = "SELECT price, stock_quantity FROM products WHERE id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("i", $product_id);
                $stmt->execute();
                $result = $stmt->get_result();
                $product = $result->fetch_assoc();
                
                if ($product) {
                    // Insert order item
                    $sql = "INSERT INTO order_items (order_id, product_id, quantity, price) VALUES (?, ?, ?, ?)";
                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param("iiid", $order_id, $product_id, $item['quantity'], $product['price']);
                    
                    if (!$stmt->execute()) {
                        throw new Exception("Failed to add order item: " . $stmt->error);
                    }
                    
                    // Update product stock
                    $sql = "UPDATE products SET stock_quantity = stock_quantity - ? WHERE id = ?";
                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param("ii", $item['quantity'], $product_id);
                    
                    if (!$stmt->execute()) {
                        throw new Exception("Failed to update stock: " . $stmt->error);
                    }
                }
            }
            
            // Commit transaction
            $conn->commit();
            
            // Clear cart
            $_SESSION['cart'] = [];
            
            // Set success message
            $_SESSION['success'] = "Order placed successfully! Your order ID is: #" . $order_id;
            
            // Redirect to payment page or order confirmation
            if ($payment_method == 'cash_on_delivery') {
                header("Location: order-confirmation.php?order_id=" . $order_id);
            } else {
                header("Location: payment.php?order_id=" . $order_id);
            }
            exit;
            
        } catch (Exception $e) {
            // Rollback transaction on error
            $conn->rollback();
            $error = "Order failed. Please try again. Error: " . $e->getMessage();
        }
    }
}
?>

<div class="container">
    <h1>Checkout</h1>
    
    <?php if (isset($error)): ?>
        <div class="alert alert-error">
            <?php echo $error; ?>
        </div>
    <?php endif; ?>
    
    <div class="checkout-content">
        <!-- Order Summary -->
        <div class="checkout-summary">
            <h2>Order Summary</h2>
            <div class="order-items">
                <?php foreach ($cart as $product_id => $item): ?>
                    <?php
                    $sql = "SELECT name, price FROM products WHERE id = ?";
                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param("i", $product_id);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    $product = $result->fetch_assoc();
                    ?>
                    <?php if ($product): ?>
                        <div class="order-item">
                            <div class="item-info">
                                <h4><?php echo htmlspecialchars($product['name']); ?></h4>
                                <p>Quantity: <?php echo $item['quantity']; ?></p>
                            </div>
                            <div class="item-price">
                                $<?php echo number_format($product['price'] * $item['quantity'], 2); ?>
                            </div>
                        </div>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
            
            <div class="order-totals">
                <div class="total-line">
                    <span>Subtotal:</span>
                    <span>$<?php echo number_format($total, 2); ?></span>
                </div>
                <div class="total-line">
                    <span>Shipping:</span>
                    <span>$<?php echo number_format($shipping_cost, 2); ?></span>
                </div>
                <div class="total-line">
                    <span>Tax (10%):</span>
                    <span>$<?php echo number_format($tax_amount, 2); ?></span>
                </div>
                <div class="total-line grand-total">
                    <span><strong>Total:</strong></span>
                    <span><strong>$<?php echo number_format($final_total, 2); ?></strong></span>
                </div>
            </div>
        </div>

        <!-- Checkout Form -->
        <div class="checkout-form">
            <h2>Shipping Information</h2>
            <form method="POST" action="">
                <div class="form-row">
                    <div class="form-group">
                        <label for="full_name">Full Name *</label>
                        <input type="text" id="full_name" name="full_name" 
                               value="<?php echo isset($_POST['full_name']) ? htmlspecialchars($_POST['full_name']) : ''; ?>" 
                               required>
                    </div>
                </div>

                <div class="form-group">
                    <label for="address">Shipping Address *</label>
                    <textarea id="address" name="address" rows="3" placeholder="Street address, apartment, suite, etc." required><?php echo isset($_POST['address']) ? htmlspecialchars($_POST['address']) : ''; ?></textarea>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="city">City *</label>
                        <input type="text" id="city" name="city" 
                               value="<?php echo isset($_POST['city']) ? htmlspecialchars($_POST['city']) : ''; ?>" 
                               required>
                    </div>
                    <div class="form-group">
                        <label for="zip_code">ZIP Code *</label>
                        <input type="text" id="zip_code" name="zip_code" 
                               value="<?php echo isset($_POST['zip_code']) ? htmlspecialchars($_POST['zip_code']) : ''; ?>" 
                               required>
                    </div>
                </div>

                <div class="form-group">
                    <label for="phone">Phone Number *</label>
                    <input type="tel" id="phone" name="phone" 
                           value="<?php echo isset($_POST['phone']) ? htmlspecialchars($_POST['phone']) : ''; ?>" 
                           placeholder="+1 (555) 123-4567"
                           required>
                </div>

                <div class="form-group">
                    <label for="payment_method">Payment Method *</label>
                    <select id="payment_method" name="payment_method" required>
                        <option value="">Select Payment Method</option>
                        <option value="credit_card" <?php echo (isset($_POST['payment_method']) && $_POST['payment_method'] == 'credit_card') ? 'selected' : ''; ?>>Credit Card</option>
                        <option value="debit_card" <?php echo (isset($_POST['payment_method']) && $_POST['payment_method'] == 'debit_card') ? 'selected' : ''; ?>>Debit Card</option>
                        <option value="paypal" <?php echo (isset($_POST['payment_method']) && $_POST['payment_method'] == 'paypal') ? 'selected' : ''; ?>>PayPal</option>
                        <option value="cash_on_delivery" <?php echo (isset($_POST['payment_method']) && $_POST['payment_method'] == 'cash_on_delivery') ? 'selected' : ''; ?>>Cash on Delivery</option>
                    </select>
                </div>

                <div class="form-actions">
                    <a href="cart.php" class="btn btn-secondary">Back to Cart</a>
                    <button type="submit" class="btn btn-primary">Place Order</button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
.checkout-content {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 2rem;
    margin-top: 2rem;
}

.checkout-summary, .checkout-form {
    background: white;
    padding: 2rem;
    border-radius: 8px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
}

.checkout-summary h2, .checkout-form h2 {
    margin-bottom: 1.5rem;
    color: #333;
    border-bottom: 2px solid #4CAF50;
    padding-bottom: 0.5rem;
}

.order-items {
    margin-bottom: 2rem;
}

.order-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 1rem 0;
    border-bottom: 1px solid #eee;
}

.order-item:last-child {
    border-bottom: none;
}

.item-info h4 {
    margin-bottom: 0.5rem;
    color: #333;
}

.item-info p {
    color: #666;
    font-size: 0.9rem;
}

.item-price {
    font-weight: bold;
    color: #4CAF50;
}

.order-totals {
    border-top: 2px solid #eee;
    padding-top: 1rem;
}

.total-line {
    display: flex;
    justify-content: space-between;
    margin-bottom: 0.5rem;
    padding: 0.5rem 0;
}

.grand-total {
    border-top: 1px solid #ddd;
    margin-top: 0.5rem;
    padding-top: 1rem;
    font-size: 1.1rem;
}

.form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1rem;
}

.form-group {
    margin-bottom: 1.5rem;
}

.form-group label {
    display: block;
    margin-bottom: 0.5rem;
    font-weight: 500;
    color: #333;
}

.form-group input,
.form-group select,
.form-group textarea {
    width: 100%;
    padding: 0.75rem;
    border: 1px solid #ddd;
    border-radius: 4px;
    font-size: 1rem;
    transition: border-color 0.3s;
}

.form-group input:focus,
.form-group select:focus,
.form-group textarea:focus {
    outline: none;
    border-color: #4CAF50;
    box-shadow: 0 0 0 2px rgba(76, 175, 80, 0.1);
}

.form-actions {
    display: flex;
    gap: 1rem;
    justify-content: flex-end;
    margin-top: 2rem;
}

.btn {
    display: inline-block;
    padding: 0.75rem 1.5rem;
    border: none;
    border-radius: 4px;
    text-decoration: none;
    font-size: 1rem;
    cursor: pointer;
    transition: all 0.3s;
    text-align: center;
}

.btn-primary {
    background: #4CAF50;
    color: white;
}

.btn-primary:hover {
    background: #45a049;
    transform: translateY(-1px);
}

.btn-secondary {
    background: #6c757d;
    color: white;
}

.btn-secondary:hover {
    background: #5a6268;
}

.alert {
    padding: 1rem;
    border-radius: 4px;
    margin-bottom: 1rem;
}

.alert-error {
    background: #f8d7da;
    color: #721c24;
    border: 1px solid #f5c6cb;
}

@media (max-width: 768px) {
    .checkout-content {
        grid-template-columns: 1fr;
    }
    
    .form-row {
        grid-template-columns: 1fr;
    }
    
    .form-actions {
        flex-direction: column;
    }
    
    .btn {
        width: 100%;
    }
}
</style>

<?php include 'includes/footer.php'; ?>