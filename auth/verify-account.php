<?php
require __DIR__ . '/../includes/session.php';
require __DIR__ . '/../config/database.php';
require __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/kiosk.php';

$user_id = $_SESSION['pending_verification_user_id'] ?? 0;
$email = $_SESSION['pending_verification_email'] ?? '';

if (!$user_id) {
    header('Location: register.php');
    exit;
}

$error = '';
$success = '';

$stmt = $conn->prepare("SELECT email_verified FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateToken($_POST['csrf_token'])) die('Invalid CSRF token');
    $code = trim($_POST['code']);
    $stmt = $conn->prepare("SELECT code, expires_at FROM email_verifications WHERE email = ? ORDER BY id DESC LIMIT 1");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    if ($row && $row['code'] == $code && strtotime($row['expires_at']) > time()) {
        $stmt = $conn->prepare("UPDATE users SET email_verified = 1 WHERE id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $_SESSION['user_id'] = $user_id;
        $_SESSION['role'] = 'customer';
        unset($_SESSION['pending_verification_user_id']);
        unset($_SESSION['pending_verification_email']);
        header("Location: ../index.php");
        exit;
    } else {
        $error = "Invalid or expired email verification code.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Verify Your Email</title>
    <link rel="stylesheet" href="../assets/css/global.css">
    <style>
        .auth-card { max-width:450px; margin:0 auto; background:white; border-radius:var(--radius-lg); padding:2rem; box-shadow:var(--shadow-lg); }
        .brand-icon { text-align:center; font-size:3rem; }
        .verification-box { border:1px solid var(--neutral-200); border-radius:var(--radius); padding:1rem; margin-bottom:1.5rem; }
        .btn-block { width:100%; padding:0.75rem; margin-top:1rem; }
        .error-message, .success-message { padding:0.75rem; border-radius:var(--radius); margin-bottom:1rem; text-align:center; }
        .error-message { background:#fee2e2; color:#dc2626; }
        .success-message { background:#dcfce7; color:#15803d; }
    </style>
</head>
<body>
<div class="auth-container">
    <div class="auth-card">
        <div class="brand-icon">✅</div>
        <h2>Verify Your Email</h2>
        <div class="verification-box">
            <h3>Email: <?= htmlspecialchars($email) ?></h3>
            <?php if ($user['email_verified']): ?>
                <p>✅ Verified</p>
            <?php else: ?>
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?= generateToken() ?>">
                    <div class="form-group">
                        <label>Verification Code</label>
                        <input type="text" name="code" placeholder="Enter 6-digit code" required>
                    </div>
                    <button type="submit" class="btn btn-primary btn-block">Verify Email</button>
                </form>
                <p><small>Didn't receive code? <a href="resend-email-code.php">Resend</a></small></p>
            <?php endif; ?>
        </div>
        <div class="auth-footer"><a href="register.php">← Back to Registration</a></div>
    </div>
</div>
</body>
</html>