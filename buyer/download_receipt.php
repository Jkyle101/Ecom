<?php
require_once '../config/db_config.php';
require_once '../includes/auth_check.php';
require_once '../includes/receipt_generator.php';

require_login();

$order_id = isset($_GET['order_id']) ? (int)$_GET['order_id'] : 0;
$user_id = $_SESSION['user_id'];

if ($order_id == 0) {
    header("Location: orders.php");
    exit();
}

// Generate the receipt HTML
$html = generateReceipt($order_id, $user_id);

if ($html) {
    // Set headers for HTML download/print
    $filename = "receipt_order_$order_id.html";
    header('Content-Type: text/html');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: private, max-age=0, must-revalidate');
    header('Pragma: public');

    // Output the HTML
    echo $html;
} else {
    // Order not found or access denied
    header("Location: orders.php");
    exit();
}
?>
