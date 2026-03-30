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

if (empty($_SESSION['cart'])) {
    header("Location: menu.php");
    exit;
}

// Fetch cart items (similar logic as cart.php)
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

// Get user balance and points
$user_id = $_SESSION['user_id'] ?? null;
$user_balance = 0;
$user_points = 0;
if ($user_id) {
    $stmt = $conn->prepare("SELECT balance, points FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $user_data = $stmt->get_result()->fetch_assoc();
    $user_balance = $user_data['balance'] ?? 0;
    $user_points = $user_data['points'] ?? 0;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateToken($_POST['csrf_token'])) die('Invalid CSRF');

    $pickup_time = $_POST['pickup_time'] ? date('Y-m-d H:i:s', strtotime($_POST['pickup_time'])) : null;
    $instructions = trim($_POST['instructions']);
    $payment_method = $_POST['payment_method'];

    $user_id = $_SESSION['user_id'] ?? null;
    $guest_email = null;
    if (!$user_id) {
        if ($payment_method !== 'cash') $error = "Guests can only pay with cash on pickup.";
        $guest_email = trim($_POST['guest_email']);
        if (!filter_var($guest_email, FILTER_VALIDATE_EMAIL)) $error = "Valid email required for guest checkout.";
    } else {
        if ($payment_method === 'wallet' && $user_balance < $total) $error = "Insufficient wallet balance.";
    }

    // Handle points
    $points_used = 0;
    $discount = 0;
    $net_total = $total;
    if ($user_id && isset($_POST['use_points']) && $_POST['use_points'] == '1') {
        $points_to_use = intval($_POST['points_to_use'] ?? 0);
        $max_points = floor($total * 100);
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
            // Insert order
            $stmt = $conn->prepare("INSERT INTO orders (user_id, guest_email, total, pickup_time, special_instructions, payment_status, payment_method, points_used) VALUES (?, ?, ?, ?, ?, 'unpaid', ?, ?)");
            $stmt->bind_param("isdsssi", $user_id, $guest_email, $net_total, $pickup_time, $instructions, $payment_method, $points_used);
            $stmt->execute();
            $order_id = $conn->insert_id;

            // Insert order items
            $stmt2 = $conn->prepare("INSERT INTO order_items (order_id, menu_item_id, quantity, price, options) VALUES (?, ?, ?, ?, ?)");
            foreach ($cart_items as $cart_item) {
                $options_json = json_encode($cart_item['options']);
                $stmt2->bind_param("iiids", $order_id, $cart_item['item']['id'], $cart_item['quantity'], $cart_item['unit_price'], $options_json);
                $stmt2->execute();
            }

            // Deduct points if used
            if ($points_used > 0) {
                $update_points = $conn->prepare("UPDATE users SET points = points - ? WHERE id = ?");
                $update_points->bind_param("ii", $points_used, $user_id);
                $update_points->execute();
                // Record points redemption
                $trans = $conn->prepare("INSERT INTO transactions (user_id, amount, type, description, order_id) VALUES (?, ?, 'points_redemption', ?, ?)");
                $points_amount = -$points_used;
                $desc = "Redeemed $points_used points for discount on order #$order_id";
                $trans->bind_param("idsi", $user_id, $points_amount, $desc, $order_id);
                $trans->execute();
            }

            // Handle wallet payment
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

            // Award points
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

            // Build email
            $email = buildOrderEmail($order_id, $total, $net_total, $discount, $points_used, $payment_method, $pickup_time, $instructions);
            $subject = $email['subject'];
            $body = $email['body'];

            if ($user_id) {
                $email_stmt = $conn->prepare("SELECT email FROM users WHERE id = ?");
                $email_stmt->bind_param("i", $user_id);
                $email_stmt->execute();
                $user_email = $email_stmt->get_result()->fetch_assoc()['email'] ?? null;
                $to = $user_email;
            } else {
                $to = $guest_email;
            }

            // Handle online payment (Stripe)
            if ($payment_method === 'online') {
                Stripe::setApiKey(getenv('STRIPE_SECRET_KEY'));
                $intent = PaymentIntent::create([
                    'amount'   => round($net_total * 100),
                    'currency' => 'usd',
                    'metadata' => ['order_id' => $order_id],
                ]);
                $_SESSION['stripe_intent_id'] = $intent->id;
                $_SESSION['stripe_client_secret'] = $intent->client_secret;
                $_SESSION['pending_order'] = $order_id;
                $_SESSION['stripe_total'] = $net_total;
                header("Location: " . kiosk_url('stripe-payment.php'));
                exit;
            }

            // Clear cart
            $_SESSION['cart'] = [];

            // Redirect
            if (!$user_id) {
                $_SESSION['guest_order'] = $order_id;
                $_SESSION['guest_email'] = $guest_email;
                $redirect = "guest-thanks.php";
            } else {
                $redirect = "order-confirmation.php?order_id=$order_id";
            }
            header("Location: " . kiosk_url($redirect));
            exit;

        } catch (Exception $e) {
            $conn->rollback();
            $error = "Failed to place order: " . $e->getMessage();
            error_log("Checkout error: " . $e->getMessage());
        }
    }
}

