<?php
/**
 * Shared helper functions for TAMCC Deli
 */

if (!function_exists('getItemOptions')) {
    function getItemOptions($conn, $item_id) {
        $options = [];
        $stmt = $conn->prepare("SELECT * FROM menu_item_options WHERE menu_item_id = ? ORDER BY sort_order");
        $stmt->bind_param("i", $item_id);
        $stmt->execute();
        $opt_res = $stmt->get_result();
        while ($opt = $opt_res->fetch_assoc()) {
            $val_stmt = $conn->prepare("SELECT * FROM menu_item_option_values WHERE option_id = ? ORDER BY sort_order");
            $val_stmt->bind_param("i", $opt['id']);
            $val_stmt->execute();
            $opt['values'] = $val_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $options[] = $opt;
        }
        return $options;
    }
}

if (!function_exists('getOptionDetails')) {
    function getOptionDetails($conn, $optionValues) {
        if (empty($optionValues)) return [];
        static $cache = [];
        $cache_key = implode(',', array_values($optionValues));
        if (isset($cache[$cache_key])) return $cache[$cache_key];
        $ids = array_values($optionValues);
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $conn->prepare("SELECT v.*, o.option_name FROM menu_item_option_values v 
                                JOIN menu_item_options o ON v.option_id = o.id 
                                WHERE v.id IN ($placeholders)");
        $stmt->bind_param(str_repeat('i', count($ids)), ...$ids);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $cache[$cache_key] = $result;
        return $result;
    }
}

if (!function_exists('buildOrderEmail')) {
    function buildOrderEmail($order_id, $total, $net_total, $payment_method, $pickup_time, $instructions) {
        $subject = "Order Confirmation #$order_id";
        $body = "<h2>Thank you for your order!</h2>
                 <p>Your order #$order_id has been placed successfully.</p>
                 <p><strong>Total Paid:</strong> $" . number_format($net_total, 2) . "</p>
                 <p><strong>Payment Method:</strong> " . ucfirst($payment_method) . "</p>
                 <p><strong>Pickup Time:</strong> " . ($pickup_time ? date('M j, Y g:i a', strtotime($pickup_time)) : 'As soon as possible') . "</p>
                 <p><strong>Special Instructions:</strong> " . nl2br(htmlspecialchars($instructions)) . "</p>
                 <p>You can view your order details in your dashboard.</p>";
        return ['subject' => $subject, 'body' => $body];
    }
}

function getCartCount() {
    $count = 0;
    if (isset($_SESSION['cart']) && is_array($_SESSION['cart'])) {
        foreach ($_SESSION['cart'] as $item) {
            $count += $item['quantity'];
        }
    }
    return $count;
}

function getUserBalance($conn, $user_id) {
    static $balances = [];
    if (!isset($balances[$user_id])) {
        $stmt = $conn->prepare("SELECT balance FROM users WHERE id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $balances[$user_id] = $result->fetch_assoc()['balance'] ?? 0;
    }
    return $balances[$user_id];
}

require_once __DIR__ . '/cache.php';

function getMenuItemsWithOptions($conn, $category = null) {
    $cache_key = 'menu_items_' . ($category ? str_replace(' ', '_', $category) : 'all');
    $items = Cache::get($cache_key);
    if ($items === null) {
        $sql = "SELECT * FROM menu_items";
        if ($category) $sql .= " WHERE category = ?";
        $sql .= " ORDER BY FIELD(category, 'Breakfast', 'A La Carte', 'Combo', 'Beverage', 'Dessert'), sort_order, name";
        $stmt = $conn->prepare($sql);
        if ($category) $stmt->bind_param("s", $category);
        $stmt->execute();
        $result = $stmt->get_result();
        $items = $result->fetch_all(MYSQLI_ASSOC);
        foreach ($items as &$item) {
            $item['options'] = getItemOptions($conn, $item['id']);
        }
        Cache::set($cache_key, $items, 3600);
    }
    return $items;
}

function getPopularItems($conn, $limit = 6) {
    $cache_key = 'popular_items';
    $items = Cache::get($cache_key);
    if ($items === null) {
        $stmt = $conn->prepare("SELECT mi.id, mi.name, mi.price, mi.image, SUM(oi.quantity) as total_sold
                                FROM order_items oi
                                JOIN menu_items mi ON oi.menu_item_id = mi.id
                                JOIN orders o ON oi.order_id = o.id
                                WHERE o.order_date > DATE_SUB(NOW(), INTERVAL 30 DAY)
                                GROUP BY mi.id
                                ORDER BY total_sold DESC
                                LIMIT ?");
        $stmt->bind_param("i", $limit);
        $stmt->execute();
        $items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        Cache::set($cache_key, $items, 7200);
    }
    return $items;
}

function clearMenuCache() {
    $cache_keys = [
        'menu_items_all',
        'menu_items_Breakfast',
        'menu_items_A_La_Carte',
        'menu_items_Combo',
        'menu_items_Beverage',
        'menu_items_Dessert'
    ];
    foreach ($cache_keys as $key) {
        Cache::delete($key);
    }
    Cache::delete('popular_items');
}