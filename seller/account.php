<?php
require_once '../config/db_config.php';
require_once '../includes/auth_check.php';

require_login();
if ($_SESSION['role'] !== 'seller') {
    header("Location: ../buyer/dashboard.php");
    exit();
}

$seller_id = $_SESSION['user_id'];
$user_id = $_SESSION['user_id'];
$success = '';
$error = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf_token();

    $username = sanitize_input(trim($_POST['username']));
    $email = sanitize_input(trim($_POST['email']));
    $phone = sanitize_input(trim($_POST['phone']));
    $address = sanitize_input(trim($_POST['address']));
    $gcash_number = sanitize_input(trim($_POST['gcash_number']));

    // Handle profile picture upload
    $profile_picture = '';
    if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = '../uploads/profiles/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }

        $file_extension = strtolower(pathinfo($_FILES['profile_picture']['name'], PATHINFO_EXTENSION));
        $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif'];

        if (in_array($file_extension, $allowed_extensions)) {
            $file_name = time() . '_' . uniqid() . '.' . $file_extension;
            $file_path = $upload_dir . $file_name;

            if (move_uploaded_file($_FILES['profile_picture']['tmp_name'], $file_path)) {
                $profile_picture = 'uploads/profiles/' . $file_name;

                // Delete old profile picture if exists
                $stmt = $conn->prepare("SELECT profile_picture FROM users WHERE id = ?");
                $stmt->bind_param("i", $user_id);
                $stmt->execute();
                $old_picture = $stmt->get_result()->fetch_assoc()['profile_picture'];

                if (!empty($old_picture) && file_exists('../' . $old_picture)) {
                    unlink('../' . $old_picture);
                }
            }
        }
    }

    // Validate inputs
    if (empty($username) || empty($email)) {
        $error = "Username and email are required";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address";
    } elseif (!empty($gcash_number) && !preg_match('/^[0-9]{11}$/', $gcash_number)) {
        $error = "GCash number must be 11 digits";
    } else {
        // Check if username is already taken by another user
        $stmt = $conn->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
        $stmt->bind_param("si", $username, $user_id);
        $stmt->execute();

        if ($stmt->get_result()->num_rows > 0) {
            $error = "Username is already taken";
        } else {
            $stmt->close();

            // Check if email is already taken by another user
            $stmt = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
            $stmt->bind_param("si", $email, $user_id);
            $stmt->execute();

            if ($stmt->get_result()->num_rows > 0) {
                $error = "Email is already taken";
            } else {
                $stmt->close();

                // Update user information
                if (!empty($profile_picture)) {
                    $stmt = $conn->prepare("UPDATE users SET username = ?, email = ?, phone = ?, address = ?, profile_picture = ?, gcash_number = ? WHERE id = ?");
                    $stmt->bind_param("ssssssi", $username, $email, $phone, $address, $profile_picture, $gcash_number, $user_id);
                } else {
                    $stmt = $conn->prepare("UPDATE users SET username = ?, email = ?, phone = ?, address = ?, gcash_number = ? WHERE id = ?");
                    $stmt->bind_param("sssssi", $username, $email, $phone, $address, $gcash_number, $user_id);
                }

                if ($stmt->execute()) {
                    $success = "Account updated successfully!";
                    $_SESSION['username'] = $username;
                } else {
                    $error = "Failed to update account";
                }
                $stmt->close();
            }
        }
    }
}

// Get current user data
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
?>

<?php include '../includes/header.php'; ?>
<link rel="stylesheet" href="../assets/css/dashboard.css">

