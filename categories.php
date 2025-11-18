<?php
include '../includes/config.php';
include 'admin-header.php';

// Check if user is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: ../login.php");
    exit;
}

// Handle category actions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['add_category'])) {
        $name = trim($_POST['name']);
        $description = trim($_POST['description']);
        
        // Check if category already exists
        $check_sql = "SELECT id FROM categories WHERE name = ?";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param("s", $name);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows > 0) {
            $_SESSION['error'] = "Category '$name' already exists!";
        } else {
            $sql = "INSERT INTO categories (name, description) VALUES (?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ss", $name, $description);
            
            if ($stmt->execute()) {
                $_SESSION['success'] = "Category '$name' added successfully!";
            } else {
                $_SESSION['error'] = "Failed to add category: " . $stmt->error;
            }
        }
        
    } elseif (isset($_POST['update_category'])) {
        $category_id = intval($_POST['category_id']);
        $name = trim($_POST['name']);
        $description = trim($_POST['description']);
        
        // Check if category name already exists (excluding current category)
        $check_sql = "SELECT id FROM categories WHERE name = ? AND id != ?";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param("si", $name, $category_id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows > 0) {
            $_SESSION['error'] = "Category '$name' already exists!";
        } else {
            $sql = "UPDATE categories SET name = ?, description = ? WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ssi", $name, $description, $category_id);
            
            if ($stmt->execute()) {
                $_SESSION['success'] = "Category updated successfully!";
            } else {
                $_SESSION['error'] = "Failed to update category: " . $stmt->error;
            }
        }
        
    } elseif (isset($_POST['delete_category'])) {
        $category_id = intval($_POST['category_id']);
        
        // Check if category has products
        $check_products = $conn->prepare("SELECT COUNT(*) as product_count FROM products WHERE category_id = ?");
        $check_products->bind_param("i", $category_id);
        $check_products->execute();
        $product_result = $check_products->get_result();
        $product_count = $product_result->fetch_assoc()['product_count'];
        
        if ($product_count > 0) {
            $_SESSION['error'] = "Cannot delete category because it has $product_count product(s). Please reassign or delete the products first.";
        } else {
            $sql = "DELETE FROM categories WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $category_id);
            
            if ($stmt->execute()) {
                $_SESSION['success'] = "Category deleted successfully!";
            } else {
                $_SESSION['error'] = "Failed to delete category: " . $stmt->error;
            }
        }
    }
}

// Fetch all categories with product counts
$categories = [];
$sql = "SELECT c.*, 
               COUNT(p.id) as product_count,
               (SELECT COUNT(*) FROM products p2 WHERE p2.category_id = c.id AND p2.stock_quantity > 0) as in_stock_count
        FROM categories c 
        LEFT JOIN products p ON c.id = p.category_id 
        GROUP BY c.id 
        ORDER BY c.name";
$result = $conn->query($sql);

if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $categories[] = $row;
    }
}

// Get category statistics
$stats = [
    'total' => count($categories),
    'total_products' => $conn->query("SELECT COUNT(*) as total FROM products")->fetch_assoc()['total'],
    'uncategorized' => $conn->query("SELECT COUNT(*) as total FROM products WHERE category_id IS NULL")->fetch_assoc()['total']
];
?>

