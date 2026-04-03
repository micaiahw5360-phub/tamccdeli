<?php
require __DIR__ . '/../includes/session.php';
require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../includes/kiosk.php';

if (!isset($_SESSION['stripe_intent_id']) || !isset($_SESSION['pending_order'])) {
    header('Location: ' . kiosk_url('/index.php'));
    exit;
}

$stripe_publishable_key = getenv('STRIPE_PUBLISHABLE_KEY');
$client_secret = $_SESSION['stripe_client_secret'];
$order_id = $_SESSION['pending_order'];
$total = $_SESSION['stripe_total'] ?? 0;

$page_title = "Card Payment | TAMCC Deli Kiosk";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
    <title><?= $page_title ?></title>
    <link rel="stylesheet" href="/assets/css/global.css">
    <link rel="stylesheet" href="/assets/css/kiosk.css">
    <script src="https://js.stripe.com/v3/"></script>
</head>
<body>
    <?php include __DIR__ . '/../includes/header.php'; ?>

    <div class="checkout-container">
        <h1>Complete Payment</h1>
        <p>Order #<?= $order_id ?> – Total: $<?= number_format($total, 2) ?></p>
        <div class="card">
            <form id="payment-form">
                <div id="card-element" class="form-control" style="padding: 0.75rem;"></div>
                <div id="card-errors" class="error-message" style="margin-top:0.5rem;"></div>
                <button type="submit" class="btn btn-primary" id="submit-button">Pay Now</button>
            </form>
        </div>
    </div>

    <a href="<?= kiosk_url('/cart.php') ?>" class="floating-cart">
        🛒 Cart <span class="cart-count" id="cart-count-kiosk">0</span>
    </a>

    <?php include __DIR__ . '/../includes/footer.php'; ?>

    <script>
        const stripe = Stripe('<?= $stripe_publishable_key ?>');
        const elements = stripe.elements();
        const card = elements.create('card', {
            style: { base: { fontSize: '16px', fontFamily: 'inherit', color: '#333' } }
        });
        card.mount('#card-element');

        card.on('change', ({error}) => {
            const displayError = document.getElementById('card-errors');
            displayError.textContent = error ? error.message : '';
        });

        const form = document.getElementById('payment-form');
        form.addEventListener('submit', async (event) => {
            event.preventDefault();
            const submitBtn = document.getElementById('submit-button');
            submitBtn.disabled = true;
            submitBtn.textContent = 'Processing...';

            const {error} = await stripe.confirmCardPayment('<?= $client_secret ?>', {
                payment_method: { card: card },
                return_url: '<?= kiosk_absolute_url('/payment-confirmation.php') ?>'
            });
            if (error) {
                document.getElementById('card-errors').textContent = error.message;
                submitBtn.disabled = false;
                submitBtn.textContent = 'Pay Now';
            }
        });

        function updateCartDisplay() {
            fetch('<?= kiosk_url('/get-cart-count.php') ?>')
                .then(r => r.json())
                .then(data => {
                    document.querySelectorAll('.cart-count').forEach(el => el.textContent = data.count);
                });
        }
        updateCartDisplay();
    </script>
</body>
</html>