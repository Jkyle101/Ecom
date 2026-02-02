<?php
require_once '../config/db_config.php';
require_once '../includes/auth_check.php';

require_role('admin');

// Get statistics
$total_products = $conn->query("SELECT COUNT(*) as count FROM products")->fetch_assoc()['count'];
$pending_products = $conn->query("SELECT COUNT(*) as count FROM products WHERE approval_status = 'pending'")->fetch_assoc()['count'];
$total_users = $conn->query("SELECT COUNT(*) as count FROM users WHERE role != 'admin'")->fetch_assoc()['count'];
$total_orders = $conn->query("SELECT COUNT(*) as count FROM orders")->fetch_assoc()['count'];
$total_feedback = $conn->query("SELECT COUNT(*) as count FROM feedback WHERE status != 'resolved'")->fetch_assoc()['count'];
$approved_products = $conn->query("SELECT COUNT(*) as count FROM products WHERE approval_status = 'approved'")->fetch_assoc()['count'];
$pending_deletions = $conn->query("SELECT COUNT(*) as count FROM product_deletion_requests WHERE status = 'pending'")->fetch_assoc()['count'];

// Get recent pending products
$recent_pending = $conn->query("SELECT p.*, u.username as seller_name FROM products p JOIN users u ON p.seller_id = u.id WHERE p.approval_status = 'pending' ORDER BY p.created_at DESC LIMIT 5");

// Get recent orders
$recent_orders = $conn->query("SELECT o.*, u.username as buyer_name FROM orders o JOIN users u ON o.user_id = u.id ORDER BY o.created_at DESC LIMIT 5");

