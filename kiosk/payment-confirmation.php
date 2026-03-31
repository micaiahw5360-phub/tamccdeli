<?php
require __DIR__ . '/../includes/session.php';
require __DIR__ . '/../config/database.php';
require __DIR__ . '/../includes/kiosk.php';

$payment_intent = $_GET['payment_intent'] ?? null;
$redirect_status = $_GET['redirect_status'] ?? null;

if (!$payment_intent || $redirect_status !== 'succeeded') {
    $_SESSION['payment_error'] = 'Payment was not successful. Please try again.';
    header('Location: ' . kiosk_url('/kiosk/cart.php'));
    exit;
}

// Optionally verify with Stripe API
\Stripe\Stripe::setApiKey(getenv('STRIPE_SECRET_KEY'));
$intent = \Stripe\PaymentIntent::retrieve($payment_intent);
if ($intent->status !== 'succeeded') {
    $_SESSION['payment_error'] = 'Payment not completed.';
    header('Location: ' . kiosk_url('/kiosk/cart.php'));
    exit;
}

// Retrieve stored order data
$total = $_SESSION['stripe_total'] ?? 0;
$cart_items = $_SESSION['stripe_cart_items'] ?? [];
$customer_email = $_SESSION['stripe_customer_email'] ?? '';
$customer_name = $_SESSION['stripe_customer_name'] ?? '';

if (empty($cart_items)) {
    header('Location: ' . kiosk_url('/kiosk/categories.php'));
    exit;
}

// Create order as guest
$stmt = $conn->prepare("INSERT INTO orders (guest_email, total, payment_method, payment_status) VALUES (?, ?, 'online', 'paid')");
$stmt->bind_param("sd", $customer_email, $total);
$stmt->execute();
$order_id = $conn->insert_id;

// Insert order items
$stmt = $conn->prepare("INSERT INTO order_items (order_id, menu_item_id, quantity, price, options) VALUES (?, ?, ?, ?, ?)");
foreach ($cart_items as $ci) {
    $options_json = json_encode($ci['options']);
    $stmt->bind_param("iiids", $order_id, $ci['item']['id'], $ci['quantity'], $ci['unit_price'], $options_json);
    $stmt->execute();
}

// Clear cart and session data
unset($_SESSION['cart']);
unset($_SESSION['stripe_intent_id'], $_SESSION['stripe_client_secret'], $_SESSION['stripe_total'], $_SESSION['stripe_cart_items'], $_SESSION['stripe_customer_email'], $_SESSION['stripe_customer_name']);

// Store order for receipt
$_SESSION['last_order'] = [
    'id' => $order_id,
    'items' => $cart_items,
    'total' => $total,
    'timestamp' => date('Y-m-d H:i:s'),
    'customer' => $customer_name,
    'payment_method' => 'online'
];

header('Location: ' . kiosk_url('/kiosk/confirmation.php'));
exit;