<main class="main-wrapper">
    <div class="container">
        <div class="page-header">
            <h1>Account Settings</h1>
            <div class="breadcrumb">Home > Seller > Account</div>
        </div>

        <?php if (!empty($success)): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>

        <?php if (!empty($error)): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>

        <div class="content-card">
            <h2>Update Account Information</h2>

            <form method="POST" enctype="multipart/form-data">
                <?php echo csrf_token_field(); ?>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 30px; margin-bottom: 30px;">
                    <!-- Profile Picture Section -->
                    <div style="text-align: center;">
                        <div style="margin-bottom: 20px;">
                            <img src="<?php echo !empty($user['profile_picture']) ? '../' . htmlspecialchars($user['profile_picture']) : 'data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMTIwIiBoZWlnaHQ9IjEyMCIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj48cmVjdCB3aWR0aD0iMTIwIiBoZWlnaHQ9IjEyMCIgZmlsbD0iI2VlZWVlZSIvPjx0ZXh0IHg9IjYwIiB5PSI2MCIgZm9udC1mYW1pbHk9IkFyaWFsIiBmb250LXNpemU9IjEyIiBmaWxsPSIjOTk5OTk5IiB0ZXh0LWFuY2hvcj0ibWlkZGxlIiBkeT0iLjNlbSI+Tm8gSW1hZ2U8L3RleHQ+PC9zdmc+'; ?>"
                                 alt="Profile Picture"
                                 style="width:150px; height:150px; border-radius:50%; object-fit:cover; border:4px solid #ddd;">
                        </div>
                        <div class="form-group">
                            <label>Update Profile Picture</label>
                            <input type="file" name="profile_picture" class="form-control" accept="image/*">
                            <small style="color: #666;">JPG, JPEG, PNG, GIF allowed. Max file size: 5MB</small>
                        </div>
                    </div>

                    <!-- Account Details -->
                    <div>
                        <div class="form-group">
                            <label>Username *</label>
                            <input type="text" name="username" class="form-control" required
                                   value="<?php echo htmlspecialchars($user['username']); ?>">
                        </div>

                        <div class="form-group">
                            <label>Email Address *</label>
                            <input type="email" name="email" class="form-control" required
                                   value="<?php echo htmlspecialchars($user['email']); ?>">
                        </div>

                        <div class="form-group">
                            <label>Phone Number</label>
                            <input type="text" name="phone" class="form-control"
                                   value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>">
                        </div>

                        <div class="form-group">
                            <label>Address</label>
                            <textarea name="address" class="form-control" rows="3"><?php echo htmlspecialchars($user['address'] ?? ''); ?></textarea>
                        </div>
                    </div>
                </div>

                <!-- GCash Information -->
                <div style="background: #f8f9fa; padding: 20px; border-radius: 8px; margin-bottom: 30px; border-left: 4px solid #28a745;">
                    <h3 style="margin-top: 0; color: #28a745;"><i class="fas fa-mobile-alt"></i> GCash Payment Information</h3>
                    <p style="margin-bottom: 15px; color: #666;">Your GCash number is required to receive payments from buyers using GCash.</p>

                    <div class="form-group">
                        <label>GCash Number *</label>
                        <input type="text" name="gcash_number" class="form-control"
                               value="<?php echo htmlspecialchars($user['gcash_number'] ?? ''); ?>"
                               placeholder="Enter 11-digit GCash number" maxlength="11" pattern="[0-9]{11}">
                        <small style="color: #666;">Format: 09XXXXXXXXX (11 digits)</small>
                    </div>

                    <?php if (empty($user['gcash_number'])): ?>
                        <div class="alert alert-warning" style="margin-top: 10px;">
                            <i class="fas fa-exclamation-triangle"></i>
                            <strong>Important:</strong> You must provide your GCash number to receive payments from buyers.
                        </div>
                    <?php else: ?>
                        <div class="alert alert-success" style="margin-top: 10px;">
                            <i class="fas fa-check-circle"></i>
                            Your GCash number is set up for receiving payments.
                        </div>
                    <?php endif; ?>
                </div>

                <div class="form-group">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Update Account
                    </button>
                    <a href="dashboard.php" class="btn btn-secondary">Cancel</a>
                </div>
            </form>
        </div>

        <!-- Account Statistics -->
        <div class="content-card" style="margin-top: 30px;">
            <h2>Account Statistics</h2>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 20px;">
                <?php
                // Get seller statistics
                $products_count = $conn->query("SELECT COUNT(*) as count FROM products WHERE seller_id = $seller_id")->fetch_assoc()['count'];
                $orders_count = $conn->query("SELECT COUNT(DISTINCT o.id) as count FROM orders o JOIN order_items oi ON o.id = oi.order_id WHERE oi.seller_id = $seller_id")->fetch_assoc()['count'];
                $total_earnings = $conn->query("SELECT SUM(oi.price * oi.quantity) as total FROM orders o JOIN order_items oi ON o.id = oi.order_id WHERE oi.seller_id = $seller_id AND o.status = 'delivered'")->fetch_assoc()['total'] ?? 0;
                ?>

                <div class="stat-item" style="text-align: center; padding: 20px; background: #f8f9fa; border-radius: 8px;">
                    <div style="font-size: 2rem; color: #28a745; margin-bottom: 5px;">
                        <i class="fas fa-box"></i>
                    </div>
                    <div style="font-size: 1.5rem; font-weight: bold;"><?php echo $products_count; ?></div>
                    <div style="color: #666;">Products Listed</div>
                </div>

                <div class="stat-item" style="text-align: center; padding: 20px; background: #f8f9fa; border-radius: 8px;">
                    <div style="font-size: 2rem; color: #007bff; margin-bottom: 5px;">
                        <i class="fas fa-shopping-cart"></i>
                    </div>
                    <div style="font-size: 1.5rem; font-weight: bold;"><?php echo $orders_count; ?></div>
                    <div style="color: #666;">Total Orders</div>
                </div>

                <div class="stat-item" style="text-align: center; padding: 20px; background: #f8f9fa; border-radius: 8px;">
                    <div style="font-size: 2rem; color: #ffc107; margin-bottom: 5px;">
                        <i class="fas fa-peso-sign"></i>
                    </div>
                    <div style="font-size: 1.5rem; font-weight: bold;">₱<?php echo number_format($total_earnings, 2); ?></div>
                    <div style="color: #666;">Total Earnings</div>
                </div>
            </div>
        </div>
    </div>
</main>

<?php include '../includes/footer.php'; ?>
