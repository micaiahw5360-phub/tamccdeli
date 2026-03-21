<?php
require __DIR__ . '/includes/session.php';
require 'config/database.php';
require 'includes/csrf.php';
require 'includes/functions.php'; // for getItemOptions if needed (but not used here)

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['order_id'])) {
    header('Location: menu.php');
    exit;
}

if (!validateToken($_POST['csrf_token'])) {
    die('Invalid CSRF token');
}

$order_id = intval($_POST['order_id']);
if (!$order_id) {
    header('Location: menu.php');
    exit;
}

// Fetch order items with options
$stmt = $conn->prepare("SELECT menu_item_id, quantity, options FROM order_items WHERE order_id = ?");
$stmt->bind_param("i", $order_id);
$stmt->execute();
$items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

if (empty($items)) {
    header('Location: menu.php');
    exit;
}

// Initialize cart if not exists
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

try {
    foreach ($items as $item) {
        $options = json_decode($item['options'], true) ?: [];
        $key = 'item_' . $item['menu_item_id'];
        if (!empty($options)) {
            ksort($options);
            $key .= '_opt_' . implode('_', array_map(function($k, $v) { return $k . '-' . $v; }, array_keys($options), $options));
        }

        if (isset($_SESSION['cart'][$key])) {
            $_SESSION['cart'][$key]['quantity'] += $item['quantity'];
        } else {
            $_SESSION['cart'][$key] = [
                'item_id' => $item['menu_item_id'],
                'quantity' => $item['quantity'],
                'options' => $options
            ];
        }
    }
} catch (Exception $e) {
    error_log("Reorder failed: " . $e->getMessage());
    $_SESSION['cart_error'] = "Could not add items to cart. Please try again.";
}

// Redirect to cart (preserve kiosk mode)
$redirect = 'cart.php';
if (isset($_SESSION['kiosk_mode']) && $_SESSION['kiosk_mode']) {
    $redirect .= '?kiosk=1';
}
header("Location: $redirect");
exit;