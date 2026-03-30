<?php
require __DIR__ . '/../middleware/admin_check.php';
require __DIR__ . '/../config/database.php';

// Fetch stats
$total_orders = $conn->query("SELECT COUNT(*) FROM orders")->fetch_row()[0];
$pending_orders = $conn->query("SELECT COUNT(*) FROM orders WHERE status = 'pending'")->fetch_row()[0];
$total_users = $conn->query("SELECT COUNT(*) FROM users")->fetch_row()[0];
$total_menu_items = $conn->query("SELECT COUNT(*) FROM menu_items")->fetch_row()[0];

$page_title = "Admin Dashboard";
include __DIR__ . '/../includes/header.php';
?>

<div class="dashboard-wrapper">
    <div class="sidebar">
        <h2>⚙️ Admin Panel</h2>
        <ul>
            <li><a href="<?= normal_url('index.php') ?>" class="active">Dashboard</a></li>
            <li><a href="<?= normal_url('menu/index.php') ?>">Manage Menu</a></li>
            <li><a href="<?= normal_url('orders.php') ?>">Manage Orders</a></li>
            <li><a href="<?= normal_url('users.php') ?>">Manage Users</a></li>
            <li><a href="<?= kiosk_url('../menu.php') ?>">View Site</a></li>
            <li><a href="<?= normal_url('../auth/logout.php') ?>">Logout</a></li>
        </ul>
    </div>
    <div class="main-content">
        <h1 class="text-2xl font-bold mb-6">Admin Dashboard</h1>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
            <div class="card">
                <div class="card-content text-center">
                    <div class="stat-icon">📦</div>
                    <h3 class="text-3xl font-bold mt-2"><?= $total_orders ?></h3>
                    <p class="text-gray-600">Total Orders</p>
                </div>
            </div>
            <div class="card">
                <div class="card-content text-center">
                    <div class="stat-icon">⏳</div>
                    <h3 class="text-3xl font-bold mt-2"><?= $pending_orders ?></h3>
                    <p class="text-gray-600">Pending Orders</p>
                </div>
            </div>
            <div class="card">
                <div class="card-content text-center">
                    <div class="stat-icon">👥</div>
                    <h3 class="text-3xl font-bold mt-2"><?= $total_users ?></h3>
                    <p class="text-gray-600">Total Users</p>
                </div>
            </div>
            <div class="card">
                <div class="card-content text-center">
                    <div class="stat-icon">🍽️</div>
                    <h3 class="text-3xl font-bold mt-2"><?= $total_menu_items ?></h3>
                    <p class="text-gray-600">Menu Items</p>
                </div>
            </div>
        </div>

        <!-- Charts -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Weekly Sales</h3>
                </div>
                <div class="card-content">
                    <canvas id="salesChart" height="200"></canvas>
                </div>
            </div>
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Popular Items</h3>
                </div>
                <div class="card-content">
                    <canvas id="popularChart" height="200"></canvas>
                </div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="card mb-8">
            <div class="card-header">
                <h3 class="card-title">Quick Actions</h3>
            </div>
            <div class="card-content">
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                    <a href="<?= normal_url('menu/create.php') ?>" class="btn btn-outline w-full">Add Menu Item</a>
                    <a href="<?= normal_url('orders.php?status=pending') ?>" class="btn btn-outline w-full">View Pending Orders</a>
                    <a href="<?= normal_url('users.php') ?>" class="btn btn-outline w-full">Manage Users</a>
                    <a href="<?= kiosk_url('../index.php') ?>" class="btn btn-outline w-full">View Site</a>
                </div>
            </div>
        </div>

        <!-- Recent Orders -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Recent Orders</h3>
            </div>
            <div class="card-content">
                <?php
                $stmt = $conn->prepare("SELECT o.id, o.total, o.status, o.order_date, u.username FROM orders o JOIN users u ON o.user_id = u.id ORDER BY o.order_date DESC LIMIT 5");
                $stmt->execute();
                $recent = $stmt->get_result();
                ?>
                <?php if ($recent->num_rows === 0): ?>
                    <p class="text-gray-500 text-center">No orders yet.</p>
                <?php else: ?>
                    <div class="table-wrapper">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Order #</th>
                                    <th>Customer</th>
                                    <th>Date</th>
                                    <th>Total</th>
                                    <th>Status</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($order = $recent->fetch_assoc()): ?>
                                <tr>
                                    <td><?= $order['id'] ?></td>
                                    <td><?= htmlspecialchars($order['username']) ?></td>
                                    <td><?= date('M j, Y g:i a', strtotime($order['order_date'])) ?></td>
                                    <td>$<?= number_format($order['total'], 2) ?></td>
                                    <td><span class="status status-<?= $order['status'] ?>"><?= ucfirst($order['status']) ?></span></td>
                                    <td><a href="<?= normal_url('../staff/order-details.php?id=' . $order['id']) ?>" class="btn btn-sm btn-outline">View</a></td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Fetch sales data from API
    fetch('get-sales-data.php')
        .then(response => response.json())
        .then(data => {
            new Chart(document.getElementById('salesChart'), {
                type: 'line',
                data: {
                    labels: data.labels,
                    datasets: [{
                        label: 'Sales ($)',
                        data: data.sales,
                        borderColor: '#074af2',
                        backgroundColor: 'rgba(7,74,242,0.1)',
                        tension: 0.3
                    }]
                },
                options: { responsive: true }
            });
            new Chart(document.getElementById('popularChart'), {
                type: 'bar',
                data: {
                    labels: data.itemLabels,
                    datasets: [{
                        label: 'Quantity Sold',
                        data: data.itemData,
                        backgroundColor: '#f97316'
                    }]
                },
                options: { responsive: true }
            });
        })
        .catch(error => console.error('Error loading chart data:', error));
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>