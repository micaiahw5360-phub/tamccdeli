<?php
session_start();
require 'config/database.php';
require 'includes/kiosk.php';

$payment_intent = $_GET['payment_intent'] ?? null;
$redirect_status = $_GET['redirect_status'] ?? null;

if (!$payment_intent || $redirect_status !== 'succeeded') {
    // Payment failed or was cancelled
    $_SESSION['payment_error'] = 'Payment was not successful. Please try again.';
    header('Location: ' . kiosk_url('cart.php'));
    exit;
}

// Optionally, verify the payment with Stripe API (recommended)
// \Stripe\Stripe::setApiKey(getenv('STRIPE_SECRET_KEY'));
// $intent = \Stripe\PaymentIntent::retrieve($payment_intent);
// if ($intent->status !== 'succeeded') { ... }

$order_id = $_SESSION['pending_order'] ?? 0;
if ($order_id) {
    // Update order status to paid
    $stmt = $conn->prepare("UPDATE orders SET payment_status = 'paid' WHERE id = ?");
    $stmt->bind_param("i", $order_id);
    $stmt->execute();

    // Clear pending session data
    unset($_SESSION['stripe_intent_id'], $_SESSION['stripe_client_secret'], $_SESSION['pending_order'], $_SESSION['stripe_total']);

    // Redirect to order confirmation (preserve kiosk mode)
    header("Location: " . kiosk_url("order-confirmation.php?order_id=$order_id"));
    exit;
}

// Fallback – no order id found
header('Location: ' . kiosk_url('index.php'));
exit;