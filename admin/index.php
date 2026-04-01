<?php
require __DIR__ . '/../middleware/admin_check.php';
require __DIR__ . '/../config/database.php';
require __DIR__ . '/../includes/csrf.php';
require __DIR__ . '/../includes/kiosk.php';

$action = $_GET['action'] ?? 'dashboard';
$error = '';
$success = '';

// ======================== Handle POST actions ========================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateToken($_POST['csrf_token'])) die('Invalid CSRF token');

    // ---- Order status update ----
    if (isset($_POST['update_status'])) {
        $order_id = intval($_POST['order_id']);
        $new_status = $_POST['status'];
        $staff_id = $_POST['staff_id'] ?? null;

        if ($new_status === 'processing' && $staff_id) {
            $stmt = $conn->prepare("UPDATE orders SET status = ?, staff_id = ? WHERE id = ?");
            $stmt->bind_param("sii", $new_status, $staff_id, $order_id);
        } else {
            $stmt = $conn->prepare("UPDATE orders SET status = ? WHERE id = ?");
            $stmt->bind_param("si", $new_status, $order_id);
        }
        $stmt->execute();
        $_SESSION['flash_message'] = "Order #$order_id status updated to " . ucfirst($new_status);
        $_SESSION['flash_type'] = 'success';
        header("Location: ?action=orders" . (isset($_SESSION['kiosk_mode']) && $_SESSION['kiosk_mode'] ? '&kiosk=1' : ''));
        exit;
    }

    // ---- User role update ----
    if (isset($_POST['update_role'])) {
        $user_id = intval($_POST['user_id']);
        $new_role = $_POST['role'];
        $stmt = $conn->prepare("UPDATE users SET role = ? WHERE id = ?");
        $stmt->bind_param("si", $new_role, $user_id);
        $stmt->execute();
        $_SESSION['flash_message'] = "User role updated successfully";
        $_SESSION['flash_type'] = 'success';
        header("Location: ?action=users" . (isset($_SESSION['kiosk_mode']) && $_SESSION['kiosk_mode'] ? '&kiosk=1' : ''));
        exit;
    }

    // ---- Toggle user active ----
    if (isset($_POST['toggle_active'])) {
        $user_id = intval($_POST['user_id']);
        $current_active = intval($_POST['current_active']);
        $new_active = $current_active ? 0 : 1;
        $stmt = $conn->prepare("UPDATE users SET is_active = ? WHERE id = ?");
        $stmt->bind_param("ii", $new_active, $user_id);
        $stmt->execute();
        $_SESSION['flash_message'] = "User status updated successfully";
        $_SESSION['flash_type'] = 'success';
        header("Location: ?action=users" . (isset($_SESSION['kiosk_mode']) && $_SESSION['kiosk_mode'] ? '&kiosk=1' : ''));
        exit;
    }

    // ---- Delete user ----
    if (isset($_POST['delete_user'])) {
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
            $_SESSION['flash_message'] = "Could not delete user. Please try again.";
            $_SESSION['flash_type'] = 'error';
        }
        header("Location: ?action=users" . (isset($_SESSION['kiosk_mode']) && $_SESSION['kiosk_mode'] ? '&kiosk=1' : ''));
        exit;
    }
}

// ======================== DISPLAY ========================
$page_title = "Admin Panel - " . ucfirst($action);
include __DIR__ . '/../includes/header.php';
?>

