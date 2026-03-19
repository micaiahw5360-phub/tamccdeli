<?php
session_start();
require __DIR__ . '/config/database.php';

if (!isset($_SESSION['pending_order']) || !isset($_SESSION['stripe_intent_id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing session data']);
    exit;
}

$order_id = $_SESSION['pending_order'];

$stmt = $conn->prepare("UPDATE orders SET payment_status = 'paid' WHERE id = ?");
$stmt->bind_param("i", $order_id);
$stmt->execute();

// Clear session data
unset($_SESSION['pending_order'], $_SESSION['stripe_intent_id'], $_SESSION['stripe_client_secret'], $_SESSION['stripe_total']);

echo json_encode(['success' => true]);