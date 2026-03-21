<?php
require __DIR__ . '/includes/session.php';
header('Content-Type: application/json');

$count = 0;
if (isset($_SESSION['cart']) && is_array($_SESSION['cart'])) {
    foreach ($_SESSION['cart'] as $item) {
        $count += $item['quantity'];
    }
}
echo json_encode(['count' => $count]);