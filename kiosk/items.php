<?php
require __DIR__ . '/../includes/session.php';
require __DIR__ . '/../config/database.php';
require __DIR__ . '/../includes/kiosk.php';
require __DIR__ . '/../includes/functions.php';
require __DIR__ . '/../includes/csrf.php';

$kiosk_mode = true;
$category = $_GET['cat'] ?? '';
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
    <script src="/assets/js/script.js" defer></script>
</head>
<body>
    <?php include __DIR__ . '/../includes/header.php'; ?>

    <div class="kiosk-header">
        <a href="<?= kiosk_url('/kiosk/categories.php') ?>" class="back-to-categories">← Back</a>
        <h1><?= htmlspecialchars($category) ?></h1>
    </div>

    <div class="menu-container">
        <div class="items-grid">
            <?php foreach ($items as $item): ?>
                <div class="menu-item" data-id="<?= $item['id'] ?>" data-name="<?= htmlspecialchars($item['name']) ?>">
                    <?php if ($item['image']): ?>
                        <img src="<?= htmlspecialchars($item['image']) ?>" alt="<?= htmlspecialchars($item['name']) ?>">
                    <?php endif; ?>
                    <div class="menu-item-content">
                        <h3><?= htmlspecialchars($item['name']) ?></h3>
                        <div class="price" data-base-price="<?= $item['price'] ?>">
                            $<?= number_format($item['price'], 2) ?>
                        </div>

                        <?php if (!empty($item['options'])): ?>
                            <div class="item-options">
                                <?php foreach ($item['options'] as $opt): ?>
                                    <div class="option-group" data-option-id="<?= $opt['id'] ?>" data-required="<?= $opt['required'] ?>">
                                        <label><?= htmlspecialchars($opt['option_name']) ?> <?= $opt['required'] ? '*' : '' ?></label>
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

                        <div class="menu-item-footer">
                            <div class="qty-control">
                                <button type="button" class="qty-btn dec">-</button>
                                <span class="qty-value">1</span>
                                <button type="button" class="qty-btn inc">+</button>
                            </div>
                            <button type="button" class="add-to-cart-btn">Add to Cart</button>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <a href="<?= kiosk_url('/cart.php') ?>" class="floating-cart">
        🛒 Cart <span class="cart-count" id="cart-count-kiosk">0</span>
    </a>

    <?php include __DIR__ . '/../includes/footer.php'; ?>

    <script>
        // Dynamic price update
        document.querySelectorAll('.menu-item').forEach(card => {
            const basePrice = parseFloat(card.querySelector('.price').dataset.basePrice);
            const priceSpan = card.querySelector('.price');
            const radios = card.querySelectorAll('input[type="radio"]');

            function updatePrice() {
                let modifier = 0;
                radios.forEach(radio => {
                    if (radio.checked) modifier += parseFloat(radio.dataset.price || 0);
                });
                priceSpan.textContent = `$${(basePrice + modifier).toFixed(2)}`;
            }
            radios.forEach(radio => radio.addEventListener('change', updatePrice));
            updatePrice();

            // Quantity controls
            const qtySpan = card.querySelector('.qty-value');
            const decBtn = card.querySelector('.dec');
            const incBtn = card.querySelector('.inc');
            let quantity = 1;
            decBtn.addEventListener('click', () => { if (quantity > 1) quantity--; qtySpan.textContent = quantity; });
            incBtn.addEventListener('click', () => { quantity++; qtySpan.textContent = quantity; });

            // Add to cart AJAX
            const addBtn = card.querySelector('.add-to-cart-btn');
            addBtn.addEventListener('click', async () => {
                const options = {};
                let valid = true;
                card.querySelectorAll('.option-group').forEach(group => {
                    const selected = group.querySelector('input:checked');
                    if (group.dataset.required === '1' && !selected) {
                        alert('Please select ' + group.querySelector('label').innerText);
                        valid = false;
                    }
                    if (selected) options[group.dataset.optionId] = selected.value;
                });
                if (!valid) return;

                const formData = new URLSearchParams();
                formData.append('csrf_token', '<?= generateToken() ?>');
                formData.append('item_id', card.dataset.id);
                formData.append('quantity', quantity);
                formData.append('options', JSON.stringify(options));

                const response = await fetch('<?= kiosk_url('/cart.php?action=add') ?>', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
                    body: formData
                });
                const data = await response.json();
                if (data.success) {
                    updateCartDisplay();
                    alert('Added to cart');
                } else {
                    alert('Error adding item');
                }
            });
        });

        function updateCartDisplay() {
            fetch('<?= kiosk_url('/get-cart-count.php') ?>')
                .then(r => r.json())
                .then(data => {
                    document.querySelectorAll('.cart-count').forEach(el => el.textContent = data.count);
                });
        }
        updateCartDisplay();
    </script>
</body>
</html>