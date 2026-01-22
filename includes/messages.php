<?php
// Function to send a message
function sendMessage($sender_id, $receiver_id, $message, $product_id = null) {
    global $conn;
    
    $stmt = $conn->prepare("INSERT INTO messages (sender_id, receiver_id, product_id, message) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("iiis", $sender_id, $receiver_id, $product_id, $message);
    $stmt->execute();
    
    return $stmt->insert_id;
}

// Function to get conversation between two users
function getConversation($user1_id, $user2_id, $limit = 50) {
    global $conn;
    
    $stmt = $conn->prepare("SELECT m.*, 
                           u_sender.username as sender_name, 
                           u_receiver.username as receiver_name,
                           p.name as product_name
                           FROM messages m 
                           JOIN users u_sender ON m.sender_id = u_sender.id
                           JOIN users u_receiver ON m.receiver_id = u_receiver.id
                           LEFT JOIN products p ON m.product_id = p.id
                           WHERE (m.sender_id = ? AND m.receiver_id = ?) 
                              OR (m.sender_id = ? AND m.receiver_id = ?)
                           ORDER BY m.created_at ASC
                           LIMIT ?");
    $stmt->bind_param("iiiii", $user1_id, $user2_id, $user2_id, $user1_id, $limit);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $messages = [];
    while ($row = $result->fetch_assoc()) {
        $messages[] = $row;
    }
    
    return $messages;
}

// Function to get all conversations for a user
function getUserConversations($user_id) {
    global $conn;
    
    // Get unique users this user has conversations with
    $stmt = $conn->prepare("SELECT 
                           DISTINCT IF(sender_id = ?, receiver_id, sender_id) as other_user_id,
                           (SELECT username FROM users WHERE id = other_user_id) as username,
                           (SELECT role FROM users WHERE id = other_user_id) as role,
                           (SELECT MAX(created_at) FROM messages 
                            WHERE (sender_id = ? AND receiver_id = other_user_id) 
                               OR (sender_id = other_user_id AND receiver_id = ?)) as last_message_time,
                           (SELECT COUNT(*) FROM messages 
                            WHERE receiver_id = ? AND sender_id = other_user_id AND is_read = 0) as unread_count
                           FROM messages
                           WHERE sender_id = ? OR receiver_id = ?
                           ORDER BY last_message_time DESC");
    $stmt->bind_param("iiiiii", $user_id, $user_id, $user_id, $user_id, $user_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $conversations = [];
    while ($row = $result->fetch_assoc()) {
        $conversations[] = $row;
    }
    
    return $conversations;
}

// Function to mark messages as read
function markMessagesAsRead($sender_id, $receiver_id) {
    global $conn;
    
    $stmt = $conn->prepare("UPDATE messages SET is_read = 1 
                           WHERE sender_id = ? AND receiver_id = ? AND is_read = 0");
    $stmt->bind_param("ii", $sender_id, $receiver_id);
    $stmt->execute();
    
    return $stmt->affected_rows;
}

// Function to count unread messages
function countUnreadMessages($user_id) {
    global $conn;

    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM messages WHERE receiver_id = ? AND is_read = 0");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();

    return $row['count'];
}

// Function to get unread messages count for a user
function getUnreadMessagesCount($user_id) {
    global $conn;

    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM messages WHERE receiver_id = ? AND is_read = 0");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();

    return $row['count'];
}

// Function to display message badge
function displayMessageBadge($user_id) {
    $count = countUnreadMessages($user_id);
    if ($count > 0) {
        return '<span class="message-badge">' . $count . '</span>';
    }
    return '';
}
?>

