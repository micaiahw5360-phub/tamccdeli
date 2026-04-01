<?php
require __DIR__ . '/../includes/session.php';
require __DIR__ . '/../config/database.php';
require __DIR__ . '/../includes/kiosk.php';
require __DIR__ . '/../includes/functions.php';

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
    <link rel="stylesheet" href="/assets/css/kiosk.css">
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
                              <tr><th>Item</th><th>Quantity</th><th>Unit Price</th><th>Subtotal</th></tr>
                        </thead>
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
    <script src="/assets/js/kiosk.js"></script>
    <script>
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
                else showToast('Error updating cart');
            });
        });
    </script>
</body>
</html>