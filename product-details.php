<?php
include 'includes/config.php';
include 'includes/header.php';

if (!isset($_GET['id'])) {
    header("Location: products.php");
    exit;
}

$product_id = $_GET['id'];
$sql = "SELECT p.*, c.name as category_name FROM products p 
        LEFT JOIN categories c ON p.category_id = c.id 
        WHERE p.id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $product_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 0) {
    echo "<div class='container'><p>Product not found.</p></div>";
    include 'includes/footer.php';
    exit;
}

$product = $result->fetch_assoc();
?>

<div class="container">
    <div class="product-detail">
        <div class="product-image-large">
            <?php if($product['image']): ?>
                <img src="images/<?php echo $product['image']; ?>" alt="<?php echo $product['name']; ?>">
            <?php else: ?>
                <div>No Image Available</div>
            <?php endif; ?>
        </div>
        <div class="product-info-large">
            <h1><?php echo $product['name']; ?></h1>
            <p class="product-category">Category: <?php echo $product['category_name']; ?></p>
            <p class="product-price">$<?php echo number_format($product['price'], 2); ?></p>
            <p class="product-description"><?php echo $product['description']; ?></p>
            <p class="product-stock">In Stock: <?php echo $product['stock_quantity']; ?></p>
            
            <?php if(isset($_SESSION['user_id'])): ?>
                <form method="POST" action="add-to-cart.php" class="add-to-cart-form">
                    <input type="hidden" name="product_id" value="<?php echo $product['id']; ?>">
                    <div class="form-group">
                        <label for="quantity">Quantity:</label>
                        <input type="number" id="quantity" name="quantity" value="1" min="1" max="<?php echo $product['stock_quantity']; ?>">
                    </div>
                    <button type="submit" class="btn">Add to Cart</button>
                </form>
            <?php else: ?>
                <p><a href="login.php">Login</a> to add items to your cart.</p>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>