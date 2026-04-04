<?php
require __DIR__ . '/includes/session.php';
require 'config/database.php';
require 'includes/csrf.php';
require_once __DIR__ . '/includes/kiosk.php';
require 'includes/functions.php';

$categories = [
    'breakfast' => 'Breakfast',
    'alacarte'  => 'A La Carte',
    'combo'     => 'Combo',
    'beverage'  => 'Beverage',
    'dessert'   => 'Dessert'
];

$kiosk_mode = $kiosk_mode ?? false;
$selected_category = isset($_GET['cat']) && array_key_exists($_GET['cat'], $categories) ? $_GET['cat'] : null;

// In kiosk mode with no category selected → show category tiles
if ($kiosk_mode && !$selected_category) {
    $page_title = "Select Category | TAMCC Deli";
    include 'includes/header.php';
    ?>
    <div class="kiosk-categories-container">
        <h1>Select Your Meal</h1>
        <div class="kiosk-categories">
            <?php foreach ($categories as $slug => $name): ?>
                <a href="<?= kiosk_url('menu.php?cat=' . $slug) ?>" class="kiosk-category">
                    <?= htmlspecialchars($name) ?>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
    <a href="<?= kiosk_url('cart.php') ?>" class="floating-cart">
        <span class="dashicons dashicons-cart"></span>
        <span class="cart-count" id="cart-count-kiosk">0</span>
    </a>
    <?php
    include 'includes/footer.php';
    exit;
}

// Normal or kiosk category view
$stmt = $conn->prepare("SELECT * FROM menu_items " . 
    ($selected_category ? "WHERE LOWER(category) = LOWER(?) " : "") . 
    "ORDER BY FIELD(category, 'Breakfast', 'A La Carte', 'Combo', 'Beverage', 'Dessert'), sort_order, name");

if ($selected_category) {
    $cat_name = $categories[$selected_category];
    $stmt->bind_param("s", $cat_name);
}
$stmt->execute();
$items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
foreach ($items as &$item) {
    $item['options'] = getItemOptions($conn, $item['id']);
}

// Group items by category for normal mode
$menu_items = [];
if (!$kiosk_mode) {
    foreach ($items as $item) {
        $menu_items[$item['category']][] = $item;
    }
}

$page_title = $kiosk_mode ? $categories[$selected_category] . " | TAMCC Deli" : "Menu | TAMCC Deli";
include 'includes/header.php';
?>

<style>
    /* Hover card effect */
    .menu-card {
        transition: transform 0.2s, box-shadow 0.2s;
        cursor: pointer;
    }
    .menu-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1), 0 10px 10px -5px rgba(0,0,0,0.02);
    }
    /* Options dialog */
    .option-dialog-overlay {
        position: fixed;
        inset: 0;
        background: rgba(0,0,0,0.5);
        display: flex;
        align-items: center;
        justify-content: center;
        z-index: 1000;
    }
    .option-dialog {
        background: white;
        border-radius: 1rem;
        max-width: 500px;
        width: 90%;
        max-height: 85vh;
        overflow-y: auto;
        padding: 1.5rem;
    }
    <?php if ($kiosk_mode): ?>
    .option-dialog { max-width: 600px; font-size: 1.2rem; }
    .option-dialog label { font-size: 1.1rem; }
    .option-dialog input, .option-dialog select { font-size: 1.1rem; }
    <?php endif; ?>
</style>

<?php if ($kiosk_mode && $selected_category): ?>
    <div class="kiosk-header">
        <a href="<?= kiosk_url('menu.php') ?>" class="back-to-categories">← Back to Categories</a>
        <h1><?= htmlspecialchars($categories[$selected_category]) ?></h1>
    </div>
