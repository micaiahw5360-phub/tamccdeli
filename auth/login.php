<?php
require __DIR__ . '/../includes/session.php';
require __DIR__ . '/../config/database.php';
require __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/kiosk.php';

$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (!validateToken($_POST['csrf_token'])) {
        die("Invalid request");
    }

    $login = trim($_POST['login']);
    $password = $_POST['password'];
    $remember = isset($_POST['remember']);

    // Determine if login is email, username, or phone
    if (filter_var($login, FILTER_VALIDATE_EMAIL)) {
        $stmt = $conn->prepare("SELECT id, password, role FROM users WHERE email = ? AND is_active = 1");
        $stmt->bind_param("s", $login);
    } elseif (preg_match('/^\+?[0-9]{7,15}$/', $login)) {
        $stmt = $conn->prepare("SELECT id, password, role FROM users WHERE phone = ? AND is_active = 1");
        $stmt->bind_param("s", $login);
    } else {
        $stmt = $conn->prepare("SELECT id, password, role FROM users WHERE username = ? AND is_active = 1");
        $stmt->bind_param("s", $login);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();

    if ($user && password_verify($password, $user['password'])) {
        session_regenerate_id(true);
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['role'] = $user['role'];

        if ($user['role'] === 'kiosk') {
            $_SESSION['kiosk_mode'] = true;
        } else {
            unset($_SESSION['kiosk_mode']);
        }

        if ($remember) {
            $token = bin2hex(random_bytes(32));
            $expires = date('Y-m-d H:i:s', strtotime('+30 days'));
            $stmt = $conn->prepare("INSERT INTO user_tokens (user_id, token, expires_at) VALUES (?, ?, ?)");
            $stmt->bind_param("iss", $user['id'], $token, $expires);
            $stmt->execute();
            setcookie('remember_token', $token, time() + 86400 * 30, '/', '', false, true);
        }

        $redirect = $_SESSION['redirect_after_login'] ?? '../index.php';
        unset($_SESSION['redirect_after_login']);
        if ($user['role'] === 'kiosk') {
            $redirect = kiosk_url('/kiosk/home.php');
        }
        header("Location: $redirect");
        exit;
    } else {
        $error = "Invalid login credentials";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | TAMCC Deli</title>
    <link rel="stylesheet" href="../assets/css/global.css">
    <style>
        .auth-card { max-width: 400px; margin: 0 auto; background: white; border-radius: var(--radius-lg); padding: 2rem; box-shadow: var(--shadow-lg); border-top: 4px solid var(--primary-600); }
        .brand-icon { text-align: center; font-size: 3rem; }
        .auth-card h2 { text-align: center; margin-bottom: 1.5rem; }
        .form-group { margin-bottom: 1rem; position: relative; }
        .form-group label { display: block; margin-bottom: 0.5rem; font-weight: 600; }
        .form-group input { width: 100%; padding: 0.75rem; border: 1.5px solid var(--neutral-300); border-radius: var(--radius); font-size: 1rem; padding-right: 2.5rem; }
        .form-group input:focus { border-color: var(--primary-600); outline: none; box-shadow: 0 0 0 3px rgba(7,74,242,0.1); }
        .password-toggle { position: absolute; right: 12px; top: 42px; cursor: pointer; color: var(--neutral-500); }
        .checkbox-group { display: flex; align-items: center; gap: 0.5rem; margin-bottom: 1.5rem; }
        .btn-block { width: 100%; padding: 0.75rem; font-size: 1.1rem; border-radius: 2rem; }
        .auth-footer { text-align: center; margin-top: 1.5rem; font-size: 0.9rem; }
        .auth-footer a { color: var(--primary-600); text-decoration: none; font-weight: 600; }
        .error-message { background: #fee2e2; color: #dc2626; padding: 0.75rem; border-radius: var(--radius); margin-bottom: 1rem; text-align: center; }
        hr { margin: 1.5rem 0; border: none; border-top: 1px solid var(--neutral-200); }
    </style>
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
                <label for="login">Username, Email, or Phone</label>
                <input type="text" id="login" name="login" required>
            </div>
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required>
                <span class="password-toggle" onclick="togglePassword()">👁️</span>
            </div>
            <div class="checkbox-group">
                <input type="checkbox" id="remember" name="remember">
                <label for="remember">Remember me</label>
            </div>
            <button type="submit" class="btn btn-primary btn-block">Log in</button>
        </form>
        <div class="auth-footer">
            <a href="forgot-password.php">Forgot password?</a>
        </div>
        <hr>
        <div class="auth-footer">
            Don't have an account? <a href="register.php">Sign up</a>
        </div>
        <div class="auth-footer" style="margin-top: 0.5rem;">
            <a href="<?= normal_url('/terms.php') ?>">Terms</a> | <a href="<?= normal_url('/privacy.php') ?>">Privacy</a>
        </div>
    </div>
</div>
<script>
    function togglePassword() {
        const pwd = document.getElementById('password');
        pwd.type = pwd.type === 'password' ? 'text' : 'password';
    }
</script>
</body>
</html>