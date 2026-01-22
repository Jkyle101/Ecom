<?php
require_once '../config/db_config.php';
require_once '../includes/auth_check.php';

require_login();

$user_id = $_SESSION['user_id'];
$category_filter = isset($_GET['category']) ? sanitize_input($_GET['category']) : '';
$search_query = isset($_GET['search']) ? sanitize_input($_GET['search']) : '';

// Build query - exclude products where seller_id = current user_id
$sql = "SELECT p.*, u.username as seller_name 
        FROM products p
        JOIN users u ON p.seller_id = u.id
        WHERE p.approval_status = 'approved' AND p.seller_id != ?";

$params = [$user_id];
$types = 'i';

if (!empty($category_filter)) {
    $sql .= " AND p.category = ?";
    $params[] = $category_filter;
    $types .= 's';
}

if (!empty($search_query)) {
    $sql .= " AND (p.name LIKE ? OR p.description LIKE ?)";
    $search_term = "%$search_query%";
    $params[] = $search_term;
    $params[] = $search_term;
    $types .= 'ss';
}

$sql .= " ORDER BY p.created_at DESC";

$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$products = $stmt->get_result();

// Get categories
$categories = $conn->query("SELECT * FROM categories");
?>

<?php include '../includes/header.php'; ?>
<link rel="stylesheet" href="../assets/css/dashboard.css">

<main class="main-wrapper">
    <div class="container">
        <div class="page-header">
            <h1>All Products</h1>
            <div class="breadcrumb">Home > Products</div>
        </div>

        <!-- Search and Filter -->
        <div class="content-card">
            <form method="GET" style="display: flex; gap: 10px; flex-wrap: wrap;">
                <input type="text" name="search" class="form-control" placeholder="Search products..." value="<?php echo htmlspecialchars($search_query); ?>" style="flex: 1; min-width: 200px;">
                <select name="category" class="form-control" style="width: 200px;">
                    <option value="">All Categories</option>
                    <?php while ($cat = $categories->fetch_assoc()): ?>
                        <option value="<?php echo htmlspecialchars($cat['name']); ?>" <?php echo $category_filter === $cat['name'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($cat['name']); ?>
                        </option>
                    <?php endwhile; ?>
                </select>
                <button type="submit" class="btn btn-primary">Search</button>
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
                            <h3 class="product-title"><?php echo htmlspecialchars($product['name']); ?></h3>
                            <p class="product-price">₱<?php echo number_format($product['price'], 2); ?></p>
                            <p class="product-category"><?php echo htmlspecialchars($product['category']); ?></p>
                            <p style="font-size: 0.9rem; color: #666;">By <?php echo htmlspecialchars($product['seller_name']); ?></p>
                            <a href="view_product.php?id=<?php echo $product['id']; ?>" class="btn btn-primary" style="margin-top: 10px; display: inline-block;">View Details</a>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-search"></i>
                    <p>No products found. Try a different search or category.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</main>

<?php include '../includes/footer.php'; ?>
