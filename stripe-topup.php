<?php
require __DIR__ . '/includes/session.php';;
require __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/../includes/kiosk.php';

if (!isset($_SESSION['stripe_intent_id']) || !isset($_SESSION['stripe_amount']) || $_SESSION['stripe_type'] !== 'topup') {
    header('Location: index.php');
    exit;
}

$stripe_publishable_key = getenv('STRIPE_PUBLISHABLE_KEY');
$client_secret = $_SESSION['stripe_client_secret'];
$amount = $_SESSION['stripe_amount'];

include 'includes/header.php';
?>

<div class="checkout-container">
    <h1>Add Funds to Wallet</h1>
    <p>Amount: $<?= number_format($amount, 2) ?></p>

    <div class="card">
        <form id="payment-form">
            <div id="card-element" class="form-control" style="padding: 0.75rem;"></div>
            <div id="card-errors" class="error-message" style="margin-top:0.5rem;"></div>
            <button type="submit" class="btn btn-primary" id="submit-button">Pay Now</button>
        </form>
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
            return_url: '<?= kiosk_absolute_url('payment-confirmation.php') ?>'
        });

        if (error) {
            document.getElementById('card-errors').textContent = error.message;
            submitBtn.disabled = false;
            submitBtn.textContent = 'Pay Now';
        }
        // Stripe will redirect on success
    });
</script>

<?php include 'includes/footer.php'; ?>