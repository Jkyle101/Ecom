<?php
require_once '../config/db_config.php';
require_once '../includes/auth_check.php';
require_once '../includes/cart.php';

require_login();

$product_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$buyer_id = $_SESSION['user_id'];

// Get product details
$stmt = $conn->prepare("SELECT p.*, u.username as seller_name, u.id as seller_id 
                        FROM products p
                        JOIN users u ON p.seller_id = u.id
                        WHERE p.id = ? AND p.approval_status = 'approved'");
$stmt->bind_param("i", $product_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header("Location: products.php");
    exit();
}

$product = $result->fetch_assoc();

// Track view
$stmt = $conn->prepare("INSERT IGNORE INTO product_views (user_id, product_id) VALUES (?, ?)");
$stmt->bind_param("ii", $buyer_id, $product_id);
$stmt->execute();

// Check if user owns this product
$is_owner = ($product['seller_id'] == $buyer_id);

// Handle add to cart - prevent if user owns product
$cart_added = false;
$cart_error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_to_cart'])) {
    check_csrf_token();
    if ($is_owner) {
        $cart_error = "You cannot buy your own product!";
    } else {
        $quantity = isset($_POST['quantity']) ? (int)$_POST['quantity'] : 1;
        addToCart($buyer_id, $product_id, $quantity);
        $cart_added = true;
    }
}

// Handle message send
$message_sent = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_message'])) {
    check_csrf_token();
    $message = sanitize_input($_POST['message']);
    if (!empty($message)) {
        require_once '../includes/messages.php';
        sendMessage($buyer_id, $product['seller_id'], $message, $product_id);
        $message_sent = true;
    }
}
?>

<?php include '../includes/header.php'; ?>
<link rel="stylesheet" href="../assets/css/dashboard.css">

<main class="main-wrapper">
    <div class="container">
        <div class="content-card">
            <a href="products.php" class="btn btn-secondary">← Back to Products</a>
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 30px; margin-top: 20px;" class="product-detail-grid">
                <div>
                    <img src="../<?php echo htmlspecialchars($product['image_path']); ?>" alt="<?php echo htmlspecialchars($product['name']); ?>" 
                         style="width: 100%; border-radius: 10px; box-shadow: 0 5px 15px rgba(0,0,0,0.1);" 
                         onerror="this.src='https://via.placeholder.com/500x500?text=No+Image'">
                </div>
                
                <div>
                    <h1><?php echo htmlspecialchars($product['name']); ?></h1>
                    <p class="product-price" style="font-size: 1.5rem; margin: 15px 0;">₱<?php echo number_format($product['price'], 2); ?></p>
                    <p class="product-category"><?php echo htmlspecialchars($product['category']); ?></p>
                    
                    <h3>Description</h3>
                    <p><?php echo nl2br(htmlspecialchars($product['description'])); ?></p>
                    
                    <?php if (!empty($product['sizes'])): ?>
                        <p><strong>Sizes:</strong> <?php echo htmlspecialchars($product['sizes']); ?></p>
                    <?php endif; ?>
                    
                    <?php if (!empty($product['colors'])): ?>
                        <p><strong>Colors:</strong> <?php echo htmlspecialchars($product['colors']); ?></p>
                    <?php endif; ?>
                    
                    <p><strong>Seller:</strong> <?php echo htmlspecialchars($product['seller_name']); ?></p>
                    <p><strong>Contact:</strong> <?php echo htmlspecialchars($product['contact_details']); ?></p>
                    
                    <div style="margin-top: 30px;">
                        <?php if ($cart_added): ?>
                            <div class="alert alert-success">Added to cart!</div>
                        <?php endif; ?>
                        <?php if (!empty($cart_error)): ?>
                            <div class="alert alert-danger"><?php echo $cart_error; ?></div>
                        <?php endif; ?>
                        
                        <?php if ($is_owner): ?>
                            <div class="alert alert-danger">
                                <i class="fas fa-info-circle"></i> This is your own product. You cannot purchase it.
                            </div>
                            <a href="../seller/view_product.php?id=<?php echo $product_id; ?>" class="btn btn-secondary">
                                <i class="fas fa-edit"></i> Manage Product
                            </a>
                        <?php else: ?>
                            <form method="POST" style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
                                <?php echo csrf_token_field(); ?>
                                <label>Quantity:</label>
                                <input type="number" name="quantity" value="1" min="1" style="width: 80px; padding: 8px;" required>
                                <button type="submit" name="add_to_cart" class="btn btn-primary">
                                    <i class="fas fa-shopping-cart"></i> Add to Cart
                                </button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <div style="margin-top: 30px; padding: 20px; background: #f9f9f9; border-radius: 10px;">
                <h3>Contact Seller</h3>
                <?php if ($message_sent): ?>
                    <div class="alert alert-success">Message sent successfully!</div>
                <?php endif; ?>
                <form method="POST">
                    <?php echo csrf_token_field(); ?>
                    <div class="form-group">
                        <textarea name="message" class="form-control" rows="3" placeholder="Send a message to the seller..." required></textarea>
                    </div>
                    <button type="submit" name="send_message" class="btn btn-primary">Send Message</button>
                </form>
            </div>
        </div>
    </div>
</main>

<?php include '../includes/footer.php'; ?>
