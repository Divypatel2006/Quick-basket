<?php
include '../includes/config.php';
include 'admin-header.php';

// Check if user is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: ../index.php");
    exit;
}

$success = '';
$error = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['update_site_settings'])) {
        // Update site settings
        $site_name = $conn->real_escape_string($_POST['site_name']);
        $site_email = $conn->real_escape_string($_POST['site_email']);
        $currency = $conn->real_escape_string($_POST['currency']);
        
        // Create settings table if it doesn't exist
        $create_table_sql = "CREATE TABLE IF NOT EXISTS site_settings (
            id INT PRIMARY KEY AUTO_INCREMENT,
            setting_key VARCHAR(100) UNIQUE,
            setting_value TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )";
        $conn->query($create_table_sql);
        
        // Update or insert settings
        $settings = [
            'site_name' => $site_name,
            'site_email' => $site_email,
            'currency' => $currency
        ];
        
        foreach ($settings as $key => $value) {
            $sql = "INSERT INTO site_settings (setting_key, setting_value) 
                    VALUES ('$key', '$value') 
                    ON DUPLICATE KEY UPDATE setting_value = '$value'";
            $conn->query($sql);
        }
        
        $success = "Site settings updated successfully!";
        
    } elseif (isset($_POST['update_user_management'])) {
        // Check and create status column if needed
        $check_sql = "SHOW COLUMNS FROM users LIKE 'status'";
        $result = $conn->query($check_sql);
        
        if ($result->num_rows == 0) {
            // Add status column
            $alter_sql = "ALTER TABLE users ADD COLUMN status ENUM('active', 'inactive') DEFAULT 'active'";
            if ($conn->query($alter_sql)) {
                $update_sql = "UPDATE users SET status = 'active' WHERE status IS NULL";
                $conn->query($update_sql);
                $success = "User management system setup completed successfully!";
            } else {
                $error = "Failed to setup user management: " . $conn->error;
            }
        } else {
            $success = "User management system is already set up!";
        }
        
    } elseif (isset($_POST['clear_cache'])) {
        // Clear cache functionality
        // You can add cache clearing logic here
        $success = "Cache cleared successfully!";
        
    } elseif (isset($_POST['backup_database'])) {
        // Basic database backup functionality
        $backup_file = '../backups/db_backup_' . date('Y-m-d_H-i-s') . '.sql';
        
        // Create backups directory if it doesn't exist
        if (!is_dir('../backups')) {
            mkdir('../backups', 0755, true);
        }
        
        // Simple table backup (you can enhance this)
        $tables = ['users', 'products', 'orders', 'order_items', 'categories'];
        $backup_content = "";
        
        foreach ($tables as $table) {
            $result = $conn->query("SHOW TABLES LIKE '$table'");
            if ($result->num_rows > 0) {
                $backup_content .= "-- Table: $table\n";
                $table_data = $conn->query("SELECT * FROM $table");
                while ($row = $table_data->fetch_assoc()) {
                    $columns = implode("`, `", array_keys($row));
                    $values = implode("', '", array_map([$conn, 'real_escape_string'], array_values($row)));
                    $backup_content .= "INSERT INTO `$table` (`$columns`) VALUES ('$values');\n";
                }
                $backup_content .= "\n";
            }
        }
        
        if (file_put_contents($backup_file, $backup_content)) {
            $success = "Database backup created successfully: " . basename($backup_file);
        } else {
            $error = "Failed to create database backup";
        }
    }
}

// Get current settings


// Check if user management is set up
$check_user_sql = "SHOW COLUMNS FROM users LIKE 'status'";
$user_management_setup = ($conn->query($check_user_sql)->num_rows > 0);

