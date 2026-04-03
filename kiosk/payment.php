<?php
require __DIR__ . '/../includes/session.php';
require __DIR__ . '/../includes/kiosk.php';

$kiosk_mode = true;
if (empty($_SESSION['cart'])) {
    header('Location: ' . kiosk_url('/kiosk/categories.php'));
    exit;
}
// All payment logic is already in /checkout.php
header('Location: ' . kiosk_url('/checkout.php'));
exit;