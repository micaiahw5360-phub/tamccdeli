<?php
session_start();
require 'config/database.php';
require 'includes/csrf.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: auth/login.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: wallet.php");
    exit;
}

if (!validateToken($_POST['csrf_token'])) {
    die('Invalid CSRF token');
}

$user_id = $_SESSION['user_id'];
$amount = floatval($_POST['amount']);

if ($amount <= 0 || $amount > 1000) {
    $_SESSION['topup_error'] = "Invalid amount. Must be between $1 and $1000.";
    header("Location: wallet.php");
    exit;
}

// Simulate successful payment (in a real app, you'd integrate a payment gateway here)
$conn->begin_transaction();
try {
    // Update user balance
    $stmt = $conn->prepare("UPDATE users SET balance = balance + ? WHERE id = ?");
    $stmt->bind_param("di", $amount, $user_id);
    $stmt->execute();

    // Record transaction
    $description = "Wallet top-up";
    $stmt2 = $conn->prepare("INSERT INTO transactions (user_id, amount, type, description) VALUES (?, ?, 'topup', ?)");
    $stmt2->bind_param("ids", $user_id, $amount, $description);
    $stmt2->execute();

    $conn->commit();
    $_SESSION['topup_success'] = "Successfully added $" . number_format($amount, 2) . " to your wallet.";
} catch (Exception $e) {
    $conn->rollback();
    $_SESSION['topup_error'] = "Transaction failed. Please try again.";
}

header("Location: wallet.php");
exit;