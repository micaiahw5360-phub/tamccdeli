<?php
require __DIR__ . '/includes/session.php';
require 'config/database.php';

if (!isset($_SESSION['guest_order'])) {
    header('Location: index.php');
    exit;
}

$order_id = $_SESSION['guest_order'];
$email = $_SESSION['guest_email'] ?? '';

$stmt = $conn->prepare("SELECT * FROM orders WHERE id = ?");
$stmt->bind_param("i", $order_id);
$stmt->execute();
$order = $stmt->get_result()->fetch_assoc();
if (!$order) {
    header('Location: index.php');
    exit;
}

$page_title = "Thank You";
include 'includes/header.php';
?>

<div class="checkout-container" style="text-align: center; max-width: 600px;">
    <div class="success-icon" style="font-size: 5rem; margin-bottom: 1rem;">✅</div>
    <h1>Thank You for Your Order!</h1>
    <p>Your order <strong>#<?= $order_id ?></strong> has been placed.</p>
    <p>We've sent a confirmation to <strong><?= htmlspecialchars($email) ?></strong>.</p>
    <p><strong>Payment Method:</strong> Cash on Pickup</p>
    <p><strong>Payment Status:</strong> <?= ucfirst($order['payment_status']) ?></p>

    <hr style="margin: 2rem 0;">

    <h2>Create an Account</h2>
    <p>Save your order history and speed up future checkouts.</p>
    <form method="post" action="auth/register.php">
        <input type="hidden" name="email" value="<?= htmlspecialchars($email) ?>">
        <button type="submit" class="btn btn-primary">Register Now</button>
    </form>

    <div style="margin-top: 1rem;">
        <a href="<?= kiosk_url('menu.php') ?>" class="btn btn-accent">Continue Browsing</a>
    </div>
</div>

<?php
unset($_SESSION['guest_order'], $_SESSION['guest_email']);
include 'includes/footer.php';
?>