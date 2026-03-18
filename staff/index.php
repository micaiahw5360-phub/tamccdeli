<?php
require __DIR__ . '/../middleware/staff_check.php';
header("Location: orders.php");
exit;