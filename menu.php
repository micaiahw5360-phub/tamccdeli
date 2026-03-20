<?php
session_start();
require 'config/database.php';
require 'includes/csrf.php';
require 'includes/kiosk.php';

// Fetch all menu items grouped by category
$categories = ['Breakfast', 'A La Carte', 'Combo', 'Beverage', 'Dessert'];
$menu_items = [];

foreach ($categories as $cat) {
    $stmt = $conn->prepare("SELECT * FROM menu_items WHERE category = ? ORDER BY sort_order, name");
    $stmt->bind_param("s", $cat);
    $stmt->execute();
    $result = $stmt->get_result();
    $items = $result->fetch_all(MYSQLI_ASSOC);
    $menu_items[$cat] = $items;
}

// Helper to get options for an item
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

$page_title = "Menu";
include 'includes/header.php';
?>

<div class="menu-search-container">
    <input type="text" id="menu-search" class="menu-search" placeholder="Search menu...">
</div>

<div class="category-filter">
    <button class="filter-btn active" data-category="all">All</button>
    <?php foreach ($categories as $cat): ?>
        <button class="filter-btn" data-category="<?= strtolower(str_replace(' ', '', $cat)) ?>"><?= $cat ?></button>
    <?php endforeach; ?>
</div>

<div class="menu-container">
    <?php foreach ($categories as $cat): ?>
        <div id="<?= strtolower(str_replace(' ', '', $cat)) ?>" class="category">
            <h2><?= $cat ?></h2>
            <div class="items-grid">
                <?php foreach ($menu_items[$cat] as $item):
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
</div>

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