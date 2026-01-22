<?php
require_once '../config/db_config.php';
require_once '../includes/auth_check.php';

require_login();

$user_id = $_SESSION['user_id'];
$error = '';
$success = '';

// Get user details
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    check_csrf_token();

    $email = sanitize_input(trim($_POST['email']));
    $address = sanitize_input(trim($_POST['address'] ?? ''));
    $phone = sanitize_input(trim($_POST['phone'] ?? ''));

    if (empty($email)) {
        $error = "Email is required";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address";
    } else {
        // Check if email is taken by another user
        $stmt = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $stmt->bind_param("si", $email, $user_id);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $error = "Email already taken";
        } else {
            $profile_picture_path = $user['profile_picture'];

            // Handle profile picture upload
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
                        // Delete old profile picture if exists
                        if (!empty($user['profile_picture']) && file_exists('../' . $user['profile_picture'])) {
                            unlink('../' . $user['profile_picture']);
                        }
                        $profile_picture_path = 'uploads/profiles/' . $file_name;
                    } else {
                        $error = "Failed to upload profile picture";
                    }
                } else {
                    $error = "Invalid file type. Only JPG, JPEG, PNG, and GIF are allowed";
                }
            }

            if (empty($error)) {
                $stmt = $conn->prepare("UPDATE users SET email = ?, address = ?, phone = ?, profile_picture = ? WHERE id = ?");
                $stmt->bind_param("ssssi", $email, $address, $phone, $profile_picture_path, $user_id);

                if ($stmt->execute()) {
                    $success = "Profile updated successfully!";
                    // Refresh user data
                    $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
                    $stmt->bind_param("i", $user_id);
                    $stmt->execute();
                    $user = $stmt->get_result()->fetch_assoc();
                } else {
                    $error = "Failed to update profile";
                }
                $stmt->close();
            }
        }
    }
}

// Handle password change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    check_csrf_token();
    
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
        $error = "Please fill all password fields";
    } elseif ($new_password != $confirm_password) {
        $error = "New passwords do not match";
    } elseif (strlen($new_password) < 8) {
        $error = "Password must be at least 8 characters long";
    } elseif (!password_verify($current_password, $user['password'])) {
        $error = "Current password is incorrect";
    } else {
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
        $stmt->bind_param("si", $hashed_password, $user_id);
        
        if ($stmt->execute()) {
            $success = "Password changed successfully!";
        } else {
            $error = "Failed to change password";
        }
        $stmt->close();
    }
}
?>

<?php include '../includes/header.php'; ?>
<link rel="stylesheet" href="../assets/css/dashboard.css">

