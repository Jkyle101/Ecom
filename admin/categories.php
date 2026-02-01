<?php
require_once '../config/db_config.php';
require_once '../includes/auth_check.php';

require_role('admin');

// Handle category actions
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_category'])) {
        // Add new category
        $category_name = trim($_POST['category_name']);
        if (!empty($category_name)) {
            $stmt = $conn->prepare("INSERT INTO categories (name) VALUES (?)");
            $stmt->bind_param("s", $category_name);
            if ($stmt->execute()) {
                $message = "Category added successfully.";
            } else {
                $error = "Error adding category: " . $conn->error;
            }
            $stmt->close();
        } else {
            $error = "Category name cannot be empty.";
        }
    } elseif (isset($_POST['edit_category'])) {
        // Edit category
        $category_id = (int)$_POST['category_id'];
        $category_name = trim($_POST['category_name']);
        if (!empty($category_name)) {
            $stmt = $conn->prepare("UPDATE categories SET name = ? WHERE id = ?");
            $stmt->bind_param("si", $category_name, $category_id);
            if ($stmt->execute()) {
                $message = "Category updated successfully.";
            } else {
                $error = "Error updating category: " . $conn->error;
            }
            $stmt->close();
        } else {
            $error = "Category name cannot be empty.";
        }
    } elseif (isset($_POST['delete_category'])) {
        // Delete category
        $category_id = (int)$_POST['category_id'];

        // Check if category is being used by products
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM products WHERE category = (SELECT name FROM categories WHERE id = ?)");
        $stmt->bind_param("i", $category_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $product_count = $result->fetch_assoc()['count'];
        $stmt->close();

        if ($product_count > 0) {
            $error = "Cannot delete category. It is being used by $product_count product(s).";
        } else {
            $stmt = $conn->prepare("DELETE FROM categories WHERE id = ?");
            $stmt->bind_param("i", $category_id);
            if ($stmt->execute()) {
                $message = "Category deleted successfully.";
            } else {
                $error = "Error deleting category: " . $conn->error;
            }
            $stmt->close();
        }
    }
}

// Get all categories
$categories = $conn->query("SELECT * FROM categories ORDER BY name");

// Get category usage statistics
$category_stats = [];
if ($categories) {
    while ($category = $categories->fetch_assoc()) {
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM products WHERE category = ?");
        $stmt->bind_param("s", $category['name']);
        $stmt->execute();
        $result = $stmt->get_result();
        $category['product_count'] = $result->fetch_assoc()['count'];
        $stmt->close();
        $category_stats[] = $category;
    }
}

// Reset categories result for display
$categories = $conn->query("SELECT * FROM categories ORDER BY name");
?>

<?php include '../includes/header.php'; ?>
<link rel="stylesheet" href="../assets/css/dashboard.css">

