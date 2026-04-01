<?php
require __DIR__ . '/../includes/session.php';
require __DIR__ . '/../includes/kiosk.php';

$kiosk_mode = true;
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
    <style>
        /* Same inline CSS as home.php plus additional for categories */
        :root { /* same as above – copy from home.php */ }
        * { margin:0; padding:0; box-sizing:border-box; }
        body { font-family: var(--font-sans); background:#f8f9fa; min-height:100vh; display:flex; align-items:center; justify-content:center; padding:var(--space-4); }
        .kiosk { max-width:1400px; width:100%; background:rgba(255,255,255,0.95); border-radius:var(--radius-xl); box-shadow:var(--shadow-xl); backdrop-filter:blur(8px); overflow:hidden; min-height:80vh; display:flex; flex-direction:column; }
        .screen { padding:var(--space-8); flex:1; }
        h1 { font-size:var(--text-4xl); font-weight:700; margin-bottom:var(--space-4); color:var(--primary-700); }
        .time { text-align:right; font-size:var(--text-lg); color:var(--neutral-500); margin-bottom:var(--space-6); }
        .card-grid { display:grid; grid-template-columns:repeat(auto-fill, minmax(280px,1fr)); gap:var(--space-6); margin-top:var(--space-8); }
        .category-card { background:white; border:3px solid var(--primary-600); border-radius:1rem; padding:2rem; text-align:center; font-size:clamp(1.5rem,5vw,3rem); font-weight:bold; color:var(--primary-600); text-decoration:none; transition:all 0.2s; display:flex; align-items:center; justify-content:center; min-height:200px; }
        .category-card:hover { background:#e9ecef; transform:scale(1.02); border-color:var(--primary-700); color:var(--primary-700); box-shadow:0 12px 24px rgba(0,0,0,0.15); }
        .cart-floating { position:fixed; bottom:30px; right:30px; background:#28a745; color:white; border-radius:60px; padding:1rem 2rem; font-size:clamp(1rem,3vw,2rem); box-shadow:0 8px 16px rgba(0,0,0,0.2); z-index:1000; cursor:pointer; }
        .cart-floating .cart-count { background:white; color:#28a745; border-radius:50%; padding:0.3rem 0.8rem; margin-left:0.8rem; font-weight:bold; }
        @media (max-width:768px) { .card-grid { grid-template-columns:1fr; } .category-card { min-height:150px; } }
    </style>
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
    </script>
</body>
</html>