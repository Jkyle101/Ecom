<?php
require_once '../config/db_config.php';
require_once '../includes/auth_check.php';

require_login();

$order_id = isset($_GET['order_id']) ? (int)$_GET['order_id'] : 0;
$user_id = $_SESSION['user_id'];

// Get order details
$stmt = $conn->prepare("SELECT * FROM orders WHERE id = ? AND user_id = ? AND payment_method = 'Gcash'");
$stmt->bind_param("ii", $order_id, $user_id);
$stmt->execute();
$order = $stmt->get_result()->fetch_assoc();

if (!$order) {
    header("Location: orders.php");
    exit();
}

// Get seller information for this order (assuming single seller per order)
$seller_info = $conn->query("
    SELECT DISTINCT u.username as seller_name, u.gcash_number
    FROM order_items oi
    JOIN users u ON oi.seller_id = u.id
    WHERE oi.order_id = $order_id
    LIMIT 1
")->fetch_assoc();

// Check if seller has GCash number set up
if (empty($seller_info['gcash_number'])) {
    $error = "The seller has not set up their GCash payment details yet. Please contact the seller or choose a different payment method.";
}

$error = '';
$success = '';

// Handle Gcash payment submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf_token();

    $gcash_number = sanitize_input($_POST['gcash_number']);
    $reference_number = sanitize_input($_POST['reference_number']);
    $amount_paid = (float)$_POST['amount_paid'];

    if (empty($gcash_number) || empty($reference_number) || $amount_paid <= 0) {
        $error = "Please fill all required fields";
    } elseif ($amount_paid != $order['total_amount']) {
        $error = "Payment amount must match the order total";
    } else {
        // In a real implementation, you would verify the payment with Gcash API
        // For now, we'll simulate successful payment

        // Update order status to processing (payment received)
        $stmt = $conn->prepare("UPDATE orders SET status = 'processing' WHERE id = ?");
        $stmt->bind_param("i", $order_id);
        $stmt->execute();

        // Store payment details (you might want to create a payments table for this)
        // For now, we'll just mark as paid

        $success = "Payment submitted successfully! Your order is now being processed.";
        header("Location: order_success.php?order_id=$order_id&payment=completed");
        exit();
    }
}
?>

<?php include '../includes/header.php'; ?>
<link rel="stylesheet" href="../assets/css/dashboard.css">

<main class="main-wrapper">
    <div class="container">
        <div class="content-card" style="max-width: 600px; margin: 40px auto;">
            <div style="text-align: center; margin-bottom: 30px;">
                <div style="width: 150px; height: 50px; background: #0066cc; color: white; display: flex; align-items: center; justify-content: center; border-radius: 5px; font-weight: bold; margin: 0 auto;">GCash</div>
                <h2>GCash Payment</h2>
                <p>Complete your payment for Order #<?php echo $order['id']; ?></p>
            </div>

            <?php if (!empty($error)): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>

            <div style="background: #f8f9fa; padding: 20px; border-radius: 10px; margin-bottom: 20px;">
                <h3>Payment Details</h3>
                <p><strong>Order Total:</strong> ₱<?php echo number_format($order['total_amount'], 2); ?></p>
                <p><strong>Merchant Name:</strong> <?php echo htmlspecialchars($seller_info['seller_name']); ?></p>
                <p><strong>GCash Account Number:</strong> <?php echo htmlspecialchars($seller_info['gcash_number'] ?? 'Not Available'); ?></p>
            </div>

            <div style="background: #e8f5e8; border: 1px solid #4CAF50; padding: 15px; border-radius: 8px; margin-bottom: 20px;">
                <h4 style="color: #2E7D32; margin: 0 0 10px 0;">📱 Payment Instructions:</h4>
                <ol style="margin: 0; padding-left: 20px; color: #2E7D32;">
                    <li>Open your GCash app</li>
                    <li>Tap "Pay QR" or "Express Send"</li>
                    <li>Enter merchant account: <strong><?php echo htmlspecialchars($seller_info['gcash_number'] ?? 'Not Available'); ?></strong></li>
                    <li>Enter amount: <strong>₱<?php echo number_format($order['total_amount'], 2); ?></strong></li>
                    <li>Complete the payment and note the reference number</li>
                </ol>
            </div>

            <form method="POST">
                <?php echo csrf_token_field(); ?>

                <div class="form-group">
                    <label>Your GCash Number *</label>
                    <input type="text" name="gcash_number" class="form-control" required
                           placeholder="09XXXXXXXXX" pattern="09[0-9]{9}" maxlength="11">
                    <small>The mobile number linked to your GCash account</small>
                </div>

                <div class="form-group">
                    <label>GCash Reference Number *</label>
                    <input type="text" name="reference_number" class="form-control" required
                           placeholder="Enter the reference number from your payment">
                    <small>You can find this in your GCash transaction history</small>
                </div>

                <div class="form-group">
                    <label>Amount Paid *</label>
                    <input type="number" name="amount_paid" class="form-control" required
                           step="0.01" min="<?php echo $order['total_amount']; ?>" max="<?php echo $order['total_amount']; ?>"
                           value="<?php echo $order['total_amount']; ?>" readonly>
                </div>

                <div class="form-group">
                    <button type="submit" class="btn btn-primary" style="width: 100%;">
                        <i class="fas fa-check"></i> Confirm Payment
                    </button>
                </div>

                <div class="form-group" style="text-align: center;">
                    <a href="view_order.php?id=<?php echo $order['id']; ?>" class="btn btn-secondary">Back to Order</a>
                </div>
            </form>
        </div>
    </div>
</main>

<?php include '../includes/footer.php'; ?>
