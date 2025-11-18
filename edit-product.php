<?php
include '../includes/config.php';
include 'admin-header.php';

// Check if user is admin
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? 'customer') != 'admin') {
    header("Location: ../login.php");
    exit;
}

// Get product ID from URL
if (!isset($_GET['id'])) {
    header("Location: products.php");
    exit;
}

$product_id = intval($_GET['id']);

// Fetch product data
$sql = "SELECT p.*, c.name as category_name 
        FROM products p 
        LEFT JOIN categories c ON p.category_id = c.id 
        WHERE p.id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $product_id);
$stmt->execute();
$result = $stmt->get_result();
$product = $result->fetch_assoc();

if (!$product) {
    echo "<div class='container'><div class='alert alert-error'>Product not found.</div></div>";
    include '../includes/footer.php';
    exit;
}

// Fetch categories for dropdown
$categories = [];
$categories_result = $conn->query("SELECT * FROM categories ORDER BY name");
if ($categories_result->num_rows > 0) {
    while($row = $categories_result->fetch_assoc()) {
        $categories[] = $row;
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = trim($_POST['name']);
    $description = trim($_POST['description']);
    $price = floatval($_POST['price']);
    $category_id = intval($_POST['category_id']);
    $stock_quantity = intval($_POST['stock_quantity']);
    
    // Handle image upload
    $image_name = $product['image']; // Keep current image by default
    
    if (isset($_FILES['image']) && $_FILES['image']['error'] == 0) {
        $allowed_types = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        $file_extension = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
        
        if (in_array($file_extension, $allowed_types)) {
            // Delete old image if exists
            if ($product['image'] && file_exists("../images/products/" . $product['image'])) {
                unlink("../images/products/" . $product['image']);
            }
            
            $image_name = time() . '_' . uniqid() . '.' . $file_extension;
            $upload_path = "../images/products/" . $image_name;
            
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
        $sql = "UPDATE products SET name=?, description=?, price=?, category_id=?, stock_quantity=?, image=? WHERE id=?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssdiisi", $name, $description, $price, $category_id, $stock_quantity, $image_name, $product_id);
        
        if ($stmt->execute()) {
            $_SESSION['success'] = "Product updated successfully!";
            header("Location: products.php");
            exit;
        } else {
            $_SESSION['error'] = "Failed to update product: " . $stmt->error;
        }
    }
}
?>

<style>
/* Edit Product Page Styles */
.container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 0 1rem;
}

.page-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 2rem;
    padding-bottom: 1rem;
    border-bottom: 2px solid #e9ecef;
    flex-wrap: wrap;
    gap: 1rem;
}

.breadcrumb {
    color: #6c757d;
    font-size: 0.9rem;
    width: 100%;
    margin-bottom: 0.5rem;
}

.breadcrumb a {
    color: #4CAF50;
    text-decoration: none;
    transition: color 0.3s;
}

.breadcrumb a:hover {
    color: #45a049;
    text-decoration: underline;
}

.breadcrumb span {
    color: #2c3e50;
    font-weight: 500;
}

.page-header h1 {
    margin: 0;
    color: #2c3e50;
    font-size: 2rem;
    font-weight: 700;
}

/* Alert Styles */
.alert {
    padding: 1rem 1.5rem;
    border-radius: 8px;
    margin-bottom: 2rem;
    border: 1px solid transparent;
    animation: slideDown 0.3s ease;
}

@keyframes slideDown {
    from { transform: translateY(-10px); opacity: 0; }
    to { transform: translateY(0); opacity: 1; }
}

.alert-error {
    background: #f8d7da;
    border-color: #f5c6cb;
    color: #721c24;
}

.alert-warning {
    background: #fff3cd;
    border-color: #ffeaa7;
    color: #856404;
}

