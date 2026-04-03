<?php
require __DIR__ . '/../includes/session.php';
require __DIR__ . '/../config/database.php';
require __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/kiosk.php';
require_once __DIR__ . '/../includes/mail.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateToken($_POST['csrf_token'])) {
        die('Invalid CSRF token');
    }

    $email = trim($_POST['email']);
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Invalid email address.';
    } else {
        $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();

        if ($user) {
            $token = bin2hex(random_bytes(32));
            $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));
            $delete = $conn->prepare("DELETE FROM password_resets WHERE user_id = ?");
            $delete->bind_param("i", $user['id']);
            $delete->execute();
            $stmt = $conn->prepare("INSERT INTO password_resets (user_id, token, expires_at) VALUES (?, ?, ?)");
            $stmt->bind_param("iss", $user['id'], $token, $expires);
            $stmt->execute();

            // Absolute URL without kiosk parameter
            $reset_link = (isset($_SERVER['HTTPS']) ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'] . '/auth/reset-password.php?token=' . $token;
            $subject = 'Password Reset Request';
            $body = "<h2>Reset Your Password</h2>
                     <p>Click the link below to set a new password:</p>
                     <p><a href='$reset_link'>$reset_link</a></p>
                     <p>Link expires in 1 hour.</p>";
            if (sendEmail($email, $subject, $body)) {
                $success = 'Reset link sent to your email.';
            } else {
                $error = 'Could not send email. Please try again later.';
            }
        } else {
            $success = 'If that email exists, we sent a reset link.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Forgot Password</title>
    <link rel="stylesheet" href="../assets/css/global.css">
    <style>
        body { margin:0; background: linear-gradient(135deg, var(--neutral-50) 0%, var(--neutral-100) 100%); min-height:100vh; display:flex; align-items:center; justify-content:center; }
        .auth-card { max-width:420px; background:white; border-radius:var(--radius-lg); padding:2rem; box-shadow:var(--shadow-lg); border-top:4px solid var(--primary-600); }
        .brand-icon { text-align:center; font-size:3rem; }
        .btn-block { width:100%; padding:0.75rem; margin-top:0.5rem; }
        .error-message, .success-message { padding:0.75rem; border-radius:var(--radius); margin-bottom:1rem; text-align:center; }
        .error-message { background:#fee2e2; color:#dc2626; }
        .success-message { background:#dcfce7; color:#15803d; }
    </style>
</head>
<body>
<div class="auth-container">
    <div class="auth-card">
        <div class="brand-icon">🔐</div>
        <h2>Forgot Password</h2>
        <?php if ($error): ?>
            <div class="error-message"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="success-message"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?= generateToken() ?>">
            <div class="form-group">
                <label for="email">Email Address</label>
                <input type="email" id="email" name="email" required>
            </div>
            <button type="submit" class="btn btn-primary btn-block">Send Reset Link</button>
        </form>
        <div class="auth-footer"><a href="login.php">Back to Login</a></div>
    </div>
</div>
</body>
</html>