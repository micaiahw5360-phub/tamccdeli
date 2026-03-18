<?php
require __DIR__ . '/../config/database.php';
require __DIR__ . '/../includes/csrf.php';

$error = '';
$success = '';
$token = $_GET['token'] ?? '';

if (!$token) {
    header('Location: login.php');
    exit;
}

// Verify token
$stmt = $conn->prepare("SELECT email FROM password_resets WHERE token = ? AND expires_at > NOW()");
$stmt->bind_param("s", $token);
$stmt->execute();
$reset = $stmt->get_result()->fetch_assoc();
if (!$reset) {
    $error = "Invalid or expired token.";
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$error) {
    if (!validateToken($_POST['csrf_token'])) {
        die('Invalid CSRF token');
    }
    $password = $_POST['password'];
    $confirm = $_POST['confirm_password'];
    if ($password !== $confirm) {
        $error = "Passwords do not match.";
    } elseif (strlen($password) < 12) {
        $error = "Password must be at least 12 characters.";
    } else {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("UPDATE users SET password = ? WHERE email = ?");
        $stmt->bind_param("ss", $hash, $reset['email']);
        $stmt->execute();

        // Delete used token
        $stmt = $conn->prepare("DELETE FROM password_resets WHERE email = ?");
        $stmt->bind_param("s", $reset['email']);
        $stmt->execute();

        $success = "Password updated. <a href='login.php'>Login now</a>";
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Reset Password</title>
    <link rel="stylesheet" href="../assets/css/global.css">
</head>
<body>
    <div class="auth-container">
        <div class="auth-card">
            <h2>Set New Password</h2>
            <?php if ($error): ?><div class="error-message"><?= $error ?></div><?php endif; ?>
            <?php if ($success): ?><div class="success-message"><?= $success ?></div><?php endif; ?>
            <?php if (!$error && !$success): ?>
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= generateToken() ?>">
                <div class="form-group">
                    <label>New Password</label>
                    <input type="password" name="password" required>
                </div>
                <div class="form-group">
                    <label>Confirm Password</label>
                    <input type="password" name="confirm_password" required>
                </div>
                <button type="submit" class="btn btn-primary btn-block">Update Password</button>
            </form>
            <?php endif; ?>
            <p class="auth-footer"><a href="login.php">Back to Login</a></p>
        </div>
    </div>
</body>
</html>