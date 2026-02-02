<?php
require_once 'config/db_config.php';

header('Content-Type: application/json');

if (isset($_GET['building_id']) && !empty($_GET['building_id'])) {
    $building_id = intval($_GET['building_id']);

    $stmt = $conn->prepare("SELECT id, name FROM rooms WHERE building_id = ? ORDER BY name");
    $stmt->bind_param("i", $building_id);
    $stmt->execute();
    $result = $stmt->get_result();

    $rooms = [];
    while ($row = $result->fetch_assoc()) {
        $rooms[] = $row;
    }

    echo json_encode($rooms);
} else {
    echo json_encode([]);
}
?>