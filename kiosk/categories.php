<?php
require __DIR__ . '/../includes/session.php';
require __DIR__ . '/../includes/kiosk.php';

$categories = [
    'Combo' => '🍔',
    'Drinks' => '🥤',
    'Breakfast' => '🍳',
    'À la carte' => '🍽️',
    'Dessert' => '🍰'
];

$page_title = "Select Category | TAMCC Deli Kiosk";
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
            <h1>What would you like?</h1>
            <div class="card-grid">
                <?php foreach ($categories as $name => $icon): ?>
                    <a href="<?= kiosk_url('/kiosk/items.php?cat=' . urlencode($name)) ?>" class="category-card">
                        <div class="icon"><?= $icon ?></div>
                        <h3><?= htmlspecialchars($name) ?></h3>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <script src="/assets/js/kiosk.js"></script>
</body>
</html>