<?php
require __DIR__ . '/../includes/session.php';
require __DIR__ . '/../config/database.php';
require __DIR__ . '/../includes/kiosk.php';
require __DIR__ . '/../includes/functions.php';
require __DIR__ . '/../includes/csrf.php';

$kiosk_mode = true;
$cart = $_SESSION['cart'] ?? [];
if (empty($cart)) {
    header('Location: ' . kiosk_url('/kiosk/menu.php'));
    exit;
}

// Calculate total and cart items (same as your original)
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
    while ($row = $result->fetch_assoc()) $items_data[$row['id']] = $row;
}
foreach ($cart as $key => $entry) {
    $item_id = $entry['item_id'];
    if (!isset($items_data[$item_id])) continue;
    $item = $items_data[$item_id];
    $quantity = $entry['quantity'];
    $options = $entry['options'] ?? [];
    $option_details = getOptionDetails($conn, $options);
    $modifier_total = 0;
    foreach ($option_details as $opt) $modifier_total += $opt['price_modifier'];
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

$error = '';
$customer_name = '';
$customer_balance = 0;
$customer_id = 0;

// Handle wallet payment (email based)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateToken($_POST['csrf_token'])) die('Invalid CSRF token');
    $email = trim($_POST['customer_email']);
    $stmt = $conn->prepare("SELECT id, username, balance FROM users WHERE email = ? AND is_active = 1");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();

    if (!$user) {
        $error = 'No account found with that email. Please register or use a different email.';
    } else {
        $customer_id = $user['id'];
        $customer_name = $user['username'];
        $customer_balance = $user['balance'];

        if ($customer_balance >= $total) {
            $new_balance = $customer_balance - $total;
            $conn->begin_transaction();
            try {
                $stmt = $conn->prepare("UPDATE users SET balance = ? WHERE id = ?");
                $stmt->bind_param("di", $new_balance, $customer_id);
                $stmt->execute();

                $stmt = $conn->prepare("INSERT INTO orders (user_id, total, payment_method, payment_status, source) VALUES (?, ?, 'wallet', 'paid', 'kiosk')");
                $stmt->bind_param("id", $customer_id, $total);
                $stmt->execute();
                $order_id = $conn->insert_id;

                $stmt = $conn->prepare("INSERT INTO order_items (order_id, menu_item_id, quantity, price, options) VALUES (?, ?, ?, ?, ?)");
                foreach ($cart_items as $ci) {
                    $options_json = json_encode($ci['options']);
                    $stmt->bind_param("iiids", $order_id, $ci['item']['id'], $ci['quantity'], $ci['unit_price'], $options_json);
                    $stmt->execute();
                }

                $conn->commit();
                unset($_SESSION['cart']);
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
            } catch (Exception $e) {
                $conn->rollback();
                $error = 'Transaction failed. Please try again.';
            }
        } else {
            $error = "Insufficient wallet balance. Your balance: $$customer_balance, Total: $$total";
        }
    }
}

$page_title = "Wallet Payment | TAMCC Deli Kiosk";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
    <title><?= $page_title ?></title>
    <style>
        * { margin:0; padding:0; box-sizing:border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background: linear-gradient(135deg, #1e3c72 0%, #2b4c7c 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem;
        }
        .payment-card {
            background: white;
            max-width: 500px;
            width: 100%;
            border-radius: 2rem;
            padding: 2rem;
            text-align: center;
            box-shadow: 0 20px 40px rgba(0,0,0,0.2);
            animation: fadeInUp 0.5s;
        }
        @keyframes fadeInUp {
            from { opacity:0; transform:translateY(30px); }
            to { opacity:1; transform:translateY(0); }
        }
        h1 { color: #1e3c72; margin-bottom: 1rem; display: flex; align-items: center; justify-content: center; gap: 0.5rem; }
        .total-amount {
            font-size: 2rem;
            font-weight: bold;
            color: #1e3c72;
            background: #e0e7ff;
            padding: 1rem;
            border-radius: 1.5rem;
            margin: 1rem 0;
        }
        input {
            width: 100%;
            padding: 0.8rem;
            margin: 0.5rem 0;
            border-radius: 2rem;
            border: 1px solid #cbd5e1;
            font-size: 1rem;
        }
        button {
            background: #1e3c72;
            color: white;
            border: none;
            width: 100%;
            padding: 0.8rem;
            border-radius: 2rem;
            font-size: 1.2rem;
            font-weight: bold;
            margin-top: 1rem;
            cursor: pointer;
            transition: background 0.2s;
        }
        button:hover { background: #2b4c7c; }
        .error { color: #dc2626; margin: 0.5rem 0; background: #fee2e2; padding: 0.5rem; border-radius: 2rem; }
        .back-link {
            display: inline-block;
            margin-top: 1rem;
            color: #1e3c72;
            text-decoration: none;
        }
        .wallet-disclaimer {
            background: #f0f7ff;
            padding: 0.8rem;
            border-radius: 1rem;
            margin-bottom: 1.5rem;
            font-size: 0.9rem;
            color: #1e3c72;
        }
    </style>
</head>
<body>
<div class="payment-card">
    <h1>💳 Wallet Payment</h1>
    <div class="wallet-disclaimer">
        ⚡ Payment will be deducted from your TAMCC Deli Wallet.<br>
        Enter your registered email address to confirm.
    </div>
    <div class="total-amount">💰 Total: $<?= number_format($total, 2) ?></div>

    <?php if ($error): ?>
        <div class="error">❌ <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST">
        <input type="hidden" name="csrf_token" value="<?= generateToken() ?>">
        <input type="email" name="customer_email" placeholder="Your Email" required autocomplete="off">
        <button type="submit">✅ Confirm & Pay</button>
    </form>
    <a href="<?= kiosk_url('/kiosk/cart.php') ?>" class="back-link">← Back to Cart</a>
</div>
</body>
</html>