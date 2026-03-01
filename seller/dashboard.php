<?php
require_once '../config/db_config.php';
require_once '../includes/auth_check.php';

require_login();
if ($_SESSION['role'] !== 'seller') {
    header("Location: ../buyer/dashboard.php");
    exit();
}

$seller_id = $_SESSION['user_id'];

// Get product count
$sql = "SELECT COUNT(*) as product_count FROM products WHERE seller_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $seller_id);
$stmt->execute();
$product_count = $stmt->get_result()->fetch_assoc()['product_count'];

// Get unread messages count
$sql = "SELECT COUNT(*) as unread_count FROM messages WHERE receiver_id = ? AND is_read = 0";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $seller_id);
$stmt->execute();
$unread_count = $stmt->get_result()->fetch_assoc()['unread_count'];

// Get recent products
$sql = "SELECT * FROM products WHERE seller_id = ? ORDER BY created_at DESC LIMIT 5";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $seller_id);
$stmt->execute();
$recent_products = $stmt->get_result();

// Get product status counts
$sql = "SELECT 
            SUM(CASE WHEN approval_status = 'approved' THEN 1 ELSE 0 END) as approved_count,
            SUM(CASE WHEN approval_status = 'pending' THEN 1 ELSE 0 END) as pending_count,
            SUM(CASE WHEN approval_status = 'rejected' THEN 1 ELSE 0 END) as rejected_count
        FROM products 
        WHERE seller_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $seller_id);
$stmt->execute();
$status_counts = $stmt->get_result()->fetch_assoc();

// Get notifications
$sql = "SELECT COUNT(*) as unread_notifications FROM notifications WHERE user_id = ? AND is_read = 0";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $seller_id);
$stmt->execute();
$unread_notifications = $stmt->get_result()->fetch_assoc()['unread_notifications'];
?>

<?php include '../includes/header.php'; ?>
<link rel="stylesheet" href="../assets/css/dashboard.css">

<main class="main-wrapper">
    <div class="container">
        <div class="page-header">
            <h1>Seller Dashboard</h1>
            <div class="breadcrumb">Home > Seller Dashboard</div>
        </div>

        <!-- Stats Cards -->
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 30px;">
            <div class="dashboard-card">
                <strong>Total Products</strong>
                <div style="font-size: 2rem; margin-top: 10px; color: var(--primary-blue);"><?php echo $product_count; ?></div>
            </div>
            
            <div class="dashboard-card">
                <strong>Approved</strong>
                <div style="font-size: 2rem; margin-top: 10px; color: var(--primary-green);"><?php echo $status_counts['approved_count'] ?? 0; ?></div>
            </div>
            
            <div class="dashboard-card">
                <strong>Pending</strong>
                <div style="font-size: 2rem; margin-top: 10px; color: var(--primary-yellow);"><?php echo $status_counts['pending_count'] ?? 0; ?></div>
            </div>
            
            <div class="dashboard-card">
                <strong>Rejected</strong>
                <div style="font-size: 2rem; margin-top: 10px; color: var(--primary-orange);"><?php echo $status_counts['rejected_count'] ?? 0; ?></div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div style="display: flex; gap: 15px; margin-bottom: 30px; flex-wrap: wrap;">
            <a href="add_product.php" class="btn btn-primary">
                <i class="fas fa-plus"></i> Add Product
            </a>
            <a href="products.php" class="btn btn-secondary">
                <i class="fas fa-list"></i> View All Products
            </a>
            <a href="sales.php" class="btn btn-secondary">
                <i class="fas fa-chart-line"></i> My Sales
            </a>
            <a href="transactions.php" class="btn btn-secondary">
                <i class="fas fa-receipt"></i> Monitor Transactions
            </a>
            <a href="orders.php" class="btn btn-secondary">
                <i class="fas fa-shopping-cart"></i> My Orders
            </a>    
            <a href="messages.php" class="btn btn-secondary">
                <i class="fas fa-envelope"></i> Messages
                <?php if ($unread_count > 0): ?>
                    <span class="message-badge"><?php echo $unread_count; ?></span>
                <?php endif; ?>
            </a>
            <a href="notifications.php" class="btn btn-secondary">
                <i class="fas fa-bell"></i> Notifications
                <?php if ($unread_notifications > 0): ?>
                    <span class="notification-badge"><?php echo $unread_notifications; ?></span>
                <?php endif; ?>
            </a>
            <a href="account.php" class="btn btn-secondary">
                <i class="fas fa-user-cog"></i> Account Settings
            </a>
        </div>

        <!-- Recent Products -->
        <div class="content-card">
            <div class="card-header">
                <h2>Recent Products</h2>
                <a href="products.php" class="btn btn-sm btn-outline">View All</a>
            </div>
            
            <div class="product-grid">
                <?php if ($recent_products && $recent_products->num_rows > 0): ?>
                    <?php while ($product = $recent_products->fetch_assoc()): ?>
                        <div class="product-card">
                            <div class="product-image">
                                <img src="../<?php echo htmlspecialchars($product['image_path']); ?>" alt="<?php echo htmlspecialchars($product['name']); ?>" onerror="this.src='data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMjUwIiBoZWlnaHQ9IjIwMCIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj48cmVjdCB3aWR0aD0iMjUwIiBoZWlnaHQ9IjIwMCIgZmlsbD0iI2VlZWVlZSIvPjx0ZXh0IHg9IjEyNSIgeT0iMTAwIiBmb250LWZhbWlseT0iQXJpYWwiIGZvbnQtc2l6ZT0iMTQiIGZpbGw9IiM5OTk5OTkiIHRleHQtYW5jaG9yPSJtaWRkbGUiIGR5PSIuM2VtIj5ObyBJbWFnZTwvdGV4dD48L3N2Zz4='">
                            </div>
                            <div class="product-info">
                                <h3><?php echo htmlspecialchars($product['name']); ?></h3>
                                <p class="product-price">₱<?php echo number_format($product['price'], 2); ?></p>
                                <p class="product-category">
                                    Status: <?php echo ucfirst($product['approval_status']); ?>
                                </p>
                                <a href="view_product.php?id=<?php echo $product['id']; ?>" class="btn btn-primary" style="margin-top: 10px;">View Details</a>
                            </div>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-box"></i>
                        <p>No products yet. Add your first product!</p>
                        <a href="add_product.php" class="btn btn-primary" style="margin-top: 15px;">Add Product</a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</main>

<?php include '../includes/footer.php'; ?>
