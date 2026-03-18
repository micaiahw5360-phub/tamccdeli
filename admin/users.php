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
    header("Location: users.php");
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
            <li><a href="index.php">Dashboard</a></li>
            <li><a href="menu/index.php">Manage Menu</a></li>
            <li><a href="orders.php">Manage Orders</a></li>
            <li><a href="users.php" class="active">Manage Users</a></li>
            <li><a href="../staff/orders.php">Staff View</a></li>
            <li><a href="../menu.php">View Site</a></li>
            <li><a href="../auth/logout.php">Logout</a></li>
        </ul>
    </div>
    <div class="main-content admin-panel">
        <h1>Manage Users</h1>

        <div class="card">
            <table>
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
                            <!-- Future: disable/delete user -->
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>