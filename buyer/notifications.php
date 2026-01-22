<?php
require_once '../config/db_config.php';
require_once '../includes/auth_check.php';
require_once '../includes/notifications.php';

require_login();

$buyer_id = $_SESSION['user_id'];

// Get notifications
$notifications = getUserNotifications($buyer_id);

// Mark as read if requested
if (isset($_GET['mark_read'])) {
    markNotificationAsRead((int)$_GET['mark_read']);
    header("Location: notifications.php");
    exit();
}
?>

<?php include '../includes/header.php'; ?>
<link rel="stylesheet" href="../assets/css/dashboard.css">

<main class="main-wrapper">
    <div class="container">
        <div class="page-header">
            <h1>Notifications</h1>
        </div>
        
        <div class="content-card">
            <?php if (!empty($notifications)): ?>
                <?php foreach ($notifications as $notif): ?>
                    <div class="notification-item <?php echo $notif['is_read'] == 0 ? 'unread' : ''; ?>">
                        <div class="notification-message">
                            <?php echo htmlspecialchars($notif['message']); ?>
                        </div>
                        <div class="notification-time">
                            <?php echo date('M j, Y g:i A', strtotime($notif['created_at'])); ?>
                            <?php if ($notif['is_read'] == 0): ?>
                                <a href="notifications.php?mark_read=<?php echo $notif['id']; ?>" class="btn btn-sm" style="margin-left: 10px;">Mark as Read</a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-bell"></i>
                    <p>No notifications</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</main>

<?php include '../includes/footer.php'; ?>

