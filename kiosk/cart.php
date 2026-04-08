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

// Handle AJAX updates (increment/decrement/remove)
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
            if ($_SESSION['cart'][$key]['quantity'] <= 0) unset($_SESSION['cart'][$key]);
        } elseif ($action === 'remove') {
            unset($_SESSION['cart'][$key]);
        }
    }
    echo json_encode(['success' => true]);
    exit;
}

// Build cart items for display
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
        $options = $entry['options'] ?? [];
        $option_details = getOptionDetails($conn, $options);
        $modifier_total = 0;
        foreach ($option_details as $opt) $modifier_total += $opt['price_modifier'];
        $unit_price = $item['price'] + $modifier_total;
        $subtotal = $unit_price * $entry['quantity'];
        $cart_items[] = [
            'key' => $key,
            'item' => $item,
            'quantity' => $entry['quantity'],
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
            background: rgba(255,255,255,0.95);
            border-radius: 2rem;
            padding: 2rem;
            animation: fadeInUp 0.5s ease;
        }
        @keyframes fadeInUp {
            from { opacity:0; transform:translateY(30px); }
            to { opacity:1; transform:translateY(0); }
        }
        .cart-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            flex-wrap: wrap;
        }
        .cart-header h1 { font-size: 2.5rem; margin:0; background: linear-gradient(135deg,#FF6B35,#FF4757); -webkit-background-clip:text; background-clip:text; color:transparent; }
        .empty-cart { text-align: center; padding: 4rem; }
        .empty-cart .empty-emoji { font-size: 5rem; }
        .cart-table { width: 100%; border-collapse: collapse; }
        .cart-table th { text-align: left; padding: 1rem; background: #f8f9fa; }
        .cart-table td { padding: 1rem; border-bottom: 1px solid #eee; vertical-align: middle; }
        .item-name { font-weight: 700; }
        .item-options { font-size: 0.85rem; color: #666; }
        .qty-control-cart {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            background: #f1f3f5;
            border-radius: 2rem;
            padding: 0.2rem;
        }
        .qty-btn-cart {
            background: white;
            border: none;
            width: 32px;
            height: 32px;
            border-radius: 50%;
            font-weight: bold;
            cursor: pointer;
            color: #FF6B35;
        }
        .remove-btn {
            background: #dc2626;
            color: white;
            border: none;
            padding: 0.4rem 1rem;
            border-radius: 2rem;
            cursor: pointer;
        }
        .cart-total {
            text-align: right;
            font-size: 1.8rem;
            font-weight: bold;
            margin: 1.5rem 0;
        }
        .checkout-btn {
            background: linear-gradient(135deg,#FF6B35,#FF4757);
            color: white;
            border: none;
            padding: 1rem 2rem;
            font-size: 1.2rem;
            border-radius: 3rem;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
        }
        .back-btn {
            background: #6C5CE7;
            color: white;
            padding: 0.5rem 1.5rem;
            border-radius: 2rem;
            text-decoration: none;
        }
        @media (max-width:768px){
            .cart-table, .cart-table tbody, .cart-table tr, .cart-table td { display: block; }
            .cart-table td { text-align: right; position: relative; padding-left: 50%; }
            .cart-table td::before { content: attr(data-label); position: absolute; left: 1rem; font-weight: bold; }
        }
    </style>
</head>
<body>
<div class="cart-wrapper">
    <div class="cart-header">
        <h1>🛒 Your Cart</h1>
        <a href="<?= kiosk_url('/kiosk/categories.php') ?>" class="back-btn">← Continue Shopping</a>
    </div>
    <?php if (empty($cart_items)): ?>
        <div class="empty-cart">
            <div class="empty-emoji">🍽️</div>
            <h2>Your cart is empty</h2>
            <p>Start adding delicious items!</p>
            <a href="<?= kiosk_url('/kiosk/categories.php') ?>" class="checkout-btn" style="display:inline-block; margin-top:1rem;">Browse Menu</a>
        </div>
    <?php else: ?>
        <table class="cart-table">
            <thead><tr><th>Item</th><th>Options</th><th>Qty</th><th>Price</th><th></th></tr></thead>
            <tbody>
                <?php foreach ($cart_items as $ci): ?>
                    <tr data-key="<?= $ci['key'] ?>">
                        <td data-label="Item"><div class="item-name"><?= htmlspecialchars($ci['item']['name']) ?></div></td>
                        <td data-label="Options">
                            <?php if (!empty($ci['options'])): ?>
                                <div class="item-options">
                                    <?php foreach ($ci['options'] as $opt): ?>
                                        <?= htmlspecialchars($opt['value_name']) ?><br>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>—<?php endif; ?>
                        </td>
                        <td data-label="Qty">
                            <div class="qty-control-cart">
                                <button class="qty-btn-cart dec-cart">−</button>
                                <span class="qty-val"><?= $ci['quantity'] ?></span>
                                <button class="qty-btn-cart inc-cart">+</button>
                            </div>
                        </td>
                        <td data-label="Price">$<?= number_format($ci['subtotal'], 2) ?></td>
                        <td data-label="Remove"><button class="remove-btn">Remove</button></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <div class="cart-total">Total: $<?= number_format($total, 2) ?></div>
        <div style="text-align: right;">
            <a href="<?= kiosk_url('/kiosk/payment.php') ?>" class="checkout-btn">Proceed to Payment →</a>
        </div>
    <?php endif; ?>
</div>

<script>
function updateCartDisplay() {
    fetch('<?= kiosk_url('/get-cart-count.php') ?>')
        .then(r => r.json())
        .then(data => {
            document.querySelectorAll('.cart-count').forEach(el => el.textContent = data.count);
        });
}

document.querySelectorAll('.remove-btn, .dec-cart, .inc-cart').forEach(btn => {
    btn.addEventListener('click', function(e) {
        e.preventDefault();
        const row = this.closest('tr');
        const key = row.dataset.key;
        let action = '';
        if (this.classList.contains('remove-btn')) action = 'remove';
        else if (this.classList.contains('dec-cart')) action = 'decrement';
        else if (this.classList.contains('inc-cart')) action = 'increment';
        
        fetch(window.location.href, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
            body: new URLSearchParams({ csrf_token: '<?= generateToken() ?>', key: key, action: action })
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) location.reload();
        });
    });
});
updateCartDisplay();
</script>
</body>
</html>