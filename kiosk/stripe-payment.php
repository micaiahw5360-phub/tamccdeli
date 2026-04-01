<?php
require __DIR__ . '/../includes/session.php';
require __DIR__ . '/../config/database.php';
require __DIR__ . '/../includes/kiosk.php';
require __DIR__ . '/../vendor/autoload.php';

$kiosk_mode = true;
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
    <style>
        /* Same CSS variables and base styles as in payment.php */
        :root {
            --primary-600: #074af2;
            --primary-700: #0538c2;
            --neutral-300: #b0b5c2;
            --neutral-200: #d1d6e0;
            --neutral-100: #e9ebf0;
            --white: #ffffff;
            --danger: #ef4444;
            --font-sans: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            --text-xl: clamp(1.25rem, 4vw, 1.5rem);
            --text-2xl: clamp(1.5rem, 5vw, 1.875rem);
            --text-4xl: clamp(2.25rem, 7vw, 3rem);
            --text-lg: clamp(1.125rem, 3.5vw, 1.25rem);
            --space-4: 1rem;
            --space-6: 1.5rem;
            --space-8: 2rem;
            --space-2: 0.5rem;
            --radius-lg: 0.75rem;
            --radius-xl: 1rem;
            --shadow-lg: 0 10px 15px -3px rgb(0 0 0 / 0.1), 0 4px 6px -4px rgb(0 0 0 / 0.1);
            --shadow-xl: 0 20px 25px -5px rgb(0 0 0 / 0.1), 0 8px 10px -6px rgb(0 0 0 / 0.1);
            --transition: all 0.2s ease;
        }
        * { margin:0; padding:0; box-sizing:border-box; }
        body {
            font-family: var(--font-sans);
            background: #f8f9fa;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: var(--space-4);
        }
        .kiosk {
            max-width: 1400px;
            width: 100%;
            background: rgba(255, 255, 255, 0.95);
            border-radius: var(--radius-xl);
            box-shadow: var(--shadow-xl);
            backdrop-filter: blur(8px);
            overflow: hidden;
            min-height: 80vh;
            display: flex;
            flex-direction: column;
        }
        .screen {
            padding: var(--space-8);
            flex: 1;
        }
        .time {
            text-align: right;
            font-size: var(--text-lg);
            color: var(--neutral-500, #6c7384);
            margin-bottom: var(--space-6);
        }
        h1 {
            font-size: var(--text-4xl);
            font-weight: 700;
            margin-bottom: var(--space-4);
            color: var(--primary-600);
        }
        .card {
            background: white;
            border-radius: var(--radius-xl);
            padding: var(--space-8);
            box-shadow: var(--shadow-lg);
            margin: var(--space-6) 0;
        }
        .form-control {
            padding: 0.75rem;
            background: white;
            border: 1px solid var(--neutral-300);
            border-radius: var(--radius-lg);
        }
        .error-message {
            background: #fee2e2;
            color: #dc2626;
            padding: var(--space-2);
            border-radius: var(--radius);
            margin-top: 0.5rem;
            text-align: center;
        }
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: var(--space-2);
            padding: var(--space-4) var(--space-8);
            font-size: var(--text-xl);
            font-weight: 600;
            text-decoration: none;
            border-radius: 9999px;
            transition: var(--transition);
            cursor: pointer;
            border: none;
            background: var(--primary-600);
            color: white;
            min-height: 64px;
            min-width: 120px;
            margin-top: var(--space-6);
            width: 100%;
        }
        .btn:active { transform: scale(0.98); }
        .btn-primary:hover { background: var(--primary-700); transform: translateY(-2px); }
    </style>
</head>
<body>
    <div class="kiosk">
        <div class="screen">
            <div class="time"></div>
            <h1>Complete Payment</h1>
            <p style="font-size: var(--text-xl); margin-bottom: var(--space-6);">
                Total: <strong>$<?= number_format($total, 2) ?></strong>
            </p>
            <div class="card">
                <form id="payment-form">
                    <div id="card-element" class="form-control"></div>
                    <div id="card-errors" class="error-message"></div>
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
            // Stripe will redirect on success
        });
    </script>
</body>
</html>