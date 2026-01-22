<?php
require_once '../config/db_config.php';
require_once '../includes/auth_check.php';

if (isset($_SESSION['user_id']) && $_SESSION['role'] === 'admin') {
    header("Location: dashboard.php");
    exit();
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf_token();
    
    $username = sanitize_input(trim($_POST['username']));
    $password = $_POST['password'];

    if (empty($username) || empty($password)) {
        $error = "Please fill all fields";
    } else {
        $sql = "SELECT id, username, password, role FROM users WHERE (username = ? OR email = ?) AND role = 'admin'";
        
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
                        $_SESSION['role'] = 'admin';
                        session_regenerate_id(true);

                        header("Location: dashboard.php");
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
    <div class="form-container">
        <h2>Admin Login</h2>
        <?php if (!empty($error)): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
            <?php echo csrf_token_field(); ?>
            <div class="form-group">
                <label>Username or Email</label>
                <input type="text" name="username" class="form-control" required>
            </div>
            
            <div class="form-group">
                <label>Password</label>
                <input type="password" name="password" class="form-control" required>
            </div>
            
            <div class="form-group">
                <button type="submit" class="btn btn-primary">Login</button>
            </div>
            
            <p><a href="../index.php">← Back to Home</a></p>
        </form>
    </div>
</div>

<?php include '../includes/footer.php'; ?>

