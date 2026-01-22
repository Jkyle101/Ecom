<?php
require_once '../config/db_config.php';
require_once '../includes/auth_check.php';

require_login();
// Allow buyers to add products and automatically become sellers

$seller_id = $_SESSION['user_id'];
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
        // Handle file upload
        $image_path = '';
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
                    $image_path = 'uploads/products/' . $file_name;
                } else {
                    $error = "Failed to upload image";
                }
            } else {
                $error = "Invalid file type. Only JPG, JPEG, PNG, and GIF are allowed";
            }
        } else {
            $error = "Please upload an image";
        }
        
        if (empty($error)) {
            $sql = "INSERT INTO products (seller_id, name, description, price, category, sizes, colors, contact_details, image_path) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("issdsssss", $seller_id, $name, $description, $price, $category, $sizes, $colors, $contact_details, $image_path);
            
            if ($stmt->execute()) {
                // Check if user is currently a buyer and upgrade them to seller
                $was_buyer = ($_SESSION['role'] === 'buyer');
                if ($was_buyer) {
                    $update_role = $conn->prepare("UPDATE users SET role = 'seller' WHERE id = ?");
                    $update_role->bind_param("i", $seller_id);
                    if ($update_role->execute()) {
                        // Update session role only if database update was successful
                        $_SESSION['role'] = 'seller';
                    }
                    $update_role->close();
                }

                if ($was_buyer && $_SESSION['role'] === 'seller') {
                    // Redirect new sellers to the seller dashboard
                    header("Location: ./dashboard.php");
                    exit();
                } else {
                    $success = "Product added successfully! It will be reviewed by admin before being published.";
                    // Clear form
                    $_POST = [];
                }
            } else {
                $error = "Failed to add product";
            }
            $stmt->close();
        }
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
            <h1>Add Product</h1>
            <div class="breadcrumb">Home > Seller > Add Product</div>
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
                    <input type="text" name="name" class="form-control" required value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>">
                </div>
                
                <div class="form-group">
                    <label>Description *</label>
                    <textarea name="description" class="form-control" rows="5" required><?php echo htmlspecialchars($_POST['description'] ?? ''); ?></textarea>
                </div>
                
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                    <div class="form-group">
                        <label>Price *</label>
                        <input type="number" name="price" class="form-control" step="0.01" min="0" required value="<?php echo htmlspecialchars($_POST['price'] ?? ''); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label>Category *</label>
                        <select name="category" class="form-control" required>
                            <option value="">Select Category</option>
                            <?php while ($cat = $categories->fetch_assoc()): ?>
                                <option value="<?php echo htmlspecialchars($cat['name']); ?>" <?php echo (isset($_POST['category']) && $_POST['category'] === $cat['name']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($cat['name']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                </div>
                
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                    <div class="form-group">
                        <label>Sizes (Optional)</label>
                        <input type="text" name="sizes" class="form-control" placeholder="e.g., S, M, L, XL" value="<?php echo htmlspecialchars($_POST['sizes'] ?? ''); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label>Colors (Optional)</label>
                        <input type="text" name="colors" class="form-control" placeholder="e.g., Red, Blue, Green" value="<?php echo htmlspecialchars($_POST['colors'] ?? ''); ?>">
                    </div>
                </div>
                
                <div class="form-group">
                    <label>Contact Details *</label>
                    <input type="text" name="contact_details" class="form-control" placeholder="Phone number or email" required value="<?php echo htmlspecialchars($_POST['contact_details'] ?? ''); ?>">
                </div>
                
                <div class="form-group">
                    <label>Product Image *</label>
                    <input type="file" name="image" class="form-control" accept="image/*" required>
                    <small>Only JPG, JPEG, PNG, and GIF files are allowed</small>
                </div>
                
                <div class="form-group">
                    <button type="submit" class="btn btn-primary">Add Product</button>
                    <a href="dashboard.php" class="btn btn-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</main>

<?php include '../includes/footer.php'; ?>
