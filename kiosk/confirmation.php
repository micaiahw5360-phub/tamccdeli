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
        * { margin:0; padding:0; box-sizing:border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem;
        }
        .confirmation-card {
            background: rgba(255,255,255,0.97);
            border-radius: 3rem;
            padding: 2rem;
            max-width: 600px;
            width: 100%;
            text-align: center;
            animation: fadeInUp 0.5s;
        }
        @keyframes fadeInUp {
            from { opacity:0; transform:translateY(30px); }
            to { opacity:1; transform:translateY(0); }
        }
        h1 { font-size: 2.5rem; margin-bottom: 1rem; }
        .receipt {
            text-align: left;
            background: #f8f9fa;
            border-radius: 1.5rem;
            padding: 1.5rem;
            margin: 1.5rem 0;
        }
        .btn-group {
            display: flex;
            gap: 1rem;
            justify-content: center;
        }
        .btn {
            background: linear-gradient(135deg, #00D25B, #00CEC9);
            color: white;
            padding: 0.8rem 1.5rem;
            border-radius: 3rem;
            text-decoration: none;
            font-weight: bold;
        }
        .btn-outline {
            background: transparent;
            border: 2px solid #FF6B35;
            color: #FF6B35;
        }
    </style>
</head>
<body>
<div class="confirmation-card">
    <h1>✅ Order Confirmed!</h1>
    <p>Thank you, <?= htmlspecialchars($order['customer']) ?>!</p>
    <div class="receipt">
        <p><strong>Order #</strong> <?= $order['id'] ?></p>
        <p><strong>Date</strong> <?= date('M j, Y g:i a', strtotime($order['timestamp'])) ?></p>
        <p><strong>Payment</strong> <?= $order['payment_method'] === 'wallet' ? 'Wallet Balance' : 'Card' ?></p>
        <hr style="margin: 1rem 0;">
        <?php foreach ($order['items'] as $item): ?>
            <p><?= $item['quantity'] ?>x <?= htmlspecialchars($item['item']['name']) ?> – $<?= number_format($item['subtotal'], 2) ?></p>
        <?php endforeach; ?>
        <hr style="margin: 1rem 0;">
        <p><strong>Total: $<?= number_format($order['total'], 2) ?></strong></p>
    </div>
    <div class="btn-group">
        <a href="<?= kiosk_url('/kiosk/categories.php') ?>" class="btn">New Order</a>
        <a href="<?= kiosk_url('/kiosk/home.php') ?>" class="btn btn-outline">Home</a>
    </div>
</div>
</body>
</html>