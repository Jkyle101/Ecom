<?php
require_once '../config/db_config.php';
require_once '../includes/auth_check.php';
require_once '../includes/cart.php';

require_login();

$user_id = $_SESSION['user_id'];
$cart_items = getCartItems($user_id);
$cart_total = getCartTotal($user_id);

if (empty($cart_items)) {
    header("Location: cart.php");
    exit();
}

// Get user details
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

// Get buildings
$buildings_query = $conn->query("SELECT * FROM buildings ORDER BY name");
$buildings = $buildings_query->fetch_all(MYSQLI_ASSOC);

// Check if all sellers have GCash numbers set up
$sellers_gcash_status = $conn->prepare("
    SELECT DISTINCT u.username as seller_name, u.gcash_number
    FROM cart c
    JOIN products p ON c.product_id = p.id
    JOIN users u ON p.seller_id = u.id
    WHERE c.user_id = ?
");
$sellers_gcash_status->bind_param("i", $user_id);
$sellers_gcash_status->execute();
$sellers_gcash_status = $sellers_gcash_status->get_result();
$gcash_available = true;
$sellers_without_gcash = [];

while ($seller = $sellers_gcash_status->fetch_assoc()) {
    if (empty($seller['gcash_number'])) {
        $gcash_available = false;
        $sellers_without_gcash[] = $seller['seller_name'];
    }
}

$error = '';
$success = '';

// Handle checkout
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf_token();

    $payment_method = sanitize_input($_POST['payment_method']);
    $building_id = sanitize_input($_POST['building_id']);
    $room_id = sanitize_input($_POST['room_id']);

    if (empty($payment_method) || empty($building_id) || empty($room_id)) {
        $error = "Please fill all required fields";
    } elseif ($payment_method === 'Gcash' && !$gcash_available) {
        $error = "GCash payment is not available because some sellers haven't set up their GCash accounts yet.";
    } else {
        // Start transaction
        $conn->begin_transaction();

        try {
            // Create order
            $stmt = $conn->prepare("INSERT INTO orders (user_id, total_amount, payment_method, building_id, room_id) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("idsii", $user_id, $cart_total, $payment_method, $building_id, $room_id);
            $stmt->execute();
            $order_id = $stmt->insert_id;
            
            // Create order items
            foreach ($cart_items as $item) {
                $stmt = $conn->prepare("INSERT INTO order_items (order_id, product_id, seller_id, quantity, price) VALUES (?, ?, ?, ?, ?)");
                $stmt->bind_param("iiiid", $order_id, $item['product_id'], $item['seller_id'], $item['quantity'], $item['price']);
                $stmt->execute();
            }
            
            // Clear cart
            clearCart($user_id);
            
            // Create notification for sellers
            require_once '../includes/notifications.php';
            foreach ($cart_items as $item) {
                createNotification($item['seller_id'], "You have a new order! Order #$order_id", 'order', $item['product_id']);
            }
            
            $conn->commit();

            // Redirect based on payment method
            if ($payment_method == 'Gcash') {
                header("Location: gcash_payment.php?order_id=$order_id");
            } else {
                header("Location: order_success.php?order_id=$order_id");
            }
            exit();
        } catch (Exception $e) {
            $conn->rollback();
            $error = "Order failed. Please try again.";
        }
    }
}
?>

<?php include '../includes/header.php'; ?>
<link rel="stylesheet" href="../assets/css/dashboard.css">

<main class="main-wrapper">
    <div class="container">
        <div class="page-header">
            <h1>Checkout</h1>
            <div class="breadcrumb">Home > Cart > Checkout</div>
        </div>

        <?php if (!empty($error)): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>

        <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 20px;" class="checkout-layout">
            <!-- Checkout Form -->
            <div class="content-card">
                <h2>Shipping Information</h2>
                <form method="POST">
                    <?php echo csrf_token_field(); ?>
                    
                    <div class="form-group">
                        <label>Full Name</label>
                        <input type="text" class="form-control" value="<?php echo htmlspecialchars($user['username']); ?>" disabled>
                    </div>
                    
                    <div class="form-group">
                        <label>Email</label>
                        <input type="email" class="form-control" value="<?php echo htmlspecialchars($user['email']); ?>" disabled>
                    </div>
                    
                    <div class="form-group">
                        <label>Building *</label>
                        <select name="building_id" id="building_id" class="form-control" required>
                            <option value="">Select Building</option>
                            <?php foreach ($buildings as $building): ?>
                                <option value="<?php echo $building['id']; ?>"><?php echo htmlspecialchars($building['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Room *</label>
                        <select name="room_id" id="room_id" class="form-control" required>
                            <option value="">Select Room</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>Payment Method *</label>
                        <select name="payment_method" class="form-control" required>
                            <option value="">Select Payment Method</option>
                            <option value="Cash on Delivery">Cash on Delivery</option>
                            <?php if ($gcash_available): ?>
                                <option value="Gcash">GCash</option>
                            <?php else: ?>
                                <option value="Gcash" disabled style="color: #999;">GCash (Not Available)</option>
                            <?php endif; ?>
                        </select>
                        <?php if (!$gcash_available): ?>
                            <small style="color: #dc3545;">
                                <i class="fas fa-exclamation-triangle"></i>
                                GCash payment is not available because some sellers haven't set up their GCash accounts yet.
                                Sellers without GCash: <?php echo implode(', ', $sellers_without_gcash); ?>
                            </small>
                        <?php endif; ?>
                    </div>
                    
                    <div class="form-group">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-lock"></i> Place Order
                        </button>
                        <a href="cart.php" class="btn btn-secondary">Back to Cart</a>
                    </div>
                </form>
            </div>
            
            <!-- Order Summary -->
            <div class="content-card" style="height: fit-content;">
                <h2>Order Summary</h2>
                <?php foreach ($cart_items as $item): ?>
                    <div style="display: flex; gap: 10px; padding: 10px 0; border-bottom: 1px solid #eee;">
                        <img src="../<?php echo htmlspecialchars($item['image_path']); ?>" alt="<?php echo htmlspecialchars($item['name']); ?>" 
                             style="width: 60px; height: 60px; object-fit: cover; border-radius: 5px;" 
                             onerror="this.src='https://via.placeholder.com/60x60?text=No+Image'">
                        <div style="flex: 1;">
                            <p style="margin: 0; font-weight: bold;"><?php echo htmlspecialchars($item['name']); ?></p>
                            <p style="margin: 0; color: #666; font-size: 0.9rem;">Qty: <?php echo $item['quantity']; ?></p>
                            <p style="margin: 0; color: #666; font-size: 0.9rem;">₱<?php echo number_format($item['price'], 2); ?></p>
                        </div>
                    </div>
                <?php endforeach; ?>
                
                <div style="margin-top: 20px;">
                    <div style="display: flex; justify-content: space-between; margin-bottom: 10px;">
                        <span>Subtotal:</span>
                        <span>₱<?php echo number_format($cart_total, 2); ?></span>
                    </div>
                    <div style="display: flex; justify-content: space-between; margin-bottom: 10px;">
                        <span>Shipping:</span>
                        <span>Free</span>
                    </div>
                    <hr>
                    <div style="display: flex; justify-content: space-between; font-size: 1.2rem; font-weight: bold; margin-top: 10px;">
                        <span>Total:</span>
                        <span class="product-price">₱<?php echo number_format($cart_total, 2); ?></span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<?php include '../includes/footer.php'; ?>

<script>
document.getElementById('building_id').addEventListener('change', function() {
    const buildingId = this.value;
    const roomSelect = document.getElementById('room_id');

    // Clear existing options
    roomSelect.innerHTML = '<option value="">Select Room</option>';

    if (buildingId) {
        // Fetch rooms for the selected building
        fetch(`../get_rooms.php?building_id=${buildingId}`)
            .then(response => response.json())
            .then(data => {
                data.forEach(room => {
                    const option = document.createElement('option');
                    option.value = room.id;
                    option.textContent = room.name;
                    roomSelect.appendChild(option);
                });
            })
            .catch(error => {
                console.error('Error fetching rooms:', error);
            });
    }
});
</script>
