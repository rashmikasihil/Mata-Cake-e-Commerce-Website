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

// Create tables
$conn->query("CREATE TABLE IF NOT EXISTS products (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100),
    description TEXT,
    price DECIMAL(10,2),
    category VARCHAR(50)
)");

$conn->query("CREATE TABLE IF NOT EXISTS categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) UNIQUE,
    description TEXT
)");

$conn->query("CREATE TABLE IF NOT EXISTS orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    customer_name VARCHAR(100),
    customer_email VARCHAR(100),
    customer_phone VARCHAR(20),
    customer_address TEXT,
    order_items TEXT,
    total_amount DECIMAL(10,2),
    order_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    status VARCHAR(50) DEFAULT 'pending'
)");

$conn->query("CREATE TABLE IF NOT EXISTS custom_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    customer_name VARCHAR(100),
    customer_email VARCHAR(100),
    phone VARCHAR(20),
    flavor VARCHAR(100),
    design_description TEXT,
    event_date DATE,
    special_notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

$conn->query("CREATE TABLE IF NOT EXISTS contact_messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100),
    email VARCHAR(100),
    message TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

// Insert default categories
$check_cat = $conn->query("SELECT COUNT(*) as cnt FROM categories");
$cat_row = $check_cat->fetch_assoc();
if ($cat_row['cnt'] == 0) {
    $conn->query("INSERT INTO categories (name, description) VALUES
        ('signature', 'Our signature collection'),
        ('chocolate', 'Chocolate cakes'),
        ('mousse', 'Mousse cakes'),
        ('italian', 'Italian desserts'),
        ('wedding', 'Wedding cakes')");
}

// Insert sample products
$check_prod = $conn->query("SELECT COUNT(*) as cnt FROM products");
$prod_row = $check_prod->fetch_assoc();
if ($prod_row['cnt'] == 0) {
    $conn->query("INSERT INTO products (name, description, price, category) VALUES
        ('Velvet Rose Cake', 'Red velvet cake with cream cheese frosting', 3800, 'signature'),
        ('Honey Lavender Cake', 'Organic honey cake with lavender', 4200, 'signature'),
        ('Mango Bliss Mousse', 'Sri Lankan mango mousse cake', 3600, 'mousse'),
        ('Dark Chocolate Cake', 'Belgian chocolate ganache', 4500, 'chocolate'),
        ('Tiramisu Classico', 'Coffee-soaked layers with mascarpone', 4400, 'italian'),
        ('Wedding Special Cake', 'Elegant 3-tier wedding cake', 12500, 'wedding')");
}

// Initialize cart
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

$message = '';

// Handle POST requests
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['add_to_cart'])) {
        $id = $_POST['product_id'];
        $name = $_POST['product_name'];
        $price = $_POST['product_price'];
        if (isset($_SESSION['cart'][$id])) {
            $_SESSION['cart'][$id]['quantity']++;
        } else {
            $_SESSION['cart'][$id] = ['id' => $id, 'name' => $name, 'price' => $price, 'quantity' => 1];
        }
        $message = '<div class="alert success">✓ ' . $name . ' added to cart!</div>';
    }
    
    if (isset($_POST['remove_item'])) {
        $id = $_POST['remove_id'];
        unset($_SESSION['cart'][$id]);
        $message = '<div class="alert success">Item removed!</div>';
    }
    
    if (isset($_POST['update_cart'])) {
        foreach ($_POST['quantity'] as $id => $qty) {
            if ($qty > 0) {
                $_SESSION['cart'][$id]['quantity'] = $qty;
            } else {
                unset($_SESSION['cart'][$id]);
            }
        }
        $message = '<div class="alert success">Cart updated!</div>';
    }
    
    if (isset($_POST['clear_cart'])) {
        $_SESSION['cart'] = [];
        $message = '<div class="alert success">Cart cleared!</div>';
    }
    
    if (isset($_POST['place_order'])) {
        $name = $_POST['customer_name'];
        $email = $_POST['customer_email'];
        $phone = $_POST['customer_phone'];
        $address = $_POST['customer_address'];
        $items = json_encode($_SESSION['cart']);
        $total = 0;
        foreach ($_SESSION['cart'] as $item) {
            $total += $item['price'] * $item['quantity'];
        }
        $stmt = $conn->prepare("INSERT INTO orders (customer_name, customer_email, customer_phone, customer_address, order_items, total_amount) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("sssssd", $name, $email, $phone, $address, $items, $total);
        if ($stmt->execute()) {
            $_SESSION['cart'] = [];
            $message = '<div class="alert success">🎉 Order placed successfully!</div>';
        } else {
            $message = '<div class="alert error">❌ Order failed!</div>';
        }
    }
    
    if (isset($_POST['custom_order'])) {
        $stmt = $conn->prepare("INSERT INTO custom_requests (customer_name, customer_email, phone, flavor, design_description, event_date, special_notes) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("sssssss", $_POST['cust_name'], $_POST['cust_email'], $_POST['cust_phone'], $_POST['cake_flavor'], $_POST['design_desc'], $_POST['event_date'], $_POST['special_notes']);
        if ($stmt->execute()) {
            $message = '<div class="alert success">✨ Custom request sent!</div>';
        } else {
            $message = '<div class="alert error">Failed to send!</div>';
        }
    }
    
    if (isset($_POST['send_message'])) {
        $stmt = $conn->prepare("INSERT INTO contact_messages (name, email, message) VALUES (?, ?, ?)");
        $stmt->bind_param("sss", $_POST['contact_name'], $_POST['contact_email'], $_POST['message']);
        if ($stmt->execute()) {
            $message = '<div class="alert success">📧 Message sent!</div>';
        } else {
            $message = '<div class="alert error">Failed to send!</div>';
        }
    }
}

$page = isset($_GET['page']) ? $_GET['page'] : 'home';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mata Cake - Artisan Bakery</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Playfair+Display:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: #fff9f2; color: #2d2418; }
        h1, h2, h3, h4, .logo { font-family: 'Playfair Display', serif; }
        .container { max-width: 1280px; margin: 0 auto; padding: 0 2rem; }
        
        /* Navbar */
        .navbar { background: #fffaf3; box-shadow: 0 2px 20px rgba(0,0,0,0.05); position: sticky; top: 0; z-index: 1000; border-bottom: 1px solid #f0e0d0; }
        .nav-wrapper { display: flex; justify-content: space-between; align-items: center; padding: 1rem 0; flex-wrap: wrap; }
        .logo a { font-size: 1.8rem; font-weight: 700; text-decoration: none; color: #c4723a; }
        .nav-links { display: flex; gap: 2rem; list-style: none; }
        .nav-links a { text-decoration: none; font-weight: 500; color: #5a3d28; transition: 0.3s; padding: 0.5rem 0; }
        .nav-links a:hover, .nav-links a.active { color: #c4723a; border-bottom: 2px solid #c4723a; }
        .cart-icon { position: relative; background: #f5e6d8; padding: 0.6rem 1rem; border-radius: 50px; text-decoration: none; display: inline-block; color: #5a3d28; }
        .cart-count { background: #c4723a; color: white; font-size: 0.7rem; border-radius: 50%; padding: 2px 6px; position: absolute; top: -5px; right: -5px; }
        .admin-link { background: #2d5a3e; color: white !important; padding: 0.5rem 1rem; border-radius: 30px; }
        .admin-link:hover { background: #1e402c !important; border-bottom: none !important; }
        
        /* ========== LOGO STYLES - LARGE BUT NAVBAR SAME ========== */
        .navbar {
            position: sticky;
            top: 0;
            background: #fffaf3;
            box-shadow: 0 2px 20px rgba(0,0,0,0.05);
            z-index: 1000;
        }

        .nav-wrapper {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.8rem 0;  /* padding එක අඩු කරලා navbar උස පාලනය කරන්න */
            min-height: 70px;   /* navbar හරියටම තියාගන්න */
        }

        /* Logo Link */
        .logo a {
            display: flex;
            align-items: center;
            text-decoration: none;
        }

        /* Logo Image - විශාල කරපු එක */
        .logo-img {
            height: 80px;        /* Logo එක විශාලයි */
            width: auto;
            max-height: 85px;
            object-fit: contain;
            transition: transform 0.3s ease;
        }

        /* Hover එකෙදි ටිකක් විශාල වෙන්න */
        .logo-img:hover {
            transform: scale(1.05);
        }

        /* Navbar Links - Normal Size */
        .nav-links {
            display: flex;
            gap: 2rem;
            list-style: none;
            align-items: center;
            margin: 0;
        }

        .nav-links a {
            text-decoration: none;
            font-weight: 500;
            color: #5a3d28;
            font-size: 1rem;     /* link size normal */
            padding: 0.5rem 0;
        }

        /* Cart Icon */
        .cart-icon {
            display: flex;
            align-items: center;
            gap: 5px;
            background: #f5e6d8;
            padding: 0.5rem 1rem;
            border-radius: 50px;
            text-decoration: none;
            color: #5a3d28;
            font-size: 1rem;
        }

        /* Mobile Responsive */
        @media (max-width: 768px) {
            .logo-img {
                height: 55px;    /* mobile එකේදි logo එක ටිකක් කුඩා කරන්න */
            }
            
            .nav-wrapper {
                flex-direction: column;
                gap: 0.5rem;
                padding: 0.6rem 0;
            }
            
            .nav-links {
                gap: 1rem;
                flex-wrap: wrap;
                justify-content: center;
            }
        }
        /* Buttons */
        .btn { display: inline-block; padding: 0.8rem 2rem; border-radius: 50px; text-decoration: none; font-weight: 600; transition: 0.3s; border: none; cursor: pointer; }
        .btn-primary { background: #c4723a; color: white; }
        .btn-primary:hover { background: #a05a2a; transform: translateY(-2px); }
        .btn-secondary { background: #2d5a3e; color: white; margin-top: 20px; }
        
        /* Hero */
        .hero { background: linear-gradient(135deg, #f8e4d0, #fcecd8); padding: 5rem 0; text-align: center; border-radius: 0 0 50px 50px; }
        .hero h1 { font-size: 3.5rem; color: #6b3a1a; margin-bottom: 1rem; }
        .hero p { font-size: 1.2rem; color: #7a5238; margin-bottom: 2rem; }
        .section-title { text-align: center; font-size: 2.5rem; color: #6b3a1a; margin: 3rem 0 2rem; }
        
        /* Products Grid */
        .products-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 2rem; margin-bottom: 3rem; }
        .product-card { background: white; border-radius: 24px; overflow: hidden; box-shadow: 0 5px 20px rgba(0,0,0,0.05); transition: 0.3s; }
        .product-card:hover { transform: translateY(-5px); box-shadow: 0 15px 35px rgba(0,0,0,0.1); }
        .product-img { height: 200px; background: linear-gradient(135deg, #f9ede0, #f5e0ce); display: flex; align-items: center; justify-content: center; font-size: 4rem; }
        .product-info { padding: 1.5rem; }
        .product-info h3 { font-size: 1.3rem; margin-bottom: 0.5rem; }
        .product-info p { color: #7a6b5d; margin-bottom: 1rem; }
        .product-price { font-size: 1.4rem; font-weight: 700; color: #c4723a; margin-bottom: 1rem; }
        .add-to-cart { background: #c4723a; color: white; border: none; padding: 0.7rem; width: 100%; border-radius: 50px; font-weight: 600; cursor: pointer; }
        .add-to-cart:hover { background: #a05a2a; }
        
        /* Custom Section */
        .custom-section { background: linear-gradient(135deg, #2d5a3e, #1e402c); padding: 4rem 2rem; text-align: center; border-radius: 50px; margin: 2rem 0; color: white; }
        
        /* Forms */
        .form-container { max-width: 700px; margin: 0 auto; background: white; padding: 2rem; border-radius: 30px; box-shadow: 0 10px 30px rgba(0,0,0,0.08); }
        .form-group { margin-bottom: 1.2rem; }
        .form-group label { display: block; margin-bottom: 0.5rem; font-weight: 500; }
        .form-group input, .form-group select, .form-group textarea { width: 100%; padding: 0.8rem 1rem; border: 1px solid #e0d0c0; border-radius: 30px; font-family: inherit; }
        
        /* Cart Table */
        .cart-wrapper { display: grid; grid-template-columns: 1fr 1fr; gap: 2rem; margin: 2rem 0; }
        .cart-table { width: 100%; border-collapse: collapse; background: white; border-radius: 20px; overflow: hidden; }
        .cart-table th, .cart-table td { padding: 1rem; text-align: left; border-bottom: 1px solid #f0e0d0; }
        .cart-table th { background: #f9ede0; }
        .qty-input { width: 60px; padding: 0.3rem; border-radius: 10px; border: 1px solid #e0d0c0; }
        .empty-cart { text-align: center; padding: 4rem; background: white; border-radius: 30px; }
        .empty-cart i { font-size: 4rem; color: #c4723a; margin-bottom: 1rem; }
        
        /* Contact Page */
        .contact-wrapper { display: grid; grid-template-columns: 1fr 1fr; gap: 2rem; margin: 2rem 0; }
        .info-card { background: white; padding: 1.5rem; border-radius: 20px; text-align: center; margin-bottom: 1rem; box-shadow: 0 5px 15px rgba(0,0,0,0.05); }
        .info-card i { font-size: 2rem; color: #c4723a; margin-bottom: 0.5rem; }
        
        /* Alerts */
        .alert { padding: 1rem; border-radius: 15px; margin-bottom: 1rem; text-align: center; }
        .alert.success { background: #d4edda; color: #155724; }
        .alert.error { background: #f8d7da; color: #721c24; }
        
        /* ========== FOOTER STYLES ========== */
        footer {
            background: #2d2418;
            color: #e0cfbc;
            padding: 3rem 0 1rem;
            margin-top: 3rem;
        }

        .footer-content {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 2rem;
            margin-bottom: 2rem;
        }

        /* Footer Logo */
        .footer-logo-img {
            height: 50px;
            width: auto;
            margin-bottom: 1rem;
           
        }

        /* Social Links */
        .social-links {
            display: flex;
            gap: 1rem;
            margin-top: 1rem;
            margin-left: 36px;
        }

        .social-links a {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 35px;
            height: 35px;
            background: #4a3a2f;
            color: #e0cfbc;
            border-radius: 50%;
            transition: 0.3s;
            text-decoration: none;
        }

        .social-links a:hover {
            background: #c4723a;
            color: white;
            transform: translateY(-3px);
        }

        /* Footer Links */
        .footer-links {
            list-style: none;
            padding: 0;
        }

        .footer-links li {
            margin-bottom: 0.5rem;
        }

        .footer-links a {
            color: #e0cfbc;
            text-decoration: none;
            transition: 0.3s;
            font-size: 0.9rem;
        }

        .footer-links a:hover {
            color: #c4723a;
            padding-left: 5px;
        }

        .footer-section h4 {
            font-family: 'Playfair Display', serif;
            margin-bottom: 1rem;
            color: #f0dcc8;
            font-size: 1.2rem;
        }

        .footer-section p {
            margin: 0.5rem 0;
            font-size: 0.9rem;
        }

        .footer-section i {
            color: #c4723a;
            width: 20px;
        }

        .footer-bottom {
            text-align: center;
            padding-top: 2rem;
            border-top: 1px solid #4a3a2f;
            font-size: 0.8rem;
        }

        .footer-bottom i {
            color: #c4723a;
        }

        /* ========== POLICY MODAL STYLES ========== */
        .policy-modal {
            display: none;
            position: fixed;
            z-index: 2000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.7);
            overflow: auto;
        }

        .policy-modal-content {
            background-color: #fff9f2;
            margin: 5% auto;
            padding: 2rem;
            width: 80%;
            max-width: 800px;
            border-radius: 20px;
            position: relative;
            animation: modalSlide 0.3s ease;
            max-height: 80vh;
            overflow-y: auto;
        }

        @keyframes modalSlide {
            from {
                transform: translateY(-50px);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }

        .policy-close {
            position: absolute;
            right: 20px;
            top: 15px;
            font-size: 28px;
            font-weight: bold;
            color: #c4723a;
            cursor: pointer;
            transition: 0.3s;
        }

        .policy-close:hover {
            color: #a05a2a;
        }

        #policyContent {
            color: #2d2418;
        }

        #policyContent h2 {
            color: #c4723a;
            margin-bottom: 1rem;
            font-family: 'Playfair Display', serif;
        }

        #policyContent h3 {
            margin-top: 1.5rem;
            margin-bottom: 0.5rem;
            color: #2d5a3e;
        }

        #policyContent p {
            margin-bottom: 1rem;
            line-height: 1.6;
        }

        #policyContent ul {
            margin-left: 2rem;
            margin-bottom: 1rem;
        }

        /* Mobile Responsive */
        @media (max-width: 768px) {
            .footer-content {
                grid-template-columns: 1fr;
                text-align: center;
            }
            
            .social-links {
                justify-content: center;
                margin-left: 0px !important;
            }
            
            .policy-modal-content {
                width: 95%;
                margin: 10% auto;
                padding: 1.5rem;
            }
        }
     
        /* ========== FOOTER LOGO STYLES ========== */
        .footer-section {
            text-align: center;
        }

        .footer-logo {
            margin-bottom: 1rem;
        }

        .footer-logo-img {
            height: 80px;     
            width: auto;
            max-height: 85px;
            object-fit: contain;
            transition: all 0.3s ease;
        }

        .footer-logo-img:hover {
            transform: scale(1.05);
            filter: brightness(0) invert(0.8); 
        }

        .footer-logo-img-light {
            height: 50px;
            width: auto;
            object-fit: contain;
        }

        /* Footer Text */
        .footer-section p {
            margin-top: 0.5rem;
            font-size: 0.9rem;
            color: #e0cfbc;        /* footer text color */
        }
        .product-img {
            height: 220px;
            overflow: hidden;
            background: linear-gradient(135deg, #f9ede0, #f5e0ce);
        }

        .product-image {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.5s ease;
        }

        .product-card:hover .product-image {
            transform: scale(1.08);
        }

        /* Placeholder Style */
        .product-img img[src*="placeholder"] {
            object-fit: contain;
            padding: 20px;
        }
    </style>
</head>
<body>

<nav class="navbar">
    <div class="container nav-wrapper">
        <div class="logo">
            <a href="?page=home">
                <img src="./image/logo.png" alt="Mata Cake Logo" class="logo-img">
            </a>
        </div>
        <ul class="nav-links">
            <li><a href="?page=home" class="<?php echo $page == 'home' ? 'active' : ''; ?>">Home</a></li>
            <li><a href="?page=cakes" class="<?php echo $page == 'cakes' ? 'active' : ''; ?>">Cakes</a></li>
            <li><a href="?page=custom" class="<?php echo $page == 'custom' ? 'active' : ''; ?>">Custom Order</a></li>
            <li><a href="?page=contact" class="<?php echo $page == 'contact' ? 'active' : ''; ?>">Contact</a></li>
            <li><a href="?page=checkout" class="<?php echo $page == 'checkout' ? 'active' : ''; ?>">Cart</a></li>
            
        </ul>
        <a href="?page=checkout" class="cart-icon">
            <i class="fas fa-shopping-bag"></i>
            <span class="cart-count"><?php echo array_sum(array_column($_SESSION['cart'], 'quantity')); ?></span>
        </a>
    </div>
</nav>

<main>
    <div class="container">
        <?php echo $message; ?>
    </div>

    <?php if ($page == 'home'): ?>
        <section class="hero">
            <div class="container">
                <h1>Artisanal Cakes,<br>Crafted with Love</h1>
                <p>Every Mata Cake is a celebration of Sri Lankan flavors & European techniques</p>
                <a href="?page=cakes" class="btn btn-primary">Order Now</a>
            </div>
        </section>
        <section class="featured-products">
    <div class="container">
        <h2 class="section-title">✨ Our Signature Collection</h2>
        <div class="products-grid">
            <?php
            // Manual image mapping by product ID
            $image_map = [
                1 => 'image/1.jpg',
                2 => 'image/2.jpg',
                3 => 'image/3.png',
                4 => 'image/4.webp',
                5 => 'image/5.webp',
                6 => 'image/6.webp',
            ];
            
            $result = $conn->query("SELECT * FROM products LIMIT 6");
            while($product = $result->fetch_assoc()):
                $product_image = isset($image_map[$product['id']]) 
                    ? $image_map[$product['id']] 
                    : 'images/cakes/cake-placeholder.jpg';
            ?>
            <div class="product-card">
                <div class="product-img">
                    <img src="<?php echo $product_image; ?>" 
                         alt="<?php echo $product['name']; ?>" 
                         class="product-image">
                </div>
                <div class="product-info">
                    <h3><?php echo $product['name']; ?></h3>
                    <p><?php echo $product['description']; ?></p>
                    <div class="product-price">Rs. <?php echo number_format($product['price'], 2); ?></div>
                    <form method="POST">
                        <input type="hidden" name="add_to_cart" value="1">
                        <input type="hidden" name="product_id" value="<?php echo $product['id']; ?>">
                        <input type="hidden" name="product_name" value="<?php echo $product['name']; ?>">
                        <input type="hidden" name="product_price" value="<?php echo $product['price']; ?>">
                        <button type="submit" class="add-to-cart">Add to Cart</button>
                    </form>
                </div>
            </div>
            <?php endwhile; ?>
        </div>
    </div>
</section>
        <div class="container">
            <div class="custom-section">
                <h2>🎂 Craving Something Unique?</h2>
                <p>Order a custom design cake — birthday, wedding, or just because.</p>
                <a href="?page=custom" class="btn btn-secondary">Custom Order →</a>
            </div>
        </div>

    <?php elseif ($page == 'cakes'): ?>
         <div class="container">
        <h2 class="section-title">🍰 All Cakes</h2>
        <div class="products-grid">
            <?php
            // Manual image mapping by product ID (same as home page)
            $image_map = [
                1 => 'image/1.jpg',
                2 => 'image/2.jpg',
                3 => 'image/3.png',
                4 => 'image/4.webp',
                5 => 'image/5.webp',
                6 => 'image/6.webp',
            ];
            
            $result = $conn->query("SELECT * FROM products");
            while($product = $result->fetch_assoc()):
                // Get image from mapping or use placeholder
                $product_image = isset($image_map[$product['id']]) 
                    ? $image_map[$product['id']] 
                    : 'image/cake-placeholder.jpg';
                
                // Check if image file exists
                if(!file_exists($product_image)) {
                    $product_image = 'image/cake-placeholder.jpg';
                }
            ?>
            <div class="product-card">
                <div class="product-img">
                    <img src="<?php echo $product_image; ?>" 
                         alt="<?php echo $product['name']; ?>" 
                         class="product-image"
                         onerror="this.src='image/cake-placeholder.jpg'">
                </div>
                <div class="product-info">
                    <h3><?php echo $product['name']; ?></h3>
                    <p><?php echo $product['description']; ?></p>
                    <div class="product-price">Rs. <?php echo number_format($product['price'], 2); ?></div>
                    <form method="POST">
                        <input type="hidden" name="add_to_cart" value="1">
                        <input type="hidden" name="product_id" value="<?php echo $product['id']; ?>">
                        <input type="hidden" name="product_name" value="<?php echo $product['name']; ?>">
                        <input type="hidden" name="product_price" value="<?php echo $product['price']; ?>">
                        <button type="submit" class="add-to-cart">
                            <i class="fas fa-cart-plus"></i> Add to Cart
                        </button>
                    </form>
                </div>
            </div>
            <?php endwhile; ?>
        </div>
    </div>

    <?php elseif ($page == 'custom'): ?>
        <div class="container">
            <h2 class="section-title">✨ Design Your Dream Cake</h2>
            <div class="form-container">
                <form method="POST">
                    <input type="hidden" name="custom_order" value="1">
                    <div class="form-group"><label>Your Name *</label><input type="text" name="cust_name" required></div>
                    <div class="form-group"><label>Email *</label><input type="email" name="cust_email" required></div>
                    <div class="form-group"><label>Phone</label><input type="tel" name="cust_phone"></div>
                    <div class="form-group"><label>Cake Flavor *</label>
                        <select name="cake_flavor" required>
                            <option value="">Select flavor</option>
                            <option>Chocolate</option><option>Vanilla</option><option>Red Velvet</option><option>Mango</option>
                        </select>
                    </div>
                    <div class="form-group"><label>Design Description *</label><textarea name="design_desc" rows="4" required></textarea></div>
                    <div class="form-group"><label>Event Date</label><input type="date" name="event_date"></div>
                    <div class="form-group"><label>Special Notes</label><textarea name="special_notes" rows="3"></textarea></div>
                    <button type="submit" class="btn btn-primary">Submit Request</button>
                </form>
            </div>
        </div>

    <?php elseif ($page == 'contact'): ?>
        <div class="container">
            <h2 class="section-title">📞 Get in Touch</h2>
            <div class="contact-wrapper">
                <div>
                    <div class="info-card"><i class="fas fa-map-marker-alt"></i><h3>Visit Us</h3><p>No 42, Galle Road, Colombo 03</p></div>
                    <div class="info-card"><i class="fas fa-phone"></i><h3>Call Us</h3><p>+94 77 123 4567</p></div>
                    <div class="info-card"><i class="fas fa-envelope"></i><h3>Email</h3><p>hello@matacake.com</p></div>
                </div>
                <div class="form-container">
                    <h3>Send us a message</h3>
                    <form method="POST">
                        <input type="hidden" name="send_message" value="1">
                        <div class="form-group"><input type="text" name="contact_name" placeholder="Your Name" required></div>
                        <div class="form-group"><input type="email" name="contact_email" placeholder="Your Email" required></div>
                        <div class="form-group"><textarea name="message" rows="5" placeholder="Your Message" required></textarea></div>
                        <button type="submit" class="btn btn-primary">Send Message</button>
                    </form>
                </div>
            </div>
        </div>

    <?php elseif ($page == 'checkout'): ?>
        <div class="container">
            <h2 class="section-title">🛒 Your Cart</h2>
            <?php if (empty($_SESSION['cart'])): ?>
                <div class="empty-cart"><i class="fas fa-shopping-basket"></i><h3>Your cart is empty</h3><a href="?page=cakes" class="btn btn-primary">Browse Cakes</a></div>
            <?php else: ?>
                <div class="cart-wrapper">
                    <div>
                        <form method="POST">
                            <table class="cart-table">
                                <thead><tr><th>Product</th><th>Price</th><th>Qty</th><th>Total</th><th></th></tr></thead>
                                <tbody>
                                    <?php $grand_total = 0;
                                    foreach ($_SESSION['cart'] as $id => $item): $item_total = $item['price'] * $item['quantity']; $grand_total += $item_total; ?>
                                    <tr>
                                        <td><?php echo $item['name']; ?></td>
                                        <td>Rs. <?php echo number_format($item['price'], 2); ?></td>
                                        <td><input type="number" name="quantity[<?php echo $id; ?>]" class="qty-input" value="<?php echo $item['quantity']; ?>" min="1"></td>
                                        <td>Rs. <?php echo number_format($item_total, 2); ?></td>
                                        <td><button type="submit" name="remove_item" value="1" onclick="this.form.remove_id.value=<?php echo $id; ?>">❌</button><input type="hidden" name="remove_id" value=""></td>
                                    </tr>
                                    <?php endforeach; ?>
                                    <tr><td colspan="3"><strong>Total</strong></td><td colspan="2"><strong>Rs. <?php echo number_format($grand_total, 2); ?></strong></td></tr>
                                </tbody>
                            </table>
                            <div style="display: flex; gap: 1rem; margin-top: 1rem;">
                                <button type="submit" name="update_cart" class="btn btn-secondary">Update</button>
                                <button type="submit" name="clear_cart" class="btn" style="background:#ab7b54; color:white;">Clear</button>
                            </div>
                        </form>
                    </div>
                    <div class="form-container">
                        <h3>Checkout</h3>
                        <form method="POST">
                            <input type="hidden" name="place_order" value="1">
                            <div class="form-group"><input type="text" name="customer_name" placeholder="Full Name" required></div>
                            <div class="form-group"><input type="email" name="customer_email" placeholder="Email" required></div>
                            <div class="form-group"><input type="tel" name="customer_phone" placeholder="Phone" required></div>
                            <div class="form-group"><textarea name="customer_address" rows="3" placeholder="Delivery Address" required></textarea></div>
                            <button type="submit" class="btn btn-primary">Place Order</button>
                        </form>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</main>

<footer>
    <div class="container">
        <div class="footer-content">
            <!-- Logo & About Section -->
            <div class="footer-section">
                <div class="footer-logo">
                    <img src="./image/logo.png" alt="Mata Cake" class="footer-logo-img">
                </div>
                <p>Artisan bakery crafting delicious memories with love and finest ingredients since 2020.</p>
                <div class="social-links">
                    <a href="#"><i class="fab fa-facebook"></i></a>
                    <a href="#"><i class="fab fa-instagram"></i></a>
                    <a href="#"><i class="fab fa-twitter"></i></a>
                    <a href="#"><i class="fab fa-whatsapp"></i></a>
                </div>
            </div>
            
            <!-- Quick Links / Services -->
            <div class="footer-section">
                <h4>Our Services</h4>
                <ul class="footer-links">
                    <li><a href="?page=cakes">Birthday Cakes</a></li>
                    <li><a href="?page=cakes">Wedding Cakes</a></li>
                    <li><a href="?page=custom">Custom Designs</a></li>
                    <li><a href="?page=cakes">Cupcakes</a></li>
                    <li><a href="?page=custom">Theme Cakes</a></li>
                </ul>
            </div>
            
            <!-- Contact Section -->
            <div class="footer-section">
                <h4>Contact Info</h4>
                <p><i class="fas fa-map-marker-alt"></i> No 42, Galle Road, Colombo 03</p>
                <p><i class="fas fa-phone"></i> +94 77 123 4567</p>
                <p><i class="fas fa-envelope"></i> hello@matacake.com</p>
                <p><i class="fas fa-clock"></i> Mon-Sat: 9am - 7pm</p>
            </div>
            
            <!-- Policies Section -->
            <div class="footer-section">
                <h4>Information</h4>
                <ul class="footer-links">
                    <li><a href="#" onclick="showPolicy('terms')">Terms & Conditions</a></li>
                    <li><a href="#" onclick="showPolicy('privacy')">Privacy Policy</a></li>
                    <li><a href="#" onclick="showPolicy('return')">Return & Refund Policy</a></li>
                    <li><a href="#" onclick="showPolicy('shipping')">Shipping Policy</a></li>
                    <li><a href="#" onclick="showPolicy('faq')">FAQ</a></li>
                </ul>
            </div>
        </div>
        
        <div class="footer-bottom">
            <p>&copy; 2026 Mata Cake. All rights reserved. | Designed with <i class="fas fa-heart"></i> for cake lovers</p>
        </div>
    </div>
</footer>

<!-- Policy Modal (Popup for Terms, Privacy, Return etc.) -->
<div id="policyModal" class="policy-modal">
    <div class="policy-modal-content">
        <span class="policy-close">&times;</span>
        <div id="policyContent"></div>
    </div>
</div>
</body>
<script>
   // Policy Modal Functions
function showPolicy(type) {
    const modal = document.getElementById('policyModal');
    const contentDiv = document.getElementById('policyContent');
    
    let content = '';
    
    switch(type) {
        case 'terms':
            content = getTermsContent();
            break;
        case 'privacy':
            content = getPrivacyContent();
            break;
        case 'return':
            content = getReturnContent();
            break;
        case 'shipping':
            content = getShippingContent();
            break;
        case 'faq':
            content = getFAQContent();
            break;
        default:
            content = getTermsContent();
    }
    
    contentDiv.innerHTML = content;
    modal.style.display = 'block';
}

// Close modal
document.querySelector('.policy-close').onclick = function() {
    document.getElementById('policyModal').style.display = 'none';
}

// Close when clicking outside
window.onclick = function(event) {
    const modal = document.getElementById('policyModal');
    if (event.target == modal) {
        modal.style.display = 'none';
    }
}

// Terms & Conditions Content
function getTermsContent() {
    return `
        <h2>📜 Terms & Conditions</h2>
        <p><strong>Last Updated:</strong> January 1, 2025</p>
        
        <h3>1. Order Placement</h3>
        <p>All orders placed through our website are subject to acceptance. We reserve the right to refuse any order.</p>
        
        <h3>2. Pricing</h3>
        <p>All prices are in Sri Lankan Rupees (LKR) and include applicable taxes. Prices are subject to change without notice.</p>
        
        <h3>3. Payment</h3>
        <p>Full payment is required at the time of order placement. We accept cash on delivery and bank transfers.</p>
        
        <h3>4. Custom Cakes</h3>
        <p>Custom cake requests require 5-7 days advance notice. Final design approval must be confirmed 48 hours before production.</p>
        
        <h3>5. Cancellations</h3>
        <p>Orders can be cancelled up to 48 hours before delivery for a full refund. Custom cakes are non-refundable once production begins.</p>
        
        <h3>6. Contact Us</h3>
        <p>For any questions regarding these terms, contact us at hello@matacake.com</p>
    `;
}

// Privacy Policy Content
function getPrivacyContent() {
    return `
        <h2>🔒 Privacy Policy</h2>
        <p><strong>Last Updated:</strong> January 1, 2025</p>
        
        <h3>Information We Collect</h3>
        <p>We collect personal information including name, email address, phone number, and delivery address when you place an order or contact us.</p>
        
        <h3>How We Use Your Information</h3>
        <p>We use your information to process orders, communicate with you about your order, and improve our services.</p>
        
        <h3>Information Sharing</h3>
        <p>We do not sell, trade, or rent your personal information to third parties.</p>
        
        <h3>Data Security</h3>
        <p>We implement security measures to protect your personal information from unauthorized access.</p>
        
        <h3>Your Rights</h3>
        <p>You have the right to access, correct, or delete your personal information.</p>
        
        <h3>Contact Us</h3>
        <p>For privacy concerns, email privacy@matacake.com</p>
    `;
}

// Return & Refund Policy Content
function getReturnContent() {
    return `
        <h2>🔄 Return & Refund Policy</h2>
        <p><strong>Last Updated:</strong> January 1, 2025</p>
        
        <h3>Returns</h3>
        <p>Due to the perishable nature of our products, we do not accept returns. However, if there is an issue with your order, please contact us within 2 hours of delivery.</p>
        
        <h3>Refunds</h3>
        <p>Refunds are issued in the following cases:</p>
        <ul>
            <li>Wrong cake delivered</li>
            <li>Cake arrives damaged</li>
            <li>Cancellation within 48 hours of order (custom cakes excluded)</li>
        </ul>
        
        <h3>How to Request a Refund</h3>
        <p>Contact us at refunds@matacake.com with your order number and photos of the issue (if applicable).</p>
        
        <h3>Processing Time</h3>
        <p>Refunds are processed within 5-7 business days.</p>
    `;
}

// Shipping Policy Content
function getShippingContent() {
    return `
        <h2>🚚 Shipping & Delivery Policy</h2>
        <p><strong>Last Updated:</strong> January 1, 2025</p>
        
        <h3>Delivery Areas</h3>
        <p>We deliver to Colombo and suburbs. Contact us for delivery to other areas.</p>
        
        <h3>Delivery Charges</h3>
        <ul>
            <li>Within Colombo 3-5: Free delivery</li>
            <li>Colombo suburbs: Rs. 500 - Rs. 1000</li>
            <li>Outside Colombo: Contact for quote</li>
        </ul>
        
        <h3>Delivery Time</h3>
        <ul>
            <li>Standard cakes: 2-3 days</li>
            <li>Custom cakes: 5-7 days</li>
            <li>Express delivery: Next day (extra charge)</li>
        </ul>
        
        <h3>Delivery Hours</h3>
        <p>Deliveries are made between 9am - 7pm, Monday to Saturday.</p>
    `;
}

// FAQ Content
function getFAQContent() {
    return `
        <h2>❓ Frequently Asked Questions</h2>
        
        <h3>How far in advance should I order?</h3>
        <p>For standard cakes, 2-3 days advance notice is recommended. For custom cakes, please order 5-7 days in advance.</p>
        
        <h3>Do you offer gluten-free or vegan options?</h3>
        <p>Yes, we offer gluten-free and vegan options. Please specify your requirements in the custom order form.</p>
        
        <h3>Can I get a cake delivered on Sunday?</h3>
        <p>Yes, we deliver on Sundays between 10am - 5pm.</p>
        
        <h3>Do you accept last-minute orders?</h3>
        <p>We accept last-minute orders subject to availability. Please call us directly for urgent orders.</p>
        
        <h3>What payment methods do you accept?</h3>
        <p>We accept cash on delivery, bank transfer, and online payments.</p>
        
        <h3>Can I customize the cake design?</h3>
        <p>Absolutely! Use our Custom Order form to describe your dream cake design.</p>
    `;
}
</script>
</html>