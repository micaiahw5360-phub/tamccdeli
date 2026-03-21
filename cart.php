<?php
require __DIR__ . '/includes/session.php';
require 'config/database.php';
require 'includes/csrf.php';
require 'includes/kiosk.php';
require 'includes/functions.php'; // new shared helper file

// Initialize cart if not exists (new structure)
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

// Helper function to get option value details (for display) – kept as is (not moved)
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

// --- AJAX Add to Cart handler (new) ---
if (isset($_GET['action']) && $_GET['action'] === 'add' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF token (if present in form)
    if (!validateToken($_POST['csrf_token'] ?? '')) {
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
            echo json_encode(['success' => false, 'error' => 'Invalid CSRF']);
            exit;
        } else {
            die('Invalid CSRF token');
        }
    }

    $item_id = intval($_POST['item_id'] ?? 0);
    $quantity = max(1, intval($_POST['quantity'] ?? 1));
    $options = $_POST['options'] ?? []; // array of option_id => value_id

    if (!$item_id) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid item']);
        exit;
    }

    // Generate a unique key for this item + options
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

    // Return JSON response for AJAX
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'cart_count' => array_sum(array_column($_SESSION['cart'], 'quantity'))
        ]);
        exit;
    }

    // Fallback for non-AJAX (should not happen with our JS)
    header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? 'menu.php'));
    exit;
}

// --- Handle Update Cart ---
if (isset($_POST['update'])) {
    if (!validateToken($_POST['csrf_token'])) {
        die('Invalid CSRF token');
    }
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
    $redirect = 'cart.php';
    if (isset($_SESSION['kiosk_mode']) && $_SESSION['kiosk_mode']) {
        $redirect .= '?kiosk=1';
    }
    header("Location: $redirect");
    exit;
}

// --- Handle Remove Item ---
if (isset($_POST['remove'])) {
    if (!validateToken($_POST['csrf_token'])) {
        die('Invalid CSRF token');
    }
    $key = $_POST['key'] ?? '';
    if ($key && isset($_SESSION['cart'][$key])) {
        unset($_SESSION['cart'][$key]);
    }
    $redirect = 'cart.php';
    if (isset($_SESSION['kiosk_mode']) && $_SESSION['kiosk_mode']) {
        $redirect .= '?kiosk=1';
    }
    header("Location: $redirect");
    exit;
}

// --- Fetch cart items for display ---
$cart_items = [];
$total = 0;
if (!empty($_SESSION['cart'])) {
    // Get all distinct item IDs
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

    // Build cart items with options
    foreach ($_SESSION['cart'] as $key => $entry) {
        $item_id = $entry['item_id'];
        if (!isset($items_data[$item_id])) continue; // item missing? skip

        $item = $items_data[$item_id];
        $quantity = $entry['quantity'];
        $options = $entry['options'] ?? [];

        // Get option value details
        $option_details = [];
        if (!empty($options)) {
            $option_details = getOptionDetails($conn, $options);
        }

        // Calculate price modifiers
        $base_price = $item['price'];
        $modifier_total = 0;
        foreach ($option_details as $opt) {
            $modifier_total += $opt['price_modifier'];
        }
        $unit_price = $base_price + $modifier_total;
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
?>
<!DOCTYPE html>
<html>
<head>
    <title>Shopping Cart | TAMCC Deli</title>
    <link rel="stylesheet" href="assets/css/global.css">
    <style>
        .cart-container { min-height: 60vh; display: flex; flex-direction: column; }
        .empty-cart-message { flex: 1; display: flex; flex-direction: column; align-items: center; justify-content: center; padding: var(--space-xl) 0; text-align: center; }
        .empty-cart-message .dashicons { font-size: 5rem; width: auto; height: auto; color: var(--neutral-400); margin-bottom: var(--space); }
        .empty-cart-message h2 { color: var(--neutral-700); margin-bottom: var(--space); }
        .option-list { margin: 0; padding-left: 1rem; font-size: 0.9rem; color: var(--neutral-600); }
        .option-list li { list-style: disc; }
    </style>
</head>
<body>
    <?php include 'includes/header.php'; ?>

    <div class="cart-container">
        <h1>Your Cart</h1>
        <?php if (empty($cart_items)): ?>
            <div class="empty-cart-message">
                <span class="dashicons dashicons-cart"></span>
                <h2>Your cart is empty</h2>
                <p>Looks like you haven't added anything yet.</p>
                <a href="<?= kiosk_url('menu.php') ?>" class="btn btn-primary">Browse Menu</a>
            </div>
        <?php else: ?>
            <form method="post">
                <input type="hidden" name="csrf_token" value="<?= generateToken() ?>">
                <div class="table-responsive">
                     <table>
                        <thead>
                             <tr>
                                <th>Item</th>
                                <th>Options</th>
                                <th>Unit Price</th>
                                <th>Quantity</th>
                                <th>Subtotal</th>
                                <th></th>
                             </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($cart_items as $cart_item): ?>
                            <tr>
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
                                    <?php else: ?>
                                        —
                                    <?php endif; ?>
                                 </td>
                                 <td>$<?= number_format($cart_item['unit_price'], 2) ?></td>
                                 <td>
                                    <input type="number" name="quantity[<?= $cart_item['key'] ?>]" value="<?= $cart_item['quantity'] ?>" min="0" max="10" style="width:60px;">
                                 </td>
                                 <td>$<?= number_format($cart_item['subtotal'], 2) ?></td>
                                 <td>
                                    <form method="post" style="display:inline;">
                                        <input type="hidden" name="csrf_token" value="<?= generateToken() ?>">
                                        <input type="hidden" name="key" value="<?= $cart_item['key'] ?>">
                                        <button type="submit" name="remove" class="btn btn-danger btn-small">Remove</button>
                                    </form>
                                 </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                     </table>
                </div>
                <div class="total">Total: $<?= number_format($total, 2) ?></div>
                <button type="submit" name="update" class="btn">Update Cart</button>
                <a href="<?= kiosk_url('checkout.php') ?>" class="btn btn-accent">Proceed to Checkout</a>
            </form>
        <?php endif; ?>
    </div>

    <?php include 'includes/footer.php'; ?>
</body>
</html>