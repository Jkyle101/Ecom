<?php
require_once '../config/db_config.php';
require_once '../includes/auth_check.php';

require_role('admin');

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

// Get all sellers with their sales statistics
$sellers_sql = "SELECT u.id, u.username, u.email,
                COUNT(DISTINCT o.id) as total_orders,
                COALESCE(SUM(oi.price * oi.quantity), 0) as total_sales,
                COUNT(DISTINCT CASE WHEN o.status = 'delivered' THEN o.id END) as completed_orders,
                COALESCE(SUM(CASE WHEN o.status = 'delivered' THEN oi.price * oi.quantity ELSE 0 END), 0) as completed_sales
                FROM users u
                LEFT JOIN order_items oi ON u.id = oi.seller_id
                LEFT JOIN orders o ON oi.order_id = o.id $date_condition
                WHERE u.role = 'seller'
                GROUP BY u.id
                ORDER BY total_sales DESC";

$sellers_result = $conn->query($sellers_sql);

// Get overall statistics
$overall_sql = "SELECT 
                COUNT(DISTINCT o.id) as total_orders,
                COALESCE(SUM(oi.price * oi.quantity), 0) as total_sales,
                COUNT(DISTINCT u.id) as total_sellers
                FROM orders o
                JOIN order_items oi ON o.id = oi.order_id
                JOIN users u ON oi.seller_id = u.id
                WHERE u.role = 'seller' $date_condition";

$overall_result = $conn->query($overall_sql);
$overall = $overall_result->fetch_assoc();

// Get recent sales by seller
$recent_sales_sql = "SELECT o.id as order_id, o.created_at, o.status,
                     u.username as seller_name, u.id as seller_id,
                     oi.price * oi.quantity as sale_amount,
                     p.name as product_name
                     FROM orders o
                     JOIN order_items oi ON o.id = oi.order_id
                     JOIN users u ON oi.seller_id = u.id
                     JOIN products p ON oi.product_id = p.id
                     WHERE u.role = 'seller' $date_condition
                     ORDER BY o.created_at DESC
                     LIMIT 20";

$recent_sales = $conn->query($recent_sales_sql);
?>

<?php include '../includes/header.php'; ?>
<link rel="stylesheet" href="../assets/css/dashboard.css">

<main class="main-wrapper">
    <div class="container">
        <div class="page-header">
            <h1>Sales Tracking</h1>
            <div class="breadcrumb">Home > Admin > Sales</div>
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

        <!-- Overall Statistics -->
        <div class="stats-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin-bottom: 30px;">
            <div class="content-card" style="text-align: center;">
                <h3 style="color: #666; font-size: 14px; margin-bottom: 10px;">Total Sales</h3>
                <p style="font-size: 32px; font-weight: bold; color: #2c3e50;">₱<?php echo number_format($overall['total_sales'], 2); ?></p>
            </div>
            <div class="content-card" style="text-align: center;">
                <h3 style="color: #666; font-size: 14px; margin-bottom: 10px;">Total Orders</h3>
                <p style="font-size: 32px; font-weight: bold; color: #2c3e50;"><?php echo number_format($overall['total_orders']); ?></p>
            </div>
            <div class="content-card" style="text-align: center;">
                <h3 style="color: #666; font-size: 14px; margin-bottom: 10px;">Total Sellers</h3>
                <p style="font-size: 32px; font-weight: bold; color: #2c3e50;"><?php echo number_format($overall['total_sellers']); ?></p>
            </div>
        </div>

        <!-- Seller Sales Table -->
        <div class="content-card">
            <h2 style="margin-bottom: 20px;">Seller Sales Summary</h2>
            <div class="table-responsive">
                <table style="width: 100%; border-collapse: collapse;">
                    <thead>
                        <tr style="border-bottom: 2px solid #ddd;">
                            <th style="padding: 12px; text-align: left;">Seller</th>
                            <th style="padding: 12px; text-align: left;">Email</th>
                            <th style="padding: 12px; text-align: right;">Orders</th>
                            <th style="padding: 12px; text-align: right;">Completed</th>
                            <th style="padding: 12px; text-align: right;">Total Sales</th>
                            <th style="padding: 12px; text-align: right;">Completed Sales</th>
                            <th style="padding: 12px; text-align: center;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($sellers_result && $sellers_result->num_rows > 0): ?>
                            <?php while ($seller = $sellers_result->fetch_assoc()): ?>
                                <tr style="border-bottom: 1px solid #eee;">
                                    <td style="padding: 12px;"><strong><?php echo htmlspecialchars($seller['username']); ?></strong></td>
                                    <td style="padding: 12px;"><?php echo htmlspecialchars($seller['email']); ?></td>
                                    <td style="padding: 12px; text-align: right;"><?php echo number_format($seller['total_orders']); ?></td>
                                    <td style="padding: 12px; text-align: right;"><?php echo number_format($seller['completed_orders']); ?></td>
                                    <td style="padding: 12px; text-align: right;" class="product-price">₱<?php echo number_format($seller['total_sales'], 2); ?></td>
                                    <td style="padding: 12px; text-align: right;" class="product-price">₱<?php echo number_format($seller['completed_sales'], 2); ?></td>
                                    <td style="padding: 12px; text-align: center;">
                                        <a href="seller_sales.php?seller_id=<?php echo $seller['id']; ?>" class="btn btn-sm btn-primary">View Details</a>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" style="padding: 40px; text-align: center;">No sellers found</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Recent Sales -->
        <div class="content-card" style="margin-top: 30px;">
            <h2 style="margin-bottom: 20px;">Recent Sales</h2>
            <div class="table-responsive">
                <table style="width: 100%; border-collapse: collapse;">
                    <thead>
                        <tr style="border-bottom: 2px solid #ddd;">
                            <th style="padding: 12px; text-align: left;">Date</th>
                            <th style="padding: 12px; text-align: left;">Order ID</th>
                            <th style="padding: 12px; text-align: left;">Seller</th>
                            <th style="padding: 12px; text-align: left;">Product</th>
                            <th style="padding: 12px; text-align: right;">Amount</th>
                            <th style="padding: 12px; text-align: left;">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($recent_sales && $recent_sales->num_rows > 0): ?>
                            <?php while ($sale = $recent_sales->fetch_assoc()): ?>
                                <tr style="border-bottom: 1px solid #eee;">
                                    <td style="padding: 12px;"><?php echo date('M j, Y g:i A', strtotime($sale['created_at'])); ?></td>
                                    <td style="padding: 12px;">#<?php echo $sale['order_id']; ?></td>
                                    <td style="padding: 12px;"><?php echo htmlspecialchars($sale['seller_name']); ?></td>
                                    <td style="padding: 12px;"><?php echo htmlspecialchars($sale['product_name']); ?></td>
                                    <td style="padding: 12px; text-align: right;" class="product-price">₱<?php echo number_format($sale['sale_amount'], 2); ?></td>
                                    <td style="padding: 12px;">
                                        <span class="product-category"><?php echo ucfirst($sale['status']); ?></span>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" style="padding: 40px; text-align: center;">No recent sales</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</main>

<?php include '../includes/footer.php'; ?>
