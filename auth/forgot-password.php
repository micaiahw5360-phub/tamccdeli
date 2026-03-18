<?php
require __DIR__ . '/../config/database.php';
require __DIR__ . '/../includes/csrf.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateToken($_POST['csrf_token'])) {
        die('Invalid CSRF token');
    }
    $email = trim($_POST['email']);
    $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    if ($user) {
        $token = bin2hex(random_bytes(32));
        $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));
        $stmt = $conn->prepare("INSERT INTO password_resets (email, token, expires_at) VALUES (?, ?, ?)");
        $stmt->bind_param("sss", $email, $token, $expires);
        $stmt->execute();

        // In a real app, send email. Here we'll just show the link.
        $reset_link = "http://localhost/tamccdeli/auth/reset-password.php?token=$token";
        $success = "Password reset link: <a href='$reset_link'>$reset_link</a> (Simulated email)";
    } else {
        $error = "Email not found.";
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Forgot Password</title>
    <link rel="stylesheet" href="../assets/css/global.css">
</head>
<body>
    <div class="auth-container">
        <div class="auth-card">
            <h2>Reset Password</h2>
            <?php if ($error): ?><div class="error-message"><?= $error ?></div><?php endif; ?>
            <?php if ($success): ?><div class="success-message"><?= $success ?></div><?php endif; ?>
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= generateToken() ?>">
                <div class="form-group">
                    <label>Email</label>
                    <input type="email" name="email" required>
                </div>
                <button type="submit" class="btn btn-primary btn-block">Send Reset Link</button>
            </form>
            <p class="auth-footer"><a href="login.php">Back to Login</a></p>
        </div>
    </div>
</body>
</html>