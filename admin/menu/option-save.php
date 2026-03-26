<?php
require __DIR__ . '/../../middleware/admin_check.php';
require __DIR__ . '/../../config/database.php';
require __DIR__ . '/../../includes/csrf.php';
require_once __DIR__ . '/../../includes/functions.php'; // for clearMenuCache()

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateToken($_POST['csrf_token'])) {
        die('Invalid CSRF token');
    }

    $menu_item_id = intval($_POST['menu_item_id']);
    $option_name = trim($_POST['option_name']);
    $option_type = $_POST['option_type'];
    $required = isset($_POST['required']) ? 1 : 0;
    $sort_order = intval($_POST['sort_order']);

    if (empty($option_name)) {
        die('Option name is required');
    }

    $stmt = $conn->prepare("INSERT INTO menu_item_options (menu_item_id, option_name, option_type, required, sort_order) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("issii", $menu_item_id, $option_name, $option_type, $required, $sort_order);
    $stmt->execute();

    clearMenuCache();

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