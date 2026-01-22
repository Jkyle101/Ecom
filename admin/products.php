<?php
require_once '../config/db_config.php';
require_once '../includes/auth_check.php';

require_role('admin');

$status_filter = isset($_GET['status']) ? sanitize_input($_GET['status']) : '';

$sql = "SELECT p.*, u.username as seller_name 
        FROM products p
        JOIN users u ON p.seller_id = u.id";

if (!empty($status_filter)) {
    $sql .= " WHERE p.approval_status = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $status_filter);
} else {
    $stmt = $conn->prepare($sql);
}

$stmt->execute();
$products = $stmt->get_result();
?>

<?php include '../includes/header.php'; ?>
<link rel="stylesheet" href="../assets/css/dashboard.css">

<main class="main-wrapper">
    <div class="container">
        <div class="page-header">
            <h1>Manage Products</h1>
            <div class="breadcrumb">Home > Admin > Products</div>
        </div>

        <!-- Filter -->
        <div class="content-card">
            <form method="GET" style="display: flex; gap: 10px;">
                <select name="status" class="form-control" style="width: 200px;">
                    <option value="">All Status</option>
                    <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                    <option value="approved" <?php echo $status_filter === 'approved' ? 'selected' : ''; ?>>Approved</option>
                    <option value="rejected" <?php echo $status_filter === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                </select>
                <button type="submit" class="btn btn-primary">Filter</button>
                <a href="products.php" class="btn btn-secondary">Clear</a>
            </form>
        </div>

        <!-- Products Grid -->
        <div class="product-grid">
            <?php if ($products && $products->num_rows > 0): ?>
                <?php while ($product = $products->fetch_assoc()): ?>
                    <div class="product-card">
                        <div class="product-image">
                            <img src="../<?php echo htmlspecialchars($product['image_path']); ?>" alt="<?php echo htmlspecialchars($product['name']); ?>" onerror="this.src='https://via.placeholder.com/250x200?text=No+Image'">
                        </div>
                        <div class="product-info">
                            <h3><?php echo htmlspecialchars($product['name']); ?></h3>
                            <p class="product-price">$<?php echo number_format($product['price'], 2); ?></p>
                            <p class="product-category">
                                Status: <strong><?php echo ucfirst($product['approval_status']); ?></strong>
                            </p>
                            <p style="font-size: 0.9rem; color: #666;">By <?php echo htmlspecialchars($product['seller_name']); ?></p>
                            <a href="view_product.php?id=<?php echo $product['id']; ?>" class="btn btn-primary" style="margin-top: 10px;">Review</a>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-box"></i>
                    <p>No products found</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</main>

<?php include '../includes/footer.php'; ?>

