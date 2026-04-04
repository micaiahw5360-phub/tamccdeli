<?php
require __DIR__ . '/includes/session.php';
require 'config/database.php';
require 'includes/csrf.php';
require_once __DIR__ . '/includes/kiosk.php';
require 'includes/functions.php';

// ========== AJAX Add to Cart Handler (MUST BE FIRST) ==========
if (isset($_GET['action']) && $_GET['action'] === 'add' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    if (!validateToken($_POST['csrf_token'] ?? '')) {
        echo json_encode(['success' => false, 'error' => 'Invalid CSRF token']);
        exit;
    }
    $item_id = intval($_POST['item_id'] ?? 0);
    $quantity = max(1, intval($_POST['quantity'] ?? 1));
    $options = json_decode($_POST['options'] ?? '{}', true);
    if (!$item_id) {
        echo json_encode(['success' => false, 'error' => 'Invalid item ID']);
        exit;
    }
    $key = 'item_' . $item_id;
    if (!empty($options)) {
        ksort($options);
        $key .= '_opt_' . implode('_', array_map(function($k, $v) { return $k . '-' . $v; }, array_keys($options), $options));
    }
    if (!isset($_SESSION['cart'])) $_SESSION['cart'] = [];
    if (isset($_SESSION['cart'][$key])) {
        $_SESSION['cart'][$key]['quantity'] += $quantity;
    } else {
        $_SESSION['cart'][$key] = [
            'item_id' => $item_id,
            'quantity' => $quantity,
            'options' => $options
        ];
    }
    echo json_encode(['success' => true, 'cart_count' => array_sum(array_column($_SESSION['cart'], 'quantity'))]);
    exit;
}

// ========== AJAX Update / Remove Handlers ==========
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
    header('Content-Type: application/json');
    if (!validateToken($_POST['csrf_token'] ?? '')) {
        echo json_encode(['success' => false, 'error' => 'Invalid CSRF']);
        exit;
    }
    $key = $_POST['key'] ?? '';
    if (isset($_POST['quantity'])) {
        $qty = intval($_POST['quantity']);
        if ($qty <= 0) {
            unset($_SESSION['cart'][$key]);
        } elseif (isset($_SESSION['cart'][$key])) {
            $_SESSION['cart'][$key]['quantity'] = $qty;
        }
        echo json_encode(['success' => true]);
        exit;
    } elseif (isset($_POST['action']) && $_POST['action'] === 'remove') {
        if ($key && isset($_SESSION['cart'][$key])) {
            unset($_SESSION['cart'][$key]);
        }
        echo json_encode(['success' => true]);
        exit;
    }
}

// ========== Rest of cart.php ==========
if (!isset($_SESSION['cart'])) $_SESSION['cart'] = [];

function getOptionDetails($conn, $optionValues) {
    if (empty($optionValues)) return [];
    $ids = array_values($optionValues);
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $conn->prepare("SELECT v.*, o.option_name FROM menu_item_option_values v JOIN menu_item_options o ON v.option_id = o.id WHERE v.id IN ($placeholders)");
    $stmt->bind_param(str_repeat('i', count($ids)), ...$ids);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

// --- Handle non‑AJAX update/remove (fallback) ---
if (isset($_POST['update'])) {
    if (!validateToken($_POST['csrf_token'])) die('Invalid CSRF token');
    if (isset($_POST['quantity']) && is_array($_POST['quantity'])) {
        foreach ($_POST['quantity'] as $key => $qty) {
            $qty = intval($qty);
            if ($qty <= 0) unset($_SESSION['cart'][$key]);
            elseif (isset($_SESSION['cart'][$key])) $_SESSION['cart'][$key]['quantity'] = $qty;
        }
    }
    $redirect = 'cart.php' . (isset($_SESSION['kiosk_mode']) && $_SESSION['kiosk_mode'] ? '?kiosk=1' : '');
    header("Location: $redirect");
    exit;
}
if (isset($_POST['remove'])) {
    if (!validateToken($_POST['csrf_token'])) die('Invalid CSRF token');
    $key = $_POST['key'] ?? '';
    if ($key && isset($_SESSION['cart'][$key])) unset($_SESSION['cart'][$key]);
    $redirect = 'cart.php' . (isset($_SESSION['kiosk_mode']) && $_SESSION['kiosk_mode'] ? '?kiosk=1' : '');
    header("Location: $redirect");
    exit;
}

// --- Fetch cart items ---
$cart_items = [];
$total = 0;
if (!empty($_SESSION['cart'])) {
    $item_ids = array_unique(array_column($_SESSION['cart'], 'item_id'));
    $items_data = [];
    if (!empty($item_ids)) {
        $placeholders = implode(',', array_fill(0, count($item_ids), '?'));
        $stmt = $conn->prepare("SELECT * FROM menu_items WHERE id IN ($placeholders)");
        $stmt->bind_param(str_repeat('i', count($item_ids)), ...$item_ids);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) $items_data[$row['id']] = $row;
    }
    foreach ($_SESSION['cart'] as $key => $entry) {
        $item_id = $entry['item_id'];
        if (!isset($items_data[$item_id])) continue;
        $item = $items_data[$item_id];
        $quantity = $entry['quantity'];
        $options = $entry['options'] ?? [];
        $option_details = !empty($options) ? getOptionDetails($conn, $options) : [];
        $modifier_total = 0;
        foreach ($option_details as $opt) $modifier_total += $opt['price_modifier'];
        $unit_price = $item['price'] + $modifier_total;
        $subtotal = $unit_price * $quantity;
        $cart_items[] = [
            'key' => $key,
            'item' => $item,
            'quantity' => $quantity,
            'options' => $option_details,
            'unit_price' => $unit_price,
            'subtotal' => $subtotal
        ];
        $total += $subtotal;
    }
}
$kiosk_mode = $kiosk_mode ?? false;
$page_title = "Shopping Cart | TAMCC Deli";
include 'includes/header.php';
?>

