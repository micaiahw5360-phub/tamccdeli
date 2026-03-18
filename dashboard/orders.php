<?php
require __DIR__ . '/../middleware/auth_check.php';
require __DIR__ . '/../config/database.php';

$user_id = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT * FROM orders WHERE user_id = ? ORDER BY order_date DESC");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$orders = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html>
<head>
    <title>My Orders | TAMCC Deli</title>
    <link rel="stylesheet" href="../assets/css/global.css">
</head>
<body>
<div class="dashboard-wrapper">
    <div class="sidebar">
        <h2>🍽️ TAMCC Deli</h2>
        <ul>
            <li><a href="index.php">Dashboard</a></li>
            <li><a href="orders.php" class="active">My Orders</a></li>
            <li><a href="payments.php">Payments</a></li>
            <li><a href="profile.php">Profile</a></li>
            <li><a href="../menu.php">View Menu</a></li>
            <li><a href="../auth/logout.php">Logout</a></li>
        </ul>
    </div>
    <div class="main-content">
        <h1>My Orders</h1>
        <?php if (empty($orders)): ?>
            <p>You haven't placed any orders yet. <a href="../menu.php">Browse menu</a>.</p>
        <?php else: ?>
            <div class="card">
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>Order #</th>
                                <th>Date</th>
                                <th>Total</th>
                                <th>Status</th>
                                <th>Actions</th>
                                <th>Receipt</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($orders as $order): ?>
                            <tr>
                                <td><?= $order['id'] ?></td>
                                <td><?= date('M j, Y g:i a', strtotime($order['order_date'])) ?></td>
                                <td>$<?= number_format($order['total'], 2) ?></td>
                                <td class="status status-<?= $order['status'] ?>"><?= ucfirst($order['status']) ?></td>
                                <td><a href="order-details.php?id=<?= $order['id'] ?>" class="btn-small">Details</a></td>
                                <td>
                                    <?php if ($order['payment_status'] === 'paid' || $order['status'] === 'completed'): ?>
                                        <a href="../receipt.php?id=<?= $order['id'] ?>" class="btn-small" style="background: var(--accent-500);">Receipt</a>
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