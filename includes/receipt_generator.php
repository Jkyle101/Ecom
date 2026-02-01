<?php
function generateReceipt($order_id, $user_id) {
    global $conn;

    // Get order details
    $stmt = $conn->prepare("SELECT * FROM orders WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $order_id, $user_id);
    $stmt->execute();
    $order = $stmt->get_result()->fetch_assoc();

    if (!$order) {
        return false;
    }

    // Get order items
    $order_items = $conn->query("SELECT oi.*, p.name, p.image_path, u.username as seller_name
                                 FROM order_items oi
                                 JOIN products p ON oi.product_id = p.id
                                 JOIN users u ON oi.seller_id = u.id
                                 WHERE oi.order_id = $order_id");

    // Get user details
    $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();

    // Convert logo to base64 for embedding in HTML
    $logo_path = '../assets/images/logo.png';
    $logo_data = '';
    if (file_exists($logo_path)) {
        $logo_data = base64_encode(file_get_contents($logo_path));
    }

    // Generate HTML receipt
    $html = '
<!DOCTYPE html>
<html>
<head>
    <title>Ecom Shop - Receipt #' . $order['id'] . '</title>
    ' . (!empty($logo_data) ? '<link rel="icon" type="image/png" href="data:image/png;base64,' . $logo_data . '">' : '') . '
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .header { text-align: center; border-bottom: 2px solid #333; padding-bottom: 20px; margin-bottom: 30px; }
        .logo { max-width: 150px; height: auto; }
        .company-info { margin: 10px 0; }
        .receipt-title { font-size: 24px; font-weight: bold; color: #333; margin: 20px 0; }
        .section { margin: 20px 0; }
        .section h3 { background: #f0f0f0; padding: 10px; margin: 0 -10px 10px -10px; border-left: 4px solid #007bff; }
        .info-table { width: 100%; border-collapse: collapse; margin: 10px 0; }
        .info-table td { padding: 5px 10px; }
        .info-table td:first-child { font-weight: bold; width: 150px; }
        .items-table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        .items-table th, .items-table td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        .items-table th { background-color: #f8f9fa; font-weight: bold; }
        .items-table td:nth-child(3), .items-table td:nth-child(4) { text-align: right; }
        .total-row { background-color: #f8f9fa; font-weight: bold; }
        .total-row td:last-child { text-align: right; font-size: 18px; }
        .footer { text-align: center; margin-top: 40px; padding-top: 20px; border-top: 1px solid #ddd; color: #666; }
        @media print { body { margin: 0; } }
    </style>
</head>
<body>
    <div class="header">
        ' . (!empty($logo_data) ? '<img src="data:image/png;base64,' . $logo_data . '" alt="Ecom Shop Logo" class="logo" style="max-width: 100px;">' : '<div class="logo-placeholder" style="width: 100px; height: 100px; background: #f0f0f0; display: inline-block; text-align: center; line-height: 100px; border: 1px solid #ddd;">LOGO</div>') . '
        <div class="company-info">
            <h1>Ecom Shop</h1>
            <p>Your trusted online marketplace</p>
            <p>Email: support@ecomshop.com | Phone: +63 123 456 7890</p>
        </div>
        <div class="receipt-title">OFFICIAL RECEIPT</div>
    </div>

    <div class="section">
        <h3>Order Information</h3>
        <table class="info-table">
            <tr><td>Order ID:</td><td>#' . $order['id'] . '</td></tr>
            <tr><td>Order Date:</td><td>' . date('M j, Y g:i A', strtotime($order['created_at'])) . '</td></tr>
            <tr><td>Payment Method:</td><td>' . htmlspecialchars($order['payment_method']) . '</td></tr>
            <tr><td>Status:</td><td>' . ucfirst($order['status']) . '</td></tr>
        </table>
    </div>

    <div class="section">
        <h3>Customer Information</h3>
        <table class="info-table">
            <tr><td>Name:</td><td>' . htmlspecialchars($user['username']) . '</td></tr>
            <tr><td>Email:</td><td>' . htmlspecialchars($user['email']) . '</td></tr>
        </table>
    </div>

    <div class="section">
        <h3>Shipping Address</h3>
        <p>' . nl2br(htmlspecialchars($order['shipping_address'])) . '</p>
    </div>

    <div class="section">
        <h3>Order Items</h3>
        <table class="items-table">
            <thead>
                <tr>
                    <th>Product</th>
                    <th style="width: 80px;">Qty</th>
                    <th style="width: 100px;">Price</th>
                    <th style="width: 100px;">Total</th>
                </tr>
            </thead>
            <tbody>';

    while ($item = $order_items->fetch_assoc()) {
        $product_name = htmlspecialchars($item['name']);
        if (strlen($product_name) > 35) {
            $product_name = substr($product_name, 0, 32) . '...';
        }

        $html .= '
                <tr>
                    <td>' . $product_name . '</td>
                    <td style="text-align: center;">' . $item['quantity'] . '</td>
                    <td style="text-align: right;">&#8369;' . number_format($item['price'], 2) . '</td>
                    <td style="text-align: right;">&#8369;' . number_format($item['price'] * $item['quantity'], 2) . '</td>
                </tr>';
    }

    $html .= '
                <tr class="total-row">
                    <td colspan="3" style="text-align: right;">Total Amount:</td>
                    <td>&#8369;' . number_format($order['total_amount'], 2) . '</td>
                </tr>
            </tbody>
        </table>
    </div>

    <div class="footer">
        <p>Thank you for shopping with Ecom Shop!</p>
        <p>This is a computer-generated receipt.</p>
        <p>Generated on: ' . date('Y-m-d H:i:s') . '</p>
    </div>
</body>
</html>';

    return $html;
}
?>
