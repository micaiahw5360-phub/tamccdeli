<?php
require __DIR__ . '/../middleware/auth_check.php';
require __DIR__ . '/../config/database.php';
require __DIR__ . '/../includes/csrf.php';
require __DIR__ . '/../includes/kiosk.php';

$user_id = $_SESSION['user_id'];
$error = '';
$success = '';

// Fetch current user data
$stmt = $conn->prepare("SELECT username, email FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateToken($_POST['csrf_token'])) {
        die('Invalid CSRF token');
    }

    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];

    if (strlen($username) < 4) {
        $error = "Username must be at least 4 characters.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Invalid email address.";
    } else {
        try {
            if (!empty($new_password)) {
                if ($new_password !== $confirm_password) {
                    $error = "New passwords do not match.";
                } elseif (strlen($new_password) < 12) {
                    $error = "Password must be at least 12 characters.";
                } else {
                    // Verify current password
                    $stmt = $conn->prepare("SELECT password FROM users WHERE id = ?");
                    $stmt->bind_param("i", $user_id);
                    $stmt->execute();
                    $result = $stmt->get_result()->fetch_assoc();
                    if (!password_verify($current_password, $result['password'])) {
                        $error = "Current password is incorrect.";
                    } else {
                        $hash = password_hash($new_password, PASSWORD_DEFAULT);
                        $stmt = $conn->prepare("UPDATE users SET username=?, email=?, password=? WHERE id=?");
                        $stmt->bind_param("sssi", $username, $email, $hash, $user_id);
                        if ($stmt->execute()) {
                            $success = "Profile updated successfully.";
                            $_SESSION['username'] = $username;
                        } else {
                            $error = "Database error: " . $conn->error;
                        }
                    }
                }
            } else {
                $stmt = $conn->prepare("UPDATE users SET username=?, email=? WHERE id=?");
                $stmt->bind_param("ssi", $username, $email, $user_id);
                if ($stmt->execute()) {
                    $success = "Profile updated successfully.";
                    $_SESSION['username'] = $username;
                } else {
                    $error = "Database error: " . $conn->error;
                }
            }
        } catch (Exception $e) {
            $error = "An error occurred. Please try again.";
            error_log("Profile update error: " . $e->getMessage());
        }
    }
    // Refresh user data
    $stmt = $conn->prepare("SELECT username, email FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Profile | TAMCC Deli</title>
    <link rel="stylesheet" href="../assets/css/global.css">
</head>
<body>
<div class="dashboard-wrapper">
    <div class="sidebar">
        <h2>🍽️ TAMCC Deli</h2>
        <ul>
            <li><a href="<?= normal_url('index.php') ?>">Dashboard</a></li>
            <li><a href="<?= normal_url('orders.php') ?>">My Orders</a></li>
            <li><a href="<?= normal_url('payments.php') ?>">Payments</a></li>
            <li><a href="<?= normal_url('profile.php') ?>" class="active">Profile</a></li>
            <li><a href="<?= kiosk_url('../menu.php') ?>">View Menu</a></li>
            <li><a href="<?= normal_url('../auth/logout.php') ?>">Logout</a></li>
        </ul>
    </div>
    <div class="main-content">
        <h1>My Profile</h1>
        <div class="card">
            <?php if ($error): ?><div class="error-message"><?= $error ?></div><?php endif; ?>
            <?php if ($success): ?><div class="success-message"><?= $success ?></div><?php endif; ?>
            <form method="POST" class="profile-form">
                <input type="hidden" name="csrf_token" value="<?= generateToken() ?>">
                <div class="form-group">
                    <label for="username">Username</label>
                    <input type="text" id="username" name="username" value="<?= htmlspecialchars($user['username']) ?>" required>
                </div>
                <div class="form-group">
                    <label for="email">Email</label>
                    <input type="email" id="email" name="email" value="<?= htmlspecialchars($user['email']) ?>" required>
                </div>
                <hr>
                <h3>Change Password (leave blank to keep current)</h3>
                <div class="form-group">
                    <label for="current_password">Current Password</label>
                    <input type="password" id="current_password" name="current_password">
                </div>
                <div class="form-group">
                    <label for="new_password">New Password</label>
                    <input type="password" id="new_password" name="new_password">
                </div>
                <div class="form-group">
                    <label for="confirm_password">Confirm New Password</label>
                    <input type="password" id="confirm_password" name="confirm_password">
                </div>
                <button type="submit" class="btn btn-primary">Update Profile</button>
            </form>
        </div>
    </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
</body>
</html>