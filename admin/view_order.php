<?php
require_once '../config/db_config.php';
require_once '../includes/auth_check.php';

require_role('admin');

$order_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Get order details with building and room info
$stmt = $conn->prepare("SELECT o.*, u.username as buyer_name, b.name as building_name, r.name as room_name
                        FROM orders o
                        JOIN users u ON o.user_id = u.id
                        LEFT JOIN buildings b ON o.building_id = b.id
                        LEFT JOIN rooms r ON o.room_id = r.id
                        WHERE o.id = ?");
$stmt->bind_param("i", $order_id);
$stmt->execute();
$order = $stmt->get_result()->fetch_assoc();

if (!$order) {
    header("Location: transactions.php");
    exit();
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
            <div class="breadcrumb">Home > Admin > Transactions > Order #<?php echo $order['id']; ?></div>
        </div>
        
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
                        <p><strong>Buyer:</strong> <?php echo htmlspecialchars($order['buyer_name']); ?></p>
                        <p><strong>Order Date:</strong> <?php echo date('M j, Y g:i A', strtotime($order['created_at'])); ?></p>
                    </div>
                    <div>
                        <h3>Payment & Status</h3>
                        <p><strong>Payment Method:</strong> <?php echo htmlspecialchars($order['payment_method']); ?></p>
                        <p><strong>Total Amount:</strong> <span class="product-price" style="font-size: 1.5rem;">₱<?php echo number_format($order['total_amount'], 2); ?></span></p>
                        <p><strong>Order Status:</strong> <span class="product-category"><?php echo ucfirst($order['status']); ?></span></p>
                        <p style="font-size: 0.9rem; color: #666; margin-top: 10px;">Status updates are managed by the product sellers</p>
                    </div>
                </div>
                
                <div style="margin-bottom: 30px;">
                    <h3>Delivery Location</h3>
                    <p><strong>Building:</strong> <?php echo htmlspecialchars($order['building_name'] ?? 'N/A'); ?></p>
                    <p><strong>Room:</strong> <?php echo htmlspecialchars($order['room_name'] ?? 'N/A'); ?></p>
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
