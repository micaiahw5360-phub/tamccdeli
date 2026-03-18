<?php
require __DIR__ . '/../middleware/auth_check.php';
require __DIR__ . '/../config/database.php';

$user_id = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT p.*, o.id as order_id, o.total 
                        FROM payments p 
                        JOIN orders o ON p.order_id = o.id 
                        WHERE o.user_id = ? 
                        ORDER BY p.created_at DESC");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$payments = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
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
            <li><a href="index.php">Dashboard</a></li>
            <li><a href="orders.php">My Orders</a></li>
            <li><a href="payments.php" class="active">Payments</a></li>
            <li><a href="profile.php">Profile</a></li>
            <li><a href="../menu.php">View Menu</a></li>
            <li><a href="../auth/logout.php">Logout</a></li>
        </ul>
    </div>
    <div class="main-content">
        <h1>Payment History</h1>
        <?php if (empty($payments)): ?>
            <p>No payment records found.</p>
        <?php else: ?>
            <div class="card">
                <table>
                    <thead>
                        <tr>
                            <th>Transaction ID</th>
                            <th>Order #</th>
                            <th>Amount</th>
                            <th>Method</th>
                            <th>Status</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($payments as $payment): ?>
                        <tr>
                            <td><?= htmlspecialchars($payment['transaction_id']) ?></td>
                            <td><a href="order-details.php?id=<?= $payment['order_id'] ?>">#<?= $payment['order_id'] ?></a></td>
                            <td>$<?= number_format($payment['amount'], 2) ?></td>
                            <td><?= ucfirst($payment['payment_method']) ?></td>
                            <td class="status-<?= $payment['status'] ?>"><?= ucfirst($payment['status']) ?></td>
                            <td><?= date('M j, Y g:i a', strtotime($payment['payment_date'] ?: $payment['created_at'])) ?></td>
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