<?php else: ?>
    <div class="menu-search-container">
        <input type="text" id="menu-search" class="menu-search" placeholder="Search menu...">
    </div>
    <div class="category-filter">
        <button class="filter-btn active" data-category="all">All</button>
        <?php foreach ($categories as $slug => $name): ?>
            <button class="filter-btn" data-category="<?= $slug ?>"><?= $name ?></button>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<div class="menu-container">
    <?php if ($kiosk_mode && $selected_category): ?>
        <div class="items-grid">
            <?php foreach ($items as $item): ?>
                <div class="menu-card menu-item" data-id="<?= $item['id'] ?>" data-name="<?= htmlspecialchars($item['name']) ?>" data-price="<?= $item['price'] ?>" data-image="<?= htmlspecialchars($item['image'] ?? '') ?>" data-description="<?= htmlspecialchars($item['description'] ?? '') ?>" data-options='<?= json_encode($item['options']) ?>'>
                    <div class="aspect-square overflow-hidden bg-gray-100">
                        <img src="<?= htmlspecialchars($item['image'] ?? 'https://via.placeholder.com/300?text=No+Image') ?>" alt="<?= htmlspecialchars($item['name']) ?>" class="w-full h-full object-cover transition-transform duration-300 group-hover:scale-105">
                    </div>
                    <div class="menu-item-content">
                        <div class="mb-2">
                            <span class="text-xs font-medium text-blue-600 bg-blue-50 px-2 py-1 rounded"><?= htmlspecialchars($item['category']) ?></span>
                        </div>
                        <h3 class="menu-item-name font-bold mb-2 <?= $kiosk_mode ? 'text-xl' : 'text-lg' ?>"><?= htmlspecialchars($item['name']) ?></h3>
                        <p class="text-gray-600 text-sm mb-4 line-clamp-2"><?= htmlspecialchars($item['description'] ?? '') ?></p>
                        <div class="flex items-center justify-between">
                            <span class="font-bold text-blue-600 <?= $kiosk_mode ? 'text-2xl' : 'text-xl' ?>">$<?= number_format($item['price'], 2) ?></span>
                            <button class="add-to-cart-btn bg-orange-500 hover:bg-orange-600 text-white font-medium py-2 px-4 rounded-lg transition <?= $kiosk_mode ? 'px-6 py-3 text-base' : 'text-sm' ?>">
                                <svg class="inline w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/></svg>
                                Add
                            </button>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <?php if (empty($items)): ?><p class="no-items">No items in this category.</p><?php endif; ?>
    <?php else: ?>
        <?php foreach ($menu_items as $cat_name => $cat_items): ?>
            <div id="<?= strtolower(str_replace(' ', '', $cat_name)) ?>" class="category">
                <h2><?= htmlspecialchars($cat_name) ?></h2>
                <div class="items-grid">
                    <?php foreach ($cat_items as $item): ?>
                        <div class="menu-card menu-item" data-id="<?= $item['id'] ?>" data-name="<?= htmlspecialchars($item['name']) ?>" data-price="<?= $item['price'] ?>" data-image="<?= htmlspecialchars($item['image'] ?? '') ?>" data-description="<?= htmlspecialchars($item['description'] ?? '') ?>" data-options='<?= json_encode($item['options']) ?>'>
                            <div class="aspect-square overflow-hidden bg-gray-100">
                                <img src="<?= htmlspecialchars($item['image'] ?? 'https://via.placeholder.com/300?text=No+Image') ?>" alt="<?= htmlspecialchars($item['name']) ?>" class="w-full h-full object-cover transition-transform duration-300">
                            </div>
                            <div class="menu-item-content">
                                <div class="mb-2">
                                    <span class="text-xs font-medium text-blue-600 bg-blue-50 px-2 py-1 rounded"><?= htmlspecialchars($item['category']) ?></span>
                                </div>
                                <h3 class="menu-item-name font-bold mb-2 <?= $kiosk_mode ? 'text-xl' : 'text-lg' ?>"><?= htmlspecialchars($item['name']) ?></h3>
                                <p class="text-gray-600 text-sm mb-4 line-clamp-2"><?= htmlspecialchars($item['description'] ?? '') ?></p>
                                <div class="flex items-center justify-between">
                                    <span class="font-bold text-blue-600 <?= $kiosk_mode ? 'text-2xl' : 'text-xl' ?>">$<?= number_format($item['price'], 2) ?></span>
                                    <button class="add-to-cart-btn bg-orange-500 hover:bg-orange-600 text-white font-medium py-2 px-4 rounded-lg transition <?= $kiosk_mode ? 'px-6 py-3 text-base' : 'text-sm' ?>">
                                        <svg class="inline w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/></svg>
                                        Add
                                    </button>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<?php if ($kiosk_mode): ?>
    <a href="<?= kiosk_url('cart.php') ?>" class="floating-cart">
        <span class="dashicons dashicons-cart"></span>
        <span class="cart-count" id="cart-count-kiosk">0</span>
    </a>
