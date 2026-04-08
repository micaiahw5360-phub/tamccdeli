<?php
require __DIR__ . '/../includes/session.php';
require __DIR__ . '/../includes/kiosk.php';

$kiosk_mode = true;
$categories = [
    'Combo' => ['emoji' => '🍔', 'color' => '#FF6B35', 'desc' => 'Best Value Meals', 'bg' => 'linear-gradient(135deg, #FF6B35, #FF4757)'],
    'Drinks' => ['emoji' => '🥤', 'color' => '#00CEC9', 'desc' => 'Cold & Refreshing', 'bg' => 'linear-gradient(135deg, #00CEC9, #00B894)'],
    'Breakfast' => ['emoji' => '🍳', 'color' => '#F7C948', 'desc' => 'Start Your Day Right', 'bg' => 'linear-gradient(135deg, #F7C948, #FFA502)'],
    'À la carte' => ['emoji' => '🍽️', 'color' => '#6C5CE7', 'desc' => 'Single Items', 'bg' => 'linear-gradient(135deg, #6C5CE7, #A55EEA)'],
    'Dessert' => ['emoji' => '🍰', 'color' => '#FF69B4', 'desc' => 'Sweet Treats', 'bg' => 'linear-gradient(135deg, #FF69B4, #FF4757)']
];

$page_title = "Select Category | TAMCC Deli Kiosk";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no, viewport-fit=cover">
    <title><?= $page_title ?></title>
    <style>
        * { margin:0; padding:0; box-sizing:border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            overflow-x: hidden;
        }
        .kiosk-categories-page {
            background: linear-gradient(135deg, rgba(0,0,0,0.7), rgba(0,0,0,0.8)), 
                        url('/assets/images/main.menu.png') center/cover fixed;
            min-height: 100vh;
            padding: 2rem;
            position: relative;
        }
        .kiosk-categories-page::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0; bottom: 0;
            background: radial-gradient(circle at 20% 40%, rgba(255,107,53,0.15) 0%, transparent 50%);
            pointer-events: none;
        }
        @keyframes fadeInScale {
            from { opacity: 0; transform: scale(0.95); }
            to { opacity: 1; transform: scale(1); }
        }
        .categories-wrapper {
            max-width: 1400px;
            margin: 0 auto;
            background: rgba(255, 255, 255, 0.96);
            backdrop-filter: blur(10px);
            border-radius: 3rem;
            padding: 3rem;
            box-shadow: 0 30px 60px rgba(0,0,0,0.3);
            animation: fadeInScale 0.5s ease;
            position: relative;
            z-index: 1;
        }
        .categories-header {
            text-align: center;
            margin-bottom: 3rem;
        }
        .categories-header h1 {
            font-size: 3.2rem;
            background: linear-gradient(135deg, #FF6B35, #FF4757, #6C5CE7, #FF69B4);
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
        }
        .category-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 2rem;
        }
        .category-card-fun {
            background: white;
            border-radius: 2rem;
            padding: 2.5rem 1.5rem;
            text-align: center;
            text-decoration: none;
            transition: all 0.3s cubic-bezier(0.34, 1.2, 0.64, 1);
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
            border: 2px solid transparent;
            display: block;
            position: relative;
            overflow: hidden;
        }
        .category-card-fun:hover {
            transform: translateY(-15px) scale(1.03);
            box-shadow: 0 30px 60px rgba(0,0,0,0.25);
            border-color: #FF6B35;
        }
        .category-emoji {
            font-size: 5.5rem;
            display: block;
            margin-bottom: 1rem;
            transition: all 0.3s;
        }
        .category-card-fun:hover .category-emoji {
            transform: scale(1.2) rotate(10deg);
        }
        .category-name {
            font-size: 1.8rem;
            font-weight: 800;
            margin-bottom: 0.5rem;
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
        }
        .category-desc {
            font-size: 1rem;
            color: #888;
        }
        .cart-status {
            position: fixed;
            top: 20px;
            right: 20px;
            background: linear-gradient(135deg, #FF6B35, #FF4757);
            padding: 0.8rem 1.8rem;
            border-radius: 3rem;
            display: flex;
            align-items: center;
            gap: 0.8rem;
            font-weight: bold;
            z-index: 100;
            cursor: pointer;
            color: white;
            box-shadow: 0 0 20px rgba(255,107,53,0.5);
        }
        .cart-status .cart-count-badge {
            background: white;
            color: #FF6B35;
            border-radius: 50%;
            width: 28px;
            height: 28px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-weight: 800;
        }
        @media (max-width: 768px) {
            .categories-wrapper { padding: 1.5rem; }
            .categories-header h1 { font-size: 2rem; }
            .category-name { font-size: 1.3rem; }
            .category-emoji { font-size: 3.5rem; }
        }
    </style>
</head>
<body>
    <div class="kiosk-categories-page">
        <div class="categories-wrapper">
            <div class="categories-header">
                <h1>🍽️ WHAT'S ON YOUR MIND? 🍽️</h1>
            </div>
            <div class="category-grid">
                <?php foreach ($categories as $name => $info): ?>
                    <a href="<?= kiosk_url('/kiosk/items.php?cat=' . urlencode($name)) ?>" 
                       class="category-card-fun" 
                       style="border-bottom: 5px solid <?= $info['color'] ?>">
                        <span class="category-emoji"><?= $info['emoji'] ?></span>
                        <div class="category-name" style="background: <?= $info['bg'] ?>; -webkit-background-clip: text; background-clip: text; color: transparent;">
                            <?= htmlspecialchars($name) ?>
                        </div>
                        <div class="category-desc"><?= $info['desc'] ?></div>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <div class="cart-status" onclick="window.location.href='<?= kiosk_url('/kiosk/cart.php') ?>'">
        🛒 MY CART 
        <span class="cart-count-badge" id="cart-count-display">0</span>
    </div>
    <script>
        function updateCartCount() {
            fetch('<?= kiosk_url('/get-cart-count.php') ?>')
                .then(r => r.json())
                .then(data => {
                    document.getElementById('cart-count-display').textContent = data.count;
                });
        }
        updateCartCount();
        setInterval(updateCartCount, 3000);
    </script>
</body>
</html>