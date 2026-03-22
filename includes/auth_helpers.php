<?php
require_once __DIR__ . '/session.php'; // Start session first
require_once __DIR__ . '/kiosk.php';   // Now normal_url() is defined

function redirect_to_login() {
    global $kiosk_mode;

    // If AJAX request, return JSON error
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
        header('Content-Type: application/json');
        http_response_code(401);
        echo json_encode(['error' => 'Unauthorized', 'redirect' => normal_url('/auth/login.php')]);
        exit;
    }

    // Store intended URL for post-login redirect
    $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
    header('Location: ' . normal_url('/auth/login.php'));
    exit;
}