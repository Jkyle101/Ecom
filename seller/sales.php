<?php
require_once '../config/db_config.php';
require_once '../includes/auth_check.php';

require_login();
if ($_SESSION['role'] !== 'seller') {
    header("Location: ../buyer/dashboard.php");
    exit();
}

$seller_id = $_SESSION['user_id'];

// Get date filter
$date_filter = isset($_GET['date_filter']) ? sanitize_input($_GET['date_filter']) : 'all';

// Build date condition
$date_condition = "";
if ($date_filter === 'today') {
    $date_condition = "AND DATE(o.created_at) = CURDATE()";
} elseif ($date_filter === 'week') {
    $date_condition = "AND o.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
} elseif ($date_filter === 'month') {
    $date_condition = "AND o.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
} elseif ($date_filter === 'year') {
    $date_condition = "AND o.created_at >= DATE_SUB(NOW(), INTERVAL 1 YEAR)";
}

// Get seller's total sales statistics - simple query
$total_sql = "SELECT 
    COUNT(DISTINCT o.id) as total_orders,
    COALESCE(SUM(oi.price * oi.quantity), 0) as total_sales
    FROM orders o
    JOIN order_items oi ON o.id = oi.order_id
    WHERE oi.seller_id = $seller_id $date_condition";

$total_result = $conn->query($total_sql);
$total_stats = $total_result->fetch_assoc();

// Get completed orders
$completed_sql = "SELECT 
    COUNT(DISTINCT o.id) as completed_orders,
    COALESCE(SUM(oi.price * oi.quantity), 0) as completed_sales
    FROM orders o
    JOIN order_items oi ON o.id = oi.order_id
    WHERE oi.seller_id = $seller_id AND o.status = 'delivered' $date_condition";

$completed_result = $conn->query($completed_sql);
$completed_stats = $completed_result->fetch_assoc();

// Get pending orders
$pending_sql = "SELECT 
    COUNT(DISTINCT o.id) as pending_orders,
    COALESCE(SUM(oi.price * oi.quantity), 0) as pending_sales
    FROM orders o
    JOIN order_items oi ON o.id = oi.order_id
    WHERE oi.seller_id = $seller_id AND o.status = 'pending' $date_condition";

$pending_result = $conn->query($pending_sql);
$pending_stats = $pending_result->fetch_assoc();

// Get cancelled orders
$cancelled_sql = "SELECT COUNT(DISTINCT o.id) as cancelled_orders
    FROM orders o
    JOIN order_items oi ON o.id = oi.order_id
    WHERE oi.seller_id = $seller_id AND o.status = 'cancelled' $date_condition";

$cancelled_result = $conn->query($cancelled_sql);
$cancelled_stats = $cancelled_result->fetch_assoc();

// Merge stats
$sales_stats = array_merge($total_stats, $completed_stats, $pending_stats, $cancelled_stats);

// Get sales by product
$product_sales_sql = "SELECT p.id, p.name, p.image_path,
    COUNT(oi.id) as total_items_sold,
    COALESCE(SUM(oi.quantity), 0) as quantity_sold,
    COALESCE(SUM(oi.price * oi.quantity), 0) as total_revenue
    FROM products p
    LEFT JOIN order_items oi ON p.id = oi.product_id
    LEFT JOIN orders o ON oi.order_id = o.id AND o.status != 'cancelled'
    WHERE p.seller_id = $seller_id
    GROUP BY p.id
    ORDER BY total_revenue DESC";

$product_sales = $conn->query($product_sales_sql);

// Get order history
$order_history_sql = "SELECT o.id as order_id, o.created_at, o.status, o.payment_method,
    u.username as buyer_name,
    SUM(oi.price * oi.quantity) as seller_total,
    COUNT(oi.id) as item_count
    FROM orders o
    JOIN order_items oi ON o.id = oi.order_id
    JOIN users u ON o.user_id = u.id
    WHERE oi.seller_id = $seller_id $date_condition
    GROUP BY o.id
    ORDER BY o.created_at DESC";

$order_history = $conn->query($order_history_sql);
?>

<?php include '../includes/header.php'; ?>
<link rel="stylesheet" href="../assets/css/dashboard.css">

