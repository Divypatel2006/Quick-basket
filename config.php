<?php
// Database configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'quick_basket');

// Create connection
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Create database if it doesn't exist
$sql = "CREATE DATABASE IF NOT EXISTS " . DB_NAME;
if ($conn->query($sql) === FALSE) {
    die("Error creating database: " . $conn->error);
}

// Select the database
$conn->select_db(DB_NAME);

// Function to initialize database tables
if (!function_exists('initializeDatabase')) {
    function initializeDatabase($conn) {
        // Check if users table exists
        $result = $conn->query("SHOW TABLES LIKE 'users'");
        if ($result->num_rows == 0) {
            // Tables don't exist, create them
            $sql = "
            CREATE TABLE users (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(100) NOT NULL,
                email VARCHAR(100) UNIQUE NOT NULL,
                password VARCHAR(255) NOT NULL,
                role ENUM('admin', 'customer') DEFAULT 'customer',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            );
            
            CREATE TABLE categories (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(100) NOT NULL,
                description TEXT
            );
            
            CREATE TABLE products (
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
            
            CREATE TABLE orders (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT,
                total_amount DECIMAL(10,2) NOT NULL,
                status ENUM('pending', 'processing', 'shipped', 'delivered', 'cancelled') DEFAULT 'pending',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id)
            );
            
            CREATE TABLE order_items (
                id INT AUTO_INCREMENT PRIMARY KEY,
                order_id INT,
                product_id INT,
                quantity INT NOT NULL,
                price DECIMAL(10,2) NOT NULL,
                FOREIGN KEY (order_id) REFERENCES orders(id),
                FOREIGN KEY (product_id) REFERENCES products(id)
            );
            ";
            
            if ($conn->multi_query($sql)) {
                do {
                    if ($result = $conn->store_result()) {
                        $result->free();
                    }
                } while ($conn->next_result());
            }
            
            // Insert sample data
            $sample_data = "
            INSERT IGNORE INTO categories (name, description) VALUES 
            ('Vegetables', 'Fresh and organic vegetables'),
            ('Fruits', 'Seasonal and exotic fruits'),
            ('Dairy', 'Milk, cheese, and other dairy products'),
            ('Bakery', 'Fresh bread and baked goods');
            
            INSERT IGNORE INTO products (name, description, price, category_id, stock_quantity) VALUES
            ('Tomatoes', 'Fresh organic tomatoes', 2.50, 1, 100),
            ('Carrots', 'Sweet and crunchy carrots', 1.80, 1, 80),
            ('Apples', 'Red delicious apples', 3.00, 2, 120),
            ('Bananas', 'Ripe yellow bananas', 1.50, 2, 150),
            ('Milk', 'Fresh whole milk 1L', 2.20, 3, 50),
            ('Bread', 'Whole wheat bread', 2.00, 4, 30);
            
            INSERT IGNORE INTO users (name, email, password, role) VALUES
            ('Admin User', 'admin@quickbasket.com', '" . password_hash('admin123', PASSWORD_DEFAULT) . "', 'admin');
            ";
            
            if ($conn->multi_query($sample_data)) {
                do {
                    if ($result = $conn->store_result()) {
                        $result->free();
                    }
                } while ($conn->next_result());
            }
        }
    }
}

// Initialize database tables
initializeDatabase($conn);

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>