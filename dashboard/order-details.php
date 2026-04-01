<?php
require __DIR__ . '/../middleware/auth_check.php';
require __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/kiosk.php';

$order_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if (!$order_id) {
    header("Location: orders.php");
    exit;
}

// Fetch order (ensure it belongs to current user)
$stmt = $conn->prepare("SELECT * FROM orders WHERE id = ? AND user_id = ?");
$stmt->bind_param("ii", $order_id, $_SESSION['user_id']);
$stmt->execute();
$order = $stmt->get_result()->fetch_assoc();
if (!$order) {
    header("Location: orders.php");
    exit;
}

// Fetch order items with options
$stmt2 = $conn->prepare("SELECT oi.*, mi.name FROM order_items oi JOIN menu_items mi ON oi.menu_item_id = mi.id WHERE oi.order_id = ?");
$stmt2->bind_param("i", $order_id);
$stmt2->execute();
$items = $stmt2->get_result()->fetch_all(MYSQLI_ASSOC);

// Decode options for each item
foreach ($items as &$item) {
    $item['options'] = json_decode($item['options'], true);
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Order Details | TAMCC Deli</title>
    <link rel="stylesheet" href="../assets/css/global.css">
    <style>
        .dashboard-wrapper { background: var(--neutral-100); }
        .sidebar a:hover { background: var(--primary-600); transform: translateX(4px); }
        .card { transition: transform 0.2s, box-shadow 0.2s; }
        .card:hover { transform: translateY(-2px); box-shadow: var(--shadow-md); }
        .option-list { margin: 0; padding-left: 1rem; font-size: 0.9rem; color: #6c757d; }
        .option-list li { list-style: disc; }
        .points-summary { background: var(--neutral-100); padding: 1rem; border-radius: var(--radius); margin-top: 1rem; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem; }
        @media (max-width: 768px) {
            .points-summary { flex-direction: column; align-items: flex-start; }
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
        <h1>Order #<?= $order['id'] ?></h1>

        <div class="card">
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1rem;">
                <div><strong>Date:</strong> <?= date('F j, Y g:i a', strtotime($order['order_date'])) ?></div>
                <div><strong>Status:</strong> <span class="status status-<?= $order['status'] ?>"><?= ucfirst($order['status']) ?></span></div>
                <div><strong>Pickup Time:</strong> <?= $order['pickup_time'] ? date('M j, Y g:i a', strtotime($order['pickup_time'])) : 'Not specified' ?></div>
                <div><strong>Payment Method:</strong> <?= ucfirst($order['payment_method']) ?></div>
                <div><strong>Payment Status:</strong> <span class="status status-<?= $order['payment_status'] ?>"><?= ucfirst($order['payment_status']) ?></span></div>
            </div>
            <p><strong>Special Instructions:</strong> <?= nl2br(htmlspecialchars($order['special_instructions'] ?: 'None')) ?></p>
            <?php if ($order['payment_status'] === 'paid' || $order['status'] === 'completed'): ?>
                <p><a href="<?= normal_url('../receipt.php?id=' . $order['id']) ?>" class="btn">View Receipt</a></p>
            <?php endif; ?>
        </div>

        <div class="card">
            <h3>Items</h3>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        60%<th>Item</th><th>Options</th><th>Quantity</th><th>Price</th><th>Subtotal</th> </thead>
                    <tbody>
                        <?php foreach ($items as $item): ?>
                         <tr>
                              <td><?= htmlspecialchars($item['name']) ?></td>
                              <td>
                                  <?php if (!empty($item['options'])): ?>
                                      <ul class="option-list">
                                          <?php foreach ($item['options'] as $opt): ?>
                                              <li><?= htmlspecialchars($opt['option_name'] ?? '') ?>: <?= htmlspecialchars($opt['value_name'] ?? '') ?></li>
                                          <?php endforeach; ?>
                                      </ul>
                                  <?php else: ?>
                                      —
                                  <?php endif; ?>
                              </td>
                              <td><?= $item['quantity'] ?></td>
                              <td>$<?= number_format($item['price'], 2) ?></td>
                              <td>$<?= number_format($item['quantity'] * $item['price'], 2) ?></td>
                          </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="points-summary">
                <span><strong>Total:</strong> $<?= number_format($order['total'], 2) ?></span>
            </div>
        </div>

        <a href="<?= normal_url('orders.php') ?>" class="btn" style="background: var(--neutral-500);">← Back to Orders</a>
    </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
</body>
</html>