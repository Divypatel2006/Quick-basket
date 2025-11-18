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
        $quantity = 1;
    }
    
    // Check product exists and has sufficient stock
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
            // Initialize cart if not exists
            if (!isset($_SESSION['cart'])) {
                $_SESSION['cart'] = [];
            }
            
            // Add or update item in cart
            if (isset($_SESSION['cart'][$product_id])) {
                $_SESSION['cart'][$product_id]['quantity'] += $quantity;
            } else {
                $_SESSION['cart'][$product_id] = [
                    'quantity' => $quantity
                ];
            }
            
            $_SESSION['success'] = "Product added to cart successfully!";
        }
    } else {
        $_SESSION['error'] = "Product not found.";
    }
}

// Redirect back to previous page or products page
$redirect = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : 'products.php';
header("Location: " . $redirect);
exit;
?>