<div class="dashboard-wrapper">
    <div class="sidebar">
        <h2>⚙️ Admin Panel</h2>
        <ul>
            <li><a href="?action=dashboard" class="<?= $action === 'dashboard' ? 'active' : '' ?>">Dashboard</a></li>
            <li><a href="?action=orders" class="<?= $action === 'orders' ? 'active' : '' ?>">Manage Orders</a></li>
            <li><a href="?action=users" class="<?= $action === 'users' ? 'active' : '' ?>">Manage Users</a></li>
            <li><a href="<?= normal_url('/admin/menu/index.php') ?>">Manage Menu</a></li>
            <li><a href="<?= kiosk_url('/menu.php') ?>">View Site</a></li>
            <li><a href="<?= normal_url('/auth/logout.php') ?>">Logout</a></li>
        </ul>
    </div>
    <div class="main-content">
        <?php
        // ------------------------------------------------------------------
        // DASHBOARD
        // ------------------------------------------------------------------
        if ($action === 'dashboard'):
            // Fetch stats
            $total_orders = $conn->query("SELECT COUNT(*) FROM orders")->fetch_row()[0];
            $pending_orders = $conn->query("SELECT COUNT(*) FROM orders WHERE status = 'pending'")->fetch_row()[0];
            $total_users = $conn->query("SELECT COUNT(*) FROM users")->fetch_row()[0];
            $total_menu_items = $conn->query("SELECT COUNT(*) FROM menu_items")->fetch_row()[0];
        ?>
            <h1>Dashboard</h1>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                <div class="card text-center"><div class="stat-icon">📦</div><h3><?= $total_orders ?></h3><p>Total Orders</p></div>
                <div class="card text-center"><div class="stat-icon">⏳</div><h3><?= $pending_orders ?></h3><p>Pending Orders</p></div>
                <div class="card text-center"><div class="stat-icon">👥</div><h3><?= $total_users ?></h3><p>Total Users</p></div>
                <div class="card text-center"><div class="stat-icon">🍽️</div><h3><?= $total_menu_items ?></h3><p>Menu Items</p></div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
                <div class="card"><div class="card-header"><h3>Weekly Sales</h3></div><div class="card-content"><canvas id="salesChart" height="200"></canvas></div></div>
                <div class="card"><div class="card-header"><h3>Popular Items</h3></div><div class="card-content"><canvas id="popularChart" height="200"></canvas></div></div>
            </div>

            <div class="card mb-8"><div class="card-header"><h3>Quick Actions</h3></div><div class="card-content grid grid-cols-1 sm:grid-cols-3 gap-4"><a href="<?= normal_url('/admin/menu/index.php?action=add') ?>" class="btn btn-outline w-full">Add Menu Item</a><a href="?action=orders&status=pending" class="btn btn-outline w-full">View Pending Orders</a><a href="?action=users" class="btn btn-outline w-full">Manage Users</a></div></div>

            <div class="card"><div class="card-header"><h3>Recent Orders</h3></div><div class="card-content">
                <?php
                $stmt = $conn->prepare("SELECT o.id, o.total, o.status, o.order_date, u.username FROM orders o JOIN users u ON o.user_id = u.id ORDER BY o.order_date DESC LIMIT 5");
                $stmt->execute();
                $recent = $stmt->get_result();
                if ($recent->num_rows === 0): ?>
                    <p>No orders yet.</p>
                <?php else: ?>
                    <div class="table-wrapper"><table class="table"><thead><tr><th>Order #</th><th>Customer</th><th>Date</th><th>Total</th><th>Status</th><th></th> </thead><tbody>
                    <?php while ($order = $recent->fetch_assoc()): ?>
                         <tr><td><?= $order['id'] ?></td><td><?= htmlspecialchars($order['username']) ?></td><td><?= date('M j, Y g:i a', strtotime($order['order_date'])) ?></td><td>$<?= number_format($order['total'], 2) ?></td><td><span class="status status-<?= $order['status'] ?>"><?= ucfirst($order['status']) ?></span></td><td><a href="<?= normal_url('/staff/order-details.php?id=' . $order['id']) ?>" class="btn btn-sm btn-outline">View</a></td></tr>
                    <?php endwhile; ?>
                    </tbody></table></div>
                <?php endif; ?>
            </div></div>

            <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
            <script>
                fetch('get-sales-data.php')
                    .then(r => r.json())
                    .then(data => {
                        new Chart(document.getElementById('salesChart'), { type: 'line', data: { labels: data.labels, datasets: [{ label: 'Sales ($)', data: data.sales, borderColor: '#074af2', backgroundColor: 'rgba(7,74,242,0.1)' }] }, options: { responsive: true } });
                        new Chart(document.getElementById('popularChart'), { type: 'bar', data: { labels: data.itemLabels, datasets: [{ label: 'Quantity Sold', data: data.itemData, backgroundColor: '#f97316' }] }, options: { responsive: true } });
                    }).catch(console.error);
            </script>
        <?php
        // ------------------------------------------------------------------
        // ORDERS MANAGEMENT
        // ------------------------------------------------------------------
        elseif ($action === 'orders'):
            $status_filter = isset($_GET['status']) ? $_GET['status'] : 'all';
            $allowed = ['all', 'pending', 'processing', 'completed', 'cancelled'];
            if (!in_array($status_filter, $allowed)) $status_filter = 'all';

            if ($status_filter === 'all') {
                $stmt = $conn->prepare("SELECT o.*, u.username AS customer_name, s.username AS staff_name FROM orders o JOIN users u ON o.user_id = u.id LEFT JOIN users s ON o.staff_id = s.id ORDER BY o.order_date DESC");
                $stmt->execute();
            } else {
                $stmt = $conn->prepare("SELECT o.*, u.username AS customer_name, s.username AS staff_name FROM orders o JOIN users u ON o.user_id = u.id LEFT JOIN users s ON o.staff_id = s.id WHERE o.status = ? ORDER BY o.order_date DESC");
                $stmt->bind_param("s", $status_filter);
                $stmt->execute();
            }
            $orders = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $staff_list = $conn->query("SELECT id, username FROM users WHERE role IN ('admin', 'staff')")->fetch_all(MYSQLI_ASSOC);
        ?>
            <h1>Manage Orders</h1>
            <div class="filter-tabs mb-4">
                <a href="?action=orders&status=all" class="btn <?= $status_filter === 'all' ? 'active' : '' ?>">All</a>
                <?php foreach (['pending', 'processing', 'completed', 'cancelled'] as $s): ?>
                    <a href="?action=orders&status=<?= $s ?>" class="btn <?= $s === $status_filter ? 'active' : '' ?>"><?= ucfirst($s) ?></a>
                <?php endforeach; ?>
            </div>
            <?php if (empty($orders)): ?>
                <p>No orders found.</p>
            <?php else: ?>
                <div class="card">
                    <div class="table-wrapper">
                        <table class="admin-table">
                            <thead> <tr><th>Order #</th><th>Customer</th><th>Date</th><th>Total</th><th>Payment</th><th>Status</th><th>Assigned Staff</th><th>Actions</th></tr> </thead>
                            <tbody>
                                <?php foreach ($orders as $order): ?>
                                <tr>
                                    <td><?= $order['id'] ?></td>
                                    <td><?= htmlspecialchars($order['customer_name']) ?></td>
                                    <td><?= date('M j, Y', strtotime($order['order_date'])) ?></td>
                                    <td>$<?= number_format($order['total'], 2) ?></td>
                                    <td class="status-<?= $order['payment_status'] ?>"><?= ucfirst($order['payment_status']) ?></td>
                                    <td class="status status-<?= $order['status'] ?>"><?= ucfirst($order['status']) ?></td>
                                    <td>
                                        <?php if ($order['status'] === 'pending'): ?>
                                            <form method="post" class="inline-form">
                                                <input type="hidden" name="csrf_token" value="<?= generateToken() ?>">
                                                <input type="hidden" name="order_id" value="<?= $order['id'] ?>">
                                                <input type="hidden" name="status" value="processing">
                                                <select name="staff_id" required>
                                                    <option value="">Assign to...</option>
                                                    <?php foreach ($staff_list as $staff): ?>
                                                        <option value="<?= $staff['id'] ?>"><?= htmlspecialchars($staff['username']) ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                                <button type="submit" name="update_status" class="btn-small">Accept</button>
                                            </form>
                                        <?php else: ?>
                                            <?= htmlspecialchars($order['staff_name'] ?? '—') ?>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <a href="<?= normal_url('/staff/order-details.php?id=' . $order['id']) ?>" class="btn-small">View</a>
                                        <?php if ($order['status'] === 'processing'): ?>
                                            <form method="post" class="inline-form">
                                                <input type="hidden" name="csrf_token" value="<?= generateToken() ?>">
                                                <input type="hidden" name="order_id" value="<?= $order['id'] ?>">
                                                <input type="hidden" name="status" value="completed">
                                                <button type="submit" name="update_status" class="btn-small btn-success">Complete</button>
                                            </form>
                                        <?php endif; ?>
                                        <?php if ($order['status'] !== 'cancelled'): ?>
                                            <button class="btn-small btn-danger" data-action="cancel" data-order-id="<?= $order['id'] ?>" data-csrf="<?= generateToken() ?>">Cancel</button>
                                            <form id="cancel-form-<?= $order['id'] ?>" method="post" style="display:none;">
                                                <input type="hidden" name="csrf_token" value="<?= generateToken() ?>">
                                                <input type="hidden" name="order_id" value="<?= $order['id'] ?>">
                                                <input type="hidden" name="status" value="cancelled">
                                                <input type="hidden" name="update_status" value="1">
                                            </form>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>
            <script>
                document.querySelectorAll('[data-action="cancel"]').forEach(btn => {
                    btn.addEventListener('click', (e) => {
                        e.preventDefault();
                        const orderId = btn.dataset.orderId;
                        showConfirmModal({
                            message: `Cancel order #${orderId}?`,
                            onConfirm: () => {
                                document.getElementById(`cancel-form-${orderId}`).submit();
                            }
                        });
                    });
                });
            </script>
        <?php
        // ------------------------------------------------------------------
        // USERS MANAGEMENT
        // ------------------------------------------------------------------
        elseif ($action === 'users'):
            $users = $conn->query("SELECT id, username, email, role, is_active, created_at FROM users ORDER BY created_at DESC")->fetch_all(MYSQLI_ASSOC);
            if (isset($_SESSION['flash_message'])) {
                echo '<div class="flash-message flash-' . $_SESSION['flash_type'] . '">' . htmlspecialchars($_SESSION['flash_message']) . '</div>';
                unset($_SESSION['flash_message'], $_SESSION['flash_type']);
            }
        ?>
            <h1>Manage Users</h1>
            <div class="card">
                <div class="table-wrapper">
                    <table class="admin-table">
                        <thead> <tr><th>ID</th><th>Username</th><th>Email</th><th>Role</th><th>Active</th><th>Registered</th><th>Actions</th></tr> </thead>
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
                                    <button class="btn-small btn-danger" data-action="delete-user" data-user-id="<?= $user['id'] ?>" data-csrf="<?= generateToken() ?>">Delete</button>
                                    <form id="delete-user-form-<?= $user['id'] ?>" method="post" style="display:none;">
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
            <script>
                document.querySelectorAll('[data-action="delete-user"]').forEach(btn => {
                    btn.addEventListener('click', (e) => {
                        e.preventDefault();
                        const userId = btn.dataset.userId;
                        showConfirmModal({
                            message: 'Delete this user? This action cannot be undone.',
                            onConfirm: () => {
                                document.getElementById(`delete-user-form-${userId}`).submit();
                            }
                        });
                    });
                });
            </script>
        <?php endif; ?>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>