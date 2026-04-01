<?php
require __DIR__ . '/../includes/session.php';
require __DIR__ . '/../config/database.php';
require __DIR__ . '/../includes/kiosk.php';
require __DIR__ . '/../includes/functions.php';

$category = isset($_GET['cat']) ? $_GET['cat'] : '';
if (!$category) {
    header('Location: ' . kiosk_url('/kiosk/categories.php'));
    exit;
}

$stmt = $conn->prepare("SELECT * FROM menu_items WHERE category = ? ORDER BY sort_order, name");
$stmt->bind_param("s", $category);
$stmt->execute();
$items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
foreach ($items as &$item) {
    $item['options'] = getItemOptions($conn, $item['id']);
}

$page_title = "$category | TAMCC Deli Kiosk";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
    <title><?= $page_title ?></title>
    <link rel="stylesheet" href="/assets/css/global.css">
    <link rel="stylesheet" href="/assets/css/kiosk.css">
</head>
<body>
    <div class="kiosk">
        <div class="screen">
            <div class="time"></div>
            <h1><?= htmlspecialchars($category) ?></h1>
            <div class="items-container card-grid">
                <?php foreach ($items as $item): ?>
                <div class="item-card" data-id="<?= $item['id'] ?>" data-name="<?= htmlspecialchars($item['name']) ?>" data-price="<?= $item['price'] ?>">
                    <?php if ($item['image']): ?>
                        <img src="<?= htmlspecialchars($item['image']) ?>" alt="<?= htmlspecialchars($item['name']) ?>">
                    <?php endif; ?>
                    <div class="item-card-content">
                        <h3><?= htmlspecialchars($item['name']) ?></h3>
                        <div class="item-price">$<?= number_format($item['price'], 2) ?></div>
                        <?php if (!empty($item['options'])): ?>
                            <div class="options-container" style="margin: var(--space-2) 0;">
                                <?php foreach ($item['options'] as $opt): ?>
                                    <div class="option-group" data-option-id="<?= $opt['id'] ?>" data-required="<?= $opt['required'] ?>">
                                        <label class="form-label"><?= htmlspecialchars($opt['option_name']) ?> <?= $opt['required'] ? '*' : '' ?></label>
                                        <div class="radio-group">
                                            <?php foreach ($opt['values'] as $val): ?>
                                                <label class="radio-option">
                                                    <input type="radio" name="options[<?= $opt['id'] ?>]" value="<?= $val['id'] ?>" data-price="<?= $val['price_modifier'] ?>" <?= $opt['required'] ? 'required' : '' ?>>
                                                    <span><?= htmlspecialchars($val['value_name']) ?>
                                                        <?php if ($val['price_modifier'] != 0): ?>
                                                            (<?= ($val['price_modifier'] > 0 ? '+' : '-') ?>$<?= number_format(abs($val['price_modifier']), 2) ?>)
                                                        <?php endif; ?>
                                                    </span>
                                                </label>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                        <div class="item-actions">
                            <div class="qty-control">
                                <button class="qty-btn dec">-</button>
                                <span class="qty-value">1</span>
                                <button class="qty-btn inc">+</button>
                            </div>
                            <button class="btn btn-small add-to-cart">Add</button>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <div class="cart-floating" onclick="window.location.href='<?= kiosk_url('/kiosk/cart.php') ?>'">
        <span>🛒 Cart</span>
        <span class="cart-count">0</span>
    </div>
    <script src="/assets/js/kiosk.js"></script>
    <script>
        // Override addToCart to include selected options
        document.querySelectorAll('.add-to-cart').forEach(btn => {
            btn.addEventListener('click', (e) => {
                const card = btn.closest('.item-card');
                const itemId = parseInt(card.dataset.id);
                const quantity = parseInt(card.querySelector('.qty-value').textContent);
                const options = {};
                let valid = true;
                card.querySelectorAll('.option-group').forEach(group => {
                    const required = group.dataset.required === '1';
                    const selected = group.querySelector('input:checked');
                    if (required && !selected) {
                        alert('Please select ' + group.querySelector('.form-label').textContent);
                        valid = false;
                    }
                    if (selected) {
                        const optionId = group.dataset.optionId;
                        options[optionId] = selected.value;
                    }
                });
                if (!valid) return;

                fetch('<?= kiosk_url('/cart.php?action=add') ?>', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
                    body: new URLSearchParams({
                        csrf_token: '<?= generateToken() ?>',
                        item_id: itemId,
                        quantity: quantity,
                        options: JSON.stringify(options)
                    })
                })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        updateCartDisplay();
                        showToast('Added to cart');
                    } else {
                        showToast('Error adding item');
                    }
                })
                .catch(err => showToast('Network error'));
            });
        });

        // Update price when options change
        document.querySelectorAll('.radio-option input').forEach(radio => {
            radio.addEventListener('change', function() {
                const card = this.closest('.item-card');
                const basePrice = parseFloat(card.dataset.price);
                let modifier = 0;
                card.querySelectorAll('input:checked').forEach(inp => {
                    modifier += parseFloat(inp.dataset.price || 0);
                });
                const total = basePrice + modifier;
                card.querySelector('.item-price').textContent = `$${total.toFixed(2)}`;
            });
        });

        updateCartDisplay();
    </script>
</body>
</html>