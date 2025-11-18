<?php
include '../includes/config.php';
include 'admin-header.php';

// Check if user is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: ../login.php");
    exit;
}

// Handle product actions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['add_product'])) {
        $name = trim($_POST['name']);
        $description = trim($_POST['description']);
        $price = floatval($_POST['price']);
        $category_id = intval($_POST['category_id']);
        $stock_quantity = intval($_POST['stock_quantity']);
        
        // Handle image upload
        $image_name = '';
        if (isset($_FILES['image']) && $_FILES['image']['error'] == 0) {
            $allowed_types = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
            $file_extension = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
            
            if (in_array($file_extension, $allowed_types)) {
                $image_name = time() . '_' . uniqid() . '.' . $file_extension;
                $upload_path = '../images/products/' . $image_name;
                
                // Create directory if it doesn't exist
                if (!is_dir('../images/products/')) {
                    mkdir('../images/products/', 0777, true);
                }
                
                if (move_uploaded_file($_FILES['image']['tmp_name'], $upload_path)) {
                    // Image uploaded successfully
                } else {
                    $_SESSION['error'] = "Failed to upload image.";
                }
            } else {
                $_SESSION['error'] = "Invalid image format. Allowed: JPG, JPEG, PNG, GIF, WEBP";
            }
        }
        
        if (!isset($_SESSION['error'])) {
            $sql = "INSERT INTO products (name, description, price, image, category_id, stock_quantity) 
                    VALUES (?, ?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ssdsii", $name, $description, $price, $image_name, $category_id, $stock_quantity);
            
            if ($stmt->execute()) {
                $_SESSION['success'] = "Product added successfully!";
            } else {
                $_SESSION['error'] = "Failed to add product: " . $stmt->error;
            }
        }
        
    } elseif (isset($_POST['update_product'])) {
        $product_id = intval($_POST['product_id']);
        $name = trim($_POST['name']);
        
        $price = floatval($_POST['price']);
        $category_id = intval($_POST['category_id']);
        $stock_quantity = intval($_POST['stock_quantity']);
        
        // Handle image upload
        $image_update = "";
        $image_name = "";
        if (isset($_FILES['image']) && $_FILES['image']['error'] == 0) {
            $allowed_types = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
            $file_extension = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
            
            if (in_array($file_extension, $allowed_types)) {
                $image_name = time() . '_' . uniqid() . '.' . $file_extension;
                $upload_path = '../images/products/' . $image_name;
                
                if (move_uploaded_file($_FILES['image']['tmp_name'], $upload_path)) {
                    $image_update = ", image = ?";
                } else {
                    $_SESSION['error'] = "Failed to upload image.";
                }
            } else {
                $_SESSION['error'] = "Invalid image format. Allowed: JPG, JPEG, PNG, GIF, WEBP";
            }
        }
        
        if (!isset($_SESSION['error'])) {
            if ($image_update) {
                $sql = "UPDATE products SET name=?, description=?, price=?, category_id=?, stock_quantity=? $image_update WHERE id=?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("ssdiisi", $name, $description, $price, $category_id, $stock_quantity, $image_name, $product_id);
            } else {
                $sql = "UPDATE products SET name=?, description=?, price=?, category_id=?, stock_quantity=? WHERE id=?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("ssdiii", $name, $description, $price, $category_id, $stock_quantity, $product_id);
            }
            
            if ($stmt->execute()) {
                $_SESSION['success'] = "Product updated successfully!";
            } else {
                $_SESSION['error'] = "Failed to update product: " . $stmt->error;
            }
        }
        
    } elseif (isset($_POST['delete_product'])) {
        $product_id = intval($_POST['product_id']);
        
        // Check if product has orders
        $check_orders = $conn->prepare("SELECT COUNT(*) as order_count FROM order_items WHERE product_id = ?");
        $check_orders->bind_param("i", $product_id);
        $check_orders->execute();
        $order_result = $check_orders->get_result();
        $order_count = $order_result->fetch_assoc()['order_count'];
        
        if ($order_count > 0) {
            $_SESSION['error'] = "Cannot delete product because it has been ordered $order_count time(s). You can set stock to 0 instead.";
        } else {
            $sql = "DELETE FROM products WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $product_id);
            
            if ($stmt->execute()) {
                $_SESSION['success'] = "Product deleted successfully!";
            } else {
                $_SESSION['error'] = "Failed to delete product: " . $stmt->error;
            }
        }
    } elseif (isset($_POST['bulk_action'])) {
        $action = $_POST['bulk_action'];
        $product_ids = isset($_POST['product_ids']) ? $_POST['product_ids'] : [];
        
        if (empty($product_ids)) {
            $_SESSION['error'] = "Please select products to perform bulk action.";
        } else {
            $placeholders = str_repeat('?,', count($product_ids) - 1) . '?';
            
            switch ($action) {
                case 'delete':
                    // Check if any products have orders
                    $check_sql = "SELECT COUNT(*) as order_count FROM order_items WHERE product_id IN ($placeholders)";
                    $check_stmt = $conn->prepare($check_sql);
                    $check_stmt->bind_param(str_repeat('i', count($product_ids)), ...$product_ids);
                    $check_stmt->execute();
                    $check_result = $check_stmt->get_result();
                    $total_orders = $check_result->fetch_assoc()['order_count'];
                    
                    if ($total_orders > 0) {
                        $_SESSION['error'] = "Cannot delete products that have been ordered. $total_orders product(s) have orders.";
                    } else {
                        $sql = "DELETE FROM products WHERE id IN ($placeholders)";
                        $stmt = $conn->prepare($sql);
                        $stmt->bind_param(str_repeat('i', count($product_ids)), ...$product_ids);
                        
                        if ($stmt->execute()) {
                            $_SESSION['success'] = count($product_ids) . " product(s) deleted successfully!";
                        } else {
                            $_SESSION['error'] = "Failed to delete products.";
                        }
                    }
                    break;
                    
                case 'out_of_stock':
                    $sql = "UPDATE products SET stock_quantity = 0 WHERE id IN ($placeholders)";
                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param(str_repeat('i', count($product_ids)), ...$product_ids);
                    
                    if ($stmt->execute()) {
                        $_SESSION['success'] = count($product_ids) . " product(s) marked as out of stock!";
                    } else {
                        $_SESSION['error'] = "Failed to update products.";
                    }
                    break;
                    
                case 'in_stock':
                    $sql = "UPDATE products SET stock_quantity = 100 WHERE id IN ($placeholders)";
                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param(str_repeat('i', count($product_ids)), ...$product_ids);
                    
                    if ($stmt->execute()) {
                        $_SESSION['success'] = count($product_ids) . " product(s) stock updated!";
                    } else {
                        $_SESSION['error'] = "Failed to update products.";
                    }
                    break;
            }
        }
    }
}

