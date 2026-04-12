<?php
require __DIR__ . '/../includes/session.php';
require __DIR__ . '/../config/database.php';
require __DIR__ . '/../includes/kiosk.php';
require __DIR__ . '/../includes/functions.php';
require __DIR__ . '/../includes/csrf.php';

$kiosk_mode = true;
$category = isset($_GET['cat']) ? $_GET['cat'] : '';
if (!$category) {
    header('Location: ' . kiosk_url('/kiosk/menu.php'));
    exit;
}

$stmt = $conn->prepare("SELECT * FROM menu_items WHERE category = ? AND is_available = 1 ORDER BY sort_order, name");
$stmt->bind_param("s", $category);
$stmt->execute();
$items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
foreach ($items as &$item) {
    $item['options'] = getItemOptions($conn, $item['id']);
}

$category_emoji = [
    'Breakfast' => '🍳',
    'A La Carte' => '🍽️',
    'Combo' => '🍔',
    'Beverage' => '🥤',
    'Dessert' => '🍰'
];
$emoji = $category_emoji[$category] ?? '🍽️';

$page_title = "$category | TAMCC Deli Kiosk";
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
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 2rem;
        }
        .kiosk-items-page { max-width: 1400px; margin: 0 auto; }
        .items-header {
            background: rgba(255,255,255,0.95);
            border-radius: 2rem;
            padding: 1rem 2rem;
            margin-bottom: 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1rem;
        }
        .back-btn {
            background: linear-gradient(135deg, #6C5CE7, #FF69B4);
            color: white;
            padding: 0.8rem 1.8rem;
            border-radius: 3rem;
            text-decoration: none;
            font-weight: bold;
            transition: transform 0.2s;
        }
        .back-btn:hover { transform: translateX(-5px); }
        .category-title {
            font-size: 2rem;
            font-weight: 800;
            background: linear-gradient(135deg, #FF6B35, #FF4757);
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
        }
        .items-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(340px, 1fr));
            gap: 2rem;
            animation: fadeInUp 0.5s ease;
        }
        @keyframes fadeInUp {
            from { opacity:0; transform:translateY(30px); }
            to { opacity:1; transform:translateY(0); }
        }
        .item-card {
            background: white;
            border-radius: 2rem;
            overflow: hidden;
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
            transition: transform 0.3s;
        }
        .item-card:hover { transform: translateY(-10px); }
        .item-image {
            width: 100%;
            height: 200px;
            object-fit: cover;
        }
        .item-info { padding: 1.5rem; }
        .item-name { font-size: 1.4rem; font-weight: 800; margin-bottom: 0.5rem; }
        .item-price { font-size: 1.8rem; font-weight: 800; color: #FF6B35; margin-bottom: 1rem; }
        .options-section {
            background: #f8f9fa;
            border-radius: 1rem;
            padding: 1rem;
            margin: 1rem 0;
        }
        .option-group { margin-bottom: 1rem; }
        .option-label { font-weight: 700; margin-bottom: 0.5rem; }
        .radio-group { display: flex; flex-wrap: wrap; gap: 0.5rem; }
        .radio-option {
            background: white;
            border: 2px solid #e2e8f0;
            border-radius: 2rem;
            padding: 0.5rem 1rem;
            cursor: pointer;
            transition: all 0.2s;
        }
        .radio-option.selected {
            background: linear-gradient(135deg, #FF6B35, #FF4757);
            color: white;
            border-color: transparent;
        }
        .item-actions {
            display: flex;
            gap: 1rem;
            align-items: center;
            margin-top: 1rem;
        }
        .qty-control {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            background: #f1f5f9;
            border-radius: 3rem;
            padding: 0.3rem;
        }
        .qty-btn {
            background: white;
            width: 48px;
            height: 48px;
            border-radius: 50%;
            border: none;
            font-size: 1.5rem;
            font-weight: bold;
            cursor: pointer;
            color: #FF6B35;
        }
        .add-btn {
            flex: 1;
            background: linear-gradient(135deg, #00D25B, #00CEC9);
            color: white;
            border: none;
            padding: 0.8rem;
            border-radius: 3rem;
            font-weight: bold;
            cursor: pointer;
        }
        .cart-floating {
            position: fixed;
            bottom: 30px;
            right: 30px;
            background: linear-gradient(135deg, #FF6B35, #FF4757);
            color: white;
            padding: 1rem 2rem;
            border-radius: 4rem;
            font-weight: bold;
            cursor: pointer;
            box-shadow: 0 0 20px rgba(255,107,53,0.5);
            animation: bounce 2s infinite;
            z-index: 1000;
        }
        @keyframes bounce {
            0%,100%{ transform:translateY(0); }
            50%{ transform:translateY(-12px); }
        }
        .toast {
            position: fixed;
            bottom: 120px;
            right: 30px;
            background: #00D25B;
            color: white;
            padding: 0.8rem 1.5rem;
            border-radius: 2rem;
            animation: fadeInUp 0.3s;
            z-index: 1001;
        }
        canvas { position: fixed; top:0; left:0; pointer-events: none; z-index: 9999; display: none; }
    </style>
</head>
<body>
<div class="kiosk-items-page">
    <div class="items-header">
        <a href="<?= kiosk_url('/kiosk/menu.php') ?>" class="back-btn">← BACK</a>
        <div class="category-title"><?= $emoji ?> <?= htmlspecialchars($category) ?> <?= $emoji ?></div>
        <div></div>
    </div>
    <div class="items-grid">
        <?php foreach ($items as $item): ?>
            <div class="item-card" data-item-id="<?= $item['id'] ?>" data-base-price="<?= $item['price'] ?>">
                <?php if ($item['image']): ?>
                    <img src="<?= htmlspecialchars($item['image']) ?>" class="item-image">
                <?php else: ?>
                    <div class="item-image" style="background: linear-gradient(135deg, #FF6B35, #FF4757); display: flex; align-items: center; justify-content: center; font-size: 4rem;">🍽️</div>
                <?php endif; ?>
                <div class="item-info">
                    <div class="item-name"><?= htmlspecialchars($item['name']) ?></div>
                    <div class="item-price" id="price-<?= $item['id'] ?>">$<?= number_format($item['price'], 2) ?></div>
                    <?php if (!empty($item['options'])): ?>
                        <div class="options-section">
                            <?php foreach ($item['options'] as $opt): ?>
                                <div class="option-group" data-option-id="<?= $opt['id'] ?>">
                                    <div class="option-label"><?= htmlspecialchars($opt['option_name']) ?> <?= $opt['required'] ? '⚠️' : '' ?></div>
                                    <div class="radio-group">
                                        <?php foreach ($opt['values'] as $val): ?>
                                            <div class="radio-option" data-value-id="<?= $val['id'] ?>" data-price="<?= $val['price_modifier'] ?>">
                                                <?= htmlspecialchars($val['value_name']) ?>
                                                <?php if ($val['price_modifier'] != 0): ?>
                                                    <?= ($val['price_modifier'] > 0 ? '+' : '-') ?>$<?= number_format(abs($val['price_modifier']), 2) ?>
                                                <?php endif; ?>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                    <div class="item-actions">
                        <div class="qty-control">
                            <button class="qty-btn dec">−</button>
                            <span class="qty-val">1</span>
                            <button class="qty-btn inc">+</button>
                        </div>
                        <button class="add-btn">➕ ADD TO ORDER</button>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>
<div class="cart-floating" onclick="window.location.href='<?= kiosk_url('/kiosk/cart.php') ?>'">
    🛒 CART (<span id="cart-count">0</span>)
</div>
<canvas id="confetti-canvas"></canvas>

<script>
    function updateCartCount() {
        fetch('<?= kiosk_url('/get-cart-count.php') ?>')
            .then(r => r.json())
            .then(data => document.getElementById('cart-count').innerText = data.count);
    }
    updateCartCount();
    setInterval(updateCartCount, 3000);

    // Option selection & price update
    document.querySelectorAll('.item-card').forEach(card => {
        const basePrice = parseFloat(card.dataset.basePrice);
        const priceSpan = card.querySelector('.item-price');
        const optionGroups = card.querySelectorAll('.option-group');
        const radioOptions = card.querySelectorAll('.radio-option');
        const decBtn = card.querySelector('.dec');
        const incBtn = card.querySelector('.inc');
        const qtySpan = card.querySelector('.qty-val');
        const addBtn = card.querySelector('.add-btn');

        let quantity = 1;

        function updatePrice() {
            let modifier = 0;
            for (let group of optionGroups) {
                const selected = group.querySelector('.radio-option.selected');
                if (selected) {
                    modifier += parseFloat(selected.dataset.price || 0);
                }
            }
            const total = basePrice + modifier;
            priceSpan.innerText = '$' + total.toFixed(2);
            return total;
        }

        radioOptions.forEach(opt => {
            opt.addEventListener('click', () => {
                const parent = opt.closest('.option-group');
                parent.querySelectorAll('.radio-option').forEach(o => o.classList.remove('selected'));
                opt.classList.add('selected');
                updatePrice();
            });
        });

        decBtn.addEventListener('click', () => {
            if (quantity > 1) quantity--;
            qtySpan.innerText = quantity;
        });
        incBtn.addEventListener('click', () => {
            if (quantity < 10) quantity++;
            qtySpan.innerText = quantity;
        });

        addBtn.addEventListener('click', () => {
            const options = {};
            optionGroups.forEach(group => {
                const selected = group.querySelector('.radio-option.selected');
                if (selected) {
                    const optId = group.dataset.optionId;
                    const valId = selected.dataset.valueId;
                    options[optId] = valId;
                }
            });
            // Check required options
            let missing = false;
            optionGroups.forEach(group => {
                const label = group.querySelector('.option-label');
                if (label.innerText.includes('⚠️') && !group.querySelector('.radio-option.selected')) {
                    missing = true;
                    alert('Please select ' + label.innerText.replace('⚠️',''));
                }
            });
            if (missing) return;

            fetch('<?= kiosk_url('/kiosk/add-to-cart.php') ?>', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({
                    csrf_token: '<?= generateToken() ?>',
                    item_id: card.dataset.itemId,
                    quantity: quantity,
                    options: JSON.stringify(options)
                })
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    showToast('✅ Added to cart!');
                    updateCartCount();
                    showConfetti();
                } else {
                    alert('Error: ' + data.error);
                }
            });
        });
    });

    function showToast(msg) {
        let toast = document.createElement('div');
        toast.className = 'toast';
        toast.innerText = msg;
        document.body.appendChild(toast);
        setTimeout(() => toast.remove(), 2000);
    }

    function showConfetti() {
        const canvas = document.getElementById('confetti-canvas');
        canvas.style.display = 'block';
        canvas.width = window.innerWidth;
        canvas.height = window.innerHeight;
        const ctx = canvas.getContext('2d');
        const colors = ['#FF6B35', '#FF4757', '#00D25B', '#6C5CE7', '#FF69B4'];
        let particles = [];
        for (let i = 0; i < 100; i++) {
            particles.push({
                x: Math.random() * canvas.width,
                y: Math.random() * canvas.height - canvas.height,
                size: Math.random() * 8 + 4,
                color: colors[Math.floor(Math.random() * colors.length)],
                speed: Math.random() * 6 + 3,
                rotation: Math.random() * 360,
                rotSpeed: Math.random() * 15 - 7.5
            });
        }
        let start = Date.now();
        function animate() {
            if (Date.now() - start > 1500) {
                ctx.clearRect(0, 0, canvas.width, canvas.height);
                canvas.style.display = 'none';
                return;
            }
            ctx.clearRect(0, 0, canvas.width, canvas.height);
            particles.forEach(p => {
                p.y += p.speed;
                p.rotation += p.rotSpeed;
                ctx.save();
                ctx.translate(p.x, p.y);
                ctx.rotate(p.rotation * Math.PI / 180);
                ctx.fillStyle = p.color;
                ctx.fillRect(-p.size/2, -p.size/2, p.size, p.size);
                ctx.restore();
                if (p.y > canvas.height) p.y = -p.size;
            });
            requestAnimationFrame(animate);
        }
        animate();
    }
</script>
</body>
</html>