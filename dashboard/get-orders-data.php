<?php
require __DIR__ . '/../middleware/auth_check.php';
require __DIR__ . '/../config/database.php';
header('Content-Type: application/json');

$user_id = $_SESSION['user_id'];
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';
if ($filter === 'all') {
    $stmt = $conn->prepare("SELECT id, total, status, order_date, payment_status FROM orders WHERE user_id = ? ORDER BY order_date DESC");
    $stmt->bind_param("i", $user_id);
} else {
    $stmt = $conn->prepare("SELECT id, total, status, order_date, payment_status FROM orders WHERE user_id = ? AND status = ? ORDER BY order_date DESC");
    $stmt->bind_param("is", $user_id, $filter);
}
$stmt->execute();
$orders = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
echo json_encode(['orders' => $orders]);