<?php
require __DIR__ . '/../middleware/auth_check.php';
require __DIR__ . '/../config/database.php';
require __DIR__ . '/../includes/kiosk.php';
require __DIR__ . '/../includes/functions.php';

$user_id = $_SESSION['user_id'];

// Fetch user details including points and profile photo
$stmt = $conn->prepare("SELECT username, role, points, profile_photo, bio FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

// Fetch recent orders (last 5) with points earned/used
$stmt = $conn->prepare("SELECT id, total, status, order_date, points_earned, points_used FROM orders WHERE user_id = ? ORDER BY order_date DESC LIMIT 5");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$recent_orders = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Fetch recent transactions (last 5)
$stmt = $conn->prepare("SELECT t.*, o.id as order_id FROM transactions t JOIN orders o ON t.order_id = o.id WHERE o.user_id = ? ORDER BY t.created_at DESC LIMIT 5");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$recent_transactions = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get order count and total spent
$stmt = $conn->prepare("SELECT COUNT(*) as order_count, COALESCE(SUM(total),0) as total_spent FROM orders WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stats = $stmt->get_result()->fetch_assoc();

$points = $user['points'];
$profile_photo = $user['profile_photo'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard | TAMCC Deli</title>
    <link rel="stylesheet" href="../assets/css/global.css">
</head>
<body>
<div class="dashboard-wrapper">
    <div class="sidebar">
        <h2>🍽️ TAMCC Deli</h2>
        <ul>
            <li><a href="<?= normal_url('index.php') ?>" class="active">Dashboard</a></li>
            <li><a href="<?= normal_url('orders.php') ?>">My Orders</a></li>
            <li><a href="<?= normal_url('payments.php') ?>">Payments</a></li>
            <li><a href="<?= normal_url('profile.php') ?>">Profile</a></li>
            <li><a href="<?= kiosk_url('../menu.php') ?>">View Menu</a></li>
            <li><a href="<?= normal_url('../auth/logout.php') ?>">Logout</a></li>
        </ul>
    </div>

    <div class="main-content">
        <div class="header-bar">
            <div style="display: flex; align-items: center; gap: 1rem;">
                <?php if ($profile_photo): ?>
                    <img src="<?= htmlspecialchars($profile_photo) ?>" alt="Profile" style="width: 50px; height: 50px; border-radius: 50%; object-fit: cover;">
                <?php else: ?>
                    <div style="width: 50px; height: 50px; background: var(--neutral-200); border-radius: 50%; display: flex; align-items: center; justify-content: center;">👤</div>
                <?php endif; ?>
                <h1>Dashboard</h1>
            </div>
            <div class="user-info">
                <span>Welcome, <strong><?php echo htmlspecialchars($user['username']); ?></strong></span>
                <a href="<?= normal_url('../auth/logout.php') ?>" class="logout-btn">Logout</a>
            </div>
        </div>

        <div class="stats-grid">
            <div class="stat-card">
                <h3><?php echo $stats['order_count']; ?></h3>
                <p>Total Orders</p>
            </div>
            <div class="stat-card">
                <h3>$<?php echo number_format($stats['total_spent'], 2); ?></h3>
                <p>Total Spent</p>
            </div>
            <div class="stat-card">
                <h3><?php echo count($recent_transactions); ?></h3>
                <p>Recent Payments</p>
            </div>
            <div class="stat-card">
                <h3><?php echo $points; ?></h3>
                <p>Loyalty Points</p>
                <small><?= floor($points / 100) ?> points = $<?= number_format(floor($points / 100), 2) ?> discount</small>
            </div>
        </div>

        <div class="card">
            <h3>Quick Actions</h3>
            <a href="<?= kiosk_url('../menu.php') ?>" class="btn">Order Food</a>
            <a href="<?= normal_url('orders.php') ?>" class="btn">View Orders</a>
            <a href="<?= normal_url('profile.php') ?>" class="btn">Update Profile</a>
        </div>

        <div class="card">
            <h3>Recent Orders</h3>
            <?php if (empty($recent_orders)): ?>
                <p>You haven't placed any orders yet. <a href="<?= kiosk_url('../menu.php') ?>">Order now!</a></p>
            <?php else: ?>
                <div class="table-responsive">
                    表表格
                        <thead>
                            <tr>
                                <th>Order #</th>
                                <th>Date</th>
                                <th>Total</th>
                                <th>Status</th>
                                <th>Points Used</th>
                                <th>Points Earned</th>
                                <th></th>
                            </thead>
                            <tbody>
                                <?php foreach ($recent_orders as $order): ?>
                                <tr>
                                    <td><?php echo $order['id']; ?></td>
                                    <td><?php echo date('M j, Y g:i a', strtotime($order['order_date'])); ?></td>
                                    <td>$<?php echo number_format($order['total'], 2); ?></td>
                                    <td class="status status-<?php echo $order['status']; ?>"><?php echo ucfirst($order['status']); ?></td>
                                    <td><?php echo $order['points_used'] ?: '—'; ?></td>
                                    <td><?php echo $order['points_earned'] ?: '—'; ?></td>
                                    <td><a href="<?= normal_url('order-details.php?id=' . $order['id']) ?>" class="btn-small">View</a></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                    表表格
                </div>
                <div class="view-all">
                    <a href="<?= normal_url('orders.php') ?>">View all orders →</a>
                </div>
            <?php endif; ?>
        </div>

        <div class="card">
            <h3>Recent Payments</h3>
            <?php if (empty($recent_transactions)): ?>
                <p>No payment records found.</p>
            <?php else: ?>
                <div class="table-responsive">
                    表表格
                        <thead>
                            <tr>
                                <th>Transaction</th>
                                <th>Order #</th>
                                <th>Amount</th>
                                <th>Type</th>
                                <th>Date</th>
                            </thead>
                            <tbody>
                                <?php foreach ($recent_transactions as $tx): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars(substr($tx['description'], 0, 20) . '...'); ?></td>
                                    <td><a href="<?= normal_url('order-details.php?id=' . $tx['order_id']) ?>">#<?php echo $tx['order_id']; ?></a></td>
                                    <td>$<?php echo number_format($tx['amount'], 2); ?></td>
                                    <td class="status-<?php echo $tx['type']; ?>"><?php echo ucfirst($tx['type']); ?></td>
                                    <td><?php echo date('M j, Y', strtotime($tx['created_at'])); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                    表表格
                </div>
                <div class="view-all">
                    <a href="<?= normal_url('payments.php') ?>">View all payments →</a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
</body>
</html>