<?php
/**
 * Shared helper functions for TAMCC Deli
 */

if (!function_exists('getItemOptions')) {
    /**
     * Fetch options (size, flavour, etc.) for a menu item
     * @param mysqli $conn Database connection
     * @param int $item_id Menu item ID
     * @return array Options with their values
     */
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
    /**
     * Get option details for given option value IDs (cached)
     * @param mysqli $conn Database connection
     * @param array $optionValues Array of option value IDs
     * @return array
     */
    function getOptionDetails($conn, $optionValues) {
        if (empty($optionValues)) return [];
        
        static $cache = [];
        $cache_key = implode(',', array_values($optionValues));
        
        if (isset($cache[$cache_key])) {
            return $cache[$cache_key];
        }
        
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
    /**
     * Build email subject and body for order confirmation
     * @param int $order_id
     * @param float $total Original total
     * @param float $net_total Total after discount
     * @param float $discount Discount amount
     * @param int $points_used Points used
     * @param string $payment_method
     * @param string|null $pickup_time
     * @param string $instructions
     * @return array ['subject' => string, 'body' => string]
     */
    function buildOrderEmail($order_id, $total, $net_total, $discount, $points_used, $payment_method, $pickup_time, $instructions) {
        $subject = "Order Confirmation #$order_id";
        $body = "<h2>Thank you for your order!</h2>
                 <p>Your order #$order_id has been placed successfully.</p>
                 <p><strong>Original Total:</strong> $" . number_format($total, 2) . "</p>";
        if ($points_used > 0) {
            $body .= "<p><strong>Points Used:</strong> $points_used points (discount: $" . number_format($discount, 2) . ")</p>";
        }
        $body .= "<p><strong>Total Paid:</strong> $" . number_format($net_total, 2) . "</p>
                 <p><strong>Payment Method:</strong> " . ucfirst($payment_method) . "</p>
                 <p><strong>Pickup Time:</strong> " . ($pickup_time ? date('M j, Y g:i a', strtotime($pickup_time)) : 'As soon as possible') . "</p>
                 <p><strong>Special Instructions:</strong> " . nl2br(htmlspecialchars($instructions)) . "</p>
                 <p>You can view your order details in your dashboard.</p>";
        return ['subject' => $subject, 'body' => $body];
    }
}

/**
 * Get total number of items in the cart
 * @return int
 */
function getCartCount() {
    $count = 0;
    if (isset($_SESSION['cart']) && is_array($_SESSION['cart'])) {
        foreach ($_SESSION['cart'] as $item) {
            $count += $item['quantity'];
        }
    }
    return $count;
}

/**
 * Get user balance (cached)
 * @param mysqli $conn Database connection
 * @param int $user_id
 * @return float
 */
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

// Add other shared functions here if needed
?>