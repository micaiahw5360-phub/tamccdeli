<?php
require __DIR__ . '/../includes/session.php'; // Correct path

if (!isset($_SESSION['user_id'])) {
    header("Location: /auth/login.php");
    exit;
}