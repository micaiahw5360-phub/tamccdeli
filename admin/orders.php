<?php
require __DIR__ . '/../middleware/admin_check.php';
require __DIR__ . '/../config/database.php';
require __DIR__ . '/../includes/csrf.php';

// Handle status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    if (!validateToken($_POST['csrf_token'])) {
        die('Invalid CSRF token');
    }
    $order_id = intval($_POST['order_id']);
    $new_status = $_POST['status'];
    $staff_id = $_POST['staff_id'] ?? null;

    if ($new_status === 'processing' && $staff_id) {
        $stmt = $conn->prepare("UPDATE orders SET status = ?, staff_id = ? WHERE id = ?");
        $stmt->bind_param("sii", $new_status, $staff_id, $order_id);
    } else {
        $stmt = $conn->prepare("UPDATE orders SET status = ? WHERE id = ?");
        $stmt->bind_param("si", $new_status, $order_id);
    }
    $stmt->execute();
    
    $redirect = "orders.php";
    if (isset($_SESSION['kiosk_mode']) && $_SESSION['kiosk_mode']) {
        $redirect .= '?kiosk=1';
    }
    header("Location: $redirect");
    exit;
}

$status_filter = isset($_GET['status']) ? $_GET['status'] : 'all';
$allowed_statuses = ['all', 'pending', 'processing', 'completed', 'cancelled'];

// Build query
if ($status_filter === 'all') {
    $stmt = $conn->prepare("SELECT o.*, u.username AS customer_name, s.username AS staff_name 
                            FROM orders o 
                            JOIN users u ON o.user_id = u.id 
                            LEFT JOIN users s ON o.staff_id = s.id 
                            ORDER BY o.order_date DESC");
} else {
    $stmt = $conn->prepare("SELECT o.*, u.username AS customer_name, s.username AS staff_name 
                            FROM orders o 
                            JOIN users u ON o.user_id = u.id 
                            LEFT JOIN users s ON o.staff_id = s.id 
                            WHERE o.status = ? 
                            ORDER BY o.order_date DESC");
    $stmt->bind_param("s", $status_filter);
}
$stmt->execute();
$orders = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get staff list for assignment (use prepared statement for consistency)
$stmt = $conn->prepare("SELECT id, username FROM users WHERE role IN ('admin', 'staff')");
$stmt->execute();
$staff_list = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$page_title = "Manage Orders";
include __DIR__ . '/../includes/header.php';
?>

<div class="dashboard-wrapper">
    <div class="sidebar">
        <h2>⚙️ Admin Panel</h2>
        <ul>
            <li><a href="<?= normal_url('index.php') ?>">Dashboard</a></li>
            <li><a href="<?= normal_url('menu/index.php') ?>">Manage Menu</a></li>
            <li><a href="<?= normal_url('orders.php') ?>" class="active">Manage Orders</a></li>
            <li><a href="<?= normal_url('users.php') ?>">Manage Users</a></li>
            <li><a href="<?= kiosk_url('../menu.php') ?>">View Site</a></li>
            <li><a href="<?= normal_url('../auth/logout.php') ?>">Logout</a></li>
        </ul>
    </div>
    <div class="main-content admin-panel">
        <h1>Manage Orders</h1>

        <div class="filter-tabs">
            <a href="?status=all" class="btn <?= $status_filter === 'all' ? 'active' : '' ?>">All</a>
            <?php foreach (['pending', 'processing', 'completed', 'cancelled'] as $s): ?>
                <a href="?status=<?= $s ?>" class="btn <?= $s === $status_filter ? 'active' : '' ?>"><?= ucfirst($s) ?></a>
            <?php endforeach; ?>
        </div>

        <?php if (empty($orders)): ?>
            <p>No orders found.</p>
        <?php else: ?>
            <div class="card">
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>Order #</th>
                                <th>Customer</th>
                                <th>Date</th>
                                <th>Total</th>
                                <th>Payment</th>
                                <th>Status</th>
                                <th>Assigned Staff</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($orders as $order): ?>
                            <tr>
                                <td><?= $order['id'] ?></td>
                                <td><?= htmlspecialchars($order['customer_name']) ?></td>
                                <td><?= date('M j, Y', strtotime($order['order_date'])) ?></td>
                                <td>$<?= number_format($order['total'], 2) ?></td>
                                <td class="status-<?= $order['payment_status'] ?>"><?= ucfirst($order['payment_status']) ?></td>
                                <td class="status status-<?= $order['status'] ?>"><?= ucfirst($order['status']) ?></td>
                                <td>
                                    <?php if ($order['status'] === 'pending'): ?>
                                        <form method="post" class="inline-form">
                                            <input type="hidden" name="csrf_token" value="<?= generateToken() ?>">
                                            <input type="hidden" name="order_id" value="<?= $order['id'] ?>">
                                            <input type="hidden" name="status" value="processing">
                                            <select name="staff_id" required>
                                                <option value="">Assign to...</option>
                                                <?php foreach ($staff_list as $staff): ?>
                                                    <option value="<?= $staff['id'] ?>"><?= htmlspecialchars($staff['username']) ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                            <button type="submit" name="update_status" class="btn-small">Accept & Assign</button>
                                        </form>
                                    <?php else: ?>
                                        <?= htmlspecialchars($order['staff_name'] ?? '—') ?>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <a href="<?= normal_url('../staff/order-details.php?id=' . $order['id']) ?>" class="btn-small">View</a>
                                    <?php if ($order['status'] === 'processing'): ?>
                                        <form method="post" class="inline-form">
                                            <input type="hidden" name="csrf_token" value="<?= generateToken() ?>">
                                            <input type="hidden" name="order_id" value="<?= $order['id'] ?>">
                                            <input type="hidden" name="status" value="completed">
                                            <button type="submit" name="update_status" class="btn-small btn-success">Complete</button>
                                        </form>
                                    <?php endif; ?>
                                    <?php if ($order['status'] !== 'cancelled'): ?>
                                        <form method="post" class="inline-form" onsubmit="return confirm('Cancel this order?');">
                                            <input type="hidden" name="csrf_token" value="<?= generateToken() ?>">
                                            <input type="hidden" name="order_id" value="<?= $order['id'] ?>">
                                            <input type="hidden" name="status" value="cancelled">
                                            <button type="submit" name="update_status" class="btn-small btn-danger">Cancel</button>
                                        </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>