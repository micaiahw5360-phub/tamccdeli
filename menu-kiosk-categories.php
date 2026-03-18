<?php
// menu-kiosk-categories.php – simplified category landing for kiosk
echo "<!-- menu-kiosk-categories.php loaded -->";
?>

<div class="kiosk-header">Choose a Category</div>

<div class="kiosk-categories">
    <a href="<?= kiosk_url('menu.php?category=breakfast#breakfast') ?>" class="kiosk-category">🍳 Breakfast</a>
    <a href="<?= kiosk_url('menu.php?category=alacarte#alacarte') ?>" class="kiosk-category">🍔 A La Carte</a>
    <a href="<?= kiosk_url('menu.php?category=combo#combo') ?>" class="kiosk-category">🍱 Combo</a>
    <a href="<?= kiosk_url('menu.php?category=beverage#beverage') ?>" class="kiosk-category">🥤 Beverage</a>
    <a href="<?= kiosk_url('menu.php?category=dessert#dessert') ?>" class="kiosk-category">🍰 Dessert</a>
</div>

<!-- Floating cart button -->
<a href="<?= kiosk_url('cart.php') ?>" class="floating-cart">
    🛒 <span id="cart-count-kiosk" class="cart-count">0</span>
</a>

<script>
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

<?php
// No footer here – it will be added by menu.php after including this file
?>