<?php
require __DIR__ . '/../middleware/auth_check.php';
require __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/kiosk.php';

$user_id = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT id, total, status, order_date, points_earned, points_used, payment_status FROM orders WHERE user_id = ? ORDER BY order_date DESC");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$orders = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html>
<head>
    <title>My Orders | TAMCC Deli</title>
    <link rel="stylesheet" href="../assets/css/global.css">
    <style>
        .dashboard-wrapper { background: var(--neutral-100); }
        .sidebar a:hover { background: var(--primary-600); transform: translateX(4px); }
        .card { transition: transform 0.2s, box-shadow 0.2s; }
        .card:hover { transform: translateY(-2px); box-shadow: var(--shadow-md); }
        .btn-small { border-radius: 2rem; padding: 0.25rem 0.75rem; }
        .btn-small:hover { transform: translateY(-2px); }
        .status { font-weight: 600; }
        @media (max-width: 768px) {
            th, td { padding: 0.75rem 0.5rem; font-size: 0.85rem; }
        }
    </style>
</head>
<body>
<div class="dashboard-wrapper">
    <div class="sidebar">
        <h2>🍽️ TAMCC Deli</h2>
        <ul>
            <li><a href="<?= normal_url('index.php') ?>">Dashboard</a></li>
            <li><a href="<?= normal_url('orders.php') ?>" class="active">My Orders</a></li>
            <li><a href="<?= normal_url('payments.php') ?>">Payments</a></li>
            <li><a href="<?= normal_url('profile.php') ?>">Profile</a></li>
            <li><a href="<?= kiosk_url('../menu.php') ?>">View Menu</a></li>
            <li><a href="<?= normal_url('../auth/logout.php') ?>">Logout</a></li>
        </ul>
    </div>
    <div class="main-content">
        <h1>My Orders</h1>
        <?php if (empty($orders)): ?>
            <div class="card">
                <p>You haven't placed any orders yet. <a href="<?= kiosk_url('../menu.php') ?>">Browse menu</a>.</p>
            </div>
        <?php else: ?>
            <div class="card">
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>Order #</th>
                                <th>Date</th>
                                <th>Total</th>
                                <th>Payment Status</th>
                                <th>Order Status</th>
                                <th>Points Used</th>
                                <th>Points Earned</th>
                                <th>Actions</th>
                                <th>Receipt</th>
                            </thead>
                            <tbody>
                                <?php foreach ($orders as $order): ?>
                                    <tr>
                                        <td><?= $order['id'] ?></td>
                                        <td><?= date('M j, Y g:i a', strtotime($order['order_date'])) ?></td>
                                        <td>$<?= number_format($order['total'], 2) ?></td>
                                        <td class="status status-<?= $order['payment_status'] ?>"><?= ucfirst($order['payment_status']) ?></td>
                                        <td class="status status-<?= $order['status'] ?>"><?= ucfirst($order['status']) ?></td>
                                        <td><?= $order['points_used'] ?: '—' ?></td>
                                        <td><?= $order['points_earned'] ?: '—' ?></td>
                                        <td><a href="<?= normal_url('order-details.php?id=' . $order['id']) ?>" class="btn-small">Details</a></td>
                                        <td>
                                            <?php if ($order['payment_status'] === 'paid' || $order['status'] === 'completed'): ?>
                                                <a href="<?= normal_url('../receipt.php?id=' . $order['id']) ?>" class="btn-small" style="background: var(--accent-500);">Receipt</a>
                                            <?php else: ?>
                                                <span style="color: #999;">—</span>
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
</body>
</html>