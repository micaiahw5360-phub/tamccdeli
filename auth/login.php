<?php
// Enable error reporting for debugging (remove later)
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Start output buffering to capture any accidental output (including warnings)
ob_start();

require __DIR__ . '/../config/database.php';
require __DIR__ . '/../includes/csrf.php';

$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (!validateToken($_POST['csrf_token'])) {
        die("Invalid request");
    }

    $username = trim($_POST['username']);
    $password = $_POST['password'];
    $remember = isset($_POST['remember']);

    $stmt = $conn->prepare("SELECT id, password, role FROM users WHERE username = ? AND is_active = 1");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();

    if ($user && password_verify($password, $user['password'])) {
        // Session already started by csrf.php, regenerate ID for security
        session_regenerate_id(true);
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['role'] = $user['role'];

        if ($remember) {
            $token = bin2hex(random_bytes(32));
            $expires = date('Y-m-d H:i:s', strtotime('+30 days'));
            $stmt = $conn->prepare("INSERT INTO user_tokens (user_id, token, expires_at) VALUES (?, ?, ?)");
            $stmt->bind_param("iss", $user['id'], $token, $expires);
            $stmt->execute();
            setcookie('remember_token', $token, time() + 86400 * 30, '/', '', false, true);
        }

        // Clear output buffer before redirect
        ob_end_clean();
        header("Location: ../index.php");
        exit;
    } else {
        $error = "Invalid login credentials";
    }
}

// If we reach here, output the page (buffer will be sent automatically at end of script)
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | TAMCC Deli</title>
    <link rel="stylesheet" href="../assets/css/global.css">
</head>
<body>
    <div class="auth-container">
        <div class="auth-card">
            <div class="brand-icon">🍽️</div>
            <h2>Login to TAMCC Deli</h2>
            <?php if ($error): ?>
                <div class="error-message"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= generateToken() ?>">
                <div class="form-group">
                    <label for="username">Username</label>
                    <input type="text" id="username" name="username" placeholder="Enter your username" required>
                </div>
                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" placeholder="Enter your password" required>
                </div>
                <div class="form-group" style="display: flex; align-items: center;">
                    <input type="checkbox" id="remember" name="remember" style="width: auto; margin-right: 8px;">
                    <label for="remember" style="display: inline;">Remember me</label>
                </div>
                <button type="submit" class="btn btn-primary btn-block">Login</button>
            </form>

            <!-- Google login button (added) -->
            <hr>
            <a href="google-login.php" class="btn btn-outline" style="width:100%; text-align:center;">
                <img src="https://www.gstatic.com/firebasejs/ui/2.0.0/images/auth/google.svg" style="height:18px; margin-right:6px;">
                Continue with Google
            </a>

            <p class="auth-footer">
                <a href="forgot-password.php">Forgot password?</a> |
                <a href="register.php">Create account</a>
            </p>
        </div>
    </div>
</body>
</html>
<?php
// End output buffering (flushes automatically)
ob_end_flush();
?>