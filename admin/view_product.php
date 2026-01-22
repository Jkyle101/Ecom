<?php
require_once '../config/db_config.php';
require_once '../includes/auth_check.php';

require_role('admin');

$product_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$stmt = $conn->prepare("SELECT p.*, u.username as seller_name 
                        FROM products p
                        JOIN users u ON p.seller_id = u.id
                        WHERE p.id = ?");
$stmt->bind_param("i", $product_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header("Location: products.php");
    exit();
}

$product = $result->fetch_assoc();

// Handle approval/rejection
$success = '';
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf_token();
    
    $action = sanitize_input($_POST['action']);
    $admin_id = $_SESSION['user_id'];
    
    if ($action === 'approve') {
        $stmt = $conn->prepare("UPDATE products SET approval_status = 'approved' WHERE id = ?");
        $stmt->bind_param("i", $product_id);
        $stmt->execute();
        
        // Create notification
        require_once '../includes/notifications.php';
        createNotification($product['seller_id'], "Your product '{$product['name']}' has been approved!", 'product_approved', $product_id);
        
        $success = "Product approved successfully!";
        $product['approval_status'] = 'approved';
    } elseif ($action === 'reject') {
        $stmt = $conn->prepare("UPDATE products SET approval_status = 'rejected' WHERE id = ?");
        $stmt->bind_param("i", $product_id);
        $stmt->execute();
        
        // Create notification
        require_once '../includes/notifications.php';
        createNotification($product['seller_id'], "Your product '{$product['name']}' has been rejected.", 'product_rejected', $product_id);
        
        $success = "Product rejected.";
        $product['approval_status'] = 'rejected';
    }
    
    $stmt->close();
}
?>

<?php include '../includes/header.php'; ?>
<link rel="stylesheet" href="../assets/css/dashboard.css">

<main class="main-wrapper">
    <div class="container">
        <div class="content-card">
            <a href="products.php" class="btn btn-secondary">← Back to Products</a>
            
            <?php if (!empty($success)): ?>
                <div class="alert alert-success" style="margin-top: 20px;"><?php echo $success; ?></div>
            <?php endif; ?>
            
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
                    
                    <p><strong>Seller:</strong> <?php echo htmlspecialchars($product['seller_name']); ?></p>
                    <p><strong>Contact:</strong> <?php echo htmlspecialchars($product['contact_details']); ?></p>
                    <p><strong>Category:</strong> <?php echo htmlspecialchars($product['category']); ?></p>
                    
                    <?php if ($product['approval_status'] === 'pending'): ?>
                        <div style="margin-top: 30px; padding: 20px; background: #f9f9f9; border-radius: 10px;">
                            <h3>Approve/Reject Product</h3>
                            <form method="POST" style="display: flex; gap: 10px; margin-top: 15px;">
                                <?php echo csrf_token_field(); ?>
                                <button type="submit" name="action" value="approve" class="btn btn-primary">Approve</button>
                                <button type="submit" name="action" value="reject" class="btn btn-secondary" onclick="return confirm('Are you sure you want to reject this product?');">Reject</button>
                            </form>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</main>

<?php include '../includes/footer.php'; ?>
