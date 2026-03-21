<?php
session_start();
require 'config/database.php';
require 'includes/csrf.php';
require 'includes/kiosk.php';

$categories = [
    'breakfast' => 'Breakfast',
    'alacarte'  => 'A La Carte',
    'combo'     => 'Combo',
    'beverage'  => 'Beverage',
    'dessert'   => 'Dessert'
];

// Determine if we're in kiosk mode and what category is selected
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
    <?php
    // Add floating cart button (always visible in kiosk mode)
    ?>
    <a href="<?= kiosk_url('cart.php') ?>" class="floating-cart">
        <span class="dashicons dashicons-cart"></span>
        <span class="cart-count" id="cart-count-kiosk">0</span>
    </a>
    <?php
    include 'includes/footer.php';
    exit;
}

// If we reach here, we're either:
// - in normal mode (show full menu), or
// - in kiosk mode with a category selected (show only that category)

$stmt = $conn->prepare("SELECT * FROM menu_items " . 
    ($selected_category ? "WHERE LOWER(category) = LOWER(?) " : "") . 
    "ORDER BY FIELD(category, 'Breakfast', 'A La Carte', 'Combo', 'Beverage', 'Dessert'), sort_order, name");

if ($selected_category) {
    // Map slug to actual category name
    $cat_name = $categories[$selected_category];
    $stmt->bind_param("s", $cat_name);
}
$stmt->execute();
$items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Group items by category for normal mode
$menu_items = [];
if (!$kiosk_mode) {
    foreach ($items as $item) {
        $menu_items[$item['category']][] = $item;
    }
}

// Helper to get options for an item (same as before)
function getItemOptions($conn, $item_id) {
    $options = [];
    $stmt = $conn->prepare("SELECT * FROM menu_item_options WHERE menu_item_id = ? ORDER BY sort_order");
    $stmt->bind_param("i", $item_id);
    $stmt->execute();
    $opt_res = $stmt->get_result();
    while ($opt = $opt_res->fetch_assoc()) {
        $val_stmt = $conn->prepare("SELECT * FROM menu_item_option_values WHERE option_id = ? ORDER BY sort_order");
        $val_stmt->bind_param("i", $opt['id']);
        $val_stmt->execute();
        $opt['values'] = $val_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $options[] = $opt;
    }
    return $options;
}