.alert-content {
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.alert-icon {
    font-size: 1.2rem;
}

/* Main Layout */
.edit-product-form {
    display: grid;
    grid-template-columns: 2fr 1fr;
    gap: 2rem;
    margin-bottom: 3rem;
}

.form-container {
    background: white;
    padding: 2rem;
    border-radius: 12px;
    box-shadow: 0 2px 20px rgba(0,0,0,0.08);
    border: 1px solid #e9ecef;
}

.product-stats {
    display: flex;
    flex-direction: column;
    gap: 1.5rem;
}

/* Form Styles */
.product-form {
    display: flex;
    flex-direction: column;
    gap: 1.5rem;
}

.form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1.5rem;
}

.form-group {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.form-group label {
    font-weight: 600;
    color: #2c3e50;
    font-size: 0.95rem;
    margin-bottom: 0.25rem;
}

.form-group input,
.form-group select,
.form-group textarea {
    padding: 0.75rem 1rem;
    border: 2px solid #e9ecef;
    border-radius: 8px;
    font-size: 1rem;
    transition: all 0.3s ease;
    background: white;
    font-family: inherit;
}

.form-group input:focus,
.form-group select:focus,
.form-group textarea:focus {
    outline: none;
    border-color: #4CAF50;
    box-shadow: 0 0 0 3px rgba(76, 175, 80, 0.1);
    background: #f8fff8;
}

.form-group textarea {
    resize: vertical;
    min-height: 100px;
    line-height: 1.5;
}

/* Button Styles */
.btn {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.75rem 1.5rem;
    border: none;
    border-radius: 8px;
    font-size: 0.95rem;
    font-weight: 600;
    text-decoration: none;
    cursor: pointer;
    transition: all 0.3s ease;
    font-family: inherit;
    justify-content: center;
}

.btn-primary {
    background: linear-gradient(135deg, #4CAF50, #45a049);
    color: white;
    box-shadow: 0 2px 10px rgba(76, 175, 80, 0.3);
}

.btn-primary:hover {
    background: linear-gradient(135deg, #45a049, #3d8b40);
    transform: translateY(-2px);
    box-shadow: 0 4px 15px rgba(76, 175, 80, 0.4);
}

.btn-secondary {
    background: #6c757d;
    color: white;
}

.btn-secondary:hover {
    background: #5a6268;
    transform: translateY(-2px);
}

.btn-outline {
    background: transparent;
    color: #6c757d;
    border: 2px solid #6c757d;
}

.btn-outline:hover {
    background: #6c757d;
    color: white;
    transform: translateY(-2px);
}

.btn-danger {
    background: linear-gradient(135deg, #e74c3c, #c0392b);
    color: white;
}

.btn-danger:hover {
    background: linear-gradient(135deg, #c0392b, #a93226);
    transform: translateY(-2px);
}

.btn-sm {
    padding: 0.5rem 1rem;
    font-size: 0.85rem;
}

.btn-icon {
    font-size: 1rem;
}

/* Form Actions */
.form-actions {
    display: flex;
    gap: 1rem;
    margin-top: 1rem;
    padding-top: 1.5rem;
    border-top: 1px solid #e9ecef;
    flex-wrap: wrap;
}

.form-actions .btn {
    flex: 1;
    min-width: 120px;
}

/* Image Upload Section */
.image-upload-section {
    display: flex;
    flex-direction: column;
    gap: 1.5rem;
}

.current-image h4,
.new-image-upload h4 {
    margin: 0 0 1rem 0;
    color: #2c3e50;
    font-weight: 600;
    font-size: 1.1rem;
}

.image-preview {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 1rem;
    background: #f8f9fa;
    border-radius: 8px;
    border: 2px dashed #dee2e6;
}

.current-product-image {
    width: 100px;
    height: 100px;
    object-fit: cover;
    border-radius: 8px;
    border: 2px solid #4CAF50;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
}

.image-info {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.image-info span {
    color: #6c757d;
    font-size: 0.9rem;
    font-family: monospace;
}

.no-image-message {
    padding: 1rem;
    background: #f8f9fa;
    border-radius: 8px;
    border: 2px dashed #dee2e6;
    text-align: center;
    color: #6c757d;
}

.no-image-message p {
    margin: 0;
}

/* File Upload */
.file-upload {
    position: relative;
}

.file-upload input[type="file"] {
    position: absolute;
    left: -9999px;
    opacity: 0;
}

.upload-preview {
    border: 2px dashed #4CAF50;
    border-radius: 8px;
    padding: 2rem;
    text-align: center;
    cursor: pointer;
    transition: all 0.3s ease;
    background: #f8fff8;
    min-height: 150px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.upload-preview:hover {
    border-color: #45a049;
    background: #f0fff0;
    transform: translateY(-2px);
}

.upload-preview.has-image {
    border-style: solid;
    padding: 1rem;
}

.upload-preview img {
    max-width: 100%;
    max-height: 120px;
    border-radius: 6px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
}

.upload-placeholder {
    color: #6c757d;
}

.upload-icon {
    font-size: 2rem;
    display: block;
    margin-bottom: 0.5rem;
}

.upload-placeholder p {
    margin: 0.5rem 0;
    font-weight: 500;
    color: #2c3e50;
}

.upload-placeholder small {
    color: #6c757d;
}

/* Stats Card */
.stats-card {
    background: white;
    padding: 2rem;
    border-radius: 12px;
    box-shadow: 0 2px 20px rgba(0,0,0,0.08);
    border: 1px solid #e9ecef;
    height: fit-content;
}

.stats-card h3 {
    margin: 0 0 1.5rem 0;
    color: #2c3e50;
    font-size: 1.3rem;
    font-weight: 700;
    border-bottom: 2px solid #4CAF50;
    padding-bottom: 0.5rem;
}

.stats-grid {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.stat-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0.75rem 0;
    border-bottom: 1px solid #f8f9fa;
}

.stat-item:last-child {
    border-bottom: none;
}

.stat-item label {
    font-weight: 600;
    color: #2c3e50;
    font-size: 0.9rem;
}

.stat-item span {
    color: #6c757d;
    font-weight: 500;
    text-align: right;
}

/* Responsive Design */
@media (max-width: 1024px) {
    .edit-product-form {
        grid-template-columns: 1fr;
        gap: 1.5rem;
    }
    
    .stats-card {
        order: -1;
    }
}

@media (max-width: 768px) {
    .page-header {
        flex-direction: column;
        align-items: flex-start;
        text-align: center;
    }
    
    .form-row {
        grid-template-columns: 1fr;
        gap: 1rem;
    }
    
    .form-actions {
        flex-direction: column;
    }
    
    .form-actions .btn {
        width: 100%;
    }
    
    .image-preview {
        flex-direction: column;
        text-align: center;
    }
    
    .stat-item {
        flex-direction: column;
        align-items: flex-start;
        gap: 0.25rem;
    }
    
    .stat-item span {
        text-align: left;
    }
}

@media (max-width: 480px) {
    .container {
        padding: 0 0.5rem;
    }
    
    .form-container,
    .stats-card {
        padding: 1.5rem;
    }
    
    .page-header h1 {
        font-size: 1.5rem;
    }
    
    .upload-preview {
        padding: 1.5rem;
    }
}

/* Animation for form elements */
.form-group input,
.form-group select,
.form-group textarea {
    animation: fadeInUp 0.5s ease;
}

@keyframes fadeInUp {
    from {
        opacity: 0;
        transform: translateY(10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

/* Loading state for buttons */
.btn:disabled {
    opacity: 0.6;
    cursor: not-allowed;
    transform: none !important;
}

.btn-loading {
    position: relative;
    color: transparent;
}

.btn-loading::after {
    content: '';
    position: absolute;
    width: 16px;
    height: 16px;
    top: 50%;
    left: 50%;
    margin-left: -8px;
    margin-top: -8px;
    border: 2px solid #ffffff;
    border-radius: 50%;
    border-right-color: transparent;
    animation: button-loading 1s linear infinite;
}

@keyframes button-loading {
    from {
        transform: rotate(0);
    }
    to {
        transform: rotate(360deg);
    }
}

/* Success state */
.form-group.success input,
.form-group.success select,
.form-group.success textarea {
    border-color: #4CAF50;
    background: #f8fff8;
}

.form-group.error input,
.form-group.error select,
.form-group.error textarea {
    border-color: #e74c3c;
    background: #fff5f5;
}

/* Price input styling */
input[type="number"] {
    -moz-appearance: textfield;
}

input[type="number"]::-webkit-outer-spin-button,
input[type="number"]::-webkit-inner-spin-button {
    -webkit-appearance: none;
    margin: 0;
}

/* Enhanced select styling */
.form-group select {
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' fill='%236c757d' viewBox='0 0 16 16'%3E%3Cpath d='M7.247 11.14 2.451 5.658C1.885 5.013 2.345 4 3.204 4h9.592a1 1 0 0 1 .753 1.659l-4.796 5.48a1 1 0 0 1-1.506 0z'/%3E%3C/svg%3E");
    background-repeat: no-repeat;
    background-position: right 1rem center;
    background-size: 12px;
    padding-right: 2.5rem;
    appearance: none;
}

/* Textarea character count */
.textarea-wrapper {
    position: relative;
}

.char-count {
    position: absolute;
    bottom: 0.5rem;
    right: 0.5rem;
    font-size: 0.8rem;
    color: #6c757d;
    background: white;
    padding: 0.1rem 0.3rem;
    border-radius: 3px;
}

.char-count.warning {
    color: #e74c3c;
    font-weight: 600;
}
</style>

<div class="container">
    <div class="page-header">
        <div class="breadcrumb">
            <a href="../index.php">Home</a> / 
            <a href="index.php">Admin</a> / 
            <a href="products.php">Products</a> / 
            <span>Edit Product</span>
        </div>
        <h1>Edit Product #<?php echo $product['id']; ?></h1>
        <a href="products.php" class="btn btn-secondary">
            <span class="btn-icon">←</span>
            Back to Products
        </a>
    </div>

    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-error">
            <div class="alert-content">
                <span class="alert-icon">❌</span>
                <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
            </div>
        </div>
    <?php endif; ?>

    <div class="edit-product-form">
        <div class="form-container">
            <form method="POST" action="" enctype="multipart/form-data" class="product-form">
                <input type="hidden" name="product_id" value="<?php echo $product['id']; ?>">
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="name">Product Name *</label>
                        <input type="text" id="name" name="name" 
                               value="<?php echo htmlspecialchars($product['name']); ?>" 
                               required>
                    </div>
                    <div class="form-group">
                        <label for="price">Price ($) *</label>
                        <input type="number" id="price" name="price" 
                               value="<?php echo $product['price']; ?>" 
                               step="0.01" min="0" required>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="description">Description *</label>
                    <textarea id="description" name="description" rows="4" required><?php echo htmlspecialchars($product['description']); ?></textarea>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="category_id">Category *</label>
                        <select id="category_id" name="category_id" required>
                            <option value="">Select Category</option>
                            <?php foreach($categories as $category): ?>
                                <option value="<?php echo $category['id']; ?>" 
                                    <?php echo $product['category_id'] == $category['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($category['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="stock_quantity">Stock Quantity *</label>
                        <input type="number" id="stock_quantity" name="stock_quantity" 
                               value="<?php echo $product['stock_quantity']; ?>" 
                               min="0" required>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="image">Product Image</label>
                    <div class="image-upload-section">
                        <!-- Current Image Preview -->
                        <?php if ($product['image']): ?>
                            <div class="current-image">
                                <h4>Current Image:</h4>
                                <div class="image-preview">
                                    <img src="../images/products/<?php echo $product['image']; ?>" 
                                         alt="<?php echo htmlspecialchars($product['name']); ?>"
                                         class="current-product-image">
                                    <div class="image-info">
                                        <span><?php echo $product['image']; ?></span>
                                        <button type="button" onclick="removeCurrentImage()" class="btn btn-danger btn-sm">
                                            Remove Image
                                        </button>
                                    </div>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="no-image-message">
                                <p>No image currently set for this product.</p>
                            </div>
                        <?php endif; ?>
                        
                        <!-- New Image Upload -->
                        <div class="new-image-upload">
                            <h4>Upload New Image:</h4>
                            <div class="file-upload">
                                <input type="file" id="image" name="image" 
                                       accept="image/*" onchange="previewNewImage(this)">
                                <div class="upload-preview" id="imagePreview">
                                    <div class="upload-placeholder">
                                        <span class="upload-icon">📁</span>
                                        <p>Click to upload or drag and drop</p>
                                        <small>PNG, JPG, GIF, WEBP up to 5MB</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">
                        <span class="btn-icon">💾</span>
                        Update Product
                    </button>
                    <a href="products.php" class="btn btn-secondary">Cancel</a>
                    <button type="reset" class="btn btn-outline">Reset Changes</button>
                </div>
            </form>
        </div>
        
        <!-- Product Statistics -->
        <div class="product-stats">
            <div class="stats-card">
                <h3>Product Information</h3>
                <div class="stats-grid">
                    <div class="stat-item">
                        <label>Product ID:</label>
                        <span>#<?php echo $product['id']; ?></span>
                    </div>
                    <div class="stat-item">
                        <label>Created:</label>
                        <span><?php echo date('M j, Y', strtotime($product['created_at'])); ?></span>
                    </div>
                    <div class="stat-item">
                        <label>Last Updated:</label>
                        <span><?php echo date('M j, Y g:i A', strtotime($product['created_at'])); ?></span>
                    </div>
                    <div class="stat-item">
                        <label>Current Category:</label>
                        <span><?php echo htmlspecialchars($product['category_name'] ?: 'Uncategorized'); ?></span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// ... [YOUR EXISTING JAVASCRIPT CODE] ...

// Enhanced form validation with visual feedback
document.addEventListener('DOMContentLoaded', function() {
    const form = document.querySelector('.product-form');
    const inputs = form.querySelectorAll('input, select, textarea');
    
    inputs.forEach(input => {
        // Add focus effects
        input.addEventListener('focus', function() {
            this.parentElement.classList.add('focused');
        });
        
        input.addEventListener('blur', function() {
            this.parentElement.classList.remove('focused');
            validateField(this);
        });
        
        // Real-time validation for price and stock
        if (input.type === 'number') {
            input.addEventListener('input', function() {
                validateField(this);
            });
        }
    });
    
    // Enhanced form submission
    form.addEventListener('submit', function(e) {
        let isValid = true;
        
        inputs.forEach(input => {
            if (!validateField(input)) {
                isValid = false;
            }
        });
        
        if (!isValid) {
            e.preventDefault();
            // Add shake animation to invalid fields
            inputs.forEach(input => {
                if (!validateField(input)) {
                    input.style.animation = 'shake 0.5s ease';
                    setTimeout(() => {
                        input.style.animation = '';
                    }, 500);
                }
            });
        } else {
            // Add loading state to submit button
            const submitBtn = form.querySelector('button[type="submit"]');
            submitBtn.innerHTML = '<span class="btn-loading"></span>';
            submitBtn.disabled = true;
        }
    });
});

function validateField(field) {
    const value = field.value.trim();
    const formGroup = field.parentElement;
    
    // Remove existing validation classes
    formGroup.classList.remove('success', 'error');
    
    if (field.hasAttribute('required') && !value) {
        formGroup.classList.add('error');
        return false;
    }
    
    if (field.type === 'number') {
        const numValue = parseFloat(value);
        
        if (field.id === 'price' && numValue <= 0) {
            formGroup.classList.add('error');
            return false;
        }
        
        if (field.id === 'stock_quantity' && numValue < 0) {
            formGroup.classList.add('error');
            return false;
        }
    }
    
    formGroup.classList.add('success');
    return true;
}

// Add shake animation for invalid fields
const style = document.createElement('style');
style.textContent = `
    @keyframes shake {
        0%, 100% { transform: translateX(0); }
        25% { transform: translateX(-5px); }
        75% { transform: translateX(5px); }
    }
    
    .form-group.focused {
        transform: translateY(-2px);
    }
    
    .form-group.success label {
        color: #4CAF50;
    }
    
    .form-group.error label {
        color: #e74c3c;
    }
`;
document.head.appendChild(style);

// ... [REST OF YOUR EXISTING JAVASCRIPT] ...
</script>

<?php include 'admin-footer.php'; ?>
<div class="container">
    <div class="page-header">
        <div class="breadcrumb">
            <a href="../index.php">Home</a> / 
            <a href="index.php">Admin</a> / 
            <a href="products.php">Products</a> / 
            <span>Edit Product</span>
        </div>
        <h1>Edit Product #<?php echo $product['id']; ?></h1>
        <a href="products.php" class="btn btn-secondary">
            <span class="btn-icon">←</span>
            Back to Products
        </a>
    </div>

    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-error">
            <div class="alert-content">
                <span class="alert-icon">❌</span>
                <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
            </div>
        </div>
    <?php endif; ?>

    <div class="edit-product-form">
        <div class="form-container">
            <form method="POST" action="" enctype="multipart/form-data" class="product-form">
                <input type="hidden" name="product_id" value="<?php echo $product['id']; ?>">
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="name">Product Name *</label>
                        <input type="text" id="name" name="name" 
                               value="<?php echo htmlspecialchars($product['name']); ?>" 
                               required>
                    </div>
                    <div class="form-group">
                        <label for="price">Price ($) *</label>
                        <input type="number" id="price" name="price" 
                               value="<?php echo $product['price']; ?>" 
                               step="0.01" min="0" required>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="description">Description *</label>
                    <textarea id="description" name="description" rows="4" required><?php echo htmlspecialchars($product['description']); ?></textarea>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="category_id">Category *</label>
                        <select id="category_id" name="category_id" required>
                            <option value="">Select Category</option>
                            <?php foreach($categories as $category): ?>
                                <option value="<?php echo $category['id']; ?>" 
                                    <?php echo $product['category_id'] == $category['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($category['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="stock_quantity">Stock Quantity *</label>
                        <input type="number" id="stock_quantity" name="stock_quantity" 
                               value="<?php echo $product['stock_quantity']; ?>" 
                               min="0" required>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="image">Product Image</label>
                    <div class="image-upload-section">
                        <!-- Current Image Preview -->
                        <?php if ($product['image']): ?>
                            <div class="current-image">
                                <h4>Current Image:</h4>
                                <div class="image-preview">
                                    <img src="../images/products/<?php echo $product['image']; ?>" 
                                         alt="<?php echo htmlspecialchars($product['name']); ?>"
                                         class="current-product-image">
                                    <div class="image-info">
                                        <span><?php echo $product['image']; ?></span>
                                        <button type="button" onclick="removeCurrentImage()" class="btn btn-danger btn-sm">
                                            Remove Image
                                        </button>
                                    </div>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="no-image-message">
                                <p>No image currently set for this product.</p>
                            </div>
                        <?php endif; ?>
                        
                        <!-- New Image Upload -->
                        <div class="new-image-upload">
                            <h4>Upload New Image:</h4>
                            <div class="file-upload">
                                <input type="file" id="image" name="image" 
                                       accept="image/*" onchange="previewNewImage(this)">
                                <div class="upload-preview" id="imagePreview">
                                    <div class="upload-placeholder">
                                        <span class="upload-icon">📁</span>
                                        <p>Click to upload or drag and drop</p>
                                        <small>PNG, JPG, GIF, WEBP up to 5MB</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">
                        <span class="btn-icon">💾</span>
                        Update Product
                    </button>
                    <a href="products.php" class="btn btn-secondary">Cancel</a>
                    <button type="reset" class="btn btn-outline">Reset Changes</button>
                </div>
            </form>
        </div>
        
        <!-- Product Statistics -->
        <div class="product-stats">
            <div class="stats-card">
                <h3>Product Information</h3>
                <div class="stats-grid">
                    <div class="stat-item">
                        <label>Product ID:</label>
                        <span>#<?php echo $product['id']; ?></span>
                    </div>
                    <div class="stat-item">
                        <label>Created:</label>
                        <span><?php echo date('M j, Y', strtotime($product['created_at'])); ?></span>
                    </div>
                    <div class="stat-item">
                        <label>Last Updated:</label>
                        <span><?php echo date('M j, Y g:i A', strtotime($product['created_at'])); ?></span>
                    </div>
                    <div class="stat-item">
                        <label>Current Category:</label>
                        <span><?php echo htmlspecialchars($product['category_name'] ?: 'Uncategorized'); ?></span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Preview new image when selected
function previewNewImage(input) {
    const preview = document.getElementById('imagePreview');
    
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        
        reader.onload = function(e) {
            preview.innerHTML = '';
            preview.classList.add('has-image');
            
            const img = document.createElement('img');
            img.src = e.target.result;
            img.alt = 'New product image preview';
            
            const removeBtn = document.createElement('button');
            removeBtn.type = 'button';
            removeBtn.className = 'btn btn-danger btn-sm';
            removeBtn.style.marginTop = '0.5rem';
            removeBtn.innerHTML = 'Remove New Image';
            removeBtn.onclick = function() {
                input.value = '';
                preview.innerHTML = `
                    <div class="upload-placeholder">
                        <span class="upload-icon">📁</span>
                        <p>Click to upload or drag and drop</p>
                        <small>PNG, JPG, GIF, WEBP up to 5MB</small>
                    </div>
                `;
                preview.classList.remove('has-image');
            };
            
            const container = document.createElement('div');
            container.style.textAlign = 'center';
            container.appendChild(img);
            container.appendChild(removeBtn);
            
            preview.appendChild(container);
        }
        
        reader.readAsDataURL(input.files[0]);
    }
}

// Remove current image
function removeCurrentImage() {
    if (confirm('Are you sure you want to remove the current product image? This action cannot be undone.')) {
        document.querySelector('.current-image').style.display = 'none';
        
        // Show message that image will be removed on save
        const message = document.createElement('div');
        message.className = 'alert alert-warning';
        message.innerHTML = 'Current image will be removed when you save changes.';
        document.querySelector('.image-upload-section').insertBefore(message, document.querySelector('.new-image-upload'));
    }
}

// Make file upload area clickable
document.addEventListener('DOMContentLoaded', function() {
    const uploadPreview = document.getElementById('imagePreview');
    const fileInput = document.getElementById('image');
    
    if (uploadPreview && fileInput) {
        uploadPreview.addEventListener('click', function() {
            fileInput.click();
        });
    }
});

// Form validation
document.querySelector('.product-form').addEventListener('submit', function(e) {
    const price = document.getElementById('price').value;
    const stock = document.getElementById('stock_quantity').value;
    
    if (price <= 0) {
        e.preventDefault();
        alert('Price must be greater than 0.');
        return false;
    }
    
    if (stock < 0) {
        e.preventDefault();
        alert('Stock quantity cannot be negative.');
        return false;
    }
});
</script>

<?php include '../includes/footer.php'; ?>