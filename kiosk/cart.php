<?php
require __DIR__ . '/../includes/session.php';
require __DIR__ . '/../config/database.php';
require __DIR__ . '/../includes/kiosk.php';
require __DIR__ . '/../includes/functions.php';
require __DIR__ . '/../includes/csrf.php';

$kiosk_mode = true;
$cart = $_SESSION['cart'] ?? [];
$cart_items = [];
$total = 0;

// Handle AJAX updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {
    header('Content-Type: application/json');
    if (!validateToken($_POST['csrf_token'] ?? '')) {
        echo json_encode(['success' => false, 'error' => 'Invalid CSRF']);
        exit;
    }
    $key = $_POST['key'] ?? '';
    $action = $_POST['action'] ?? '';
    if (isset($_SESSION['cart'][$key])) {
        if ($action === 'increment') {
            $_SESSION['cart'][$key]['quantity']++;
        } elseif ($action === 'decrement') {
            $_SESSION['cart'][$key]['quantity']--;
            if ($_SESSION['cart'][$key]['quantity'] <= 0) {
                unset($_SESSION['cart'][$key]);
            }
        } elseif ($action === 'remove') {
            unset($_SESSION['cart'][$key]);
        }
    }
    echo json_encode(['success' => true]);
    exit;
}

if (!empty($cart)) {
    $item_ids = array_unique(array_column($cart, 'item_id'));
    $items_data = [];
    if (!empty($item_ids)) {
        $placeholders = implode(',', array_fill(0, count($item_ids), '?'));
        $stmt = $conn->prepare("SELECT * FROM menu_items WHERE id IN ($placeholders)");
        $stmt->bind_param(str_repeat('i', count($item_ids)), ...$item_ids);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) $items_data[$row['id']] = $row;
    }
    foreach ($cart as $key => $entry) {
        $item_id = $entry['item_id'];
        if (!isset($items_data[$item_id])) continue;
        $item = $items_data[$item_id];
        $quantity = $entry['quantity'];
        $options = $entry['options'] ?? [];
        $option_details = getOptionDetails($conn, $options);
        $modifier_total = 0;
        foreach ($option_details as $opt) $modifier_total += $opt['price_modifier'];
        $unit_price = $item['price'] + $modifier_total;
        $subtotal = $unit_price * $quantity;
        $cart_items[] = [
            'key' => $key,
            'item' => $item,
            'quantity' => $quantity,
            'options' => $option_details,
            'unit_price' => $unit_price,
            'subtotal' => $subtotal
        ];
        $total += $subtotal;
    }
}

