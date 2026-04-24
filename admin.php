<?php
session_start();

// Database Configuration
$host = 'localhost';
$user = 'root';
$pass = '';
$dbname = 'mata_cake_db';

$conn = new mysqli($host, $user, $pass, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Create admin table
$conn->query("CREATE TABLE IF NOT EXISTS admins (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE,
    password VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

// Insert default admin
$check_admin = $conn->query("SELECT COUNT(*) as cnt FROM admins");
$admin_row = $check_admin->fetch_assoc();
if ($admin_row['cnt'] == 0) {
    $default_password = password_hash('admin123', PASSWORD_DEFAULT);
    $conn->query("INSERT INTO admins (username, password) VALUES ('admin', '$default_password')");
}

// Handle Login
$login_error = '';
if (isset($_POST['login'])) {
    $username = $_POST['username'];
    $password = $_POST['password'];
    $result = $conn->query("SELECT * FROM admins WHERE username='$username'");
    if ($result->num_rows > 0) {
        $admin = $result->fetch_assoc();
        if (password_verify($password, $admin['password'])) {
            $_SESSION['admin_logged_in'] = true;
            $_SESSION['admin_username'] = $username;
        } else {
            $login_error = 'Invalid password!';
        }
    } else {
        $login_error = 'Username not found!';
    }
}

// Handle Logout
if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: admin.php");
    exit();
}

// Check if logged in
$is_admin = isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;

// Handle Category Operations
$cat_message = '';
if ($is_admin && $_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['add_category'])) {
        $name = $_POST['cat_name'];
        $desc = $_POST['cat_description'];
        $stmt = $conn->prepare("INSERT INTO categories (name, description) VALUES (?, ?)");
        $stmt->bind_param("ss", $name, $desc);
        if ($stmt->execute()) {
            $cat_message = '<div class="alert success">Category added!</div>';
        } else {
            $cat_message = '<div class="alert error">Category already exists!</div>';
        }
    }
    
    if (isset($_POST['delete_category'])) {
        $id = $_POST['delete_id'];
        $conn->query("DELETE FROM categories WHERE id=$id");
        $cat_message = '<div class="alert success">Category deleted!</div>';
    }
    
    if (isset($_POST['add_product'])) {
        $name = $_POST['prod_name'];
        $desc = $_POST['prod_description'];
        $price = $_POST['prod_price'];
        $category = $_POST['prod_category'];
        $stmt = $conn->prepare("INSERT INTO products (name, description, price, category) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("ssds", $name, $desc, $price, $category);
        if ($stmt->execute()) {
            $cat_message = '<div class="alert success">Product added!</div>';
        } else {
            $cat_message = '<div class="alert error">Failed to add product!</div>';
        }
    }
    
    if (isset($_POST['delete_product'])) {
        $id = $_POST['delete_prod_id'];
        $conn->query("DELETE FROM products WHERE id=$id");
        $cat_message = '<div class="alert success">Product deleted!</div>';
    }
    
    if (isset($_POST['update_order_status'])) {
        $order_id = $_POST['order_id'];
        $status = $_POST['order_status'];
        $conn->query("UPDATE orders SET status='$status' WHERE id=$order_id");
        $cat_message = '<div class="alert success">Order status updated!</div>';
    }
}

