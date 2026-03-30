<?php
require __DIR__ . '/includes/session.php';
require 'config/database.php';
require 'includes/csrf.php';
require_once __DIR__ . '/includes/kiosk.php';
require 'includes/functions.php';

// Initialize cart if not exists
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

// Helper function for option details
function getOptionDetails($conn, $optionValues) {
    if (empty($optionValues)) return [];
    $ids = array_values($optionValues);
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $conn->prepare("SELECT v.*, o.option_name FROM menu_item_option_values v 
                            JOIN menu_item_options o ON v.option_id = o.id 
                            WHERE v.id IN ($placeholders)");
    $stmt->bind_param(str_repeat('i', count($ids)), ...$ids);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

// Handle AJAX add
if (isset($_GET['action']) && $_GET['action'] === 'add' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateToken($_POST['csrf_token'] ?? '')) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Invalid CSRF']);
        exit;
    }
    $item_id = intval($_POST['item_id'] ?? 0);
    $quantity = max(1, intval($_POST['quantity'] ?? 1));
    $options = $_POST['options'] ?? [];
    if (!$item_id) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid item']);
        exit;
    }
    $key = 'item_' . $item_id;
    if (!empty($options)) {
        ksort($options);
        $key .= '_opt_' . implode('_', array_map(function($k, $v) { return $k . '-' . $v; }, array_keys($options), $options));
    }
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

// Handle update cart
if (isset($_POST['update'])) {
    if (!validateToken($_POST['csrf_token'])) die('Invalid CSRF');
    if (isset($_POST['quantity']) && is_array($_POST['quantity'])) {
        foreach ($_POST['quantity'] as $key => $qty) {
            $qty = intval($qty);
            if ($qty <= 0) {
                unset($_SESSION['cart'][$key]);
            } elseif (isset($_SESSION['cart'][$key])) {
                $_SESSION['cart'][$key]['quantity'] = $qty;
            }
        }
    }
    header('Location: ' . kiosk_url('cart.php'));
    exit;
}

// Handle remove item
if (isset($_POST['remove'])) {
    if (!validateToken($_POST['csrf_token'])) die('Invalid CSRF');
    $key = $_POST['key'] ?? '';
    if ($key && isset($_SESSION['cart'][$key])) {
        unset($_SESSION['cart'][$key]);
    }
    header('Location: ' . kiosk_url('cart.php'));
    exit;
}