<?php endif; ?>

<!-- Options Dialog (hidden by default) -->
<div id="optionsDialog" class="option-dialog-overlay" style="display: none;">
    <div class="option-dialog">
        <div class="flex justify-between items-center mb-4">
            <h2 id="dialogTitle" class="text-xl font-bold"></h2>
            <button id="closeDialog" class="text-gray-500 hover:text-gray-700 text-2xl">&times;</button>
        </div>
        <div class="aspect-video overflow-hidden rounded-lg mb-4">
            <img id="dialogImage" src="" alt="" class="w-full h-full object-cover">
        </div>
        <p id="dialogDesc" class="text-gray-600 mb-4"></p>
        <div id="dialogOptionsContainer" class="space-y-4 mb-6"></div>
        <div class="flex justify-between items-center mb-4">
            <span class="font-bold text-blue-600 text-2xl">$<span id="dialogTotalPrice">0.00</span></span>
            <button id="dialogAddToCart" class="bg-orange-500 hover:bg-orange-600 text-white font-bold py-2 px-6 rounded-lg">Add to Cart</button>
        </div>
    </div>
</div>

<script>
// Search & filter
const searchInput = document.getElementById('menu-search');
const filterBtns = document.querySelectorAll('.filter-btn');
const menuCards = document.querySelectorAll('.menu-card');
const categoriesDiv = document.querySelectorAll('.category');

function filterMenu() {
    const term = searchInput ? searchInput.value.toLowerCase() : '';
    const activeCat = document.querySelector('.filter-btn.active')?.dataset.category || 'all';
    menuCards.forEach(card => {
        const name = card.dataset.name?.toLowerCase() || '';
        const catId = card.closest('.category')?.id || '';
        const matchesSearch = name.includes(term);
        const matchesCat = activeCat === 'all' || catId === activeCat;
        card.style.display = matchesSearch && matchesCat ? '' : 'none';
    });
    if (categoriesDiv.length) {
        categoriesDiv.forEach(cat => {
            const hasVisible = Array.from(cat.querySelectorAll('.menu-card')).some(c => c.style.display !== 'none');
            cat.style.display = hasVisible ? '' : 'none';
        });
    }
}
if (searchInput) searchInput.addEventListener('input', filterMenu);
filterBtns.forEach(btn => {
    btn.addEventListener('click', () => {
        filterBtns.forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
        filterMenu();
    });
});

// Options dialog logic
const dialog = document.getElementById('optionsDialog');
const closeDialog = document.getElementById('closeDialog');
const dialogTitle = document.getElementById('dialogTitle');
const dialogImage = document.getElementById('dialogImage');
const dialogDesc = document.getElementById('dialogDesc');
const dialogOptionsContainer = document.getElementById('dialogOptionsContainer');
const dialogTotalPriceSpan = document.getElementById('dialogTotalPrice');
const dialogAddToCart = document.getElementById('dialogAddToCart');
let currentItem = null;
let currentOptionsState = {};

function openDialog(item) {
    currentItem = item;
    dialogTitle.textContent = item.name;
    dialogImage.src = item.image || 'https://via.placeholder.com/400?text=No+Image';
    dialogDesc.textContent = item.description || '';
    dialogTotalPriceSpan.textContent = item.price.toFixed(2);
    currentOptionsState = {};
    // Build options UI
    dialogOptionsContainer.innerHTML = '';
    if (item.options && item.options.length > 0) {
        item.options.forEach(opt => {
            const optDiv = document.createElement('div');
            optDiv.className = 'space-y-2';
            optDiv.innerHTML = `<label class="block font-medium">${opt.option_name} ${opt.required ? '*' : ''}</label>`;
            const radioGroup = document.createElement('div');
            radioGroup.className = 'space-y-2';
            opt.values.forEach(val => {
                const radioId = `opt_${opt.id}_${val.id}`;
                const radio = document.createElement('input');
                radio.type = 'radio';
                radio.name = `option_${opt.id}`;
                radio.value = val.id;
                radio.id = radioId;
                radio.className = 'mr-2';
                if (opt.required && !currentOptionsState[opt.id]) {
                    radio.checked = true;
                    currentOptionsState[opt.id] = val.id;
                }
                radio.addEventListener('change', (e) => {
                    if (e.target.checked) {
                        currentOptionsState[opt.id] = val.id;
                        updateDialogPrice();
                    }
                });
                const label = document.createElement('label');
                label.htmlFor = radioId;
                label.className = 'cursor-pointer';
                let priceText = val.value_name;
                if (val.price_modifier !== 0) {
                    const sign = val.price_modifier > 0 ? '+' : '-';
                    priceText += ` (${sign}$${Math.abs(val.price_modifier).toFixed(2)})`;
                }
                label.textContent = priceText;
                const wrapper = document.createElement('div');
                wrapper.className = 'flex items-center';
                wrapper.appendChild(radio);
                wrapper.appendChild(label);
                radioGroup.appendChild(wrapper);
            });
            optDiv.appendChild(radioGroup);
            dialogOptionsContainer.appendChild(optDiv);
        });
    }
    updateDialogPrice();
    dialog.style.display = 'flex';
}

