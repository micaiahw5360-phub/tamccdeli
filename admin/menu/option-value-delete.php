<?php
require __DIR__ . '/../../middleware/admin_check.php';
require __DIR__ . '/../../config/database.php';
require __DIR__ . '/../../includes/csrf.php';
require_once __DIR__ . '/../../includes/functions.php'; // for clearMenuCache()

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateToken($_POST['csrf_token'])) {
        die('Invalid CSRF token');
    }

    $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
    if ($id) {
        // Get menu_item_id before deleting
        $stmt = $conn->prepare("SELECT o.menu_item_id FROM menu_item_option_values v JOIN menu_item_options o ON v.option_id = o.id WHERE v.id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $val = $stmt->get_result()->fetch_assoc();
        $menu_item_id = $val['menu_item_id'] ?? 0;

        $delete = $conn->prepare("DELETE FROM menu_item_option_values WHERE id = ?");
        $delete->bind_param("i", $id);
        $delete->execute();

        clearMenuCache();

        if ($menu_item_id) {
            $redirect = "options.php?item_id=" . $menu_item_id;
            if (isset($_SESSION['kiosk_mode']) && $_SESSION['kiosk_mode']) {
                $redirect .= '&kiosk=1';
            }
            header("Location: $redirect");
            exit;
        }
    }
}
header("Location: index.php");
exit;