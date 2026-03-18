<?php
require __DIR__ . '/../middleware/auth_check.php';
require __DIR__ . '/../config/database.php';

$order_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if (!$order_id) {
    header("Location: orders.php");
    exit;
}

// Fetch order (ensure it belongs to current user)
$stmt = $conn->prepare("SELECT * FROM orders WHERE id = ? AND user_id = ?");
$stmt->bind_param("ii", $order_id, $_SESSION['user_id']);
$stmt->execute();
$order = $stmt->get_result()->fetch_assoc();
if (!$order) {
    header("Location: orders.php");
    exit;
}

// Fetch order items
$stmt2 = $conn->prepare("SELECT oi.*, mi.name FROM order_items oi JOIN menu_items mi ON oi.menu_item_id = mi.id WHERE oi.order_id = ?");
$stmt2->bind_param("i", $order_id);
$stmt2->execute();
$items = $stmt2->get_result()->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html>
<head>
    <title>Order Details | TAMCC Deli</title>
    <link rel="stylesheet" href="../assets/css/global.css">
</head>
<body>
<div class="dashboard-wrapper">
    <div class="sidebar">
        <h2>🍽️ TAMCC Deli</h2>
        <ul>
            <li><a href="index.php">Dashboard</a></li>
            <li><a href="orders.php">My Orders</a></li>
            <li><a href="payments.php">Payments</a></li>
            <li><a href="profile.php">Profile</a></li>
            <li><a href="../menu.php">View Menu</a></li>
            <li><a href="../auth/logout.php">Logout</a></li>
        </ul>
    </div>
    <div class="main-content">
        <h1>Order #<?= $order['id'] ?></h1>
        <div class="card">
            <p><strong>Date:</strong> <?= date('F j, Y g:i a', strtotime($order['order_date'])) ?></p>
            <p><strong>Status:</strong> <span class="status status-<?= $order['status'] ?>"><?= ucfirst($order['status']) ?></span></p>
            <p><strong>Pickup Time:</strong> <?= $order['pickup_time'] ? date('M j, Y g:i a', strtotime($order['pickup_time'])) : 'Not specified' ?></p>
            <p><strong>Special Instructions:</strong> <?= nl2br(htmlspecialchars($order['special_instructions'] ?: 'None')) ?></p>
            <p><strong>Payment Method:</strong> <?= ucfirst($order['payment_method']) ?></p>
            <p><strong>Payment Status:</strong> <span class="status status-<?= $order['payment_status'] ?>"><?= ucfirst($order['payment_status']) ?></span></p>
            <?php if ($order['payment_status'] === 'paid' || $order['status'] === 'completed'): ?>
                <p><a href="../receipt.php?id=<?= $order['id'] ?>" class="btn">View Receipt</a></p>
            <?php endif; ?>
        </div>
        <div class="card">
            <h3>Items</h3>
            <table>
                <thead><tr><th>Item</th><th>Quantity</th><th>Price</th><th>Subtotal</th></tr></thead>
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
            <div class="total">Total: $<?= number_format($order['total'], 2) ?></div>
        </div>
        <a href="orders.php">← Back to Orders</a>
    </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
</body>
</html>