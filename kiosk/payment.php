<?php
require __DIR__ . '/../includes/session.php';
require __DIR__ . '/../config/database.php';
require __DIR__ . '/../includes/kiosk.php';
require __DIR__ . '/../includes/functions.php';
require __DIR__ . '/../includes/csrf.php';

$kiosk_mode = true;
$cart = $_SESSION['cart'] ?? [];
if (empty($cart)) {
    // FIXED: redirect to menu.php instead of categories.php
    header('Location: ' . kiosk_url('/kiosk/menu.php'));
    exit;
}

// Calculate total and cart items
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

// Handle wallet payment
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
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem;
        }
        .kiosk {
            max-width: 600px;
            width: 100%;
            background: rgba(255,255,255,0.97);
            border-radius: 3rem;
            box-shadow: 0 30px 60px rgba(0,0,0,0.3);
            overflow: hidden;
            animation: fadeInUp 0.5s ease;
        }
        @keyframes fadeInUp {
            from { opacity:0; transform:translateY(30px); }
            to { opacity:1; transform:translateY(0); }
        }
        .screen { padding: 2rem; }
        h1 {
            font-size: 2.5rem;
            background: linear-gradient(135deg, #FF6B35, #FF4757);
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
            margin-bottom: 1rem;
        }
        .total-box {
            background: linear-gradient(135deg, #FF6B35, #FF4757);
            color: white;
            padding: 1rem;
            border-radius: 2rem;
            text-align: center;
            font-size: 1.5rem;
            font-weight: bold;
            margin-bottom: 2rem;
        }
        .form-group { margin-bottom: 1.5rem; }
        label { font-weight: 600; display: block; margin-bottom: 0.5rem; }
        input {
            width: 100%;
            padding: 1rem;
            font-size: 1.1rem;
            border: 2px solid #e2e8f0;
            border-radius: 2rem;
            transition: all 0.2s;
        }
        input:focus { border-color: #FF6B35; outline: none; box-shadow: 0 0 0 3px rgba(255,107,53,0.2); }
        .btn {
            background: linear-gradient(135deg, #00D25B, #00CEC9);
            color: white;
            border: none;
            padding: 1rem;
            width: 100%;
            font-size: 1.3rem;
            font-weight: bold;
            border-radius: 3rem;
            cursor: pointer;
            transition: transform 0.2s;
        }
        .btn:active { transform: scale(0.98); }
        .error-message {
            background: #fee2e2;
            color: #dc2626;
            padding: 0.75rem;
            border-radius: 2rem;
            margin-bottom: 1rem;
            text-align: center;
        }
        .back-link {
            display: inline-block;
            margin-top: 1rem;
            text-align: center;
            width: 100%;
            color: #FF6B35;
            text-decoration: none;
            font-weight: 600;
        }
        .wallet-info {
            background: #f1f5f9;
            border-radius: 1.5rem;
            padding: 1rem;
            margin-bottom: 1.5rem;
            text-align: center;
        }
    </style>
</head>
<body>
<div class="kiosk">
    <div class="screen">
        <h1>💳 Pay with Wallet</h1>
        <div class="total-box">Total: $<?= number_format($total, 2) ?></div>
        <?php if ($error): ?>
            <div class="error-message"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        <form method="post">
            <input type="hidden" name="csrf_token" value="<?= generateToken() ?>">
            <div class="form-group">
                <label>Your Email (registered account)</label>
                <input type="email" id="customer_email" name="customer_email" required>
            </div>
            <div id="walletInfo" class="wallet-info" style="display: none;">
                <p>👤 <strong id="userName"></strong></p>
                <p>💰 Balance: <strong id="userBalance"></strong></p>
            </div>
            <button type="submit" class="btn" id="payBtn" disabled>Pay with Wallet</button>
        </form>
        <a href="<?= kiosk_url('/cart.php') ?>" class="back-link">← Back to Cart</a>
    </div>
</div>
<script>
    const emailInput = document.getElementById('customer_email');
    const walletInfo = document.getElementById('walletInfo');
    const userNameSpan = document.getElementById('userName');
    const userBalanceSpan = document.getElementById('userBalance');
    const payBtn = document.getElementById('payBtn');
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
                            userNameSpan.textContent = data.name;
                            userBalanceSpan.textContent = '$' + data.balance.toFixed(2);
                            if (data.balance >= <?= $total ?>) {
                                payBtn.disabled = false;
                                payBtn.style.opacity = '1';
                            } else {
                                payBtn.disabled = true;
                                payBtn.style.opacity = '0.5';
                            }
                        } else {
                            walletInfo.style.display = 'none';
                            payBtn.disabled = true;
                            payBtn.style.opacity = '0.5';
                        }
                    })
                    .catch(() => {
                        walletInfo.style.display = 'none';
                        payBtn.disabled = true;
                    });
            } else {
                walletInfo.style.display = 'none';
                payBtn.disabled = true;
            }
        }, 500);
    });
</script>
</body>
</html>