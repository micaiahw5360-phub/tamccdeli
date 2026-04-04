<?php
require __DIR__ . '/../middleware/auth_check.php';
require __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/kiosk.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/csrf.php';

$user_id = $_SESSION['user_id'];
$error = '';
$success = '';
$active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'profile';

// ---------- Helper: Get user's notification preferences ----------
function getUserNotifications($conn, $user_id) {
    $stmt = $conn->prepare("SELECT order_updates, promotions, newsletter FROM user_notifications WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $res = $stmt->get_result()->fetch_assoc();
    if (!$res) {
        $stmt2 = $conn->prepare("INSERT INTO user_notifications (user_id) VALUES (?)");
        $stmt2->bind_param("i", $user_id);
        $stmt2->execute();
        return ['order_updates' => 1, 'promotions' => 0, 'newsletter' => 1];
    }
    return $res;
}

// ---------- Handle POST actions ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!validateToken($_POST['csrf_token'])) die('Invalid CSRF token');

    // 1. Update Profile
    if ($_POST['action'] === 'update_profile') {
        $username = trim($_POST['username']);
        $email = trim($_POST['email']);
        $phone = trim($_POST['phone']);
        $student_id = trim($_POST['student_id']);
        $address = trim($_POST['address']);

        if (strlen($username) < 4) {
            $error = "Username must be at least 4 characters.";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = "Invalid email address.";
        } else {
            // Handle profile photo upload
            $profile_photo = null;
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
            if (!$error) {
                if ($profile_photo) {
                    $stmt = $conn->prepare("UPDATE users SET username=?, email=?, phone=?, student_id=?, address=?, profile_photo=? WHERE id=?");
                    $stmt->bind_param("ssssssi", $username, $email, $phone, $student_id, $address, $profile_photo, $user_id);
                } else {
                    $stmt = $conn->prepare("UPDATE users SET username=?, email=?, phone=?, student_id=?, address=? WHERE id=?");
                    $stmt->bind_param("sssssi", $username, $email, $phone, $student_id, $address, $user_id);
                }
                if ($stmt->execute()) {
                    $success = "Profile updated successfully.";
                    $_SESSION['username'] = $username;
                    if ($profile_photo) $_SESSION['profile_photo'] = $profile_photo;
                    regenerateToken();
                } else {
                    $error = "Database error: " . $conn->error;
                }
            }
        }
    }

    // 2. Change Password
    elseif ($_POST['action'] === 'change_password') {
        $current = $_POST['current_password'];
        $new = $_POST['new_password'];
        $confirm = $_POST['confirm_password'];

        $stmt = $conn->prepare("SELECT password FROM users WHERE id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $user_pass = $stmt->get_result()->fetch_assoc();

        if (!password_verify($current, $user_pass['password'])) {
            $error = "Current password is incorrect.";
        } elseif ($new !== $confirm) {
            $error = "New passwords do not match.";
        } elseif (strlen($new) < 12) {
            $error = "Password must be at least 12 characters.";
        } else {
            $hash = password_hash($new, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE users SET password=? WHERE id=?");
            $stmt->bind_param("si", $hash, $user_id);
            if ($stmt->execute()) {
                $success = "Password changed successfully.";
                regenerateToken();
            } else {
                $error = "Database error.";
            }
        }
    }

    // 3. Save Notification Preferences
    elseif ($_POST['action'] === 'save_notifications') {
        $order_updates = isset($_POST['order_updates']) ? 1 : 0;
        $promotions = isset($_POST['promotions']) ? 1 : 0;
        $newsletter = isset($_POST['newsletter']) ? 1 : 0;
        $stmt = $conn->prepare("INSERT INTO user_notifications (user_id, order_updates, promotions, newsletter) VALUES (?, ?, ?, ?)
                                ON DUPLICATE KEY UPDATE order_updates=?, promotions=?, newsletter=?");
        $stmt->bind_param("iiiiii", $user_id, $order_updates, $promotions, $newsletter, $order_updates, $promotions, $newsletter);
        if ($stmt->execute()) {
            $success = "Notification preferences saved.";
        } else {
            $error = "Failed to save preferences.";
        }
    }

    // Refresh page to show updated data
    header("Location: ?tab=" . $active_tab);
    exit;
}

// ---------- Fetch user data ----------
$stmt = $conn->prepare("SELECT username, email, phone, student_id, address, points, profile_photo, bio FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

// Wallet balance
$stmt = $conn->prepare("SELECT balance FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$balance = $stmt->get_result()->fetch_assoc()['balance'] ?? 0;

// Transaction history
$stmt = $conn->prepare("SELECT * FROM transactions WHERE user_id = ? ORDER BY created_at DESC LIMIT 20");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$transactions = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Saved cards (mock or from DB)
$cards = [];
// For demo, we'll show sample cards if table empty
$stmt = $conn->prepare("SELECT card_brand, last4, expiry_month, expiry_year FROM user_cards WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$cards = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
if (empty($cards)) {
    // Demo cards
    $cards = [
        ['card_brand' => 'Visa', 'last4' => '4242', 'expiry_month' => 12, 'expiry_year' => 2026],
        ['card_brand' => 'Mastercard', 'last4' => '8888', 'expiry_month' => 8, 'expiry_year' => 2027],
    ];
}

// Notification preferences
$notif = getUserNotifications($conn, $user_id);
$page_title = "My Account | TAMCC Deli";
include __DIR__ . '/../includes/header.php';
?>

<style>
    /* Dashboard styles – React-like */
    .dashboard-wrapper {
        background: var(--neutral-100);
        min-height: 100vh;
        padding: 2rem;
    }
    .max-w-4xl {
        max-width: 64rem;
        margin: 0 auto;
    }
    /* Tabs (same as shadcn/ui) */
    .tabs-list {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 0.5rem;
        background: var(--neutral-100);
        border-radius: 0.75rem;
        padding: 0.25rem;
        margin-bottom: 1.5rem;
    }
    .tabs-trigger {
        background: transparent;
        border: none;
        padding: 0.5rem 1rem;
        border-radius: 0.5rem;
        font-weight: 500;
        cursor: pointer;
        transition: all 0.2s;
        color: var(--neutral-600);
    }
    .tabs-trigger.active {
        background: white;
        color: var(--primary);
        box-shadow: var(--shadow-sm);
    }
    .card {
        background: white;
        border-radius: 1rem;
        box-shadow: var(--shadow);
        border: 1px solid var(--border);
        overflow: hidden;
    }
    .card-header {
        padding: 1.5rem 1.5rem 0 1.5rem;
    }
    .card-title {
        font-size: 1.25rem;
        font-weight: 700;
        margin-bottom: 0.25rem;
    }
    .card-description {
        font-size: 0.875rem;
        color: var(--muted-foreground);
    }
    .card-content {
        padding: 1.5rem;
    }
    .form-group {
        margin-bottom: 1rem;
    }
    .form-group label {
        display: block;
        font-weight: 500;
        margin-bottom: 0.25rem;
    }
    .form-group input, .form-group textarea {
        width: 100%;
        padding: 0.5rem 0.75rem;
        border: 1px solid var(--border);
        border-radius: 0.5rem;
        background: var(--input-background);
    }
    .input-icon {
        position: relative;
    }
    .input-icon input {
        padding-left: 2rem;
    }
    .input-icon svg {
        position: absolute;
        left: 0.75rem;
        top: 50%;
        transform: translateY(-50%);
        width: 1rem;
        height: 1rem;
        color: var(--muted-foreground);
    }
    .flex-between {
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    .btn {
        display: inline-block;
        background: var(--primary);
        color: white;
        padding: 0.5rem 1rem;
        border-radius: 2rem;
        border: none;
        cursor: pointer;
        transition: all 0.2s;
    }
    .btn:hover {
        background: var(--primary-700);
        transform: translateY(-2px);
    }
    .btn-outline {
        background: transparent;
        border: 1px solid var(--border);
        color: var(--foreground);
    }
    .btn-outline:hover {
        background: var(--neutral-100);
        transform: none;
    }
    .switch {
        position: relative;
        display: inline-block;
        width: 44px;
        height: 24px;
    }
    .switch input {
        opacity: 0;
        width: 0;
        height: 0;
    }
    .slider {
        position: absolute;
        cursor: pointer;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background-color: var(--switch-background);
        transition: 0.3s;
        border-radius: 24px;
    }
    .slider:before {
        position: absolute;
        content: "";
        height: 18px;
        width: 18px;
        left: 3px;
        bottom: 3px;
        background-color: white;
        transition: 0.3s;
        border-radius: 50%;
    }
    input:checked + .slider {
        background-color: var(--primary);
    }
    input:checked + .slider:before {
        transform: translateX(20px);
    }
    .credit-card-preview {
        width: 48px;
        height: 32px;
        border-radius: 6px;
        background: linear-gradient(135deg, #3b82f6, #8b5cf6);
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
    }
    @media (max-width: 768px) {
        .tabs-list {
            grid-template-columns: repeat(2, 1fr);
        }
        .dashboard-wrapper {
            padding: 1rem;
        }
    }
</style>

<div class="dashboard-wrapper">
    <div class="max-w-4xl">
        <div class="mb-8">
            <h1 class="text-3xl font-bold text-gray-900 mb-2">My Account</h1>
            <p class="text-gray-600">Manage your profile and preferences</p>
        </div>

        <!-- Tabs -->
        <div class="tabs-list" id="mainTabs">
            <button class="tabs-trigger <?= $active_tab === 'profile' ? 'active' : '' ?>" data-tab="profile">Profile</button>
            <button class="tabs-trigger <?= $active_tab === 'security' ? 'active' : '' ?>" data-tab="security">Security</button>
            <button class="tabs-trigger <?= $active_tab === 'notifications' ? 'active' : '' ?>" data-tab="notifications">Notifications</button>
            <button class="tabs-trigger <?= $active_tab === 'payment' ? 'active' : '' ?>" data-tab="payment">Payment</button>
        </div>

        <!-- Profile Tab -->
        <div id="profile-tab" class="tab-content" style="display: <?= $active_tab === 'profile' ? 'block' : 'none' ?>;">
            <div class="card">
                <div class="card-header">
                    <div class="card-title">Personal Information</div>
                    <div class="card-description">Update your personal details and student information</div>
                </div>
                <div class="card-content">
                    <?php if ($error && $_POST['action'] === 'update_profile') echo '<div class="error-message" style="margin-bottom:1rem;">' . $error . '</div>'; ?>
                    <?php if ($success && $_POST['action'] === 'update_profile') echo '<div class="success-message" style="margin-bottom:1rem;">' . $success . '</div>'; ?>
                    <form method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="csrf_token" value="<?= generateToken() ?>">
                        <input type="hidden" name="action" value="update_profile">

                        <div class="flex items-center gap-4 mb-6">
                            <?php if ($user['profile_photo']): ?>
                                <img src="<?= htmlspecialchars($user['profile_photo']) ?>" alt="Profile" class="w-20 h-20 rounded-full object-cover border-2 border-primary">
                            <?php else: ?>
                                <div class="w-20 h-20 rounded-full bg-primary flex items-center justify-center text-white text-2xl font-bold">
                                    <?= strtoupper(substr($user['username'], 0, 1)) ?>
                                </div>
                            <?php endif; ?>
                            <div>
                                <label class="btn btn-outline cursor-pointer inline-block">
                                    Change Photo
                                    <input type="file" name="profile_photo" accept="image/*" class="hidden" onchange="this.form.submit()">
                                </label>
                            </div>
                        </div>

                        <div class="grid gap-6">
                            <div class="form-group">
                                <label>Full Name</label>
                                <div class="input-icon">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" class="w-4 h-4"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" /></svg>
                                    <input type="text" name="username" value="<?= htmlspecialchars($user['username']) ?>" required>
                                </div>
                            </div>
                            <div class="form-group">
                                <label>Email Address</label>
                                <div class="input-icon">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" class="w-4 h-4"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" /></svg>
                                    <input type="email" name="email" value="<?= htmlspecialchars($user['email']) ?>" required>
                                </div>
                            </div>
                            <div class="form-group">
                                <label>Student ID</label>
                                <input type="text" name="student_id" value="<?= htmlspecialchars($user['student_id'] ?? '') ?>" placeholder="e.g., TC123456">
                            </div>
                            <div class="form-group">
                                <label>Phone Number</label>
                                <div class="input-icon">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" class="w-4 h-4"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" /></svg>
                                    <input type="tel" name="phone" value="<?= htmlspecialchars($user['phone'] ?? '') ?>" placeholder="+14735551234">
                                </div>
                            </div>
                            <div class="form-group">
                                <label>Campus Address</label>
                                <div class="input-icon">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" class="w-4 h-4"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" /></svg>
                                    <input type="text" name="address" value="<?= htmlspecialchars($user['address'] ?? 'TAMCC Campus, St. George\'s, Grenada') ?>">
                                </div>
                            </div>
                        </div>

                        <button type="submit" class="btn w-full mt-6">Save Changes</button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Security Tab -->
        <div id="security-tab" class="tab-content" style="display: <?= $active_tab === 'security' ? 'block' : 'none' ?>;">
            <div class="card">
                <div class="card-header">
                    <div class="card-title">Security Settings</div>
                    <div class="card-description">Manage your password and security preferences</div>
                </div>
                <div class="card-content">
                    <?php if ($error && $_POST['action'] === 'change_password') echo '<div class="error-message" style="margin-bottom:1rem;">' . $error . '</div>'; ?>
                    <?php if ($success && $_POST['action'] === 'change_password') echo '<div class="success-message" style="margin-bottom:1rem;">' . $success . '</div>'; ?>
                    <form method="POST">
                        <input type="hidden" name="csrf_token" value="<?= generateToken() ?>">
                        <input type="hidden" name="action" value="change_password">
                        <div class="form-group">
                            <label>Current Password</label>
                            <div class="input-icon">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" class="w-4 h-4"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" /></svg>
                                <input type="password" name="current_password" required>
                            </div>
                        </div>
                        <div class="form-group">
                            <label>New Password (min. 12 characters)</label>
                            <div class="input-icon">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" class="w-4 h-4"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" /></svg>
                                <input type="password" name="new_password" required>
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Confirm New Password</label>
                            <div class="input-icon">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" class="w-4 h-4"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" /></svg>
                                <input type="password" name="confirm_password" required>
                            </div>
                        </div>
                        <button type="submit" class="btn w-full mt-4">Change Password</button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Notifications Tab -->
        <div id="notifications-tab" class="tab-content" style="display: <?= $active_tab === 'notifications' ? 'block' : 'none' ?>;">
            <div class="card">
                <div class="card-header">
                    <div class="card-title">Notification Preferences</div>
                    <div class="card-description">Choose what notifications you want to receive</div>
                </div>
                <div class="card-content">
                    <?php if ($success && $_POST['action'] === 'save_notifications') echo '<div class="success-message" style="margin-bottom:1rem;">' . $success . '</div>'; ?>
                    <form method="POST">
                        <input type="hidden" name="csrf_token" value="<?= generateToken() ?>">
                        <input type="hidden" name="action" value="save_notifications">

                        <div class="space-y-6">
                            <div class="flex-between">
                                <div>
                                    <div class="flex items-center gap-2"><span>🔔</span> <label class="font-medium">Order Updates</label></div>
                                    <p class="text-sm text-gray-500">Receive notifications about your order status</p>
                                </div>
                                <label class="switch">
                                    <input type="checkbox" name="order_updates" <?= $notif['order_updates'] ? 'checked' : '' ?>>
                                    <span class="slider"></span>
                                </label>
                            </div>
                            <hr class="border-t">
                            <div class="flex-between">
                                <div>
                                    <div class="flex items-center gap-2"><span>🎉</span> <label class="font-medium">Promotions & Deals</label></div>
                                    <p class="text-sm text-gray-500">Get notified about special offers and discounts</p>
                                </div>
                                <label class="switch">
                                    <input type="checkbox" name="promotions" <?= $notif['promotions'] ? 'checked' : '' ?>>
                                    <span class="slider"></span>
                                </label>
                            </div>
                            <hr class="border-t">
                            <div class="flex-between">
                                <div>
                                    <div class="flex items-center gap-2"><span>📧</span> <label class="font-medium">Newsletter</label></div>
                                    <p class="text-sm text-gray-500">Receive our monthly newsletter with menu updates</p>
                                </div>
                                <label class="switch">
                                    <input type="checkbox" name="newsletter" <?= $notif['newsletter'] ? 'checked' : '' ?>>
                                    <span class="slider"></span>
                                </label>
                            </div>
                        </div>
                        <button type="submit" class="btn w-full mt-6">Save Preferences</button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Payment Tab -->
        <div id="payment-tab" class="tab-content" style="display: <?= $active_tab === 'payment' ? 'block' : 'none' ?>;">
            <div class="card">
                <div class="card-header">
                    <div class="card-title">Payment Methods</div>
                    <div class="card-description">Manage your saved payment methods</div>
                </div>
                <div class="card-content">
                    <!-- Saved Cards -->
                    <div class="space-y-4">
                        <?php foreach ($cards as $card): ?>
                            <div class="flex-between border rounded-lg p-4">
                                <div class="flex items-center gap-4">
                                    <div class="credit-card-preview">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="white" viewBox="0 0 24 24"><rect x="2" y="5" width="20" height="14" rx="2"/><line x1="2" y1="10" x2="22" y2="10"/></svg>
                                    </div>
                                    <div>
                                        <p class="font-medium"><?= htmlspecialchars($card['card_brand']) ?> •••• <?= $card['last4'] ?></p>
                                        <p class="text-sm text-gray-500">Expires <?= str_pad($card['expiry_month'],2,'0',STR_PAD_LEFT) ?>/<?= substr($card['expiry_year'], -2) ?></p>
                                    </div>
                                </div>
                                <button class="btn-outline text-sm py-1 px-3 rounded-full">Remove</button>
                            </div>
                        <?php endforeach; ?>
                        <button class="btn-outline w-full py-2 rounded-lg">+ Add Payment Method</button>
                    </div>

                    <hr class="my-6">

                    <!-- Wallet Balance -->
                    <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                        <h4 class="font-medium text-blue-900 mb-2">TAMCC Wallet Balance</h4>
                        <p class="text-2xl font-bold text-blue-900 mb-2">$<?= number_format($balance, 2) ?></p>
                        <a href="<?= normal_url('wallet.php') ?>" class="btn-outline text-sm py-1 px-3 rounded-full inline-block">Add Funds</a>
                    </div>

                    <hr class="my-6">

                    <!-- Transaction History -->
                    <div>
                        <h4 class="font-medium mb-3">Transaction History</h4>
                        <?php if (empty($transactions)): ?>
                            <p class="text-gray-500 text-sm">No transactions yet.</p>
                        <?php else: ?>
                            <div class="overflow-x-auto">
                                <table class="w-full text-sm">
                                    <thead class="text-gray-500 border-b">
                                        <tr><th class="text-left py-2">Date</th><th class="text-left">Description</th><th class="text-right">Amount</th></tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($transactions as $tx): ?>
                                            <tr>
                                                <td class="py-2"><?= date('M j, Y', strtotime($tx['created_at'])) ?></td>
                                                <td><?= htmlspecialchars($tx['description'] ?? ($tx['type'] === 'topup' ? 'Wallet Top‑up' : 'Order Payment')) ?></td>
                                                <td class="text-right <?= $tx['type'] === 'topup' ? 'text-green-600' : 'text-red-600' ?>">
                                                    <?= $tx['type'] === 'topup' ? '+' : '-' ?> $<?= number_format($tx['amount'], 2) ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    // Tab switching
    const tabs = document.querySelectorAll('.tabs-trigger');
    const contents = {
        profile: document.getElementById('profile-tab'),
        security: document.getElementById('security-tab'),
        notifications: document.getElementById('notifications-tab'),
        payment: document.getElementById('payment-tab')
    };
    tabs.forEach(btn => {
        btn.addEventListener('click', () => {
            const tab = btn.dataset.tab;
            Object.values(contents).forEach(c => c.style.display = 'none');
            contents[tab].style.display = 'block';
            tabs.forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            history.pushState({ tab }, '', `?tab=${tab}`);
        });
    });
    // If URL has tab, activate it
    const urlTab = new URLSearchParams(window.location.search).get('tab');
    if (urlTab && contents[urlTab]) {
        document.querySelector(`.tabs-trigger[data-tab="${urlTab}"]`).click();
    }
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>