<?php
// setup.php - Database setup script

// Database configuration
$host = 'localhost';
$user = 'root';
$pass = '';
$dbname = 'quick_basket';

// Create connection without selecting database
$conn = new mysqli($host, $user, $pass);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Create database
$sql = "CREATE DATABASE IF NOT EXISTS $dbname";
if ($conn->query($sql) === TRUE) {
    echo "Database created successfully<br>";
} else {
    die("Error creating database: " . $conn->error);
}

// Select the database
$conn->select_db($dbname);

// SQL schema
$sql = "
-- Users table
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin', 'customer') DEFAULT 'customer',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Categories table
CREATE TABLE IF NOT EXISTS categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description TEXT
);

-- Products table
CREATE TABLE IF NOT EXISTS products (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    price DECIMAL(10,2) NOT NULL,
    image VARCHAR(255),
    category_id INT,
    stock_quantity INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES categories(id)
);

-- Orders table
CREATE TABLE IF NOT EXISTS orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    total_amount DECIMAL(10,2) NOT NULL,
    status ENUM('pending', 'processing', 'shipped', 'delivered') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
);

-- Order items table
CREATE TABLE IF NOT EXISTS order_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT,
    product_id INT,
    quantity INT NOT NULL,
    price DECIMAL(10,2) NOT NULL,
    FOREIGN KEY (order_id) REFERENCES orders(id),
    FOREIGN KEY (product_id) REFERENCES products(id)
);
-- Add shipping address columns to orders table
ALTER TABLE orders 
ADD COLUMN shipping_name VARCHAR(100),
ADD COLUMN shipping_address TEXT,
ADD COLUMN shipping_city VARCHAR(100),
ADD COLUMN shipping_zip_code VARCHAR(20),
ADD COLUMN shipping_phone VARCHAR(20),
ADD COLUMN payment_method VARCHAR(50);
ALTER TABLE orders 
ADD COLUMN shipping_name VARCHAR(100),
ADD COLUMN shipping_address TEXT,
ADD COLUMN shipping_city VARCHAR(100),
ADD COLUMN shipping_zip_code VARCHAR(20),
ADD COLUMN shipping_phone VARCHAR(20),
ADD COLUMN payment_method VARCHAR(50);

CREATE TABLE IF NOT EXISTS categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

INSERT IGNORE INTO categories (name, description) VALUES 
('Vegetables', 'Fresh and organic vegetables'),
('Fruits', 'Seasonal and exotic fruits'),
('Dairy', 'Milk, cheese, and other dairy products'),
('Bakery', 'Fresh bread and baked goods');

ALTER TABLE users ADD COLUMN status ENUM('active', 'inactive') DEFAULT 'active';


$sql = "SELECT u.*, COUNT(o.id) as order_count 
        FROM users u 
        LEFT JOIN orders o ON u.id = o.user_id 
        WHERE u.status = 'active'  -- Add this line
        GROUP BY u.id 
        ORDER BY u.created_at DESC";
";

// Execute multi query
if ($conn->multi_query($sql)) {
    do {
        if ($result = $conn->store_result()) {
            $result->free();
        }
    } while ($conn->next_result());
    echo "Tables created successfully<br>";
} else {
    echo "Error creating tables: " . $conn->error . "<br>";
}

// Insert sample data
$sample_data = "
INSERT IGNORE INTO categories (id, name, description) VALUES 
(1, 'Vegetables', 'Fresh and organic vegetables'),
(2, 'Fruits', 'Seasonal and exotic fruits'),
(3, 'Dairy', 'Milk, cheese, and other dairy products'),
(4, 'Bakery', 'Fresh bread and baked goods');

INSERT IGNORE INTO products (id, name, description, price, category_id, stock_quantity) VALUES
(1, 'Tomatoes', 'Fresh organic tomatoes', 2.50, 1, 100),
(2, 'Carrots', 'Sweet and crunchy carrots', 1.80, 1, 80),
(3, 'Apples', 'Red delicious apples', 3.00, 2, 120),
(4, 'Bananas', 'Ripe yellow bananas', 1.50, 2, 150),
(5, 'Milk', 'Fresh whole milk 1L', 2.20, 3, 50),
(6, 'Bread', 'Whole wheat bread', 2.00, 4, 30);

INSERT IGNORE INTO users (id, name, email, password, role) VALUES
(1, 'Admin User', 'admin@quickbasket.com', '" . password_hash('admin123', PASSWORD_DEFAULT) . "', 'admin'),
(2, 'John Customer', 'john@example.com', '" . password_hash('password123', PASSWORD_DEFAULT) . "', 'customer');
";

if ($conn->multi_query($sample_data)) {
    do {
        if ($result = $conn->store_result()) {
            $result->free();
        }
    } while ($conn->next_result());
    echo "Sample data inserted successfully<br>";
} else {
    echo "Error inserting sample data: " . $conn->error . "<br>";
}

echo "<h3>Setup completed successfully!</h3>";
echo "<p>You can now <a href='index.php'>go to the website</a></p>";
echo "<p><strong>Admin Login:</strong><br>";
echo "Email: admin@quickbasket.com<br>";
echo "Password: admin123</p>";

$conn->close();
?>