<?php 
require __DIR__ . '/../includes/session.php';
require __DIR__ . '/../config/database.php';
require __DIR__ . '/../includes/csrf.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid method']);
    exit;
}

if (!isset($_POST['csrf_token']) || !validateToken($_POST['csrf_token'])) {
    echo json_encode(['success' => false, 'error' => 'Invalid CSRF token']);
    exit;
}

$item_id = (int)($_POST['item_id'] ?? 0);
$quantity = max(1, (int)($_POST['quantity'] ?? 1));
$options_json = $_POST['options'] ?? '{}';
$options = json_decode($options_json, true);
if (!is_array($options)) $options = [];

// Get item details
$stmt = $conn->prepare("SELECT id, name, price FROM menu_items WHERE id = ?");
$stmt->bind_param("i", $item_id);
$stmt->execute();
$item = $stmt->get_result()->fetch_assoc();
if (!$item) {
    echo json_encode(['success' => false, 'error' => 'Item not found']);
    exit;
}

// Calculate total price including option modifiers
$total_price = (float)$item['price'];
$option_ids = [];
foreach ($options as $opt_id => $value_id) {
    $stmt = $conn->prepare("SELECT price_modifier FROM menu_item_option_values WHERE id = ?");
    $stmt->bind_param("i", $value_id);
    $stmt->execute();
    $opt = $stmt->get_result()->fetch_assoc();
    if ($opt) {
        $total_price += (float)$opt['price_modifier'];
        $option_ids[$opt_id] = $value_id;
    }
}

if (!isset($_SESSION['cart'])) $_SESSION['cart'] = [];

// Check if identical item + options already exists
$found_key = null;
foreach ($_SESSION['cart'] as $key => $existing) {
    if ($existing['item_id'] == $item_id && $existing['options'] == $option_ids) {
        $found_key = $key;
        break;
    }
}

if ($found_key !== null) {
    $_SESSION['cart'][$found_key]['quantity'] += $quantity;
} else {
    $key = uniqid('cart_', true);
    $_SESSION['cart'][$key] = [
        'item_id' => $item_id,
        'name' => $item['name'],
        'price' => $total_price,
        'quantity' => $quantity,
        'options' => $option_ids
    ];
}

$cart_count = array_sum(array_column($_SESSION['cart'], 'quantity'));
echo json_encode(['success' => true, 'count' => $cart_count]);