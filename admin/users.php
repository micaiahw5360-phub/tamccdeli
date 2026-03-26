<?php
require __DIR__ . '/../middleware/admin_check.php';
require __DIR__ . '/../config/database.php';
require __DIR__ . '/../includes/csrf.php';

// Handle role update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_role'])) {
    if (!validateToken($_POST['csrf_token'])) {
        die('Invalid CSRF token');
    }
    $user_id = intval($_POST['user_id']);
    $new_role = $_POST['role'];
    $stmt = $conn->prepare("UPDATE users SET role = ? WHERE id = ?");
    $stmt->bind_param("si", $new_role, $user_id);
    $stmt->execute();
    
    $_SESSION['flash_message'] = "User role updated successfully";
    $_SESSION['flash_type'] = 'success';
    
    $redirect = "users.php";
    if (isset($_SESSION['kiosk_mode']) && $_SESSION['kiosk_mode']) {
        $redirect .= '?kiosk=1';
    }
    header("Location: $redirect");
    exit;
}

// Handle toggle active (disable/enable)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_active'])) {
    if (!validateToken($_POST['csrf_token'])) {
        die('Invalid CSRF token');
    }
    $user_id = intval($_POST['user_id']);
    $current_active = intval($_POST['current_active']);
    $new_active = $current_active ? 0 : 1;
    $stmt = $conn->prepare("UPDATE users SET is_active = ? WHERE id = ?");
    $stmt->bind_param("ii", $new_active, $user_id);
    $stmt->execute();
    
    $_SESSION['flash_message'] = "User status updated successfully";
    $_SESSION['flash_type'] = 'success';
    
    $redirect = "users.php";
    if (isset($_SESSION['kiosk_mode']) && $_SESSION['kiosk_mode']) {
        $redirect .= '?kiosk=1';
    }
    header("Location: $redirect");
    exit;
}

// Handle delete user
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_user'])) {
    if (!validateToken($_POST['csrf_token'])) {
        die('Invalid CSRF token');
    }
    $user_id = intval($_POST['user_id']);

    $conn->begin_transaction();
    try {
        $stmt = $conn->prepare("UPDATE transactions SET user_id = NULL WHERE user_id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();

        $stmt2 = $conn->prepare("UPDATE orders SET user_id = NULL WHERE user_id = ?");
        $stmt2->bind_param("i", $user_id);
        $stmt2->execute();

        $stmt3 = $conn->prepare("DELETE FROM users WHERE id = ?");
        $stmt3->bind_param("i", $user_id);
        $stmt3->execute();

        $conn->commit();
        $_SESSION['flash_message'] = "User deleted successfully";
        $_SESSION['flash_type'] = 'success';
    } catch (Exception $e) {
        $conn->rollback();
        error_log("User deletion failed: " . $e->getMessage());
        $_SESSION['flash_message'] = "Could not delete user. Please try again.";
        $_SESSION['flash_type'] = 'error';
    }
    
    $redirect = "users.php";
    if (isset($_SESSION['kiosk_mode']) && $_SESSION['kiosk_mode']) {
        $redirect .= '?kiosk=1';
    }
    header("Location: $redirect");
    exit;
}

$users = $conn->query("SELECT id, username, email, role, is_active, created_at FROM users ORDER BY created_at DESC")->fetch_all(MYSQLI_ASSOC);

$page_title = "Manage Users";
include __DIR__ . '/../includes/header.php';
?>

<div class="dashboard-wrapper">
    <div class="sidebar">
        <h2>⚙️ Admin Panel</h2>
        <ul>
            <li><a href="<?= normal_url('index.php') ?>">Dashboard</a></li>
            <li><a href="<?= normal_url('menu/index.php') ?>">Manage Menu</a></li>
            <li><a href="<?= normal_url('orders.php') ?>">Manage Orders</a></li>
            <li><a href="<?= normal_url('users.php') ?>" class="active">Manage Users</a></li>
            <li><a href="<?= normal_url('../staff/orders.php') ?>">Staff View</a></li>
            <li><a href="<?= kiosk_url('../menu.php') ?>">View Site</a></li>
            <li><a href="<?= normal_url('../auth/logout.php') ?>">Logout</a></li>
        </ul>
    </div>
    <div class="main-content admin-panel">
        <h1>Manage Users</h1>

        <?php if (isset($_SESSION['flash_message'])): ?>
            <div id="flash-message" data-message="<?= htmlspecialchars($_SESSION['flash_message']) ?>" data-type="<?= $_SESSION['flash_type'] ?>"></div>
            <?php unset($_SESSION['flash_message'], $_SESSION['flash_type']); ?>
        <?php endif; ?>

        <div class="card">
            <div class="table-responsive">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Username</th>
                            <th>Email</th>
                            <th>Role</th>
                            <th>Active</th>
                            <th>Registered</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $user): ?>
                            <tr>
                                <td><?= $user['id'] ?></td>
                                <td><?= htmlspecialchars($user['username']) ?></td>
                                <td><?= htmlspecialchars($user['email']) ?></td>
                                <td>
                                    <form method="post" class="inline-form">
                                        <input type="hidden" name="csrf_token" value="<?= generateToken() ?>">
                                        <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                        <select name="role">
                                            <option value="customer" <?= $user['role'] === 'customer' ? 'selected' : '' ?>>Customer</option>
                                            <option value="staff" <?= $user['role'] === 'staff' ? 'selected' : '' ?>>Staff</option>
                                            <option value="admin" <?= $user['role'] === 'admin' ? 'selected' : '' ?>>Admin</option>
                                        </select>
                                        <button type="submit" name="update_role" class="btn-small">Update</button>
                                    </form>
                                </td>
                                <td><?= $user['is_active'] ? 'Yes' : 'No' ?></td>
                                <td><?= date('M j, Y', strtotime($user['created_at'])) ?></td>
                                <td>
                                    <form method="post" class="inline-form" style="display:inline;">
                                        <input type="hidden" name="csrf_token" value="<?= generateToken() ?>">
                                        <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                        <input type="hidden" name="current_active" value="<?= $user['is_active'] ?>">
                                        <button type="submit" name="toggle_active" class="btn-small <?= $user['is_active'] ? 'btn-warning' : 'btn-success' ?>">
                                            <?= $user['is_active'] ? 'Disable' : 'Enable' ?>
                                        </button>
                                    </form>
                                    <button class="btn-small btn-danger" data-action="delete" data-user-id="<?= $user['id'] ?>" data-csrf="<?= generateToken() ?>">Delete</button>
                                    <form id="delete-form-<?= $user['id'] ?>" method="post" style="display:none;">
                                        <input type="hidden" name="csrf_token" value="<?= generateToken() ?>">
                                        <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                        <input type="hidden" name="delete_user" value="1">
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const deleteBtns = document.querySelectorAll('[data-action="delete"]');
    deleteBtns.forEach(btn => {
        btn.addEventListener('click', async (e) => {
            e.preventDefault();
            const userId = btn.dataset.userId;
            const confirmed = await showConfirmModal({
                message: `Are you sure you want to delete this user? This action cannot be undone.`
            });
            if (confirmed) {
                document.getElementById(`delete-form-${userId}`).submit();
            }
        });
    });
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>