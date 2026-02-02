<?php
require_once '../config/db_config.php';
require_once '../includes/auth_check.php';

require_login();
if ($_SESSION['role'] !== 'seller') {
    header("Location: ../buyer/dashboard.php");
    exit();
}

$seller_id = $_SESSION['user_id'];
$order_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$success = '';
$error = '';

// Handle order deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_order'])) {
    check_csrf_token();

    // Verify the order belongs to this seller and check if it can be deleted
    $stmt = $conn->prepare("
        SELECT o.status FROM orders o
        JOIN order_items oi ON o.id = oi.order_id
        WHERE o.id = ? AND oi.seller_id = ?
        LIMIT 1
    ");
    $stmt->bind_param("ii", $order_id, $seller_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $order_status = $result->fetch_assoc()['status'];

        // Only allow deletion of delivered orders (completed transactions)
        if ($order_status === 'delivered') {
            // Delete order items first (due to foreign key constraints)
            $conn->query("DELETE FROM order_items WHERE order_id = $order_id");
            // Delete the order
            $conn->query("DELETE FROM orders WHERE id = $order_id");

            header("Location: transactions.php?deleted=1");
            exit();
        } else {
            $error = "Cannot delete order with status: " . ucfirst($order_status);
        }
    } else {
        $error = "Order not found or access denied";
    }
}

// Handle status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    check_csrf_token();

    $status = sanitize_input($_POST['status']);

    // Verify the order belongs to this seller
    $stmt = $conn->prepare("
        SELECT oi.id FROM order_items oi
        JOIN orders o ON oi.order_id = o.id
        WHERE o.id = ? AND oi.seller_id = ?
    ");
    $stmt->bind_param("ii", $order_id, $seller_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $stmt = $conn->prepare("UPDATE orders SET status = ? WHERE id = ?");
        $stmt->bind_param("si", $status, $order_id);

        if ($stmt->execute()) {
            $success = "Order status updated successfully!";
        } else {
            $error = "Failed to update order status";
        }
        $stmt->close();
    } else {
        $error = "Order not found or access denied";
    }
}

// Handle tracking number update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_tracking'])) {
    check_csrf_token();

    $tracking_number = sanitize_input(trim($_POST['tracking_number']));

    // Verify the order belongs to this seller
    $stmt = $conn->prepare("
        SELECT oi.id FROM order_items oi
        JOIN orders o ON oi.order_id = o.id
        WHERE o.id = ? AND oi.seller_id = ?
    ");
    $stmt->bind_param("ii", $order_id, $seller_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $stmt = $conn->prepare("UPDATE orders SET tracking_number = ? WHERE id = ?");
        $stmt->bind_param("si", $tracking_number, $order_id);

        if ($stmt->execute()) {
            $success = "Tracking number updated successfully!";
        } else {
            $error = "Failed to update tracking number";
        }
        $stmt->close();
    } else {
        $error = "Order not found or access denied";
    }
}

