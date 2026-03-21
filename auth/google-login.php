<?php
require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/includes/session.php';

$client_id = getenv('GOOGLE_CLIENT_ID');
$client_secret = getenv('GOOGLE_CLIENT_SECRET');
$redirect_uri = (isset($_SERVER['HTTPS']) ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'] . '/auth/google-callback.php';

$client = new Google\Client();
$client->setClientId($client_id);
$client->setClientSecret($client_secret);
$client->setRedirectUri($redirect_uri);
$client->addScope('email');
$client->addScope('profile');

// Generate and store state to prevent CSRF
$state = bin2hex(random_bytes(16));
$_SESSION['google_state'] = $state;
$client->setState($state);

$auth_url = $client->createAuthUrl();
header('Location: ' . filter_var($auth_url, FILTER_SANITIZE_URL));
exit;