// Handle search and filters
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$category_filter = isset($_GET['category']) ? intval($_GET['category']) : '';
$stock_filter = isset($_GET['stock']) ? $_GET['stock'] : '';
$sort_by = isset($_GET['sort']) ? $_GET['sort'] : 'id_desc';

// Build query with filters
$where_conditions = [];
$params = [];
$types = '';

if (!empty($search)) {
    $where_conditions[] = "(p.name LIKE ? OR p.description LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $types .= 'ss';
}

if (!empty($category_filter)) {
    $where_conditions[] = "p.category_id = ?";
    $params[] = $category_filter;
    $types .= 'i';
}

if (!empty($stock_filter)) {
    switch ($stock_filter) {
        case 'in_stock':
            $where_conditions[] = "p.stock_quantity > 0";
            break;
        case 'out_of_stock':
            $where_conditions[] = "p.stock_quantity = 0";
            break;
        case 'low_stock':
            $where_conditions[] = "p.stock_quantity > 0 AND p.stock_quantity < 10";
            break;
    }
}

$where_clause = '';
if (!empty($where_conditions)) {
    $where_clause = "WHERE " . implode(' AND ', $where_conditions);
}

// Build sort clause
$sort_clause = "ORDER BY ";
switch ($sort_by) {
    case 'name_asc': $sort_clause .= "p.name ASC"; break;
    case 'name_desc': $sort_clause .= "p.name DESC"; break;
    case 'price_asc': $sort_clause .= "p.price ASC"; break;
    case 'price_desc': $sort_clause .= "p.price DESC"; break;
    case 'stock_asc': $sort_clause .= "p.stock_quantity ASC"; break;
    case 'stock_desc': $sort_clause .= "p.stock_quantity DESC"; break;
    case 'newest': $sort_clause .= "p.created_at DESC"; break;
    case 'oldest': $sort_clause .= "p.created_at ASC"; break;
    default: $sort_clause .= "p.id DESC"; break;
}

// Fetch products with filters
$products = [];
$categories = [];

// Get total count for pagination
$count_sql = "SELECT COUNT(*) as total FROM products p $where_clause";
$count_stmt = $conn->prepare($count_sql);
if (!empty($params)) {
    $count_stmt->bind_param($types, ...$params);
}
$count_stmt->execute();
$total_result = $count_stmt->get_result();
$total_products = $total_result->fetch_assoc()['total'];

// Pagination
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$per_page = 10;
$total_pages = ceil($total_products / $per_page);
$offset = ($page - 1) * $per_page;

// Main query
$sql = "SELECT p.*, c.name as category_name, 
               (SELECT COUNT(*) FROM order_items oi WHERE oi.product_id = p.id) as order_count
        FROM products p 
        LEFT JOIN categories c ON p.category_id = c.id 
        $where_clause 
        $sort_clause 
        LIMIT ? OFFSET ?";

$params[] = $per_page;
$params[] = $offset;
$types .= 'ii';

$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $products[] = $row;
    }
}

// Fetch categories for filter
$categories_result = $conn->query("SELECT * FROM categories ORDER BY name");
if ($categories_result->num_rows > 0) {
    while($row = $categories_result->fetch_assoc()) {
        $categories[] = $row;
    }
}

// Get product statistics
$stats = [
    'total' => $total_products,
    'in_stock' => $conn->query("SELECT COUNT(*) as total FROM products WHERE stock_quantity > 0")->fetch_assoc()['total'],
    'out_of_stock' => $conn->query("SELECT COUNT(*) as total FROM products WHERE stock_quantity = 0")->fetch_assoc()['total'],
    'low_stock' => $conn->query("SELECT COUNT(*) as total FROM products WHERE stock_quantity > 0 AND stock_quantity < 10")->fetch_assoc()['total'],
    'total_categories' => $conn->query("SELECT COUNT(*) as total FROM categories")->fetch_assoc()['total']
];
?>
<style>
/* COMPREHENSIVE PRODUCT MANAGEMENT CSS */

/* Admin Panel Base Styles */
.admin-container {
    display: flex;
    min-height: 100vh;
    background: #f8f9fa;
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
}