<div class="cart-container">
    <h1>Shopping Cart</h1>
    <?php if (empty($cart_items)): ?>
        <div class="empty-cart-message">
            <div class="w-24 h-24 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-6">
                <svg class="w-12 h-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-1.5 6M17 13l1.5 6M9 21h6M12 18v3"/></svg>
            </div>
            <h2 class="text-2xl font-bold mb-2">Your cart is empty</h2>
            <p class="text-gray-600 mb-8">Add some delicious items from our menu to get started!</p>
            <a href="<?= kiosk_url('menu.php') ?>" class="btn btn-primary">Browse Menu</a>
        </div>
    <?php else: ?>
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <!-- Cart Items -->
            <div class="lg:col-span-2">
                <div class="bg-white rounded-lg shadow overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="cart-table w-full">
                            <thead class="bg-gray-50">
                                <tr><th>Item</th><th>Options</th><th>Unit Price</th><th>Quantity</th><th>Subtotal</th><th></th></tr>
                            </thead>
                            <tbody>
                                <?php foreach ($cart_items as $cart_item): ?>
                                <tr data-key="<?= $cart_item['key'] ?>">
                                    <td><?= htmlspecialchars($cart_item['item']['name']) ?></td>
                                    <td>
                                        <?php if (!empty($cart_item['options'])): ?>
                                            <ul class="option-list">
                                                <?php foreach ($cart_item['options'] as $opt): ?>
                                                    <li><?= htmlspecialchars($opt['option_name']) ?>: <?= htmlspecialchars($opt['value_name']) ?>
                                                        (<?= ($opt['price_modifier'] > 0 ? '+' : '-') ?>$<?= number_format(abs($opt['price_modifier']), 2) ?>)
                                                    </li>
                                                <?php endforeach; ?>
                                            </ul>
                                        <?php else: ?>—<?php endif; ?>
                                    </td>
                                    <td class="text-center">$<?= number_format($cart_item['unit_price'], 2) ?></td>
                                    <td class="text-center">
                                        <input type="number" class="qty-input w-20 px-2 py-1 border rounded text-center" value="<?= $cart_item['quantity'] ?>" min="1" data-key="<?= $cart_item['key'] ?>">
                                    </td>
                                    <td class="text-center subtotal">$<?= number_format($cart_item['subtotal'], 2) ?></td>
                                    <td class="text-center">
                                        <button class="remove-item text-red-500 hover:text-red-700" data-key="<?= $cart_item['key'] ?>">
                                            <svg class="w-5 h-5 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                        </button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <!-- Order Summary -->
            <div>
                <div class="bg-white rounded-lg shadow p-6 sticky top-20">
                    <h2 class="text-xl font-bold mb-6">Order Summary</h2>
                    <div class="space-y-4 mb-6">
                        <div class="flex justify-between"><span class="text-gray-600">Subtotal</span><span id="subtotal">$<?= number_format($total, 2) ?></span></div>
                        <div class="flex justify-between"><span class="text-gray-600">Tax (10%)</span><span id="tax">$<?= number_format($total * 0.1, 2) ?></span></div>
                        <div class="border-t pt-4"><div class="flex justify-between"><span class="font-bold text-lg">Total</span><span class="font-bold text-blue-600 text-lg" id="grandTotal">$<?= number_format($total * 1.1, 2) ?></span></div></div>
                    </div>
                    <a href="<?= kiosk_url('checkout.php') ?>" class="block w-full bg-orange-500 hover:bg-orange-600 text-white text-center font-bold py-3 rounded-lg transition <?= $kiosk_mode ? 'py-4 text-lg' : '' ?>">Proceed to Checkout</a>
                    <a href="<?= kiosk_url('menu.php') ?>" class="block w-full text-center border border-gray-300 mt-3 py-2 rounded-lg hover:bg-gray-50">Continue Shopping</a>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php if ($kiosk_mode): ?>
    <a href="<?= kiosk_url('cart.php') ?>" class="floating-cart">
        <span class="dashicons dashicons-cart"></span>
        <span class="cart-count" id="cart-count-kiosk">0</span>
    </a>
<?php endif; ?>

<script>
// AJAX quantity update
document.querySelectorAll('.qty-input').forEach(input => {
    input.addEventListener('change', function() {
        const key = this.dataset.key;
        const qty = parseInt(this.value);
        if (isNaN(qty) || qty < 1) return;
        const formData = new FormData();
        formData.append('csrf_token', '<?= generateToken() ?>');
        formData.append('key', key);
        formData.append('quantity', qty);
        fetch('<?= kiosk_url('cart.php') ?>', {
            method: 'POST',
            body: formData,
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(res => res.json())
        .then(data => { if (data.success) location.reload(); else alert('Error'); });
    });
});
// AJAX remove item
document.querySelectorAll('.remove-item').forEach(btn => {
    btn.addEventListener('click', () => {
        const key = btn.dataset.key;
        const formData = new FormData();
        formData.append('csrf_token', '<?= generateToken() ?>');
        formData.append('key', key);
        formData.append('action', 'remove');
        fetch('<?= kiosk_url('cart.php') ?>', {
            method: 'POST',
            body: formData,
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(res => res.json())
        .then(data => { if (data.success) location.reload(); else alert('Error'); });
    });
});
function updateCartCount() {
    fetch('<?= kiosk_url('/get-cart-count.php') ?>')
        .then(r => r.json())
        .then(data => { document.querySelectorAll('.cart-count').forEach(el => el.textContent = data.count); });
}
updateCartCount();
</script>

<?php include 'includes/footer.php'; ?>
</body>
</html>