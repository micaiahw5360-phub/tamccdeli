<?php
session_start();
require __DIR__ . '/config/database.php';
require __DIR__ . '/includes/csrf.php';
require __DIR__ . '/includes/kiosk.php';
require __DIR__ . '/includes/mail.php'; // Include email helper
require __DIR__ . '/includes/functions.php'; // new shared helper file
require_once __DIR__ . '/vendor/autoload.php';

use Stripe\Stripe;
use Stripe\PaymentIntent;

if (empty($_SESSION['cart'])) {
    header("Location: menu.php");
    exit;
}

// Helper to get option value details (kept here – not moved to functions.php)
function getOptionDetails($conn, $optionValues) {
    if (empty($optionValues)) return [];
    $ids = array_values($optionValues);
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $conn->prepare("SELECT v.*, o.option_name FROM menu_item_option_values v 
                            JOIN menu_item_options o ON v.option_id = o.id 
                            WHERE v.id IN ($placeholders)");
    $stmt->bind_param(str_repeat('i', count($ids)), ...$ids);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

// Fetch all distinct item IDs from cart
$cart = $_SESSION['cart'];
$item_ids = array_unique(array_column($cart, 'item_id'));
$items_data = [];
if (!empty($item_ids)) {
    $placeholders = implode(',', array_fill(0, count($item_ids), '?'));
    $stmt = $conn->prepare("SELECT * FROM menu_items WHERE id IN ($placeholders)");
    $stmt->bind_param(str_repeat('i', count($item_ids)), ...$item_ids);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $items_data[$row['id']] = $row;
    }
}

// Build cart items with options and prices
$cart_items = [];
$total = 0;
foreach ($cart as $key => $entry) {
    $item_id = $entry['item_id'];
    if (!isset($items_data[$item_id])) continue;
    $item = $items_data[$item_id];
    $quantity = $entry['quantity'];
    $options = $entry['options'] ?? [];

    $option_details = getOptionDetails($conn, $options);
    $modifier_total = 0;
    foreach ($option_details as $opt) {
        $modifier_total += $opt['price_modifier'];
    }
    $unit_price = $item['price'] + $modifier_total;
    $subtotal = $unit_price * $quantity;

    $cart_items[] = [
        'key' => $key,
        'item' => $item,
        'quantity' => $quantity,
        'options' => $option_details,
        'unit_price' => $unit_price,
        'subtotal' => $subtotal
    ];
    $total += $subtotal;
}

// Fetch user balance if logged in
$user_balance = 0;
if (isset($_SESSION['user_id'])) {
    $stmt = $conn->prepare("SELECT balance FROM users WHERE id = ?");
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    $user_balance = $result->fetch_assoc()['balance'] ?? 0;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateToken($_POST['csrf_token'])) {
        die('Invalid CSRF token');
    }

    $pickup_time = $_POST['pickup_time'] ? date('Y-m-d H:i:s', strtotime($_POST['pickup_time'])) : null;
    $instructions = trim($_POST['instructions']);
    $payment_method = $_POST['payment_method'];

    $user_id = $_SESSION['user_id'] ?? null;
    $guest_email = null;
    if (!$user_id) {
        if ($payment_method !== 'cash') {
            $error = "Guests can only pay with cash on pickup.";
        }
        $guest_email = trim($_POST['guest_email']);
        if (!filter_var($guest_email, FILTER_VALIDATE_EMAIL)) {
            $error = "Valid email required for guest checkout.";
        }
    } else {
        if ($payment_method === 'wallet' && $user_balance < $total) {
            $error = "Insufficient wallet balance.";
        }
    }

    if (!$error) {
        $conn->begin_transaction();
        try {
            // Insert order
            $stmt = $conn->prepare("INSERT INTO orders (user_id, guest_email, total, pickup_time, special_instructions, payment_status, payment_method) 
                                     VALUES (?, ?, ?, ?, ?, 'unpaid', ?)");
            $stmt->bind_param("isdsss", $user_id, $guest_email, $total, $pickup_time, $instructions, $payment_method);
            $stmt->execute();
            $order_id = $conn->insert_id;

            // Insert order items with options
            $stmt2 = $conn->prepare("INSERT INTO order_items (order_id, menu_item_id, quantity, price, options) VALUES (?, ?, ?, ?, ?)");
            foreach ($cart_items as $cart_item) {
                $options_json = json_encode($cart_item['options']);
                $stmt2->bind_param("iiids", $order_id, $cart_item['item']['id'], $cart_item['quantity'], $cart_item['unit_price'], $options_json);
                $stmt2->execute();
            }

            // Handle wallet payment
            if ($payment_method === 'wallet' && $user_id) {
                $update = $conn->prepare("UPDATE users SET balance = balance - ? WHERE id = ?");
                $update->bind_param("di", $total, $user_id);
                $update->execute();

                $description = "Payment for order #$order_id";
                $trans = $conn->prepare("INSERT INTO transactions (user_id, amount, type, description, order_id) VALUES (?, ?, 'payment', ?, ?)");
                $trans->bind_param("idsi", $user_id, $total, $description, $order_id);
                $trans->execute();

                $update_order = $conn->prepare("UPDATE orders SET payment_status = 'paid' WHERE id = ?");
                $update_order->bind_param("i", $order_id);
                $update_order->execute();
            }

            // Award points if logged in
            if ($user_id) {
                $points_earned = floor($total);
                $update_points = $conn->prepare("UPDATE users SET points = points + ? WHERE id = ?");
                $update_points->bind_param("ii", $points_earned, $user_id);
                $update_points->execute();

                $update_order = $conn->prepare("UPDATE orders SET points_earned = ? WHERE id = ?");
                $update_order->bind_param("ii", $points_earned, $order_id);
                $update_order->execute();
            }

            $conn->commit();

            // --- Send confirmation email ---
            $subject = "Order Confirmation #$order_id";
            $body = "<h2>Thank you for your order!</h2>
                     <p>Your order #$order_id has been placed successfully.</p>
                     <p><strong>Total:</strong> $" . number_format($total, 2) . "</p>
                     <p><strong>Payment Method:</strong> " . ucfirst($payment_method) . "</p>
                     <p><strong>Pickup Time:</strong> " . ($pickup_time ? date('M j, Y g:i a', strtotime($pickup_time)) : 'As soon as possible') . "</p>
                     <p><strong>Special Instructions:</strong> " . nl2br(htmlspecialchars($instructions)) . "</p>
                     <p>You can view your order details in your dashboard.</p>";

            // Determine recipient email
            if ($user_id) {
                $email_stmt = $conn->prepare("SELECT email FROM users WHERE id = ?");
                $email_stmt->bind_param("i", $user_id);
                $email_stmt->execute();
                $user_email = $email_stmt->get_result()->fetch_assoc()['email'] ?? null;
                $to = $user_email;
            } else {
                $to = $guest_email;
            }

            if ($to) {
                sendEmail($to, $subject, $body);
            }
            // --------------------------------

            // Handle online payment
            if ($payment_method === 'online') {
                Stripe::setApiKey(getenv('STRIPE_SECRET_KEY'));
                $intent = PaymentIntent::create([
                    'amount'   => round($total * 100),
                    'currency' => 'usd',
                    'metadata' => ['order_id' => $order_id],
                ]);
                $_SESSION['stripe_intent_id'] = $intent->id;
                $_SESSION['stripe_client_secret'] = $intent->client_secret;
                $_SESSION['pending_order'] = $order_id;
                $_SESSION['stripe_total'] = $total;
                header("Location: stripe-payment.php" . ($kiosk_mode ? '?kiosk=1' : ''));
                exit;
            }

            // Clear cart (order is placed)
            $_SESSION['cart'] = [];

            // Handle guest vs. logged-in redirection
            if (!$user_id) {
                // Guest order: store order info in session and redirect to guest-thanks
                $_SESSION['guest_order'] = $order_id;
                $_SESSION['guest_email'] = $guest_email;
                $redirect = "guest-thanks.php";
                if ($kiosk_mode) $redirect .= '?kiosk=1';
                header("Location: $redirect");
                exit;
            } else {
                // Logged-in user: go to order confirmation
                $redirect = "order-confirmation.php?order_id=$order_id";
                if ($kiosk_mode) $redirect .= '&kiosk=1';
                header("Location: $redirect");
                exit;
            }

        } catch (Exception $e) {
            $conn->rollback();
            $error = "Failed to place order: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Checkout | TAMCC Deli</title>
    <link rel="stylesheet" href="assets/css/global.css">
    <style>
        .loading { display: none; }
        .btn.loading .btn-text { display: none; }
        .btn.loading .loading { display: inline-block; }
        .option-list { margin: 0; padding-left: 1rem; font-size: 0.9rem; }
    </style>
</head>
<body>
    <?php include 'includes/header.php'; ?>

    <div class="checkout-container">
        <h1>Checkout</h1>
        <?php if ($error): ?><div class="error-message"><?= $error ?></div><?php endif; ?>

        <div class="order-summary">
            <h3>Order Summary</h3>
            <table>
                <thead><tr><th>Item</th><th>Options</th><th>Qty</th><th>Price</th><th>Subtotal</th></tr></thead>
                <tbody>
                <?php foreach ($cart_items as $item): ?>
                <tr>
                    <td><?= htmlspecialchars($item['item']['name']) ?></td>
                    <td>
                        <?php if (!empty($item['options'])): ?>
                            <ul class="option-list">
                                <?php foreach ($item['options'] as $opt): ?>
                                    <li><?= htmlspecialchars($opt['option_name']) ?>: <?= htmlspecialchars($opt['value_name']) ?></li>
                                <?php endforeach; ?>
                            </ul>
                        <?php else: ?>
                            —
                        <?php endif; ?>
                    </td>
                    <td><?= $item['quantity'] ?></td>
                    <td>$<?= number_format($item['unit_price'], 2) ?></td>
                    <td>$<?= number_format($item['subtotal'], 2) ?></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <div class="total">Total: $<?= number_format($total, 2) ?></div>
        </div>

        <form method="POST" id="checkout-form">
            <input type="hidden" name="csrf_token" value="<?= generateToken() ?>">

            <?php if (!isset($_SESSION['user_id'])): ?>
                <div class="form-group">
                    <label for="guest_email">Your Email (for order confirmation)</label>
                    <input type="email" id="guest_email" name="guest_email" required>
                </div>
            <?php endif; ?>

            <div class="form-group">
                <label>Pickup Time (optional)</label>
                <input type="datetime-local" name="pickup_time">
            </div>
            <div class="form-group">
                <label>Special Instructions</label>
                <textarea name="instructions" rows="3"></textarea>
            </div>

            <div class="form-group">
                <label>Payment Method</label>
                <select name="payment_method" required>
                    <option value="cash">Cash on Pickup</option>
                    <?php if (isset($_SESSION['user_id'])): ?>
                        <option value="wallet">Wallet Balance ($<?= number_format($user_balance, 2) ?>)</option>
                        <option value="online">Online Payment (Card)</option>
                    <?php endif; ?>
                </select>
            </div>

            <button type="submit" class="btn btn-primary" id="place-order-btn">
                <span class="btn-text">Place Order</span>
                <span class="loading">⏳</span>
            </button>
            <a href="<?= kiosk_url('cart.php') ?>" class="btn">Return to Cart</a>
        </form>
    </div>

    <script>
        document.getElementById('checkout-form').addEventListener('submit', function() {
            const btn = document.getElementById('place-order-btn');
            btn.classList.add('loading');
            btn.disabled = true;
        });
    </script>

    <?php include 'includes/footer.php'; ?>
</body>
</html>