$page_title = "Checkout | TAMCC Deli";
include 'includes/header.php';
?>

<div class="container">
    <h1 class="text-3xl font-bold mb-8">Checkout</h1>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        <!-- Checkout Form -->
        <div class="lg:col-span-2 space-y-6">
            <form method="POST" id="checkout-form">
                <input type="hidden" name="csrf_token" value="<?= generateToken() ?>">

                <?php if (!isset($_SESSION['user_id'])): ?>
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">Contact Information</h3>
                        </div>
                        <div class="card-content">
                            <div class="form-group">
                                <label class="form-label">Your Email (for order confirmation)</label>
                                <input type="email" name="guest_email" class="form-input" required>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Pickup Details</h3>
                    </div>
                    <div class="card-content space-y-4">
                        <div class="form-group">
                            <label class="form-label">Pickup Time (optional)</label>
                            <input type="datetime-local" name="pickup_time" class="form-input">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Special Instructions</label>
                            <textarea name="instructions" rows="3" class="form-textarea"></textarea>
                        </div>
                    </div>
                </div>

                <?php if (isset($_SESSION['user_id']) && $user_points > 0): ?>
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">Loyalty Points</h3>
                        </div>
                        <div class="card-content">
                            <div class="flex items-center gap-2 mb-2">
                                <input type="checkbox" id="use_points" name="use_points" value="1">
                                <label for="use_points" class="form-label cursor-pointer">Use my points (<?= $user_points ?> points available)</label>
                            </div>
                            <div id="points_amount_group" style="display: none;">
                                <label class="form-label">Points to use (100 points = $1.00):</label>
                                <input type="number" id="points_to_use" name="points_to_use" min="0" max="<?= min($user_points, floor($total * 100)) ?>" step="1" value="0" class="form-input">
                                <small class="text-gray-500">Max discount: $<?= number_format(min($total, $user_points / 100), 2) ?></small>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Payment Method</h3>
                    </div>
                    <div class="card-content">
                        <div class="space-y-3">
                            <label class="flex items-center gap-3 p-4 border rounded-lg cursor-pointer hover:bg-gray-50">
                                <input type="radio" name="payment_method" value="cash" checked>
                                <span class="flex-1">Cash on Pickup</span>
                            </label>
                            <?php if (isset($_SESSION['user_id'])): ?>
                                <label class="flex items-center gap-3 p-4 border rounded-lg cursor-pointer hover:bg-gray-50">
                                    <input type="radio" name="payment_method" value="wallet">
                                    <span class="flex-1">Wallet Balance ($<?= number_format($user_balance, 2) ?>)</span>
                                </label>
                                <label class="flex items-center gap-3 p-4 border rounded-lg cursor-pointer hover:bg-gray-50">
                                    <input type="radio" name="payment_method" value="online">
                                    <span class="flex-1">Online Payment (Card)</span>
                                </label>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <button type="submit" class="btn btn-accent w-full btn-lg" id="place-order-btn">Place Order</button>
                <a href="<?= kiosk_url('cart.php') ?>" class="btn btn-outline w-full mt-3">Return to Cart</a>
            </form>
        </div>

        <!-- Order Summary -->
        <div>
            <div class="card sticky top-20">
                <div class="card-header">
                    <h3 class="card-title">Order Summary</h3>
                </div>
                <div class="card-content space-y-4">
                    <div class="max-h-48 overflow-y-auto space-y-3 pb-4 border-b">
                        <?php foreach ($cart_items as $item): ?>
                            <div class="flex justify-between text-sm">
                                <span class="text-gray-600"><?= $item['quantity'] ?>x <?= htmlspecialchars($item['item']['name']) ?></span>
                                <span class="font-medium">$<?= number_format($item['subtotal'], 2) ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="space-y-2">
                        <div class="flex justify-between">
                            <span class="text-gray-600">Subtotal</span>
                            <span>$<?= number_format($total, 2) ?></span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-600">Tax (10%)</span>
                            <span>$<?= number_format($total * 0.1, 2) ?></span>
                        </div>
                        <div class="border-t pt-2">
                            <div class="flex justify-between">
                                <span class="font-bold text-lg">Total</span>
                                <span class="font-bold text-primary text-xl">$<?= number_format($total * 1.1, 2) ?></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    // Show/hide points input
    const usePointsCheckbox = document.getElementById('use_points');
    const pointsGroup = document.getElementById('points_amount_group');
    if (usePointsCheckbox) {
        usePointsCheckbox.addEventListener('change', function() {
            pointsGroup.style.display = this.checked ? 'block' : 'none';
        });
    }
    // Form loading state
    const form = document.getElementById('checkout-form');
    form.addEventListener('submit', function() {
        const btn = document.getElementById('place-order-btn');
        btn.disabled = true;
        btn.textContent = 'Processing...';
    });
</script>

<?php include 'includes/footer.php'; ?>