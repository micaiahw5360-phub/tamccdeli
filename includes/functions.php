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