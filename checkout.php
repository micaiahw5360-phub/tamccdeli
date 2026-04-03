<?php
ob_start();

require __DIR__ . '/includes/session.php';
require __DIR__ . '/config/database.php';
require __DIR__ . '/includes/csrf.php';
require __DIR__ . '/includes/kiosk.php';
require __DIR__ . '/includes/mail.php';
require __DIR__ . '/includes/functions.php';

use Stripe\Stripe;
use Stripe\PaymentIntent;

if (isset($_POST['payment_method']) && $_POST['payment_method'] === 'online') {
    require_once __DIR__ . '/vendor/autoload.php';
}

if (empty($_SESSION['cart'])) {
    header("Location: menu.php");
    exit;
}

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

$user_id = $_SESSION['user_id'] ?? null;
$user_balance = 0;
$user_name = '';
if ($user_id) {
    $stmt = $conn->prepare("SELECT username, balance FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $user_balance = $user['balance'] ?? 0;
    $user_name = $user['username'] ?? '';
}

$error = '';
$show_account_panel = ($kiosk_mode && !$user_id); // Show email lookup in kiosk mode for guests

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateToken($_POST['csrf_token'])) die('Invalid CSRF token');

    $pickup_time = $_POST['pickup_time'] ? date('Y-m-d H:i:s', strtotime($_POST['pickup_time'])) : null;
    $instructions = trim($_POST['instructions']);
    $payment_method = $_POST['payment_method'];

    // For kiosk mode with account lookup: we may have a temporary user_id from the AJAX lookup
    $temp_user_id = $_POST['temp_user_id'] ?? null;
    $guest_email = null;

    if ($kiosk_mode && !$user_id && $temp_user_id) {
        // User identified via email in kiosk mode – use that account
        $user_id = intval($temp_user_id);
        // Fetch balance again for this user
        $stmt = $conn->prepare("SELECT balance FROM users WHERE id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $user_balance = $stmt->get_result()->fetch_assoc()['balance'] ?? 0;
    }

    if (!$user_id) {
        // Guest checkout (no account)
        if ($payment_method !== 'cash') {
            $error = "Guests can only pay with cash on pickup. Please create an account or log in to use wallet/card.";
        }
        $guest_email = trim($_POST['guest_email'] ?? '');
        if (!filter_var($guest_email, FILTER_VALIDATE_EMAIL)) {
            $error = "Valid email required for guest checkout.";
        }
    } else {
        // Logged-in user or identified kiosk user
        if ($payment_method === 'wallet' && $user_balance < $total) {
            $error = "Insufficient wallet balance. Please choose another payment method.";
        }
    }

    if (!$error) {
        $conn->begin_transaction();
        try {
            $stmt = $conn->prepare("INSERT INTO orders (user_id, guest_email, total, pickup_time, special_instructions, payment_status, payment_method, source) 
                                     VALUES (?, ?, ?, ?, ?, 'unpaid', ?, 'web')");
            $stmt->bind_param("isdsss", $user_id, $guest_email, $total, $pickup_time, $instructions, $payment_method);
            $stmt->execute();
            $order_id = $conn->insert_id;

            $stmt2 = $conn->prepare("INSERT INTO order_items (order_id, menu_item_id, quantity, price, options) VALUES (?, ?, ?, ?, ?)");
            foreach ($cart_items as $cart_item) {
                $options_json = json_encode($cart_item['options']);
                $stmt2->bind_param("iiids", $order_id, $cart_item['item']['id'], $cart_item['quantity'], $cart_item['unit_price'], $options_json);
                $stmt2->execute();
            }

            if ($payment_method === 'wallet' && $user_id) {
                $new_balance = $user_balance - $total;
                $update = $conn->prepare("UPDATE users SET balance = ? WHERE id = ?");
                $update->bind_param("di", $new_balance, $user_id);
                $update->execute();

                $description = "Order #$order_id payment";
                $trans = $conn->prepare("INSERT INTO transactions (user_id, amount, type, description, order_id) VALUES (?, ?, 'payment', ?, ?)");
                $trans->bind_param("idsi", $user_id, $total, $description, $order_id);
                $trans->execute();

                $update_order = $conn->prepare("UPDATE orders SET payment_status = 'paid' WHERE id = ?");
                $update_order->bind_param("i", $order_id);
                $update_order->execute();
            }

            $conn->commit();

            // Prepare email recipient
            if ($user_id) {
                $email_stmt = $conn->prepare("SELECT email FROM users WHERE id = ?");
                $email_stmt->bind_param("i", $user_id);
                $email_stmt->execute();
                $user_email = $email_stmt->get_result()->fetch_assoc()['email'] ?? null;
                $to = $user_email;
            } else {
                $to = $guest_email;
            }

            // Online payment: redirect to Stripe
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

            // Wallet or cash: clear cart, send email, redirect
            $_SESSION['cart'] = [];

            $emailData = buildOrderEmail($order_id, $total, $payment_method, $pickup_time, $instructions);
            if ($to) {
                ignore_user_abort(true);
                sendEmail($to, $emailData['subject'], $emailData['body']);
            }

            if (!$user_id) {
                $_SESSION['guest_order'] = $order_id;
                $_SESSION['guest_email'] = $guest_email;
                $redirect = "guest-thanks.php";
                if ($kiosk_mode) $redirect .= '?kiosk=1';
            } else {
                $redirect = "order-confirmation.php?order_id=$order_id";
                if ($kiosk_mode) $redirect .= '&kiosk=1';
            }

            header("Location: $redirect");
            ob_end_flush();
            flush();
            exit;

        } catch (Exception $e) {
            $conn->rollback();
            $error = "Failed to place order: " . $e->getMessage();
            error_log("Checkout error: " . $e->getMessage());
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
        .account-info { background: #f8f9fa; padding: 1rem; border-radius: 8px; margin-bottom: 1.5rem; }
        .wallet-balance { font-size: 1.2rem; font-weight: bold; color: #28a745; }
        .payment-options { margin-top: 1rem; }
        .payment-option { margin-bottom: 0.5rem; }
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
                    <tr><th>Item</th><th>Options</th><th>Qty</th><th>Price</th><th>Subtotal</th></tr>
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
                            <?php else: ?>—<?php endif; ?>
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
            <input type="hidden" name="temp_user_id" id="temp_user_id" value="">

            <?php if ($show_account_panel): ?>
                <!-- Kiosk mode: prompt for email to connect to wallet/card -->
                <div class="account-info" id="account-info">
                    <div class="form-group">
                        <label for="account_email">Your Email (to use wallet or card)</label>
                        <input type="email" id="account_email" name="account_email" placeholder="student@tamcc.edu.gd">
                        <small>If you have an account, you can pay with wallet or card. Otherwise, use cash below.</small>
                    </div>
                    <div id="account-details" style="display: none;">
                        <p><strong>Welcome, <span id="account_name"></span>!</strong></p>
                        <p>Wallet Balance: <span id="account_balance" class="wallet-balance">$0.00</span></p>
                    </div>
                </div>
            <?php endif; ?>

            <?php if (!isset($_SESSION['user_id']) && !$show_account_panel): ?>
                <!-- Guest checkout (non-kiosk) -->
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
                <select name="payment_method" id="payment_method" required>
                    <option value="cash">Cash on Pickup</option>
                    <?php if ($user_id): ?>
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

    <?php if ($show_account_panel): ?>
    <script>
        const emailInput = document.getElementById('account_email');
        const accountDetails = document.getElementById('account-details');
        const accountNameSpan = document.getElementById('account_name');
        const accountBalanceSpan = document.getElementById('account_balance');
        const paymentMethodSelect = document.getElementById('payment_method');
        const tempUserIdInput = document.getElementById('temp_user_id');
        let timeout;

        emailInput.addEventListener('input', function() {
            clearTimeout(timeout);
            timeout = setTimeout(() => {
                const email = this.value.trim();
                if (email && email.includes('@')) {
                    fetch('<?= kiosk_url('/kiosk/get-user.php') ?>?email=' + encodeURIComponent(email))
                        .then(r => r.json())
                        .then(data => {
                            if (data.success) {
                                accountDetails.style.display = 'block';
                                accountNameSpan.textContent = data.name;
                                accountBalanceSpan.textContent = '$' + data.balance.toFixed(2);
                                tempUserIdInput.value = data.id;
                                // Update payment options
                                paymentMethodSelect.innerHTML = '<option value="cash">Cash on Pickup</option>';
                                if (data.balance >= <?= $total ?>) {
                                    paymentMethodSelect.innerHTML += '<option value="wallet">Wallet Balance ($' + data.balance.toFixed(2) + ')</option>';
                                }
                                paymentMethodSelect.innerHTML += '<option value="online">Online Payment (Card)</option>';
                            } else {
                                accountDetails.style.display = 'none';
                                tempUserIdInput.value = '';
                                paymentMethodSelect.innerHTML = '<option value="cash">Cash on Pickup</option>';
                                alert('Account not found. Please register or continue with cash.');
                            }
                        })
                        .catch(() => {
                            accountDetails.style.display = 'none';
                            tempUserIdInput.value = '';
                            paymentMethodSelect.innerHTML = '<option value="cash">Cash on Pickup</option>';
                        });
                } else {
                    accountDetails.style.display = 'none';
                    tempUserIdInput.value = '';
                    paymentMethodSelect.innerHTML = '<option value="cash">Cash on Pickup</option>';
                }
            }, 500);
        });
    </script>
    <?php endif; ?>

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