<?php
// Start output buffering and session
ob_start();

require __DIR__ . '/includes/session.php';
require __DIR__ . '/config/database.php';
require __DIR__ . '/includes/csrf.php';
require __DIR__ . '/includes/kiosk.php';
require __DIR__ . '/includes/mail.php';
require __DIR__ . '/includes/functions.php';

// Use Stripe classes (must be at top)
use Stripe\Stripe;
use Stripe\PaymentIntent;

// For wallet/cash, we may not need Stripe; load only if needed
if (isset($_POST['payment_method']) && $_POST['payment_method'] === 'online') {
    require_once __DIR__ . '/vendor/autoload.php';
}

// ----------------------------------------------------------------------
// TIMING DEBUGGING (optional, can be removed in production)
$start_time = microtime(true);
error_log("Checkout started at: " . date('Y-m-d H:i:s'));
// ----------------------------------------------------------------------

if (empty($_SESSION['cart'])) {
    header("Location: menu.php");
    exit;
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

$after_cart = microtime(true);
error_log("Cart processing took: " . round(($after_cart - $start_time) * 1000, 2) . "ms");
// ----------------------------------------------------------------------

// Build cart items with options and prices
$cart_items = [];
$total = 0;
foreach ($cart as $key => $entry) {
    $item_id = $entry['item_id'];
    if (!isset($items_data[$item_id])) continue;
    $item = $items_data[$item_id];
    $quantity = $entry['quantity'];
    $options = $entry['options'] ?? [];

    $option_details = getOptionDetails($conn, $options); // from functions.php
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

// ----------------------------------------------------------------------
// Fetch user balance and points
$user_balance = 0;
$user_points = 0;
$user_id = $_SESSION['user_id'] ?? null;
if ($user_id) {
    static $balance_cache = [];
    if (isset($balance_cache[$user_id])) {
        $user_balance = $balance_cache[$user_id];
    } else {
        $stmt = $conn->prepare("SELECT balance, points FROM users WHERE id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $user_data = $result->fetch_assoc();
        $user_balance = $user_data['balance'] ?? 0;
        $user_points = $user_data['points'] ?? 0;
        $balance_cache[$user_id] = $user_balance;
    }
}
$after_db = microtime(true);
error_log("Database queries took: " . round(($after_db - $after_cart) * 1000, 2) . "ms");
// ----------------------------------------------------------------------

$error = '';
$original_total = $total; // keep for later use (award points based on original total? we'll use net total after discount)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // No need to load Stripe again; we already did if needed
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

    // Handle loyalty points redemption
    $points_used = 0;
    $discount = 0;
    $net_total = $total; // we'll update this if points are used
    if ($user_id && isset($_POST['use_points']) && $_POST['use_points'] == '1') {
        $points_to_use = intval($_POST['points_to_use'] ?? 0);
        $max_points = floor($total * 100); // because 100 points = $1
        $points_to_use = min($points_to_use, $user_points, $max_points);
        if ($points_to_use > 0) {
            $discount = $points_to_use / 100;
            $net_total = max(0, $total - $discount);
            $points_used = $points_to_use;
        }
    } else {
        $net_total = $total;
    }

    if (!$error) {
        $conn->begin_transaction();
        try {
            // Insert order with net_total (after discount)
            $stmt = $conn->prepare("INSERT INTO orders (user_id, guest_email, total, pickup_time, special_instructions, payment_status, payment_method, points_used) 
                                     VALUES (?, ?, ?, ?, ?, 'unpaid', ?, ?)");
            $stmt->bind_param("isdsssi", $user_id, $guest_email, $net_total, $pickup_time, $instructions, $payment_method, $points_used);
            $stmt->execute();
            $order_id = $conn->insert_id;

            // Insert order items with options (use original unit prices; discount is applied at order level)
            $stmt2 = $conn->prepare("INSERT INTO order_items (order_id, menu_item_id, quantity, price, options) VALUES (?, ?, ?, ?, ?)");
            foreach ($cart_items as $cart_item) {
                $options_json = json_encode($cart_item['options']);
                $stmt2->bind_param("iiids", $order_id, $cart_item['item']['id'], $cart_item['quantity'], $cart_item['unit_price'], $options_json);
                $stmt2->execute();
            }

            // If points were used, deduct them
            if ($points_used > 0) {
                $update_points = $conn->prepare("UPDATE users SET points = points - ? WHERE id = ?");
                $update_points->bind_param("ii", $points_used, $user_id);
                $update_points->execute();

                // Record the points usage in a transaction
                $trans = $conn->prepare("INSERT INTO transactions (user_id, amount, type, description, order_id) VALUES (?, ?, 'points_redemption', ?, ?)");
                $points_amount = -$points_used;
                $desc = "Redeemed $points_used points for discount on order #$order_id";
                $trans->bind_param("idsi", $user_id, $points_amount, $desc, $order_id);
                $trans->execute();
            }

            // Handle wallet payment (using net_total)
            if ($payment_method === 'wallet' && $user_id) {
                if ($user_balance >= $net_total) {
                    $update = $conn->prepare("UPDATE users SET balance = balance - ? WHERE id = ?");
                    $update->bind_param("di", $net_total, $user_id);
                    $update->execute();

                    $description = "Payment for order #$order_id";
                    $trans = $conn->prepare("INSERT INTO transactions (user_id, amount, type, description, order_id) VALUES (?, ?, 'payment', ?, ?)");
                    $trans->bind_param("idsi", $user_id, $net_total, $description, $order_id);
                    $trans->execute();

                    $update_order = $conn->prepare("UPDATE orders SET payment_status = 'paid' WHERE id = ?");
                    $update_order->bind_param("i", $order_id);
                    $update_order->execute();
                } else {
                    throw new Exception("Insufficient wallet balance after discount.");
                }
            }

            // Award points if logged in (based on net_total)
            if ($user_id) {
                $points_earned = floor($net_total);
                $update_points = $conn->prepare("UPDATE users SET points = points + ? WHERE id = ?");
                $update_points->bind_param("ii", $points_earned, $user_id);
                $update_points->execute();

                $update_order = $conn->prepare("UPDATE orders SET points_earned = ? WHERE id = ?");
                $update_order->bind_param("ii", $points_earned, $order_id);
                $update_order->execute();
            }

            $conn->commit();
            error_log("Transaction committed");

            // --- Build email using helper function ---
            $email = buildOrderEmail($order_id, $total, $net_total, $discount, $points_used, $payment_method, $pickup_time, $instructions);
            $subject = $email['subject'];
            $body = $email['body'];

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

if ($payment_method === 'online') {
    Stripe::setApiKey(getenv('STRIPE_SECRET_KEY'));
    $intent = PaymentIntent::create([
        'amount'   => round($net_total * 100),
        'currency' => 'usd',
        'metadata' => ['order_id' => $order_id],
    ], [
        'timeout' => 30, // request timeout in seconds
    ]);
    $_SESSION['stripe_intent_id'] = $intent->id;
    $_SESSION['stripe_client_secret'] = $intent->client_secret;
    $_SESSION['pending_order'] = $order_id;
    $_SESSION['stripe_total'] = $net_total;
    header("Location: stripe-payment.php" . ($kiosk_mode ? '?kiosk=1' : ''));
    exit;
}
            // --- Clear cart and prepare redirect ---
            $_SESSION['cart'] = [];

            if (!$user_id) {
                $_SESSION['guest_order'] = $order_id;
                $_SESSION['guest_email'] = $guest_email;
                $redirect = "guest-thanks.php";
                if ($kiosk_mode) $redirect .= '?kiosk=1';
            } else {
                $redirect = "order-confirmation.php?order_id=$order_id";
                if ($kiosk_mode) $redirect .= '&kiosk=1';
            }

            // --- Redirect immediately ---
            header("Location: $redirect");
            // Flush all buffers to send the redirect
            ob_end_flush();
            flush();

            // --- Now, after redirect, send email in the background ---
            if ($to) {
                // Allow the script to continue even if the user leaves
                ignore_user_abort(true);
                // Send email (with 10s timeout already set in mail.php)
                sendEmail($to, $subject, $body);
            }

            exit;

        } catch (Exception $e) {
            $conn->rollback();
            $error = "Failed to place order: " . $e->getMessage();
            error_log("Checkout error: " . $e->getMessage());
        }
    }
}
$after_commit = microtime(true);
error_log("Total checkout time: " . round(($after_commit - $start_time) * 1000, 2) . "ms");
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
        .points-input-group { margin-top: 0.5rem; }
        #points_amount_group { margin-top: 0.5rem; }
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
                <thead>
                    <tr>
                        <th>Item</th>
                        <th>Options</th>
                        <th>Qty</th>
                        <th>Price</th>
                        <th>Subtotal</th>
                    </thead>
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

            <?php if (isset($_SESSION['user_id']) && $user_points > 0): ?>
                <div class="form-group">
                    <label>Loyalty Points</label>
                    <div class="points-input-group">
                        <input type="checkbox" id="use_points" name="use_points" value="1">
                        <label for="use_points">Use my points (<?= $user_points ?> points available)</label>
                    </div>
                    <div id="points_amount_group" style="display: none;">
                        <label for="points_to_use">Points to use (100 points = $1.00):</label>
                        <input type="number" id="points_to_use" name="points_to_use" min="0" max="<?= min($user_points, floor($total * 100)) ?>" step="1" value="0">
                        <small>Max discount: $<?= number_format(min($total, $user_points / 100), 2) ?></small>
                    </div>
                </div>
            <?php endif; ?>

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

        // Show/hide points amount input
        const usePointsCheckbox = document.getElementById('use_points');
        const pointsGroup = document.getElementById('points_amount_group');
        if (usePointsCheckbox) {
            usePointsCheckbox.addEventListener('change', function() {
                pointsGroup.style.display = this.checked ? 'block' : 'none';
                if (!this.checked) {
                    document.getElementById('points_to_use').value = 0;
                }
            });
        }
    </script>

    <?php include 'includes/footer.php'; ?>
</body>
</html>