// Get order details - verify seller owns at least one item in this order
$stmt = $conn->prepare("
    SELECT o.*, b.name as building_name, r.name as room_name FROM orders o
    JOIN order_items oi ON o.id = oi.order_id
    LEFT JOIN buildings b ON o.building_id = b.id
    LEFT JOIN rooms r ON o.room_id = r.id
    WHERE o.id = ? AND oi.seller_id = ?
    LIMIT 1
");
$stmt->bind_param("ii", $order_id, $seller_id);
$stmt->execute();
$order = $stmt->get_result()->fetch_assoc();

if (!$order) {
    header("Location: transactions.php");
    exit();
}

// Get order items for this seller
$order_items = $conn->query("
    SELECT oi.*, p.name, p.image_path, u.username as seller_name
    FROM order_items oi
    JOIN products p ON oi.product_id = p.id
    JOIN users u ON oi.seller_id = u.id
    WHERE oi.order_id = $order_id AND oi.seller_id = $seller_id
");

// Get buyer information
$buyer = $conn->query("SELECT username, email, phone FROM users WHERE id = {$order['user_id']}")->fetch_assoc();

// Get seller information (current user)
$user = $conn->query("SELECT gcash_number FROM users WHERE id = $seller_id")->fetch_assoc();
?>

<?php include '../includes/header.php'; ?>
<link rel="stylesheet" href="../assets/css/dashboard.css">

<main class="main-wrapper">
    <div class="container">
        <div class="page-header">
            <h1>Order Management</h1>
            <div class="breadcrumb">Home > Transactions > Order #<?php echo $order['id']; ?></div>
        </div>

        <?php if (!empty($error)): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>

        <?php if (!empty($success)): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>

        <div class="content-card">
            <a href="transactions.php" class="btn btn-secondary">← Back to Transactions</a>

            <div style="margin-top: 30px;">
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 30px;">
                    <div>
                        <h3>Order Information</h3>
                        <p><strong>Order ID:</strong> #<?php echo $order['id']; ?></p>
                        <p><strong>Buyer:</strong> <?php echo htmlspecialchars($buyer['username']); ?></p>
                        <p><strong>Email:</strong> <?php echo htmlspecialchars($buyer['email']); ?></p>
                        <?php if (!empty($buyer['phone'])): ?>
                            <p><strong>Phone:</strong> <?php echo htmlspecialchars($buyer['phone']); ?></p>
                        <?php endif; ?>
                        <p><strong>Order Date:</strong> <?php echo date('M j, Y g:i A', strtotime($order['created_at'])); ?></p>
                    </div>
                    <div>
                        <h3>Order Management</h3>
                        <form method="POST" style="margin-bottom: 15px;">
                            <?php echo csrf_token_field(); ?>
                            <label><strong>Order Status:</strong></label>
                            <div style="display: flex; gap: 10px; margin-top: 5px;">
                                <select name="status" class="form-control" style="width: 200px;">
                                    <option value="pending" <?php echo $order['status'] === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                    <option value="processing" <?php echo $order['status'] === 'processing' ? 'selected' : ''; ?>>Processing</option>
                                    <option value="shipped" <?php echo $order['status'] === 'shipped' ? 'selected' : ''; ?>>Shipped</option>
                                    <option value="delivered" <?php echo $order['status'] === 'delivered' ? 'selected' : ''; ?>>Delivered</option>
                                    <option value="cancelled" <?php echo $order['status'] === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                                </select>
                                <button type="submit" name="update_status" class="btn btn-primary">Update Status</button>
                            </div>
                        </form>

                        <form method="POST" style="margin-bottom: 15px;">
                            <?php echo csrf_token_field(); ?>
                            <label><strong>Tracking Number:</strong></label>
                            <div style="display: flex; gap: 10px; margin-top: 5px;">
                                <input type="text" name="tracking_number" value="<?php echo htmlspecialchars($order['tracking_number'] ?? ''); ?>"
                                       placeholder="Enter tracking number" class="form-control" style="width: 200px;">
                                <button type="submit" name="update_tracking" class="btn btn-secondary">Update Tracking</button>
                            </div>
                        </form>

                        <?php if ($order['status'] === 'delivered'): ?>
                            <div class="alert alert-success" style="margin-top: 15px;">
                                <i class="fas fa-check-circle"></i> <strong>Order Completed:</strong> This transaction has been successfully delivered.
                                <form method="POST" style="display: inline-block; margin-left: 15px;" onsubmit="return confirm('Are you sure you want to delete this completed transaction? This will permanently remove it from your records.');">
                                    <?php echo csrf_token_field(); ?>
                                    <button type="submit" name="delete_order" class="btn btn-sm" style="background: #28a745; color: white; border: none;">
                                        <i class="fas fa-trash"></i> Remove Transaction
                                    </button>
                                </form>
                            </div>
                        <?php endif; ?>

        <?php if ($order['status'] === 'cancelled'): ?>
            <div class="alert alert-warning" style="margin-top: 15px;">
                <i class="fas fa-exclamation-triangle"></i> <strong>Order Cancelled:</strong> This order has been cancelled by the buyer.
                <?php if (in_array($order['status'], ['pending', 'cancelled'])): ?>
                    <form method="POST" style="display: inline-block; margin-left: 15px;" onsubmit="return confirm('Are you sure you want to delete this cancelled order? This action cannot be undone.');">
                        <?php echo csrf_token_field(); ?>
                        <button type="submit" name="delete_order" class="btn btn-sm" style="background: #6c757d; color: white; border: none;">
                            <i class="fas fa-trash"></i> Remove Order
                        </button>
                    </form>
                <?php endif; ?>
            </div>
        <?php elseif (in_array($order['status'], ['pending', 'cancelled'])): ?>
            <div class="alert alert-info" style="margin-top: 15px;">
                <i class="fas fa-info-circle"></i> This order can be deleted if no longer needed.
                <form method="POST" style="display: inline-block; margin-left: 15px;" onsubmit="return confirm('Are you sure you want to delete this order? This action cannot be undone.');">
                    <?php echo csrf_token_field(); ?>
                    <button type="submit" name="delete_order" class="btn btn-sm" style="background: #6c757d; color: white; border: none;">
                        <i class="fas fa-trash"></i> Delete Order
                    </button>
                </form>
            </div>
        <?php endif; ?>
                    </div>
                </div>

                <div style="margin-bottom: 30px;">
                    <h3>Payment & Shipping</h3>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                        <div>
                            <p><strong>Payment Method:</strong> <?php echo htmlspecialchars($order['payment_method']); ?></p>
                            <p><strong>Total Amount:</strong> <span class="product-price" style="font-size: 1.5rem;">₱<?php echo number_format($order['total_amount'], 2); ?></span></p>
                            <?php if ($order['payment_method'] === 'GCash' && !empty($user['gcash_number'])): ?>
                                <div style="background: #e8f5e8; border: 1px solid #c3e6c3; border-radius: 6px; padding: 10px; margin-top: 10px;">
                                    <p style="margin: 0; color: #2d5a2d;"><strong><i class="fas fa-mobile-alt"></i> Your GCash Number:</strong></p>
                                    <p style="margin: 5px 0 0 0; font-family: monospace; font-size: 1.1rem; color: #2d5a2d;"><?php echo htmlspecialchars($user['gcash_number']); ?></p>
                                    <small style="color: #5a8f5a;">Buyer can send payment to this number</small>
                                </div>
                            <?php elseif ($order['payment_method'] === 'GCash' && empty($user['gcash_number'])): ?>
                                <div style="background: #fff3cd; border: 1px solid #ffeaa7; border-radius: 6px; padding: 10px; margin-top: 10px;">
                                    <p style="margin: 0; color: #856404;"><strong><i class="fas fa-exclamation-triangle"></i> GCash Number Required</strong></p>
                                    <p style="margin: 5px 0 0 0; color: #856404;">You need to set up your GCash number to receive payments.</p>
                                    <a href="account.php" class="btn btn-sm" style="background: #ffc107; color: #000; margin-top: 5px;">Add GCash Number</a>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div>
                            <p><strong>Status:</strong> <span class="product-category"><?php echo ucfirst($order['status']); ?></span></p>
                            <?php if (!empty($order['tracking_number'])): ?>
                                <p><strong>Tracking:</strong> <span style="font-family: monospace; background: #f5f5f5; padding: 2px 6px; border-radius: 3px;"><?php echo htmlspecialchars($order['tracking_number']); ?></span></p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div style="margin-bottom: 30px;">
                    <h3>Delivery Location</h3>
                    <p><strong>Building:</strong> <?php echo htmlspecialchars($order['building_name'] ?? 'N/A'); ?></p>
                    <p><strong>Room:</strong> <?php echo htmlspecialchars($order['room_name'] ?? 'N/A'); ?></p>
                </div>

                <h3>Your Products in This Order</h3>
                <div class="product-grid">
                    <?php while ($item = $order_items->fetch_assoc()): ?>
                        <div class="product-card">
                            <div class="product-image">
                                <img src="../<?php echo htmlspecialchars($item['image_path']); ?>" alt="<?php echo htmlspecialchars($item['name']); ?>"
                                     onerror="this.src='https://via.placeholder.com/250x200?text=No+Image'">
                            </div>
                            <div class="product-info">
                                <h3><?php echo htmlspecialchars($item['name']); ?></h3>
                                <p>Quantity: <?php echo $item['quantity']; ?></p>
                                <p class="product-price">₱<?php echo number_format($item['price'], 2); ?> each</p>
                                <p class="product-price">Subtotal: ₱<?php echo number_format($item['price'] * $item['quantity'], 2); ?></p>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>
            </div>
        </div>
    </div>
</main>

<?php include '../includes/footer.php'; ?>
