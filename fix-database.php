<?php
// fix-database.php - Run this once to fix the database structure

// Database configuration
$host = 'localhost';
$user = 'root';
$pass = '';
$dbname = 'quick_basket';

// Create connection
$conn = new mysqli($host, $user, $pass, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

echo "<h2>Fixing Database Structure...</h2>";

// Add missing columns to orders table
$sql = "SHOW COLUMNS FROM orders LIKE 'shipping_name'";
$result = $conn->query($sql);

if ($result->num_rows == 0) {
    // Add the missing columns
    $alter_sql = "
    ALTER TABLE orders 
    ADD COLUMN shipping_name VARCHAR(100),
    ADD COLUMN shipping_address TEXT,
    ADD COLUMN shipping_city VARCHAR(100),
    ADD COLUMN shipping_zip_code VARCHAR(20),
    ADD COLUMN shipping_phone VARCHAR(20),
    ADD COLUMN payment_method VARCHAR(50)
    ";
    
    if ($conn->multi_query($alter_sql)) {
        echo "<p style='color: green;'>✅ Successfully added shipping columns to orders table</p>";
    } else {
        echo "<p style='color: red;'>❌ Error adding columns: " . $conn->error . "</p>";
    }
} else {
    echo "<p style='color: green;'>✅ Shipping columns already exist</p>";
}

// Check if orders table has the correct structure
echo "<h3>Current Orders Table Structure:</h3>";
$sql = "DESCRIBE orders";
$result = $conn->query($sql);

echo "<table border='1' cellpadding='5'>";
echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
while ($row = $result->fetch_assoc()) {
    echo "<tr>";
    echo "<td>" . $row['Field'] . "</td>";
    echo "<td>" . $row['Type'] . "</td>";
    echo "<td>" . $row['Null'] . "</td>";
    echo "<td>" . $row['Key'] . "</td>";
    echo "<td>" . $row['Default'] . "</td>";
    echo "<td>" . $row['Extra'] . "</td>";
    echo "</tr>";
}
echo "</table>";

$conn->close();

echo "<h3 style='color: green;'>Database fix completed! You can now use the checkout system.</h3>";
echo "<p><a href='checkout.php'>Go to Checkout</a> | <a href='index.php'>Go to Home</a></p>";
?>