// Fetch cart items for display
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
        while ($row = $result->fetch_assoc()) {
            $items_data[$row['id']] = $row;
        }
    }
    foreach ($_SESSION['cart'] as $key => $entry) {
        $item_id = $entry['item_id'];
        if (!isset($items_data[$item_id])) continue;
        $item = $items_data[$item_id];
        $quantity = $entry['quantity'];
        $options = $entry['options'] ?? [];
        $option_details = getOptionDetails($conn, $options);
        $modifier_total = 0;
        foreach ($option_details as $opt) {
            $modifier_total += $opt['price_modifier'];
        }
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

$page_title = "Shopping Cart | TAMCC Deli";
include 'includes/header.php';
?>

<div class="container">
    <h1 class="text-3xl font-bold mb-4">Shopping Cart</h1>
    <?php if (empty($cart_items)): ?>
        <div class="text-center py-12">
            <div class="inline-flex items-center justify-center w-24 h-24 bg-gray-100 rounded-full mb-6">
                <span class="dashicons dashicons-cart text-gray-400" style="font-size: 3rem;"></span>
            </div>
            <h2 class="text-2xl font-bold mb-2">Your cart is empty</h2>
            <p class="text-gray-600 mb-8">Add some delicious items from our menu to get started!</p>
            <a href="<?= kiosk_url('menu.php') ?>" class="btn btn-primary">Browse Menu</a>
        </div>
    <?php else: ?>
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <!-- Cart Items -->
            <div class="lg:col-span-2">
                <div class="card">
                    <div class="card-content p-0">
                        <!-- Desktop Table -->
                        <div class="hidden md:block overflow-x-auto">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Item</th>
                                        <th>Details</th>
                                        <th>Price</th>
                                        <th>Quantity</th>
                                        <th>Subtotal</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($cart_items as $item): ?>
                                    <tr>
                                        <td>
                                            <img src="<?= htmlspecialchars($item['item']['image']) ?>" alt="<?= htmlspecialchars($item['item']['name']) ?>" class="w-20 h-20 object-cover rounded">
                                        </td>
                                        <td>
                                            <p class="font-medium"><?= htmlspecialchars($item['item']['name']) ?></p>
                                            <?php if (!empty($item['options'])): ?>
                                                <p class="text-sm text-gray-500">
                                                    <?= implode(', ', array_map(function($opt) { return htmlspecialchars($opt['value_name']); }, $item['options'])) ?>
                                                </p>
                                            <?php endif; ?>
                                        </td>
                                        <td>$<?= number_format($item['unit_price'], 2) ?></td>
                                        <td>
                                            <form method="post" class="inline">
                                                <input type="hidden" name="csrf_token" value="<?= generateToken() ?>">
                                                <input type="number" name="quantity[<?= $item['key'] ?>]" value="<?= $item['quantity'] ?>" min="1" class="form-input w-20">
                                        </td>
                                        <td class="font-medium">$<?= number_format($item['subtotal'], 2) ?></td>
                                        <td>
                                            <button type="submit" name="update" class="btn btn-sm btn-primary mr-2">Update</button>
                                            </form>
                                            <form method="post" class="inline">
                                                <input type="hidden" name="csrf_token" value="<?= generateToken() ?>">
                                                <input type="hidden" name="key" value="<?= $item['key'] ?>">
                                                <button type="submit" name="remove" class="btn btn-sm btn-danger">Remove</button>
                                            </form>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <!-- Mobile Card View -->
                        <div class="md:hidden divide-y">
                            <?php foreach ($cart_items as $item): ?>
                            <div class="p-4">
                                <div class="flex space-x-4">
                                    <img src="<?= htmlspecialchars($item['item']['image']) ?>" alt="<?= htmlspecialchars($item['item']['name']) ?>" class="w-20 h-20 object-cover rounded">
                                    <div class="flex-1">
                                        <h3 class="font-medium mb-1"><?= htmlspecialchars($item['item']['name']) ?></h3>
                                        <?php if (!empty($item['options'])): ?>
                                            <p class="text-gray-500 text-sm mb-2">
                                                <?= implode(', ', array_map(function($opt) { return htmlspecialchars($opt['value_name']); }, $item['options'])) ?>
                                            </p>
                                        <?php endif; ?>
                                        <div class="flex items-center space-x-4 mb-2">
                                            <form method="post" class="inline">
                                                <input type="hidden" name="csrf_token" value="<?= generateToken() ?>">
                                                <input type="number" name="quantity[<?= $item['key'] ?>]" value="<?= $item['quantity'] ?>" min="1" class="form-input w-16">
                                                <button type="submit" name="update" class="btn btn-sm btn-primary">Update</button>
                                            </form>
                                            <span class="font-medium">$<?= number_format($item['subtotal'], 2) ?></span>
                                        </div>
                                        <form method="post" class="inline">
                                            <input type="hidden" name="csrf_token" value="<?= generateToken() ?>">
                                            <input type="hidden" name="key" value="<?= $item['key'] ?>">
                                            <button type="submit" name="remove" class="text-red-500 hover:text-red-700">Remove</button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
            <!-- Order Summary -->
            <div>
                <div class="card sticky top-20">
                    <div class="card-content">
                        <h2 class="text-xl font-bold mb-4">Order Summary</h2>
                        <div class="space-y-4">
                            <div class="flex justify-between">
                                <span class="text-gray-600">Subtotal</span>
                                <span class="font-medium">$<?= number_format($total, 2) ?></span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-600">Tax (10%)</span>
                                <span class="font-medium">$<?= number_format($total * 0.1, 2) ?></span>
                            </div>
                            <div class="border-t pt-4">
                                <div class="flex justify-between">
                                    <span class="font-bold text-lg">Total</span>
                                    <span class="font-bold text-primary text-xl">$<?= number_format($total * 1.1, 2) ?></span>
                                </div>
                            </div>
                        </div>
                        <a href="<?= kiosk_url('checkout.php') ?>" class="btn btn-accent w-full mt-6">Proceed to Checkout</a>
                        <a href="<?= kiosk_url('menu.php') ?>" class="btn btn-outline w-full mt-3">Continue Shopping</a>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php include 'includes/footer.php'; ?>