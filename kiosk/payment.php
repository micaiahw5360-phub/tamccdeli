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
$success = false;

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateToken($_POST['csrf_token'] ?? '')) {
        $error = 'Security validation failed.';
    } else {
        $payment_method = $_POST['payment_method'] ?? '';
        $email = trim($_POST['customer_email'] ?? '');

        if ($payment_method === 'wallet') {
            // Wallet payment: require valid email with sufficient balance
            if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $error = 'Please enter a valid email address.';
            } else {
                $stmt = $conn->prepare("SELECT id, username, balance FROM users WHERE email = ? AND is_active = 1");
                $stmt->bind_param("s", $email);
                $stmt->execute();
                $user = $stmt->get_result()->fetch_assoc();

                if (!$user) {
                    $error = 'No account found with that email. Please register or use a different email.';
                } elseif ($user['balance'] < $total) {
                    $error = "Insufficient wallet balance. Your balance: $" . number_format($user['balance'], 2) . ", Total: $" . number_format($total, 2);
                } else {
                    // Deduct balance
                    $new_balance = $user['balance'] - $total;
                    $conn->begin_transaction();
                    try {
                        $stmt = $conn->prepare("UPDATE users SET balance = ? WHERE id = ?");
                        $stmt->bind_param("di", $new_balance, $user['id']);
                        $stmt->execute();

                        // Insert order with customer name and email
                        $customer_name = $user['username'];
                        $customer_email = $email;
                        $stmt = $conn->prepare("INSERT INTO orders (user_id, customer_name, customer_email, total, payment_method, payment_status, source) VALUES (?, ?, ?, ?, 'wallet', 'paid', 'kiosk')");
                        $stmt->bind_param("issd", $user['id'], $customer_name, $customer_email, $total);
                        $stmt->execute();
                        $order_id = $conn->insert_id;

                        // Insert order items
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
                            'customer' => $customer_name,
                            'customer_email' => $customer_email,
                            'payment_method' => 'wallet'
                        ];
                        header('Location: ' . kiosk_url('/kiosk/confirmation.php'));
                        exit;
                    } catch (Exception $e) {
                        $conn->rollback();
                        $error = 'Transaction failed. Please try again.';
                    }
                }
            }
        } elseif ($payment_method === 'cash') {
            // Cash payment: no deduction, just record order
            // Extract name from email local part, or use a default
            if (!empty($email) && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $local_part = explode('@', $email)[0];
                $customer_name = preg_replace('/[^a-zA-Z0-9\s]/', '', $local_part); // sanitize
                $customer_name = ucwords(trim($customer_name));
                if (empty($customer_name)) $customer_name = 'Guest';
                $customer_email = $email;
            } else {
                // If no email, ask for a name (you could add an input field, but for simplicity we use a generic name)
                $customer_name = trim($_POST['customer_name'] ?? '');
                if (empty($customer_name)) $customer_name = 'Guest';
                $customer_email = null;
            }

            $conn->begin_transaction();
            try {
                $stmt = $conn->prepare("INSERT INTO orders (customer_name, customer_email, total, payment_method, payment_status, source) VALUES (?, ?, ?, 'cash', 'pending', 'kiosk')");
                $stmt->bind_param("ssd", $customer_name, $customer_email, $total);
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
                    'customer' => $customer_name,
                    'customer_email' => $customer_email,
                    'payment_method' => 'cash'
                ];
                header('Location: ' . kiosk_url('/kiosk/confirmation.php'));
                exit;
            } catch (Exception $e) {
                $conn->rollback();
                $error = 'Failed to create cash order. Please try again.';
            }
        } else {
            $error = 'Please select a payment method.';
        }
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
            max-width: 550px;
            width: 100%;
            border-radius: 2rem;
            padding: 2rem;
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
            text-align: center;
        }
        .payment-options {
            display: flex;
            gap: 1rem;
            margin: 1.5rem 0;
            justify-content: center;
        }
        .payment-option {
            flex: 1;
            background: #f1f5f9;
            border: 2px solid #cbd5e1;
            border-radius: 1.5rem;
            padding: 1rem;
            text-align: center;
            cursor: pointer;
            transition: all 0.2s;
        }
        .payment-option.selected {
            background: #1e3c72;
            border-color: #1e3c72;
            color: white;
        }
        .payment-option.selected .method-icon,
        .payment-option.selected .method-name {
            color: white;
        }
        .method-icon { font-size: 2rem; display: block; margin-bottom: 0.5rem; color: #1e3c72; }
        .method-name { font-weight: bold; color: #1e3c72; }
        .form-group { margin-bottom: 1.5rem; }
        label { font-weight: 600; display: block; margin-bottom: 0.5rem; color: #1e3c72; }
        input {
            width: 100%;
            padding: 0.8rem;
            border-radius: 2rem;
            border: 1px solid #cbd5e1;
            font-size: 1rem;
        }
        input:focus { outline: none; border-color: #1e3c72; box-shadow: 0 0 0 3px rgba(30,60,114,0.2); }
        .wallet-info {
            background: #f0f7ff;
            padding: 0.8rem;
            border-radius: 1rem;
            margin-bottom: 1rem;
            font-size: 0.9rem;
            color: #1e3c72;
            text-align: center;
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
            cursor: pointer;
            transition: background 0.2s;
        }
        button:hover { background: #2b4c7c; }
        .error { color: #dc2626; margin: 0.5rem 0; background: #fee2e2; padding: 0.5rem; border-radius: 2rem; text-align: center; }
        .back-link {
            display: inline-block;
            margin-top: 1rem;
            text-align: center;
            width: 100%;
            color: #1e3c72;
            text-decoration: none;
            font-weight: bold;
        }
        .hidden { display: none; }
        .note { font-size: 0.8rem; color: #4a5568; margin-top: 0.5rem; text-align: center; }
    </style>
</head>
<body>
<div class="payment-card">
    <h1>💳 Payment</h1>
    <div class="total-amount">💰 Total: $<?= number_format($total, 2) ?></div>

    <?php if ($error): ?>
        <div class="error">❌ <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST" id="paymentForm">
        <input type="hidden" name="csrf_token" value="<?= generateToken() ?>">
        
        <div class="payment-options">
            <div class="payment-option" data-method="wallet">
                <span class="method-icon">💳</span>
                <span class="method-name">Wallet</span>
            </div>
            <div class="payment-option" data-method="cash">
                <span class="method-icon">💵</span>
                <span class="method-name">Cash</span>
            </div>
        </div>
        <input type="hidden" name="payment_method" id="payment_method" value="">

        <div id="walletFields" class="hidden">
            <div class="form-group">
                <label>📧 Registered Email</label>
                <input type="email" name="customer_email" id="wallet_email" placeholder="your@email.com" autocomplete="off">
                <div class="note">We'll check your wallet balance.</div>
            </div>
            <div id="walletInfo" class="wallet-info hidden">
                <span id="walletUserName"></span> – Balance: <strong id="walletBalance"></strong>
            </div>
        </div>

        <div id="cashFields" class="hidden">
            <div class="form-group">
                <label>📧 Email (optional, for receipt)</label>
                <input type="email" name="customer_email" id="cash_email" placeholder="your@email.com" autocomplete="off">
                <div class="note">Your name will be taken from the email's local part. Leave blank to use "Guest".</div>
            </div>
            <!-- Optional: add a name field if no email provided -->
            <div class="form-group">
                <label>👤 Name (if no email)</label>
                <input type="text" name="customer_name" id="cash_name" placeholder="Your name" autocomplete="off">
            </div>
        </div>

        <button type="submit" id="payBtn" disabled>Confirm & Pay</button>
    </form>
    <a href="<?= kiosk_url('/kiosk/cart.php') ?>" class="back-link">← Back to Cart</a>
</div>

<script>
    const methodOptions = document.querySelectorAll('.payment-option');
    const paymentMethodInput = document.getElementById('payment_method');
    const walletFields = document.getElementById('walletFields');
    const cashFields = document.getElementById('cashFields');
    const payBtn = document.getElementById('payBtn');
    const walletEmail = document.getElementById('wallet_email');
    const walletInfoDiv = document.getElementById('walletInfo');
    const walletUserName = document.getElementById('walletUserName');
    const walletBalanceSpan = document.getElementById('walletBalance');
    const cashEmail = document.getElementById('cash_email');
    const cashName = document.getElementById('cash_name');

    let selectedMethod = '';

    function updateWalletInfo() {
        const email = walletEmail.value.trim();
        if (selectedMethod === 'wallet' && email && email.includes('@')) {
            fetch('<?= kiosk_url('/kiosk/get-user.php') ?>?email=' + encodeURIComponent(email))
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        walletUserName.textContent = data.name;
                        walletBalanceSpan.textContent = '$' + data.balance.toFixed(2);
                        walletInfoDiv.classList.remove('hidden');
                        if (data.balance >= <?= $total ?>) {
                            payBtn.disabled = false;
                        } else {
                            payBtn.disabled = true;
                            walletInfoDiv.innerHTML += '<div style="color:#dc2626; margin-top:0.5rem;">❌ Insufficient balance</div>';
                        }
                    } else {
                        walletInfoDiv.classList.add('hidden');
                        payBtn.disabled = true;
                    }
                })
                .catch(() => {
                    walletInfoDiv.classList.add('hidden');
                    payBtn.disabled = true;
                });
        } else {
            walletInfoDiv.classList.add('hidden');
            payBtn.disabled = true;
        }
    }

    function enablePayButton() {
        if (selectedMethod === 'wallet') {
            // Already handled by updateWalletInfo
        } else if (selectedMethod === 'cash') {
            // Cash always allowed (no balance check)
            payBtn.disabled = false;
        } else {
            payBtn.disabled = true;
        }
    }

    methodOptions.forEach(opt => {
        opt.addEventListener('click', () => {
            methodOptions.forEach(o => o.classList.remove('selected'));
            opt.classList.add('selected');
            selectedMethod = opt.dataset.method;
            paymentMethodInput.value = selectedMethod;

            // Show/hide fields
            if (selectedMethod === 'wallet') {
                walletFields.classList.remove('hidden');
                cashFields.classList.add('hidden');
                updateWalletInfo();
                walletEmail.addEventListener('input', updateWalletInfo);
            } else if (selectedMethod === 'cash') {
                cashFields.classList.remove('hidden');
                walletFields.classList.add('hidden');
                payBtn.disabled = false;
                // Remove wallet email listener
                walletEmail.removeEventListener('input', updateWalletInfo);
            }
        });
    });

    // Initial: no method selected
    payBtn.disabled = true;

    // For cash, if user wants to fill name or email, no extra validation needed
</script>
</body>
</html>