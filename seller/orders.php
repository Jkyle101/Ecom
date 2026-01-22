<?php
require_once '../config/db_config.php';
require_once '../includes/auth_check.php';

require_login();
if ($_SESSION['role'] !== 'seller') {
    // Debug: Log the current role for troubleshooting
    error_log("Access denied to seller orders. User role: " . ($_SESSION['role'] ?? 'not set') . ", User ID: " . ($_SESSION['user_id'] ?? 'not set'));
    header("Location: ../buyer/dashboard.php");
    exit();
}

$seller_id = $_SESSION['user_id'];
$error = '';
$success = '';

// Handle tracking number update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_tracking'])) {
    check_csrf_token();

    $order_id = (int)$_POST['order_id'];
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

// Handle status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    check_csrf_token();

    $order_id = (int)$_POST['order_id'];
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

// Get orders for this seller's products
$orders = $conn->query("
    SELECT DISTINCT
        o.id as order_id,
        o.user_id as buyer_id,
        u.username as buyer_name,
        u.email as buyer_email,
        u.phone as buyer_phone,
        o.total_amount,
        o.payment_method,
        o.shipping_address,
        o.status,
        o.tracking_number,
        o.created_at,
        GROUP_CONCAT(p.name SEPARATOR ', ') as product_names,
        SUM(oi.quantity) as total_quantity
    FROM orders o
    JOIN order_items oi ON o.id = oi.order_id
    JOIN products p ON oi.product_id = p.id
    JOIN users u ON o.user_id = u.id
    WHERE oi.seller_id = $seller_id
    GROUP BY o.id
    ORDER BY o.created_at DESC
");
?>

<?php include '../includes/header.php'; ?>
<link rel="stylesheet" href="../assets/css/dashboard.css">

<main class="main-wrapper">
    <div class="container">
        <div class="page-header">
            <h1>My Orders</h1>
            <div class="breadcrumb">Home > Orders</div>
        </div>

        <?php if (!empty($error)): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>

        <?php if (!empty($success)): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>

        <?php if ($orders && $orders->num_rows > 0): ?>
            <div class="content-card">
                <div class="table-responsive">
                    <table style="width: 100%; border-collapse: collapse;">
                        <thead>
                            <tr style="border-bottom: 2px solid #ddd;">
                                <th style="padding: 12px; text-align: left;">Order ID</th>
                                <th style="padding: 12px; text-align: left;">Buyer</th>
                                <th style="padding: 12px; text-align: left;">Products</th>
                                <th style="padding: 12px; text-align: left;">Amount</th>
                                <th style="padding: 12px; text-align: left;">Payment</th>
                                <th style="padding: 12px; text-align: left;">Status</th>
                                <th style="padding: 12px; text-align: left;">Tracking</th>
                                <th style="padding: 12px; text-align: left;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($order = $orders->fetch_assoc()): ?>
                                <tr style="border-bottom: 1px solid #eee;">
                                    <td style="padding: 12px;">
                                        <strong>#<?php echo $order['order_id']; ?></strong><br>
                                        <small style="color: #666;"><?php echo date('M j, Y', strtotime($order['created_at'])); ?></small>
                                    </td>
                                    <td style="padding: 12px;">
                                        <strong><?php echo htmlspecialchars($order['buyer_name']); ?></strong><br>
                                        <small><?php echo htmlspecialchars($order['buyer_email']); ?></small><br>
                                        <small><?php echo htmlspecialchars($order['buyer_phone'] ?? 'No phone'); ?></small>
                                    </td>
                                    <td style="padding: 12px;">
                                        <?php echo htmlspecialchars($order['product_names']); ?><br>
                                        <small style="color: #666;">Qty: <?php echo $order['total_quantity']; ?></small>
                                    </td>
                                    <td style="padding: 12px; font-weight: bold;">
                                        ₱<?php echo number_format($order['total_amount'], 2); ?>
                                    </td>
                                    <td style="padding: 12px;">
                                        <?php echo htmlspecialchars($order['payment_method']); ?>
                                    </td>
                                    <td style="padding: 12px;">
                                        <form method="POST" style="display: inline;">
                                            <?php echo csrf_token_field(); ?>
                                            <input type="hidden" name="order_id" value="<?php echo $order['order_id']; ?>">
                                            <select name="status" onchange="this.form.submit()" style="padding: 4px 8px; border: 1px solid #ddd; border-radius: 4px;">
                                                <option value="pending" <?php echo $order['status'] == 'pending' ? 'selected' : ''; ?>>Pending</option>
                                                <option value="processing" <?php echo $order['status'] == 'processing' ? 'selected' : ''; ?>>Processing</option>
                                                <option value="shipped" <?php echo $order['status'] == 'shipped' ? 'selected' : ''; ?>>Shipped</option>
                                                <option value="delivered" <?php echo $order['status'] == 'delivered' ? 'selected' : ''; ?>>Delivered</option>
                                                <option value="cancelled" <?php echo $order['status'] == 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                                            </select>
                                            <input type="hidden" name="update_status" value="1">
                                        </form>
                                    </td>
                                    <td style="padding: 12px;">
                                        <form method="POST" style="display: inline;">
                                            <?php echo csrf_token_field(); ?>
                                            <input type="hidden" name="order_id" value="<?php echo $order['order_id']; ?>">
                                            <input type="text" name="tracking_number" value="<?php echo htmlspecialchars($order['tracking_number'] ?? ''); ?>"
                                                   placeholder="Enter tracking #" style="padding: 4px 8px; border: 1px solid #ddd; border-radius: 4px; width: 120px;">
                                            <button type="submit" name="update_tracking" class="btn btn-sm btn-primary" style="margin-left: 4px;">Update</button>
                                        </form>
                                    </td>
                                    <td style="padding: 12px;">
                                        <a href="messages.php?user_id=<?php echo $order['buyer_id']; ?>&order_id=<?php echo $order['order_id']; ?>"
                                           class="btn btn-sm btn-secondary">
                                            <i class="fas fa-envelope"></i> Message
                                        </a>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php else: ?>
            <div class="content-card">
                <div class="empty-state">
                    <i class="fas fa-shopping-cart"></i>
                    <p>No orders yet</p>
                    <p>Orders for your products will appear here once buyers make purchases.</p>
                    <a href="products.php" class="btn btn-primary" style="margin-top: 15px;">View My Products</a>
                </div>
            </div>
        <?php endif; ?>
    </div>
</main>

<?php include '../includes/footer.php'; ?>
