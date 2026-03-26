<?php
require __DIR__ . '/../middleware/admin_check.php';
require __DIR__ . '/../config/database.php';

// Fetch stats using prepared statements
$stmt = $conn->prepare("SELECT COUNT(*) FROM orders");
$stmt->execute();
$total_orders = $stmt->get_result()->fetch_row()[0];

$stmt = $conn->prepare("SELECT COUNT(*) FROM orders WHERE status = 'pending'");
$stmt->execute();
$pending_orders = $stmt->get_result()->fetch_row()[0];

$stmt = $conn->prepare("SELECT COUNT(*) FROM users");
$stmt->execute();
$total_users = $stmt->get_result()->fetch_row()[0];

$stmt = $conn->prepare("SELECT COUNT(*) FROM menu_items");
$stmt->execute();
$total_menu_items = $stmt->get_result()->fetch_row()[0];

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
    <div class="main-content admin-panel">
        <h1>Admin Dashboard</h1>

        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon">📦</div>
                <h3><?= $total_orders ?></h3>
                <p>Total Orders</p>
            </div>
            <div class="stat-card">
                <div class="stat-icon">⏳</div>
                <h3><?= $pending_orders ?></h3>
                <p>Pending Orders</p>
            </div>
            <div class="stat-card">
                <div class="stat-icon">👥</div>
                <h3><?= $total_users ?></h3>
                <p>Total Users</p>
            </div>
            <div class="stat-card">
                <div class="stat-icon">🍽️</div>
                <h3><?= $total_menu_items ?></h3>
                <p>Menu Items</p>
            </div>
        </div>

        <!-- Chart Containers with Refresh Button -->
        <div class="card">
            <h3>Weekly Sales</h3>
            <canvas id="salesChart" width="400" height="200"></canvas>
            <button id="refresh-charts" class="btn btn-small" style="margin-top:10px;">Refresh Charts</button>
        </div>
        <div class="card">
            <h3>Popular Items</h3>
            <canvas id="popularChart" width="400" height="200"></canvas>
        </div>

        <div class="card">
            <h3>Quick Actions</h3>
            <a href="<?= normal_url('menu/create.php') ?>" class="btn">Add Menu Item</a>
            <a href="<?= normal_url('orders.php?status=pending') ?>" class="btn">View Pending Orders</a>
            <a href="<?= normal_url('users.php') ?>" class="btn">Manage Users</a>
        </div>

        <div class="card">
            <h3>Recent Orders</h3>
            <?php
            $stmt = $conn->prepare("SELECT o.id, o.total, o.status, o.order_date, u.username 
                                    FROM orders o 
                                    JOIN users u ON o.user_id = u.id 
                                    ORDER BY o.order_date DESC LIMIT 5");
            $stmt->execute();
            $recent = $stmt->get_result();
            if ($recent->num_rows === 0): ?>
                <p>No orders yet.</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="admin-table">
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
                                    <td class="status status-<?= $order['status'] ?>"><?= ucfirst($order['status']) ?></td>
                                    <td><a href="<?= normal_url('../staff/order-details.php?id=' . $order['id']) ?>" class="btn-small">View</a></td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    let salesChart, popularChart;
    const refreshBtn = document.getElementById('refresh-charts');

    function loadChartData() {
        if (refreshBtn) refreshBtn.disabled = true;
        fetch('get-sales-data.php')
            .then(response => {
                if (!response.ok) throw new Error('Network response was not ok');
                return response.json();
            })
            .then(data => {
                if (salesChart) salesChart.destroy();
                if (popularChart) popularChart.destroy();

                salesChart = new Chart(document.getElementById('salesChart'), {
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

                popularChart = new Chart(document.getElementById('popularChart'), {
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
            .catch(error => {
                console.error('Error loading chart data:', error);
                showToast('Could not load chart data.', 'error');
            })
            .finally(() => {
                if (refreshBtn) refreshBtn.disabled = false;
            });
    }

    document.addEventListener('DOMContentLoaded', function() {
        loadChartData();
        if (refreshBtn) refreshBtn.addEventListener('click', loadChartData);
    });
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>