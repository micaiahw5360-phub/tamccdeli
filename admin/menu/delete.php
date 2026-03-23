<?php
require __DIR__ . '/../../middleware/admin_check.php';
require __DIR__ . '/../../config/database.php';
require __DIR__ . '/includes/csrf.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateToken($_POST['csrf_token'])) {
        die('Invalid CSRF token');
    }
    $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
    if ($id) {
        $stmt = $conn->prepare("DELETE FROM menu_items WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
    }
}
$redirect = "index.php";
if (isset($_SESSION['kiosk_mode']) && $_SESSION['kiosk_mode']) {
    $redirect .= '?kiosk=1';
}
header("Location: $redirect");
exit;