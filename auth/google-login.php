<?php
require __DIR__ . '/../includes/session.php';
require __DIR__ . '/../vendor/autoload.php';

// Determine if we are on HTTPS (Render sets HTTPS environment variable)
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
$redirect_uri = $protocol . $_SERVER['HTTP_HOST'] . '/auth/google-callback.php';

$client_id = getenv('GOOGLE_CLIENT_ID');
$client_secret = getenv('GOOGLE_CLIENT_SECRET');

$client = new Google\Client();
$client->setClientId($client_id);
$client->setClientSecret($client_secret);
$client->setRedirectUri($redirect_uri);
$client->addScope('email');
$client->addScope('profile');

$state = bin2hex(random_bytes(16));
$_SESSION['google_state'] = $state;
$client->setState($state);

$auth_url = $client->createAuthUrl();
header('Location: ' . filter_var($auth_url, FILTER_SANITIZE_URL));
exit;