<?php
require __DIR__ . '/../middleware/auth_check.php';
require __DIR__ . '/../config/database.php';

$order_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if (!$order_id) exit('Invalid order');

$user_id = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT * FROM orders WHERE id = ? AND user_id = ?");
$stmt->bind_param("ii", $order_id, $user_id);
$stmt->execute();
$order = $stmt->get_result()->fetch_assoc();
if (!$order) exit('Order not found');

$stmt2 = $conn->prepare("SELECT oi.*, mi.name FROM order_items oi JOIN menu_items mi ON oi.menu_item_id = mi.id WHERE oi.order_id = ?");
$stmt2->bind_param("i", $order_id);
$stmt2->execute();
$items = $stmt2->get_result()->fetch_all(MYSQLI_ASSOC);
foreach ($items as &$item) $item['options'] = json_decode($item['options'], true);
?>
<h3>Order #<?= $order['id'] ?></h3>
<div><strong>Date:</strong> <?= date('F j, Y g:i a', strtotime($order['order_date'])) ?></div>
<div><strong>Status:</strong> <span class="status status-<?= $order['status'] ?>"><?= ucfirst($order['status']) ?></span></div>
<div><strong>Total:</strong> $<?= number_format($order['total'], 2) ?></div>
<div><strong>Payment Method:</strong> <?= ucfirst($order['payment_method']) ?></div>
<div><strong>Pickup Time:</strong> <?= $order['pickup_time'] ? date('M j, Y g:i a', strtotime($order['pickup_time'])) : 'Not specified' ?></div>
<div><strong>Special Instructions:</strong> <?= nl2br(htmlspecialchars($order['special_instructions'] ?: 'None')) ?></div>
<hr>
<h4>Items</h4>
<table style="width:100%"><thead><tr><th>Item</th><th>Options</th><th>Qty</th><th>Price</th><th>Subtotal</th></tr></thead><tbody>
<?php foreach ($items as $item): ?>
<tr><td><?= htmlspecialchars($item['name']) ?></td><td><?php if (!empty($item['options'])): ?><ul><?php foreach ($item['options'] as $opt): ?><li><?= htmlspecialchars($opt['option_name'] ?? '') ?>: <?= htmlspecialchars($opt['value_name'] ?? '') ?></li><?php endforeach; ?></ul><?php else: ?>—<?php endif; ?></td><td><?= $item['quantity'] ?></td><td>$<?= number_format($item['price'], 2) ?></td><td>$<?= number_format($item['quantity'] * $item['price'], 2) ?></td></tr>
<?php endforeach; ?>
</tbody></table>
<?php if ($order['payment_status'] === 'paid' || $order['status'] === 'completed'): ?>
    <p><a href="<?= normal_url('receipt.php?id=' . $order['id']) ?>" class="btn" target="_blank">View Receipt</a></p>
<?php endif; ?>