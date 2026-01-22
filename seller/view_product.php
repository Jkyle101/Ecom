<?php
require_once '../config/db_config.php';
require_once '../includes/auth_check.php';

require_login();
if ($_SESSION['role'] !== 'seller') {
    header("Location: ../buyer/dashboard.php");
    exit();
}

$product_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$seller_id = $_SESSION['user_id'];

$stmt = $conn->prepare("SELECT * FROM products WHERE id = ? AND seller_id = ?");
$stmt->bind_param("ii", $product_id, $seller_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header("Location: products.php");
    exit();
}

$product = $result->fetch_assoc();

// Check if deletion request is pending
$del_stmt = $conn->prepare("SELECT status FROM product_deletion_requests WHERE product_id = ? AND status = 'pending'");
$del_stmt->bind_param("i", $product_id);
$del_stmt->execute();
$del_request = $del_stmt->get_result()->fetch_assoc();
$del_stmt->close();
?>

<?php include '../includes/header.php'; ?>
<link rel="stylesheet" href="../assets/css/dashboard.css">

<main class="main-wrapper">
    <div class="container">
        <div class="content-card">
            <a href="products.php" class="btn btn-secondary">← Back to Products</a>
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 30px; margin-top: 20px;">
                <div>
                    <img src="../<?php echo htmlspecialchars($product['image_path']); ?>" alt="<?php echo htmlspecialchars($product['name']); ?>" 
                         style="width: 100%; border-radius: 10px; box-shadow: 0 5px 15px rgba(0,0,0,0.1);" 
                         onerror="this.src='https://via.placeholder.com/500x500?text=No+Image'">
                </div>
                
                <div>
                    <h1><?php echo htmlspecialchars($product['name']); ?></h1>
                    <p class="product-price" style="font-size: 1.5rem; margin: 15px 0;">₱<?php echo number_format($product['price'], 2); ?></p>
                    <p class="product-category">
                        Status: <strong><?php echo ucfirst($product['approval_status']); ?></strong>
                    </p>
                    
                    <h3>Description</h3>
                    <p><?php echo nl2br(htmlspecialchars($product['description'])); ?></p>
                    
                    <?php if (!empty($product['sizes'])): ?>
                        <p><strong>Sizes:</strong> <?php echo htmlspecialchars($product['sizes']); ?></p>
                    <?php endif; ?>
                    
                    <?php if (!empty($product['colors'])): ?>
                        <p><strong>Colors:</strong> <?php echo htmlspecialchars($product['colors']); ?></p>
                    <?php endif; ?>
                    
                    <p><strong>Category:</strong> <?php echo htmlspecialchars($product['category']); ?></p>
                    <p><strong>Contact:</strong> <?php echo htmlspecialchars($product['contact_details']); ?></p>
                    
                    <?php if ($del_request): ?>
                        <div class="alert alert-danger" style="margin-top: 10px;">
                            <i class="fas fa-clock"></i> Deletion request is pending admin approval.
                        </div>
                    <?php endif; ?>

                    <div style="margin-top: 20px; display: flex; gap: 10px; flex-wrap: wrap;">
                        <a href="edit_product.php?id=<?php echo $product['id']; ?>" class="btn btn-primary">Edit Product</a>
                        <a href="delete_product.php?id=<?php echo $product['id']; ?>" class="btn btn-secondary" style="<?php echo $del_request ? 'background:#ff6b6b; color:white;' : ''; ?>">
                            <i class="fas fa-trash"></i> Delete
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<?php include '../includes/footer.php'; ?>
