<?php
require_once '../config/db_config.php';
require_once '../includes/auth_check.php';

require_login();
if ($_SESSION['role'] !== 'seller') {
    header("Location: ../buyer/dashboard.php");
    exit();
}

$seller_id = $_SESSION['user_id'];

$sql = "SELECT * FROM products WHERE seller_id = ? ORDER BY created_at DESC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $seller_id);
$stmt->execute();
$products = $stmt->get_result();
?>

<?php include '../includes/header.php'; ?>
<link rel="stylesheet" href="../assets/css/dashboard.css">

<main class="main-wrapper">
    <div class="container">
        <div class="page-header">
            <h1>My Products</h1>
            <div class="breadcrumb">Home > Seller > Products</div>
        </div>
        
        <div style="margin-bottom: 20px;">
            <a href="add_product.php" class="btn btn-primary">Add New Product</a>
        </div>
        
        <div class="product-grid">
            <?php if ($products && $products->num_rows > 0): ?>
                <?php while ($product = $products->fetch_assoc()): ?>
                    <div class="product-card">
                        <div class="product-image">
                            <img src="../<?php echo htmlspecialchars($product['image_path']); ?>" alt="<?php echo htmlspecialchars($product['name']); ?>" onerror="this.src='https://via.placeholder.com/250x200?text=No+Image'">
                        </div>
                        <div class="product-info">
                            <h3><?php echo htmlspecialchars($product['name']); ?></h3>
                            <p class="product-price">₱<?php echo number_format($product['price'], 2); ?></p>
                            <p class="product-category">
                                Status: <strong><?php echo ucfirst($product['approval_status']); ?></strong>
                            </p>
                            <?php
                            // Check if deletion request exists
                            $del_stmt = $conn->prepare("SELECT status FROM product_deletion_requests WHERE product_id = ? AND status = 'pending'");
                            $del_stmt->bind_param("i", $product['id']);
                            $del_stmt->execute();
                            $del_request = $del_stmt->get_result()->fetch_assoc();
                            $del_stmt->close();
                            ?>
                            <?php if ($del_request): ?>
                                <p style="color: #ff6b6b; font-size: 0.9rem; margin-top: 5px;">
                                    <i class="fas fa-clock"></i> Deletion Request Pending
                                </p>
                            <?php endif; ?>
                            <div style="margin-top: 10px; display: flex; gap: 10px; flex-wrap: wrap;">
                                <a href="view_product.php?id=<?php echo $product['id']; ?>" class="btn btn-primary">View</a>
                                <a href="edit_product.php?id=<?php echo $product['id']; ?>" class="btn btn-secondary">Edit</a>
                                <a href="delete_product.php?id=<?php echo $product['id']; ?>" class="btn btn-secondary" 
                                   style="<?php echo $del_request ? 'background: #ff6b6b; color: white;' : ''; ?>">
                                    <i class="fas fa-trash"></i> Delete
                                </a>
                            </div>
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
</main>

<?php include '../includes/footer.php'; ?>