function updateDialogPrice() {
    if (!currentItem) return;
    let modifiers = 0;
    if (currentItem.options) {
        for (const [optId, valId] of Object.entries(currentOptionsState)) {
            const opt = currentItem.options.find(o => o.id == optId);
            if (opt) {
                const val = opt.values.find(v => v.id == valId);
                if (val) modifiers += parseFloat(val.price_modifier || 0);
            }
        }
    }
    const total = currentItem.price + modifiers;
    dialogTotalPriceSpan.textContent = total.toFixed(2);
}

function addToCartFromDialog() {
    if (!currentItem) return;
    // Validate required options
    if (currentItem.options) {
        for (const opt of currentItem.options) {
            if (opt.required && !currentOptionsState[opt.id]) {
                alert(`Please select ${opt.option_name}`);
                return;
            }
        }
    }
    // Build options object
    const options = {};
    for (const [optId, valId] of Object.entries(currentOptionsState)) {
        options[optId] = valId;
    }
    // AJAX add to cart
    const formData = new URLSearchParams();
    formData.append('csrf_token', '<?= generateToken() ?>');
    formData.append('item_id', currentItem.id);
    formData.append('quantity', 1);
    formData.append('options', JSON.stringify(options));
    fetch('<?= kiosk_url('/cart.php?action=add') ?>', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            alert('Added to cart!');
            dialog.style.display = 'none';
            updateCartCount();
        } else {
            alert('Error adding item');
        }
    })
    .catch(err => alert('Network error'));
}

// Attach click events to menu cards and Add buttons
document.querySelectorAll('.menu-card').forEach(card => {
    const addBtn = card.querySelector('.add-to-cart-btn');
    // Card click opens dialog if options exist
    card.addEventListener('click', (e) => {
        if (e.target === addBtn || addBtn.contains(e.target)) return;
        const options = JSON.parse(card.dataset.options || '[]');
        if (options.length > 0) {
            const item = {
                id: parseInt(card.dataset.id),
                name: card.dataset.name,
                price: parseFloat(card.dataset.price),
                image: card.dataset.image,
                description: card.dataset.description,
                options: options
            };
            openDialog(item);
        }
    });
    // Add button click
    addBtn.addEventListener('click', (e) => {
        e.stopPropagation();
        const options = JSON.parse(card.dataset.options || '[]');
        if (options.length > 0) {
            const item = {
                id: parseInt(card.dataset.id),
                name: card.dataset.name,
                price: parseFloat(card.dataset.price),
                image: card.dataset.image,
                description: card.dataset.description,
                options: options
            };
            openDialog(item);
        } else {
            // Direct add (no options)
            const formData = new URLSearchParams();
            formData.append('csrf_token', '<?= generateToken() ?>');
            formData.append('item_id', card.dataset.id);
            formData.append('quantity', 1);
            formData.append('options', '{}');
            fetch('<?= kiosk_url('/cart.php?action=add') ?>', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    alert('Added to cart!');
                    updateCartCount();
                } else alert('Error');
            });
        }
    });
});

closeDialog.addEventListener('click', () => dialog.style.display = 'none');
dialogAddToCart.addEventListener('click', addToCartFromDialog);

function updateCartCount() {
    fetch('<?= kiosk_url('/get-cart-count.php') ?>')
        .then(r => r.json())
        .then(data => {
            document.querySelectorAll('.cart-count').forEach(el => el.textContent = data.count);
        });
}
updateCartCount();
</script>

<?php include 'includes/footer.php'; ?>