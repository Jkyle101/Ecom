<?php
require_once '../config/db_config.php';
require_once '../includes/auth_check.php';
require_once '../includes/cart.php';

require_login();

$user_id = $_SESSION['user_id'];

// Handle add to cart
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_to_cart'])) {
    check_csrf_token();
    $product_id = (int)$_POST['product_id'];
    $quantity = isset($_POST['quantity']) ? (int)$_POST['quantity'] : 1;
    addToCart($user_id, $product_id, $quantity);
    header("Location: cart.php");
    exit();
}

// Handle remove from cart
if (isset($_GET['remove'])) {
    $product_id = (int)$_GET['remove'];
    removeFromCart($user_id, $product_id);
    header("Location: cart.php");
    exit();
}

// Handle update quantity
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_quantity'])) {
    check_csrf_token();
    $product_id = (int)$_POST['product_id'];
    $quantity = (int)$_POST['quantity'];
    updateCartQuantity($user_id, $product_id, $quantity);
    header("Location: cart.php");
    exit();
}

$cart_items = getCartItems($user_id);
$cart_total = getCartTotal($user_id);
?>

<?php include '../includes/header.php'; ?>
<link rel="stylesheet" href="../assets/css/dashboard.css">

<main class="main-wrapper">
    <div class="container">
        <div class="page-header">
            <h1>Shopping Cart</h1>
            <div class="breadcrumb">Home > Cart</div>
        </div>

        <?php if (empty($cart_items)): ?>
            <div class="content-card">
                <div class="empty-state">
                    <i class="fas fa-shopping-cart"></i>
                    <p>Your cart is empty</p>
                    <a href="products.php" class="btn btn-primary" style="margin-top: 15px;">Browse Products</a>
                </div>
            </div>
        <?php else: ?>
            <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 20px;" class="cart-layout">
                <!-- Cart Items -->
                <div class="content-card">
                    <h2>Cart Items</h2>
                    <?php foreach ($cart_items as $item): ?>
                        <div style="display: flex; gap: 20px; padding: 20px; border-bottom: 1px solid #eee; align-items: center;" class="cart-item">
                            <img src="../<?php echo htmlspecialchars($item['image_path']); ?>" alt="<?php echo htmlspecialchars($item['name']); ?>" 
                                 style="width: 100px; height: 100px; object-fit: cover; border-radius: 8px;" 
                                 onerror="this.src='https://via.placeholder.com/100x100?text=No+Image'">
                            
                            <div style="flex: 1;">
                                <h3><?php echo htmlspecialchars($item['name']); ?></h3>
                                <p style="color: #666; margin: 5px 0;">Seller: <?php echo htmlspecialchars($item['seller_name']); ?></p>
                                <p class="product-price" style="font-size: 1.2rem;">₱<?php echo number_format($item['price'], 2); ?></p>
                            </div>
                            
                            <div style="text-align: center;">
                                <form method="POST" style="display: inline-block;">
                                    <?php echo csrf_token_field(); ?>
                                    <input type="hidden" name="product_id" value="<?php echo $item['product_id']; ?>">
                                    <input type="number" name="quantity" value="<?php echo $item['quantity']; ?>" min="1" 
                                           style="width: 60px; padding: 5px; text-align: center;" required>
                                    <button type="submit" name="update_quantity" class="btn btn-sm" style="margin-top: 5px;">Update</button>
                                </form>
                            </div>
                            
                            <div style="text-align: right;">
                                <p class="product-price" style="font-size: 1.2rem;">
                                    ₱<?php echo number_format($item['price'] * $item['quantity'], 2); ?>
                                </p>
                                <a href="cart.php?remove=<?php echo $item['product_id']; ?>" class="btn btn-sm btn-secondary" 
                                   onclick="return confirm('Remove this item from cart?');">Remove</a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <!-- Order Summary -->
                <div class="content-card" style="height: fit-content;">
                    <h2>Order Summary</h2>
                    <div style="margin: 20px 0;">
                        <div style="display: flex; justify-content: space-between; margin-bottom: 15px;">
                            <span>Subtotal:</span>
                            <span>₱<?php echo number_format($cart_total, 2); ?></span>
                        </div>
                        <div style="display: flex; justify-content: space-between; margin-bottom: 15px;">
                            <span>Shipping:</span>
                            <span>Free</span>
                        </div>
                        <hr>
                        <div style="display: flex; justify-content: space-between; font-size: 1.2rem; font-weight: bold; margin-top: 15px;">
                            <span>Total:</span>
                            <span class="product-price">₱<?php echo number_format($cart_total, 2); ?></span>
                        </div>
                    </div>
                    
                    <a href="checkout.php" class="btn btn-primary" style="width: 100%; text-align: center; margin-top: 20px;">
                        <i class="fas fa-shopping-bag"></i> Proceed to Checkout
                    </a>
                    
                    <a href="products.php" class="btn btn-secondary" style="width: 100%; text-align: center; margin-top: 10px;">
                        Continue Shopping
                    </a>
                </div>
            </div>
        <?php endif; ?>
    </div>
</main>

<?php include '../includes/footer.php'; ?>
