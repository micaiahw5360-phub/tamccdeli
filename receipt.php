<?php
session_start();
require 'config/database.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: auth/login.php");
    exit;
}

$order_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if (!$order_id) {
    header("Location: dashboard/orders.php");
    exit;
}

// Fetch order details and ensure user owns it or is admin
$stmt = $conn->prepare("SELECT o.*, u.username, u.email FROM orders o JOIN users u ON o.user_id = u.id WHERE o.id = ?");
$stmt->bind_param("i", $order_id);
$stmt->execute();
$order = $stmt->get_result()->fetch_assoc();
if (!$order || ($order['user_id'] != $_SESSION['user_id'] && $_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'staff')) {
    header("Location: dashboard/orders.php");
    exit;
}

// Fetch order items
$stmt2 = $conn->prepare("SELECT oi.*, mi.name FROM order_items oi JOIN menu_items mi ON oi.menu_item_id = mi.id WHERE oi.order_id = ?");
$stmt2->bind_param("i", $order_id);
$stmt2->execute();
$items = $stmt2->get_result()->fetch_all(MYSQLI_ASSOC);

// Fetch receipt (if exists) or create one
$stmt3 = $conn->prepare("SELECT * FROM receipts WHERE order_id = ?");
$stmt3->bind_param("i", $order_id);
$stmt3->execute();
$receipt = $stmt3->get_result()->fetch_assoc();
if (!$receipt) {
    // Generate new receipt
    $receipt_number = 'RCP-' . date('Ymd') . '-' . $order_id;
    $insert = $conn->prepare("INSERT INTO receipts (order_id, receipt_number) VALUES (?, ?)");
    $insert->bind_param("is", $order_id, $receipt_number);
    $insert->execute();
    $receipt_id = $conn->insert_id;
    // Fetch newly created
    $stmt3->execute();
    $receipt = $stmt3->get_result()->fetch_assoc();
}

$page_title = "Receipt #" . $order_id;
include 'includes/header.php';
?>

<div class="container">
    <h1>Digital Receipt</h1>
    <div class="card" style="max-width:600px; margin:0 auto;">
        <div style="text-align:center; margin-bottom:20px;">
            <h2>TAMCC Deli</h2>
            <p>T.A. Marryshow Community College</p>
        </div>
        <p><strong>Receipt #:</strong> <?= htmlspecialchars($receipt['receipt_number']) ?></p>
        <p><strong>Order #:</strong> <?= $order_id ?></p>
        <p><strong>Date:</strong> <?= date('F j, Y g:i a', strtotime($order['order_date'])) ?></p>
        <p><strong>Student:</strong> <?= htmlspecialchars($order['username']) ?> (<?= htmlspecialchars($order['email']) ?>)</p>
        <p><strong>Pickup Time:</strong> <?= $order['pickup_time'] ? date('M j, Y g:i a', strtotime($order['pickup_time'])) : 'Not specified' ?></p>
        <p><strong>Payment Method:</strong> <?= ucfirst($order['payment_method']) ?></p>
        <p><strong>Payment Status:</strong> <?= ucfirst($order['payment_status']) ?></p>
        <hr>
        <h3>Items</h3>
        <table style="width:100%; border-collapse:collapse;">
            <thead><tr><th>Item</th><th>Qty</th><th>Price</th><th>Subtotal</th></tr></thead>
            <tbody>
            <?php foreach ($items as $item): ?>
                <tr>
                    <td><?= htmlspecialchars($item['name']) ?></td>
                    <td><?= $item['quantity'] ?></td>
                    <td>$<?= number_format($item['price'], 2) ?></td>
                    <td>$<?= number_format($item['quantity'] * $item['price'], 2) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <hr>
        <p style="text-align:right; font-size:1.5rem; font-weight:bold;">Total: $<?= number_format($order['total'], 2) ?></p>
        <p style="text-align:center; margin-top:30px;"><em>Thank you for ordering! Your food will be ready for pickup at the specified time.</em></p>
        <button onclick="window.print()" class="btn">Print Receipt</button>
        <a href="<?= kiosk_url('dashboard/orders.php') ?>" class="btn">Back to Orders</a>
    </div>
</div>

<?php include 'includes/footer.php'; ?>