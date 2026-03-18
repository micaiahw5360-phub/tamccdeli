<?php
require __DIR__ . '/../middleware/auth_check.php';
require __DIR__ . '/../config/database.php';

$user_id = $_SESSION['user_id'];

// Fetch user details including points
$stmt = $conn->prepare("SELECT username, role, points FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

// Fetch recent orders (last 5)
$stmt = $conn->prepare("SELECT id, total, status, order_date FROM orders WHERE user_id = ? ORDER BY order_date DESC LIMIT 5");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$recent_orders = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Fetch recent payments (last 5)
$stmt = $conn->prepare("SELECT p.*, o.id as order_id FROM payments p JOIN orders o ON p.order_id = o.id WHERE o.user_id = ? ORDER BY p.created_at DESC LIMIT 5");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$recent_payments = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get order count and total spent
$stmt = $conn->prepare("SELECT COUNT(*) as order_count, COALESCE(SUM(total),0) as total_spent FROM orders WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stats = $stmt->get_result()->fetch_assoc();

// Get loyalty points
$points = $user['points'];
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
    <!-- Sidebar -->
    <div class="sidebar">
        <h2>🍽️ TAMCC Deli</h2>
        <ul>
            <li><a href="index.php" class="active">Dashboard</a></li>
            <li><a href="orders.php">My Orders</a></li>
            <li><a href="payments.php">Payments</a></li>
            <li><a href="profile.php">Profile</a></li>
            <?php if ($user['role'] === 'admin'): ?>
                <li><a href="../admin/menu/index.php">Manage Menu</a></li>
            <?php endif; ?>
            <li><a href="../menu.php">View Menu</a></li>
            <li><a href="../auth/logout.php">Logout</a></li>
        </ul>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <div class="header-bar">
            <h1>Dashboard</h1>
            <div class="user-info">
                <span>Welcome, <strong><?php echo htmlspecialchars($user['username']); ?></strong></span>
                <a href="../auth/logout.php" class="logout-btn">Logout</a>
            </div>
        </div>

        <!-- Stats Cards -->
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
                <h3><?php echo count($recent_payments); ?></h3>
                <p>Recent Payments</p>
            </div>
            <div class="stat-card">
                <h3><?php echo $points; ?></h3>
                <p>Loyalty Points</p>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="card">
            <h3>Quick Actions</h3>
            <a href="../menu.php" class="btn">Order Food</a>
            <a href="orders.php" class="btn">View Orders</a>
            <a href="profile.php" class="btn">Update Profile</a>
        </div>

        <!-- Recent Orders -->
        <div class="card">
            <h3>Recent Orders</h3>
            <?php if (empty($recent_orders)): ?>
                <p>You haven't placed any orders yet. <a href="../menu.php">Order now!</a></p>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>Order #</th>
                            <th>Date</th>
                            <th>Total</th>
                            <th>Status</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recent_orders as $order): ?>
                        <tr>
                            <td><?php echo $order['id']; ?></td>
                            <td><?php echo date('M j, Y g:i a', strtotime($order['order_date'])); ?></td>
                            <td>$<?php echo number_format($order['total'], 2); ?></td>
                            <td class="status status-<?php echo $order['status']; ?>"><?php echo ucfirst($order['status']); ?></td>
                            <td><a href="order-details.php?id=<?php echo $order['id']; ?>" class="btn-small">View</a></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <div class="view-all">
                    <a href="orders.php">View all orders →</a>
                </div>
            <?php endif; ?>
        </div>

        <!-- Recent Payments -->
        <div class="card">
            <h3>Recent Payments</h3>
            <?php if (empty($recent_payments)): ?>
                <p>No payment records found.</p>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>Transaction</th>
                            <th>Order #</th>
                            <th>Amount</th>
                            <th>Status</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recent_payments as $payment): ?>
                        <tr>
                            <td><?php echo htmlspecialchars(substr($payment['transaction_id'], 0, 8) . '...'); ?></td>
                            <td><a href="order-details.php?id=<?php echo $payment['order_id']; ?>">#<?php echo $payment['order_id']; ?></a></td>
                            <td>$<?php echo number_format($payment['amount'], 2); ?></td>
                            <td class="status-<?php echo $payment['status']; ?>"><?php echo ucfirst($payment['status']); ?></td>
                            <td><?php echo date('M j, Y', strtotime($payment['payment_date'] ?: $payment['created_at'])); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <div class="view-all">
                    <a href="payments.php">View all payments →</a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
</body>
</html>