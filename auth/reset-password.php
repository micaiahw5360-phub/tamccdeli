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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password | TAMCC Deli</title>
    <link rel="stylesheet" href="../assets/css/global.css">
    <style>
        /* Override to ensure full‑width auth container without navbar interference */
        body {
            margin: 0;
            padding: 0;
            background: linear-gradient(135deg, var(--neutral-50) 0%, var(--neutral-100) 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .auth-container {
            width: 100%;
            padding: 2rem;
        }
        .auth-card {
            max-width: 420px;
            margin: 0 auto;
            background: white;
            border-radius: var(--radius-lg);
            padding: 2rem;
            box-shadow: var(--shadow-lg);
            border-top: 4px solid var(--primary-600);
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
        .btn-block {
            width: 100%;
            padding: 0.75rem;
            font-size: 1.1rem;
            border-radius: 2rem;
            font-weight: 600;
            margin-top: 0.5rem;
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
        .error-message, .success-message {
            padding: 0.75rem;
            border-radius: var(--radius);
            margin-bottom: 1rem;
            text-align: center;
            border-left: 3px solid;
        }
        .error-message {
            background: #fee2e2;
            color: #dc2626;
            border-left-color: #dc2626;
        }
        .success-message {
            background: #dcfce7;
            color: #15803d;
            border-left-color: #15803d;
        }
    </style>
</head>
<body>
    <div class="auth-container">
        <div class="auth-card">
            <div class="brand-icon">🔑</div>
            <h2>Reset Password</h2>

            <?php if ($error): ?>
                <div class="error-message"><?= htmlspecialchars($error) ?></div>
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

            <div class="auth-footer">
                <a href="login.php">Back to Login</a>
            </div>
        </div>
    </div>
</body>
</html>