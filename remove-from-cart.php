<?php
include 'includes/config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

if (isset($_GET['id'])) {
    $product_id = $_GET['id'];
    
    // Remove item from cart
    if (isset($_SESSION['cart'][$product_id])) {
        unset($_SESSION['cart'][$product_id]);
        $_SESSION['success'] = "Item removed from cart.";
    } else {
        $_SESSION['error'] = "Item not found in cart.";
    }
}

// Redirect back to cart
header("Location: cart.php");
exit;
?>