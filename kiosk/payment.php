<?php
require __DIR__ . '/../includes/session.php';
require __DIR__ . '/../config/database.php';
require __DIR__ . '/../includes/kiosk.php';
require __DIR__ . '/../includes/functions.php';
require __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../vendor/autoload.php';

use Stripe\Stripe;
use Stripe\PaymentIntent;

$cart = $_SESSION['cart'] ?? [];
if (empty($cart)) {
    header('Location: ' . kiosk_url('/kiosk/categories.php'));
    exit;
}

// Calculate total
$total = 0;
$cart_items = [];
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

// Handle wallet payment (POST)
$error = '';
$customer_name = '';
$customer_balance = 0;
$customer_id = 0;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['payment_type'])) {
    if (!validateToken($_POST['csrf_token'])) die('Invalid CSRF token');

    $email = trim($_POST['customer_email']);

    // Fetch user by email
    $stmt = $conn->prepare("SELECT id, username, balance FROM users WHERE email = ? AND is_active = 1");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();

    if (!$user) {
        $error = 'No account found with that email. Please register or use card payment.';
    } else {
        $customer_id = $user['id'];
        $customer_name = $user['username'];
        $customer_balance = $user['balance'];

        if ($_POST['payment_type'] === 'wallet') {
            if ($customer_balance >= $total) {
                // Deduct from wallet
                $new_balance = $customer_balance - $total;
                $stmt = $conn->prepare("UPDATE users SET balance = ? WHERE id = ?");
                $stmt->bind_param("di", $new_balance, $customer_id);
                $stmt->execute();

                // Create order
                $stmt = $conn->prepare("INSERT INTO orders (user_id, total, payment_method, payment_status) VALUES (?, ?, 'wallet', 'paid')");
                $stmt->bind_param("id", $customer_id, $total);
                $stmt->execute();
                $order_id = $conn->insert_id;

                // Insert order items
                $stmt = $conn->prepare("INSERT INTO order_items (order_id, menu_item_id, quantity, price, options) VALUES (?, ?, ?, ?, ?)");
                foreach ($cart_items as $ci) {
                    $options_json = json_encode($ci['options']);
                    $stmt->bind_param("iiids", $order_id, $ci['item']['id'], $ci['quantity'], $ci['unit_price'], $options_json);
                    $stmt->execute();
                }

                // Clear cart
                unset($_SESSION['cart']);

                // Store order in session for receipt
                $_SESSION['last_order'] = [
                    'id' => $order_id,
                    'items' => $cart_items,
                    'total' => $total,
                    'timestamp' => date('Y-m-d H:i:s'),
                    'customer' => $customer_name,
                    'payment_method' => 'wallet'
                ];

                header('Location: ' . kiosk_url('/kiosk/confirmation.php'));
                exit;
            } else {
                $error = 'Insufficient wallet balance. Please use card payment.';
            }
        }
    }
}

// For card payment, we create a Stripe PaymentIntent and redirect to a Stripe checkout page
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['payment_type']) && $_POST['payment_type'] === 'card') {
    if (!validateToken($_POST['csrf_token'])) die('Invalid CSRF token');
    $email = trim($_POST['customer_email']);
    $customer_name = trim($_POST['customer_name'] ?? '');

    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } elseif (empty($customer_name)) {
        $error = 'Please enter your name.';
    } else {
        Stripe::setApiKey(getenv('STRIPE_SECRET_KEY'));
        $intent = PaymentIntent::create([
            'amount'   => round($total * 100),
            'currency' => 'usd',
            'metadata' => [
                'customer_email' => $email,
                'customer_name'  => $customer_name,
                'cart_items'     => json_encode($cart_items)
            ]
        ]);

        $_SESSION['stripe_intent_id'] = $intent->id;
        $_SESSION['stripe_client_secret'] = $intent->client_secret;
        $_SESSION['stripe_total'] = $total;
        $_SESSION['stripe_cart_items'] = $cart_items;
        $_SESSION['stripe_customer_email'] = $email;
        $_SESSION['stripe_customer_name'] = $customer_name;

        header('Location: ' . kiosk_url('/kiosk/stripe-payment.php'));
        exit;
    }
}

$page_title = "Payment | TAMCC Deli Kiosk";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
    <title><?= $page_title ?></title>
    <link rel="stylesheet" href="/assets/css/kiosk.css">
</head>
<body>
    <div class="kiosk">
        <div class="screen">
            <div class="time"></div>
            <h1>Complete Your Order</h1>
            <p style="font-size: var(--text-xl); margin-bottom: var(--space-6);">
                Total: <strong>$<?= number_format($total, 2) ?></strong>
            </p>

            <?php if ($error): ?>
                <div class="error-message"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <form id="payment-form" method="post">
                <input type="hidden" name="csrf_token" value="<?= generateToken() ?>">
                <div class="form-group">
                    <label class="form-label">Your Email (to identify your account)</label>
                    <input type="email" id="customer-email" name="customer_email" class="form-input" required>
                </div>
                <div id="wallet-info" style="display: none;">
                    <div class="form-group">
                        <label class="form-label">Your Name</label>
                        <input type="text" id="customer-name" name="customer_name" class="form-input" readonly>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Wallet Balance</label>
                        <input type="text" id="customer-balance" class="form-input" readonly>
                    </div>
                    <button type="submit" name="payment_type" value="wallet" class="btn btn-primary" id="pay-wallet-btn" style="display: none;">Pay with Wallet</button>
                </div>
                <div id="card-info" style="display: none;">
                    <div class="form-group">
                        <label class="form-label">Your Name (for receipt)</label>
                        <input type="text" id="card-customer-name" name="customer_name" class="form-input" required>
                    </div>
                    <button type="submit" name="payment_type" value="card" class="btn btn-accent">Pay with Credit/Debit Card</button>
                </div>
            </form>

            <div style="margin-top: var(--space-6);">
                <a href="<?= kiosk_url('/kiosk/cart.php') ?>" class="btn btn-outline">Back to Cart</a>
            </div>
        </div>
    </div>

    <script src="/assets/js/kiosk.js"></script>
    <script>
        const emailInput = document.getElementById('customer-email');
        const walletInfo = document.getElementById('wallet-info');
        const cardInfo = document.getElementById('card-info');
        const customerNameInput = document.getElementById('customer-name');
        const balanceInput = document.getElementById('customer-balance');
        const payWalletBtn = document.getElementById('pay-wallet-btn');
        const cardNameInput = document.getElementById('card-customer-name');
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
                                walletInfo.style.display = 'block';
                                cardInfo.style.display = 'none';
                                customerNameInput.value = data.name;
                                balanceInput.value = '$' + data.balance.toFixed(2);
                                if (data.balance >= <?= $total ?>) {
                                    payWalletBtn.style.display = 'inline-block';
                                    document.querySelector('button[value="card"]').style.display = 'none';
                                } else {
                                    payWalletBtn.style.display = 'none';
                                    document.querySelector('button[value="card"]').style.display = 'inline-block';
                                }
                            } else {
                                walletInfo.style.display = 'none';
                                cardInfo.style.display = 'block';
                                if (data.name) cardNameInput.value = data.name;
                                document.querySelector('button[value="card"]').style.display = 'inline-block';
                            }
                        })
                        .catch(() => {
                            walletInfo.style.display = 'none';
                            cardInfo.style.display = 'block';
                        });
                } else {
                    walletInfo.style.display = 'none';
                    cardInfo.style.display = 'none';
                }
            }, 500);
        });
    </script>
</body>
</html>