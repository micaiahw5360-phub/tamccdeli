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

<div class="container">
    <div class="card max-w-2xl mx-auto text-center">
        <div class="card-content">
            <div class="inline-flex items-center justify-center w-24 h-24 bg-green-100 rounded-full mb-6">
                <span class="dashicons dashicons-yes-alt text-green-600" style="font-size: 3rem;"></span>
            </div>
            <h1 class="text-3xl font-bold text-green-600 mb-2">Order Confirmed!</h1>
            <p class="text-gray-600 mb-8">Thank you for your order. Your food is being prepared.</p>

            <div class="bg-gray-50 rounded-lg p-6 mb-8 text-left">
                <div class="space-y-4">
                    <div class="flex justify-between">
                        <span class="text-gray-600">Order Number</span>
                        <span class="font-bold"><?= $order['id'] ?></span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-600">Total Paid</span>
                        <span class="font-bold text-primary">$<?= number_format($order['total'], 2) ?></span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-600">Payment Method</span>
                        <span class="capitalize"><?= $payment_method_display ?></span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-600">Order Time</span>
                        <span><?= date('g:i a', strtotime($order['order_date'])) ?></span>
                    </div>
                    <div class="border-t pt-4">
                        <h3 class="font-bold mb-2">Pickup Instructions</h3>
                        <p class="text-gray-600">Please show your order number when collecting your food at the counter.</p>
                    </div>
                </div>
            </div>

            <?php if ($order['payment_method'] === 'cash'): ?>
                <p class="text-gray-600 mb-8">Please pay <strong>$<?= number_format($order['total'], 2) ?></strong> when you pick up your order.</p>
            <?php else: ?>
                <p class="text-green-600 font-medium mb-8">Payment completed successfully!</p>
            <?php endif; ?>

            <div class="flex flex-col sm:flex-row gap-4 justify-center">
                <button onclick="window.print()" class="btn btn-outline">Print Receipt</button>
                <a href="<?= kiosk_url('menu.php') ?>" class="btn btn-outline">Order Again</a>
                <a href="<?= kiosk_url('index.php') ?>" class="btn btn-primary">Back to Home</a>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>