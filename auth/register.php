<?php
require __DIR__ . '/../includes/session.php';
require __DIR__ . '/../config/database.php';
require __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/kiosk.php';
require_once __DIR__ . '/../includes/mail.php';
require_once __DIR__ . '/../includes/firebase.php';

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
    $phone = preg_replace('/[^0-9+]/', '', trim($_POST['phone']));
    $password = $_POST['password'];
    $firebase_id_token = $_POST['firebase_id_token'] ?? '';
    $phone_verified_flag = $_POST['phone_verified'] ?? '0';

    // Validate input
    if (strlen($username) < 4) {
        $error = "Username must be at least 4 characters.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Invalid email address.";
    } elseif (!preg_match('/^\+?[0-9]{7,15}$/', $phone)) {
        $error = "Invalid phone number (use international format, e.g., +14735551234).";
    } elseif (strlen($password) < 12) {
        $error = "Password must be at least 12 characters.";
    } elseif ($phone_verified_flag !== '1' || empty($firebase_id_token)) {
        $error = "Please verify your phone number first.";
    } else {
        // Verify Firebase token
        $verification = FirebaseAuth::verifyIdToken($firebase_id_token);
        if (!$verification['success'] || $verification['phone_number'] !== $phone) {
            $error = "Phone verification failed. Please try again.";
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
                    // Check existing phone
                    $stmt = $conn->prepare("SELECT id FROM users WHERE phone = ?");
                    $stmt->bind_param("s", $phone);
                    $stmt->execute();
                    if ($stmt->get_result()->num_rows > 0) {
                        $error = "Phone number already registered.";
                    } else {
                        // Create user (phone verified via Firebase)
                        $hash = password_hash($password, PASSWORD_DEFAULT);
                        $firebase_uid = $verification['uid'];
                        $stmt = $conn->prepare("INSERT INTO users (username, email, phone, password, firebase_uid, phone_verified, email_verified) VALUES (?, ?, ?, ?, ?, 1, 0)");
                        $stmt->bind_param("sssss", $username, $email, $phone, $hash, $firebase_uid);
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
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register | TAMCC Deli</title>
    <link rel="stylesheet" href="../assets/css/global.css">
    <script src="https://www.gstatic.com/firebasejs/10.7.1/firebase-app-compat.js"></script>
    <script src="https://www.gstatic.com/firebasejs/10.7.1/firebase-auth-compat.js"></script>
    <style>
        .auth-card { max-width: 450px; margin: 0 auto; background: white; border-radius: var(--radius-lg); padding: 2rem; box-shadow: var(--shadow-lg); border-top: 4px solid var(--primary-600); }
        .brand-icon { text-align: center; font-size: 3rem; }
        .auth-card h2 { text-align: center; margin-bottom: 1rem; }
        .sub-title { text-align: center; font-size: 0.85rem; color: var(--neutral-600); margin-bottom: 1.5rem; }
        .form-group { margin-bottom: 1rem; position: relative; }
        .form-group label { display: block; margin-bottom: 0.5rem; font-weight: 600; }
        .form-group input { width: 100%; padding: 0.75rem; border: 1.5px solid var(--neutral-300); border-radius: var(--radius); font-size: 1rem; }
        .form-group input:focus { border-color: var(--primary-600); outline: none; box-shadow: 0 0 0 3px rgba(7,74,242,0.1); }
        .password-toggle { position: absolute; right: 12px; top: 42px; cursor: pointer; color: var(--neutral-500); }
        .btn-block { width: 100%; padding: 0.75rem; font-size: 1.1rem; border-radius: 2rem; margin-top: 0.5rem; }
        .btn-secondary { background: var(--neutral-600); }
        .phone-verification { background: var(--neutral-50); padding: 1rem; border-radius: var(--radius); margin-bottom: 1rem; }
        .verification-status { margin-top: 0.5rem; font-size: 0.85rem; }
        .verification-status.verified { color: var(--success); }
        .verification-status.pending { color: var(--warning); }
        .auth-footer { text-align: center; margin-top: 1.5rem; font-size: 0.9rem; }
        .auth-footer a { color: var(--primary-600); text-decoration: none; font-weight: 600; }
        .error-message { background: #fee2e2; color: #dc2626; padding: 0.75rem; border-radius: var(--radius); margin-bottom: 1rem; text-align: center; border-left: 3px solid #dc2626; }
        hr { margin: 1.5rem 0; border: none; border-top: 1px solid var(--neutral-200); }
    </style>
</head>
<body>
<div class="auth-container">
    <div class="auth-card">
        <div class="brand-icon">📝</div>
        <h2>Create an Account</h2>
        <div class="sub-title">Join TAMCC Deli – verify your email and phone</div>

        <?php if ($error): ?>
            <div class="error-message"><?= $error ?></div>
        <?php endif; ?>

        <form method="POST" id="registerForm">
            <input type="hidden" name="csrf_token" value="<?= generateToken() ?>">
            <input type="hidden" name="firebase_id_token" id="firebase_id_token" value="">
            <input type="hidden" name="phone_verified" id="phone_verified" value="0">
            <div class="form-group">
                <label for="username">Username</label>
                <input type="text" id="username" name="username" value="<?= htmlspecialchars($prefill_username) ?>" required>
            </div>
            <div class="form-group">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" value="<?= htmlspecialchars($prefill_email) ?>" required>
            </div>
            
            <!-- Phone verification section -->
            <div class="phone-verification">
                <div class="form-group">
                    <label for="phone">Phone Number (with country code)</label>
                    <input type="tel" id="phone" name="phone" value="<?= htmlspecialchars($prefill_phone) ?>" placeholder="+14735551234" required>
                </div>
                <button type="button" id="sendSmsBtn" class="btn btn-secondary btn-block">Send Verification Code</button>
                <div id="codeSection" style="display:none;">
                    <div class="form-group" style="margin-top: 1rem;">
                        <label for="smsCode">Verification Code</label>
                        <input type="text" id="smsCode" placeholder="Enter 6-digit code">
                    </div>
                    <button type="button" id="verifyCodeBtn" class="btn btn-primary btn-block">Verify Code</button>
                </div>
                <div id="verification-status" class="verification-status" style="display:none;"></div>
            </div>
            
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required>
                <span class="password-toggle" onclick="togglePassword('password')">👁️</span>
            </div>
            <button type="submit" class="btn btn-primary btn-block" id="submitBtn" disabled>Register & Verify</button>
        </form>
        <div class="auth-footer">
            Already have an account? <a href="login.php">Log in</a>
        </div>
    </div>
</div>

<script>
    // Replace with your Firebase config
    const firebaseConfig = {
        apiKey: "YOUR_API_KEY",
        authDomain: "YOUR_AUTH_DOMAIN",
        projectId: "YOUR_PROJECT_ID",
        storageBucket: "YOUR_STORAGE_BUCKET",
        messagingSenderId: "YOUR_MESSAGING_SENDER_ID",
        appId: "YOUR_APP_ID"
    };
    firebase.initializeApp(firebaseConfig);
    const auth = firebase.auth();
    let confirmationResult = null;

    function togglePassword(id) {
        const field = document.getElementById(id);
        field.type = field.type === 'password' ? 'text' : 'password';
    }

    document.getElementById('sendSmsBtn').addEventListener('click', async function() {
        const phone = document.getElementById('phone').value.trim();
        if (!phone) { alert('Please enter phone number'); return; }
        if (!phone.match(/^\+?[0-9]{7,15}$/)) {
            alert('Use international format, e.g., +14735551234');
            return;
        }
        const btn = this;
        btn.disabled = true;
        btn.textContent = 'Sending...';
        try {
            const appVerifier = new firebase.auth.RecaptchaVerifier('sendSmsBtn', { size: 'invisible' });
            confirmationResult = await auth.signInWithPhoneNumber(phone, appVerifier);
            document.getElementById('codeSection').style.display = 'block';
            document.getElementById('verification-status').style.display = 'block';
            document.getElementById('verification-status').textContent = 'Code sent! Check your SMS.';
            document.getElementById('verification-status').className = 'verification-status pending';
        } catch (error) {
            alert('Failed to send code: ' + error.message);
        } finally {
            btn.disabled = false;
            btn.textContent = 'Send Verification Code';
        }
    });

    document.getElementById('verifyCodeBtn').addEventListener('click', async function() {
        const code = document.getElementById('smsCode').value.trim();
        if (!code) { alert('Please enter verification code'); return; }
        if (!confirmationResult) { alert('Request a code first'); return; }
        const btn = this;
        btn.disabled = true;
        btn.textContent = 'Verifying...';
        try {
            const result = await confirmationResult.confirm(code);
            const idToken = await result.user.getIdToken();
            document.getElementById('firebase_id_token').value = idToken;
            document.getElementById('phone_verified').value = '1';
            document.getElementById('submitBtn').disabled = false;
            document.getElementById('verification-status').textContent = '✓ Phone verified!';
            document.getElementById('verification-status').className = 'verification-status verified';
        } catch (error) {
            alert('Invalid code: ' + error.message);
            document.getElementById('phone_verified').value = '0';
            document.getElementById('submitBtn').disabled = true;
            document.getElementById('verification-status').textContent = 'Verification failed. Try again.';
        } finally {
            btn.disabled = false;
            btn.textContent = 'Verify Code';
        }
    });
</script>
</body>
</html>