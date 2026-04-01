<?php
require __DIR__ . '/../includes/session.php';
require __DIR__ . '/../includes/kiosk.php';

$greeting = '';
$hour = date('H');
if ($hour < 12) $greeting = "Good Morning";
elseif ($hour < 18) $greeting = "Good Afternoon";
else $greeting = "Good Evening";

$page_title = "Welcome | TAMCC Deli Kiosk";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
    <title><?= $page_title ?></title>
    <link rel="stylesheet" href="/assets/css/kiosk.css">
</head>
<body>
    <div class="kiosk">
        <div class="screen">
            <div class="time"></div>
            <h1>Welcome to Marryshow Mealhouse</h1>
            <p style="font-size: var(--text-xl); margin-bottom: var(--space-8);">
                <?= $greeting ?>, guest!
            </p>
            <div style="display: flex; justify-content: center;">
                <a href="<?= kiosk_url('/kiosk/categories.php') ?>" class="btn btn-accent btn-large">Start Order</a>
            </div>
        </div>
    </div>
    <script src="/assets/js/kiosk.js"></script>
</body>
</html>