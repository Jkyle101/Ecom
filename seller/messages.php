<?php
require_once '../config/db_config.php';
require_once '../includes/auth_check.php';
require_once '../includes/messages.php';

require_login();
if ($_SESSION['role'] !== 'seller') {
    header("Location: ../buyer/dashboard.php");
    exit();
}

$seller_id = $_SESSION['user_id'];
$selected_user_id = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;

// Get conversations
$conversations = getUserConversations($seller_id);

// Get messages for selected conversation
$messages = [];
if ($selected_user_id > 0) {
    $messages = getConversation($seller_id, $selected_user_id);
    markMessagesAsRead($selected_user_id, $seller_id);
}

// Handle sending message
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_message'])) {
    check_csrf_token();
    $message = sanitize_input($_POST['message']);
    if (!empty($message) && $selected_user_id > 0) {
        sendMessage($seller_id, $selected_user_id, $message);
        header("Location: messages.php?user_id=$selected_user_id");
        exit();
    }
}
?>

<?php include '../includes/header.php'; ?>
<link rel="stylesheet" href="../assets/css/dashboard.css">

<main class="main-wrapper">
    <div class="container">
        <div class="page-header">
            <h1>Messages</h1>
        </div>
        
        <div style="display: grid; grid-template-columns: 300px 1fr; gap: 20px;">
            <!-- Conversations List -->
            <div class="content-card">
                <h3>Conversations</h3>
                <ul class="conversation-list">
                    <?php if (!empty($conversations)): ?>
                        <?php foreach ($conversations as $conv): ?>
                            <li class="conversation-item <?php echo $selected_user_id == $conv['other_user_id'] ? 'active' : ''; ?>">
                                <a href="messages.php?user_id=<?php echo $conv['other_user_id']; ?>" style="text-decoration: none; color: inherit; display: block;">
                                    <strong><?php echo htmlspecialchars($conv['username']); ?></strong>
                                    <?php if ($conv['unread_count'] > 0): ?>
                                        <span class="unread-count"><?php echo $conv['unread_count']; ?></span>
                                    <?php endif; ?>
                                    <div style="font-size: 0.9rem; color: #666;"><?php echo htmlspecialchars($conv['role']); ?></div>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <li>No conversations yet</li>
                    <?php endif; ?>
                </ul>
            </div>
            
            <!-- Messages -->
            <div class="content-card">
                <?php if ($selected_user_id > 0): ?>
                    <?php 
                    $selected_user = $conn->prepare("SELECT username FROM users WHERE id = ?");
                    $selected_user->bind_param("i", $selected_user_id);
                    $selected_user->execute();
                    $selected_user_result = $selected_user->get_result();
                    $selected_user_data = $selected_user_result->fetch_assoc();
                    ?>
                    <h3>Chat with <?php echo htmlspecialchars($selected_user_data['username']); ?></h3>
                    
                    <div class="messages-container">
                        <?php if (!empty($messages)): ?>
                            <?php foreach ($messages as $msg): ?>
                                <div class="message <?php echo $msg['sender_id'] == $seller_id ? 'sent' : 'received'; ?>">
                                    <div class="message-header">
                                        <?php echo htmlspecialchars($msg['sender_name']); ?>
                                    </div>
                                    <div class="message-content"><?php echo nl2br(htmlspecialchars($msg['message'])); ?></div>
                                    <div class="message-time"><?php echo date('M j, Y g:i A', strtotime($msg['created_at'])); ?></div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p>No messages yet. Start the conversation!</p>
                        <?php endif; ?>
                    </div>
                    
                    <form method="POST">
                        <?php echo csrf_token_field(); ?>
                        <div style="display: flex; gap: 10px;">
                            <input type="text" name="message" class="form-control" placeholder="Type your message..." required>
                            <button type="submit" name="send_message" class="btn btn-primary">Send</button>
                        </div>
                    </form>
                <?php else: ?>
                    <p>Select a conversation from the list</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</main>

<?php include '../includes/footer.php'; ?>
