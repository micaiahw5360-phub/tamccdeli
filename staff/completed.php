<?php
require __DIR__ . '/../middleware/staff_check.php';
require __DIR__ . '/../config/database.php';

// Fetch completed orders
$stmt = $conn->prepare("SELECT o.*, u.username AS customer_name, s.username AS staff_name 
                        FROM orders o 
                        JOIN users u ON o.user_id = u.id 
                        LEFT JOIN users s ON o.staff_id = s.id 
                        WHERE o.status = 'completed' 
                        ORDER BY o.order_date DESC");
$stmt->execute();
$orders = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$page_title = "Completed Orders";
include __DIR__ . '/../includes/header.php';
?>

<div class="dashboard-wrapper">
    <div class="sidebar">
        <h2>🍽️ Staff Panel</h2>
        <ul>
            <li><a href="<?= normal_url('orders.php') ?>">Orders</a></li>
            <li><a href="<?= normal_url('completed.php') ?>" class="active">Completed Orders</a></li>
            <li><a href="<?= normal_url('../menu.php') ?>">View Menu</a></li>
            <li><a href="<?= normal_url('../auth/logout.php') ?>">Logout</a></li>
        </ul>
    </div>
    <div class="main-content staff-panel">
        <h1>Completed Orders</h1>
        <?php if (empty($orders)): ?>
            <p>No completed orders yet.</p>
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
                            <th>Staff</th>
                            <th>Receipt</th>
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
                            <td><?= htmlspecialchars($order['staff_name'] ?? '—') ?></td>
                            <td>
                                <?php
                                $receipt = $conn->prepare("SELECT receipt_number FROM receipts WHERE order_id = ?");
                                $receipt->bind_param("i", $order['id']);
                                $receipt->execute();
                                $r = $receipt->get_result()->fetch_assoc();
                                if ($r): ?>
                                    <a href="<?= normal_url('../receipt.php?id=' . $order['id']) ?>" target="_blank"><?= $r['receipt_number'] ?></a>
                                <?php else: ?>
                                    —
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