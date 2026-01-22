<?php
// Function to create a notification
function createNotification($user_id, $message, $type = 'general', $product_id = null) {
    global $conn;
    
    $stmt = $conn->prepare("INSERT INTO notifications (user_id, product_id, message, type) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("iiss", $user_id, $product_id, $message, $type);
    $stmt->execute();
    
    return $stmt->insert_id;
}

// Function to get notifications for a user
function getUserNotifications($user_id, $limit = 20) {
    global $conn;
    
    $stmt = $conn->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT ?");
    $stmt->bind_param("ii", $user_id, $limit);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $notifications = [];
    while ($row = $result->fetch_assoc()) {
        $notifications[] = $row;
    }
    
    return $notifications;
}

// Function to count unread notifications
function countUnreadNotifications($user_id) {
    global $conn;

    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = 0");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();

    return $row['count'];
}

// Function to mark notification as read
function markNotificationAsRead($notification_id) {
    global $conn;
    
    $stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE id = ?");
    $stmt->bind_param("i", $notification_id);
    $stmt->execute();
    
    return $stmt->affected_rows;
}

// Function to display notification badge
function displayNotificationBadge($user_id) {
    $count = countUnreadNotifications($user_id);
    if ($count > 0) {
        return '<span class="notification-badge">' . $count . '</span>';
    }
    return '';
}
?>

