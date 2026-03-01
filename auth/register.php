<?php 
require_once '../config/db_config.php';
require_once '../includes/auth_check.php';

// Redirect if already logged in
if (is_logged_in()) {
    header("Location: ../buyer/dashboard.php"); // Default redirection
    exit();
}

$error = '';
$success = '';

// Process registration form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF token
    check_csrf_token();
    
    // Sanitize input
    $username = sanitize_input(trim($_POST['username']));
    $email = sanitize_input(trim($_POST['email']));
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $selected_role = 'buyer'; // Always set as buyer
    
    // Validate input
    if (empty($username) || empty($email) || empty($password) || empty($confirm_password)) {
        $error = "Please fill all required fields";
    } elseif ($password != $confirm_password) {
        $error = "Passwords do not match";
    } elseif (strlen($password) < 8) {
        $error = "Password must be at least 8 characters long";
} elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address";
    } elseif (!preg_match('/@llcc\.edu\.ph$/i', $email)) {
        $error = "Please use a valid @llcc.edu.ph email address";
    } else {
        // Check if username already exists
        $sql = "SELECT id FROM users WHERE username = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $stmt->store_result();
        
        if ($stmt->num_rows > 0) {
            $error = "Username already exists";
        } else {
            $stmt->close();
            
            // Check if email already exists
            $sql = "SELECT id FROM users WHERE email = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $stmt->store_result();
            
            if ($stmt->num_rows > 0) {
                $error = "Email already exists";
            } else {
                $stmt->close();
                
                // Prepare insert with chosen role (seller or buyer)
                $sql = "INSERT INTO users (username, email, password, role) VALUES (?, ?, ?, ?)";
                
                if ($stmt = $conn->prepare($sql)) {
                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                    $stmt->bind_param("ssss", $username, $email, $hashed_password, $selected_role);
                    
                    if ($stmt->execute()) {
                        $success = "Registration successful! You can now login.";
                    } else {
                        $error = "Something went wrong. Please try again later.";
                    }
                    $stmt->close();
                }
            }
        }
    }
}
?>

<?php include '../includes/header.php'; ?>

<div class="container">
    <div class="form-container">
        <h2>Register</h2>
        <?php if (!empty($error)): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <?php if (!empty($success)): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>
        
        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" data-validate>
            <?php echo csrf_token_field(); ?>
            <div class="form-group">
                <label>Username</label>
                <input type="text" name="username" class="form-control" required>
            </div>
            
            <div class="form-group">
                <label>Email (LLCC Email only)</label>
                <input type="email" name="email" class="form-control" placeholder="user@llcc.edu.ph" required>
            </div>
            
            <div class="form-group">
                <label>Password</label>
                <div style="position: relative;">
                    <input type="password" name="password" id="password" class="form-control" required minlength="8">
                    <button type="button" onclick="togglePassword('password')" style="position: absolute; right: 10px; top: 50%; transform: translateY(-50%); background: none; border: none; color: #666; cursor: pointer;">
                        <i class="fas fa-eye" id="password-icon"></i>
                    </button>
                </div>
                <small>Password must be at least 8 characters long</small>
            </div>

            <div class="form-group">
                <label>Confirm Password</label>
                <div style="position: relative;">
                    <input type="password" name="confirm_password" id="confirm_password" class="form-control" required>
                    <button type="button" onclick="togglePassword('confirm_password')" style="position: absolute; right: 10px; top: 50%; transform: translateY(-50%); background: none; border: none; color: #666; cursor: pointer;">
                        <i class="fas fa-eye" id="confirm_password-icon"></i>
                    </button>
                </div>
            </div>
            
            <div class="form-group">
                <button type="submit" class="btn btn-primary">Register</button>
            </div>
            
            <p>Already have an account? <a href="login.php">Login here</a></p>
        </form>
    </div>
</div>

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
