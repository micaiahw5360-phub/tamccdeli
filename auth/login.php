<?php
require __DIR__ . '/../includes/session.php';
require __DIR__ . '/../config/database.php';
require __DIR__ . '/../includes/csrf.php';
require __DIR__ . '/../includes/kiosk.php'; // Required for normal_url()

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

        // Clear any kiosk mode
        unset($_SESSION['kiosk_mode']);
        
        header("Location: ../index.php");
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
        /* Additional inline styles (same as before) */
        .auth-card {
            width: 100%;
            max-width: 400px;
            background: white;
            border-radius: var(--radius-lg);
            padding: 2rem;
            box-shadow: var(--shadow-lg);
            border-top: 4px solid var(--primary-600);
            transition: transform 0.2s;
        }
        .auth-card:hover {
            transform: translateY(-2px);
        }
        .brand-icon {
            text-align: center;
            font-size: 3rem;
            margin-bottom: 0.5rem;
        }
        .auth-card h2 {
            text-align: center;
            margin-bottom: 1.5rem;
            font-size: 1.8rem;
            font-weight: 700;
            color: var(--neutral-800);
        }
        .form-group {
            margin-bottom: 1rem;
        }
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: var(--neutral-700);
        }
        .form-group input {
            width: 100%;
            padding: 0.75rem;
            border: 1.5px solid var(--neutral-300);
            border-radius: var(--radius);
            font-size: 1rem;
            transition: var(--transition);
        }
        .form-group input:focus {
            border-color: var(--primary-600);
            outline: none;
            box-shadow: 0 0 0 3px rgba(7,74,242,0.1);
        }
        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 1.5rem;
        }
        .checkbox-group input {
            width: auto;
            margin: 0;
        }
        .checkbox-group label {
            margin: 0;
            font-weight: normal;
        }
        .btn-block {
            width: 100%;
            padding: 0.75rem;
            font-size: 1.1rem;
            border-radius: 2rem;
            font-weight: 600;
        }
        .btn-outline {
            background: transparent;
            border: 1px solid var(--neutral-300);
            color: var(--neutral-700);
            box-shadow: none;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            width: 100%;
            padding: 0.75rem;
            border-radius: 2rem;
            text-decoration: none;
            transition: all 0.2s;
        }
        .btn-outline:hover {
            background: var(--neutral-100);
            border-color: var(--primary-600);
            color: var(--primary-600);
        }
        .social-login {
            margin-top: 1.5rem;
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }
        .auth-footer {
            text-align: center;
            margin-top: 1.5rem;
            font-size: 0.9rem;
            color: var(--neutral-600);
        }
        .auth-footer a {
            color: var(--primary-600);
            text-decoration: none;
            font-weight: 600;
        }
        .auth-footer a:hover {
            text-decoration: underline;
        }
        hr {
            margin: 1.5rem 0;
            border: none;
            border-top: 1px solid var(--neutral-200);
        }
        .error-message {
            background: #fee2e2;
            color: #dc2626;
            padding: 0.75rem;
            border-radius: var(--radius);
            margin-bottom: 1rem;
            text-align: center;
            border-left: 3px solid #dc2626;
        }
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
                    <label for="username">Username</label>
                    <input type="text" id="username" name="username" placeholder="Enter your username" required>
                </div>
                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" placeholder="Enter your password" required>
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

            <div class="social-login">
                <a href="google-login.php" class="btn-outline">
                    <svg stroke="currentColor" fill="currentColor" stroke-width="0" version="1.1" viewBox="0 0 48 48" height="18" width="18" xmlns="http://www.w3.org/2000/svg">
                        <path fill="#FFC107" d="M43.611,20.083H42V20H24v8h11.303c-1.649,4.657-6.08,8-11.303,8c-6.627,0-12-5.373-12-12c0-6.627,5.373-12,12-12c3.059,0,5.842,1.154,7.961,3.039l5.657-5.657C34.046,6.053,29.268,4,24,4C12.955,4,4,12.955,4,24c0,11.045,8.955,20,20,20c11.045,0,20-8.955,20-20C44,22.659,43.862,21.35,43.611,20.083z"></path>
                        <path fill="#FF3D00" d="M6.306,14.691l6.571,4.819C14.655,15.108,18.961,12,24,12c3.059,0,5.842,1.154,7.961,3.039l5.657-5.657C34.046,6.053,29.268,4,24,4C16.318,4,9.656,8.337,6.306,14.691z"></path>
                        <path fill="#4CAF50" d="M24,44c5.166,0,9.86-1.977,13.409-5.192l-6.19-5.238C29.211,35.091,26.715,36,24,36c-5.202,0-9.619-3.317-11.283-7.946l-6.522,5.025C9.505,39.556,16.227,44,24,44z"></path>
                        <path fill="#1976D2" d="M43.611,20.083H42V20H24v8h11.303c-0.792,2.237-2.231,4.166-4.087,5.571c0.001-0.001,0.002-0.001,0.003-0.002l6.19,5.238C36.971,39.205,44,34,44,24C44,22.659,43.862,21.35,43.611,20.083z"></path>
                    </svg>
                    Continue with Google
                </a>
                <!-- Apple login is a placeholder; you can remove or implement later -->
            </div>

            <div class="auth-footer">
                Don't have an account? <a href="register.php">Sign up</a>
            </div>
            <div class="auth-footer" style="margin-top: 0.5rem;">
                <a href="<?= normal_url('/terms.php') ?>">Terms & Conditions</a> |
                <a href="<?= normal_url('/privacy.php') ?>">Privacy Policy</a>
            </div>
        </div>
    </div>
</body>
</html>