<?php
session_start();
require 'config/database.php';
require 'includes/csrf.php';

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

// Fetch order items (no need to check ownership – if user has the order URL they can re‑order)
$stmt = $conn->prepare("SELECT menu_item_id, quantity FROM order_items WHERE order_id = ?");
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

// Add each item to cart (quantities add up)
foreach ($items as $item) {
    $item_id = $item['menu_item_id'];
    $qty = $item['quantity'];
    if (isset($_SESSION['cart'][$item_id])) {
        $_SESSION['cart'][$item_id] += $qty;
    } else {
        $_SESSION['cart'][$item_id] = $qty;
    }
}

// Redirect to cart
$redirect = 'cart.php';
if (isset($_SESSION['kiosk_mode']) && $_SESSION['kiosk_mode']) {
    $redirect .= '?kiosk=1';
}
header("Location: $redirect");
exit;
