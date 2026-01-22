<?php
require_once '../config/db_config.php';
require_once '../includes/auth_check.php';

require_role('admin');

$feedback_list = $conn->query("SELECT f.*, u.username 
                                FROM feedback f
                                JOIN users u ON f.user_id = u.id
                                ORDER BY f.created_at DESC");
?>

<?php include '../includes/header.php'; ?>
<link rel="stylesheet" href="../assets/css/dashboard.css">

<main class="main-wrapper">
    <div class="container">
        <div class="page-header">
            <h1>Feedback</h1>
            <div class="breadcrumb">Home > Admin > Feedback</div>
        </div>
        
        <div class="content-card">
            <?php if ($feedback_list && $feedback_list->num_rows > 0): ?>
                <?php while ($feedback = $feedback_list->fetch_assoc()): ?>
                    <div class="notification-item">
                        <div style="display: flex; justify-content: space-between; align-items: start;">
                            <div>
                                <strong><?php echo htmlspecialchars($feedback['subject']); ?></strong>
                                <span class="product-category" style="margin-left: 10px;"><?php echo ucfirst($feedback['type']); ?></span>
                                <div style="margin-top: 8px; color: #666;">
                                    From: <?php echo htmlspecialchars($feedback['username']); ?>
                                </div>
                                <div class="notification-message">
                                    <?php echo nl2br(htmlspecialchars($feedback['message'])); ?>
                                </div>
                                <div class="notification-time">
                                    <?php echo date('M j, Y g:i A', strtotime($feedback['created_at'])); ?>
                                    <span style="margin-left: 10px;">Status: <?php echo ucfirst($feedback['status']); ?></span>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-comment"></i>
                    <p>No feedback yet</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</main>

<?php include '../includes/footer.php'; ?>

