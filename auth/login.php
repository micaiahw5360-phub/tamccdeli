<?php
require __DIR__ . '/../includes/session.php';
require __DIR__ . '/../config/database.php';
require __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/kiosk.php';
require_once __DIR__ . '/../includes/firebase.php';

$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (!validateToken($_POST['csrf_token'])) {
        die("Invalid request");
    }

    // Handle Firebase Google Sign-In
    if (!empty($_POST['firebase_id_token'])) {
        $idToken = $_POST['firebase_id_token'];
        $verification = FirebaseAuth::verifyIdToken($idToken);
        if ($verification['success']) {
            $email = $verification['email'];
            $firebase_uid = $verification['uid'];
            // Check if user exists
            $stmt = $conn->prepare("SELECT id, role FROM users WHERE email = ?");
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $user = $stmt->get_result()->fetch_assoc();
            if (!$user) {
                // Create new user
                $username = explode('@', $email)[0];
                $base = $username;
                $counter = 1;
                while (true) {
                    $check = $conn->prepare("SELECT id FROM users WHERE username = ?");
                    $check->bind_param("s", $username);
                    $check->execute();
                    if ($check->get_result()->num_rows === 0) break;
                    $username = $base . $counter;
                    $counter++;
                }
                $stmt = $conn->prepare("INSERT INTO users (username, email, firebase_uid, role, is_active) VALUES (?, ?, ?, 'customer', 1)");
                $stmt->bind_param("sss", $username, $email, $firebase_uid);
                $stmt->execute();
                $user_id = $conn->insert_id;
                $_SESSION['user_id'] = $user_id;
                $_SESSION['role'] = 'customer';
            } else {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['role'] = $user['role'];
            }
            $redirect = $_SESSION['redirect_after_login'] ?? '../index.php';
            unset($_SESSION['redirect_after_login']);
            header("Location: $redirect");
            exit;
        } else {
            $error = "Google sign-in failed. Please try again.";
        }
    } else {
        // Regular login with username/email/phone + password
        $login = trim($_POST['login']);
        $password = $_POST['password'];
        $remember = isset($_POST['remember']);

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
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | TAMCC Deli</title>
    <link rel="stylesheet" href="../assets/css/global.css">
    <script src="https://www.gstatic.com/firebasejs/10.7.1/firebase-app-compat.js"></script>
    <script src="https://www.gstatic.com/firebasejs/10.7.1/firebase-auth-compat.js"></script>
    <style>
        .auth-card { max-width: 400px; margin: 0 auto; background: white; border-radius: var(--radius-lg); padding: 2rem; box-shadow: var(--shadow-lg); border-top: 4px solid var(--primary-600); }
        .brand-icon { text-align: center; font-size: 3rem; }
        .auth-card h2 { text-align: center; margin-bottom: 1.5rem; }
        .form-group { margin-bottom: 1rem; position: relative; }
        .form-group label { display: block; margin-bottom: 0.5rem; font-weight: 600; }
        .form-group input { width: 100%; padding: 0.75rem; border: 1.5px solid var(--neutral-300); border-radius: var(--radius); font-size: 1rem; padding-right: 2.5rem; }
        .form-group input:focus { border-color: var(--primary-600); outline: none; box-shadow: 0 0 0 3px rgba(7,74,242,0.1); }
        .password-toggle { position: absolute; right: 12px; top: 42px; cursor: pointer; color: var(--neutral-600); font-size: 0.85rem; font-weight: 500; background: var(--neutral-100); padding: 0.2rem 0.5rem; border-radius: 20px; transition: all 0.2s; }
        .password-toggle:hover { background: var(--neutral-200); }
        .checkbox-group { display: flex; align-items: center; gap: 0.5rem; margin-bottom: 1.5rem; }
        .checkbox-group input { margin: 0; width: auto; }
        .checkbox-group label { margin: 0; font-weight: normal; cursor: pointer; }
        .btn-block { width: 100%; padding: 0.75rem; font-size: 1.1rem; border-radius: 2rem; }
        .btn-outline { background: transparent; border: 1px solid var(--neutral-300); color: var(--neutral-700); display: flex; align-items: center; justify-content: center; gap: 0.5rem; width: 100%; padding: 0.75rem; border-radius: 2rem; text-decoration: none; transition: all 0.2s; cursor: pointer; }
        .btn-outline:hover { background: var(--neutral-100); border-color: var(--primary-600); color: var(--primary-600); }
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
        <form method="POST" id="loginForm">
            <input type="hidden" name="csrf_token" value="<?= generateToken() ?>">
            <input type="hidden" name="firebase_id_token" id="firebase_id_token" value="">
            <div class="form-group">
                <label for="login">Username, Email, or Phone</label>
                <input type="text" id="login" name="login" required>
            </div>
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required>
                <span class="password-toggle" id="togglePwdBtn" onclick="togglePassword()">Show</span>
            </div>
            <div class="checkbox-group">
                <input type="checkbox" id="remember" name="remember">
                <label for="remember">Remember me</label>
            </div>
            <button type="submit" class="btn btn-primary btn-block">Log in</button>
        </form>

        <hr>

        <button type="button" id="googleSignInBtn" class="btn-outline">
            <svg stroke="currentColor" fill="currentColor" stroke-width="0" version="1.1" viewBox="0 0 48 48" height="18" width="18" xmlns="http://www.w3.org/2000/svg">
                <path fill="#FFC107" d="M43.611,20.083H42V20H24v8h11.303c-1.649,4.657-6.08,8-11.303,8c-6.627,0-12-5.373-12-12c0-6.627,5.373-12,12-12c3.059,0,5.842,1.154,7.961,3.039l5.657-5.657C34.046,6.053,29.268,4,24,4C12.955,4,4,12.955,4,24c0,11.045,8.955,20,20,20c11.045,0,20-8.955,20-20C44,22.659,43.862,21.35,43.611,20.083z"></path>
                <path fill="#FF3D00" d="M6.306,14.691l6.571,4.819C14.655,15.108,18.961,12,24,12c3.059,0,5.842,1.154,7.961,3.039l5.657-5.657C34.046,6.053,29.268,4,24,4C16.318,4,9.656,8.337,6.306,14.691z"></path>
                <path fill="#4CAF50" d="M24,44c5.166,0,9.86-1.977,13.409-5.192l-6.19-5.238C29.211,35.091,26.715,36,24,36c-5.202,0-9.619-3.317-11.283-7.946l-6.522,5.025C9.505,39.556,16.227,44,24,44z"></path>
                <path fill="#1976D2" d="M43.611,20.083H42V20H24v8h11.303c-0.792,2.237-2.231,4.166-4.087,5.571c0.001-0.001,0.002-0.001,0.003-0.002l6.19,5.238C36.971,39.205,44,34,44,24C44,22.659,43.862,21.35,43.611,20.083z"></path>
            </svg>
            Sign in with Google
        </button>

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
    const firebaseConfig = {
        apiKey: "AIzaSyBSOFZGNOq83FiBZHZgBjNFu1wcuBLtQUU",
        authDomain: "tamccdeli-b01f0.firebaseapp.com",
        projectId: "tamccdeli-b01f0",
        storageBucket: "tamccdeli-b01f0.firebasestorage.app",
        messagingSenderId: "187179883843",
        appId: "1:187179883843:web:555216aa5ef51e1d65e3f6",
        measurementId: "G-LW6LH9MQT8"
    };
    firebase.initializeApp(firebaseConfig);
    const auth = firebase.auth();
    const provider = new firebase.auth.GoogleAuthProvider();

    function togglePassword() {
        const pwd = document.getElementById('password');
        const toggleBtn = document.getElementById('togglePwdBtn');
        if (pwd.type === 'password') {
            pwd.type = 'text';
            toggleBtn.textContent = 'Hide';
        } else {
            pwd.type = 'password';
            toggleBtn.textContent = 'Show';
        }
    }

    document.getElementById('googleSignInBtn').addEventListener('click', async () => {
        try {
            const result = await auth.signInWithPopup(provider);
            const idToken = await result.user.getIdToken();
            document.getElementById('firebase_id_token').value = idToken;
            document.getElementById('loginForm').submit();
        } catch (error) {
            alert('Google sign-in failed: ' + error.message);
        }
    });
</script>
</body>
</html>