// Get system info
$system_info = [
    'php_version' => PHP_VERSION,
    'mysql_version' => $conn->server_info,
    'server_software' => $_SERVER['SERVER_SOFTWARE'],
    'users_count' => $conn->query("SELECT COUNT(*) as count FROM users")->fetch_assoc()['count'],
    'products_count' => $conn->query("SELECT COUNT(*) as count FROM products")->fetch_assoc()['count'],
    'orders_count' => $conn->query("SELECT COUNT(*) as count FROM orders")->fetch_assoc()['count']
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
                <li class="nav-item active">
                    <a href="settings.php" class="nav-link">
                        <span class="nav-icon">⚙️</span>
                        <span class="nav-text">Settings</span>
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
    
    <div class="admin-main">
        <div class="admin-header">
            <h1>Admin Settings</h1>
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
            <?php if ($success): ?>
                <div class="alert alert-success">
                    <div class="alert-content">
                        <span class="alert-icon">✅</span>
                        <?php echo $success; ?>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="alert alert-error">
                    <div class="alert-content">
                        <span class="alert-icon">❌</span>
                        <?php echo $error; ?>
                    </div>
                </div>
            <?php endif; ?>

            <div class="settings-grid">
                <!-- Site Settings Card -->
                <div class="card">
                    <div class="card-header">
                        <h3>📝 Site Settings</h3>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="">
                            <div class="form-group">
                                <label for="site_name">Site Name</label>
                                <input type="text" id="site_name" name="site_name" 
                                       value="<?php echo isset($site_settings['site_name']) ? $site_settings['site_name'] : 'Quick Basket'; ?>" 
                                       class="form-control">
                            </div>
                            
                            <div class="form-group">
                                <label for="site_email">Site Email</label>
                                <input type="email" id="site_email" name="site_email" 
                                       value="<?php echo isset($site_settings['site_email']) ? $site_settings['site_email'] : 'admin@quickbasket.com'; ?>" 
                                       class="form-control">
                            </div>
                            
                            <div class="form-group">
                                <label for="currency">Currency</label>
                                <select id="currency" name="currency" class="form-control">
                                    <option value="$" <?php echo (isset($site_settings['currency']) && $site_settings['currency'] == '$') ? 'selected' : ''; ?>>USD ($)</option>
                                    <option value="€" <?php echo (isset($site_settings['currency']) && $site_settings['currency'] == '€') ? 'selected' : ''; ?>>Euro (€)</option>
                                    <option value="£" <?php echo (isset($site_settings['currency']) && $site_settings['currency'] == '£') ? 'selected' : ''; ?>>Pound (£)</option>
                                    <option value="₹" <?php echo (isset($site_settings['currency']) && $site_settings['currency'] == '₹') ? 'selected' : ''; ?>>Rupee (₹)</option>
                                </select>
                            </div>
                            
                            <button type="submit" name="update_site_settings" class="btn btn-primary">
                                <span class="btn-icon">💾</span>
                                Save Site Settings
                            </button>
                        </form>
                    </div>
                </div>

                <!-- User Management Card -->
                <div class="card">
                    <div class="card-header">
                        <h3>👥 User Management</h3>
                    </div>
                    <div class="card-body">
                        <div class="setting-status">
                            <div class="status-indicator <?php echo $user_management_setup ? 'status-active' : 'status-inactive'; ?>">
                                <span class="status-dot"></span>
                                <?php echo $user_management_setup ? 'Active' : 'Not Set Up'; ?>
                            </div>
                        </div>
                        
                        <p class="setting-description">
                            Enable user deactivation feature to manage users with orders without deleting them.
                        </p>
                        
                        <form method="POST" action="">
                            <button type="submit" name="update_user_management" 
                                    class="btn <?php echo $user_management_setup ? 'btn-secondary' : 'btn-primary'; ?>"
                                    <?php echo $user_management_setup ? 'disabled' : ''; ?>>
                                <span class="btn-icon">⚙️</span>
                                <?php echo $user_management_setup ? 'Already Set Up' : 'Setup User Management'; ?>
                            </button>
                        </form>
                    </div>
                </div>

                <!-- System Tools Card -->
                <div class="card">
                    <div class="card-header">
                        <h3>🛠️ System Tools</h3>
                    </div>
                    <div class="card-body">
                        <div class="tools-grid">
                            <form method="POST" action="" class="tool-form">
                                <button type="submit" name="clear_cache" class="btn btn-warning">
                                    <span class="btn-icon">🧹</span>
                                    Clear Cache
                                </button>
                            </form>
                            
                            <form method="POST" action="" class="tool-form">
                                <button type="submit" name="backup_database" class="btn btn-info">
                                    <span class="btn-icon">💾</span>
                                    Backup Database
                                </button>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- System Information Card -->
                <div class="card">
                    <div class="card-header">
                        <h3>📊 System Information</h3>
                    </div>
                    <div class="card-body">
                        <div class="info-grid">
                            <div class="info-item">
                                <span class="info-label">PHP Version:</span>
                                <span class="info-value"><?php echo $system_info['php_version']; ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">MySQL Version:</span>
                                <span class="info-value"><?php echo $system_info['mysql_version']; ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Server Software:</span>
                                <span class="info-value"><?php echo $system_info['server_software']; ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Total Users:</span>
                                <span class="info-value"><?php echo $system_info['users_count']; ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Total Products:</span>
                                <span class="info-value"><?php echo $system_info['products_count']; ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Total Orders:</span>
                                <span class="info-value"><?php echo $system_info['orders_count']; ?></span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Quick Actions Card -->
                <div class="card">
                    <div class="card-header">
                        <h3>🚀 Quick Actions</h3>
                    </div>
                    <div class="card-body">
                        <div class="actions-grid">
                            <a href="products.php?action=add" class="btn btn-success">
                                <span class="btn-icon">➕</span>
                                Add New Product
                            </a>
                            <a href="users.php" class="btn btn-primary">
                                <span class="btn-icon">👥</span>
                                Manage Users
                            </a>
                            <a href="orders.php" class="btn btn-info">
                                <span class="btn-icon">🛒</span>
                                View Orders
                            </a>
                            <a href="../index.php" class="btn btn-secondary">
                                <span class="btn-icon">🏠</span>
                                Visit Site
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.settings-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
    gap: 1.5rem;
    margin-bottom: 2rem;
}