<div class="admin-container">
    <!-- Sidebar -->
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
                <li class="nav-item">
                    <a href="products.php" class="nav-link">
                        <span class="nav-icon">📦</span>
                        <span class="nav-text">Products</span>
                    </a>
                </li>
                <li class="nav-item active">
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
            <h1>Category Management</h1>
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

            <!-- Category Statistics -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon total">🏷️</div>
                    <div class="stat-info">
                        <h3><?php echo $stats['total']; ?></h3>
                        <p>Total Categories</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon products">📦</div>
                    <div class="stat-info">
                        <h3><?php echo $stats['total_products']; ?></h3>
                        <p>Total Products</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon warning">⚠️</div>
                    <div class="stat-info">
                        <h3><?php echo $stats['uncategorized']; ?></h3>
                        <p>Uncategorized Products</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon average">📊</div>
                    <div class="stat-info">
                        <h3><?php echo $stats['total'] > 0 ? round($stats['total_products'] / $stats['total'], 1) : 0; ?></h3>
                        <p>Avg. Products/Category</p>
                    </div>
                </div>
            </div>

            <div class="categories-layout">
                <!-- Left Column - Add Category Form -->
                <div class="left-column">
                    <div class="card">
                        <div class="card-header">
                            <h3>Add New Category</h3>
                        </div>
                        <div class="card-body">
                            <form method="POST" action="" class="category-form">
                                <div class="form-group">
                                    <label for="name">Category Name *</label>
                                    <input type="text" id="name" name="name" required 
                                           placeholder="Enter category name (e.g., Fruits, Vegetables)">
                                </div>
                                
                                <div class="form-group">
                                    <label for="description">Description</label>
                                    <textarea id="description" name="description" rows="3" 
                                              placeholder="Enter category description (optional)"></textarea>
                                </div>
                                
                                <div class="form-actions">
                                    <button type="submit" name="add_category" class="btn btn-primary">
                                        <span class="btn-icon">➕</span>
                                        Add Category
                                    </button>
                                    <button type="reset" class="btn btn-secondary">Clear Form</button>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- Quick Actions -->
                    <div class="card">
                        <div class="card-header">
                            <h3>Quick Actions</h3>
                        </div>
                        <div class="card-body">
                            <div class="quick-actions">
                                <a href="products.php?filter=uncategorized" class="btn btn-warning btn-block">
                                    <span class="btn-icon">📦</span>
                                    View Uncategorized Products
                                </a>
                                <a href="products.php?action=add" class="btn btn-secondary btn-block">
                                    <span class="btn-icon">➕</span>
                                    Add New Product
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Right Column - Categories List -->
                <div class="right-column">
                    <div class="card">
                        <div class="card-header">
                            <h3>All Categories (<?php echo count($categories); ?>)</h3>
                            <div class="card-actions">
                                <div class="search-box">
                                    <input type="text" id="searchCategories" placeholder="Search categories...">
                                    <span class="search-icon">🔍</span>
                                </div>
                            </div>
                        </div>
                        <div class="card-body">
                            <?php if (empty($categories)): ?>
                                <div class="empty-state">
                                    <div class="empty-icon">🏷️</div>
                                    <h3>No Categories Found</h3>
                                    <p>Get started by adding your first category!</p>
                                </div>
                            <?php else: ?>
                                <div class="categories-grid">
                                    <?php foreach($categories as $category): ?>
                                    <div class="category-card" data-category-id="<?php echo $category['id']; ?>">
                                        <div class="category-header">
                                            <h4 class="category-name"><?php echo htmlspecialchars($category['name']); ?></h4>
                                            <div class="category-actions">
                                                <button type="button" onclick="editCategory(<?php echo $category['id']; ?>)" 
                                                        class="btn btn-primary btn-sm" title="Edit Category">
                                                    <span class="btn-icon">✏️</span>
                                                </button>
                                                <form method="POST" action="" style="display: inline;">
                                                    <input type="hidden" name="category_id" value="<?php echo $category['id']; ?>">
                                                    <button type="submit" name="delete_category" 
                                                            class="btn btn-danger btn-sm"
                                                            onclick="return confirmDeleteCategory('<?php echo htmlspecialchars($category['name']); ?>', <?php echo $category['product_count']; ?>)"
                                                            title="Delete Category">
                                                        <span class="btn-icon">🗑️</span>
                                                    </button>
                                                </form>
                                            </div>
                                        </div>
                                        
                                        <div class="category-body">
                                            <?php if ($category['description']): ?>
                                                <p class="category-description"><?php echo htmlspecialchars($category['description']); ?></p>
                                            <?php else: ?>
                                                <p class="category-description no-description">No description provided</p>
                                            <?php endif; ?>
                                            
                                            <div class="category-stats">
                                                <div class="stat">
                                                    <span class="stat-value"><?php echo $category['product_count']; ?></span>
                                                    <span class="stat-label">Total Products</span>
                                                </div>
                                                <div class="stat">
                                                    <span class="stat-value"><?php echo $category['in_stock_count']; ?></span>
                                                    <span class="stat-label">In Stock</span>
                                                </div>
                                                <div class="stat">
                                                    <span class="stat-value"><?php echo $category['product_count'] - $category['in_stock_count']; ?></span>
                                                    <span class="stat-label">Out of Stock</span>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="category-footer">
                                            <a href="products.php?category=<?php echo $category['id']; ?>" class="view-products-btn">
                                                View Products →
                                            </a>
                                            <span class="category-id">ID: #<?php echo $category['id']; ?></span>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Edit Category Modal -->
