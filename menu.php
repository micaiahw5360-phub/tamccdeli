<?php
$page_title = "Menu | TAMCC Deli";
include 'includes/header.php';

// If kiosk mode is active and NO category is requested, show the simplified landing
if ($kiosk_mode && !isset($_GET['category'])) {
    include 'menu-kiosk-categories.php';
    include 'includes/footer.php';
    exit;
}

// ... rest of your regular menu code (fetch items, display categories, etc.)
require 'config/database.php';
require 'includes/csrf.php';

$stmt = $conn->prepare("SELECT * FROM menu_items ORDER BY FIELD(category, 'Breakfast', 'A La Carte', 'Combo', 'Beverage', 'Dessert'), sort_order, name");
$stmt->execute();
$items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$menu_by_category = [];
foreach ($items as $item) {
    $menu_by_category[$item['category']][] = $item;
}
?>

<!-- Search Bar -->
<div class="menu-search-container">
    <input type="text" id="menu-search" class="menu-search" placeholder="Search menu items...">
</div>

<!-- Category Filter Buttons -->
<div class="category-filter">
    <button class="filter-btn active" data-category="all">All</button>
    <?php
    $categories = array_keys($menu_by_category);
    foreach ($categories as $cat):
        $cat_id = strtolower(str_replace(' ', '', $cat));
    ?>
        <button class="filter-btn" data-category="<?= $cat_id ?>"><?= htmlspecialchars($cat) ?></button>
    <?php endforeach; ?>
</div>

<div class="menu-container">
    <h1 class="menu-title">Our Menu</h1>

    <?php foreach ($menu_by_category as $category => $items):
        $cat_id = strtolower(str_replace(' ', '', $category));
        $first = true;
    ?>
        <div class="category" id="<?= $cat_id ?>">
            <h2 class="category-title"><?= htmlspecialchars($category) ?></h2>
            <div class="items-grid">
                <?php foreach ($items as $item): 
                    // Get average rating and count for this item
                    $rating_stmt = $conn->prepare("SELECT AVG(rating) as avg, COUNT(*) as count FROM reviews WHERE menu_item_id = ?");
                    $rating_stmt->bind_param("i", $item['id']);
                    $rating_stmt->execute();
                    $rating_data = $rating_stmt->get_result()->fetch_assoc();
                    $avg_rating = round($rating_data['avg'] ?? 0, 1);
                    $review_count = $rating_data['count'] ?? 0;
                ?>
                    <div class="menu-item" id="item-<?= $item['id'] ?>">
                        <?php if (!empty($item['image'])): ?>
                            <div class="menu-item-image">
                                <img src="<?= htmlspecialchars($item['image']) ?>" alt="<?= htmlspecialchars($item['name']) ?>">
                            </div>
                        <?php endif; ?>

                        <div class="menu-item-content">
                            <h3 class="menu-item-name"><?= htmlspecialchars($item['name']) ?></h3>

                            <?php if ($first): ?>
                                <span class="badge popular">🔥 Popular</span>
                                <?php $first = false; ?>
                            <?php endif; ?>

                            <!-- Rating display -->
                            <div class="rating">
                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                    <span class="star <?= $i <= $avg_rating ? 'filled' : '' ?>">★</span>
                                <?php endfor; ?>
                                <span class="review-count">(<?= $review_count ?>)</span>
                            </div>

                            <div class="price">$<?= number_format($item['price'], 2) ?></div>

                            <div class="menu-item-footer">
                                <form class="add-to-cart-form" method="post" action="cart.php?action=add">
                                    <input type="hidden" name="item_id" value="<?= $item['id'] ?>">
                                    <input type="hidden" name="csrf_token" value="<?= generateToken() ?>">
                                    <input type="number" name="quantity" value="1" min="1" max="10" class="qty-input">
                                    <button type="submit" class="btn btn-primary add-to-cart-btn">Add</button>
                                </form>

                                <?php if (isset($_SESSION['user_id'])): ?>
                                    <button class="btn-small btn-review" onclick="document.getElementById('review-form-<?= $item['id'] ?>').style.display='block';">Review</button>
                                <?php endif; ?>
                            </div>

                            <?php if (isset($_SESSION['user_id'])): ?>
                                <div id="review-form-<?= $item['id'] ?>" class="review-form" style="display:none; margin-top:10px;">
                                    <form method="post" action="submit-review.php">
                                        <input type="hidden" name="csrf_token" value="<?= generateToken() ?>">
                                        <input type="hidden" name="item_id" value="<?= $item['id'] ?>">
                                        <select name="rating" required style="width:100%; margin-bottom:5px;">
                                            <option value="">Rate this item</option>
                                            <option value="5">5 ★</option>
                                            <option value="4">4 ★</option>
                                            <option value="3">3 ★</option>
                                            <option value="2">2 ★</option>
                                            <option value="1">1 ★</option>
                                        </select>
                                        <textarea name="comment" placeholder="Your review (optional)" rows="2" style="width:100%; margin-bottom:5px;"></textarea>
                                        <button type="submit" name="submit_review" class="btn-small">Submit Review</button>
                                    </form>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<style>
    /* Star rating styles */
    .rating {
        margin: 5px 0;
        line-height: 1;
    }
    .star {
        color: #ccc;
        font-size: 1.2rem;
        display: inline-block;
    }
    .star.filled {
        color: #f5b301;
    }
    .review-count {
        font-size: 0.9rem;
        color: var(--neutral-600);
        margin-left: 5px;
    }
    .btn-review {
        background: #4caf50;
        color: white;
        border: none;
        padding: 5px 10px;
        border-radius: var(--radius);
        cursor: pointer;
        font-size: 0.85rem;
        margin-left: 5px;
    }
    .btn-review:hover {
        background: #45a049;
    }
    .review-form {
        background: #f9f9f9;
        padding: 10px;
        border-radius: var(--radius);
        border: 1px solid var(--neutral-200);
    }
</style>

<?php if ($kiosk_mode): ?>
    <!-- Floating cart button for kiosk mode -->
    <a href="<?= kiosk_url('cart.php') ?>" class="floating-cart">
        🛒 <span id="cart-count-kiosk" class="cart-count">0</span>
    </a>
    <script>
    // Update kiosk cart count
    function updateKioskCartCount() {
        fetch('get-cart-count.php')
            .then(response => response.json())
            .then(data => {
                document.getElementById('cart-count-kiosk').textContent = data.count;
            })
            .catch(err => console.error('Failed to update cart count', err));
    }
    updateKioskCartCount();
    setInterval(updateKioskCartCount, 2000);
    </script>
<?php endif; ?>

<?php include 'includes/footer.php'; ?>