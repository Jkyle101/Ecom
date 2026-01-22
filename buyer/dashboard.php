<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../config/db_config.php';
require_once '../includes/auth_check.php';

// Ensure user is logged in (buyer or seller)
require_login();

// Check if user wants to switch to seller mode
if (isset($_GET['mode']) && $_GET['mode'] == 'seller') {
    $_SESSION['view_mode'] = 'seller';
    header("Location: ../seller/dashboard.php");
    exit();
}
$_SESSION['view_mode'] = 'buyer';

$buyer_id = $_SESSION['user_id'] ?? null;
$username = $_SESSION['username'] ?? '';

// Helper: check table/column exists (safe)
function tableExists($conn, $table) {
    $table = $conn->real_escape_string($table);
    $res = $conn->query("SHOW TABLES LIKE '{$table}'");
    return ($res && $res->num_rows > 0);
}
function columnExists($conn, $table, $col) {
    $table = $conn->real_escape_string($table);
    $col = $conn->real_escape_string($col);
    $res = $conn->query("SHOW COLUMNS FROM {$table} LIKE '{$col}'");
    return ($res && $res->num_rows > 0);
}

// Unread messages count
$unread_count = 0;
if ($buyer_id && tableExists($conn, 'messages')) {
    if (columnExists($conn, 'messages', 'is_read')) {
        $col = 'is_read';
    } elseif (columnExists($conn, 'messages', 'read_status')) {
        $col = 'read_status';
    } else {
        $col = null;
    }

    if ($col) {
        $sql = "SELECT COUNT(*) AS unread_count FROM messages WHERE receiver_id = ? AND {$col} = 0";
        if ($stmt = $conn->prepare($sql)) {
            $stmt->bind_param("i", $buyer_id);
            $stmt->execute();
            $res = $stmt->get_result();
            $unread_count = $res ? (int)$res->fetch_assoc()['unread_count'] : 0;
            $stmt->close();
        }
    }
}

// Featured products (approved, latest 6) - exclude own products
$featured_products = $conn->query(
    "SELECT p.*, u.username as seller_name 
     FROM products p
     JOIN users u ON p.seller_id = u.id
     WHERE p.approval_status = 'approved' AND p.seller_id != $buyer_id
     ORDER BY p.created_at DESC
     LIMIT 6"
);

// Categories
$categories = $conn->query("SELECT * FROM categories");

// Notifications
$notifications = false;
$unread_notifications = 0;
if ($buyer_id && tableExists($conn, 'notifications')) {
    $sql = "SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 5";
    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param("i", $buyer_id);
        $stmt->execute();
        $notifications = $stmt->get_result();
        $stmt->close();
    }

    if (columnExists($conn, 'notifications', 'is_read')) {
        $sql = "SELECT COUNT(*) AS unread_notifications FROM notifications WHERE user_id = ? AND is_read = 0";
        if ($stmt = $conn->prepare($sql)) {
            $stmt->bind_param("i", $buyer_id);
            $stmt->execute();
            $res = $stmt->get_result();
            $unread_notifications = $res ? (int)$res->fetch_assoc()['unread_notifications'] : 0;
            $stmt->close();
        }
    }
}

// Recently viewed
$recently_viewed = false;
if ($buyer_id && tableExists($conn, 'product_views')) {
    $sql = "SELECT p.*, u.username as seller_name 
            FROM products p
            JOIN users u ON p.seller_id = u.id
            JOIN product_views v ON p.id = v.product_id
            WHERE v.user_id = ? AND p.approval_status = 'approved'
            ORDER BY v.viewed_at DESC
            LIMIT 4";
    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param("i", $buyer_id);
        $stmt->execute();
        $recently_viewed = $stmt->get_result();
        $stmt->close();
    }
}

?>

<?php include '../includes/header.php'; ?>

<link rel="stylesheet" href="../assets/css/dashboard.css">