$page_title = $kiosk_mode ? $categories[$selected_category] . " | TAMCC Deli" : "Menu | TAMCC Deli";
include 'includes/header.php';
?>

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
            <?php foreach ($items as $item):
                $options = getItemOptions($conn, $item['id']);
            ?>
                <div class="menu-item" data-name="<?= strtolower(htmlspecialchars($item['name'])) ?>">
                    <?php if ($item['image']): ?>
                        <img src="<?= htmlspecialchars($item['image']) ?>" alt="<?= htmlspecialchars($item['name']) ?>">
                    <?php endif; ?>
                    <div class="menu-item-content">
                        <h3 class="menu-item-name"><?= htmlspecialchars($item['name']) ?></h3>
                        <div class="price">$<?= number_format($item['price'], 2) ?></div>

                        <form class="add-to-cart-form" method="post">
                            <input type="hidden" name="csrf_token" value="<?= generateToken() ?>">
                            <input type="hidden" name="item_id" value="<?= $item['id'] ?>">

                            <?php if (!empty($options)): ?>
                                <div class="item-options" data-base-price="<?= $item['price'] ?>">
                                    <?php foreach ($options as $opt): ?>
                                        <div class="option-group">
                                            <label><?= htmlspecialchars($opt['option_name']) ?> <?= $opt['required'] ? '*' : '' ?></label>
                                            <?php if ($opt['option_type'] == 'dropdown'): ?>
                                                <select name="options[<?= $opt['id'] ?>]" class="option-select" <?= $opt['required'] ? 'required' : '' ?>>
                                                    <option value="">-- Select --</option>
                                                    <?php foreach ($opt['values'] as $val): ?>
                                                        <option value="<?= $val['id'] ?>" data-price="<?= $val['price_modifier'] ?>">
                                                            <?= htmlspecialchars($val['value_name']) ?>
                                                            <?php if ($val['price_modifier'] != 0): ?>
                                                                (<?= ($val['price_modifier'] > 0 ? '+' : '-') ?>$<?= number_format(abs($val['price_modifier']), 2) ?>)
                                                            <?php endif; ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            <?php else: ?>
                                                <div class="radio-group">
                                                    <?php foreach ($opt['values'] as $val): ?>
                                                        <label>
                                                            <input type="radio" name="options[<?= $opt['id'] ?>]" value="<?= $val['id'] ?>" data-price="<?= $val['price_modifier'] ?>" <?= $opt['required'] ? 'required' : '' ?>>
                                                            <?= htmlspecialchars($val['value_name']) ?>
                                                            <?php if ($val['price_modifier'] != 0): ?>
                                                                (<?= ($val['price_modifier'] > 0 ? '+' : '-') ?>$<?= number_format(abs($val['price_modifier']), 2) ?>)
                                                            <?php endif; ?>
                                                        </label>
                                                    <?php endforeach; ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    <?php endforeach; ?>
                                    <div class="dynamic-price">Price: $<span class="item-total-price"><?= number_format($item['price'], 2) ?></span></div>
                                </div>
                            <?php endif; ?>

                            <div class="menu-item-footer">
                                <input type="number" name="quantity" value="1" min="1" max="10" class="qty-input">
                                <button type="submit" class="add-to-cart-btn">Add to Cart</button>
                            </div>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <?php if (empty($items)): ?>
            <p class="no-items">No items in this category.</p>
        <?php endif; ?>

    <?php else: ?>
        <!-- Normal mode: show all categories with sections -->
        <?php foreach ($menu_items as $cat_name => $cat_items): ?>
            <div id="<?= strtolower(str_replace(' ', '', $cat_name)) ?>" class="category">
                <h2><?= htmlspecialchars($cat_name) ?></h2>
                <div class="items-grid">
                    <?php foreach ($cat_items as $item):
                        $options = getItemOptions($conn, $item['id']);
                    ?>
                        <div class="menu-item" data-name="<?= strtolower(htmlspecialchars($item['name'])) ?>">
                            <?php if ($item['image']): ?>
                                <img src="<?= htmlspecialchars($item['image']) ?>" alt="<?= htmlspecialchars($item['name']) ?>">
                            <?php endif; ?>
                            <div class="menu-item-content">
                                <h3 class="menu-item-name"><?= htmlspecialchars($item['name']) ?></h3>
                                <div class="price">$<?= number_format($item['price'], 2) ?></div>

                                <form class="add-to-cart-form" method="post">
                                    <input type="hidden" name="csrf_token" value="<?= generateToken() ?>">
                                    <input type="hidden" name="item_id" value="<?= $item['id'] ?>">

                                    <?php if (!empty($options)): ?>
                                        <div class="item-options" data-base-price="<?= $item['price'] ?>">
                                            <?php foreach ($options as $opt): ?>
                                                <div class="option-group">
                                                    <label><?= htmlspecialchars($opt['option_name']) ?> <?= $opt['required'] ? '*' : '' ?></label>
                                                    <?php if ($opt['option_type'] == 'dropdown'): ?>
                                                        <select name="options[<?= $opt['id'] ?>]" class="option-select" <?= $opt['required'] ? 'required' : '' ?>>
                                                            <option value="">-- Select --</option>
                                                            <?php foreach ($opt['values'] as $val): ?>
                                                                <option value="<?= $val['id'] ?>" data-price="<?= $val['price_modifier'] ?>">
                                                                    <?= htmlspecialchars($val['value_name']) ?>
                                                                    <?php if ($val['price_modifier'] != 0): ?>
                                                                        (<?= ($val['price_modifier'] > 0 ? '+' : '-') ?>$<?= number_format(abs($val['price_modifier']), 2) ?>)
                                                                    <?php endif; ?>
                                                                </option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                    <?php else: ?>
                                                        <div class="radio-group">
                                                            <?php foreach ($opt['values'] as $val): ?>
                                                                <label>
                                                                    <input type="radio" name="options[<?= $opt['id'] ?>]" value="<?= $val['id'] ?>" data-price="<?= $val['price_modifier'] ?>" <?= $opt['required'] ? 'required' : '' ?>>
                                                                    <?= htmlspecialchars($val['value_name']) ?>
                                                                    <?php if ($val['price_modifier'] != 0): ?>
                                                                        (<?= ($val['price_modifier'] > 0 ? '+' : '-') ?>$<?= number_format(abs($val['price_modifier']), 2) ?>)
                                                                    <?php endif; ?>
                                                                </label>
                                                            <?php endforeach; ?>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                            <?php endforeach; ?>
                                            <div class="dynamic-price">Price: $<span class="item-total-price"><?= number_format($item['price'], 2) ?></span></div>
                                        </div>
                                    <?php endif; ?>

                                    <div class="menu-item-footer">
                                        <input type="number" name="quantity" value="1" min="1" max="10" class="qty-input">
                                        <button type="submit" class="add-to-cart-btn">Add to Cart</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<?php if ($kiosk_mode): ?>
    <!-- Floating cart button (already added above for category screen, but also needed on item screens) -->
    <a href="<?= kiosk_url('cart.php') ?>" class="floating-cart">
        <span class="dashicons dashicons-cart"></span>
        <span class="cart-count" id="cart-count-kiosk">0</span>
    </a>
<?php endif; ?>

<script>
// Dynamic price update when options change
document.querySelectorAll('.item-options').forEach(container => {
    const basePrice = parseFloat(container.dataset.basePrice);
    const priceSpan = container.querySelector('.item-total-price');
    const selects = container.querySelectorAll('select, input[type="radio"]');

    function updatePrice() {
        let modifiers = 0;
        selects.forEach(input => {
            if (input.checked || (input.selectedIndex !== undefined && input.value)) {
                const selected = input.options ? input.options[input.selectedIndex] : input;
                const priceMod = parseFloat(selected.dataset.price || 0);
                if (!isNaN(priceMod)) modifiers += priceMod;
            }
        });
        const total = basePrice + modifiers;
        priceSpan.textContent = total.toFixed(2);
    }

    selects.forEach(input => input.addEventListener('change', updatePrice));
    updatePrice();
});
</script>

<?php include 'includes/footer.php'; ?>