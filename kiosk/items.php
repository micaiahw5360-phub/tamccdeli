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

$category_emoji = ['Combo'=>'🍔','Drinks'=>'🥤','Breakfast'=>'🍳','À la carte'=>'🍽️','Dessert'=>'🍰'];
$emoji = $category_emoji[$category] ?? '🍽️';
$page_title = "$category | TAMCC Deli Kiosk";
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
            background: linear-gradient(135deg, #f5f7fa 0%, #e9ecef 100%);
            min-height: 100vh;
        }
        .kiosk-items-page { padding: 2rem; }
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
            padding: 0.8rem 1.8rem;
            border-radius: 3rem;
            text-decoration: none;
            font-weight: 600;
            transition: 0.3s;
        }
        .back-btn:hover { transform: translateX(-5px); }
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
        @keyframes fadeInUp {
            from { opacity:0; transform:translateY(30px); }
            to { opacity:1; transform:translateY(0); }
        }
        .item-card-fun {
            background: white;
            border-radius: 2rem;
            overflow: hidden;
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
            transition: all 0.3s cubic-bezier(0.34,1.2,0.64,1);
        }
        .item-card-fun:hover { transform: translateY(-10px); box-shadow: 0 30px 50px rgba(0,0,0,0.2); }
        .item-image {
            width: 100%;
            height: 200px;
            object-fit: cover;
            transition: transform 0.3s;
        }
        .item-card-fun:hover .item-image { transform: scale(1.05); }
        .item-info { padding: 1.5rem; }
        .item-name { font-size: 1.4rem; font-weight: 800; margin-bottom: 0.5rem; }
        .item-price { font-size: 2rem; font-weight: 800; background: linear-gradient(135deg, #FF6B35, #FF4757); -webkit-background-clip: text; background-clip: text; color: transparent; margin-bottom: 1rem; }
        .options-section {
            background: linear-gradient(135deg, #f8f9fa, #f1f3f5);
            border-radius: 1.5rem;
            padding: 1rem;
            margin: 1rem 0;
        }
        .option-group-fun { margin-bottom: 1rem; }
        .option-label { font-weight: 700; margin-bottom: 0.5rem; color: #555; }
        .radio-group-fun { display: flex; flex-wrap: wrap; gap: 0.8rem; }
        .radio-option-fun {
            background: white;
            border: 2px solid #e5e7eb;
            border-radius: 3rem;
            padding: 0.6rem 1.2rem;
            cursor: pointer;
            transition: 0.2s;
            font-size: 0.9rem;
        }
        .radio-option-fun.selected {
            background: linear-gradient(135deg, #FF6B35, #FF4757);
            color: white;
            border-color: transparent;
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
            color: #FF6B35;
        }
        .qty-value-fun { font-size: 1.3rem; font-weight: 700; min-width: 40px; text-align: center; }
        .add-btn-fun {
            flex: 1;
            background: linear-gradient(135deg, #00D25B, #00CEC9);
            color: white;
            border: none;
            padding: 0.8rem;
            border-radius: 3rem;
            font-weight: 700;
            cursor: pointer;
        }
        .cart-floating-fun {
            position: fixed;
            bottom: 30px;
            right: 30px;
            background: linear-gradient(135deg, #FF6B35, #FF4757);
            color: white;
            border-radius: 4rem;
            padding: 1rem 2rem;
            font-weight: bold;
            box-shadow: 0 0 20px rgba(255,107,53,0.5);
            z-index: 1000;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 0.8rem;
            animation: bounce 2s infinite;
        }
        @keyframes bounce { 0%,100%{transform:translateY(0);} 50%{transform:translateY(-12px);} }
        .success-toast {
            position: fixed;
            bottom: 120px;
            right: 30px;
            background: #00D25B;
            color: white;
            padding: 1rem 2rem;
            border-radius: 3rem;
            z-index: 1001;
            animation: slideInRight 0.3s ease;
        }
        @keyframes slideInRight {
            from { opacity:0; transform:translateX(100px); }
            to { opacity:1; transform:translateX(0); }
        }
        @media (max-width:768px){
            .items-grid-fun{grid-template-columns:1fr;}
            .cart-floating-fun{padding:0.7rem 1.5rem; font-size:1rem;}
        }
    </style>
</head>
<body>
<div class="kiosk-items-page">
    <div class="items-header">
        <a href="<?= kiosk_url('/kiosk/categories.php') ?>" class="back-btn">← BACK TO MENU</a>
        <div class="category-title"><?= $emoji ?> <?= htmlspecialchars($category) ?> <?= $emoji ?></div>
        <div></div>
    </div>
    <div class="items-grid-fun">
        <?php foreach ($items as $item): ?>
            <div class="item-card-fun" data-item-id="<?= $item['id'] ?>" data-base-price="<?= $item['price'] ?>">
                <?php if ($item['image']): ?>
                    <img src="<?= htmlspecialchars($item['image']) ?>" class="item-image">
                <?php else: ?>
                    <div class="item-image" style="background:linear-gradient(135deg,#FF6B35,#FF4757); display:flex; align-items:center; justify-content:center; font-size:4rem;">🍽️</div>
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

<script>
// Update cart count
function updateCartCount() {
    fetch('<?= kiosk_url('/get-cart-count.php') ?>')
        .then(r => r.json())
        .then(data => document.getElementById('cart-count').textContent = data.count);
}
updateCartCount();
setInterval(updateCartCount, 3000);

// Price update & option selection logic
document.querySelectorAll('.item-card-fun').forEach(card => {
    const basePrice = parseFloat(card.dataset.basePrice);
    const priceSpan = card.querySelector('.item-price');
    const optionGroups = card.querySelectorAll('.option-group-fun');
    const radioOptions = card.querySelectorAll('.radio-option-fun');
    
    function updatePrice() {
        let modifier = 0;
        card.querySelectorAll('.radio-option-fun.selected').forEach(opt => {
            modifier += parseFloat(opt.dataset.price || 0);
        });
        let newPrice = basePrice + modifier;
        priceSpan.textContent = `$${newPrice.toFixed(2)}`;
    }
    
    radioOptions.forEach(opt => {
        opt.addEventListener('click', function(e) {
            const group = this.closest('.option-group-fun');
            const required = group.dataset.required === '1';
            // Deselect others in same group
            group.querySelectorAll('.radio-option-fun').forEach(o => o.classList.remove('selected'));
            this.classList.add('selected');
            updatePrice();
        });
        // Auto-select first if required? We'll leave to user, but validation on add
    });
    
    // Quantity controls
    const qtySpan = card.querySelector('.qty-value-fun');
    const decBtn = card.querySelector('.dec-btn');
    const incBtn = card.querySelector('.inc-btn');
    let qty = 1;
    decBtn.addEventListener('click', () => { if (qty > 1) qty--; qtySpan.textContent = qty; });
    incBtn.addEventListener('click', () => { qty++; qtySpan.textContent = qty; });
    
    // Add to cart AJAX (uses your backend)
    const addBtn = card.querySelector('.add-btn-fun');
    addBtn.addEventListener('click', async () => {
        const options = {};
        let valid = true;
        card.querySelectorAll('.option-group-fun').forEach(group => {
            const selected = group.querySelector('.radio-option-fun.selected');
            if (group.dataset.required === '1' && !selected) {
                alert('Please select ' + group.querySelector('.option-label').innerText);
                valid = false;
            }
            if (selected) options[group.dataset.optionId] = selected.dataset.valueId;
        });
        if (!valid) return;
        
        const formData = new URLSearchParams();
        formData.append('csrf_token', '<?= generateToken() ?>');
        formData.append('item_id', card.dataset.itemId);
        formData.append('quantity', qty);
        formData.append('options', JSON.stringify(options));
        
        const response = await fetch('<?= kiosk_url('/cart.php?action=add') ?>', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
            body: formData
        });
        const data = await response.json();
        if (data.success) {
            updateCartCount();
            // Show toast
            let toast = document.createElement('div');
            toast.className = 'success-toast';
            toast.textContent = '✓ Added to cart!';
            document.body.appendChild(toast);
            setTimeout(() => toast.remove(), 2000);
        } else {
            alert('Error adding item');
        }
    });
});
</script>
</body>
</html>