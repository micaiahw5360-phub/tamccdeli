<?php
require __DIR__ . '/../config/database.php';
require __DIR__ . '/../includes/kiosk.php';

$email = $_GET['email'] ?? '';
if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false]);
    exit;
}

$stmt = $conn->prepare("SELECT id, username, balance FROM users WHERE email = ? AND is_active = 1");
$stmt->bind_param("s", $email);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

if ($user) {
    echo json_encode([
        'success' => true,
        'name' => $user['username'],
        'balance' => (float)$user['balance']
    ]);
} else {
    echo json_encode(['success' => false, 'name' => '']);
}