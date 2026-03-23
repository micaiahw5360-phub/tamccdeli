<?php
require __DIR__ . '/includes/session.php';
require 'config/database.php';
require_once __DIR__ . '/includes/kiosk.php';

$payment_intent = $_GET['payment_intent'] ?? null;
$redirect_status = $_GET['redirect_status'] ?? null;

if (!$payment_intent || $redirect_status !== 'succeeded') {
    $_SESSION['payment_error'] = 'Payment was not successful. Please try again.';
    header('Location: ' . kiosk_url('cart.php'));
    exit;
}

// Optionally verify with Stripe API (recommended)
// \Stripe\Stripe::setApiKey(getenv('STRIPE_SECRET_KEY'));
// $intent = \Stripe\PaymentIntent::retrieve($payment_intent);
// if ($intent->status !== 'succeeded') { ... }

$type = $_SESSION['stripe_type'] ?? 'order';

if ($type === 'topup') {
    $user_id = $_SESSION['user_id'] ?? 0;
    $amount = $_SESSION['stripe_amount'] ?? 0;
    if ($user_id && $amount > 0) {
        // Update user balance
        $stmt = $conn->prepare("UPDATE users SET balance = balance + ? WHERE id = ?");
        $stmt->bind_param("di", $amount, $user_id);
        $stmt->execute();

        // Record transaction
        $stmt2 = $conn->prepare("INSERT INTO transactions (user_id, amount, type, description) VALUES (?, ?, 'topup', 'Wallet top-up')");
        $stmt2->bind_param("id", $user_id, $amount);
        $stmt2->execute();

        $_SESSION['topup_success'] = "Successfully added $" . number_format($amount, 2) . " to your wallet.";
    }

    // Clear session data
    unset($_SESSION['stripe_intent_id'], $_SESSION['stripe_client_secret'], $_SESSION['stripe_amount'], $_SESSION['stripe_type']);

    header('Location: ' . kiosk_url('wallet.php'));
    exit;
}

// Otherwise, it's an order payment (existing code)
$order_id = $_SESSION['pending_order'] ?? 0;
if ($order_id) {
    $stmt = $conn->prepare("UPDATE orders SET payment_status = 'paid' WHERE id = ?");
    $stmt->bind_param("i", $order_id);
    $stmt->execute();

    unset($_SESSION['stripe_intent_id'], $_SESSION['stripe_client_secret'], $_SESSION['pending_order'], $_SESSION['stripe_total']);

    header("Location: " . kiosk_url("order-confirmation.php?order_id=$order_id"));
    exit;
}

header('Location: ' . kiosk_url('index.php'));
exit;