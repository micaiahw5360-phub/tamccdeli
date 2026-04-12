<?php
require __DIR__ . '/../includes/session.php';
require __DIR__ . '/../config/database.php';
require __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/kiosk.php';
require __DIR__ . '/../includes/functions.php';

$categories = [
    'breakfast' => '🍳 Breakfast',
    'alacarte'  => '🍔 A La Carte',
    'combo'     => '🍱 Combo',
    'beverage'  => '🥤 Beverage',
    'dessert'   => '🍰 Dessert'
];

$kiosk_mode = $kiosk_mode ?? false;
$selected_category = isset($_GET['cat']) && array_key_exists($_GET['cat'], $categories) ? $_GET['cat'] : null;

// If no category selected, show category tiles
if ($kiosk_mode && !$selected_category) {
    $page_title = "Select Category | TAMCC Deli";
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
                position: relative;
            }
            .kiosk-categories {
                background: rgba(255,255,255,0.96);
                border-radius: 3rem;
                padding: 3rem;
                text-align: center;
                max-width: 900px;
                width: 100%;
                animation: fadeInUp 0.5s;
                box-shadow: 0 20px 40px rgba(0,0,0,0.2);
            }
            @keyframes fadeInUp {
                from { opacity:0; transform:translateY(30px); }
                to { opacity:1; transform:translateY(0); }
            }
            h1 {
                font-size: 2.5rem;
                color: #1e3c72;
                margin-bottom: 2rem;
            }
            .category-grid {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
                gap: 1.5rem;
            }
            .category-card {
                background: white;
                border-radius: 2rem;
                padding: 1.5rem;
                text-decoration: none;
                color: #1e3c72;
                font-weight: bold;
                font-size: 1.3rem;
                transition: all 0.2s;
                box-shadow: 0 5px 15px rgba(0,0,0,0.1);
                display: flex;
                align-items: center;
                justify-content: center;
                gap: 0.5rem;
                border: 2px solid transparent;
            }
            .category-card:hover {
                transform: translateY(-5px);
                border-color: #1e3c72;
                background: #f0f7ff;
            }
            .cart-fab {
                position: fixed;
                bottom: 20px;
                right: 20px;
                background: #1e3c72;
                color: white;
                padding: 0.8rem 1.5rem;
                border-radius: 3rem;
                text-decoration: none;
                font-weight: bold;
                box-shadow: 0 5px 15px rgba(0,0,0,0.2);
                transition: transform 0.2s;
                z-index: 100;
            }
            .cart-fab:hover { transform: scale(1.05); background: #2b4c7c; }
        </style>
    </head>
    <body>
        <div class="kiosk-categories">
            <h1>🍽️ What would you like today?</h1>
            <div class="category-grid">
                <?php foreach ($categories as $slug => $name): ?>
                    <a href="<?= kiosk_url('menu.php?cat=' . $slug) ?>" class="category-card">
                        <?= $name ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
        <a href="<?= kiosk_url('/kiosk/cart.php') ?>" class="cart-fab">
            🛒 Cart (<span id="cart-count">0</span>)
        </a>
        <script>
            function updateCartCount() {
                fetch('<?= kiosk_url('/get-cart-count.php') ?>')
                    .then(r => r.json())
                    .then(d => {
                        document.getElementById('cart-count').innerText = d.count;
                    })
                    .catch(() => {});
            }
            updateCartCount();
            setInterval(updateCartCount, 3000);
        </script>
    </body>
    </html>
    <?php
    exit;
}

// If a category is selected, redirect to items.php
if ($selected_category) {
    $category_name = $categories[$selected_category];
    header('Location: ' . kiosk_url('/kiosk/items.php?cat=' . urlencode($category_name)));
    exit;
}

// Fallback
echo "Menu not available.";
?>