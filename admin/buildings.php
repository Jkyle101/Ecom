<?php
require_once '../config/db_config.php';
require_once '../includes/auth_check.php';

require_role('admin');

// Handle building actions
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_building'])) {
        // Add new building
        $building_name = trim($_POST['building_name']);
        if (!empty($building_name)) {
            $stmt = $conn->prepare("INSERT INTO buildings (name) VALUES (?)");
            $stmt->bind_param("s", $building_name);
            if ($stmt->execute()) {
                $message = "Building added successfully.";
            } else {
                $error = "Error adding building: " . $conn->error;
            }
            $stmt->close();
        } else {
            $error = "Building name cannot be empty.";
        }
    } elseif (isset($_POST['edit_building'])) {
        // Edit building
        $building_id = (int)$_POST['building_id'];
        $building_name = trim($_POST['building_name']);
        if (!empty($building_name)) {
            $stmt = $conn->prepare("UPDATE buildings SET name = ? WHERE id = ?");
            $stmt->bind_param("si", $building_name, $building_id);
            if ($stmt->execute()) {
                $message = "Building updated successfully.";
            } else {
                $error = "Error updating building: " . $conn->error;
            }
            $stmt->close();
        } else {
            $error = "Building name cannot be empty.";
        }
    } elseif (isset($_POST['delete_building'])) {
        // Delete building
        $building_id = (int)$_POST['building_id'];

        // Check if building is being used by rooms
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM rooms WHERE building_id = ?");
        $stmt->bind_param("i", $building_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $room_count = $result->fetch_assoc()['count'];
        $stmt->close();

        if ($room_count > 0) {
            $error = "Cannot delete building. It contains $room_count room(s).";
        } else {
            $stmt = $conn->prepare("DELETE FROM buildings WHERE id = ?");
            $stmt->bind_param("i", $building_id);
            if ($stmt->execute()) {
                $message = "Building deleted successfully.";
            } else {
                $error = "Error deleting building: " . $conn->error;
            }
            $stmt->close();
        }
    }
}

// Get all buildings
$buildings = $conn->query("SELECT * FROM buildings ORDER BY name");
?>

<?php include '../includes/header.php'; ?>
<link rel="stylesheet" href="../assets/css/dashboard.css">

<main class="main-wrapper">
    <div class="container">
        <div class="page-header">
            <h1>Building Management</h1>
            <div class="breadcrumb">Home > Admin > Buildings</div>
        </div>

        <?php if (!empty($message)): ?>
            <div class="alert alert-success"><?php echo $message; ?></div>
        <?php endif; ?>

        <?php if (!empty($error)): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>

        <div class="content-card">
            <div class="card-header">
                <h2>Manage Buildings</h2>
                <button class="btn btn-primary" onclick="showAddForm()">
                    <i class="fas fa-plus"></i> Add New Building
                </button>
            </div>

            <!-- Add Building Form -->
            <div id="addForm" class="form-section" style="display: none; margin-top: 20px; padding: 20px; background: #f8f9fa; border-radius: 8px;">
                <h3>Add New Building</h3>
                <form method="POST">
                    <?php echo csrf_token_field(); ?>
                    <div class="form-group">
                        <label>Building Name *</label>
                        <input type="text" name="building_name" class="form-control" required placeholder="Enter building name">
                    </div>
                    <div class="form-group">
                        <button type="submit" name="add_building" class="btn btn-success">
                            <i class="fas fa-plus"></i> Add Building
                        </button>
                        <button type="button" class="btn btn-secondary" onclick="hideAddForm()">
                            <i class="fas fa-times"></i> Cancel
                        </button>
                    </div>
                </form>
            </div>

            <!-- Buildings Table -->
            <div class="table-responsive" style="margin-top: 20px;">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Building Name</th>
                            <th>Rooms Count</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($buildings && $buildings->num_rows > 0): ?>
                            <?php while ($building = $buildings->fetch_assoc()):
                                // Get room count for this building
                                $stmt = $conn->prepare("SELECT COUNT(*) as count FROM rooms WHERE building_id = ?");
                                $stmt->bind_param("i", $building['id']);
                                $stmt->execute();
                                $result = $stmt->get_result();
                                $room_count = $result->fetch_assoc()['count'];
                                $stmt->close();
                            ?>
                                <tr>
                                    <td><?php echo $building['id']; ?></td>
                                    <td><?php echo htmlspecialchars($building['name']); ?></td>
                                    <td>
                                        <span class="badge"><?php echo $room_count; ?> rooms</span>
                                    </td>
                                    <td>
                                        <button class="btn btn-sm btn-primary" onclick="editBuilding(<?php echo $building['id']; ?>, '<?php echo htmlspecialchars($building['name']); ?>')">
                                            <i class="fas fa-edit"></i> Edit
                                        </button>
                                        <button class="btn btn-sm btn-danger" onclick="deleteBuilding(<?php echo $building['id']; ?>, '<?php echo htmlspecialchars($building['name']); ?>', <?php echo $room_count; ?>)">
                                            <i class="fas fa-trash"></i> Delete
                                        </button>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="4" class="empty-state">
                                    <i class="fas fa-building"></i>
                                    <p>No buildings found</p>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Edit Building Modal -->
        <div id="editModal" class="modal" style="display: none;">
            <div class="modal-content">
                <div class="modal-header">
                    <h3>Edit Building</h3>
                    <span class="close" onclick="closeModal()">&times;</span>
                </div>
                <form method="POST">
                    <?php echo csrf_token_field(); ?>
                    <input type="hidden" name="building_id" id="editBuildingId">
                    <div class="form-group">
                        <label>Building Name *</label>
                        <input type="text" name="building_name" id="editBuildingName" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <button type="submit" name="edit_building" class="btn btn-success">
                            <i class="fas fa-save"></i> Update Building
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

function editBuilding(id, name) {
    document.getElementById('editBuildingId').value = id;
    document.getElementById('editBuildingName').value = name;
    document.getElementById('editModal').style.display = 'block';
}

function deleteBuilding(id, name, roomCount) {
    if (roomCount > 0) {
        alert('Cannot delete "' + name + '" building. It contains ' + roomCount + ' room(s). Please remove these rooms first.');
        return;
    }

    if (confirm('Are you sure you want to delete the building "' + name + '"?')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="building_id" value="${id}">
            <input type="hidden" name="delete_building" value="1">
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