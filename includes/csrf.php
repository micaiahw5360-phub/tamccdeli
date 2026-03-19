<?php
ob_start(); // Start output buffering to catch any stray output

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function generateToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function validateToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

ob_end_flush(); // Send the buffer (optional, can be omitted)
?>