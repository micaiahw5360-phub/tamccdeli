<?php
require __DIR__ . '/../includes/session.php';
require __DIR__ . '/../config/database.php';
require __DIR__ . '/../includes/kiosk.php';
require __DIR__ . '/../includes/functions.php';

$kiosk_mode = true;
$cart = $_SESSION['cart'] ?? [];
$cart_items = [];
$total = 0;
if (!empty($cart)) {
    $item_ids = array_unique(array_column($cart, 'item_id'));
    $items_data = [];
    if (!empty($item_ids)) {
        $placeholders = implode(',', array_fill(0, count($item_ids), '?'));
        $stmt = $conn->prepare("SELECT * FROM menu_items WHERE id IN ($placeholders)");
        $stmt->bind_param(str_repeat('i', count($item_ids)), ...$item_ids);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $items_data[$row['id']] = $row;
        }
    }
    foreach ($cart as $key => $entry) {
        $item_id = $entry['item_id'];
        if (!isset($items_data[$item_id])) continue;
        $item = $items_data[$item_id];
        $quantity = $entry['quantity'];
        $options = $entry['options'] ?? [];
        $option_details = getOptionDetails($conn, $options);
        $modifier_total = 0;
        foreach ($option_details as $opt) {
            $modifier_total += $opt['price_modifier'];
        }
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
        :root { /* same variables as home.php – copy all */ }
        * { margin:0; padding:0; box-sizing:border-box; }
        body { font-family:var(--font-sans); background:#f8f9fa; min-height:100vh; display:flex; align-items:center; justify-content:center; padding:var(--space-4); }
        .kiosk { max-width:1400px; width:100%; background:rgba(255,255,255,0.95); border-radius:var(--radius-xl); box-shadow:var(--shadow-xl); backdrop-filter:blur(8px); overflow:hidden; min-height:80vh; display:flex; flex-direction:column; }
        .screen { padding:var(--space-8); flex:1; }
        h1 { font-size:var(--text-4xl); font-weight:700; margin-bottom:var(--space-4); color:var(--primary-700); }
        .time { text-align:right; font-size:var(--text-lg); color:var(--neutral-500); margin-bottom:var(--space-6); }
        .cart-table { width:100%; border-collapse:collapse; margin:var(--space-6) 0; }
        .cart-table th, .cart-table td { padding:var(--space-4); text-align:left; border-bottom:1px solid var(--neutral-200); font-size:var(--text-lg); }
        .cart-table th { background:var(--neutral-100); font-weight:600; }
        .cart-actions { display:flex; gap:var(--space-2); align-items:center; }
        .total { font-size:var(--text-2xl); font-weight:700; text-align:right; margin-top:var(--space-6); padding-top:var(--space-4); border-top:2px solid var(--neutral-200); }
        .btn { display:inline-flex; align-items:center; justify-content:center; gap:var(--space-2); padding:var(--space-4) var(--space-8); font-size:var(--text-xl); font-weight:600; text-decoration:none; border-radius:var(--radius-full); transition:var(--transition); cursor:pointer; border:none; background:var(--primary-600); color:white; min-height:64px; min-width:120px; }
        .btn:active { transform:scale(0.98); }
        .btn-outline { background:transparent; border:2px solid var(--primary-600); color:var(--primary-600); }
        .btn-outline:hover { background:var(--primary-50); transform:translateY(-2px); }
        .btn-primary { background:var(--primary-600); }
        .qty-btn { background:var(--neutral-200); border:none; width:48px; height:48px; border-radius:var(--radius-full); font-size:1.5rem; font-weight:bold; cursor:pointer; transition:var(--transition); }
        .btn-small { padding:var(--space-2) var(--space-4); font-size:var(--text-base); min-height:48px; min-width:80px; }
        .option-list { margin:0; padding-left:1rem; font-size:0.9rem; color:var(--neutral-600); }
        @media (max-width:768px) { .cart-table th, .cart-table td { padding:var(--space-2); font-size:var(--text-base); } }
    </style>
</head>
<body>
    <div class="kiosk">
        <div class="screen">
            <div class="time"></div>
            <h1>Your Cart</h1>
            <div class="cart-items-container">
                <?php if (empty($cart_items)): ?>
                    <p>Your cart is empty.</p>
                <?php else: ?>
                    <table class="cart-table">
                        <thead>
                            60%<th>Item</th><th>Quantity</th><th>Unit Price</th><th>Subtotal</th> </thead>
                        <tbody class="cart-items">
                            <?php foreach ($cart_items as $item): ?>
                             <tr data-key="<?= $item['key'] ?>">
                                 <td><?= htmlspecialchars($item['item']['name']) ?>
                                     <?php if (!empty($item['options'])): ?>
                                         <div class="option-list">
                                             <?php foreach ($item['options'] as $opt): ?>
                                                 <small><?= htmlspecialchars($opt['option_name']) ?>: <?= htmlspecialchars($opt['value_name']) ?></small><br>
                                             <?php endforeach; ?>
                                         </div>
                                     <?php endif; ?>
                                  </td>
                                  <td>
                                     <div class="cart-actions">
                                         <button class="qty-btn dec" data-key="<?= $item['key'] ?>">-</button>
                                         <span class="qty-value"><?= $item['quantity'] ?></span>
                                         <button class="qty-btn inc" data-key="<?= $item['key'] ?>">+</button>
                                         <button class="btn-small remove" data-key="<?= $item['key'] ?>">Remove</button>
                                     </div>
                                  </td>
                                  <td>$<?= number_format($item['unit_price'], 2) ?></td>
                                 <td class="subtotal">$<?= number_format($item['subtotal'], 2) ?></td>
                              </tr>
                            <?php endforeach; ?>
                        </tbody>
                     </table>
                <?php endif; ?>
            </div>
            <div class="total">Total: <span class="cart-total">$<?= number_format($total, 2) ?></span></div>
            <div style="display: flex; gap: var(--space-4); margin-top: var(--space-8);">
                <a href="<?= kiosk_url('/kiosk/categories.php') ?>" class="btn btn-outline">Continue Shopping</a>
                <?php if (!empty($cart_items)): ?>
                    <a href="<?= kiosk_url('/kiosk/payment.php') ?>" class="btn btn-primary">Proceed to Payment</a>
                <?php endif; ?>
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

        document.querySelectorAll('.dec, .inc, .remove').forEach(btn => {
            btn.addEventListener('click', async function() {
                const key = this.dataset.key;
                const action = this.classList.contains('dec') ? 'decrement' : (this.classList.contains('inc') ? 'increment' : 'remove');
                const formData = new FormData();
                formData.append('csrf_token', '<?= generateToken() ?>');
                formData.append('key', key);
                formData.append('action', action);
                const response = await fetch('<?= kiosk_url('/cart.php') ?>', {
                    method: 'POST',
                    body: formData,
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                });
                if (response.ok) location.reload();
                else alert('Error updating cart');
            });
        });
    </script>
</body>
</html>