$admin_page = isset($_GET['admin_page']) ? $_GET['admin_page'] : 'dashboard';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Panel - Mata Cake</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: #f0f2f5; }
        .admin-container { display: flex; min-height: 100vh; }
        
        /* Sidebar */
        .sidebar { width: 280px; background: #1a1a2e; color: white; padding: 2rem 1rem; }
        .sidebar h2 { margin-bottom: 2rem; padding-bottom: 1rem; border-bottom: 1px solid #333; }
        .sidebar a { display: flex; align-items: center; gap: 1rem; padding: 0.8rem 1rem; color: #ccc; text-decoration: none; border-radius: 10px; margin-bottom: 0.5rem; transition: 0.3s; }
        .sidebar a:hover, .sidebar a.active { background: #c4723a; color: white; }
        
        /* Main Content */
        .main-content { flex: 1; padding: 2rem; }
        .top-bar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; padding-bottom: 1rem; border-bottom: 1px solid #ddd; }
        
        /* Cards */
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1.5rem; margin-bottom: 2rem; }
        .stat-card { background: white; padding: 1.5rem; border-radius: 15px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); }
        .stat-card h3 { color: #666; font-size: 0.9rem; margin-bottom: 0.5rem; }
        .stat-card .number { font-size: 2rem; font-weight: bold; color: #c4723a; }
        
        /* Tables */
        .data-table { width: 100%; background: white; border-radius: 15px; overflow: hidden; box-shadow: 0 2px 10px rgba(0,0,0,0.05); }
        .data-table th, .data-table td { padding: 1rem; text-align: left; border-bottom: 1px solid #eee; }
        .data-table th { background: #f8f9fa; font-weight: 600; }
        
        /* Forms */
        .form-card { background: white; padding: 2rem; border-radius: 15px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); margin-bottom: 2rem; }
        .form-group { margin-bottom: 1rem; }
        .form-group label { display: block; margin-bottom: 0.5rem; font-weight: 500; }
        .form-group input, .form-group select, .form-group textarea { width: 100%; padding: 0.8rem; border: 1px solid #ddd; border-radius: 10px; }
        .btn { padding: 0.7rem 1.5rem; border: none; border-radius: 10px; cursor: pointer; font-weight: 500; }
        .btn-primary { background: #c4723a; color: white; }
        .btn-danger { background: #dc3545; color: white; }
        .btn-success { background: #28a745; color: white; }
        
        /* Login Box */
        .login-box { max-width: 400px; margin: 100px auto; background: white; padding: 2rem; border-radius: 20px; box-shadow: 0 10px 30px rgba(0,0,0,0.1); }
        .login-box h2 { text-align: center; margin-bottom: 2rem; color: #c4723a; }
        
        .alert { padding: 1rem; border-radius: 10px; margin-bottom: 1rem; }
        .alert.success { background: #d4edda; color: #155724; }
        .alert.error { background: #f8d7da; color: #721c24; }
        
        .logout-btn { background: #dc3545; color: white; padding: 0.5rem 1rem; border-radius: 10px; text-decoration: none; }
    </style>
</head>
<body>

<?php if (!$is_admin): ?>
    <!-- Login Form -->
    <div class="login-box">
        <h2><i class="fas fa-cake-candles"></i> Admin Login</h2>
        <?php if ($login_error): ?>
            <div class="alert error"><?php echo $login_error; ?></div>
        <?php endif; ?>
        <form method="POST">
            <div class="form-group">
                <label>Username</label>
                <input type="text" name="username" required>
            </div>
            <div class="form-group">
                <label>Password</label>
                <input type="password" name="password" required>
            </div>
            <button type="submit" name="login" class="btn btn-primary" style="width:100%">Login</button>
        </form>
        <p style="text-align:center; margin-top:1rem; font-size:0.8rem;">Default: admin / admin123</p>
    </div>
<?php else: ?>
    <div class="admin-container">
        <!-- Sidebar -->
        <div class="sidebar">
            <h2><i class="fas fa-cake-candles"></i> Mata Cake</h2>
            <a href="?admin_page=dashboard" class="<?php echo $admin_page == 'dashboard' ? 'active' : ''; ?>"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
            <a href="?admin_page=categories" class="<?php echo $admin_page == 'categories' ? 'active' : ''; ?>"><i class="fas fa-tags"></i> Categories</a>
            <a href="?admin_page=products" class="<?php echo $admin_page == 'products' ? 'active' : ''; ?>"><i class="fas fa-cake"></i> Products</a>
            <a href="?admin_page=orders" class="<?php echo $admin_page == 'orders' ? 'active' : ''; ?>"><i class="fas fa-shopping-cart"></i> Orders</a>
            <a href="?admin_page=custom_orders" class="<?php echo $admin_page == 'custom_orders' ? 'active' : ''; ?>"><i class="fas fa-pencil-ruler"></i> Custom Orders</a>
            <a href="?admin_page=messages" class="<?php echo $admin_page == 'messages' ? 'active' : ''; ?>"><i class="fas fa-envelope"></i> Messages</a>
            <a href="?logout=1" class="logout-btn" style="margin-top:2rem;"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </div>
        
        <!-- Main Content -->
        <div class="main-content">
            <div class="top-bar">
                <h1>Welcome, <?php echo $_SESSION['admin_username']; ?></h1>
                <a href="index.php" target="_blank"><i class="fas fa-external-link-alt"></i> View Website</a>
            </div>
            
            <?php echo $cat_message; ?>
            
            <?php if ($admin_page == 'dashboard'): ?>
                <?php
                $total_products = $conn->query("SELECT COUNT(*) as cnt FROM products")->fetch_assoc()['cnt'];
                $total_categories = $conn->query("SELECT COUNT(*) as cnt FROM categories")->fetch_assoc()['cnt'];
                $total_orders = $conn->query("SELECT COUNT(*) as cnt FROM orders")->fetch_assoc()['cnt'];
                $total_custom = $conn->query("SELECT COUNT(*) as cnt FROM custom_requests")->fetch_assoc()['cnt'];
                $total_messages = $conn->query("SELECT COUNT(*) as cnt FROM contact_messages")->fetch_assoc()['cnt'];
                ?>
                <div class="stats-grid">
                    <div class="stat-card"><h3>Total Products</h3><div class="number"><?php echo $total_products; ?></div></div>
                    <div class="stat-card"><h3>Categories</h3><div class="number"><?php echo $total_categories; ?></div></div>
                    <div class="stat-card"><h3>Orders</h3><div class="number"><?php echo $total_orders; ?></div></div>
                    <div class="stat-card"><h3>Custom Requests</h3><div class="number"><?php echo $total_custom; ?></div></div>
                    <div class="stat-card"><h3>Messages</h3><div class="number"><?php echo $total_messages; ?></div></div>
                </div>
                
                <h3>Recent Orders</h3>
                <table class="data-table">
                    <thead><tr><th>ID</th><th>Customer</th><th>Total</th><th>Date</th><th>Status</th></tr></thead>
                    <tbody>
                        <?php $orders = $conn->query("SELECT * FROM orders ORDER BY id DESC LIMIT 5");
                        while($order = $orders->fetch_assoc()): ?>
                        <tr>
                            <td>#<?php echo $order['id']; ?></td>
                            <td><?php echo $order['customer_name']; ?></td>
                            <td>Rs. <?php echo number_format($order['total_amount'], 2); ?></td>
                            <td><?php echo $order['order_date']; ?></td>
                            <td><?php echo $order['status']; ?></td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
                
            <?php elseif ($admin_page == 'categories'): ?>
                <div class="form-card">
                    <h3>Add New Category</h3>
                    <form method="POST">
                        <div class="form-group"><label>Category Name</label><input type="text" name="cat_name" required></div>
                        <div class="form-group"><label>Description</label><textarea name="cat_description" rows="2"></textarea></div>
                        <button type="submit" name="add_category" class="btn btn-primary">Add Category</button>
                    </form>
                </div>
                
                <h3>All Categories</h3>
                <table class="data-table">
                    <thead><tr><th>ID</th><th>Name</th><th>Description</th><th>Actions</th></tr></thead>
                    <tbody>
                        <?php $cats = $conn->query("SELECT * FROM categories");
                        while($cat = $cats->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo $cat['id']; ?></td>
                            <td><?php echo $cat['name']; ?></td>
                            <td><?php echo $cat['description']; ?></td>
                            <td>
                                <form method="POST" style="display:inline;">
                                    <input type="hidden" name="delete_id" value="<?php echo $cat['id']; ?>">
                                    <button type="submit" name="delete_category" class="btn btn-danger" style="padding:0.3rem 0.8rem;" onclick="return confirm('Delete this category?')">Delete</button>
                                </form>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
                
            <?php elseif ($admin_page == 'products'): ?>
                <div class="form-card">
                    <h3>Add New Product</h3>
                    <form method="POST">
                        <div class="form-group"><label>Product Name</label><input type="text" name="prod_name" required></div>
                        <div class="form-group"><label>Description</label><textarea name="prod_description" rows="2"></textarea></div>
                        <div class="form-group"><label>Price (Rs.)</label><input type="number" name="prod_price" step="0.01" required></div>
                        <div class="form-group"><label>Category</label>
                            <select name="prod_category" required>
                                <option value="">Select Category</option>
                                <?php $cats = $conn->query("SELECT * FROM categories");
                                while($cat = $cats->fetch_assoc()): ?>
                                <option value="<?php echo $cat['name']; ?>"><?php echo $cat['name']; ?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <button type="submit" name="add_product" class="btn btn-primary">Add Product</button>
                    </form>
                </div>
                
                <h3>All Products</h3>
                <table class="data-table">
                    <thead><tr><th>ID</th><th>Name</th><th>Description</th><th>Price</th><th>Category</th><th>Actions</th></tr></thead>
                    <tbody>
                        <?php $prods = $conn->query("SELECT * FROM products");
                        while($prod = $prods->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo $prod['id']; ?></td>
                            <td><?php echo $prod['name']; ?></td>
                            <td><?php echo substr($prod['description'], 0, 50); ?>...</td>
                            <td>Rs. <?php echo number_format($prod['price'], 2); ?></td>
                            <td><?php echo $prod['category']; ?></td>
                            <td>
                                <form method="POST" style="display:inline;">
                                    <input type="hidden" name="delete_prod_id" value="<?php echo $prod['id']; ?>">
                                    <button type="submit" name="delete_product" class="btn btn-danger" style="padding:0.3rem 0.8rem;" onclick="return confirm('Delete this product?')">Delete</button>
                                </form>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
                
            <?php elseif ($admin_page == 'orders'): ?>
                <h3>All Orders</h3>
                <table class="data-table">
                    <thead><tr><th>ID</th><th>Customer</th><th>Phone</th><th>Address</th><th>Total</th><th>Date</th><th>Status</th><th>Action</th></tr></thead>
                    <tbody>
                        <?php $orders = $conn->query("SELECT * FROM orders ORDER BY id DESC");
                        while($order = $orders->fetch_assoc()): ?>
                        <tr>
                            <td>#<?php echo $order['id']; ?></td>
                            <td><?php echo $order['customer_name']; ?></td>
                            <td><?php echo $order['customer_phone']; ?></td>
                            <td><?php echo substr($order['customer_address'], 0, 30); ?>...</td>
                            <td>Rs. <?php echo number_format($order['total_amount'], 2); ?></td>
                            <td><?php echo $order['order_date']; ?></td>
                            <td><?php echo $order['status']; ?></td>
                            <td>
                                <form method="POST">
                                    <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                                    <select name="order_status" onchange="this.form.submit()">
                                        <option value="pending" <?php echo $order['status']=='pending'?'selected':''; ?>>Pending</option>
                                        <option value="confirmed" <?php echo $order['status']=='confirmed'?'selected':''; ?>>Confirmed</option>
                                        <option value="preparing" <?php echo $order['status']=='preparing'?'selected':''; ?>>Preparing</option>
                                        <option value="delivered" <?php echo $order['status']=='delivered'?'selected':''; ?>>Delivered</option>
                                    </select>
                                    <input type="hidden" name="update_order_status" value="1">
                                </form>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
                
            <?php elseif ($admin_page == 'custom_orders'): ?>
                <h3>Custom Cake Requests</h3>
                <table class="data-table">
                    <thead><tr><th>ID</th><th>Customer</th><th>Flavor</th><th>Design</th><th>Event Date</th><th>Created</th></tr></thead>
                    <tbody>
                        <?php $customs = $conn->query("SELECT * FROM custom_requests ORDER BY id DESC");
                        while($custom = $customs->fetch_assoc()): ?>
                        <tr>
                            <td>#<?php echo $custom['id']; ?></td>
                            <td><?php echo $custom['customer_name']; ?><br><small><?php echo $custom['customer_email']; ?></small></td>
                            <td><?php echo $custom['flavor']; ?></td>
                            <td><?php echo substr($custom['design_description'], 0, 40); ?>...</td>
                            <td><?php echo $custom['event_date']; ?></td>
                            <td><?php echo $custom['created_at']; ?></td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
                
            <?php elseif ($admin_page == 'messages'): ?>
                <h3>Contact Messages</h3>
                <table class="data-table">
                    <thead><tr><th>ID</th><th>Name</th><th>Email</th><th>Message</th><th>Date</th></tr></thead>
                    <tbody>
                        <?php $msgs = $conn->query("SELECT * FROM contact_messages ORDER BY id DESC");
                        while($msg = $msgs->fetch_assoc()): ?>
                        <tr>
                            <td>#<?php echo $msg['id']; ?></td>
                            <td><?php echo $msg['name']; ?></td>
                            <td><?php echo $msg['email']; ?></td>
                            <td><?php echo substr($msg['message'], 0, 50); ?>...</td>
                            <td><?php echo $msg['created_at']; ?></td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
<?php endif; ?>
</body>
</html>