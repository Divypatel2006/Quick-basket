<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
$isLoggedIn = isset($_SESSION['user_id']);
// Safely get role with null coalescing operator to avoid undefined key errors
$userRole = $isLoggedIn ? ($_SESSION['role'] ?? 'customer') : '';
$userName = $isLoggedIn ? ($_SESSION['user_name'] ?? 'User') : '';

// Calculate cart items count
$cartCount = 0;
if (isset($_SESSION['cart']) && is_array($_SESSION['cart'])) {
    foreach ($_SESSION['cart'] as $item) {
        $cartCount += $item['quantity'] ?? 0;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quick Basket - Fresh Groceries Delivered</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <header class="site-header">
        <div class="container">
            <div class="logo">
                <h1>🍎 Quick Basket</h1>
            </div>
            
            <nav class="main-nav">
                <ul>
                    <li><a href="index.php">Home</a></li>
                    <li><a href="products.php">Products</a></li>
                    <li><a href="about.php">About</a></li>
                    <li><a href="contact.php">Contact</a></li>
                    
                    <?php if($isLoggedIn): ?>
                        <li class="cart-link">
                            <a href="cart.php">
                                🛒 Cart 
                                <?php if($cartCount > 0): ?>
                                    <span class="cart-count"><?php echo $cartCount; ?></span>
                                <?php endif; ?>
                            </a>
                        </li>
                        <li class="user-menu">
                            <span class="user-greeting">Welcome, <?php echo htmlspecialchars($userName); ?></span>
                            <div class="user-dropdown">
                                <a href="profile.php">Profile</a>
                                <a href="orders.php">My Orders</a>
                                <?php if($userRole == 'admin'): ?>
                                    <a href="admin/index.php">Admin Panel</a>
                                <?php endif; ?>
                                <a href="logout.php">Logout</a>
                            </div>
                        </li>
                    <?php else: ?>
                        <li><a href="login.php">Login</a></li>
                        <li><a href="register.php">Register</a></li>
                    <?php endif; ?>
                </ul>
            </nav>
            
            <div class="mobile-menu-toggle" onclick="toggleMobileMenu()">
                <span></span>
                <span></span>
                <span></span>
            </div>
        </div>
    </header>

    <main class="main-content">