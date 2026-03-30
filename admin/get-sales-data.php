<?php
require __DIR__ . '/../config/database.php';

header('Content-Type: application/json');

// Default empty response
$response = [
    'labels' => ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'],
    'sales' => [0, 0, 0, 0, 0, 0, 0],
    'itemLabels' => [],
    'itemData' => []
];

try {
    // Generate last 7 days as dates (Y-m-d) and labels (day names)
    $dates = [];
    $labels = [];
    for ($i = 6; $i >= 0; $i--) {
        $date = date('Y-m-d', strtotime("-$i days"));
        $dates[] = $date;
        $labels[] = date('D', strtotime($date));
    }

    $placeholders = implode(',', array_fill(0, 7, '?'));
    $stmt = $conn->prepare("SELECT DATE(order_date) as day, COALESCE(SUM(total),0) as total 
                             FROM orders 
                             WHERE DATE(order_date) IN ($placeholders)
                             GROUP BY DATE(order_date)");
    $stmt->bind_param(str_repeat('s', 7), ...$dates);
    $stmt->execute();
    $result = $stmt->get_result();
    $daily = [];
    while ($row = $result->fetch_assoc()) {
        $daily[$row['day']] = $row['total'];
    }

    $sales = [];
    foreach ($dates as $date) {
        $sales[] = $daily[$date] ?? 0;
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

    $response = [
        'labels' => $labels,
        'sales' => $sales,
        'itemLabels' => $itemNames,
        'itemData' => $itemQtys
    ];
} catch (Exception $e) {
    // Log error but return empty data
    error_log('get-sales-data.php error: ' . $e->getMessage());
}

echo json_encode($response);