<?php
include 'includes/config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $product_id = $_POST['product_id'];
    $quantity = intval($_POST['quantity']);
    
    // Validate quantity
    if ($quantity < 1) {
        // Remove item if quantity is 0 or less
        unset($_SESSION['cart'][$product_id]);
        $_SESSION['success'] = "Item removed from cart.";
    } else {
        // Check product stock
        $sql = "SELECT stock_quantity FROM products WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $product_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows == 1) {
            $product = $result->fetch_assoc();
            
            if ($quantity > $product['stock_quantity']) {
                $_SESSION['error'] = "Requested quantity exceeds available stock.";
            } else {
                // Update cart quantity
                $_SESSION['cart'][$product_id]['quantity'] = $quantity;
                $_SESSION['success'] = "Cart updated successfully!";
            }
        }
    }
}

// Redirect back to cart
header("Location: cart.php");
exit;
?>