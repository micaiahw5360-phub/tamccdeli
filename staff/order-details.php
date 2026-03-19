<?php
require __DIR__ . '/../middleware/staff_check.php';
require __DIR__ . '/../config/database.php';
require __DIR__ . '/../includes/csrf.php';

$order_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if (!$order_id) {
    header("Location: orders.php");
    exit;
}

// Handle status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    if (!validateToken($_POST['csrf_token'])) {
        die('Invalid CSRF token');
    }

    $new_status = $_POST['update_status']; // value from button
    $staff_id = $_SESSION['user_id'];

    // Allowed status transitions
    $allowed = ['processing', 'completed', 'cancelled'];
    if (!in_array($new_status, $allowed)) {
        die('Invalid status');
    }

    if ($new_status === 'processing') {
        // Only allow processing if current status is pending
        $stmt = $conn->prepare("UPDATE orders SET status = ?, staff_id = ? WHERE id = ? AND status = 'pending'");
        $stmt->bind_param("sii", $new_status, $staff_id, $order_id);
    } else {
        $stmt = $conn->prepare("UPDATE orders SET status = ? WHERE id = ?");
        $stmt->bind_param("si", $new_status, $order_id);
    }
    $stmt->execute();
    header("Location: order-details.php?id=$order_id");
    exit;
}

// Fetch order with customer and staff info
$stmt = $conn->prepare("SELECT o.*, u.username AS customer_name, u.email AS customer_email, 
                        s.username AS staff_name 
                        FROM orders o 
                        JOIN users u ON o.user_id = u.id 
                        LEFT JOIN users s ON o.staff_id = s.id 
                        WHERE o.id = ?");
$stmt->bind_param("i", $order_id);
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

// Check if receipt exists
$stmt3 = $conn->prepare("SELECT receipt_number FROM receipts WHERE order_id = ?");
$stmt3->bind_param("i", $order_id);
$stmt3->execute();
$receipt = $stmt3->get_result()->fetch_assoc();

$page_title = "Order #$order_id";
include __DIR__ . '/../includes/header.php';
?>

<div class="dashboard-wrapper">
    <div class="sidebar">
        <h2>🍽️ Staff Panel</h2>
        <ul>
            <li><a href="orders.php">Orders</a></li>
            <li><a href="completed.php">Completed Orders</a></li>
            <li><a href="../menu.php">View Menu</a></li>
            <li><a href="../auth/logout.php">Logout</a></li>
        </ul>
    </div>
    <div class="main-content staff-panel">
        <h1>Order #<?= $order_id ?></h1>

        <!-- Order Details Card -->
        <div class="card">
            <p><strong>Customer:</strong> <?= htmlspecialchars($order['customer_name']) ?> (<?= htmlspecialchars($order['customer_email']) ?>)</p>
            <p><strong>Order Date:</strong> <?= date('F j, Y g:i a', strtotime($order['order_date'])) ?></p>
            <p><strong>Pickup Time:</strong> <?= $order['pickup_time'] ? date('M j, Y g:i a', strtotime($order['pickup_time'])) : 'Not specified' ?></p>
            <p><strong>Special Instructions:</strong> <?= nl2br(htmlspecialchars($order['special_instructions'] ?: 'None')) ?></p>
            <p><strong>Payment Method:</strong> <?= ucfirst($order['payment_method']) ?></p>
            <p><strong>Payment Status:</strong> <span class="status status-<?= $order['payment_status'] ?>"><?= ucfirst($order['payment_status']) ?></span></p>
            <p><strong>Order Status:</strong> <span class="status status-<?= $order['status'] ?>"><?= ucfirst($order['status']) ?></span></p>
            <p><strong>Assigned Staff:</strong> <?= htmlspecialchars($order['staff_name'] ?? 'Not assigned') ?></p>
            <?php if ($receipt): ?>
                <p><strong>Receipt:</strong> <a href="../receipt.php?id=<?= $order_id ?>" target="_blank"><?= htmlspecialchars($receipt['receipt_number']) ?></a></p>
            <?php endif; ?>
        </div>

        <!-- Items Card -->
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
            <p class="total">Total: $<?= number_format($order['total'], 2) ?></p>
        </div>

        <!-- Status update form -->
        <div class="card">
            <h3>Update Status</h3>
            <form method="post">
                <input type="hidden" name="csrf_token" value="<?= generateToken() ?>">
                <?php if ($order['status'] === 'pending'): ?>
                    <button type="submit" name="update_status" value="processing" class="btn">Accept Order</button>
                <?php elseif ($order['status'] === 'processing'): ?>
                    <button type="submit" name="update_status" value="completed" class="btn btn-success">Mark as Ready</button>
                <?php elseif ($order['status'] === 'completed'): ?>
                    <p>Order completed.</p>
                <?php endif; ?>
                <a href="orders.php" class="btn">Back to Orders</a>
            </form>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>