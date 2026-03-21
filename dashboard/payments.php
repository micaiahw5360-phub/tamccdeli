<?php
require __DIR__ . '/../middleware/auth_check.php';
require __DIR__ . '/../config/database.php';
require __DIR__ . '/../includes/kiosk.php';

$user_id = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT t.*, o.id as order_id, o.total 
                        FROM transactions t 
                        JOIN orders o ON t.order_id = o.id 
                        WHERE o.user_id = ? 
                        ORDER BY t.created_at DESC");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$transactions = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html>
<head>
    <title>Payment History | TAMCC Deli</title>
    <link rel="stylesheet" href="../assets/css/global.css">
</head>
<body>
<div class="dashboard-wrapper">
    <div class="sidebar">
        <h2>🍽️ TAMCC Deli</h2>
        <ul>
            <li><a href="<?= normal_url('index.php') ?>">Dashboard</a></li>
            <li><a href="<?= normal_url('orders.php') ?>">My Orders</a></li>
            <li><a href="<?= normal_url('payments.php') ?>" class="active">Payments</a></li>
            <li><a href="<?= normal_url('profile.php') ?>">Profile</a></li>
            <li><a href="<?= kiosk_url('../menu.php') ?>">View Menu</a></li>
            <li><a href="<?= normal_url('../auth/logout.php') ?>">Logout</a></li>
        </ul>
    </div>
    <div class="main-content">
        <h1>Payment History</h1>
        <?php if (empty($transactions)): ?>
            <p>No payment records found.</p>
        <?php else: ?>
            <div class="card">
                 <table>
                    <thead>
                         <tr><th>Description</th><th>Order #</th><th>Amount</th><th>Type</th><th>Date</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($transactions as $tx): ?>
                         <tr>
                            <td><?= htmlspecialchars($tx['description'] ?: 'Payment') ?></td>
                            <td><a href="<?= normal_url('order-details.php?id=' . $tx['order_id']) ?>">#<?= $tx['order_id'] ?></a></td>
                            <td>$<?= number_format($tx['amount'], 2) ?></td>
                            <td class="status-<?= $tx['type'] ?>"><?= ucfirst($tx['type']) ?></td>
                            <td><?= date('M j, Y g:i a', strtotime($tx['created_at'])) ?></td>
                         </tr>
                        <?php endforeach; ?>
                    </tbody>
                 </table>
            </div>
        <?php endif; ?>
    </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
</body>
</html>