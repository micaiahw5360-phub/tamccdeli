<?php
require __DIR__ . '/../includes/session.php';
require __DIR__ . '/../config/database.php';
require __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/kiosk.php';
require_once __DIR__ . '/../includes/mail.php';

$error = "";
$prefill_email = $_POST['email'] ?? $_GET['email'] ?? '';
$prefill_username = $_POST['username'] ?? '';
$prefill_phone = $_POST['phone'] ?? '';

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (!validateToken($_POST['csrf_token'])) {
        die("Invalid request");
    }

    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $phone = !empty($_POST['phone']) ? preg_replace('/[^0-9+]/', '', trim($_POST['phone'])) : null;
    $password = $_POST['password'];

    // Validate input
    if (strlen($username) < 4) {
        $error = "Username must be at least 4 characters.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Invalid email address.";
    } elseif ($phone !== null && !preg_match('/^\+?[0-9]{7,15}$/', $phone)) {
        $error = "Invalid phone number (use international format, e.g., +14735551234). Leave empty if not needed.";
    } elseif (strlen($password) < 12) {
        $error = "Password must be at least 12 characters.";
    } else {
        // Check existing email
        $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            $error = "An account with this email already exists. <a href='login.php'>Login</a> instead.";
        } else {
            // Check existing username
            $stmt = $conn->prepare("SELECT id FROM users WHERE username = ?");
            $stmt->bind_param("s", $username);
            $stmt->execute();
            if ($stmt->get_result()->num_rows > 0) {
                $error = "Username already taken. Please choose another.";
            } else {
                // Check existing phone only if provided
                if ($phone !== null) {
                    $stmt = $conn->prepare("SELECT id FROM users WHERE phone = ?");
                    $stmt->bind_param("s", $phone);
                    $stmt->execute();
                    if ($stmt->get_result()->num_rows > 0) {
                        $error = "Phone number already registered.";
                    }
                }
                if (empty($error)) {
                    // Create user (no phone verification required)
                    $hash = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $conn->prepare("INSERT INTO users (username, email, phone, password, email_verified) VALUES (?, ?, ?, ?, 0)");
                    $stmt->bind_param("ssss", $username, $email, $phone, $hash);
                    if ($stmt->execute()) {
                        $user_id = $conn->insert_id;

                        // Send email verification code
                        $email_code = rand(100000, 999999);
                        $expires = date('Y-m-d H:i:s', strtotime('+15 minutes'));
                        $stmt = $conn->prepare("INSERT INTO email_verifications (email, code, expires_at) VALUES (?, ?, ?)");
                        $stmt->bind_param("sss", $email, $email_code, $expires);
                        $stmt->execute();

                        $subject = "Verify Your Email - TAMCC Deli";
                        $body = "<h2>Email Verification</h2>
                                 <p>Your verification code is: <strong>$email_code</strong></p>
                                 <p>Enter this code on the verification page to activate your account.</p>
                                 <p>Code expires in 15 minutes.</p>";
                        sendEmail($email, $subject, $body);

                        $_SESSION['pending_verification_user_id'] = $user_id;
                        $_SESSION['pending_verification_email'] = $email;

                        header("Location: verify-account.php");
                        exit;
                    } else {
                        $error = "Registration failed. Please try again.";
                    }
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register | TAMCC Deli</title>
    <link rel="stylesheet" href="../assets/css/global.css">
    <style>
        .auth-card { max-width: 450px; margin: 0 auto; background: white; border-radius: var(--radius-lg); padding: 2rem; box-shadow: var(--shadow-lg); border-top: 4px solid var(--primary-600); }
        .brand-icon { text-align: center; font-size: 3rem; }
        .auth-card h2 { text-align: center; margin-bottom: 1rem; }
        .sub-title { text-align: center; font-size: 0.85rem; color: var(--neutral-600); margin-bottom: 1.5rem; }
        .form-group { margin-bottom: 1rem; position: relative; }
        .form-group label { display: block; margin-bottom: 0.5rem; font-weight: 600; }
        .form-group input { width: 100%; padding: 0.75rem; border: 1.5px solid var(--neutral-300); border-radius: var(--radius); font-size: 1rem; }
        .form-group input:focus { border-color: var(--primary-600); outline: none; box-shadow: 0 0 0 3px rgba(7,74,242,0.1); }
        .password-toggle { position: absolute; right: 12px; top: 42px; cursor: pointer; color: var(--neutral-600); font-size: 0.85rem; font-weight: 500; background: var(--neutral-100); padding: 0.2rem 0.5rem; border-radius: 20px; transition: all 0.2s; }
        .password-toggle:hover { background: var(--neutral-200); }
        .btn-block { width: 100%; padding: 0.75rem; font-size: 1.1rem; border-radius: 2rem; margin-top: 0.5rem; }
        .auth-footer { text-align: center; margin-top: 1.5rem; font-size: 0.9rem; }
        .auth-footer a { color: var(--primary-600); text-decoration: none; font-weight: 600; }
        .error-message { background: #fee2e2; color: #dc2626; padding: 0.75rem; border-radius: var(--radius); margin-bottom: 1rem; text-align: center; border-left: 3px solid #dc2626; }
        hr { margin: 1.5rem 0; border: none; border-top: 1px solid var(--neutral-200); }
        .optional-badge { font-size: 0.7rem; font-weight: normal; color: var(--neutral-500); margin-left: 0.5rem; }
    </style>
</head>
<body>
<div class="auth-container">
    <div class="auth-card">
        <div class="brand-icon">📝</div>
        <h2>Create an Account</h2>
        <div class="sub-title">Join TAMCC Deli – verify your email after registration</div>

        <?php if ($error): ?>
            <div class="error-message"><?= $error ?></div>
        <?php endif; ?>

        <form method="POST" id="registerForm">
            <input type="hidden" name="csrf_token" value="<?= generateToken() ?>">
            <div class="form-group">
                <label for="username">Username</label>
                <input type="text" id="username" name="username" value="<?= htmlspecialchars($prefill_username) ?>" required>
            </div>
            <div class="form-group">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" value="<?= htmlspecialchars($prefill_email) ?>" required>
            </div>
            <div class="form-group">
                <label for="phone">Phone Number <span class="optional-badge">(optional)</span></label>
                <input type="tel" id="phone" name="phone" value="<?= htmlspecialchars($prefill_phone) ?>" placeholder="+14735551234">
            </div>
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required>
                <span class="password-toggle" id="togglePwdBtn" onclick="togglePassword()">Show</span>
            </div>
            <button type="submit" class="btn btn-primary btn-block">Register & Verify Email</button>
        </form>
        <div class="auth-footer">
            Already have an account? <a href="login.php">Log in</a>
        </div>
    </div>
</div>

<script>
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
</script>
</body>
</html>