<main class="main-wrapper">
    <div class="container">
        <div class="page-header">
            <h1>Manage Account</h1>
            <div class="breadcrumb">Home > Account</div>
        </div>

        <?php if (!empty($error)): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <?php if (!empty($success)): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;" class="account-layout">
            <!-- Profile Information -->
            <div class="content-card">
                <h2>Profile Information</h2>
                <form method="POST" enctype="multipart/form-data">
                    <?php echo csrf_token_field(); ?>

                    <!-- Profile Picture Section -->
                    <div class="form-group" style="text-align: center; margin-bottom: 20px;">
                        <label>Profile Picture</label>
                        <div style="margin: 10px 0;">
                            <img src="<?php echo !empty($user['profile_picture']) ? '../' . htmlspecialchars($user['profile_picture']) : 'https://via.placeholder.com/100x100?text=No+Image'; ?>"
                                 alt="Profile Picture"
                                 style="width: 100px; height: 100px; border-radius: 50%; object-fit: cover; border: 2px solid #ddd;">
                        </div>
                        <input type="file" name="profile_picture" class="form-control" accept="image/*" style="max-width: 250px; margin: 0 auto;">
                        <small>Upload a new profile picture (JPG, PNG, GIF)</small>
                    </div>

                    <div class="form-group">
                        <label>Username</label>
                        <input type="text" class="form-control" value="<?php echo htmlspecialchars($user['username']); ?>" disabled>
                        <small>Username cannot be changed</small>
                    </div>
                    
                    <div class="form-group">
                        <label>Email *</label>
                        <input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($user['email']); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Address</label>
                        <textarea name="address" class="form-control" rows="3" placeholder="Enter your address"><?php echo htmlspecialchars($user['address'] ?? ''); ?></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label>Phone</label>
                        <input type="text" name="phone" class="form-control" value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>" placeholder="Enter your phone number">
                    </div>
                    
                    <div class="form-group">
                        <button type="submit" name="update_profile" class="btn btn-primary">Update Profile</button>
                    </div>
                </form>
            </div>
            
            <!-- Change Password -->
            <div class="content-card">
                <h2>Change Password</h2>
                <form method="POST">
                    <?php echo csrf_token_field(); ?>
                    
                    <div class="form-group">
                        <label>Current Password *</label>
                        <div style="position: relative;">
                            <input type="password" name="current_password" id="current_password" class="form-control" required>
                            <button type="button" onclick="togglePassword('current_password')" style="position: absolute; right: 10px; top: 50%; transform: translateY(-50%); background: none; border: none; color: #666; cursor: pointer;">
                                <i class="fas fa-eye" id="current_password-icon"></i>
                            </button>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>New Password *</label>
                        <div style="position: relative;">
                            <input type="password" name="new_password" id="new_password" class="form-control" required minlength="8">
                            <button type="button" onclick="togglePassword('new_password')" style="position: absolute; right: 10px; top: 50%; transform: translateY(-50%); background: none; border: none; color: #666; cursor: pointer;">
                                <i class="fas fa-eye" id="new_password-icon"></i>
                            </button>
                        </div>
                        <small>Password must be at least 8 characters long</small>
                    </div>

                    <div class="form-group">
                        <label>Confirm New Password *</label>
                        <div style="position: relative;">
                            <input type="password" name="confirm_password" id="confirm_password" class="form-control" required>
                            <button type="button" onclick="togglePassword('confirm_password')" style="position: absolute; right: 10px; top: 50%; transform: translateY(-50%); background: none; border: none; color: #666; cursor: pointer;">
                                <i class="fas fa-eye" id="confirm_password-icon"></i>
                            </button>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <button type="submit" name="change_password" class="btn btn-primary">Change Password</button>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Account Statistics -->
        <div class="content-card" style="margin-top: 20px;">
            <h2>Account Statistics</h2>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-top: 20px;">
                <?php
                // Get order count
                $order_count = $conn->query("SELECT COUNT(*) as count FROM orders WHERE user_id = $user_id")->fetch_assoc()['count'];
                
                // Get cart count
                require_once '../includes/cart.php';
                $cart_count = getCartCount($user_id);
                
                // Get product count (if user is also a seller)
                $product_count = $conn->query("SELECT COUNT(*) as count FROM products WHERE seller_id = $user_id")->fetch_assoc()['count'];
                ?>
                
                <div class="dashboard-card">
                    <strong>Total Orders</strong>
                    <div style="font-size: 2rem; margin-top: 10px; color: var(--primary-blue);"><?php echo $order_count; ?></div>
                </div>
                
                <div class="dashboard-card">
                    <strong>Items in Cart</strong>
                    <div style="font-size: 2rem; margin-top: 10px; color: var(--primary-orange);"><?php echo $cart_count; ?></div>
                </div>
                
                <?php if ($product_count > 0): ?>
                <div class="dashboard-card">
                    <strong>Products Selling</strong>
                    <div style="font-size: 2rem; margin-top: 10px; color: var(--primary-green);"><?php echo $product_count; ?></div>
                    <a href="../seller/products.php" class="btn btn-sm btn-primary" style="margin-top: 10px; display: inline-block;">Manage</a>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</main>

<script>
function togglePassword(inputId) {
    const input = document.getElementById(inputId);
    const icon = document.getElementById(inputId + '-icon');

    if (input.type === 'password') {
        input.type = 'text';
        icon.className = 'fas fa-eye-slash';
    } else {
        input.type = 'password';
        icon.className = 'fas fa-eye';
    }
}
</script>

<?php include '../includes/footer.php'; ?>
