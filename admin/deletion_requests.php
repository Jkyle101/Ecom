<?php
require_once '../config/db_config.php';
require_once '../includes/auth_check.php';

require_role('admin');

// Handle approval/rejection
$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    check_csrf_token();
    
    $request_id = (int)$_POST['request_id'];
    $action = sanitize_input($_POST['action']);
    $admin_id = $_SESSION['user_id'];
    $admin_comments = sanitize_input(trim($_POST['admin_comments'] ?? ''));
    
    // Get deletion request
    $stmt = $conn->prepare("SELECT dr.*, p.name as product_name, p.image_path, u.username as seller_name 
                            FROM product_deletion_requests dr
                            JOIN products p ON dr.product_id = p.id
                            JOIN users u ON dr.seller_id = u.id
                            WHERE dr.id = ?");
    $stmt->bind_param("i", $request_id);
    $stmt->execute();
    $request_data = $stmt->get_result()->fetch_assoc();
    
    if ($request_data && $request_data['status'] === 'pending') {
        if ($action === 'approve') {
            // Start transaction
            $conn->begin_transaction();
            
            try {
                $product_id = $request_data['product_id'];
                
                // Update request status first
                $stmt = $conn->prepare("UPDATE product_deletion_requests SET status = 'approved', admin_id = ?, admin_comments = ?, reviewed_at = NOW() WHERE id = ?");
                $stmt->bind_param("isi", $admin_id, $admin_comments, $request_id);
                $stmt->execute();
                
                // Get image path before deleting
                $image_path = $request_data['image_path'];
                
                // Delete product (this will cascade delete the deletion request due to foreign key)
                $stmt = $conn->prepare("DELETE FROM products WHERE id = ?");
                $stmt->bind_param("i", $product_id);
                $stmt->execute();
                
                // Delete image file if exists
                if (!empty($image_path) && file_exists('../' . $image_path)) {
                    unlink('../' . $image_path);
                }
                
                $conn->commit();
                
                // Notify seller
                require_once '../includes/notifications.php';
                createNotification($request_data['seller_id'], "Your deletion request for '{$request_data['product_name']}' has been approved.", 'deletion_approved', null);
                
                $success = "Product deleted successfully!";
            } catch (Exception $e) {
                $conn->rollback();
                $error = "Failed to delete product. Please try again.";
            }
        } elseif ($action === 'reject') {
            // Update request status
            $stmt = $conn->prepare("UPDATE product_deletion_requests SET status = 'rejected', admin_id = ?, admin_comments = ?, reviewed_at = NOW() WHERE id = ?");
            $stmt->bind_param("isi", $admin_id, $admin_comments, $request_id);
            $stmt->execute();
            
            // Notify seller
            require_once '../includes/notifications.php';
            createNotification($request_data['seller_id'], "Your deletion request for '{$request_data['product_name']}' has been rejected. " . ($admin_comments ? "Reason: $admin_comments" : ""), 'deletion_rejected', $request_data['product_id']);
            
            $success = "Deletion request rejected.";
        }
            } else {
                $error = "Request not found or already processed.";
            }
            if (isset($stmt)) {
                $stmt->close();
            }
}

// Get all pending deletion requests
$deletion_requests = $conn->query("SELECT dr.*, p.name as product_name, p.price, p.category, p.image_path, u.username as seller_name 
                                   FROM product_deletion_requests dr
                                   JOIN products p ON dr.product_id = p.id
                                   JOIN users u ON dr.seller_id = u.id
                                   WHERE dr.status = 'pending'
                                   ORDER BY dr.requested_at DESC");
?>

<?php include '../includes/header.php'; ?>
<link rel="stylesheet" href="../assets/css/dashboard.css">

<style>
.request-card {
    background: #fff;
    border-radius: 12px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    margin-bottom: 20px;
    overflow: hidden;
    border: 1px solid #e0e0e0;
}

.request-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 20px;
    display: flex;
    align-items: center;
    gap: 15px;
}

.request-header img {
    width: 80px;
    height: 80px;
    border-radius: 8px;
    object-fit: cover;
    border: 3px solid rgba(255,255,255,0.3);
}

.request-header .header-content {
    flex: 1;
}

.request-header .header-content h3 {
    margin: 0 0 5px 0;
    font-size: 1.4rem;
    font-weight: 600;
}

.request-meta {
    display: flex;
    gap: 15px;
    font-size: 0.9rem;
    opacity: 0.9;
}

.request-body {
    padding: 20px;
}

.request-details {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 15px;
    margin-bottom: 20px;
}

.detail-item {
    background: #f8f9fa;
    padding: 12px 15px;
    border-radius: 6px;
    border-left: 3px solid #667eea;
}

.detail-item strong {
    color: #333;
    font-weight: 600;
}

.detail-item .value {
    color: #666;
    margin-top: 2px;
}

.reason-section {
    background: #fff3cd;
    border: 1px solid #ffeaa7;
    border-radius: 6px;
    padding: 15px;
    margin-bottom: 20px;
}

.reason-section strong {
    color: #856404;
}

.request-actions {
    background: #f8f9fa;
    padding: 20px;
    border-top: 1px solid #e0e0e0;
}

.action-form {
    display: grid;
    grid-template-columns: 1fr auto;
    gap: 15px;
    align-items: end;
}

.comments-section {
    background: white;
    padding: 15px;
    border-radius: 6px;
    border: 1px solid #ddd;
}

.action-buttons {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
}

.btn-approve {
    background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
    border: none;
    color: white;
    padding: 10px 20px;
    border-radius: 6px;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.3s ease;
}

