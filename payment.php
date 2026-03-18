<?php
session_start();
require "config/database.php";
require "includes/csrf.php";

// Check if user is logged in and has a pending order
if (!isset($_SESSION['user_id']) || !isset($_SESSION['pending_order'])) {
    header("Location: index.php");
    exit;
}

$order_id = $_SESSION['pending_order'];

// Fetch order details
$stmt = $conn->prepare("SELECT * FROM orders WHERE id = ? AND user_id = ?");
$stmt->bind_param("ii", $order_id, $_SESSION['user_id']);
$stmt->execute();
$order = $stmt->get_result()->fetch_assoc();

if (!$order) {
    unset($_SESSION['pending_order']);
    header("Location: index.php");
    exit;
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateToken($_POST['csrf_token'])) {
        die('Invalid CSRF token');
    }

    // Simulate payment processing
    $card_number = preg_replace('/\s+/', '', $_POST['card_number']);
    $expiry = $_POST['expiry'];
    $cvv = $_POST['cvv'];
    $cardholder = trim($_POST['cardholder']);

    // Basic validation (simulated)
    if (strlen($card_number) != 16 || !is_numeric($card_number)) {
        $error = "Invalid card number. Must be 16 digits.";
    } elseif (!preg_match('/^\d{2}\/\d{2}$/', $expiry)) {
        $error = "Invalid expiry format. Use MM/YY.";
    } elseif (strlen($cvv) != 3 || !is_numeric($cvv)) {
        $error = "Invalid CVV. Must be 3 digits.";
    } elseif (empty($cardholder)) {
        $error = "Cardholder name is required.";
    } else {
        // Simulate successful payment
        $transaction_id = 'TXN' . time() . rand(100, 999);
        $payment_date = date('Y-m-d H:i:s');

        // Update order
        $update = $conn->prepare("UPDATE orders SET payment_status = 'paid', payment_date = ? WHERE id = ?");
        $update->bind_param("si", $payment_date, $order_id);
        $update->execute();

        // Insert payment record
        $insert = $conn->prepare("INSERT INTO payments (order_id, amount, payment_method, transaction_id, status, payment_date) 
                                   VALUES (?, ?, 'online', ?, 'completed', ?)");
        $insert->bind_param("idss", $order_id, $order['total'], $transaction_id, $payment_date);
        $insert->execute();

        // Clear pending order from session
        unset($_SESSION['pending_order']);

        // Redirect to confirmation
        header("Location: order-confirmation.php?order_id=$order_id");
        exit;
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Payment | TAMCC Deli</title>
    <link rel="stylesheet" href="assets/css/global.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>

    <div class="checkout-container">
        <h1>Complete Payment</h1>

        <?php if ($error): ?>
            <div class="error-message"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <div class="order-summary">
            <h3>Order Summary</h3>
            <p><strong>Order #<?= $order['id'] ?></strong></p>
            <p><strong>Total:</strong> $<?= number_format($order['total'], 2) ?></p>
        </div>

        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?= generateToken() ?>">

            <div class="form-group">
                <label>Card Number</label>
                <input type="text" name="card_number" placeholder="1234 5678 9012 3456" maxlength="19" required>
            </div>

            <div class="row" style="display: flex; gap: 1rem;">
                <div class="form-group" style="flex: 1;">
                    <label>Expiry (MM/YY)</label>
                    <input type="text" name="expiry" placeholder="MM/YY" maxlength="5" required>
                </div>
                <div class="form-group" style="flex: 1;">
                    <label>CVV</label>
                    <input type="text" name="cvv" placeholder="123" maxlength="3" required>
                </div>
            </div>

            <div class="form-group">
                <label>Cardholder Name</label>
                <input type="text" name="cardholder" placeholder="John Doe" required>
            </div>

            <button type="submit" class="btn btn-primary">Pay $<?= number_format($order['total'], 2) ?></button>
            <a href="<?= kiosk_url('cart.php') ?>" class="btn" style="background: #6c757d;">Cancel</a>
        </form>
    </div>

    <?php include 'includes/footer.php'; ?>
</body>
</html>