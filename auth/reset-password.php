<?php
require __DIR__ . '/../includes/session.php';
require __DIR__ . '/../config/database.php';
require __DIR__ . '/../includes/csrf.php';
require __DIR__ . '/../includes/kiosk.php';

$token = $_GET['token'] ?? '';
$error = '';
$success = '';

if (empty($token)) {
    header('Location: login.php');
    exit;
}

// Validate token
$stmt = $conn->prepare("SELECT user_id, expires_at FROM password_resets WHERE token = ? AND used = 0");
$stmt->bind_param("s", $token);
$stmt->execute();
$reset = $stmt->get_result()->fetch_assoc();

if (!$reset || strtotime($reset['expires_at']) < time()) {
    $error = 'Invalid or expired reset link.';
} else {
    $user_id = $reset['user_id'];
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!validateToken($_POST['csrf_token'])) {
            die('Invalid CSRF token');
        }
        $password = $_POST['password'];
        $confirm = $_POST['confirm_password'];
        if (strlen($password) < 12) {
            $error = 'Password must be at least 12 characters.';
        } elseif ($password !== $confirm) {
            $error = 'Passwords do not match.';
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
            $stmt->bind_param("si", $hash, $user_id);
            $stmt->execute();

            // Mark token as used
            $conn->query("UPDATE password_resets SET used = 1 WHERE token = '$token'");

            $success = 'Password updated successfully. You can now <a href="login.php">login</a>.';
        }
    }
}

$page_title = "Reset Password";
include __DIR__ . '/../includes/header.php';
?>

<div class="auth-container">
    <div class="auth-card">
        <h2>Reset Password</h2>
        <?php if ($error): ?>
            <div class="error-message"><?= $error ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="success-message"><?= $success ?></div>
        <?php else: ?>
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= generateToken() ?>">
                <div class="form-group">
                    <label for="password">New Password</label>
                    <input type="password" id="password" name="password" required>
                </div>
                <div class="form-group">
                    <label for="confirm_password">Confirm Password</label>
                    <input type="password" id="confirm_password" name="confirm_password" required>
                </div>
                <button type="submit" class="btn btn-primary btn-block">Reset Password</button>
            </form>
        <?php endif; ?>
        <p class="auth-footer"><a href="login.php">Back to Login</a></p>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>