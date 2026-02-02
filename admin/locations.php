<?php
require_once '../config/db_config.php';
require_once '../includes/auth_check.php';

require_role('admin');

// Handle building actions
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Handle building actions
    if (isset($_POST['add_building'])) {
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

    // Handle room actions
    elseif (isset($_POST['add_room'])) {
        $building_id = (int)$_POST['building_id'];
        $room_name = trim($_POST['room_name']);
        if (!empty($room_name) && $building_id > 0) {
            $stmt = $conn->prepare("INSERT INTO rooms (building_id, name) VALUES (?, ?)");
            $stmt->bind_param("is", $building_id, $room_name);
            if ($stmt->execute()) {
                $message = "Room added successfully.";
            } else {
                $error = "Error adding room: " . $conn->error;
            }
            $stmt->close();
        } else {
            $error = "Building and room name are required.";
        }
    } elseif (isset($_POST['edit_room'])) {
        $room_id = (int)$_POST['room_id'];
        $building_id = (int)$_POST['building_id'];
        $room_name = trim($_POST['room_name']);
        if (!empty($room_name) && $building_id > 0) {
            $stmt = $conn->prepare("UPDATE rooms SET building_id = ?, name = ? WHERE id = ?");
            $stmt->bind_param("isi", $building_id, $room_name, $room_id);
            if ($stmt->execute()) {
                $message = "Room updated successfully.";
            } else {
                $error = "Error updating room: " . $conn->error;
            }
            $stmt->close();
        } else {
            $error = "Building and room name are required.";
        }
    } elseif (isset($_POST['delete_room'])) {
        $room_id = (int)$_POST['room_id'];

        // Check if room is being used in orders
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM orders WHERE room_id = ?");
        $stmt->bind_param("i", $room_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $order_count = $result->fetch_assoc()['count'];
        $stmt->close();

        if ($order_count > 0) {
            $error = "Cannot delete room. It is being used in $order_count order(s).";
        } else {
            $stmt = $conn->prepare("DELETE FROM rooms WHERE id = ?");
            $stmt->bind_param("i", $room_id);
            if ($stmt->execute()) {
                $message = "Room deleted successfully.";
            } else {
                $error = "Error deleting room: " . $conn->error;
            }
            $stmt->close();
        }
    }
}

// Get all buildings
$buildings = $conn->query("SELECT * FROM buildings ORDER BY name");

// Get all rooms with building names
$rooms_query = $conn->query("
    SELECT r.*, b.name as building_name
    FROM rooms r
    JOIN buildings b ON r.building_id = b.id
    ORDER BY b.name, r.name
");
$rooms = [];
if ($rooms_query) {
    while ($row = $rooms_query->fetch_assoc()) {
        $rooms[] = $row;
    }
}
?>

<?php include '../includes/header.php'; ?>
<link rel="stylesheet" href="../assets/css/dashboard.css">

<main class="main-wrapper">
    <div class="container">
        <div class="page-header">
            <h1>Locations Management</h1>
            <div class="breadcrumb">Home > Admin > Locations</div>
        </div>

        <?php if (!empty($message)): ?>
            <div class="alert alert-success"><?php echo $message; ?></div>
        <?php endif; ?>

        <?php if (!empty($error)): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>

        <!-- Tab Navigation -->
        <div class="content-card">
            <div class="tab-navigation" style="display: flex; border-bottom: 1px solid #dee2e6; margin-bottom: 20px;">
                <button class="tab-button active" onclick="showTab('buildings')" style="padding: 10px 20px; border: none; background: none; cursor: pointer; border-bottom: 2px solid #007bff; color: #007bff; font-weight: bold;">
                    <i class="fas fa-building"></i> Buildings
                </button>
                <button class="tab-button" onclick="showTab('rooms')" style="padding: 10px 20px; border: none; background: none; cursor: pointer; color: #666;">
                    <i class="fas fa-door-open"></i> Rooms
                </button>
            </div>

            <!-- Buildings Tab -->
            <div id="buildings-tab" class="tab-content">
                <div class="card-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                    <h2 style="margin: 0;">Manage Buildings</h2>
                    <button class="btn btn-primary" onclick="showAddBuildingForm()">
                        <i class="fas fa-plus"></i> Add New Building
                    </button>
                </div>

                <!-- Add Building Form -->
                <div id="addBuildingForm" class="form-section" style="display: none; margin-bottom: 20px; padding: 20px; background: #f8f9fa; border-radius: 8px;">
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
                            <button type="button" class="btn btn-secondary" onclick="hideAddBuildingForm()">
                                <i class="fas fa-times"></i> Cancel
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Buildings Table -->
                <div class="table-responsive">
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
                            <?php if ($buildings && $buildings->num_rows > 0):
                                $buildings->data_seek(0); // Reset pointer
                                while ($building = $buildings->fetch_assoc()):
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

            <!-- Rooms Tab -->
            <div id="rooms-tab" class="tab-content" style="display: none;">
                <div class="card-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                    <h2 style="margin: 0;">Manage Rooms</h2>
                    <button class="btn btn-primary" onclick="showAddRoomForm()">
                        <i class="fas fa-plus"></i> Add New Room
                    </button>
                </div>

                <!-- Add Room Form -->
                <div id="addRoomForm" class="form-section" style="display: none; margin-bottom: 20px; padding: 20px; background: #f8f9fa; border-radius: 8px;">
                    <h3>Add New Room</h3>
                    <form method="POST">
                        <?php echo csrf_token_field(); ?>
                        <div class="form-group">
                            <label>Building *</label>
                            <select name="building_id" class="form-control" required>
                                <option value="">Select Building</option>
                                <?php if ($buildings):
                                    $buildings->data_seek(0); // Reset pointer
                                    while ($building = $buildings->fetch_assoc()): ?>
                                        <option value="<?php echo $building['id']; ?>"><?php echo htmlspecialchars($building['name']); ?></option>
                                    <?php endwhile; ?>
                                <?php endif; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Room Name *</label>
                            <input type="text" name="room_name" class="form-control" required placeholder="Enter room name">
                        </div>
                        <div class="form-group">
                            <button type="submit" name="add_room" class="btn btn-success">
                                <i class="fas fa-plus"></i> Add Room
                            </button>
                            <button type="button" class="btn btn-secondary" onclick="hideAddRoomForm()">
                                <i class="fas fa-times"></i> Cancel
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Rooms Table -->
                <div class="table-responsive">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Building</th>
                                <th>Room Name</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($rooms)): ?>
                                <?php foreach ($rooms as $room):
                                    // Get order count for this room
                                    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM orders WHERE room_id = ?");
                                    $stmt->bind_param("i", $room['id']);
                                    $stmt->execute();
                                    $result = $stmt->get_result();
                                    $order_count = $result->fetch_assoc()['count'];
                                    $stmt->close();
                                ?>
                                    <tr>
                                        <td><?php echo $room['id']; ?></td>
                                        <td><?php echo htmlspecialchars($room['building_name']); ?></td>
                                        <td><?php echo htmlspecialchars($room['name']); ?></td>
                                        <td>
                                            <button class="btn btn-sm btn-primary" onclick="editRoom(<?php echo $room['id']; ?>, <?php echo $room['building_id']; ?>, '<?php echo htmlspecialchars($room['name']); ?>')">
                                                <i class="fas fa-edit"></i> Edit
                                            </button>
                                            <button class="btn btn-sm btn-danger" onclick="deleteRoom(<?php echo $room['id']; ?>, '<?php echo htmlspecialchars($room['name']); ?>', <?php echo $order_count; ?>)">
                                                <i class="fas fa-trash"></i> Delete
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="4" class="empty-state">
                                        <i class="fas fa-door-open"></i>
                                        <p>No rooms found</p>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Edit Building Modal -->
        <div id="editBuildingModal" class="modal" style="display: none;">
            <div class="modal-content">
                <div class="modal-header">
                    <h3>Edit Building</h3>
                    <span class="close" onclick="closeModal('editBuildingModal')">&times;</span>
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
                        <button type="button" class="btn btn-secondary" onclick="closeModal('editBuildingModal')">
                            <i class="fas fa-times"></i> Cancel
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Edit Room Modal -->
        <div id="editRoomModal" class="modal" style="display: none;">
            <div class="modal-content">
                <div class="modal-header">
                    <h3>Edit Room</h3>
                    <span class="close" onclick="closeModal('editRoomModal')">&times;</span>
                </div>
                <form method="POST">
                    <?php echo csrf_token_field(); ?>
                    <input type="hidden" name="room_id" id="editRoomId">
                    <div class="form-group">
                        <label>Building *</label>
                        <select name="building_id" id="editRoomBuildingId" class="form-control" required>
                            <option value="">Select Building</option>
                            <?php if ($buildings):
                                $buildings->data_seek(0); // Reset pointer
                                while ($building = $buildings->fetch_assoc()): ?>
                                    <option value="<?php echo $building['id']; ?>"><?php echo htmlspecialchars($building['name']); ?></option>
                                <?php endwhile; ?>
                            <?php endif; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Room Name *</label>
                        <input type="text" name="room_name" id="editRoomName" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <button type="submit" name="edit_room" class="btn btn-success">
                            <i class="fas fa-save"></i> Update Room
                        </button>
                        <button type="button" class="btn btn-secondary" onclick="closeModal('editRoomModal')">
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

.tab-navigation {
    margin-bottom: 20px;
}

.tab-button.active {
    border-bottom: 2px solid #007bff !important;
    color: #007bff !important;
}
</style>

<script>
function showTab(tabName) {
    // Hide all tabs
    document.getElementById('buildings-tab').style.display = 'none';
    document.getElementById('rooms-tab').style.display = 'none';

    // Remove active class from all buttons
    document.querySelectorAll('.tab-button').forEach(btn => {
        btn.classList.remove('active');
        btn.style.borderBottom = 'none';
        btn.style.color = '#666';
    });

    // Show selected tab and activate button
    document.getElementById(tabName + '-tab').style.display = 'block';
    event.target.classList.add('active');
    event.target.style.borderBottom = '2px solid #007bff';
    event.target.style.color = '#007bff';
}

function showAddBuildingForm() {
    document.getElementById('addBuildingForm').style.display = 'block';
}

function hideAddBuildingForm() {
    document.getElementById('addBuildingForm').style.display = 'none';
}

function showAddRoomForm() {
    document.getElementById('addRoomForm').style.display = 'block';
}

function hideAddRoomForm() {
    document.getElementById('addRoomForm').style.display = 'none';
}

function editBuilding(id, name) {
    document.getElementById('editBuildingId').value = id;
    document.getElementById('editBuildingName').value = name;
    document.getElementById('editBuildingModal').style.display = 'block';
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

function editRoom(id, buildingId, name) {
    document.getElementById('editRoomId').value = id;
    document.getElementById('editRoomBuildingId').value = buildingId;
    document.getElementById('editRoomName').value = name;
    document.getElementById('editRoomModal').style.display = 'block';
}

function deleteRoom(id, name, orderCount) {
    if (orderCount > 0) {
        alert('Cannot delete "' + name + '" room. It is being used in ' + orderCount + ' order(s).');
        return;
    }

    if (confirm('Are you sure you want to delete the room "' + name + '"?')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="room_id" value="${id}">
            <input type="hidden" name="delete_room" value="1">
            <?php echo csrf_token_field(); ?>
        `;
        document.body.appendChild(form);
        form.submit();
    }
}

function closeModal(modalId) {
    document.getElementById(modalId).style.display = 'none';
}

// Close modal when clicking outside
window.onclick = function(event) {
    const modals = ['editBuildingModal', 'editRoomModal'];
    modals.forEach(modalId => {
        const modal = document.getElementById(modalId);
        if (event.target == modal) {
            modal.style.display = 'none';
        }
    });
}
</script>

<?php include '../includes/footer.php'; ?>