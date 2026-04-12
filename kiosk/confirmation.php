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
            background: linear-gradient(135deg, #1e3c72 0%, #2b4c7c 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem;
        }
        .confirmation-card {
            background: white;
            max-width: 600px;
            width: 100%;
            border-radius: 2rem;
            padding: 2rem;
            box-shadow: 0 20px 40px rgba(0,0,0,0.2);
            animation: fadeInUp 0.5s;
            text-align: center;
        }
        @keyframes fadeInUp {
            from { opacity:0; transform:translateY(30px); }
            to { opacity:1; transform:translateY(0); }
        }
        h1 { color: #1e3c72; margin-bottom: 0.5rem; display: flex; align-items: center; justify-content: center; gap: 0.5rem; }
        .checkmark { font-size: 4rem; margin: 1rem 0; }
        .order-number {
            background: #e0e7ff;
            padding: 0.5rem 1rem;
            border-radius: 2rem;
            display: inline-block;
            font-weight: bold;
            font-size: 1.2rem;
            margin: 1rem 0;
            color: #1e3c72;
        }
        .receipt {
            background: #f8fafc;
            border-radius: 1.5rem;
            padding: 1.5rem;
            text-align: left;
            margin: 1.5rem 0;
            border: 1px solid #e2e8f0;
        }
        .receipt-item {
            display: flex;
            justify-content: space-between;
            padding: 0.5rem 0;
            border-bottom: 1px dashed #cbd5e1;
        }
        .receipt-total {
            font-size: 1.3rem;
            font-weight: bold;
            text-align: right;
            margin-top: 1rem;
            padding-top: 0.5rem;
            border-top: 2px solid #cbd5e1;
            color: #1e3c72;
        }
        .auto-redirect {
            margin-top: 1.5rem;
            font-size: 0.9rem;
            color: #4a5568;
        }
        .btn-home {
            display: inline-block;
            background: #1e3c72;
            color: white;
            text-decoration: none;
            padding: 0.7rem 1.5rem;
            border-radius: 2rem;
            margin-top: 1rem;
            transition: background 0.2s;
        }
        .btn-home:hover { background: #2b4c7c; }
    </style>
</head>
<body>
<div class="confirmation-card">
    <div class="checkmark">✅🎉</div>
    <h1>Thank You, <?= htmlspecialchars($order['customer']) ?>!</h1>
    <p>Your order has been placed and sent to the kitchen.</p>

    <div class="order-number">🧾 Order #: <?= htmlspecialchars($order['id']) ?></div>

    <div class="receipt">
        <h3 style="margin-bottom: 1rem; color: #1e3c72;">Order Summary</h3>
        <?php foreach ($order['items'] as $item): ?>
            <div class="receipt-item">
                <span><?= $item['quantity'] ?>x <?= htmlspecialchars($item['item']['name']) ?></span>
                <span>$<?= number_format($item['subtotal'], 2) ?></span>
            </div>
            <?php if (!empty($item['options'])): ?>
                <div style="font-size: 0.8rem; color: #4a5568; margin-left: 1rem; margin-bottom: 0.5rem;">
                    <?php foreach ($item['options'] as $opt): ?>
                        • <?= htmlspecialchars($opt['option_name'] ?? $opt['value_name'] ?? 'Option') ?><br>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        <?php endforeach; ?>
        <div class="receipt-total">Total: $<?= number_format($order['total'], 2) ?></div>
    </div>

    <p>💳 Paid via <?= htmlspecialchars($order['payment_method']) ?></p>

    <div class="auto-redirect">
        ⏳ Redirecting to home page in <span id="countdown">5</span> seconds...
    </div>
    <a href="<?= kiosk_url('/kiosk/home.php') ?>" class="btn-home">🏠 Go to Home Now</a>
</div>

<script>
    let seconds = 5;
    const countdownEl = document.getElementById('countdown');
    const interval = setInterval(() => {
        seconds--;
        countdownEl.textContent = seconds;
        if (seconds <= 0) {
            clearInterval(interval);
            window.location.href = '<?= kiosk_url('/kiosk/home.php') ?>';
        }
    }, 1000);
</script>
</body>
</html>