<div id="editCategoryModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Edit Category</h3>
            <span class="close" onclick="closeEditModal()">&times;</span>
        </div>
        <div class="modal-body">
            <form id="editCategoryForm" method="POST" action="">
                <input type="hidden" name="category_id" id="edit_category_id">
                <div class="form-group">
                    <label for="edit_name">Category Name *</label>
                    <input type="text" id="edit_name" name="name" required>
                </div>
                
                <div class="form-group">
                    <label for="edit_description">Description</label>
                    <textarea id="edit_description" name="description" rows="3"></textarea>
                </div>
                
                <div class="form-actions">
                    <button type="submit" name="update_category" class="btn btn-primary">Update Category</button>
                    <button type="button" onclick="closeEditModal()" class="btn btn-secondary">Cancel</button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
/* Category Management Specific Styles */
.categories-layout {
    display: grid;
    grid-template-columns: 400px 1fr;
    gap: 2rem;
}

.left-column {
    display: flex;
    flex-direction: column;
    gap: 1.5rem;
}

.right-column {
    display: flex;
    flex-direction: column;
    gap: 1.5rem;
}

/* Category Form */
.category-form {
    display: flex;
    flex-direction: column;
    gap: 1.5rem;
}

/* Quick Actions */
.quick-actions {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
}

.btn-block {
    width: 100%;
    justify-content: center;
}

.btn-warning {
    background: linear-gradient(135deg, #ffc107, #e0a800);
    color: #212529;
}

.btn-warning:hover {
    background: linear-gradient(135deg, #e0a800, #d39e00);
}

/* Categories Grid */
.categories-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 1.5rem;
}

.category-card {
    background: white;
    border: 1px solid #e9ecef;
    border-radius: 12px;
    padding: 1.5rem;
    transition: all 0.3s ease;
    box-shadow: 0 2px 8px rgba(0,0,0,0.05);
}

.category-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 4px 15px rgba(0,0,0,0.1);
    border-color: #4CAF50;
}

.category-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 1rem;
}

.category-name {
    margin: 0;
    color: #2c3e50;
    font-size: 1.2rem;
    font-weight: 700;
    flex: 1;
    margin-right: 1rem;
}

.category-actions {
    display: flex;
    gap: 0.5rem;
}

.category-body {
    margin-bottom: 1.5rem;
}

.category-description {
    color: #6c757d;
    font-size: 0.9rem;
    line-height: 1.5;
    margin: 0 0 1rem 0;
}

.category-description.no-description {
    font-style: italic;
    color: #999;
}

.category-stats {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 0.75rem;
    padding: 1rem;
    background: #f8f9fa;
    border-radius: 8px;
}

.stat {
    text-align: center;
}

.stat-value {
    display: block;
    font-size: 1.3rem;
    font-weight: 700;
    color: #4CAF50;
    margin-bottom: 0.25rem;
}

