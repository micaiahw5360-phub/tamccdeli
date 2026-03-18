<?php
require_once __DIR__ . '/auth_check.php'; // ensures user is logged in and session started

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("HTTP/1.0 403 Forbidden");
    echo "<h1>Access Denied</h1><p>Only administrators can access this page.</p>";
    exit;
}
?>