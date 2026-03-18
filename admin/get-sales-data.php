<?php
require __DIR__ . '/../config/database.php';

// Get last 7 days sales with a single query
$salesData = [];
for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $labels[] = date('D', strtotime($date));
}
$placeholders = implode(',', array_fill(0, 7, '?'));
$stmt = $conn->prepare("SELECT DATE(order_date) as day, COALESCE(SUM(total),0) as total 
                         FROM orders 
                         WHERE DATE(order_date) IN ($placeholders)
                         GROUP BY DATE(order_date)");
$stmt->bind_param(str_repeat('s', 7), ...$labels);
$stmt->execute();
$result = $stmt->get_result();
$daily = [];
while ($row = $result->fetch_assoc()) {
    $daily[$row['day']] = $row['total'];
}

$sales = [];
foreach ($labels as $day) {
    $sales[] = $daily[$day] ?? 0;
}

// Get top 5 items
$stmt = $conn->prepare("SELECT mi.name, SUM(oi.quantity) as total_qty 
                        FROM order_items oi 
                        JOIN menu_items mi ON oi.menu_item_id = mi.id 
                        GROUP BY oi.menu_item_id 
                        ORDER BY total_qty DESC LIMIT 5");
$stmt->execute();
$popular = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$itemNames = [];
$itemQtys = [];
foreach ($popular as $item) {
    $itemNames[] = $item['name'];
    $itemQtys[] = (int)$item['total_qty'];
}

header('Content-Type: application/json');
echo json_encode([
    'labels' => $labels,
    'sales' => $sales,
    'itemLabels' => $itemNames,
    'itemData' => $itemQtys
]);