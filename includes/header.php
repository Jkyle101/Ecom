<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Determine base path dynamically
$base_path = str_replace($_SERVER['DOCUMENT_ROOT'], '', dirname(dirname(__FILE__)));

// Get relative path for includes
$include_path = dirname(__DIR__);

// include database and message-related functions
require_once $include_path . '/config/db_config.php';
require_once $include_path . '/includes/messages.php';
require_once $include_path . '/includes/notifications.php';
require_once $include_path . '/includes/cart.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student E-Commerce Platform</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <link rel="stylesheet" href="<?php echo $base_path; ?>/assets/css/style.css">
</head>
<body>
    <header>
        <nav class="navbar">
            <div class="logo">
                <a href="<?php echo $base_path; ?>/index.php">E-Commerce Platform</a>
            </div>
            <ul class="nav-links">
                <?php if(isset($_SESSION['user_id'])): ?>
                    <?php if($_SESSION['role'] == 'admin'): ?>
                        <li><a href="<?php echo $base_path; ?>/admin/dashboard.php">Dashboard</a></li>
                        <li><a href="<?php echo $base_path; ?>/admin/users.php">Users</a></li>
                        <li><a href="<?php echo $base_path; ?>/admin/products.php">Listings</a></li>
                        <li><a href="<?php echo $base_path; ?>/admin/deletion_requests.php">Deletions</a></li>
                        <li><a href="<?php echo $base_path; ?>/admin/transactions.php">Transactions</a></li>
                        <li><a href="<?php echo $base_path; ?>/admin/view_feedback.php">Feedback</a></li>
                        <li><a href="<?php echo $base_path; ?>/admin/system_maintenance.php">Maintenance</a></li>
                        <li><a href="<?php echo $base_path; ?>/admin/account.php">Account</a></li>
                    <?php else: ?>
                        <li><a href="<?php echo $base_path; ?>/buyer/dashboard.php">Dashboard</a></li>
                        <li><a href="<?php echo $base_path; ?>/buyer/products.php">Products</a></li>
                        <li><a href="<?php echo $base_path; ?>/buyer/cart.php">
                            Cart <span class="message-badge"><?php echo getCartCount($_SESSION['user_id']); ?></span>
                        </a></li>
                        <li><a href="<?php echo $base_path; ?>/buyer/messages.php">
                            Messages <?php echo displayMessageBadge($_SESSION['user_id']); ?>
                        </a></li>
                        <li><a href="<?php echo $base_path; ?>/buyer/orders.php">My Orders</a></li>
                        <li><a href="<?php echo $base_path; ?>/buyer/account.php">Account</a></li>
                    <?php endif; ?>
                    <li><a href="<?php echo $base_path; ?>/auth/logout.php">Logout</a></li>
                <?php else: ?>
                    <li><a href="<?php echo $base_path; ?>/index.php">Home</a></li>
                    <li><a href="<?php echo $base_path; ?>/auth/login.php">Login</a></li>
                    <li><a href="<?php echo $base_path; ?>/auth/register.php">Register</a></li>
                <?php endif; ?>
            </ul>
        </nav>
    </header>
    <main>
