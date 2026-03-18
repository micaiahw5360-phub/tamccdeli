<?php
require __DIR__ . '/../middleware/staff_check.php';
require __DIR__ . '/../config/database.php';
require __DIR__ . '/../includes/csrf.php';

// Handle status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    if (!validateToken($_POST['csrf_token'])) {
        die('Invalid CSRF token');
    }
    $order_id = intval($_POST['order_id']);
    $new_status = $_POST['status'];
    $staff_id = $_SESSION['user_id'];

    if ($new_status === 'processing') {
        $stmt = $conn->prepare("UPDATE orders SET status = ?, staff_id = ? WHERE id = ? AND status = 'pending'");
        $stmt->bind_param("sii", $new_status, $staff_id, $order_id);
    } else {
        $stmt = $conn->prepare("UPDATE orders SET status = ? WHERE id = ?");
        $stmt->bind_param("si", $new_status, $order_id);
    }
    $stmt->execute();
    header("Location: orders.php");
    exit;
}

// Filter by status
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'pending';
$allowed_statuses = ['pending', 'processing', 'completed', 'cancelled'];
if (!in_array($status_filter, $allowed_statuses)) {
    $status_filter = 'pending';
}

// Fetch orders
$stmt = $conn->prepare("SELECT o.*, u.username AS customer_name, s.username AS staff_name 
                        FROM orders o 
                        JOIN users u ON o.user_id = u.id 
                        LEFT JOIN users s ON o.staff_id = s.id 
                        WHERE o.status = ? 
                        ORDER BY o.order_date DESC");
$stmt->bind_param("s", $status_filter);
$stmt->execute();
$orders = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$page_title = "Staff Orders";
include __DIR__ . '/../includes/header.php';
?>

<div class="dashboard-wrapper">
    <div class="sidebar">
        <h2>🍽️ Staff Panel</h2>
        <ul>
            <li><a href="orders.php" class="active">Orders</a></li>
            <li><a href="completed.php">Completed Orders</a></li>
            <li><a href="../menu.php">View Menu</a></li>
            <li><a href="../auth/logout.php">Logout</a></li>
        </ul>
    </div>
    <div class="main-content staff-panel">
        <h1>Order Management</h1>

        <div class="filter-tabs">
            <?php foreach ($allowed_statuses as $s): ?>
                <a href="?status=<?= $s ?>" class="btn <?= $s === $status_filter ? 'active' : '' ?>"><?= ucfirst($s) ?></a>
            <?php endforeach; ?>
        </div>

        <?php if (empty($orders)): ?>
            <p>No orders with status "<?= $status_filter ?>".</p>
        <?php else: ?>
            <div class="card">
                <table>
                    <thead>
                        <tr>
                            <th>Order #</th>
                            <th>Customer</th>
                            <th>Date</th>
                            <th>Total</th>
                            <th>Payment</th>
                            <th>Status</th>
                            <th>Staff</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($orders as $order): ?>
                        <tr>
                            <td><?= $order['id'] ?></td>
                            <td><?= htmlspecialchars($order['customer_name']) ?></td>
                            <td><?= date('M j, g:i a', strtotime($order['order_date'])) ?></td>
                            <td>$<?= number_format($order['total'], 2) ?></td>
                            <td class="status-<?= $order['payment_status'] ?>"><?= ucfirst($order['payment_status']) ?></td>
                            <td class="status status-<?= $order['status'] ?>"><?= ucfirst($order['status']) ?></td>
                            <td><?= htmlspecialchars($order['staff_name'] ?? '—') ?></td>
                            <td>
                                <a href="order-details.php?id=<?= $order['id'] ?>" class="btn-small">View</a>
                                <?php if ($order['status'] === 'pending'): ?>
                                    <form method="post" class="inline-form">
                                        <input type="hidden" name="csrf_token" value="<?= generateToken() ?>">
                                        <input type="hidden" name="order_id" value="<?= $order['id'] ?>">
                                        <input type="hidden" name="status" value="processing">
                                        <button type="submit" name="update_status" class="btn-small btn-primary">Accept</button>
                                    </form>
                                <?php elseif ($order['status'] === 'processing'): ?>
                                    <form method="post" class="inline-form">
                                        <input type="hidden" name="csrf_token" value="<?= generateToken() ?>">
                                        <input type="hidden" name="order_id" value="<?= $order['id'] ?>">
                                        <input type="hidden" name="status" value="completed">
                                        <button type="submit" name="update_status" class="btn-small btn-success">Complete</button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>