.form-group {
    margin-bottom: 1.5rem;
}

.form-group label {
    display: block;
    margin-bottom: 0.5rem;
    font-weight: 600;
    color: #2c3e50;
}

.form-control {
    width: 100%;
    padding: 0.75rem;
    border: 1px solid #ddd;
    border-radius: 8px;
    font-size: 1rem;
    transition: all 0.3s;
}

.form-control:focus {
    outline: none;
    border-color: #4CAF50;
    box-shadow: 0 0 0 2px rgba(76, 175, 80, 0.1);
}

.setting-status {
    margin-bottom: 1rem;
}

.status-indicator {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.5rem 1rem;
    border-radius: 20px;
    font-size: 0.9rem;
    font-weight: 500;
}

.status-active {
    background: #d4edda;
    color: #155724;
    border: 1px solid #c3e6cb;
}

.status-inactive {
    background: #f8d7da;
    color: #721c24;
    border: 1px solid #f5c6cb;
}

.status-dot {
    width: 8px;
    height: 8px;
    border-radius: 50%;
}

.status-active .status-dot {
    background: #28a745;
}

.status-inactive .status-dot {
    background: #dc3545;
}

.setting-description {
    color: #6c757d;
    margin-bottom: 1.5rem;
    line-height: 1.5;
}

.tools-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1rem;
}

.tool-form {
    margin: 0;
}

.info-grid {
    display: grid;
    grid-template-columns: 1fr;
    gap: 0.75rem;
}

.info-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0.75rem;
    background: #f8f9fa;
    border-radius: 6px;
    border: 1px solid #e9ecef;
}

.info-label {
    font-weight: 600;
    color: #2c3e50;
}

.info-value {
    color: #6c757d;
    font-family: 'Courier New', monospace;
}

.actions-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1rem;
}

.actions-grid .btn {
    width: 100%;
    justify-content: center;
}

/* Your existing admin styles */
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

.admin-content {
    padding: 2rem;
}

.card {
    background: white;
    border-radius: 12px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.08);
    border: 1px solid #e9ecef;
    overflow: hidden;
}

.card-header {
    padding: 1.5rem;
    border-bottom: 1px solid #e9ecef;
    background: #f8f9fa;
}

.card-header h3 {
    margin: 0;
    color: #2c3e50;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.card-body {
    padding: 1.5rem;
}

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
    background: linear-gradient(135deg, #3498db, #2980b9);
    color: white;
}

.btn-primary:hover {
    background: linear-gradient(135deg, #2980b9, #1f618d);
    transform: translateY(-1px);
    box-shadow: 0 2px 8px rgba(52, 152, 219, 0.3);
}

.btn-secondary {
    background: linear-gradient(135deg, #95a5a6, #7f8c8d);
    color: white;
}

.btn-secondary:hover {
    background: linear-gradient(135deg, #7f8c8d, #6c7b7d);
    transform: translateY(-1px);
    box-shadow: 0 2px 8px rgba(149, 165, 166, 0.3);
}

.btn-warning {
    background: linear-gradient(135deg, #f39c12, #e67e22);
    color: white;
}

.btn-warning:hover {
    background: linear-gradient(135deg, #e67e22, #d35400);
    transform: translateY(-1px);
    box-shadow: 0 2px 8px rgba(243, 156, 18, 0.3);
}

.btn-info {
    background: linear-gradient(135deg, #17a2b8, #138496);
    color: white;
}

.btn-info:hover {
    background: linear-gradient(135deg, #138496, #117a8b);
    transform: translateY(-1px);
    box-shadow: 0 2px 8px rgba(23, 162, 184, 0.3);
}

.btn-success {
    background: linear-gradient(135deg, #28a745, #218838);
    color: white;
}

.btn-success:hover {
    background: linear-gradient(135deg, #218838, #1e7e34);
    transform: translateY(-1px);
    box-shadow: 0 2px 8px rgba(40, 167, 69, 0.3);
}

.btn:disabled {
    background: #6c757d;
    cursor: not-allowed;
    opacity: 0.6;
    transform: none;
    box-shadow: none;
}

.btn-icon {
    font-size: 1rem;
}

@media (max-width: 768px) {
    .settings-grid {
        grid-template-columns: 1fr;
    }
    
    .tools-grid,
    .actions-grid {
        grid-template-columns: 1fr;
    }
}
</style>

<?php include 'admin-footer.php'; ?>