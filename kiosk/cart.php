<?php
require __DIR__ . '/../includes/session.php';
require __DIR__ . '/../config/database.php';
require __DIR__ . '/../includes/kiosk.php';
require __DIR__ . '/../includes/functions.php';

$kiosk_mode = true;
$cart = $_SESSION['cart'] ?? [];
$cart_items = [];
$total = 0;

// Handle AJAX updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {
    header('Content-Type: application/json');
    if (!validateToken($_POST['csrf_token'] ?? '')) {
        echo json_encode(['success' => false, 'error' => 'Invalid CSRF']);
        exit;
    }
    
    $key = $_POST['key'] ?? '';
    $action = $_POST['action'] ?? '';
    
    if (isset($_SESSION['cart'][$key])) {
        if ($action === 'increment') {
            $_SESSION['cart'][$key]['quantity']++;
        } elseif ($action === 'decrement') {
            $_SESSION['cart'][$key]['quantity']--;
            if ($_SESSION['cart'][$key]['quantity'] <= 0) {
                unset($_SESSION['cart'][$key]);
            }
        } elseif ($action === 'remove') {
            unset($_SESSION['cart'][$key]);
        }
    }
    
    echo json_encode(['success' => true]);
    exit;
}

if (!empty($cart)) {
    $item_ids = array_unique(array_column($cart, 'item_id'));
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
    foreach ($cart as $key => $entry) {
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

$page_title = "Your Cart | TAMCC Deli Kiosk";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
    <title><?= $page_title ?></title>
    <link rel="stylesheet" href="/assets/css/global.css">
    <link rel="stylesheet" href="/assets/css/kiosk.css">
    <style>
        .kiosk-cart-page {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 2rem;
        }
        
        .cart-wrapper {
            max-width: 1200px;
            margin: 0 auto;
            background: rgba(255,255,255,0.95);
            border-radius: 2rem;
            padding: 2rem;
            animation: fadeInUp 0.5s ease;
        }
        
        .cart-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            flex-wrap: wrap;
            gap: 1rem;
        }
        
        .cart-header h1 {
            font-size: 2.5rem;
            margin: 0;
        }
        
        .empty-cart {
            text-align: center;
            padding: 4rem;
        }
        
        .empty-cart .empty-emoji {
            font-size: 5rem;
            margin-bottom: 1rem;
        }
        
        .cart-items-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .cart-items-table th {
            text-align: left;