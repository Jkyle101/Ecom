<?php
require_once '../config/db_config.php';
require_once '../includes/auth_check.php';

require_login();
if ($_SESSION['role'] !== 'seller') {
    header("Location: ../buyer/dashboard.php");
    exit();
}

$seller_id = $_SESSION['user_id'];
$status_filter = isset($_GET['status']) ? sanitize_input($_GET['status']) : '';

// Check for success messages
$success = '';
if (isset($_GET['deleted']) && $_GET['deleted'] == '1') {
    $success = "Order deleted successfully!";
}

// Build query for orders containing seller's products
$sql = "SELECT DISTINCT o.*, u.username as buyer_name,
               SUM(oi.price * oi.quantity) as seller_total,
               COUNT(oi.id) as item_count
        FROM orders o
        JOIN order_items oi ON o.id = oi.order_id
        JOIN users u ON o.user_id = u.id
        WHERE oi.seller_id = ?";

if (!empty($status_filter)) {
    $sql .= " AND o.status = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("is", $seller_id, $status_filter);
} else {
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $seller_id);
}

$sql .= " GROUP BY o.id ORDER BY o.created_at DESC";
$stmt = $conn->prepare($sql);

if (!empty($status_filter)) {
    $stmt->bind_param("is", $seller_id, $status_filter);
} else {
    $stmt->bind_param("i", $seller_id);
}

$stmt->execute();
$orders = $stmt->get_result();
?>

<?php include '../includes/header.php'; ?>
<link rel="stylesheet" href="../assets/css/dashboard.css">

<main class="main-wrapper">
    <div class="container">
        <div class="page-header">
            <h1>Monitor Transactions</h1>
            <div class="breadcrumb">Home > Seller > Transactions</div>
        </div>

        <?php if (!empty($success)): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>

        <!-- Filter -->
        <div class="content-card">
            <form method="GET" style="display: flex; gap: 10px;">
                <select name="status" class="form-control" style="width: 200px;">
                    <option value="">All Status</option>
                    <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                    <option value="processing" <?php echo $status_filter === 'processing' ? 'selected' : ''; ?>>Processing</option>
                    <option value="shipped" <?php echo $status_filter === 'shipped' ? 'selected' : ''; ?>>Shipped</option>
                    <option value="delivered" <?php echo $status_filter === 'delivered' ? 'selected' : ''; ?>>Delivered</option>
                    <option value="cancelled" <?php echo $status_filter === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                </select>
                <button type="submit" class="btn btn-primary">Filter</button>
                <a href="transactions.php" class="btn btn-secondary">Clear</a>
            </form>
        </div>

        <div class="content-card">
            <table style="width: 100%; border-collapse: collapse;">
                <thead>
                    <tr style="border-bottom: 2px solid #ddd;">
                        <th style="padding: 12px; text-align: left;">Order ID</th>
                        <th style="padding: 12px; text-align: left;">Buyer</th>
                        <th style="padding: 12px; text-align: left;">Items</th>
                        <th style="padding: 12px; text-align: left;">Your Earnings</th>
                        <th style="padding: 12px; text-align: left;">Payment Method</th>
                        <th style="padding: 12px; text-align: left;">Status</th>
                        <th style="padding: 12px; text-align: left;">Date</th>
                        <th style="padding: 12px; text-align: left;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($orders && $orders->num_rows > 0): ?>
                        <?php while ($order = $orders->fetch_assoc()): ?>
                            <tr style="border-bottom: 1px solid #eee;">
                                <td style="padding: 12px;">#<?php echo $order['id']; ?></td>
                                <td style="padding: 12px;"><?php echo htmlspecialchars($order['buyer_name']); ?></td>
                                <td style="padding: 12px;"><?php echo $order['item_count']; ?> item(s)</td>
                                <td style="padding: 12px;" class="product-price">₱<?php echo number_format($order['seller_total'], 2); ?></td>
                                <td style="padding: 12px;"><?php echo htmlspecialchars($order['payment_method']); ?></td>
                                <td style="padding: 12px;">
                                    <span class="product-category"><?php echo ucfirst($order['status']); ?></span>
                                </td>
                                <td style="padding: 12px;"><?php echo date('M j, Y g:i A', strtotime($order['created_at'])); ?></td>
                                <td style="padding: 12px;">
                                    <a href="view_order.php?id=<?php echo $order['id']; ?>" class="btn btn-sm btn-primary">View Details</a>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="8" style="padding: 40px; text-align: center;">No transactions found</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</main>

<?php include '../includes/footer.php'; ?>
