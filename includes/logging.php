<?php
function log_admin_action($action, $target_type = null, $target_id = null, $details = null) {
    global $conn;
    if (!isset($_SESSION['user_id'])) return;
    $admin_id = $_SESSION['user_id'];
    $stmt = $conn->prepare("INSERT INTO admin_logs (admin_id, action, target_type, target_id, details) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("issis", $admin_id, $action, $target_type, $target_id, $details);
    $stmt->execute();
}

function log_security_event($user_id, $action, $details = null) {
    global $conn;
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    $stmt = $conn->prepare("INSERT INTO security_logs (user_id, action, details, ip) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("isss", $user_id, $action, $details, $ip);
    $stmt->execute();
}