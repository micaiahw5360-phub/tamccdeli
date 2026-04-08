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

$category_emoji = [
    'Combo' => '🍔',
    'Drinks' => '🥤', 
    'Breakfast' => '🍳',
    'À la carte' => '🍽️',
    'Dessert' => '🍰'
];
$emoji = $category_emoji[$category] ?? '🍽️';

$page_title = "$category | TAMCC Deli Kiosk";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no, viewport-fit=cover">
    <title><?= $page_title ?></title>
    <link rel="stylesheet" href="/assets/css/global.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            overflow-x: hidden;
        }

        .kiosk-items-page {
            background: linear-gradient(135deg, #f5f7fa 0%, #e9ecef 100%);
            min-height: 100vh;
            padding: 2rem;
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes slideInRight {
            from {
                opacity: 0;
                transform: translateX(100px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        .items-header {
            background: white;
            border-radius: 2rem;
            padding: 1.5rem 2rem;
            margin-bottom: 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1rem;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
        }

        .back-btn {
            background: linear-gradient(135deg, #6C5CE7, #FF69B4);
            color: white;
            border: none;
            padding: 0.8rem 1.8rem;
            border-radius: 3rem;
            font-size: 1.1rem;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.3s;
        }

        .back-btn:hover {
            transform: translateX(-5px);
            box-shadow: 0 5px 15px rgba(108,92,231,0.4);
        }

        .category-title {
            font-size: 2.2rem;
            font-weight: 800;
            background: linear-gradient(135deg, #FF6B35, #FF4757);
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
        }

        .items-grid-fun {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(340px, 1fr));
            gap: 2rem;
            animation: fadeInUp 0.5s ease;
        }

        .item-card-fun {
            background: white;
            border-radius: 2rem;
            overflow: hidden;
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
            transition: all 0.3s cubic-bezier(0.34, 1.2, 0.64, 1);
            cursor: pointer;
        }

        .item-card-fun:hover {
            transform: translateY(-10px);
            box-shadow: 0 30px 50px rgba(0,0,0,0.2);
        }

        .item-image {
            width: 100%;
            height: 200px;
            object-fit: cover;
            transition: transform 0.3s;
        }

        .item-card-fun:hover .item-image {
            transform: scale(1.05);
        }

        .item-info {
            padding: 1.5rem;
        }

        .item-name {
            font-size: 1.4rem;
            font-weight: 800;
            margin-bottom: 0.5rem;
            color: #333;
        }

        .item-price {
            font-size: 2rem;
            font-weight: 800;
            background: linear-gradient(135deg, #FF6B35, #FF4757);
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
            margin-bottom: 1rem;
        }

        .options-section {
            background: linear-gradient(135deg, #f8f9fa, #f1f3f5);
            border-radius: 1.5rem;
            padding: 1rem;
            margin: 1rem 0;
        }

        .option-group-fun {
            margin-bottom: 1rem;
        }

        .option-label {
            font-weight: 700;
            margin-bottom: 0.5rem;
            display: block;
            color: #555;
        }

        .radio-group-fun {
            display: flex;
            flex-wrap: wrap;
            gap: 0.8rem;
        }

        .radio-option-fun {
            background: white;
            border: 2px solid #e5e7eb;
            border-radius: 3rem;
            padding: 0.6rem 1.2rem;
            cursor: pointer;
            transition: all 0.2s;
            font-size: 0.9rem;
            font-weight: 500;
        }

        .radio-option-fun:hover {
            border-color: #FF6B35;
            transform: scale(1.02);
        }

        .radio-option-fun.selected {
            background: linear-gradient(135deg, #FF6B35, #FF4757);
            color: white;
            border-color: transparent;
            box-shadow: 0 5px 15px rgba(255,107,53,0.3);
        }

        .item-actions-fun {
            display: flex;
            gap: 1rem;
            align-items: center;
            margin-top: 1rem;
        }

        .qty-control-fun {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            background: #f1f3f5;
            border-radius: 3rem;
            padding: 0.3rem;
        }

        .qty-btn-fun {
            background: white;
            border: none;
            width: 48px;
            height: 48px;
            border-radius: 50%;
            font-size: 1.5rem;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.2s;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            color: #FF6B35;
        }

        .qty-btn-fun:active {
            transform: scale(0.9);
        }

        .qty-value-fun {
            font-size: 1.3rem;
            font-weight: 700;
            min-width: 40px;
            text-align: center;
        }

        .add-btn-fun {
            flex: 1;
            background: linear-gradient(135deg, #00D25B, #00CEC9);
            color: white;
            border: none;
            padding: 0.8rem;
            border-radius: 3rem;
            font-size: 1rem;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.2s;
        }

        .add-btn-fun:active {
            transform: scale(0.98);
        }

        .cart-floating-fun {
            position: fixed;
            bottom: 30px;
            right: 30px;
            background: linear-gradient(135deg, #FF6B35, #FF4757);
            color: white;
            border-radius: 4rem;
            padding: 1rem 2rem;
            font-size: 1.3rem;
            font-weight: bold;
            box-shadow: 0 0 20px rgba(255,107,53,0.5);
            z-index: 1000;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 0.8rem;
            transition: all 0.3s;
            animation: bounce 2s infinite;
            border: 2px solid rgba(255,255,255,0.5);
        }

        @keyframes bounce {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-12px); }
        }

        .cart-floating-fun:hover {
            transform: scale(1.08);
            box-shadow: 0 0 30px rgba(255,107,53,0.8);
        }

        .success-toast {
            position: fixed;
            bottom: 120px;
            right: 30px;
            background: linear-gradient(135deg, #00D25B, #00CEC9);
            color: white;
            padding: 1rem 2rem;
            border-radius: 3rem;
            font-weight: bold;
            z-index: 1001;
            animation: slideInRight 0.3s ease;
            box-shadow: 0 5px 20px rgba(0,210,91,0.4);
            font-size: 1.1rem;
        }

        .confetti-canvas {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            pointer-events: none;
            z-index: 9999;
        }

        @media (max-width: 768px) {
            .items-grid-fun {
                grid-template-columns: 1fr;
            }
            .cart-floating-fun {
                padding: 0.7rem 1.5rem;
                font-size: 1rem;
                bottom: 20px;
                right: 20px;
            }
            .category-title {
                font-size: 1.5rem;
            }
        }
    </style>
</head>
<body>
    <div class="kiosk-items-page">
        <div class="items-header">
            <a href="<?= kiosk_url('/kiosk/categories.php') ?>" class="back-btn">
                ← BACK TO MENU
            </a>
            <div class="category-title"><?= $emoji ?> <?= htmlspecialchars($category) ?> <?= $emoji ?></div>
            <div></div>
        </div>
        
        <div class="items-grid-fun">
            <?php foreach ($items as $item): ?>
                <div class="item-card-fun" data-item-id="<?= $item['id'] ?>" data-base-price="<?= $item['price'] ?>">
                    <?php if ($item['image']): ?>
                        <img src="<?= htmlspecialchars($item['image']) ?>" class="item-image" alt="<?= htmlspecialchars($item['name']) ?>">
                    <?php else: ?>
                        <div class="item-image" style="background: linear-gradient(135deg, #FF6B35, #FF4757); display: flex; align-items: center; justify-content: center; font-size: 4rem;">
                            🍽️
                        </div>
                    <?php endif; ?>
                    <div class="item-info">
                        <div class="item-name"><?= htmlspecialchars($item['name']) ?></div>
                        <div class="item-price" id="price-<?= $item['id'] ?>">$<?= number_format($item['price'], 2) ?></div>
                        
                        <?php if (!empty($item['options'])): ?>
                            <div class="options-section">
                                <?php foreach ($item['options'] as $opt): ?>
                                    <div class="option-group-fun" data-option-id="<?= $opt['id'] ?>" data-required="<?= $opt['required'] ?>">
                                        <div class="option-label"><?= htmlspecialchars($opt['option_name']) ?> <?= $opt['required'] ? '⚠️ Required' : '' ?></div>
                                        <div class="radio-group-fun">
                                            <?php foreach ($opt['values'] as $val): ?>
                                                <div class="radio-option-fun" data-value-id="<?= $val['id'] ?>" data-price="<?= $val['price_modifier'] ?>">
                                                    <?= htmlspecialchars($val['value_name']) ?>
                                                    <?php if ($val['price_modifier'] != 0): ?>
                                                        <?= ($val['price_modifier'] > 0 ? '➕' : '➖') ?>$<?= number_format(abs($val['price_modifier']), 2) ?>
                                                    <?php endif; ?>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                        
                        <div class="item-actions-fun">
                            <div class="qty-control-fun">
                                <button class="qty-btn-fun dec-btn">−</button>
                                <span class="qty-value-fun">1</span>
                                <button class="qty-btn-fun inc-btn">+</button>
                            </div>
                            <button class="add-btn-fun">➕ ADD TO ORDER</button>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    
    <div class="cart-floating-fun" onclick="window.location.href='<?= kiosk_url('/kiosk/cart.php') ?>'">
        🛒 CART (<span id="cart-count">0</span>)
    </div>
    
    <canvas id="confetti-canvas" class="confetti-canvas" style="display: none;"></canvas>
    
    <script>
        // Update cart count
        function updateCartCount() {
            fetch('<?= kiosk_url('/get-cart-count.php') ?>')
                .then(r => r.json())
                .then(data => {
                    document.getElementById('cart-count').textContent = data.count;
                })
                .catch(console.error);
        }
        updateCartCount();
        setInterval(updateCartCount, 3000);
        
        // Confetti effect
        function showConfetti() {
            const canvas = document.getElementById('confetti-canvas');
            canvas.style.display = 'block';
            const ctx = canvas.getContext('2d');
            canvas.width = window.innerWidth;
            canvas.height = window.innerHeight;
            
            const colors = ['#FF6B35', '#FF4757', '#F7C948', '#00D25B', '#6C5CE7', '#FF69B4', '#00CEC9'];
            const particles = [];
            
            for (let i = 0; i < 100; i++) {
                particles.push({
                    x: Math.random() * canvas.width,
                    y: Math.random() * canvas.height - canvas.height,
                    size: Math.random() * 8 + 4,
                    color: colors[Math.floor(Math.random() * colors.length)],
                    speed: Math.random() * 6 + 3,
                    rotation: Math.random() * 360,
                    rotationSpeed: Math.random() * 15 - 7.5
                });
            }
            
            let startTime = Date.now();
            
            function animate() {
                if (Date.now() - startTime > 1500) {
                    ctx.clearRect(0, 0, canvas.width, canvas.height);
                    canvas.style.display = 'none';
                    return;
                }
                
                ctx.clearRect(0, 0, canvas.width, canvas.height);
                
                particles.forEach(p => {
                    p.y += p.speed;
                    p.rotation += p.rotationSpeed;
                    
                    ctx.save();
                    ctx.translate(p.x, p.y);
                    ctx.rotate(p.rotation * Math.PI / 180);
                    ctx.fillStyle = p.color;
                    ctx.fillRect(-p.size/2, -p.size/2, p.size, p.size);
                    ctx.restore();
                    
                    if (p.y > canvas.height) {
                        p.y