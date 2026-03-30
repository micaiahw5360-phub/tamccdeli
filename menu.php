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

$selected_category = isset($_GET['cat']) && array_key_exists($_GET['cat'], $categories) ? $_GET['cat'] : null;

// Kiosk mode category selection screen
if ($kiosk_mode && !$selected_category) {
    $page_title = "Select Category | TAMCC Deli";
    include 'includes/header.php';
    ?>
    <div class="container">
        <h1 class="text-3xl font-bold text-center mb-8">Select Your Meal</h1>
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

// Fetch items
$stmt = $conn->prepare("SELECT * FROM menu_items " .
    ($selected_category ? "WHERE LOWER(category) = LOWER(?) " : "") .
    "ORDER BY FIELD(category, 'Breakfast', 'A La Carte', 'Combo', 'Beverage', 'Dessert'), sort_order, name");
if ($selected_category) {
    $cat_name = $categories[$selected_category];
    $stmt->bind_param("s", $cat_name);
}
$stmt->execute();
$items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$page_title = $kiosk_mode ? $categories[$selected_category] . " | TAMCC Deli" : "Menu | TAMCC Deli";
include 'includes/header.php';
?>

<div class="container">
    <?php if ($kiosk_mode && $selected_category): ?>
        <div class="flex items-center justify-between mb-6">
            <a href="<?= kiosk_url('menu.php') ?>" class="back-to-categories">← Back to Categories</a>
            <h1 class="text-3xl font-bold"><?= htmlspecialchars($categories[$selected_category]) ?></h1>
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

    <div class="menu-grid" id="menu-grid">
        <?php foreach ($items as $item):
            $options = getItemOptions($conn, $item['id']);
            $hasOptions = !empty($options);
        ?>
            <div class="menu-item" data-category="<?= strtolower(str_replace(' ', '', $item['category'])) ?>">
                <?php if ($item['image']): ?>
                    <img src="<?= htmlspecialchars($item['image']) ?>" alt="<?= htmlspecialchars($item['name']) ?>" class="menu-item-image">
                <?php endif; ?>
                <div class="menu-item-content">
                    <span class="menu-item-category"><?= htmlspecialchars($item['category']) ?></span>
                    <h3 class="menu-item-title"><?= htmlspecialchars($item['name']) ?></h3>
                    <p class="menu-item-description"><?= htmlspecialchars($item['description'] ?? '') ?></p>
                    <div class="menu-item-footer">
                        <span class="menu-item-price">$<?= number_format($item['price'], 2) ?></span>
                        <?php if ($hasOptions): ?>
                            <button class="btn btn-accent btn-sm" onclick="openItemModal(<?= $item['id'] ?>, '<?= addslashes($item['name']) ?>', <?= $item['price'] ?>, '<?= addslashes($item['image']) ?>', '<?= addslashes($item['description']) ?>', `<?php
                                // Build options HTML for modal
                                ob_start();
                                foreach ($options as $opt) {
                                    echo '<div class="form-group">';
                                    echo '<label class="form-label">' . htmlspecialchars($opt['option_name']) . ($opt['required'] ? ' <span class="text-danger">*</span>' : '') . '</label>';
                                    if ($opt['option_type'] == 'dropdown') {
                                        echo '<select name="options[' . $opt['id'] . ']" class="form-select" ' . ($opt['required'] ? 'required' : '') . '>';
                                        echo '<option value="">-- Select --</option>';
                                        foreach ($opt['values'] as $val) {
                                            echo '<option value="' . $val['id'] . '" data-price="' . $val['price_modifier'] . '">';
                                            echo htmlspecialchars($val['value_name']);
                                            if ($val['price_modifier'] != 0) {
                                                echo ' (' . ($val['price_modifier'] > 0 ? '+' : '-') . '$' . number_format(abs($val['price_modifier']), 2) . ')';
                                            }
                                            echo '</option>';
                                        }
                                        echo '</select>';
                                    } else {
                                        echo '<div class="radio-group">';
                                        foreach ($opt['values'] as $val) {
                                            echo '<label class="radio-option">';
                                            echo '<input type="radio" name="options[' . $opt['id'] . ']" value="' . $val['id'] . '" data-price="' . $val['price_modifier'] . '" ' . ($opt['required'] ? 'required' : '') . '>';
                                            echo '<span>' . htmlspecialchars($val['value_name']);
                                            if ($val['price_modifier'] != 0) {
                                                echo ' (' . ($val['price_modifier'] > 0 ? '+' : '-') . '$' . number_format(abs($val['price_modifier']), 2) . ')';
                                            }
                                            echo '</span></label>';
                                        }
                                        echo '</div>';
                                    }
                                    echo '</div>';
                                }
                                $optionsHtml = ob_get_clean();
                                echo addslashes($optionsHtml);
                            ?>`)">Customize</button>
                        <?php else: ?>
                            <form class="add-to-cart-form" method="post">
                                <input type="hidden" name="csrf_token" value="<?= generateToken() ?>">
                                <input type="hidden" name="item_id" value="<?= $item['id'] ?>">
                                <input type="hidden" name="quantity" value="1">
                                <button type="submit" class="btn btn-accent btn-sm">Add to Cart</button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<?php if ($kiosk_mode): ?>
    <a href="<?= kiosk_url('cart.php') ?>" class="floating-cart">
        <span class="dashicons dashicons-cart"></span>
        <span class="cart-count" id="cart-count-kiosk">0</span>
    </a>
<?php endif; ?>

<?php include 'includes/footer.php'; ?>