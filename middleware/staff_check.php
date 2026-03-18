<?php
require_once __DIR__ . '/auth_check.php'; // ensures user is logged in
if ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'staff') {
    header("HTTP/1.0 403 Forbidden");
    echo "<h1>Access Denied</h1><p>Only staff members can access this page.</p>";
    exit;
}