<style>
    .main-wrapper { padding-top: 84px; }
    .section-title { font-size: 1.4rem; margin-bottom: 12px; color: var(--dark-text); }
    .section-block { margin-bottom: 28px; }

    /* Colored icons */
    .icon-blue { color: var(--primary-blue); }
    .icon-green { color: var(--primary-green); }
    .icon-yellow { color: var(--primary-yellow); }
    .icon-orange { color: var(--primary-orange); }
</style>

<main class="main-wrapper">
    <div class="container">

        <!-- Quick Info Cards -->
        <div style="display:flex; gap:16px; flex-wrap:wrap; margin-bottom:18px;">
            <div class="dashboard-card" style="flex:1; min-width:180px;">
                <div style="text-align: center; padding: 15px;">
                    <?php
                    // Get user profile picture
                    $user_profile_pic = '';
                    if ($buyer_id) {
                        $stmt = $conn->prepare("SELECT profile_picture FROM users WHERE id = ?");
                        $stmt->bind_param("i", $buyer_id);
                        $stmt->execute();
                        $user_data = $stmt->get_result()->fetch_assoc();
                        $user_profile_pic = $user_data['profile_picture'] ?? '';
                        $stmt->close();
                    }
                    ?>
                    <div style="position: relative; display: inline-block; margin-bottom: 10px;">
                    <img src="<?php echo !empty($user_profile_pic) ? '../' . htmlspecialchars($user_profile_pic) : 'data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMTIwIiBoZWlnaHQ9IjEyMCIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj48cmVjdCB3aWR0aD0iMTIwIiBoZWlnaHQ9IjEyMCIgZmlsbD0iI2VlZWVlZSIvPjx0ZXh0IHg9IjYwIiB5PSI2MCIgZm9udC1mYW1pbHk9IkFyaWFsIiBmb250LXNpemU9IjEyIiBmaWxsPSIjOTk5OTk5IiB0ZXh0LWFuY2hvcj0ibWlkZGxlIiBkeT0iLjNlbSI+Tm8gSW1hZ2U8L3RleHQ+PC9zdmc+'; ?>"
                         alt="Profile Picture"
                         style="width:120px; height:120px; border-radius:50%; object-fit:cover; border:4px solid #fff; box-shadow: 0 2px 10px rgba(0,0,0,0.1);">
                        <div style="position: absolute; bottom: 5px; right: 5px; width: 24px; height: 24px; background: #4CAF50; border-radius: 50%; border: 2px solid #fff; display: flex; align-items: center; justify-content: center;">
                            <i class="fas fa-check" style="color: white; font-size: 12px;"></i>
                        </div>
                    </div>
                    <div>
                        <strong style="font-size: 1.1rem;">Welcome back!</strong>
                        <div style="margin-top:6px; font-weight:600; font-size: 1.2rem; color: var(--primary-blue);"><?php echo htmlspecialchars($username); ?></div>
                        <div style="font-size:0.9rem; color:#666; margin-top: 4px;">User Dashboard</div>
                    </div>
                </div>
            </div>

            <a href="cart.php" class="dashboard-card" style="flex:1; min-width:180px; text-decoration:none; color:inherit;">
                <strong>Shopping Cart</strong>
                <div style="margin-top:8px; font-weight:600;"><?php echo getCartCount($buyer_id); ?> items</div>
            </a>

            <a href="messages.php" class="dashboard-card" style="flex:1; min-width:180px; text-decoration:none; color:inherit;">
                <strong>Messages</strong>
                <div style="margin-top:8px; font-weight:600;"><?php echo (int)$unread_count; ?> unread</div>
            </a>

            <a href="notifications.php" class="dashboard-card" style="flex:1; min-width:180px; text-decoration:none; color:inherit;">
                <strong>Notifications</strong>
                <div style="margin-top:8px; font-weight:600;"><?php echo (int)$unread_notifications; ?> unread</div>
            </a>
        </div>
        
        <!-- Action Buttons -->
        <div style="display: flex; gap: 15px; margin-bottom: 30px; flex-wrap: wrap;">
            <a href="products.php" class="btn btn-primary">
                <i class="fas fa-shopping-bag"></i> Browse Products
            </a>
            <?php
            // Check if user is already a seller (has products)
            $is_seller = false;
            if ($buyer_id) {
                $seller_check = $conn->prepare("SELECT COUNT(*) as product_count FROM products WHERE seller_id = ?");
                $seller_check->bind_param("i", $buyer_id);
                $seller_check->execute();
                $seller_result = $seller_check->get_result()->fetch_assoc();
                $is_seller = ($seller_result['product_count'] > 0);
                $seller_check->close();
            }
            if ($is_seller):
            ?>
            <a href="../seller/dashboard.php" class="btn btn-primary">
                <i class="fas fa-tachometer-alt"></i> Seller Dashboard
            </a>
            <?php else: ?>
            <a href="../seller/add_product.php" class="btn btn-secondary">
                <i class="fas fa-store"></i> Start Selling
            </a>
            <?php endif; ?>
            <a href="orders.php" class="btn btn-secondary">
                <i class="fas fa-receipt"></i> My Orders
            </a>
            <a href="account.php" class="btn btn-secondary">
                <i class="fas fa-user-cog"></i> Manage Account
            </a>
        </div>

        <!-- Hero -->
        <section class="hero-banner" style="border-radius:12px; padding:28px; margin-bottom:22px;">
            <div style="display:flex; align-items:center; justify-content:space-between; gap:16px; flex-wrap:wrap;">
                <div>
                    <h2 style="margin:0; color:#fff;">Welcome to Marketplace</h2>
                    <p style="margin:8px 0 0; color:rgba(255,255,255,0.95);">Find great deals from sellers.</p>
                </div>
                <div>
                    <a href="products.php" class="btn btn-primary">Browse Products</a>
                </div>
            </div>
        </section>

        <!-- Featured Products -->
        <section class="section-block">
            <div class="card-header" style="margin-bottom:12px;">
                <h3 class="section-title">Featured Products</h3>
                <a href="products.php" class="btn btn-sm btn-outline">View All</a>
            </div>

            <?php
            $icon_colors = ['icon-blue', 'icon-green', 'icon-yellow', 'icon-orange'];
            ?>
            <div class="product-slider">
                <?php if ($featured_products && $featured_products->num_rows > 0): ?>
                    <?php while ($product = $featured_products->fetch_assoc()): ?>
                        <?php $color_class = $icon_colors[array_rand($icon_colors)]; ?>
                        <div class="product-card" role="article">
                            <div class="product-image">
                                <img src="../<?php echo htmlspecialchars($product['image_path'] ?? ''); ?>" alt="<?php echo htmlspecialchars($product['name'] ?? 'Product'); ?>" onerror="this.src='data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMjUwIiBoZWlnaHQ9IjE2MCIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj48cmVjdCB3aWR0aD0iMjUwIiBoZWlnaHQ9IjE2MCIgZmlsbD0iI2VlZWVlZSIvPjx0ZXh0IHg9IjEyNSIgeT0iODAiIGZvbnQtZmFtaWx5PSJBcmlhbCIgZm9udC1zaXplPSIxNCIgZmlsbD0iIzk5OTk5OSIgdGV4dC1hbmNob3I9Im1pZGRsZSIgZHk9Ii4zZW0iPk5vIEltYWdlPC90ZXh0Pjwvc3ZnPg=='">
                            </div>
                            <div class="product-details">
                                <h3>
                                    <i class="fas fa-shopping-bag <?php echo $color_class; ?>"></i>
                                    <?php echo htmlspecialchars($product['name'] ?? 'Product'); ?>
                                </h3>
                                <p class="product-price">₱<?php echo number_format($product['price'] ?? 0, 2); ?></p>
                                <p class="product-category"><?php echo htmlspecialchars($product['category'] ?? ''); ?></p>
                                <p class="product-seller">By <?php echo htmlspecialchars($product['seller_name'] ?? ''); ?></p>
                            </div>
                            <div class="product-actions" style="margin-top:8px;">
                                <a href="view_product.php?id=<?php echo (int)$product['id']; ?>" class="btn">View Details</a>
                            </div>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="empty-state" style="padding:18px; background:#fff; border-radius:8px;">
                        <i class="fas fa-shopping-bag" style="font-size:22px; color:#777;"></i>
                        <p style="margin:8px 0 0;">No featured products available yet.</p>
                    </div>
                <?php endif; ?>
            </div>
        </section>

        <!-- Shop by Category -->
        <section class="section-block">
            <div class="card-header" style="margin-bottom:12px;">
                <h3 class="section-title">Shop by Category</h3>
            </div>

            <div class="category-grid">
                <?php if ($categories && $categories->num_rows > 0): ?>
                    <?php while ($category = $categories->fetch_assoc()): ?>
                        <?php 
                        $color_class = $icon_colors[array_rand($icon_colors)];
                        $cname = strtolower($category['name']);
                        ?>
                        <a href="products.php?category=<?php echo urlencode($category['name']); ?>" class="category-card">
                            <div class="category-icon">
                                <?php
                                if (strpos($cname, 'cloth') !== false) echo '<i class="fas fa-tshirt ' . $color_class . '"></i>';
                                elseif (strpos($cname, 'elect') !== false) echo '<i class="fas fa-laptop ' . $color_class . '"></i>';
                                elseif (strpos($cname, 'access') !== false) echo '<i class="fas fa-glasses ' . $color_class . '"></i>';
                                elseif (strpos($cname, 'school') !== false) echo '<i class="fas fa-book ' . $color_class . '"></i>';
                                else echo '<i class="fas fa-shopping-bag ' . $color_class . '"></i>';
                                ?>
                            </div>
                            <h3><?php echo htmlspecialchars($category['name']); ?></h3>
                        </a>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="empty-state" style="padding:18px; background:#fff; border-radius:8px;">
                        <p>No categories found.</p>
                    </div>
                <?php endif; ?>
            </div>
        </section>

        <!-- Recently Viewed -->
        <section class="section-block">
            <div class="card-header" style="margin-bottom:12px;">
                <h3 class="section-title">Recently Viewed</h3>
            </div>

            <?php if ($recently_viewed && $recently_viewed->num_rows > 0): ?>
                <div class="recent-products">
                    <?php while ($r = $recently_viewed->fetch_assoc()): ?>
                        <div class="recent-product-item">
                            <div class="recent-product-image">
                                <img src="../<?php echo htmlspecialchars($r['image_path'] ?? ''); ?>" alt="<?php echo htmlspecialchars($r['name'] ?? ''); ?>" onerror="this.src='data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iNzAiIGhlaWdodD0iNzAiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyI+PHJlY3Qgd2lkdGg9IjcwIiBoZWlnaHQ9IjcwIiBmaWxsPSIjZWVlZWVlIi8+PHRleHQgeD0iMzUiIHk9IjM1IiBmb250LWZhbWlseT0iQXJpYWwiIGZvbnQtc2l6ZT0iMTEiIGZpbGw9IiM5OTk5OTkiIHRleHQtYW5jaG9yPSJtaWRkbGUiIGR5PSIuM2VtIj5ObyBJbWFnZTwvdGV4dD48L3N2Zz4='">
                            </div>
                            <div class="recent-product-info">
                                <h3><?php echo htmlspecialchars($r['name'] ?? ''); ?></h3>
                                <p class="product-price">₱<?php echo number_format($r['price'] ?? 0, 2); ?></p>
                                <a href="view_product.php?id=<?php echo (int)$r['id']; ?>" class="btn btn-sm btn-outline">View Again</a>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>
            <?php else: ?>
                <div class="empty-state" style="padding:18px; background:#fff; border-radius:8px;">
                    <i class="fas fa-eye-slash" style="font-size:20px; color:#777;"></i>
                    <p style="margin:8px 0 0;">No recently viewed products</p>
                    <a href="products.php" class="btn btn-primary" style="margin-top:8px; display:inline-block;">Browse Products</a>
                </div>
            <?php endif; ?>
        </section>

    </div> <!-- /.container -->
</main>

<!-- page scripts -->
<script src="../assets/js/dashboard.js"></script>

<?php include '../includes/footer.php'; ?>
