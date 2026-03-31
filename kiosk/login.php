<?php
// Ensure session is started and kiosk mode is set
require __DIR__ . '/../includes/session.php';
require __DIR__ . '/../includes/kiosk.php';
require __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/auth_helpers.php';

// If already logged in, go to home
if (isset($_SESSION['user_id'])) {
    header('Location: ' . kiosk_url('/kiosk/home.php'));
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateToken($_POST['csrf_token'])) die('Invalid CSRF token');

    $username = trim($_POST['staff_name']);
    $password = $_POST['staff_password'];

    // Authenticate staff (admin or staff role)
    $stmt = $conn->prepare("SELECT id, password, role FROM users WHERE username = ? AND role IN ('admin', 'staff') AND is_active = 1");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();

    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['username'] = $username;
        header('Location: ' . kiosk_url('/kiosk/home.php'));
        exit;
    } else {
        $error = 'Invalid credentials.';
    }
}

$page_title = "Staff Login | TAMCC Deli Kiosk";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
    <title><?= $page_title ?></title>
    <link rel="stylesheet" href="/assets/css/kiosk.css">
</head>
<body>
    <div class="kiosk">
        <div class="screen">
            <div class="time"></div>
            <h1>Staff Login</h1>
            <?php if ($error): ?>
                <div class="error-message"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
            <form id="login-form" method="post">
                <input type="hidden" name="csrf_token" value="<?= generateToken() ?>">
                <div class="form-group">
                    <label class="form-label">Staff Name / ID</label>
                    <input type="text" id="staff-name" name="staff_name" class="form-input" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Password</label>
                    <input type="password" id="staff-password" name="staff_password" class="form-input" required>
                </div>
                <button type="submit" class="btn btn-primary btn-large">Login</button>
            </form>
        </div>
    </div>
    <script src="/assets/js/kiosk.js"></script>
    <script>
        // Override login form to use our PHP handler (already does)
        document.getElementById('login-form').addEventListener('submit', function(e) {
            // Already submitted to PHP
        });
    </script>
</body>
</html>