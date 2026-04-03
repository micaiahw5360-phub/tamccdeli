<?php
require __DIR__ . '/../includes/session.php';
require __DIR__ . '/../includes/kiosk.php';

$kiosk_mode = true;
$page_title = "Select Category | TAMCC Deli Kiosk";

$categories = [
    'Combo' => '🍔',
    'Drinks' => '🥤',
    'Breakfast' => '🍳',
    'À la carte' => '🍽️',
    'Dessert' => '🍰'
];
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

    <div class="kiosk-categories-container">
        <h1>What would you like?</h1>
        <div class="kiosk-categories">
            <?php foreach ($categories as $name => $icon): ?>
                <a href="<?= kiosk_url('/kiosk/items.php?cat=' . urlencode($name)) ?>" class="kiosk-category">
                    <span class="icon"><?= $icon ?></span>
                    <h3><?= htmlspecialchars($name) ?></h3>
                </a>
            <?php endforeach; ?>
        </div>
    </div>

    <a href="<?= kiosk_url('/cart.php') ?>" class="floating-cart">
        🛒 Cart <span class="cart-count" id="cart-count-kiosk">0</span>
    </a>

    <?php include __DIR__ . '/../includes/footer.php'; ?>
    <script>
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