.btn-approve:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(40, 167, 69, 0.3);
}

.btn-reject {
    background: linear-gradient(135deg, #dc3545 0%, #fd7e14 100%);
    border: none;
    color: white;
    padding: 10px 20px;
    border-radius: 6px;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.3s ease;
}

.btn-reject:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(220, 53, 69, 0.3);
}

.request-stats {
    display: flex;
    gap: 15px;
    margin-bottom: 20px;
    flex-wrap: wrap;
}

.stat-badge {
    background: #e9ecef;
    color: #495057;
    padding: 6px 12px;
    border-radius: 20px;
    font-size: 0.85rem;
    font-weight: 500;
    display: flex;
    align-items: center;
    gap: 5px;
}

.stat-badge i {
    font-size: 0.8rem;
}

@media (max-width: 768px) {
    .request-header {
        flex-direction: column;
        text-align: center;
    }

    .request-meta {
        justify-content: center;
    }

    .request-details {
        grid-template-columns: 1fr;
    }

    .action-form {
        grid-template-columns: 1fr;
        gap: 10px;
    }

    .action-buttons {
        justify-content: center;
    }
}
</style>

<main class="main-wrapper">
    <div class="container">
        <div class="page-header">
            <h1>Product Deletion Requests</h1>
            <div class="breadcrumb">Home > Admin > Deletion Requests</div>
        </div>

        <!-- Statistics -->
        <div class="request-stats">
            <div class="stat-badge">
                <i class="fas fa-clock"></i>
                <?php echo $deletion_requests ? $deletion_requests->num_rows : 0; ?> Pending Requests
            </div>
            <div class="stat-badge">
                <i class="fas fa-exclamation-triangle"></i>
                Requires Admin Review
            </div>
        </div>

        <?php if (!empty($success)): ?>
            <div class="alert alert-success" style="margin-bottom: 20px;">
                <i class="fas fa-check-circle"></i> <?php echo $success; ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($error)): ?>
            <div class="alert alert-danger" style="margin-bottom: 20px;">
                <i class="fas fa-exclamation-triangle"></i> <?php echo $error; ?>
            </div>
        <?php endif; ?>

        <?php if ($deletion_requests && $deletion_requests->num_rows > 0): ?>
            <?php while ($request = $deletion_requests->fetch_assoc()): ?>
                <div class="request-card">
                    <!-- Request Header -->
                    <div class="request-header">
                        <img src="../<?php echo htmlspecialchars($request['image_path']); ?>"
                             alt="<?php echo htmlspecialchars($request['product_name']); ?>"
                             onerror="this.src='https://via.placeholder.com/80x80?text=No+Image'">
                        <div class="header-content">
                            <h3><?php echo htmlspecialchars($request['product_name']); ?></h3>
                            <div class="request-meta">
                                <span><i class="fas fa-user"></i> <?php echo htmlspecialchars($request['seller_name']); ?></span>
                                <span><i class="fas fa-calendar"></i> <?php echo date('M j, Y', strtotime($request['requested_at'])); ?></span>
                                <span><i class="fas fa-clock"></i> <?php echo date('g:i A', strtotime($request['requested_at'])); ?></span>
                            </div>
                        </div>
                    </div>

                    <!-- Request Body -->
                    <div class="request-body">
                        <div class="request-details">
                            <div class="detail-item">
                                <strong>Price</strong><br>
                                <span class="value">₱<?php echo number_format($request['price'], 2); ?></span>
                            </div>
                            <div class="detail-item">
                                <strong>Category</strong><br>
                                <span class="value"><?php echo htmlspecialchars($request['category']); ?></span>
                            </div>
                            <div class="detail-item">
                                <strong>Status</strong><br>
                                <span class="value" style="color: #ffc107; font-weight: 600;">Pending Review</span>
                            </div>
                        </div>

                        <?php if (!empty($request['reason'])): ?>
                            <div class="reason-section">
                                <strong><i class="fas fa-comment"></i> Seller's Reason:</strong><br>
                                <?php echo nl2br(htmlspecialchars($request['reason'])); ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Request Actions -->
                    <div class="request-actions">
                        <form method="POST" class="action-form">
                            <?php echo csrf_token_field(); ?>
                            <input type="hidden" name="request_id" value="<?php echo $request['id']; ?>">

                            <div class="comments-section">
                                <label style="font-weight: 600; color: #333; margin-bottom: 8px; display: block;">
                                    <i class="fas fa-comment"></i> Admin Comments (Optional)
                                </label>
                                <textarea name="admin_comments" class="form-control" rows="2"
                                          placeholder="Add comments for the seller..."></textarea>
                            </div>

                            <div class="action-buttons">
                                <button type="submit" name="action" value="approve" class="btn-approve"
                                        onclick="return confirm('Are you sure you want to approve this deletion? The product will be permanently deleted and cannot be recovered.');">
                                    <i class="fas fa-check"></i> Approve Deletion
                                </button>
                                <button type="submit" name="action" value="reject" class="btn-reject">
                                    <i class="fas fa-times"></i> Reject Request
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <div class="content-card">
                <div class="empty-state">
                    <i class="fas fa-check-circle" style="font-size: 3rem; color: #28a745; margin-bottom: 15px;"></i>
                    <h3 style="margin: 0 0 10px 0; color: #333;">All Caught Up!</h3>
                    <p style="margin: 0; color: #666;">No pending deletion requests at this time.</p>
                </div>
            </div>
        <?php endif; ?>
    </div>
</main>

<?php include '../includes/footer.php'; ?>
