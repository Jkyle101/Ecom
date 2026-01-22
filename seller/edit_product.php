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

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf_token();
    
    $name = sanitize_input(trim($_POST['name']));
    $description = sanitize_input(trim($_POST['description']));
    $price = sanitize_input(trim($_POST['price']));
    $category = sanitize_input(trim($_POST['category']));
    $sizes = sanitize_input(trim($_POST['sizes'] ?? ''));
    $colors = sanitize_input(trim($_POST['colors'] ?? ''));
    $contact_details = sanitize_input(trim($_POST['contact_details']));
    
    if (empty($name) || empty($description) || empty($price) || empty($category) || empty($contact_details)) {
        $error = "Please fill all required fields";
    } elseif (!is_numeric($price) || $price <= 0) {
        $error = "Please enter a valid price";
    } else {
        $image_path = $product['image_path'];
        
        // Handle file upload if new image provided
        if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = '../uploads/products/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            $file_extension = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
            $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif'];
            
            if (in_array($file_extension, $allowed_extensions)) {
                $file_name = time() . '_' . uniqid() . '.' . $file_extension;
                $file_path = $upload_dir . $file_name;
                
                if (move_uploaded_file($_FILES['image']['tmp_name'], $file_path)) {
                    // Delete old image if exists
                    if (file_exists('../' . $product['image_path'])) {
                        unlink('../' . $product['image_path']);
                    }
                    $image_path = 'uploads/products/' . $file_name;
                }
            }
        }
        
        $sql = "UPDATE products SET name = ?, description = ?, price = ?, category = ?, sizes = ?, colors = ?, contact_details = ?, image_path = ? WHERE id = ? AND seller_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssdsssssii", $name, $description, $price, $category, $sizes, $colors, $contact_details, $image_path, $product_id, $seller_id);
        
        if ($stmt->execute()) {
            $success = "Product updated successfully!";
            // Refresh product data
            $stmt = $conn->prepare("SELECT * FROM products WHERE id = ? AND seller_id = ?");
            $stmt->bind_param("ii", $product_id, $seller_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $product = $result->fetch_assoc();
        } else {
            $error = "Failed to update product";
        }
        $stmt->close();
    }
}

// Get categories
$categories = $conn->query("SELECT * FROM categories");
?>

<?php include '../includes/header.php'; ?>
<link rel="stylesheet" href="../assets/css/dashboard.css">

<main class="main-wrapper">
    <div class="container">
        <div class="page-header">
            <h1>Edit Product</h1>
            <div class="breadcrumb">Home > Seller > Edit Product</div>
        </div>
        
        <div class="form-container" style="max-width: 800px;">
            <?php if (!empty($success)): ?>
                <div class="alert alert-success"><?php echo $success; ?></div>
            <?php endif; ?>
            
            <?php if (!empty($error)): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <form method="POST" enctype="multipart/form-data">
                <?php echo csrf_token_field(); ?>
                
                <div class="form-group">
                    <label>Product Name *</label>
                    <input type="text" name="name" class="form-control" required value="<?php echo htmlspecialchars($product['name']); ?>">
                </div>
                
                <div class="form-group">
                    <label>Description *</label>
                    <textarea name="description" class="form-control" rows="5" required><?php echo htmlspecialchars($product['description']); ?></textarea>
                </div>
                
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                    <div class="form-group">
                        <label>Price *</label>
                        <input type="number" name="price" class="form-control" step="0.01" min="0" required value="<?php echo htmlspecialchars($product['price']); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label>Category *</label>
                        <select name="category" class="form-control" required>
                            <option value="">Select Category</option>
                            <?php 
                            $categories->data_seek(0);
                            while ($cat = $categories->fetch_assoc()): ?>
                                <option value="<?php echo htmlspecialchars($cat['name']); ?>" <?php echo $product['category'] === $cat['name'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($cat['name']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                </div>
                
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                    <div class="form-group">
                        <label>Sizes (Optional)</label>
                        <input type="text" name="sizes" class="form-control" placeholder="e.g., S, M, L, XL" value="<?php echo htmlspecialchars($product['sizes'] ?? ''); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label>Colors (Optional)</label>
                        <input type="text" name="colors" class="form-control" placeholder="e.g., Red, Blue, Green" value="<?php echo htmlspecialchars($product['colors'] ?? ''); ?>">
                    </div>
                </div>
                
                <div class="form-group">
                    <label>Contact Details *</label>
                    <input type="text" name="contact_details" class="form-control" placeholder="Phone number or email" required value="<?php echo htmlspecialchars($product['contact_details']); ?>">
                </div>
                
                <div class="form-group">
                    <label>Current Image</label>
                    <img src="../<?php echo htmlspecialchars($product['image_path']); ?>" alt="Current image" style="max-width: 200px; display: block; margin-bottom: 10px;" onerror="this.src='https://via.placeholder.com/200x200?text=No+Image'">
                </div>
                
                <div class="form-group">
                    <label>New Product Image (Optional - leave blank to keep current)</label>
                    <input type="file" name="image" class="form-control" accept="image/*">
                    <small>Only JPG, JPEG, PNG, and GIF files are allowed</small>
                </div>
                
                <div class="form-group">
                    <button type="submit" class="btn btn-primary">Update Product</button>
                    <a href="view_product.php?id=<?php echo $product['id']; ?>" class="btn btn-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</main>

<?php include '../includes/footer.php'; ?>
