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
        .btn-secondary { background: #2d5a3e; color: white; }
        
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
        
        /* Footer */
        footer { background: #2d2418; color: #e0cfbc; padding: 3rem 0 1rem; margin-top: 3rem; }
        .footer-content { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 2rem; margin-bottom: 2rem; }
        .footer-bottom { text-align: center; padding-top: 2rem; border-top: 1px solid #4a3a2f; }
        
        @media (max-width: 768px) {
            .nav-wrapper { flex-direction: column; gap: 1rem; }
            .nav-links { gap: 1rem; flex-wrap: wrap; justify-content: center; }
            .cart-wrapper, .contact-wrapper { grid-template-columns: 1fr; }
            .hero h1 { font-size: 2rem; }
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
                    $result = $conn->query("SELECT * FROM products LIMIT 6");
                    while($product = $result->fetch_assoc()):
                    ?>
                    <div class="product-card">
                        <div class="product-img">🍰</div>
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
                $result = $conn->query("SELECT * FROM products");
                while($product = $result->fetch_assoc()):
                ?>
                <div class="product-card">
                    <div class="product-img">🍰</div>
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
            <div class="footer-section">
                <div class="footer-logo">
                    <img src="./image/logo.png" alt="Mata Cake" class="footer-logo-img">
                </div>
                <p>Artisan bakery crafting delicious memories</p>
            </div>
            <div class="footer-section"><h4>Contact</h4><p><i class="fas fa-phone"></i> +94 77 123 4567</p><p><i class="fas fa-envelope"></i> hello@matacake.com</p></div>
            <div class="footer-section"><h4>Hours</h4><p>Mon-Sat: 9am - 7pm</p><p>Sun: 10am - 5pm</p></div>
        </div>
        <div class="footer-bottom"><p>&copy; 2025 Mata Cake. All rights reserved.</p></div>
    </div>
</footer>
</body>
</html>