<main class="main-wrapper">
    <div class="container">
        <div class="page-header">
            <h1>Category Management</h1>
            <div class="breadcrumb">Home > Admin > Categories</div>
        </div>

        <?php if (!empty($message)): ?>
            <div class="alert alert-success"><?php echo $message; ?></div>
        <?php endif; ?>

        <?php if (!empty($error)): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>

        <div class="content-card">
            <div class="card-header">
                <h2>Manage Product Categories</h2>
                <button class="btn btn-primary" onclick="showAddForm()">
                    <i class="fas fa-plus"></i> Add New Category
                </button>
            </div>

            <!-- Add Category Form -->
            <div id="addForm" class="form-section" style="display: none; margin-top: 20px; padding: 20px; background: #f8f9fa; border-radius: 8px;">
                <h3>Add New Category</h3>
                <form method="POST">
                    <?php echo csrf_token_field(); ?>
                    <div class="form-group">
                        <label>Category Name *</label>
                        <input type="text" name="category_name" class="form-control" required placeholder="Enter category name">
                    </div>
                    <div class="form-group">
                        <button type="submit" name="add_category" class="btn btn-success">
                            <i class="fas fa-plus"></i> Add Category
                        </button>
                        <button type="button" class="btn btn-secondary" onclick="hideAddForm()">
                            <i class="fas fa-times"></i> Cancel
                        </button>
                    </div>
                </form>
            </div>

            <!-- Categories Table -->
            <div class="table-responsive" style="margin-top: 20px;">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Category Name</th>
                            <th>Products Count</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($categories && $categories->num_rows > 0): ?>
                            <?php while ($category = $categories->fetch_assoc()):
                                // Get product count for this category
                                $stmt = $conn->prepare("SELECT COUNT(*) as count FROM products WHERE category = ?");
                                $stmt->bind_param("s", $category['name']);
                                $stmt->execute();
                                $result = $stmt->get_result();
                                $product_count = $result->fetch_assoc()['count'];
                                $stmt->close();
                            ?>
                                <tr>
                                    <td><?php echo $category['id']; ?></td>
                                    <td><?php echo htmlspecialchars($category['name']); ?></td>
                                    <td>
                                        <span class="badge"><?php echo $product_count; ?> products</span>
                                    </td>
                                    <td>
                                        <button class="btn btn-sm btn-primary" onclick="editCategory(<?php echo $category['id']; ?>, '<?php echo htmlspecialchars($category['name']); ?>')">
                                            <i class="fas fa-edit"></i> Edit
                                        </button>
                                        <button class="btn btn-sm btn-danger" onclick="deleteCategory(<?php echo $category['id']; ?>, '<?php echo htmlspecialchars($category['name']); ?>', <?php echo $product_count; ?>)">
                                            <i class="fas fa-trash"></i> Delete
                                        </button>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="4" class="empty-state">
                                    <i class="fas fa-folder-open"></i>
                                    <p>No categories found</p>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Edit Category Modal -->
        <div id="editModal" class="modal" style="display: none;">
            <div class="modal-content">
                <div class="modal-header">
                    <h3>Edit Category</h3>
                    <span class="close" onclick="closeModal()">&times;</span>
                </div>
                <form method="POST">
                    <?php echo csrf_token_field(); ?>
                    <input type="hidden" name="category_id" id="editCategoryId">
                    <div class="form-group">
                        <label>Category Name *</label>
                        <input type="text" name="category_name" id="editCategoryName" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <button type="submit" name="edit_category" class="btn btn-success">
                            <i class="fas fa-save"></i> Update Category
                        </button>
                        <button type="button" class="btn btn-secondary" onclick="closeModal()">
                            <i class="fas fa-times"></i> Cancel
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</main>

<style>
.modal {
    display: none;
    position: fixed;
    z-index: 1000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0,0,0,0.5);
}

.modal-content {
    background-color: #fefefe;
    margin: 15% auto;
    padding: 0;
    border: 1px solid #888;
    width: 90%;
    max-width: 500px;
    border-radius: 8px;
}

.modal-header {
    padding: 20px;
    background: #f8f9fa;
    border-bottom: 1px solid #dee2e6;
    border-radius: 8px 8px 0 0;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.modal-header h3 {
    margin: 0;
}

.close {
    color: #aaa;
    font-size: 28px;
    font-weight: bold;
    cursor: pointer;
}

.close:hover {
    color: black;
}

.badge {
    background: #007bff;
    color: white;
    padding: 2px 8px;
    border-radius: 12px;
    font-size: 0.8rem;
}
</style>

<script>
function showAddForm() {
    document.getElementById('addForm').style.display = 'block';
}

function hideAddForm() {
    document.getElementById('addForm').style.display = 'none';
}

function editCategory(id, name) {
    document.getElementById('editCategoryId').value = id;
    document.getElementById('editCategoryName').value = name;
    document.getElementById('editModal').style.display = 'block';
}

function deleteCategory(id, name, productCount) {
    if (productCount > 0) {
        alert('Cannot delete "' + name + '" category. It contains ' + productCount + ' product(s). Please reassign or remove these products first.');
        return;
    }

    if (confirm('Are you sure you want to delete the category "' + name + '"?')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="category_id" value="${id}">
            <input type="hidden" name="delete_category" value="1">
            <?php echo csrf_token_field(); ?>
        `;
        document.body.appendChild(form);
        form.submit();
    }
}

function closeModal() {
    document.getElementById('editModal').style.display = 'none';
}

// Close modal when clicking outside
window.onclick = function(event) {
    const modal = document.getElementById('editModal');
    if (event.target == modal) {
        modal.style.display = 'none';
    }
}
</script>

<?php include '../includes/footer.php'; ?>