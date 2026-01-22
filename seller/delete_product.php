<?php
require_once '../config/db_config.php';
require_once '../includes/auth_check.php';
require_once '../includes/notifications.php';

require_login();
if ($_SESSION['role'] !== 'seller') {
    header("Location: ../buyer/dashboard.php");
    exit();
}

$product_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$seller_id = $_SESSION['user_id'];
$error = '';
$success = '';

// Handle deletion request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf_token();
    
    $product_id = (int)$_POST['product_id'];
    $reason = sanitize_input(trim($_POST['reason'] ?? ''));
    
    // Check if product belongs to user
    $stmt = $conn->prepare("SELECT id, name FROM products WHERE id = ? AND seller_id = ?");
    $stmt->bind_param("ii", $product_id, $seller_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $product = $result->fetch_assoc();
        
        // Check if deletion request already exists
        $stmt = $conn->prepare("SELECT id FROM product_deletion_requests WHERE product_id = ? AND status = 'pending'");
        $stmt->bind_param("i", $product_id);
        $stmt->execute();
        $existing_request = $stmt->get_result();
        
        if ($existing_request->num_rows > 0) {
            $error = "Deletion request for this product is already pending admin approval.";
        } else {
            // Create deletion request
            $stmt = $conn->prepare("INSERT INTO product_deletion_requests (product_id, seller_id, reason) VALUES (?, ?, ?)");
            $stmt->bind_param("iis", $product_id, $seller_id, $reason);
            $stmt->execute();
            
            // Notify admin
            $admins = $conn->query("SELECT id FROM users WHERE role = 'admin'");
            while ($admin = $admins->fetch_assoc()) {
                createNotification($admin['id'], "Product deletion request: {$product['name']}", 'deletion_request', $product_id);
            }
            
            $success = "Deletion request submitted successfully! It will be reviewed by admin.";
        }
        $stmt->close();
    } else {
        $error = "Product not found or you don't have permission to delete it.";
    }
} else {
    // GET request - show confirmation form
    if ($product_id > 0) {
        $stmt = $conn->prepare("SELECT * FROM products WHERE id = ? AND seller_id = ?");
        $stmt->bind_param("ii", $product_id, $seller_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            header("Location: products.php");
            exit();
        }
        
        $product = $result->fetch_assoc();
        
        // Check if deletion request already exists
        $stmt = $conn->prepare("SELECT * FROM product_deletion_requests WHERE product_id = ? AND status = 'pending'");
        $stmt->bind_param("i", $product_id);
        $stmt->execute();
        $existing_request = $stmt->get_result()->fetch_assoc();
    } else {
        header("Location: products.php");
        exit();
    }
}
?>

<?php include '../includes/header.php'; ?>
<link rel="stylesheet" href="../assets/css/dashboard.css">

<main class="main-wrapper">
    <div class="container">
        <div class="page-header">
            <h1>Request Product Deletion</h1>
            <div class="breadcrumb">Home > Seller > Products > Delete</div>
        </div>
        
        <div class="content-card">
            <?php if (isset($existing_request) && $existing_request): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-info-circle"></i> A deletion request for this product is already pending admin approval.
                </div>
                <a href="products.php" class="btn btn-secondary">Back to Products</a>
            <?php else: ?>
                <?php if (!empty($error)): ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>
                
                <?php if (!empty($success)): ?>
                    <div class="alert alert-success"><?php echo $success; ?></div>
                    <a href="products.php" class="btn btn-primary">Back to Products</a>
                <?php else: ?>
                    <a href="products.php" class="btn btn-secondary" style="margin-bottom: 20px;">← Back to Products</a>
                    
                    <div class="product-card" style="max-width: 500px; margin-bottom: 20px;">
                        <div class="product-image">
                            <img src="../<?php echo htmlspecialchars($product['image_path']); ?>" alt="<?php echo htmlspecialchars($product['name']); ?>" 
                                 onerror="this.src='https://via.placeholder.com/250x200?text=No+Image'">
                        </div>
                        <div class="product-info">
                            <h3><?php echo htmlspecialchars($product['name']); ?></h3>
                            <p class="product-price">₱<?php echo number_format($product['price'], 2); ?></p>
                            <p class="product-category"><?php echo htmlspecialchars($product['category']); ?></p>
                        </div>
                    </div>
                    
                    <form method="POST">
                        <?php echo csrf_token_field(); ?>
                        <input type="hidden" name="product_id" value="<?php echo $product['id']; ?>">
                        
                        <div class="form-group">
                            <label>Reason for Deletion (Optional)</label>
                            <textarea name="reason" class="form-control" rows="4" placeholder="Please provide a reason for deleting this product..."></textarea>
                        </div>
                        
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-triangle"></i> 
                            <strong>Warning:</strong> This will request deletion of the product. Admin approval is required. Once approved, the product will be permanently deleted.
                        </div>
                        
                        <div class="form-group">
                            <button type="submit" class="btn btn-primary" onclick="return confirm('Are you sure you want to request deletion of this product?');">
                                <i class="fas fa-trash"></i> Request Deletion
                            </button>
                            <a href="products.php" class="btn btn-secondary">Cancel</a>
                        </div>
                    </form>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</main>

<?php include '../includes/footer.php'; ?>