.admin-sidebar {
    width: 280px;
    background: linear-gradient(135deg, #2c3e50 0%, #34495e 100%);
    color: white;
    position: fixed;
    height: 100vh;
    overflow-y: auto;
    box-shadow: 2px 0 10px rgba(0,0,0,0.1);
}

.sidebar-header {
    padding: 2rem 1.5rem 1.5rem;
    border-bottom: 1px solid #34495e;
    background: rgba(0,0,0,0.1);
}

.sidebar-header h3 {
    margin: 0 0 0.5rem 0;
    font-size: 1.5rem;
    color: #4CAF50;
    font-weight: 700;
}

.sidebar-header p {
    margin: 0;
    color: #bdc3c7;
    font-size: 0.9rem;
}

.sidebar-nav {
    padding: 1rem 0;
}

.nav-item {
    margin-bottom: 0.25rem;
}

.nav-link {
    display: flex;
    align-items: center;
    padding: 1rem 1.5rem;
    color: #bdc3c7;
    text-decoration: none;
    transition: all 0.3s ease;
    position: relative;
    border-left: 3px solid transparent;
}

.nav-link:hover {
    background: rgba(255,255,255,0.05);
    color: white;
    border-left-color: #4CAF50;
}

.nav-item.active .nav-link {
    background: rgba(76, 175, 80, 0.1);
    color: white;
    border-left-color: #4CAF50;
}

.nav-icon {
    font-size: 1.2rem;
    margin-right: 1rem;
    width: 20px;
    text-align: center;
}

.nav-text {
    flex: 1;
    font-weight: 500;
}

.nav-badge {
    background: #e74c3c;
    color: white;
    border-radius: 10px;
    padding: 0.2rem 0.6rem;
    font-size: 0.8rem;
    font-weight: bold;
}

/* Main Content Styles */
.admin-main {
    flex: 1;
    margin-left: 280px;
    min-height: 100vh;
}

.admin-header {
    background: white;
    padding: 1.5rem 2rem;
    border-bottom: 1px solid #e9ecef;
    display: flex;
    justify-content: space-between;
    align-items: center;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.admin-header h1 {
    margin: 0;
    color: #2c3e50;
    font-size: 1.8rem;
    font-weight: 700;
}

.admin-user {
    position: relative;
    cursor: pointer;
    padding: 0.5rem 1rem;
    border-radius: 8px;
    transition: background 0.3s;
    background: #f8f9fa;
    border: 1px solid #e9ecef;
}

.admin-user:hover {
    background: #e9ecef;
}

.admin-user:hover .user-dropdown {
    display: block;
}

.user-dropdown {
    display: none;
    position: absolute;
    top: 100%;
    right: 0;
    background: white;
    min-width: 150px;
    box-shadow: 0 4px 15px rgba(0,0,0,0.1);
    border-radius: 8px;
    overflow: hidden;
    z-index: 1000;
    border: 1px solid #e9ecef;
}

.user-dropdown a {
    display: block;
    padding: 0.75rem 1rem;
    color: #333;
    text-decoration: none;
    border-bottom: 1px solid #eee;
    transition: background 0.3s;
}

.user-dropdown a:hover {
    background: #4CAF50;
    color: white;
}

/* Admin Content */
.admin-content {
    padding: 2rem;
}

/* Alert Styles */
.alert {
    padding: 1rem;
    border-radius: 8px;
    margin-bottom: 1.5rem;
    border: 1px solid transparent;
}

.alert-success {
    background: #d4edda;
    border-color: #c3e6cb;
    color: #155724;
}

.alert-error {
    background: #f8d7da;
    border-color: #f5c6cb;
    color: #721c24;
}

.alert-content {
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.alert-icon {
    font-size: 1.2rem;
}

/* Statistics Grid */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1.5rem;
    margin-bottom: 2rem;
}

.stat-card {
    background: white;
    padding: 1.5rem;
    border-radius: 12px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.08);
    display: flex;
    align-items: center;
    transition: all 0.3s ease;
    border: 1px solid #e9ecef;
}

.stat-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 5px 20px rgba(0,0,0,0.15);
}

.stat-icon {
    font-size: 2.5rem;
    margin-right: 1rem;
    width: 70px;
    height: 70px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 12px;
}

