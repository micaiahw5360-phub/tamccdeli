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

// Add other shared functions here if needed
?>