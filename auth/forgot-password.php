<?php
require __DIR__ . '/../includes/session.php';
require __DIR__ . '/../config/database.php';
require __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/includes/kiosk.php';
require __DIR__ . '/../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateToken($_POST['csrf_token'])) {
        die('Invalid CSRF token');
    }

    $email = trim($_POST['email']);
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Invalid email address.';
    } else {
        // Check if email exists
        $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();

        if ($user) {
            // Generate reset token
            $token = bin2hex(random_bytes(32));
            $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));

            // Delete any old token for this user
            $delete = $conn->prepare("DELETE FROM password_resets WHERE user_id = ?");
            $delete->bind_param("i", $user['id']);
            $delete->execute();

            // Store new token
            $stmt = $conn->prepare("INSERT INTO password_resets (user_id, token, expires_at) VALUES (?, ?, ?)");
            $stmt->bind_param("iss", $user['id'], $token, $expires);
            $stmt->execute();

            // Send email
            $reset_link = kiosk_url('/auth/reset-password.php?token=' . $token);
            $mail = new PHPMailer(true);
            try {
                // Server settings
                $mail->isSMTP();
                $mail->Host       = getenv('SMTP_HOST');
                $mail->SMTPAuth   = true;
                $mail->Username   = getenv('SMTP_USER');
                $mail->Password   = getenv('SMTP_PASS');
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                $mail->Port       = getenv('SMTP_PORT') ?: 587;

                // Recipients
                $mail->setFrom('noreply@tamccdeli.com', 'TAMCC Deli');
                $mail->addAddress($email);

                // Content
                $mail->isHTML(true);
                $mail->Subject = 'Password Reset Request';
                $mail->Body    = "
                    <h2>Reset Your Password</h2>
                    <p>You requested a password reset. Click the link below to set a new password:</p>
                    <p><a href='$reset_link'>$reset_link</a></p>
                    <p>This link expires in 1 hour.</p>
                    <p>If you did not request this, ignore this email.</p>
                ";
                $mail->AltBody = "Reset your password: $reset_link";

                $mail->send();
                $success = 'Reset link sent to your email. Check your inbox.';
            } catch (Exception $e) {
                error_log("Mailer Error: " . $mail->ErrorInfo);
                $error = 'Could not send email. Please try again later.';
            }
        } else {
            // For security, still show success but don't actually send
            $success = 'If that email exists, we sent a reset link.';
        }
    }
}

$page_title = "Forgot Password";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password | TAMCC Deli</title>
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
            <div class="brand-icon">🔐</div>
            <h2>Forgot Password</h2>

            <?php if ($error): ?>
                <div class="error-message"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
            <?php if ($success): ?>
                <div class="success-message"><?= htmlspecialchars($success) ?></div>
            <?php endif; ?>

            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= generateToken() ?>">
                <div class="form-group">
                    <label for="email">Email Address</label>
                    <input type="email" id="email" name="email" required>
                </div>
                <button type="submit" class="btn btn-primary btn-block">Send Reset Link</button>
            </form>

            <div class="auth-footer">
                <a href="login.php">Back to Login</a>
            </div>
        </div>
    </div>
</body>
</html>