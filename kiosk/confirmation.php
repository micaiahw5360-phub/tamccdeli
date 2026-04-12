<?php
require __DIR__ . '/../includes/session.php';
require __DIR__ . '/../includes/kiosk.php';

$order = $_SESSION['last_order'] ?? null;
if (!$order) {
    header('Location: ' . kiosk_url('/kiosk/menu.php'));
    exit;
}
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
            background: white;
            border-radius: 2rem;
            padding: 2rem;
            max-width: 600px;
            width: 100%;
            box-shadow: 0 20px 40px rgba(0,0,0,0.2);
            text-align: center;
            animation: fadeInUp 0.5s ease;
        }
        @keyframes fadeInUp {
            from { opacity:0; transform:translateY(30px); }
            to { opacity:1; transform:translateY(0); }
        }
        .success-icon { font-size: 4rem; margin-bottom: 1rem; }
        h1 { color: #00D25B; margin-bottom: 0.5rem; }
        .order-details {
            text-align: left;
            margin: 2rem 0;
            border-top: 1px solid #eee;
            padding-top: 1rem;
        }
        .total {
            font-size: 1.5rem;
            font-weight: bold;
            color: #FF6B35;
            text-align: right;
            margin-top: 1rem;
        }
        .btn {
            background: linear-gradient(135deg, #FF6B35, #FF4757);
            color: white;
            border: none;
            padding: 0.8rem 2rem;
            font-size: 1.1rem;
            border-radius: 3rem;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            margin-top: 1rem;
        }
        .btn:hover { transform: scale(1.02); }
        .back-link {
            display: inline-block;
            margin-top: 1rem;
            color: #FF6B35;
            text-decoration: none;
            font-weight: bold;
        }
    </style>
</head>
<body>
<div class="confirmation-card">
    <div class="success-icon">🎉✅</div>
    <h1>Order Confirmed!</h1>
    <p>Thank you, <?= htmlspecialchars($order['customer']) ?>! Your order #<?= $order['id'] ?> has been received.</p>
    <div class="order-details">
        <h3>Order Summary</h3>
        <?php foreach ($order['items'] as $item): ?>
            <p>
                <strong><?= $item['quantity'] ?>x <?= htmlspecialchars($item['item']['name']) ?></strong><br>
                <?php if (!empty($item['options'])): ?>
                    <small>
                    <?php foreach ($item['options'] as $opt): ?>
                        • <?= htmlspecialchars($opt['option_name']) ?>: <?= htmlspecialchars($opt['value_name']) ?>
                        <?php if ($opt['price_modifier'] != 0): ?>
                            (<?= ($opt['price_modifier'] > 0 ? '+' : '-') ?>$<?= number_format(abs($opt['price_modifier']), 2) ?>)
                        <?php endif; ?><br>
                    <?php endforeach; ?>
                    </small>
                <?php endif; ?>
                – $<?= number_format($item['subtotal'], 2) ?>
            </p>
        <?php endforeach; ?>
        <div class="total">Total Paid: $<?= number_format($order['total'], 2) ?></div>
        <p><small>Payment method: <?= htmlspecialchars($order['payment_method']) ?></small></p>
    </div>
    <a href="<?= kiosk_url('/kiosk/menu.php') ?>" class="btn">Start New Order</a>
    <div><a href="<?= kiosk_url('/kiosk/home.php') ?>" class="back-link">← Back to Home</a></div>
</div>
</body>
</html>