<?php
require __DIR__ . '/../../middleware/admin_check.php';
require __DIR__ . '/../../config/database.php';
require __DIR__ . '/../../includes/csrf.php';
require_once __DIR__ . '/../../includes/functions.php'; // for clearMenuCache()

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateToken($_POST['csrf_token'])) {
        die('Invalid CSRF token');
    }

    $option_id = intval($_POST['option_id']);
    $value_name = trim($_POST['value_name']);
    $price_modifier = floatval($_POST['price_modifier']);
    $sort_order = intval($_POST['sort_order']);

    if (empty($value_name)) {
        die('Value name is required');
    }

    $stmt = $conn->prepare("INSERT INTO menu_item_option_values (option_id, value_name, price_modifier, sort_order) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("isdi", $option_id, $value_name, $price_modifier, $sort_order);
    $stmt->execute();

    clearMenuCache(); // Clear cache after adding value

    // Get menu_item_id for redirection
    $opt_stmt = $conn->prepare("SELECT menu_item_id FROM menu_item_options WHERE id = ?");
    $opt_stmt->bind_param("i", $option_id);
    $opt_stmt->execute();
    $opt = $opt_stmt->get_result()->fetch_assoc();
    $menu_item_id = $opt['menu_item_id'];

    $redirect = "options.php?item_id=" . $menu_item_id;
    if (isset($_SESSION['kiosk_mode']) && $_SESSION['kiosk_mode']) {
        $redirect .= '&kiosk=1';
    }
    header("Location: $redirect");
    exit;
} else {
    header('Location: index.php');
    exit;
}