<?php
require __DIR__ . '/../middleware/auth_check.php';
require __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/kiosk.php';

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
    <style>
        .dashboard-wrapper { background: var(--neutral-100); }
        .sidebar a:hover { background: var(--primary-600); transform: translateX(4px); }
        .card { transition: transform 0.2s, box-shadow 0.2s; }
        .card:hover { transform: translateY(-2px); box-shadow: var(--shadow-md); }
        .status-topup, .status-payment, .status-refund { font-weight: 600; padding: 0.25rem 0.5rem; border-radius: 20px; display: inline-block; }
        .status-topup { background: #dcfce7; color: #15803d; }
        .status-payment { background: #fee2e2; color: #b91c1c; }
        .status-refund { background: #fff3e0; color: #c2410c; }
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
            <div class="card">
                <p>No payment records found.</p>
            </div>
        <?php else: ?>
            <div class="card">
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>Description</th>
                                <th>Order #</th>
                                <th>Amount</th>
                                <th>Type</th>
                                <th>Date</th>
                            </thead>
                            <tbody>
                                <?php foreach ($transactions as $tx): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($tx['description'] ?: 'Payment') ?></td>
                                        <td><a href="<?= normal_url('order-details.php?id=' . $tx['order_id']) ?>">#<?= $tx['order_id'] ?></a></td>
                                        <td>$<?= number_format($tx['amount'], 2) ?></td>
                                        <td><span class="status-<?= $tx['type'] ?>"><?= ucfirst($tx['type']) ?></span></td>
                                        <td><?= date('M j, Y g:i a', strtotime($tx['created_at'])) ?></td>
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