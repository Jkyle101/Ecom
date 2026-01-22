<?php
require_once '../config/db_config.php';
require_once '../includes/auth_check.php';

require_login();

$order_id = isset($_GET['order_id']) ? (int)$_GET['order_id'] : 0;
$user_id = $_SESSION['user_id'];
$payment_completed = isset($_GET['payment']) && $_GET['payment'] == 'completed';

if ($order_id == 0) {
    header("Location: orders.php");
    exit();
}

// Get order details
$stmt = $conn->prepare("SELECT * FROM orders WHERE id = ? AND user_id = ?");
$stmt->bind_param("ii", $order_id, $user_id);
$stmt->execute();
$order = $stmt->get_result()->fetch_assoc();

if (!$order) {
    header("Location: orders.php");
    exit();
}
?>

<?php include '../includes/header.php'; ?>
<link rel="stylesheet" href="../assets/css/dashboard.css">

<main class="main-wrapper">
    <div class="container">
        <div class="content-card" style="text-align: center; max-width: 600px; margin: 40px auto;">
            <div style="font-size: 4rem; color: var(--primary-green); margin-bottom: 20px;">
                <i class="fas fa-check-circle"></i>
            </div>
            <h1><?php echo $payment_completed ? 'Payment Confirmed!' : 'Order Placed Successfully!'; ?></h1>
            <p style="font-size: 1.2rem; color: #666; margin: 20px 0;">
                <?php if ($payment_completed): ?>
                    Thank you for your payment! Your GCash payment has been confirmed and your order is now being processed.
                <?php elseif ($order['payment_method'] == 'Gcash'): ?>
                    Thank you for your order. Please complete your GCash payment to proceed with processing.
                <?php else: ?>
                    Thank you for your order. Your order has been received and is being processed.
                <?php endif; ?>
            </p>
            
            <div style="background: #f9f9f9; padding: 20px; border-radius: 10px; margin: 30px 0;">
                <p><strong>Order ID:</strong> #<?php echo $order['id']; ?></p>
                <p><strong>Total Amount:</strong> ₱<?php echo number_format($order['total_amount'], 2); ?></p>
                <p><strong>Payment Method:</strong> <?php echo htmlspecialchars($order['payment_method']); ?></p>
                <p><strong>Order Status:</strong> <?php echo ucfirst($order['status']); ?></p>
            </div>
            
            <div style="margin-top: 30px;">
                <a href="orders.php" class="btn btn-primary">View My Orders</a>
                <a href="products.php" class="btn btn-secondary">Continue Shopping</a>
            </div>
        </div>
    </div>
</main>

<?php include '../includes/footer.php'; ?>
