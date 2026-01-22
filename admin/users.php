<?php
require_once '../config/db_config.php';
require_once '../includes/auth_check.php';

require_role('admin');

$search_query = isset($_GET['search']) ? sanitize_input($_GET['search']) : '';
$role_filter = isset($_GET['role']) ? sanitize_input($_GET['role']) : '';

// Build query
$sql = "SELECT * FROM users WHERE 1=1";
$params = [];
$types = '';

if (!empty($search_query)) {
    $sql .= " AND (username LIKE ? OR email LIKE ?)";
    $search_term = "%$search_query%";
    $params[] = $search_term;
    $params[] = $search_term;
    $types .= 'ss';
}

if (!empty($role_filter)) {
    $sql .= " AND role = ?";
    $params[] = $role_filter;
    $types .= 's';
}

$sql .= " ORDER BY created_at DESC";

$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$users = $stmt->get_result();

// Handle user actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    check_csrf_token();
    $action = sanitize_input($_POST['action']);
    $user_id = (int)$_POST['user_id'];
    
    if ($action === 'delete' && $user_id != $_SESSION['user_id']) {
        $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        header("Location: users.php");
        exit();
    }
}
?>

<?php include '../includes/header.php'; ?>
<link rel="stylesheet" href="../assets/css/dashboard.css">

<main class="main-wrapper">
    <div class="container">
        <div class="page-header">
            <h1>Manage Users</h1>
            <div class="breadcrumb">Home > Admin > Manage Users</div>
        </div>

        <!-- Search and Filter -->
        <div class="content-card">
            <form method="GET" style="display: flex; gap: 10px; flex-wrap: wrap;">
                <input type="text" name="search" class="form-control" placeholder="Search by username or email..." 
                       value="<?php echo htmlspecialchars($search_query); ?>" style="flex: 1; min-width: 200px;">
                <select name="role" class="form-control" style="width: 150px;">
                    <option value="">All Roles</option>
                    <option value="buyer" <?php echo $role_filter === 'buyer' ? 'selected' : ''; ?>>Buyer</option>
                    <option value="seller" <?php echo $role_filter === 'seller' ? 'selected' : ''; ?>>Seller</option>
                    <option value="admin" <?php echo $role_filter === 'admin' ? 'selected' : ''; ?>>Admin</option>
                </select>
                <button type="submit" class="btn btn-primary">Search</button>
                <a href="users.php" class="btn btn-secondary">Clear</a>
            </form>
        </div>

        <!-- Users Table -->
        <div class="content-card">
            <table style="width: 100%; border-collapse: collapse;">
                <thead>
                    <tr style="border-bottom: 2px solid #ddd;">
                        <th style="padding: 12px; text-align: left;">ID</th>
                        <th style="padding: 12px; text-align: left;">Username</th>
                        <th style="padding: 12px; text-align: left;">Email</th>
                        <th style="padding: 12px; text-align: left;">Role</th>
                        <th style="padding: 12px; text-align: left;">Joined</th>
                        <th style="padding: 12px; text-align: left;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($users && $users->num_rows > 0): ?>
                        <?php while ($user = $users->fetch_assoc()): ?>
                            <tr style="border-bottom: 1px solid #eee;">
                                <td style="padding: 12px;"><?php echo $user['id']; ?></td>
                                <td style="padding: 12px;"><?php echo htmlspecialchars($user['username']); ?></td>
                                <td style="padding: 12px;"><?php echo htmlspecialchars($user['email']); ?></td>
                                <td style="padding: 12px;">
                                    <span class="product-category"><?php echo ucfirst($user['role']); ?></span>
                                </td>
                                <td style="padding: 12px;"><?php echo date('M j, Y', strtotime($user['created_at'])); ?></td>
                                <td style="padding: 12px;">
                                    <?php if ($user['id'] != $_SESSION['user_id']): ?>
                                        <form method="POST" style="display: inline-block;" onsubmit="return confirm('Are you sure you want to delete this user?');">
                                            <?php echo csrf_token_field(); ?>
                                            <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                            <input type="hidden" name="action" value="delete">
                                            <button type="submit" class="btn btn-sm btn-secondary">Delete</button>
                                        </form>
                                    <?php else: ?>
                                        <span style="color: #999;">Current User</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" style="padding: 40px; text-align: center;">No users found</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</main>

<?php include '../includes/footer.php'; ?>

