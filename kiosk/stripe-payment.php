<?php
require __DIR__ . '/../includes/session.php';
require __DIR__ . '/../config/database.php';
require __DIR__ . '/../includes/kiosk.php';
require __DIR__ . '/../vendor/autoload.php';

if (!isset($_SESSION['stripe_intent_id']) || !isset($_SESSION['stripe_total'])) {
    header('Location: ' . kiosk_url('/kiosk/categories.php'));
    exit;
}

$stripe_publishable_key = getenv('STRIPE_PUBLISHABLE_KEY');
$client_secret = $_SESSION['stripe_client_secret'];
$total = $_SESSION['stripe_total'];
$cart_items = $_SESSION['stripe_cart_items'];
$customer_email = $_SESSION['stripe_customer_email'];
$customer_name = $_SESSION['stripe_customer_name'];

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
</head>
<body>
    <div class="kiosk">
        <div class="screen">
            <div class="time"></div>
            <h1>Complete Payment</h1>
            <p>Total: <strong>$<?= number_format($total, 2) ?></strong></p>
            <div class="card">
                <form id="payment-form">
                    <div id="card-element" class="form-control" style="padding: 0.75rem; background: white; border: 1px solid var(--neutral-300); border-radius: var(--radius-lg);"></div>
                    <div id="card-errors" class="error-message" style="margin-top:0.5rem;"></div>
                    <button type="submit" class="btn btn-primary" id="submit-button">Pay Now</button>
                </form>
            </div>
        </div>
    </div>

    <script src="https://js.stripe.com/v3/"></script>
    <script>
        const stripe = Stripe('<?= $stripe_publishable_key ?>');
        const elements = stripe.elements();
        const card = elements.create('card', {
            style: {
                base: {
                    fontSize: '16px',
                    fontFamily: 'inherit',
                    color: '#333',
                }
            }
        });
        card.mount('#card-element');

        card.on('change', ({error}) => {
            const displayError = document.getElementById('card-errors');
            if (error) displayError.textContent = error.message;
            else displayError.textContent = '';
        });

        const form = document.getElementById('payment-form');
        form.addEventListener('submit', async (event) => {
            event.preventDefault();
            const submitBtn = document.getElementById('submit-button');
            submitBtn.disabled = true;
            submitBtn.textContent = 'Processing...';

            const {error} = await stripe.confirmCardPayment('<?= $client_secret ?>', {
                payment_method: { card: card },
                return_url: '<?= kiosk_absolute_url('/kiosk/payment-confirmation.php') ?>'
            });

            if (error) {
                document.getElementById('card-errors').textContent = error.message;
                submitBtn.disabled = false;
                submitBtn.textContent = 'Pay Now';
            }
        });
    </script>
</body>
</html>