.stat-icon.total { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; }
.stat-icon.in-stock { background: linear-gradient(135deg, #4CAF50 0%, #45a049 100%); color: white; }
.stat-icon.out-of-stock { background: linear-gradient(135deg, #f44336 0%, #d32f2f 100%); color: white; }
.stat-icon.low-stock { background: linear-gradient(135deg, #ff9800 0%, #f57c00 100%); color: white; }
.stat-icon.categories { background: linear-gradient(135deg, #2196f3 0%, #1976d2 100%); color: white; }

.stat-info h3 {
    margin: 0 0 0.5rem 0;
    font-size: 2rem;
    color: #2c3e50;
    font-weight: 700;
}

.stat-info p {
    margin: 0;
    color: #6c757d;
    font-weight: 500;
    font-size: 0.9rem;
}

/* Card Styles */
.card {
    background: white;
    border-radius: 12px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.08);
    border: 1px solid #e9ecef;
    overflow: hidden;
    margin-bottom: 2rem;
}

.card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 1.5rem;
    border-bottom: 1px solid #e9ecef;
    background: #f8f9fa;
}

.card-header h3 {
    margin: 0;
    color: #2c3e50;
    font-weight: 600;
}

.card-actions {
    display: flex;
    align-items: center;
    gap: 1rem;
}

.card-body {
    padding: 1.5rem;
}

/* Form Styles */
.filters-form {
    margin-bottom: 0;
}

.filter-row {
    display: grid;
    grid-template-columns: 2fr 1fr 1fr 1fr auto;
    gap: 1rem;
    align-items: end;
}

.filter-group {
    margin-bottom: 0;
}

.search-input, .filter-select {
    width: 100%;
    padding: 0.75rem;
    border: 1px solid #ddd;
    border-radius: 6px;
    font-size: 0.9rem;
}

.filter-actions {
    display: flex;
    gap: 0.5rem;
}

.btn {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.75rem 1.5rem;
    border: none;
    border-radius: 6px;
    font-size: 0.9rem;
    font-weight: 500;
    text-decoration: none;
    cursor: pointer;
    transition: all 0.3s ease;
    font-family: inherit;
}

.btn-primary {
    background: linear-gradient(135deg, #4CAF50, #45a049);
    color: white;
}

.btn-primary:hover {
    background: linear-gradient(135deg, #45a049, #3d8b40);
    transform: translateY(-1px);
    box-shadow: 0 2px 8px rgba(76, 175, 80, 0.3);
}

.btn-secondary {
    background: #6c757d;
    color: white;
}

.btn-secondary:hover {
    background: #5a6268;
    transform: translateY(-1px);
}

.btn-sm {
    padding: 0.5rem 1rem;
    font-size: 0.8rem;
}

/* Product Form */
.product-form {
    display: flex;
    flex-direction: column;
    gap: 1.5rem;
}

.form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1rem;
}

.form-group {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.form-group label {
    font-weight: 600;
    color: #2c3e50;
    font-size: 0.9rem;
}

.form-group input,
.form-group select,
.form-group textarea {
    padding: 0.75rem;
    border: 1px solid #ddd;
    border-radius: 6px;
    font-size: 0.9rem;
    transition: all 0.3s;
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
    margin-top: 1rem;
}

/* File Upload */
.file-upload {
    margin-top: 0.5rem;
}

.upload-preview {
    margin-top: 0.5rem;
    max-width: 200px;
    max-height: 150px;
    border: 2px dashed #ddd;
    border-radius: 8px;
    padding: 0.5rem;
    text-align: center;
}

.upload-preview img {
    max-width: 100%;
    max-height: 120px;
    border-radius: 4px;
}

.file-help {
    display: block;
    margin-top: 0.5rem;
    color: #6c757d;
    font-size: 0.8rem;
}

/* Bulk Actions */
.bulk-actions {
    display: flex;
    gap: 0.5rem;
    align-items: center;
}

.bulk-select {
    padding: 0.5rem;
    border: 1px solid #ddd;
    border-radius: 4px;
    font-size: 0.9rem;
}

/* Table Styles */
.table-container {
    overflow-x: auto;
}

.data-table {
    width: 100%;
    border-collapse: collapse;
}

.data-table th {
    background: #f8f9fa;
    padding: 1rem;
    text-align: left;
    font-weight: 600;
    color: #2c3e50;
    border-bottom: 2px solid #e9ecef;
    font-size: 0.9rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.data-table td {
    padding: 1rem;
    border-bottom: 1px solid #e9ecef;
    vertical-align: middle;
}

.data-table tr:hover {
    background: #f8f9fa;
}

/* Product Table Enhancements */
.product-row.out-of-stock {
    background: #fff5f5;
}

.product-row.low-stock {
    background: #fffbf0;
}

.product-info {
    display: flex;
    gap: 1rem;
    align-items: flex-start;
}

.product-image {
    flex-shrink: 0;
}

.product-image img {
    width: 60px;
    height: 60px;
    object-fit: cover;
    border-radius: 8px;
    border: 1px solid #e9ecef;
}

.no-image {
    width: 60px;
    height: 60px;
    background: #f8f9fa;
    border: 1px dashed #e9ecef;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #6c757d;
    font-size: 0.8rem;
}

.product-details {
    flex: 1;
    min-width: 0;
}

.product-name {
    display: block;
    font-weight: 600;
    color: #2c3e50;
    margin-bottom: 0.25rem;
}

.product-desc {
    color: #6c757d;
    font-size: 0.85rem;
    margin: 0.25rem 0;
    line-height: 1.4;
}

.product-meta {
    display: flex;
    gap: 1rem;
    font-size: 0.8rem;
    color: #999;
}

.category-tag {
    background: #e3f2fd;
    color: #1976d2;
    padding: 0.25rem 0.75rem;
    border-radius: 12px;
    font-size: 0.8rem;
    font-weight: 500;
}

.price {
    font-weight: 700;
    color: #4CAF50;
    font-size: 1.1rem;
}

.stock-info {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
}

.stock-quantity {
    padding: 0.25rem 0.75rem;
    border-radius: 12px;
    font-weight: 600;
    font-size: 0.9rem;
    text-align: center;
    min-width: 40px;
    display: inline-block;
}

.stock-quantity.in-stock {
    background: #e8f5e8;
    color: #4CAF50;
}

.stock-quantity.low-stock {
    background: #fff3cd;
    color: #856404;
}

.stock-quantity.out-of-stock {
    background: #f8d7da;
    color: #721c24;
}

.stock-warning {
    font-size: 0.75rem;
    color: #dc3545;
    font-weight: 500;
}

.order-count {
    text-align: center;
}

.order-count .count {
    display: block;
    font-weight: 700;
    color: #2c3e50;
    font-size: 1.1rem;
}

.order-count .label {
    font-size: 0.8rem;
    color: #6c757d;
}

.status-badge {
    padding: 0.4rem 0.8rem;
    border-radius: 12px;
    font-size: 0.8rem;
    font-weight: 600;
    text-align: center;
    display: inline-block;
    min-width: 80px;
}

.status-in-stock {
    background: #d4edda;
    color: #155724;
    border: 1px solid #c3e6cb;
}

.status-low-stock {
    background: #fff3cd;
    color: #856404;
    border: 1px solid #ffeaa7;
}

.status-out-of-stock {
    background: #f8d7da;
    color: #721c24;
    border: 1px solid #f5c6cb;
}

/* Action Buttons */
.action-buttons {
    display: flex;
    gap: 0.5rem;
    align-items: center;
}

.btn-danger {
    background: linear-gradient(135deg, #e74c3c, #c0392b);
    color: white;
}

.btn-danger:hover {
    background: linear-gradient(135deg, #c0392b, #a93226);
    transform: translateY(-1px);
    box-shadow: 0 2px 8px rgba(231, 76, 60, 0.3);
}

/* Empty State */
.empty-state {
    text-align: center;
    padding: 3rem 2rem;
    color: #6c757d;
}

.empty-icon {
    font-size: 4rem;
    margin-bottom: 1rem;
    opacity: 0.5;
}

.empty-state h3 {
    margin: 0 0 0.5rem 0;
    color: #2c3e50;
    font-weight: 600;
}

.empty-state p {
    margin: 0;
    font-size: 0.95rem;
}

/* Modal Styles */
.modal {
    display: none;
    position: fixed;
    z-index: 1000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0,0,0,0.5);
}

.modal-content {
    background-color: white;
    margin: 5% auto;
    border-radius: 12px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.2);
    max-width: 600px;
    animation: modalSlideIn 0.3s ease;
}

@keyframes modalSlideIn {
    from { transform: translateY(-50px); opacity: 0; }
    to { transform: translateY(0); opacity: 1; }
}

.modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 1.5rem;
    border-bottom: 1px solid #e9ecef;
}

.modal-header h3 {
    margin: 0;
    color: #2c3e50;
}

.close {
    font-size: 1.5rem;
    font-weight: bold;
    cursor: pointer;
    color: #6c757d;
}

.close:hover {
    color: #2c3e50;
}

.modal-body {
    padding: 1.5rem;
}

/* Pagination */
.pagination {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-top: 2rem;
    padding-top: 1.5rem;
    border-top: 1px solid #e9ecef;
}

.pagination-info {
    color: #6c757d;
    font-size: 0.9rem;
}

.pagination-links {
    display: flex;
    gap: 0.5rem;
    align-items: center;
}

.page-link {
    padding: 0.5rem 0.75rem;
    border: 1px solid #e9ecef;
    border-radius: 4px;
    color: #2c3e50;
    text-decoration: none;
    font-size: 0.9rem;
    transition: all 0.3s;
}

.page-link:hover {
    background: #4CAF50;
    color: white;
    border-color: #4CAF50;
}

.page-link.active {
    background: #4CAF50;
    color: white;
    border-color: #4CAF50;
}

/* Responsive Design */
@media (max-width: 1024px) {
    .admin-sidebar {
        width: 250px;
    }
    .admin-main {
        margin-left: 250px;
    }
}

@media (max-width: 768px) {
    .admin-container {
        flex-direction: column;
    }
    
    .admin-sidebar {
        width: 100%;
        height: auto;
        position: relative;
    }
    
    .admin-main {
        margin-left: 0;
    }
    
    .filter-row {
        grid-template-columns: 1fr;
    }
    
    .form-row {
        grid-template-columns: 1fr;
    }
    
    .admin-header {
        flex-direction: column;
        gap: 1rem;
        align-items: flex-start;
    }
    
    .product-info {
        flex-direction: column;
        text-align: center;
    }
    
    .action-buttons {
        flex-direction: column;
    }
    
    .bulk-actions {
        flex-direction: column;
        align-items: stretch;
    }
    
    .pagination {
        flex-direction: column;
        gap: 1rem;
        text-align: center;
    }
}

@media (max-width: 480px) {
    .admin-content {
        padding: 1rem;
    }
    
    .stats-grid {
        grid-template-columns: 1fr;
    }
    
    .card-header {
        flex-direction: column;
        gap: 1rem;
        align-items: flex-start;
    }
    
    .product-meta {
        flex-direction: column;
        gap: 0.25rem;
    }
    
    .pagination-links {
        flex-wrap: wrap;
        justify-content: center;
    }
}
</style>
<div class="admin-container">
    <!-- Enhanced Sidebar -->
    <div class="admin-sidebar">
        <div class="sidebar-header">
            <h3>🍎 Quick Basket</h3>
            <p>Admin Panel</p>
        </div>
        <nav class="sidebar-nav">
            <ul>
                <li class="nav-item">
                    <a href="index.php" class="nav-link">
                        <span class="nav-icon">📊</span>
                        <span class="nav-text">Dashboard</span>
                    </a>
                </li>
                <li class="nav-item active">
                    <a href="products.php" class="nav-link">
                        <span class="nav-icon">📦</span>
                        <span class="nav-text">Products</span>
                        <?php if ($stats['low_stock'] > 0): ?>
                            <span class="nav-badge"><?php echo $stats['low_stock']; ?></span>
                        <?php endif; ?>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="categories.php" class="nav-link">
                        <span class="nav-icon">🏷️</span>
                        <span class="nav-text">Categories</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="orders.php" class="nav-link">
                        <span class="nav-icon">🛒</span>
                        <span class="nav-text">Orders</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="users.php" class="nav-link">
                        <span class="nav-icon">👥</span>
                        <span class="nav-text">Users</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="../index.php" class="nav-link">
                        <span class="nav-icon">🏠</span>
                        <span class="nav-text">Back to Site</span>
                    </a>
                </li>
            </ul>
        </nav>
    </div>

    <!-- Main Content -->
    <div class="admin-main">
        <div class="admin-header">
            <h1>Product Management</h1>
            <div class="admin-user">
                <span>Welcome, <?php echo $_SESSION['user_name']; ?></span>
                <div class="user-dropdown">
                    <a href="../profile.php">Profile</a>
                    <a href="../logout.php">Logout</a>
                </div>
            </div>
        </div>

        <div class="admin-content">
            <!-- Notifications -->
            <?php if (isset($_SESSION['success'])): ?>
                <div class="alert alert-success">
                    <div class="alert-content">
                        <span class="alert-icon">✅</span>
                        <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
                    </div>
                </div>
            <?php endif; ?>

            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert alert-error">
                    <div class="alert-content">
                        <span class="alert-icon">❌</span>
                        <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Product Statistics -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon total">📦</div>
                    <div class="stat-info">
                        <h3><?php echo $stats['total']; ?></h3>
                        <p>Total Products</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon in-stock">✅</div>
                    <div class="stat-info">
                        <h3><?php echo $stats['in_stock']; ?></h3>
                        <p>In Stock</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon out-of-stock">❌</div>
                    <div class="stat-info">
                        <h3><?php echo $stats['out_of_stock']; ?></h3>
                        <p>Out of Stock</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon low-stock">⚠️</div>
                    <div class="stat-info">
                        <h3><?php echo $stats['low_stock']; ?></h3>
                        <p>Low Stock</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon categories">🏷️</div>
                    <div class="stat-info">
                        <h3><?php echo $stats['total_categories']; ?></h3>
                        <p>Categories</p>
                    </div>
                </div>
            </div>

            <!-- Filters and Search -->
            <div class="card">
                <div class="card-header">
                    <h3>Filters & Search</h3>
                </div>
                <div class="card-body">
                    <form method="GET" action="" class="filters-form">
                        <div class="filter-row">
                            <div class="filter-group">
                                <input type="text" name="search" placeholder="Search products..." 
                                       value="<?php echo htmlspecialchars($search); ?>" class="search-input">
                            </div>
                            <div class="filter-group">
                                <select name="category" class="filter-select">
                                    <option value="">All Categories</option>
                                    <?php foreach($categories as $category): ?>
                                        <option value="<?php echo $category['id']; ?>" 
                                            <?php echo $category_filter == $category['id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($category['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="filter-group">
                                <select name="stock" class="filter-select">
                                    <option value="">All Stock Status</option>
                                    <option value="in_stock" <?php echo $stock_filter == 'in_stock' ? 'selected' : ''; ?>>In Stock</option>
                                    <option value="out_of_stock" <?php echo $stock_filter == 'out_of_stock' ? 'selected' : ''; ?>>Out of Stock</option>
                                    <option value="low_stock" <?php echo $stock_filter == 'low_stock' ? 'selected' : ''; ?>>Low Stock</option>
                                </select>
                            </div>
                            <div class="filter-group">
                                <select name="sort" class="filter-select">
                                    <option value="id_desc" <?php echo $sort_by == 'id_desc' ? 'selected' : ''; ?>>Newest First</option>
                                    <option value="oldest" <?php echo $sort_by == 'oldest' ? 'selected' : ''; ?>>Oldest First</option>
                                    <option value="name_asc" <?php echo $sort_by == 'name_asc' ? 'selected' : ''; ?>>Name A-Z</option>
                                    <option value="name_desc" <?php echo $sort_by == 'name_desc' ? 'selected' : ''; ?>>Name Z-A</option>
                                    <option value="price_asc" <?php echo $sort_by == 'price_asc' ? 'selected' : ''; ?>>Price Low to High</option>
                                    <option value="price_desc" <?php echo $sort_by == 'price_desc' ? 'selected' : ''; ?>>Price High to Low</option>
                                    <option value="stock_desc" <?php echo $sort_by == 'stock_desc' ? 'selected' : ''; ?>>Stock High to Low</option>
                                    <option value="stock_asc" <?php echo $sort_by == 'stock_asc' ? 'selected' : ''; ?>>Stock Low to High</option>
                                </select>
                            </div>
                            <div class="filter-actions">
                                <button type="submit" class="btn btn-primary">Apply Filters</button>
                                <a href="products.php" class="btn btn-secondary">Reset</a>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Add Product Form -->
            <div class="card">
                <div class="card-header">
                    <h3>Add New Product</h3>
                    <button type="button" class="btn btn-secondary btn-sm" onclick="toggleProductForm()">
                        <span id="formToggleIcon">▼</span> Toggle Form
                    </button>
                </div>
                <div class="card-body" id="productForm" style="display: block;">
                    <form method="POST" action="" enctype="multipart/form-data" class="product-form">
                        <div class="form-row">
                            <div class="form-group">
                                <label for="name">Product Name *</label>
                                <input type="text" id="name" name="name" required>
                            </div>
                            <div class="form-group">
                                <label for="price">Price ($) *</label>
                                <input type="number" id="price" name="price" step="0.01" min="0" required>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="description">Description *</label>
                            <textarea id="description" name="description" rows="3" required></textarea>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="category_id">Category *</label>
                                <select id="category_id" name="category_id" required>
                                    <option value="">Select Category</option>
                                    <?php foreach($categories as $category): ?>
                                        <option value="<?php echo $category['id']; ?>"><?php echo htmlspecialchars($category['name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="stock_quantity">Stock Quantity *</label>
                                <input type="number" id="stock_quantity" name="stock_quantity" min="0" required>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="image">Product Image</label>
                            <div class="file-upload">
                                <input type="file" id="image" name="image" accept="image/*" onchange="previewImage(this)">
                                <div class="upload-preview" id="imagePreview"></div>
                                <small class="file-help">Supported formats: JPG, JPEG, PNG, GIF, WEBP (Max: 5MB)</small>
                            </div>
                        </div>
                        
                        <div class="form-actions">
                            <button type="submit" name="add_product" class="btn btn-primary">
                                <span class="btn-icon">➕</span>
                                Add Product
                            </button>
                            <button type="reset" class="btn btn-secondary">Reset Form</button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Products List -->
            <div class="card">
                <div class="card-header">
                    <h3>All Products (<?php echo $total_products; ?>)</h3>
                    <div class="card-actions">
                        <!-- Bulk Actions -->
                        <div class="bulk-actions">
                            <select id="bulkAction" class="bulk-select">
                                <option value="">Bulk Actions</option>
                                <option value="delete">Delete Selected</option>
                                <option value="out_of_stock">Mark as Out of Stock</option>
                                <option value="in_stock">Restock (100 units)</option>
                            </select>
                            <button type="button" onclick="applyBulkAction()" class="btn btn-secondary btn-sm">Apply</button>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <?php if (empty($products)): ?>
                        <div class="empty-state">
                            <div class="empty-icon">📦</div>
                            <h3>No Products Found</h3>
                            <p><?php echo $total_products > 0 ? 'Try adjusting your filters.' : 'Get started by adding your first product!'; ?></p>
                        </div>
                    <?php else: ?>
                        <form id="bulkForm" method="POST" action="">
                            <div class="table-container">
                                <table class="data-table">
                                    <thead>
                                        <tr>
                                            <th width="30">
                                                <input type="checkbox" id="selectAll" onchange="toggleSelectAll(this)">
                                            </th>
                                            <th>Product</th>
                                            <th>Category</th>
                                            <th>Price</th>
                                            <th>Stock</th>
                                            <th>Orders</th>
                                            <th>Status</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach($products as $product): ?>
                                        <tr class="product-row <?php echo $product['stock_quantity'] == 0 ? 'out-of-stock' : ($product['stock_quantity'] < 10 ? 'low-stock' : ''); ?>">
                                            <td>
                                                <input type="checkbox" name="product_ids[]" value="<?php echo $product['id']; ?>" class="product-checkbox">
                                            </td>
                                            <td>
                                                <div class="product-info">
                                                    <div class="product-image">
                                                        <?php if($product['image']): ?>
                                                            <img src="../images/products/<?php echo $product['image']; ?>" 
                                                                 alt="<?php echo htmlspecialchars($product['name']); ?>">
                                                        <?php else: ?>
                                                            <div class="no-image">No Image</div>
                                                        <?php endif; ?>
                                                    </div>
                                                    <div class="product-details">
                                                        <strong class="product-name"><?php echo htmlspecialchars($product['name']); ?></strong>
                                                        <p class="product-desc"><?php echo htmlspecialchars(substr($product['description'], 0, 80)); ?>...</p>
                                                        <div class="product-meta">
                                                            <span class="product-id">#<?php echo $product['id']; ?></span>
                                                            <span class="product-date">Added: <?php echo date('M j, Y', strtotime($product['created_at'])); ?></span>
                                                        </div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <span class="category-tag"><?php echo $product['category_name'] ?: 'Uncategorized'; ?></span>
                                            </td>
                                            <td>
                                                <div class="price">$<?php echo number_format($product['price'], 2); ?></div>
                                            </td>
                                            <td>
                                                <div class="stock-info">
                                                    <span class="stock-quantity <?php echo $product['stock_quantity'] == 0 ? 'out-of-stock' : ($product['stock_quantity'] < 10 ? 'low-stock' : 'in-stock'); ?>">
                                                        <?php echo $product['stock_quantity']; ?>
                                                    </span>
                                                    <?php if ($product['stock_quantity'] < 10 && $product['stock_quantity'] > 0): ?>
                                                        <div class="stock-warning">Low stock!</div>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                            <td>
                                                <div class="order-count">
                                                    <span class="count"><?php echo $product['order_count']; ?></span>
                                                    <span class="label">orders</span>
                                                </div>
                                            </td>
                                            <td>
                                                <span class="status-badge status-<?php echo $product['stock_quantity'] == 0 ? 'out-of-stock' : ($product['stock_quantity'] < 10 ? 'low-stock' : 'in-stock'); ?>">
                                                    <?php echo $product['stock_quantity'] == 0 ? 'Out of Stock' : ($product['stock_quantity'] < 10 ? 'Low Stock' : 'In Stock'); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div class="action-buttons">
                                                    <a href="edit-product.php?id=<?php echo $product['id']; ?>" class="btn btn-primary btn-sm" title="Edit Product">
                                                        <span class="btn-icon">✏️</span>
                                                        Edit
                                                    </a>
                                                    <button type="button" onclick="quickEdit(<?php echo $product['id']; ?>)" class="btn btn-secondary btn-sm" title="Quick Edit">
                                                        <span class="btn-icon">⚡</span>
                                                        Quick
                                                    </button>
                                                    <form method="POST" action="" style="display: inline;">
                                                        <input type="hidden" name="product_id" value="<?php echo $product['id']; ?>">
                                                        <button type="submit" name="delete_product" 
                                                                class="btn btn-danger btn-sm"
                                                                onclick="return confirmDelete(<?php echo $product['id']; ?>, <?php echo $product['order_count']; ?>)"
                                                                title="Delete Product">
                                                            <span class="btn-icon">🗑️</span>
                                                            Delete
                                                        </button>
                                                    </form>
                                                </div>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>

                            <!-- Bulk Action Form -->
                            <input type="hidden" name="bulk_action" id="bulkActionValue">
                        </form>

                        <!-- Pagination -->
                        <?php if ($total_pages > 1): ?>
                        <div class="pagination">
                            <div class="pagination-info">
                                Showing <?php echo (($page - 1) * $per_page) + 1; ?> - 
                                <?php echo min($page * $per_page, $total_products); ?> of <?php echo $total_products; ?> products
                            </div>
                            <div class="pagination-links">
                                <?php if ($page > 1): ?>
                                    <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => 1])); ?>" class="page-link first">First</a>
                                    <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>" class="page-link prev">Previous</a>
                                <?php endif; ?>
                                
                                <div class="page-numbers">
                                    <?php 
                                    $start_page = max(1, $page - 2);
                                    $end_page = min($total_pages, $page + 2);
                                    
                                    for ($i = $start_page; $i <= $end_page; $i++): 
                                    ?>
                                        <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>" 
                                           class="page-link <?php echo $i == $page ? 'active' : ''; ?>">
                                            <?php echo $i; ?>
                                        </a>
                                    <?php endfor; ?>
                                </div>
                                
                                <?php if ($page < $total_pages): ?>
                                    <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>" class="page-link next">Next</a>
                                    <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $total_pages])); ?>" class="page-link last">Last</a>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Quick Edit Modal -->
<div id="quickEditModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Quick Edit Product</h3>
            <span class="close" onclick="closeQuickEdit()">&times;</span>
        </div>
        <div class="modal-body">
            <form id="quickEditForm" method="POST" action="">
                <input type="hidden" name="product_id" id="quick_edit_id">
                <div class="form-row">
                    <div class="form-group">
                        <label for="quick_name">Product Name</label>
                        <input type="text" id="quick_name" name="name" required>
                    </div>
                    <div class="form-group">
                        <label for="quick_price">Price ($)</label>
                        <input type="number" id="quick_price" name="price" step="0.01" min="0" required>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label for="quick_stock">Stock Quantity</label>
                        <input type="number" id="quick_stock" name="stock_quantity" min="0" required>
                    </div>
                    <div class="form-group">
                        <label for="quick_category">Category</label>
                        <select id="quick_category" name="category_id" required>
                            <?php foreach($categories as $category): ?>
                                <option value="<?php echo $category['id']; ?>"><?php echo htmlspecialchars($category['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="form-actions">
                    <button type="submit" name="update_product" class="btn btn-primary">Update Product</button>
                    <button type="button" onclick="closeQuickEdit()" class="btn btn-secondary">Cancel</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ADD THE COMPREHENSIVE CSS HERE -->
<style>
/* COMPREHENSIVE PRODUCT MANAGEMENT CSS */
/* ... [PASTE THE ENTIRE CSS FROM MY PREVIOUS RESPONSE HERE] ... */
</style>

<script>
// Your existing JavaScript code here
function toggleProductForm() {
    const form = document.getElementById('productForm');
    const icon = document.getElementById('formToggleIcon');
    if (form.style.display === 'none') {
        form.style.display = 'block';
        icon.textContent = '▼';
    } else {
        form.style.display = 'none';
        icon.textContent = '▶';
    }
}

function previewImage(input) {
    const preview = document.getElementById('imagePreview');
    preview.innerHTML = '';
    
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = function(e) {
            const img = document.createElement('img');
            img.src = e.target.result;
            preview.appendChild(img);
        }
        reader.readAsDataURL(input.files[0]);
    }
}

function toggleSelectAll(checkbox) {
    const checkboxes = document.querySelectorAll('.product-checkbox');
    checkboxes.forEach(cb => {
        cb.checked = checkbox.checked;
    });
}

function applyBulkAction() {
    const action = document.getElementById('bulkAction').value;
    const checkboxes = document.querySelectorAll('.product-checkbox:checked');
    
    if (!action) {
        alert('Please select a bulk action.');
        return;
    }
    
    if (checkboxes.length === 0) {
        alert('Please select at least one product.');
        return;
    }
    
    if (action === 'delete') {
        if (!confirm(`Are you sure you want to delete ${checkboxes.length} product(s)? This action cannot be undone.`)) {
            return;
        }
    }
    
    document.getElementById('bulkActionValue').value = action;
    document.getElementById('bulkForm').submit();
}

function quickEdit(productId) {
    document.getElementById('quick_edit_id').value = productId;
    document.getElementById('quickEditModal').style.display = 'block';
    
    setTimeout(() => {
        document.getElementById('quick_name').value = 'Loading...';
        document.getElementById('quick_price').value = '0';
        document.getElementById('quick_stock').value = '0';
    }, 100);
}

function closeQuickEdit() {
    document.getElementById('quickEditModal').style.display = 'none';
}

function confirmDelete(productId, orderCount) {
    if (orderCount > 0) {
        alert(`Cannot delete product #${productId} because it has been ordered ${orderCount} time(s).\n\nYou can set stock to 0 instead.`);
        return false;
    }
    
    return confirm(`Are you sure you want to delete product #${productId}?\n\nThis action cannot be undone.`);
}

window.addEventListener('click', function(event) {
    const modals = document.querySelectorAll('.modal');
    modals.forEach(modal => {
        if (event.target == modal) {
            modal.style.display = 'none';
        }
    });
});

document.addEventListener('DOMContentLoaded', function() {
    const autoSubmitFields = document.querySelectorAll('select[name="sort"]');
    autoSubmitFields.forEach(field => {
        field.addEventListener('change', function() {
            this.form.submit();
        });
    });
});
</script>

<?php include 'admin-footer.php'; ?>