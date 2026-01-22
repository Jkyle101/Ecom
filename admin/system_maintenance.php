<?php
require_once '../config/db_config.php';
require_once '../includes/auth_check.php';

require_role('admin');

$success = '';
$error = '';

// Handle database optimization
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['optimize'])) {
    check_csrf_token();
    $tables = ['users', 'products', 'orders', 'order_items', 'cart', 'messages', 'notifications', 'feedback'];
    foreach ($tables as $table) {
        $conn->query("OPTIMIZE TABLE $table");
    }
    $success = "Database tables optimized successfully!";
}

// Get system statistics
$db_size_query = $conn->query("SELECT 
    ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) AS 'db_size_mb'
    FROM information_schema.tables 
    WHERE table_schema = DATABASE()");
$db_size = $db_size_query->fetch_assoc()['db_size_mb'] ?? 0;

$total_tables = $conn->query("SELECT COUNT(*) as count FROM information_schema.tables WHERE table_schema = DATABASE()")->fetch_assoc()['count'];
?>

<?php include '../includes/header.php'; ?>
<link rel="stylesheet" href="../assets/css/dashboard.css">

<main class="main-wrapper">
    <div class="container">
        <div class="page-header">
            <h1>System Maintenance</h1>
            <div class="breadcrumb">Home > Admin > System Maintenance</div>
        </div>

        <?php if (!empty($success)): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>
        
        <?php if (!empty($error)): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>

        <!-- System Information -->
        <div class="content-card">
            <h2>System Information</h2>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin-top: 20px;">
                <div class="dashboard-card">
                    <strong>Database Size</strong>
                    <div style="font-size: 1.5rem; margin-top: 10px; color: var(--primary-blue);"><?php echo $db_size; ?> MB</div>
                </div>
                
                <div class="dashboard-card">
                    <strong>Total Tables</strong>
                    <div style="font-size: 1.5rem; margin-top: 10px; color: var(--primary-green);"><?php echo $total_tables; ?></div>
                </div>
                
                <div class="dashboard-card">
                    <strong>PHP Version</strong>
                    <div style="font-size: 1.5rem; margin-top: 10px; color: var(--primary-orange);"><?php echo phpversion(); ?></div>
                </div>
                
                <div class="dashboard-card">
                    <strong>MySQL Version</strong>
                    <div style="font-size: 1.5rem; margin-top: 10px; color: var(--primary-yellow);"><?php echo $conn->server_info; ?></div>
                </div>
            </div>
        </div>

        <!-- Maintenance Tools -->
        <div class="content-card" style="margin-top: 20px;">
            <h2>Maintenance Tools</h2>
            <div style="margin-top: 20px;">
                <form method="POST" onsubmit="return confirm('This will optimize all database tables. Continue?');">
                    <?php echo csrf_token_field(); ?>
                    <button type="submit" name="optimize" class="btn btn-primary">
                        <i class="fas fa-database"></i> Optimize Database
                    </button>
                    <small style="display: block; margin-top: 10px; color: #666;">
                        This will optimize all database tables to improve performance
                    </small>
                </form>
            </div>
        </div>

        <!-- System Settings -->
        <div class="content-card" style="margin-top: 20px;">
            <h2>System Settings</h2>
            <div style="margin-top: 20px;">
                <p><strong>Maintenance Mode:</strong> Off</p>
                <p><strong>Debug Mode:</strong> Off</p>
                <p><strong>Email Notifications:</strong> Enabled</p>
                <p><strong>Auto-approve Products:</strong> Disabled</p>
            </div>
        </div>
    </div>
</main>

<?php include '../includes/footer.php'; ?>