// Get recent deletion requests
$recent_deletions = $conn->query("SELECT dr.*, p.name as product_name, u.username as seller_name 
                                  FROM product_deletion_requests dr
                                  JOIN products p ON dr.product_id = p.id
                                  JOIN users u ON dr.seller_id = u.id
                                  WHERE dr.status = 'pending'
                                  ORDER BY dr.requested_at DESC LIMIT 3");
?>

<?php include '../includes/header.php'; ?>
<link rel="stylesheet" href="../assets/css/dashboard.css">

<main class="main-wrapper">
    <div class="container">
        <div class="page-header">
            <h1>Admin Dashboard</h1>
            <div class="breadcrumb">Home > Admin Dashboard</div>
        </div>

        <!-- Stats Cards -->
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 30px;">
            <div class="dashboard-card">
                <strong>Total Users</strong>
                <div style="font-size: 2rem; margin-top: 10px; color: var(--primary-blue);"><?php echo $total_users; ?></div>
                <a href="users.php" class="btn btn-sm btn-outline" style="margin-top: 10px; display: inline-block;">Manage</a>
            </div>
            
            <div class="dashboard-card">
                <strong>Total Products</strong>
                <div style="font-size: 2rem; margin-top: 10px; color: var(--primary-green);"><?php echo $total_products; ?></div>
                <a href="products.php" class="btn btn-sm btn-outline" style="margin-top: 10px; display: inline-block;">Manage</a>
            </div>
            
            <div class="dashboard-card">
                <strong>Pending Approval</strong>
                <div style="font-size: 2rem; margin-top: 10px; color: var(--primary-orange);"><?php echo $pending_products; ?></div>
                <a href="products.php?status=pending" class="btn btn-sm btn-outline" style="margin-top: 10px; display: inline-block;">Review</a>
            </div>
            
            <div class="dashboard-card">
                <strong>Total Orders</strong>
                <div style="font-size: 2rem; margin-top: 10px; color: var(--primary-yellow);"><?php echo $total_orders; ?></div>
                <a href="transactions.php" class="btn btn-sm btn-outline" style="margin-top: 10px; display: inline-block;">Monitor</a>
            </div>
            
            <div class="dashboard-card">
                <strong>Pending Feedback</strong>
                <div style="font-size: 2rem; margin-top: 10px; color: #ff6b6b;"><?php echo $total_feedback; ?></div>
                <a href="view_feedback.php" class="btn btn-sm btn-outline" style="margin-top: 10px; display: inline-block;">Review</a>
            </div>
            
            <div class="dashboard-card">
                <strong>Approved Products</strong>
                <div style="font-size: 2rem; margin-top: 10px; color: var(--primary-green);"><?php echo $approved_products; ?></div>
            </div>
            
            <div class="dashboard-card">
                <strong>Deletion Requests</strong>
                <div style="font-size: 2rem; margin-top: 10px; color: #ff6b6b;"><?php echo $pending_deletions; ?></div>
                <a href="deletion_requests.php" class="btn btn-sm btn-outline" style="margin-top: 10px; display: inline-block;">Review</a>
            </div>
        </div>

        <!-- Quick Actions Menu -->
        <div class="content-card">
            <h2>Admin Functions</h2>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin-top: 20px;">
                <a href="users.php" class="quick-action-card" style="text-decoration: none; color: inherit;">
                    <div class="action-icon blue">
                        <i class="fas fa-users"></i>
                    </div>
                    <div>
                        <h3>Manage Users</h3>
                        <p>View, edit, and manage user accounts</p>
                    </div>
                </a>
                
                <a href="products.php" class="quick-action-card" style="text-decoration: none; color: inherit;">
                    <div class="action-icon green">
                        <i class="fas fa-box"></i>
                    </div>
                    <div>
                        <h3>Manage Listings</h3>
                        <p>Approve, reject, and manage product listings</p>
                    </div>
                </a>
                
                <a href="deletion_requests.php" class="quick-action-card" style="text-decoration: none; color: inherit;">
                    <div class="action-icon" style="background: #ff6b6b; color: white;">
                        <i class="fas fa-trash-alt"></i>
                    </div>
                    <div>
                        <h3>Deletion Requests</h3>
                        <p>Review and approve product deletion requests <?php if ($pending_deletions > 0): ?><span style="color: #ff6b6b;">(<?php echo $pending_deletions; ?>)</span><?php endif; ?></p>
                    </div>
                </a>
                
                <a href="transactions.php" class="quick-action-card" style="text-decoration: none; color: inherit;">
                    <div class="action-icon orange">
                        <i class="fas fa-receipt"></i>
                    </div>
                    <div>
                        <h3>Monitor Transactions</h3>
                        <p>View and monitor all orders and transactions</p>
                    </div>
                </a>
                
                <a href="view_feedback.php" class="quick-action-card" style="text-decoration: none; color: inherit;">
                    <div class="action-icon purple">
                        <i class="fas fa-comments"></i>
                    </div>
                    <div>
                        <h3>Oversee Reports/Feedback</h3>
                        <p>View and manage user feedback and reports</p>
                    </div>
                </a>
                
                <a href="categories.php" class="quick-action-card" style="text-decoration: none; color: inherit;">
                    <div class="action-icon" style="background: #9b59b6; color: white;">
                        <i class="fas fa-tags"></i>
                    </div>
                    <div>
                        <h3>Manage Categories</h3>
                        <p>Add, edit, and manage product categories</p>
                    </div>
                </a>

                <a href="locations.php" class="quick-action-card" style="text-decoration: none; color: inherit;">
                    <div class="action-icon" style="background: #3498db; color: white;">
                        <i class="fas fa-map-marker-alt"></i>
                    </div>
                    <div>
                        <h3>Manage Locations</h3>
                        <p>Add, edit buildings and rooms for delivery</p>
                    </div>
                </a>

                <a href="system_maintenance.php" class="quick-action-card" style="text-decoration: none; color: inherit;">
                    <div class="action-icon" style="background: #ff6b6b; color: white;">
                        <i class="fas fa-cog"></i>
                    </div>
                    <div>
                        <h3>System Maintenance</h3>
                        <p>System settings and maintenance tools</p>
                    </div>
                </a>

                <a href="account.php" class="quick-action-card" style="text-decoration: none; color: inherit;">
                    <div class="action-icon" style="background: #4ecdc4; color: white;">
                        <i class="fas fa-user-cog"></i>
                    </div>
                    <div>
                        <h3>Manage Account</h3>
                        <p>Update admin account settings</p>
                    </div>
                </a>
            </div>
        </div>

        <!-- Recent Activity -->
        <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 20px; margin-top: 20px;">
            <!-- Pending Products -->
            <div class="content-card">
                <div class="card-header">
                    <h2>Products Pending Approval</h2>
                    <a href="products.php?status=pending" class="btn btn-sm btn-outline">View All</a>
                </div>
                
                <?php if ($recent_pending && $recent_pending->num_rows > 0): ?>
                    <div class="product-grid">
                        <?php while ($product = $recent_pending->fetch_assoc()): ?>
                            <div class="product-card">
                                <div class="product-image">
                                    <img src="../<?php echo htmlspecialchars($product['image_path']); ?>" alt="<?php echo htmlspecialchars($product['name']); ?>" onerror="this.src='https://via.placeholder.com/250x200?text=No+Image'">
                                </div>
                                <div class="product-info">
                                    <h3><?php echo htmlspecialchars($product['name']); ?></h3>
                                    <p class="product-price">₱<?php echo number_format($product['price'], 2); ?></p>
                                    <p style="font-size: 0.9rem; color: #666;">By <?php echo htmlspecialchars($product['seller_name']); ?></p>
                                    <a href="view_product.php?id=<?php echo $product['id']; ?>" class="btn btn-primary" style="margin-top: 10px;">Review</a>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-check-circle"></i>
                        <p>No pending products</p>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Deletion Requests -->
            <div class="content-card">
                <div class="card-header">
                    <h2>Deletion Requests</h2>
                    <a href="deletion_requests.php" class="btn btn-sm btn-outline">View All</a>
                </div>
                
                <?php if ($recent_deletions && $recent_deletions->num_rows > 0): ?>
                    <?php while ($del_req = $recent_deletions->fetch_assoc()): ?>
                        <div style="padding: 15px; border-bottom: 1px solid #eee;">
                            <h4 style="margin: 0 0 5px 0;"><?php echo htmlspecialchars($del_req['product_name']); ?></h4>
                            <p style="font-size: 0.9rem; color: #666; margin: 5px 0;">Seller: <?php echo htmlspecialchars($del_req['seller_name']); ?></p>
                            <p style="font-size: 0.8rem; color: #999;">Requested: <?php echo date('M j, Y', strtotime($del_req['requested_at'])); ?></p>
                            <a href="deletion_requests.php" class="btn btn-sm btn-primary" style="margin-top: 10px;">Review</a>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-check-circle"></i>
                        <p>No deletion requests</p>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Recent Orders -->
            <div class="content-card">
                <div class="card-header">
                    <h2>Recent Orders</h2>
                    <a href="transactions.php" class="btn btn-sm btn-outline">View All</a>
                </div>
                
                <?php if ($recent_orders && $recent_orders->num_rows > 0): ?>
                    <table style="width: 100%; border-collapse: collapse;">
                        <thead>
                            <tr style="border-bottom: 2px solid #ddd;">
                                <th style="padding: 10px; text-align: left;">Order ID</th>
                                <th style="padding: 10px; text-align: left;">Buyer</th>
                                <th style="padding: 10px; text-align: left;">Amount</th>
                                <th style="padding: 10px; text-align: left;">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($order = $recent_orders->fetch_assoc()): ?>
                                <tr style="border-bottom: 1px solid #eee;">
                                    <td style="padding: 10px;">#<?php echo $order['id']; ?></td>
                                    <td style="padding: 10px;"><?php echo htmlspecialchars($order['buyer_name']); ?></td>
                                    <td style="padding: 10px;">₱<?php echo number_format($order['total_amount'], 2); ?></td>
                                    <td style="padding: 10px;">
                                        <span class="product-category"><?php echo ucfirst($order['status']); ?></span>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-receipt"></i>
                        <p>No recent orders</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</main>

<?php include '../includes/footer.php'; ?>
