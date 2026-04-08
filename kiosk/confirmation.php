<?php
require __DIR__ . '/../includes/session.php';
require __DIR__ . '/../includes/kiosk.php';

$kiosk_mode = true;
if (!isset($_SESSION['last_order'])) {
    header('Location: ' . kiosk_url('/kiosk/categories.php'));
    exit;
}

$order = $_SESSION['last_order'];
unset($_SESSION['last_order']);

$page_title = "Order Confirmation | TAMCC Deli Kiosk";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
    <title><?= $page_title ?></title>
    <style>
        /* Same inline CSS as before, plus receipt styles */
        .receipt-card { background:white; border-radius:var(--radius-xl); padding:var(--space-8); box-shadow:var(--shadow-lg); margin:var(--space-6) 0; }
        .receipt-header { text-align:center; border-bottom:2px dashed var(--neutral-200); padding-bottom:var(--space-4); margin-bottom:var(--space-6); }
        .receipt-items { margin:var(--space-6) 0; }
        .receipt-item { display:flex; justify-content:space-between; padding:var(--space-2) 0; }
        .receipt-total { font-size:var(--text-2xl); font-weight:bold; text-align:right; border-top:2px solid var(--neutral-200); padding-top:var(--space-4); margin-top:var(--space-4); }
    </style>
</head>
<body>
    <div class="kiosk">
        <div class="screen">
            <div class="time"></div>
            <h1>Thank You!</h1>
            <div class="receipt-card">
                <div class="receipt-header">
                    <h2>TAMCC Deli</h2>
                    <p>Order Confirmation</p>
                </div>
                <p><strong>Order ID:</strong> <span class="order-id"><?= $order['id'] ?></span></p>
                <p><strong>Date/Time:</strong> <span class="order-time"><?= date('M j, Y g:i a', strtotime($order['timestamp'])) ?></span></p>
                <p><strong>Customer:</strong> <span class="customer-name"><?= htmlspecialchars($order['customer']) ?></span></p>
                <p><strong>Payment Method:</strong> <?= $order['payment_method'] === 'wallet' ? 'Wallet Balance' : 'Credit/Debit Card' ?></p>
                <div class="receipt-items">
                    <?php foreach ($order['items'] as $item): ?>
                    <div class="receipt-item">
                        <span><?= $item['quantity'] ?>x <?= htmlspecialchars($item['item']['name']) ?></span>
                        <span>$<?= number_format($item['subtotal'], 2) ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>
                <div class="receipt-total">Total: $<?= number_format($order['total'], 2) ?></div>
            </div>
            <div style="display: flex; justify-content: center; gap: var(--space-4); margin-top: var(--space-8);">
                <a href="<?= kiosk_url('/kiosk/categories.php') ?>" class="btn btn-primary">New Order</a>
                <a href="<?= kiosk_url('/kiosk/home.php') ?>" class="btn btn-outline">Home</a>
            </div>
        </div>
    </div>
    <script>
        function updateCartDisplay() {
            fetch('<?= kiosk_url('/get-cart-count.php') ?>')
                .then(r => r.json())
                .then(data => {
                    document.querySelectorAll('.cart-count').forEach(el => el.textContent = data.count);
                })
                .catch(console.error);
        }
        updateCartDisplay();
    </script>
</body>
</html>