<?php
require __DIR__ . '/../includes/session.php';
require __DIR__ . '/../config/database.php';
require __DIR__ . '/../includes/kiosk.php';
require __DIR__ . '/../includes/functions.php';

$kiosk_mode = true;
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
    <style>
        :root { /* same as home.php – copy all variables */ }
        * { margin:0; padding:0; box-sizing:border-box; }
        body { font-family:var(--font-sans); background:#f8f9fa; min-height:100vh; display:flex; align-items:center; justify-content:center; padding:var(--space-4); }
        .kiosk { max-width:1400px; width:100%; background:rgba(255,255,255,0.95); border-radius:var(--radius-xl); box-shadow:var(--shadow-xl); backdrop-filter:blur(8px); overflow:hidden; min-height:80vh; display:flex; flex-direction:column; }
        .screen { padding:var(--space-8); flex:1; }
        h1 { font-size:var(--text-4xl); font-weight:700; margin-bottom:var(--space-4); color:var(--primary-700); }
        .time { text-align:right; font-size:var(--text-lg); color:var(--neutral-500); margin-bottom:var(--space-6); }
        .card-grid { display:grid; grid-template-columns:repeat(auto-fill, minmax(280px,1fr)); gap:var(--space-6); margin-top:var(--space-8); }
        .item-card { background:white; border-radius:var(--radius-xl); box-shadow:var(--shadow-md); overflow:hidden; transition:var(--transition); cursor:pointer; display:flex; flex-direction:column; min-height:280px; }
        .item-card:hover { transform:translateY(-4px); box-shadow:var(--shadow-xl); }
        .item-card img { width:100%; height:180px; object-fit:cover; }
        .item-card-content { padding:var(--space-4); }
        .item-card h3 { font-size:var(--text-xl); margin-bottom:var(--space-2); }
        .item-price { font-size:var(--text-2xl); font-weight:700; color:var(--primary-600); margin:var(--space-2) 0; }
        .item-actions { display:flex; justify-content:space-between; align-items:center; margin-top:var(--space-4); }
        .qty-control { display:flex; align-items:center; gap:var(--space-2); background:var(--neutral-100); border-radius:var(--radius-full); padding:var(--space-2); }
        .qty-btn { background:var(--neutral-200); border:none; width:48px; height:48px; border-radius:var(--radius-full); font-size:1.5rem; font-weight:bold; cursor:pointer; transition:var(--transition); }
        .qty-btn:active { transform:scale(0.9); }
        .qty-value { font-size:var(--text-xl); font-weight:600; min-width:40px; text-align:center; }
        .btn { display:inline-flex; align-items:center; justify-content:center; gap:var(--space-2); padding:var(--space-2) var(--space-4); font-size:var(--text-base); font-weight:600; text-decoration:none; border-radius:var(--radius-full); transition:var(--transition); cursor:pointer; border:none; background:var(--primary-600); color:white; min-height:48px; min-width:80px; }
        .btn:active { transform:scale(0.98); }
        .btn-small { padding:var(--space-2) var(--space-4); font-size:var(--text-base); min-height:48px; min-width:80px; }
        .cart-floating { position:fixed; bottom:30px; right:30px; background:#28a745; color:white; border-radius:60px; padding:1rem 2rem; font-size:clamp(1rem,3vw,2rem); box-shadow:0 8px 16px rgba(0,0,0,0.2); z-index:1000; cursor:pointer; }
        .cart-floating .cart-count { background:white; color:#28a745; border-radius:50%; padding:0.3rem 0.8rem; margin-left:0.8rem; font-weight:bold; }
        .radio-group { display:flex; flex-direction:column; gap:var(--space-2); }
        .radio-option { display:flex; align-items:center; gap:var(--space-2); padding:var(--space-2); border:1px solid var(--neutral-200); border-radius:var(--radius); cursor:pointer; }
        .radio-option.selected { border-color:var(--primary-600); background:rgba(7,74,242,0.1); }
        .form-label { display:block; font-size:var(--text-lg); font-weight:600; margin-bottom:var(--space-2); color:var(--neutral-700); }
        @media (max-width:768px) { .card-grid { grid-template-columns:1fr; } }
    </style>
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
                        alert('Added to cart');
                    } else {
                        alert('Error adding item');
                    }
                })
                .catch(err => alert('Network error'));
            });
        });

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
    </script>
</body>
</html>