$page_title = "Your Cart | TAMCC Deli Kiosk";
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
            padding: 2rem;
        }
        .cart-wrapper {
            max-width: 1200px;
            margin: 0 auto;
            background: rgba(255,255,255,0.97);
            border-radius: 2rem;
            padding: 2rem;
            animation: fadeInUp 0.5s ease;
        }
        @keyframes fadeInUp {
            from { opacity:0; transform:translateY(30px); }
            to { opacity:1; transform:translateY(0); }
        }
        h1 {
            font-size: 2.5rem;
            background: linear-gradient(135deg, #FF6B35, #FF4757);
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
            margin-bottom: 1.5rem;
        }
        .cart-items {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }
        .cart-item {
            background: white;
            border-radius: 1.5rem;
            padding: 1rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        .item-details {
            flex: 2;
        }
        .item-name {
            font-size: 1.2rem;
            font-weight: bold;
        }
        .item-options {
            font-size: 0.85rem;
            color: #666;
        }
        .item-price {
            font-weight: bold;
            color: #FF6B35;
        }
        .quantity-control {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            background: #f1f5f9;
            border-radius: 2rem;
            padding: 0.3rem;
        }
        .qty-btn {
            background: white;
            border: none;
            width: 36px;
            height: 36px;
            border-radius: 50%;
            font-size: 1.2rem;
            font-weight: bold;
            cursor: pointer;
            color: #FF6B35;
        }
        .remove-btn {
            background: #fee2e2;
            color: #dc2626;
            border: none;
            padding: 0.5rem 1rem;
            border-radius: 2rem;
            cursor: pointer;
            font-weight: bold;
        }
        .cart-summary {
            margin-top: 2rem;
            text-align: right;
            border-top: 2px dashed #e2e8f0;
            padding-top: 1rem;
        }
        .total {
            font-size: 1.8rem;
            font-weight: bold;
        }
        .checkout-btn {
            background: linear-gradient(135deg, #00D25B, #00CEC9);
            color: white;
            border: none;
            padding: 1rem 2rem;
            font-size: 1.3rem;
            border-radius: 3rem;
            cursor: pointer;
            margin-top: 1rem;
            display: inline-block;
            text-decoration: none;
        }
        .empty-cart {
            text-align: center;
            padding: 3rem;
        }
        .back-link {
            display: inline-block;
            margin-top: 1rem;
            color: #FF6B35;
            text-decoration: none;
            font-weight: bold;
        }
        @media (max-width: 768px) {
            .cart-item { flex-direction: column; align-items: stretch; gap: 0.5rem; }
            .quantity-control { justify-content: center; }
        }
    </style>
</head>
<body>
<div class="cart-wrapper">
    <h1>🛒 Your Order</h1>
    <?php if (empty($cart_items)): ?>
        <div class="empty-cart">
            <div style="font-size: 4rem;">🍽️</div>
            <p>Your cart is empty.</p>
            <a href="<?= kiosk_url('/kiosk/categories.php') ?>" class="checkout-btn">Start Ordering</a>
        </div>
    <?php else: ?>
        <div class="cart-items">
            <?php foreach ($cart_items as $item): ?>
                <div class="cart-item" data-key="<?= $item['key'] ?>">
                    <div class="item-details">
                        <div class="item-name"><?= htmlspecialchars($item['item']['name']) ?></div>
                        <?php if (!empty($item['options'])): ?>
                            <div class="item-options">
                                <?php foreach ($item['options'] as $opt): ?>
                                    <?= htmlspecialchars($opt['option_name']) ?>: <?= htmlspecialchars($opt['value_name']) ?> (+$<?= number_format($opt['price_modifier'], 2) ?>)<br>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="item-price">$<?= number_format($item['unit_price'], 2) ?> each</div>
                    <div class="quantity-control">
                        <button class="qty-btn decrement" data-key="<?= $item['key'] ?>">−</button>
                        <span class="qty-value"><?= $item['quantity'] ?></span>
                        <button class="qty-btn increment" data-key="<?= $item['key'] ?>">+</button>
                    </div>
                    <div class="item-subtotal">Subtotal: $<?= number_format($item['subtotal'], 2) ?></div>
                    <button class="remove-btn" data-key="<?= $item['key'] ?>">Remove</button>
                </div>
            <?php endforeach; ?>
        </div>
        <div class="cart-summary">
            <div class="total">Total: $<?= number_format($total, 2) ?></div>
            <a href="<?= kiosk_url('/kiosk/payment.php') ?>" class="checkout-btn">Proceed to Payment →</a>
        </div>
    <?php endif; ?>
    <div><a href="<?= kiosk_url('/kiosk/categories.php') ?>" class="back-link">← Continue Shopping</a></div>
</div>
<script>
    function updateCartCount() {
        fetch('<?= kiosk_url('/get-cart-count.php') ?>')
            .then(r => r.json())
            .then(data => {
                document.querySelectorAll('.cart-count').forEach(el => el.textContent = data.count);
            });
    }
    function updateCartItem(key, action) {
        fetch('<?= kiosk_url('/kiosk/cart.php') ?>', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
            body: new URLSearchParams({
                csrf_token: '<?= generateToken() ?>',
                key: key,
                action: action
            })
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) location.reload();
        });
    }
    document.querySelectorAll('.increment').forEach(btn => {
        btn.addEventListener('click', () => updateCartItem(btn.dataset.key, 'increment'));
    });
    document.querySelectorAll('.decrement').forEach(btn => {
        btn.addEventListener('click', () => updateCartItem(btn.dataset.key, 'decrement'));
    });
    document.querySelectorAll('.remove-btn').forEach(btn => {
        btn.addEventListener('click', () => updateCartItem(btn.dataset.key, 'remove'));
    });
    updateCartCount();
</script>
</body>
</html>