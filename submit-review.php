<?php
session_start();
require 'config/database.php';
require 'includes/csrf.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: auth/login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_review'])) {
    if (!validateToken($_POST['csrf_token'])) {
        die('Invalid CSRF token');
    }

    $item_id = intval($_POST['item_id']);
    $rating = intval($_POST['rating']);
    $comment = trim($_POST['comment']);
    $user_id = $_SESSION['user_id'];

    if ($rating < 1 || $rating > 5) {
        die('Invalid rating');
    }

    $stmt = $conn->prepare("INSERT INTO reviews (user_id, menu_item_id, rating, comment) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("iiis", $user_id, $item_id, $rating, $comment);
    $stmt->execute();

   $redirect = "menu.php#item-$item_id";
if (isset($_SESSION['kiosk_mode']) && $_SESSION['kiosk_mode']) {
    $redirect .= (strpos($redirect, '?') === false ? '?' : '&') . 'kiosk=1';
}
header("Location: $redirect");
exit;
}
?>