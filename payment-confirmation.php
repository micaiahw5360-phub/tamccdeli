<?php
require __DIR__ . '/../includes/session.php';
require __DIR__ . '/../config/database.php';
require __DIR__ . '/../includes/kiosk.php';
require __DIR__ . '/../includes/mail.php';  // <-- Add this line

$payment_intent = $_GET['payment_intent'] ?? null;
$redirect_status = $_GET['redirect_status'] ?? null;

if (!$payment_intent || $redirect_status !== 'succeeded') {
    $_SESSION['payment_error'] = 'Payment was not successful. Please try again.';
    header('Location: ' . kiosk_url('/cart.php'));
    exit;
}

$type = $_SESSION['stripe_type'] ?? 'order';

if ($type === 'topup') {
    $user_id = $_SESSION['user_id'] ?? 0;
    $amount = $_SESSION['stripe_amount'] ?? 0;
    if ($user_id && $amount > 0) {
        // Update balance
        $stmt = $conn->prepare("UPDATE users SET balance = balance + ? WHERE id = ?");
        $stmt->bind_param("di", $amount, $user_id);
        $stmt->execute();
        
        // Record transaction
        $stmt2 = $conn->prepare("INSERT INTO transactions (user_id, amount, type, description) VALUES (?, ?, 'topup', 'Wallet top-up')");
        $stmt2->bind_param("id", $user_id, $amount);
        $stmt2->execute();
        
        // Fetch user email
        $stmt3 = $conn->prepare("SELECT email FROM users WHERE id = ?");
        $stmt3->bind_param("i", $user_id);
        $stmt3->execute();
        $user = $stmt3->get_result()->fetch_assoc();
        
        if ($user && !empty($user['email'])) {
            $emailData = buildTopupEmail($amount);
            sendEmail($user['email'], $emailData['subject'], $emailData['body']);
        }
        
        $_SESSION['topup_success'] = "Successfully added $" . number_format($amount, 2) . " to your wallet.";
    }
    unset($_SESSION['stripe_intent_id'], $_SESSION['stripe_client_secret'], $_SESSION['stripe_amount'], $_SESSION['stripe_type']);
    header('Location: ' . kiosk_url('/wallet.php'));
    exit;
}

// Otherwise, it's an order payment
$order_id = $_SESSION['pending_order'] ?? 0;
if ($order_id) {
    // Update order status
    $stmt = $conn->prepare("UPDATE orders SET payment_status = 'paid' WHERE id = ?");
    $stmt->bind_param("i", $order_id);
    $stmt->execute();
    
    // Fetch order details and user email
    $stmt2 = $conn->prepare("
        SELECT o.*, u.email, u.username 
        FROM orders o 
        LEFT JOIN users u ON o.user_id = u.id 
        WHERE o.id = ?
    ");
    $stmt2->bind_param("i", $order_id);
    $stmt2->execute();
    $order = $stmt2->get_result()->fetch_assoc();
    
    if ($order) {
        $to = $order['email'] ?? $order['guest_email'] ?? null;
        if ($to) {
            $emailData = buildOrderEmail(
                $order_id,
                $order['total'],
                $order['payment_method'],
                $order['pickup_time'],
                $order['special_instructions']
            );
            sendEmail($to, $emailData['subject'], $emailData['body']);
        }
    }
    
    unset($_SESSION['stripe_intent_id'], $_SESSION['stripe_client_secret'], $_SESSION['pending_order'], $_SESSION['stripe_total']);
    header("Location: " . kiosk_url("/order-confirmation.php?order_id=$order_id"));
    exit;
}

header('Location: ' . kiosk_url('/index.php'));
exit;