<?php
require __DIR__ . '/includes/session.php';
require 'config/database.php';
require 'includes/csrf.php';
require 'includes/kiosk.php';
require_once __DIR__ . '/vendor/autoload.php';

use Stripe\Stripe;
use Stripe\PaymentIntent;

if (!isset($_SESSION['user_id'])) {
    header("Location: auth/login.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: wallet.php");
    exit;
}

if (!validateToken($_POST['csrf_token'])) {
    die('Invalid CSRF token');
}

$user_id = $_SESSION['user_id'];
$amount = floatval($_POST['amount']);

if ($amount <= 0 || $amount > 1000) {
    $_SESSION['topup_error'] = "Invalid amount. Must be between $1 and $1000.";
    header("Location: " . kiosk_url('wallet.php'));
    exit;
}

// Create a Stripe PaymentIntent
Stripe::setApiKey(getenv('STRIPE_SECRET_KEY'));
$intent = PaymentIntent::create([
    'amount'   => round($amount * 100),
    'currency' => 'usd',
    'metadata' => ['user_id' => $user_id, 'type' => 'topup'],
]);

$_SESSION['stripe_intent_id'] = $intent->id;
$_SESSION['stripe_client_secret'] = $intent->client_secret;
$_SESSION['stripe_amount'] = $amount;
$_SESSION['stripe_type'] = 'topup';

header("Location: " . kiosk_url('stripe-topup.php'));
exit;