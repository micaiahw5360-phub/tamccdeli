<?php
require __DIR__ . '/includes/session.php';
require "config/database.php";
require "includes/kiosk.php";

$order_id = isset($_GET['order_id']) ? intval($_GET['order_id']) : 0;
if (!$order_id || !isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

$stmt = $conn->prepare("SELECT * FROM orders WHERE id = ? AND user_id = ?");
$stmt->bind_param("ii", $order_id, $_SESSION['user_id']);
$stmt->execute();
$order = $stmt->get_result()->fetch_assoc();

if (!$order) {
    header("Location: index.php");
    exit;
}

$page_title = "Order Confirmation";
include 'includes/header.php';

$payment_display = [
    'cash' => 'Cash on Pickup',
    'wallet' => 'Wallet Balance',
    'online' => 'Online Payment (Card)'
];
$payment_method_display = $payment_display[$order['payment_method']] ?? ucfirst($order['payment_method']);
?>

<div class="checkout-container" style="text-align: center;">
    <div class="success-icon" style="font-size: 5rem; margin-bottom: 1rem;">✅</div>
    <h1>Thank You!</h1>
    <p>Your order <strong>#<?= $order['id'] ?></strong> has been placed successfully.</p>
    <p><strong>Total:</strong> $<?= number_format($order['total'], 2) ?></p>
    <p><strong>Payment Status:</strong> <?= ucfirst($order['payment_status']) ?></p>
    <p><strong>Payment Method:</strong> <?= $payment_method_display ?></p>

    <div style="margin-top: 2rem; display: flex; gap: 1rem; justify-content: center; flex-wrap: wrap;">
        <?php if ($order['payment_status'] === 'paid'): ?>
            <a href="<?= kiosk_url('receipt.php?id=' . $order_id) ?>" class="btn btn-primary">View Receipt</a>
        <?php endif; ?>
        <a href="<?= kiosk_url('menu.php') ?>" class="btn btn-accent">Order Again</a>
        <a href="<?= normal_url('dashboard/orders.php') ?>" class="btn">My Orders</a>
    </div>
</div>

<?php include 'includes/footer.php'; ?>