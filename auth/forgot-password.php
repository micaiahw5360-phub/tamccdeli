<?php
require __DIR__ . '/includes/session.php';
require __DIR__ . '/../config/database.php';
require __DIR__ . '/../includes/csrf.php';
require __DIR__ . '/../includes/kiosk.php';
require __DIR__ . '/../vendor/autoload.php'; // for PHPMailer

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

            // Delete any old token for this user (using prepared statement)
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
                $mail->Host       = getenv('SMTP_HOST');       // e.g., smtp.gmail.com
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
include __DIR__ . '/../includes/header.php';
?>

<div class="auth-container">
    <div class="auth-card">
        <h2>Forgot Password</h2>
        <?php if ($error): ?>
            <div class="error-message"><?= $error ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="success-message"><?= $success ?></div>
        <?php endif; ?>
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?= generateToken() ?>">
            <div class="form-group">
                <label for="email">Email Address</label>
                <input type="email" id="email" name="email" required>
            </div>
            <button type="submit" class="btn btn-primary btn-block">Send Reset Link</button>
        </form>
        <p class="auth-footer"><a href="login.php">Back to Login</a></p>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>