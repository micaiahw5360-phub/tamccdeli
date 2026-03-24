<?php
require __DIR__ . '/../middleware/auth_check.php';
require __DIR__ . '/../config/database.php';
require __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/kiosk.php';
require __DIR__ . '/../includes/functions.php';

$user_id = $_SESSION['user_id'];
$error = '';
$success = '';

// Fetch current user data
$stmt = $conn->prepare("SELECT username, email, profile_photo, bio FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateToken($_POST['csrf_token'])) {
        die('Invalid CSRF token');
    }

    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $bio = trim($_POST['bio']);
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];

    if (strlen($username) < 4) {
        $error = "Username must be at least 4 characters.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Invalid email address.";
    } else {
        try {
            // Handle profile photo upload
            $profile_photo = $user['profile_photo']; // keep current by default
            if (isset($_FILES['profile_photo']) && $_FILES['profile_photo']['error'] === UPLOAD_ERR_OK) {
                $allowed = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
                $file_type = mime_content_type($_FILES['profile_photo']['tmp_name']);
                if (!in_array($file_type, $allowed)) {
                    $error = "Only JPG, PNG, GIF, and WEBP images are allowed.";
                } else {
                    $upload_dir = __DIR__ . '/../uploads/profile/';
                    if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
                    $ext = pathinfo($_FILES['profile_photo']['name'], PATHINFO_EXTENSION);
                    $filename = 'user_' . $user_id . '_' . time() . '.' . $ext;
                    $target = $upload_dir . $filename;
                    if (move_uploaded_file($_FILES['profile_photo']['tmp_name'], $target)) {
                        $profile_photo = '/uploads/profile/' . $filename;
                    } else {
                        $error = "Failed to upload image.";
                    }
                }
            }

            // Update user data
            if (!empty($new_password)) {
                if ($new_password !== $confirm_password) {
                    $error = "New passwords do not match.";
                } elseif (strlen($new_password) < 12) {
                    $error = "Password must be at least 12 characters.";
                } else {
                    $stmt = $conn->prepare("SELECT password FROM users WHERE id = ?");
                    $stmt->bind_param("i", $user_id);
                    $stmt->execute();
                    $result = $stmt->get_result()->fetch_assoc();
                    if (!password_verify($current_password, $result['password'])) {
                        $error = "Current password is incorrect.";
                    } else {
                        $hash = password_hash($new_password, PASSWORD_DEFAULT);
                        $stmt = $conn->prepare("UPDATE users SET username=?, email=?, bio=?, profile_photo=?, password=? WHERE id=?");
                        $stmt->bind_param("sssssi", $username, $email, $bio, $profile_photo, $hash, $user_id);
                        if ($stmt->execute()) {
                            $success = "Profile updated successfully.";
                            $_SESSION['username'] = $username;
                            $_SESSION['profile_photo'] = $profile_photo;
                            regenerateToken(); // new CSRF token after important change
                        } else {
                            $error = "Database error: " . $conn->error;
                        }
                    }
                }
            } else {
                $stmt = $conn->prepare("UPDATE users SET username=?, email=?, bio=?, profile_photo=? WHERE id=?");
                $stmt->bind_param("ssssi", $username, $email, $bio, $profile_photo, $user_id);
                if ($stmt->execute()) {
                    $success = "Profile updated successfully.";
                    $_SESSION['username'] = $username;
                    $_SESSION['profile_photo'] = $profile_photo;
                    regenerateToken();
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
    $stmt = $conn->prepare("SELECT username, email, profile_photo, bio FROM users WHERE id = ?");
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
    <style>
        .dashboard-wrapper { background: var(--neutral-100); }
        .sidebar a:hover { background: var(--primary-600); transform: translateX(4px); }
        .card { transition: transform 0.2s, box-shadow 0.2s; }
        .card:hover { transform: translateY(-2px); box-shadow: var(--shadow-md); }
        .profile-form .btn {
            width: 100%;
            margin-top: 1rem;
            padding: 0.75rem;
            font-size: 1rem;
        }
        .profile-photo-preview {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            object-fit: cover;
            margin: 0 auto 1rem;
            display: block;
            border: 3px solid var(--primary-600);
        }
        .profile-photo-placeholder {
            width: 120px;
            height: 120px;
            background: var(--neutral-200);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 3rem;
            margin: 0 auto 1rem;
            border: 3px solid var(--primary-600);
        }
        @media (max-width: 768px) {
            .profile-photo-preview, .profile-photo-placeholder {
                width: 100px;
                height: 100px;
            }
        }
    </style>
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
            <form method="POST" class="profile-form" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?= generateToken() ?>">
                
                <!-- Profile Photo Preview -->
                <div style="text-align: center;">
                    <?php if ($user['profile_photo']): ?>
                        <img src="<?= htmlspecialchars($user['profile_photo']) ?>" alt="Profile Photo" class="profile-photo-preview" id="profile-preview">
                    <?php else: ?>
                        <div class="profile-photo-placeholder" id="profile-preview-placeholder">👤</div>
                        <img style="display:none;" id="profile-preview" class="profile-photo-preview">
                    <?php endif; ?>
                    <div class="form-group">
                        <label for="profile_photo">Change Profile Photo</label>
                        <input type="file" id="profile_photo" name="profile_photo" accept="image/*" onchange="previewPhoto(event)">
                        <small class="small-note">JPG, PNG, GIF, WEBP up to 2MB</small>
                    </div>
                </div>

                <div class="form-group">
                    <label for="username">Username</label>
                    <input type="text" id="username" name="username" value="<?= htmlspecialchars($user['username']) ?>" required>
                </div>
                <div class="form-group">
                    <label for="email">Email</label>
                    <input type="email" id="email" name="email" value="<?= htmlspecialchars($user['email']) ?>" required>
                </div>
                <div class="form-group">
                    <label for="bio">Bio</label>
                    <textarea id="bio" name="bio" rows="3"><?= htmlspecialchars($user['bio'] ?? '') ?></textarea>
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
<script>
function previewPhoto(event) {
    const file = event.target.files[0];
    if (file) {
        const reader = new FileReader();
        reader.onload = function(e) {
            const previewImg = document.getElementById('profile-preview');
            const placeholder = document.getElementById('profile-preview-placeholder');
            if (placeholder) placeholder.style.display = 'none';
            previewImg.src = e.target.result;
            previewImg.style.display = 'block';
        }
        reader.readAsDataURL(file);
    }
}
</script>
<?php include __DIR__ . '/../includes/footer.php'; ?>
</body>
</html>