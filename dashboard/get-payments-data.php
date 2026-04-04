<?php
require __DIR__ . '/../middleware/auth_check.php';
require __DIR__ . '/../config/database.php';
header('Content-Type: application/json');

$user_id = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT t.*, o.id as order_id FROM transactions t JOIN orders o ON t.order_id = o.id WHERE o.user_id = ? ORDER BY t.created_at DESC");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$transactions = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
echo json_encode(['transactions' => $transactions]);