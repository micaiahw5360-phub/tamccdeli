<?php
require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../config/database.php';
require __DIR__ . '/includes/session.php';

if (!isset($_GET['code']) || !isset($_GET['state'])) {
    die('Invalid request');
}

// Verify state to prevent CSRF
if ($_GET['state'] !== ($_SESSION['google_state'] ?? '')) {
    die('State mismatch');
}
unset($_SESSION['google_state']);

$client_id = getenv('GOOGLE_CLIENT_ID');
$client_secret = getenv('GOOGLE_CLIENT_SECRET');
$redirect_uri = (isset($_SERVER['HTTPS']) ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'] . '/auth/google-callback.php';

$client = new Google\Client();
$client->setClientId($client_id);
$client->setClientSecret($client_secret);
$client->setRedirectUri($redirect_uri);

$token = $client->fetchAccessTokenWithAuthCode($_GET['code']);
if (isset($token['error'])) {
    die('Error fetching token: ' . $token['error_description']);
}
$client->setAccessToken($token);

// Get user info
$oauth = new Google\Service\Oauth2($client);
$userinfo = $oauth->userinfo->get();

$email = $userinfo->email;
$first_name = $userinfo->givenName;
$last_name = $userinfo->familyName;
$picture = $userinfo->picture;

// Check if user exists by email
$stmt = $conn->prepare("SELECT id, role FROM users WHERE email = ?");
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

if ($user) {
    // Existing user – log in
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['role'] = $user['role'];
    $_SESSION['username'] = $first_name . ' ' . $last_name;
    $_SESSION['profile_photo'] = $picture;
} else {
    // New user – create account
    $random_password = bin2hex(random_bytes(8));
    $hashed = password_hash($random_password, PASSWORD_DEFAULT);
    $username = $first_name . ' ' . $last_name;
    $stmt = $conn->prepare("INSERT INTO users (username, email, password, role, is_active) VALUES (?, ?, ?, 'customer', 1)");
    $stmt->bind_param("sss", $username, $email, $hashed);
    $stmt->execute();
    $user_id = $conn->insert_id;
    $_SESSION['user_id'] = $user_id;
    $_SESSION['role'] = 'customer';
    $_SESSION['username'] = $username;
    $_SESSION['profile_photo'] = $picture;
}

// Redirect to intended page (e.g., index.php)
header('Location: ../index.php');
exit;