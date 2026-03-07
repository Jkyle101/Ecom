<?php
require_once '../config/db_config.php';
require_once '../includes/auth_check.php';

if (is_logged_in()) {
    // Redirect based on role
    if ($_SESSION['role'] == 'admin') {
        header("Location: ../admin/dashboard.php");
    } elseif ($_SESSION['role'] == 'seller') {
        header("Location: ../seller/dashboard.php");
    } else {
        header("Location: ../buyer/dashboard.php");
    }
    exit();
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf_token();
    
    $username = sanitize_input(trim($_POST['username']));
    $password = $_POST['password'];

    if (empty($username) || empty($password)) {
        $error = "Please fill all fields";
    } elseif (!preg_match('/@llcc\.edu\.ph$/i', $username)) {
        $error = "Please use your @llcc.edu.ph email address to login";
    } else {
        $sql = "SELECT id, username, password, role FROM users WHERE username = ? OR email = ?";
        
        if ($stmt = $conn->prepare($sql)) {
            $stmt->bind_param("ss", $username, $username);
            
            if ($stmt->execute()) {
                $result = $stmt->get_result();
                
                if ($result->num_rows == 1) {
                    $user = $result->fetch_assoc();
                    
                    if (password_verify($password, $user['password'])) {
                        session_start();
                        $_SESSION['user_id'] = $user['id'];
                        $_SESSION['username'] = $user['username'];
                        $_SESSION['role'] = $user['role'];
                        session_regenerate_id(true);

                        // Redirect based on role
                        if ($user['role'] == 'admin') {
                            header("Location: ../admin/dashboard.php");
                        } elseif ($user['role'] == 'seller') {
                            header("Location: ../seller/dashboard.php");
                        } else {
                            header("Location: ../buyer/dashboard.php");
                        }
                        exit();
                    } else {
                        $error = "Invalid username or password";
                    }
                } else {
                    $error = "Invalid username or password";
                }
            } else {
                $error = "Oops! Something went wrong.";
            }
            $stmt->close();
        }
    }
}
?>

<?php include '../includes/header.php'; ?>

<div class="container">
    <div class="form-container auth-form-card">
        <div class="login-logo">
            <img src="../assets/images/logo.png" alt="Platform Logo">
        </div>
        <h2 class="auth-title">Welcome Back</h2>
        <p class="auth-subtitle">Sign in to continue to your dashboard</p>
        <?php if (!empty($error)): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" data-validate class="auth-form">
            <?php echo csrf_token_field(); ?>
            <div class="form-group">
                <label>Username or Email</label>
                <input type="text" name="username" class="form-control" placeholder="name@llcc.edu.ph" required>
            </div>
            
            <div class="form-group">
                <label>Password</label>
                <div class="password-field" style="position: relative;">
                    <input type="password" name="password" id="password" class="form-control" style="padding-right: 46px;" required>
                    <button type="button" class="password-toggle" style="position: absolute; top: 50%; right: 8px; transform: translateY(-50%); width: 32px; height: 32px; border: none; background: transparent; cursor: pointer; color: #666;" onclick="togglePassword('password')">
                        <i class="fas fa-eye" id="password-icon"></i>
                    </button>
                </div>
            </div>
            
            <div class="form-group">
                <button type="submit" class="btn btn-primary auth-submit-btn">Login</button>
            </div>
            
            <p class="auth-meta">Don't have an account? <a href="register.php">Register here</a></p>
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
