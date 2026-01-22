<?php
require_once '../config/db_config.php';
require_once '../includes/auth_check.php';

require_login();

$order_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$user_id = $_SESSION['user_id'];

// Get order details
$stmt = $conn->prepare("SELECT * FROM orders WHERE id = ? AND user_id = ?");
$stmt->bind_param("ii", $order_id, $user_id);
$stmt->execute();
$order = $stmt->get_result()->fetch_assoc();

if (!$order) {
    header("Location: orders.php");
    exit();
}

// Handle order cancellation
$success = '';
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancel_order'])) {
    check_csrf_token();

    // Only allow cancellation for orders that haven't been shipped
    if (in_array($order['status'], ['pending', 'processing'])) {
        // Update order status to cancelled
        $stmt = $conn->prepare("UPDATE orders SET status = 'cancelled' WHERE id = ?");
        $stmt->bind_param("i", $order_id);
        $stmt->execute();

        // Create notifications for sellers
        require_once '../includes/notifications.php';
        $seller_ids = $conn->query("SELECT DISTINCT seller_id FROM order_items WHERE order_id = $order_id");

        while ($seller = $seller_ids->fetch_assoc()) {
            createNotification($seller['seller_id'], "Order #$order_id has been cancelled by the buyer.", 'order_cancelled', null);
        }

        $success = "Order cancelled successfully. Any payments will be refunded according to our refund policy.";
        $order['status'] = 'cancelled'; // Update local variable
    } else {
        $error = "This order cannot be cancelled at this stage.";
    }
}

// Handle delete from history (for delivered orders)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_from_history'])) {
    check_csrf_token();

    // Only allow deletion for delivered orders
    if ($order['status'] === 'delivered') {
        // Delete order items first (due to foreign key constraints)
        $conn->query("DELETE FROM order_items WHERE order_id = $order_id");
        // Delete the order
        $conn->query("DELETE FROM orders WHERE id = $order_id");

        header("Location: orders.php?deleted=1");
        exit();
    } else {
        $error = "This order cannot be removed from history.";
    }
}

// Get order items
$order_items = $conn->query("SELECT oi.*, p.name, p.image_path, u.username as seller_name
                             FROM order_items oi
                             JOIN products p ON oi.product_id = p.id
                             JOIN users u ON oi.seller_id = u.id
                             WHERE oi.order_id = $order_id");
?>

<?php include '../includes/header.php'; ?>
<link rel="stylesheet" href="../assets/css/dashboard.css">

<main class="main-wrapper">
    <div class="container">
        <div class="page-header">
            <h1>Order Details</h1>
            <div class="breadcrumb">Home > Orders > Order #<?php echo $order['id']; ?></div>
        </div>
        
        <div class="content-card">
            <a href="orders.php" class="btn btn-secondary">← Back to Orders</a>

            <?php if (!empty($success)): ?>
                <div class="alert alert-success" style="margin-top: 15px;"><?php echo $success; ?></div>
            <?php endif; ?>

            <?php if (!empty($error)): ?>
                <div class="alert alert-danger" style="margin-top: 15px;"><?php echo $error; ?></div>
            <?php endif; ?>

            <?php if (in_array($order['status'], ['pending', 'processing'])): ?>
                <form method="POST" style="margin-top: 15px;" onsubmit="return confirm('Are you sure you want to cancel this order? This action cannot be undone.');">
                    <?php echo csrf_token_field(); ?>
                    <button type="submit" name="cancel_order" class="btn" style="background: #dc3545; color: white; border: none;">
                        <i class="fas fa-times"></i> Cancel Order
                    </button>
                    <small style="color: #666; margin-left: 10px;">Cancel before shipping to get refund</small>
                </form>
            <?php elseif ($order['status'] === 'cancelled'): ?>
                <div class="alert alert-info" style="margin-top: 15px;">
                    <i class="fas fa-info-circle"></i> This order has been cancelled.
                </div>
            <?php elseif ($order['status'] === 'delivered'): ?>
                <div class="alert alert-success" style="margin-top: 15px;">
                    <i class="fas fa-check-circle"></i> <strong>Order Completed:</strong> This order has been successfully delivered.
                    <form method="POST" style="display: inline-block; margin-left: 15px;" onsubmit="return confirm('Are you sure you want to remove this order from your history? This action cannot be undone.');">
                        <?php echo csrf_token_field(); ?>
                        <input type="hidden" name="delete_from_history" value="1">
                        <button type="submit" class="btn btn-sm" style="background: #6c757d; color: white; border: none;">
                            <i class="fas fa-trash"></i> Remove from History
                        </button>
                    </form>
                    <small style="display: block; margin-top: 5px; color: #666;">You can keep this in your history for reference or remove it if you prefer.</small>
                </div>
            <?php endif; ?>
            
            <div style="margin-top: 30px;">
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 30px;">
                    <div>
                        <h3>Order Information</h3>
                        <p><strong>Order ID:</strong> #<?php echo $order['id']; ?></p>
                        <p><strong>Order Date:</strong> <?php echo date('M j, Y g:i A', strtotime($order['created_at'])); ?></p>
                        <p><strong>Status:</strong> <span class="product-category"><?php echo ucfirst($order['status']); ?></span></p>
                        <?php if (!empty($order['tracking_number'])): ?>
                            <p><strong>Tracking Number:</strong> <span style="font-family: monospace; background: #f5f5f5; padding: 2px 6px; border-radius: 3px;"><?php echo htmlspecialchars($order['tracking_number']); ?></span></p>
                        <?php endif; ?>

                        <?php if ($order['payment_method'] == 'Gcash' && $order['status'] == 'pending'): ?>
                            <p><strong>Payment Status:</strong> <span style="color: #ff6b35;">Payment Required</span></p>
                            <p style="margin-top: 10px;">
                                <a href="gcash_payment.php?order_id=<?php echo $order['id']; ?>" class="btn btn-primary">
                                    <i class="fas fa-mobile-alt"></i> Complete GCash Payment
                                </a>
                            </p>
                        <?php endif; ?>
                    </div>
                    <div>
                        <h3>Payment Information</h3>
                        <p><strong>Payment Method:</strong> <?php echo htmlspecialchars($order['payment_method']); ?></p>
                        <p><strong>Total Amount:</strong> <span class="product-price" style="font-size: 1.5rem;">₱<?php echo number_format($order['total_amount'], 2); ?></span></p>
                    </div>
                </div>
                
                <div style="margin-bottom: 30px;">
                    <h3>Shipping Address</h3>
                    <p><?php echo nl2br(htmlspecialchars($order['shipping_address'])); ?></p>
                </div>
                
                <h3>Order Items</h3>
                <div class="product-grid">
                    <?php while ($item = $order_items->fetch_assoc()): ?>
                        <div class="product-card">
                            <div class="product-image">
                                <img src="../<?php echo htmlspecialchars($item['image_path']); ?>" alt="<?php echo htmlspecialchars($item['name']); ?>" 
                                     onerror="this.src='https://via.placeholder.com/250x200?text=No+Image'">
                            </div>
                            <div class="product-info">
                                <h3><?php echo htmlspecialchars($item['name']); ?></h3>
                                <p style="color: #666;">Seller: <?php echo htmlspecialchars($item['seller_name']); ?></p>
                                <p>Quantity: <?php echo $item['quantity']; ?></p>
                                <p class="product-price">₱<?php echo number_format($item['price'], 2); ?> each</p>
                                <p class="product-price">Total: ₱<?php echo number_format($item['price'] * $item['quantity'], 2); ?></p>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>
            </div>
        </div>
    </div>
</main>

<?php include '../includes/footer.php'; ?>
