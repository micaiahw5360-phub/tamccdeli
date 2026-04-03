<?php
require __DIR__ . '/../includes/session.php';
require __DIR__ . '/../config/database.php';
require __DIR__ . '/../includes/mail.php';

$user_id = $_SESSION['pending_verification_user_id'] ?? 0;
if (!$user_id) {
    header('Location: register.php');
    exit;
}

$stmt = $conn->prepare("SELECT email FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

$email_code = rand(100000, 999999);
$expires = date('Y-m-d H:i:s', strtotime('+15 minutes'));
$stmt = $conn->prepare("INSERT INTO email_verifications (email, code, expires_at) VALUES (?, ?, ?)");
$stmt->bind_param("sss", $user['email'], $email_code, $expires);
$stmt->execute();

$subject = "Email Verification - TAMCC Deli";
$body = "<h2>Your new verification code</h2><p><strong>$email_code</strong></p><p>Expires in 15 minutes.</p>";
sendEmail($user['email'], $subject, $body);

$_SESSION['resend_message'] = "New code sent to your email.";
header("Location: verify-account.php");
exit;