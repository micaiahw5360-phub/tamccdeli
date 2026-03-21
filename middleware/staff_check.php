<?php
require __DIR__ . '/../includes/session.php'; // Correct path

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'staff'])) {
    header("Location: /auth/login.php");
    exit;
}