<?php
require_once '../config/db_config.php';
require_once '../includes/auth_check.php';

require_role('admin');

$status_filter = isset($_GET['status']) ? sanitize_input($_GET['status']) : '';

// Build query for orders
$sql = "SELECT o.*, u.username as buyer_name 
        FROM orders o
        JOIN users u ON o.user_id = u.id";

if (!empty($status_filter)) {
    $sql .= " WHERE o.status = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $status_filter);
} else {
    $stmt = $conn->prepare($sql);
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
            <div class="breadcrumb">Home > Admin > Transactions</div>
        </div>

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
                        <th style="padding: 12px; text-align: left;">Total Amount</th>
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
                                <td style="padding: 12px;" class="product-price">$<?php echo number_format($order['total_amount'], 2); ?></td>
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
                            <td colspan="7" style="padding: 40px; text-align: center;">No orders found</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</main>

<?php include '../includes/footer.php'; ?>