<main class="main-wrapper">
    <div class="container">
        <div class="page-header">
            <h1>My Sales</h1>
            <div class="breadcrumb">Home > Seller > Sales</div>
        </div>

        <!-- Date Filter -->
        <div class="content-card">
            <form method="GET" style="display: flex; gap: 10px; align-items: center;">
                <label>Filter by:</label>
                <select name="date_filter" class="form-control" style="width: 200px;">
                    <option value="all" <?php echo $date_filter === 'all' ? 'selected' : ''; ?>>All Time</option>
                    <option value="today" <?php echo $date_filter === 'today' ? 'selected' : ''; ?>>Today</option>
                    <option value="week" <?php echo $date_filter === 'week' ? 'selected' : ''; ?>>This Week</option>
                    <option value="month" <?php echo $date_filter === 'month' ? 'selected' : ''; ?>>This Month</option>
                    <option value="year" <?php echo $date_filter === 'year' ? 'selected' : ''; ?>>This Year</option>
                </select>
                <button type="submit" class="btn btn-primary">Apply</button>
            </form>
        </div>

        <!-- Sales Statistics -->
        <div class="stats-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 30px;">
            <div class="content-card" style="text-align: center;">
                <h3 style="color: #666; font-size: 14px; margin-bottom: 10px;">Total Sales</h3>
                <p style="font-size: 28px; font-weight: bold; color: #2c3e50;">₱<?php echo number_format($sales_stats['total_sales'], 2); ?></p>
            </div>
            <div class="content-card" style="text-align: center;">
                <h3 style="color: #666; font-size: 14px; margin-bottom: 10px;">Completed Sales</h3>
                <p style="font-size: 28px; font-weight: bold; color: #27ae60;">₱<?php echo number_format($sales_stats['completed_sales'], 2); ?></p>
            </div>
            <div class="content-card" style="text-align: center;">
                <h3 style="color: #666; font-size: 14px; margin-bottom: 10px;">Pending Sales</h3>
                <p style="font-size: 28px; font-weight: bold; color: #f39c12;">₱<?php echo number_format($sales_stats['pending_sales'], 2); ?></p>
            </div>
            <div class="content-card" style="text-align: center;">
                <h3 style="color: #666; font-size: 14px; margin-bottom: 10px;">Total Orders</h3>
                <p style="font-size: 28px; font-weight: bold; color: #2c3e50;"><?php echo number_format($sales_stats['total_orders']); ?></p>
            </div>
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 30px;">
            <div class="content-card">
                <h3 style="margin-bottom: 15px;">Completed Orders</h3>
                <p style="font-size: 24px; font-weight: bold; color: #27ae60;"><?php echo number_format($sales_stats['completed_orders']); ?></p>
            </div>
            <div class="content-card">
                <h3 style="margin-bottom: 15px;">Cancelled Orders</h3>
                <p style="font-size: 24px; font-weight: bold; color: #e74c3c;"><?php echo number_format($sales_stats['cancelled_orders']); ?></p>
            </div>
        </div>

        <!-- Sales by Product -->
        <div class="content-card">
            <h2 style="margin-bottom: 20px;">Sales by Product</h2>
            <div class="table-responsive">
                <table style="width: 100%; border-collapse: collapse;">
                    <thead>
                        <tr style="border-bottom: 2px solid #ddd;">
                            <th style="padding: 12px; text-align: left;">Product</th>
                            <th style="padding: 12px; text-align: right;">Items Sold</th>
                            <th style="padding: 12px; text-align: right;">Quantity</th>
                            <th style="padding: 12px; text-align: right;">Revenue</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($product_sales && $product_sales->num_rows > 0): ?>
                            <?php while ($product = $product_sales->fetch_assoc()): ?>
                                <tr style="border-bottom: 1px solid #eee;">
                                    <td style="padding: 12px;">
                                        <div style="display: flex; align-items: center; gap: 10px;">
                                            <?php if ($product['image_path']): ?>
                                                <img src="../<?php echo htmlspecialchars($product['image_path']); ?>" alt="" style="width: 40px; height: 40px; object-fit: cover; border-radius: 4px;">
                                            <?php endif; ?>
                                            <strong><?php echo htmlspecialchars($product['name']); ?></strong>
                                        </div>
                                    </td>
                                    <td style="padding: 12px; text-align: right;"><?php echo number_format($product['total_items_sold']); ?></td>
                                    <td style="padding: 12px; text-align: right;"><?php echo number_format($product['quantity_sold']); ?></td>
                                    <td style="padding: 12px; text-align: right;" class="product-price">₱<?php echo number_format($product['total_revenue'], 2); ?></td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="4" style="padding: 40px; text-align: center;">No product sales yet</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Order History -->
        <div class="content-card" style="margin-top: 30px;">
            <h2 style="margin-bottom: 20px;">Sales History</h2>
            <div class="table-responsive">
                <table style="width: 100%; border-collapse: collapse;">
                    <thead>
                        <tr style="border-bottom: 2px solid #ddd;">
                            <th style="padding: 12px; text-align: left;">Order ID</th>
                            <th style="padding: 12px; text-align: left;">Date</th>
                            <th style="padding: 12px; text-align: left;">Buyer</th>
                            <th style="padding: 12px; text-align: right;">Items</th>
                            <th style="padding: 12px; text-align: right;">Earnings</th>
                            <th style="padding: 12px; text-align: left;">Payment</th>
                            <th style="padding: 12px; text-align: left;">Status</th>
                            <th style="padding: 12px; text-align: left;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($order_history && $order_history->num_rows > 0): ?>
                            <?php while ($order = $order_history->fetch_assoc()): ?>
                                <tr style="border-bottom: 1px solid #eee;">
                                    <td style="padding: 12px;">#<?php echo $order['order_id']; ?></td>
                                    <td style="padding: 12px;"><?php echo date('M j, Y g:i A', strtotime($order['created_at'])); ?></td>
                                    <td style="padding: 12px;"><?php echo htmlspecialchars($order['buyer_name']); ?></td>
                                    <td style="padding: 12px; text-align: right;"><?php echo $order['item_count']; ?> item(s)</td>
                                    <td style="padding: 12px; text-align: right;" class="product-price">₱<?php echo number_format($order['seller_total'], 2); ?></td>
                                    <td style="padding: 12px;"><?php echo htmlspecialchars($order['payment_method']); ?></td>
                                    <td style="padding: 12px;">
                                        <span class="product-category"><?php echo ucfirst($order['status']); ?></span>
                                    </td>
                                    <td style="padding: 12px;">
                                        <a href="view_order.php?id=<?php echo $order['order_id']; ?>" class="btn btn-sm btn-primary">View</a>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8" style="padding: 40px; text-align: center;">No sales history yet</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</main>

<?php include '../includes/footer.php'; ?>
