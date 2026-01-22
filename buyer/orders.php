<?php
require_once '../config/db_config.php';
require_once '../includes/auth_check.php';

require_login();

$user_id = $_SESSION['user_id'];

// Check for success messages
$success = '';
if (isset($_GET['deleted']) && $_GET['deleted'] == '1') {
    $success = "Order removed from history successfully.";
}

// Get all orders
$orders = $conn->query("SELECT * FROM orders WHERE user_id = $user_id ORDER BY created_at DESC");
?>

<?php include '../includes/header.php'; ?>
<link rel="stylesheet" href="../assets/css/dashboard.css">

<main class="main-wrapper">
    <div class="container">
        <div class="page-header">
            <h1>My Orders</h1>
            <div class="breadcrumb">Home > Orders</div>
        </div>

        <?php if (!empty($success)): ?>
            <div class="alert alert-success" style="margin-bottom: 20px;"><?php echo $success; ?></div>
        <?php endif; ?>

        <?php if ($orders && $orders->num_rows > 0): ?>
            <div class="content-card">
                <table style="width: 100%; border-collapse: collapse;">
                    <thead>
                        <tr style="border-bottom: 2px solid #ddd;">
                            <th style="padding: 12px; text-align: left;">Order ID</th>
                            <th style="padding: 12px; text-align: left;">Date</th>
                            <th style="padding: 12px; text-align: left;">Total Amount</th>
                            <th style="padding: 12px; text-align: left;">Payment Method</th>
                            <th style="padding: 12px; text-align: left;">Status</th>
                            <th style="padding: 12px; text-align: left;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($order = $orders->fetch_assoc()): ?>
                            <tr style="border-bottom: 1px solid #eee;">
                                <td style="padding: 12px;">#<?php echo $order['id']; ?></td>
                                <td style="padding: 12px;"><?php echo date('M j, Y g:i A', strtotime($order['created_at'])); ?></td>
                                <td style="padding: 12px;" class="product-price">₱<?php echo number_format($order['total_amount'], 2); ?></td>
                                <td style="padding: 12px;"><?php echo htmlspecialchars($order['payment_method']); ?></td>
                                <td style="padding: 12px;">
                                    <span class="product-category"><?php echo ucfirst($order['status']); ?></span>
                                </td>
                                <td style="padding: 12px;">
                                    <a href="view_order.php?id=<?php echo $order['id']; ?>" class="btn btn-sm btn-primary">View Details</a>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="content-card">
                <div class="empty-state">
                    <i class="fas fa-receipt"></i>
                    <p>No orders yet</p>
                    <a href="products.php" class="btn btn-primary" style="margin-top: 15px;">Start Shopping</a>
                </div>
            </div>
        <?php endif; ?>
    </div>
</main>

<?php include '../includes/footer.php'; ?>
