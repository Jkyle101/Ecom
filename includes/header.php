<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Determine base path dynamically
$script_path = str_replace('\\', '/', dirname(dirname(__FILE__)));
$document_root = str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT']);
$base_path = str_replace($document_root, '', $script_path);

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
    <?php
    $logo_path = $include_path . '/assets/images/logo.png';
    if (file_exists($logo_path)) {
        $logo_data = base64_encode(file_get_contents($logo_path));
        echo '<link rel="icon" type="image/png" href="data:image/png;base64,' . $logo_data . '">';
    }
    ?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <link rel="stylesheet" href="<?php echo $base_path; ?>/assets/css/style.css">
</head>
<body>
    <header>
        <nav class="navbar">
            <div class="nav-left">
            <img src="data:image/png;base64,<?php echo $logo_data; ?>" alt="Logo" style="width: 50px; height: 50px;">
                <div class="logo">
                     
                    <a href="<?php echo $base_path; ?>/buyer/dashboard.php">E-Commerce Platform</a>
                </div>
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
    <nav class="mobile-bottom-nav">
        <ul>
            <?php if(isset($_SESSION['user_id'])): ?>
                <?php if($_SESSION['role'] == 'admin'): ?>
                    <li>
                        <a href="<?php echo $base_path; ?>/buyer/dashboard.php">
                            <i class="fas fa-home"></i>
                            <span>Home</span>
                        </a>
                    </li>
                    <li>
                        <a href="<?php echo $base_path; ?>/admin/products.php">
                            <i class="fas fa-th-large"></i>
                            <span>Listings</span>
                        </a>
                    </li>
                    <li>
                        <a href="<?php echo $base_path; ?>/admin/transactions.php">
                            <i class="fas fa-receipt"></i>
                            <span>Orders</span>
                        </a>
                    </li>
                    <li>
                        <a href="<?php echo $base_path; ?>/admin/account.php">
                            <i class="fas fa-user"></i>
                            <span>Account</span>
                        </a>
                    </li>
                    <li>
                        <a href="<?php echo $base_path; ?>/auth/logout.php">
                            <i class="fas fa-sign-out-alt"></i>
                            <span>Logout</span>
                        </a>
                    </li>
                <?php elseif($_SESSION['role'] == 'seller'): ?>
                    <li>
                        <a href="<?php echo $base_path; ?>/buyer/dashboard.php">
                            <i class="fas fa-home"></i>
                            <span>Home</span>
                        </a>
                    </li>
                    <li>
                        <a href="<?php echo $base_path; ?>/seller/products.php">
                            <i class="fas fa-box"></i>
                            <span>Products</span>
                        </a>
                    </li>
                    <li>
                        <a href="<?php echo $base_path; ?>/seller/orders.php">
                            <i class="fas fa-shopping-bag"></i>
                            <span>Orders</span>
                        </a>
                    </li>
                    <li>
                        <a href="<?php echo $base_path; ?>/seller/account.php">
                            <i class="fas fa-user"></i>
                            <span>Account</span>
                        </a>
                    </li>
                    <li>
                        <a href="<?php echo $base_path; ?>/auth/logout.php">
                            <i class="fas fa-sign-out-alt"></i>
                            <span>Logout</span>
                        </a>
                    </li>
                    <li>
                        <a href="<?php echo $base_path; ?>/buyer/messages.php">
                            <i class="fas fa-envelope"></i>
                            <span>Messages</span>
                            <?php echo displayMessageBadge($_SESSION['user_id']); ?>
                        </a>
                    </li>
                    <li>
                        <a href="<?php echo $base_path; ?>/buyer/notifications.php">
                            <i class="fas fa-bell"></i>
                            <?php if(isset($_SESSION['user_id']) && function_exists('countUnreadNotifications')): ?>
                                <?php $unread_notif = countUnreadNotifications($_SESSION['user_id']); ?>
                                <?php if($unread_notif > 0): ?>
                                    <span class="notification-badge"><?php echo $unread_notif; ?></span>
                                <?php endif; ?>
                            <?php endif; ?>
                            <span>Notifications</span>
                        </a>
                    </li>
                    <li>
                        <a href="<?php echo $base_path; ?>/buyer/cart.php">
                            <i class="fas fa-shopping-cart"></i>
                            <span class="message-badge"><?php echo getCartCount($_SESSION['user_id']); ?></span>
                            <span>Cart</span>
                        </a>
                    </li>
                    <li>
                        <a href="<?php echo $base_path; ?>/auth/logout.php">
                            <i class="fas fa-sign-out-alt"></i>
                            <span>Logout</span>
                        </a>
                    </li>
                <?php else: ?>
                    <li>
                        <a href="<?php echo $base_path; ?>/buyer/dashboard.php">
                            <i class="fas fa-home"></i>
                            <span>Home</span>
                        </a>
                    </li>
                    <li>
                        <a href="<?php echo $base_path; ?>/buyer/products.php">
                            <i class="fas fa-search"></i>
                            <span>Search</span>
                        </a>
                    </li>
                    <li>
                        <a href="<?php echo $base_path; ?>/buyer/cart.php">
                            <i class="fas fa-shopping-cart"></i>
                            <span class="message-badge"><?php echo getCartCount($_SESSION['user_id']); ?></span>
                            <span>Cart</span>
                        </a>
                    </li>
                    <li>
                        <a href="<?php echo $base_path; ?>/buyer/messages.php">
                            <i class="fas fa-envelope"></i>
                            <span>Messages</span>
                            <?php echo displayMessageBadge($_SESSION['user_id']); ?>
                        </a>
                    </li>
                    <li>
                        <a href="<?php echo $base_path; ?>/buyer/notifications.php">
                            <i class="fas fa-bell"></i>
                            <?php if(isset($_SESSION['user_id']) && function_exists('countUnreadNotifications')): ?>
                                <?php $unread_notif = countUnreadNotifications($_SESSION['user_id']); ?>
                                <?php if($unread_notif > 0): ?>
                                    <span class="notification-badge"><?php echo $unread_notif; ?></span>
                                <?php endif; ?>
                            <?php endif; ?>
                            <span>Notifications</span>
                        </a>
                    </li>
                    <li>
                        <a href="<?php echo $base_path; ?>/buyer/account.php">
                            <i class="fas fa-user"></i>
                            <span>Account</span>
                        </a>
                    </li>
                    <li>
                        <a href="<?php echo $base_path; ?>/auth/logout.php">
                            <i class="fas fa-sign-out-alt"></i>
                            <span>Logout</span>
                        </a>
                    </li>
                <?php endif; ?>
            <?php else: ?>
                <li>
                    <a href="<?php echo $base_path; ?>/index.php">
                        <i class="fas fa-home"></i>
                        <span>Home</span>
                    </a>
                </li>
                <li>
                    <a href="<?php echo $base_path; ?>/auth/login.php">
                        <i class="fas fa-sign-in-alt"></i>
                        <span>Login</span>
                    </a>
                </li>
                <li>
                    <a href="<?php echo $base_path; ?>/auth/register.php">
                        <i class="fas fa-user-plus"></i>
                        <span>Register</span>
                    </a>
                </li>
            <?php endif; ?>
        </ul>
    </nav>
    <main>