.stat-label {
    font-size: 0.75rem;
    color: #6c757d;
    font-weight: 500;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.category-footer {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding-top: 1rem;
    border-top: 1px solid #e9ecef;
}

.view-products-btn {
    color: #4CAF50;
    text-decoration: none;
    font-weight: 500;
    font-size: 0.9rem;
    transition: color 0.3s;
}

.view-products-btn:hover {
    color: #45a049;
    text-decoration: underline;
}

.category-id {
    font-size: 0.8rem;
    color: #999;
    font-family: 'Courier New', monospace;
}

/* Statistics Icons */
.stat-icon.products { background: linear-gradient(135deg, #667eea, #764ba2); color: white; }
.stat-icon.warning { background: linear-gradient(135deg, #ffc107, #e0a800); color: white; }
.stat-icon.average { background: linear-gradient(135deg, #6f42c1, #5a3596); color: white; }

/* Search Box */
.search-box {
    position: relative;
    display: flex;
    align-items: center;
}

.search-box input {
    padding: 0.75rem 1rem 0.75rem 2.5rem;
    border: 1px solid #ddd;
    border-radius: 8px;
    font-size: 0.9rem;
    width: 250px;
    transition: all 0.3s;
}

.search-box input:focus {
    outline: none;
    border-color: #4CAF50;
    box-shadow: 0 0 0 2px rgba(76, 175, 80, 0.1);
}

.search-icon {
    position: absolute;
    left: 0.75rem;
    color: #6c757d;
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
    max-width: 500px;
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

/* Responsive Design */
@media (max-width: 1024px) {
    .categories-layout {
        grid-template-columns: 1fr;
    }
    
    .left-column {
        order: 2;
    }
    
    .right-column {
        order: 1;
    }
}

@media (max-width: 768px) {
    .categories-grid {
        grid-template-columns: 1fr;
    }
    
    .category-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 1rem;
    }
    
    .category-actions {
        align-self: flex-end;
    }
    
    .category-stats {
        grid-template-columns: repeat(3, 1fr);
    }
    
    .search-box input {
        width: 200px;
    }
}

@media (max-width: 480px) {
    .category-stats {
        grid-template-columns: 1fr;
        gap: 0.5rem;
    }
    
    .category-footer {
        flex-direction: column;
        gap: 0.5rem;
        align-items: flex-start;
    }
    
    .search-box input {
        width: 100%;
    }
}
</style>

<script>
// Search functionality
document.getElementById('searchCategories')?.addEventListener('input', function(e) {
    const searchTerm = e.target.value.toLowerCase();
    const categoryCards = document.querySelectorAll('.category-card');
    
    categoryCards.forEach(card => {
        const categoryName = card.querySelector('.category-name').textContent.toLowerCase();
        const categoryDesc = card.querySelector('.category-description').textContent.toLowerCase();
        
        if (categoryName.includes(searchTerm) || categoryDesc.includes(searchTerm)) {
            card.style.display = 'block';
        } else {
            card.style.display = 'none';
        }
    });
});

// Edit category modal
function editCategory(categoryId) {
    // In a real implementation, you would fetch category data via AJAX
    // For now, we'll redirect to a separate edit page or show a modal with current data
    const categoryCard = document.querySelector(`[data-category-id="${categoryId}"]`);
    const categoryName = categoryCard.querySelector('.category-name').textContent;
    const categoryDesc = categoryCard.querySelector('.category-description').textContent;
    
    document.getElementById('edit_category_id').value = categoryId;
    document.getElementById('edit_name').value = categoryName;
    document.getElementById('edit_description').value = categoryDesc === 'No description provided' ? '' : categoryDesc;
    
    document.getElementById('editCategoryModal').style.display = 'block';
}

function closeEditModal() {
    document.getElementById('editCategoryModal').style.display = 'none';
}

// Enhanced delete confirmation
function confirmDeleteCategory(categoryName, productCount) {
    if (productCount > 0) {
        alert(`Cannot delete category "${categoryName}" because it contains ${productCount} product(s).\n\nPlease reassign or delete the products first.`);
        return false;
    }
    
    return confirm(`Are you sure you want to delete the category "${categoryName}"?\n\nThis action cannot be undone.`);
}

// Close modal when clicking outside
window.addEventListener('click', function(event) {
    const modal = document.getElementById('editCategoryModal');
    if (event.target == modal) {
        modal.style.display = 'none';
    }
});

// Form validation
document.addEventListener('DOMContentLoaded', function() {
    const forms = document.querySelectorAll('form');
    
    forms.forEach(form => {
        form.addEventListener('submit', function(e) {
            const nameInput = this.querySelector('input[name="name"]');
            if (nameInput && !nameInput.value.trim()) {
                e.preventDefault();
                alert('Category name is required!');
                nameInput.focus();
                return false;
            }
        });
    });
});

// Auto-focus on search input
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('searchCategories');
    if (searchInput) {
        searchInput.focus();
    }
});

// Add loading state to forms
document.querySelectorAll('form').forEach(form => {
    form.addEventListener('submit', function() {
        const submitBtn = this.querySelector('button[type="submit"]');
        if (submitBtn) {
            submitBtn.innerHTML = '<span class="btn-loading"></span> Processing...';
            submitBtn.disabled = true;
        }
    });
});
</script>

<?php include 'admin-footer.php'; ?>