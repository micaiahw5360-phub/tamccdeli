<?php
require_once __DIR__ . '/session.php';
require_once __DIR__ . '/kiosk.php';

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

    // Redirect to appropriate login page
    if ($kiosk_mode) {
        header('Location: ' . kiosk_url('/kiosk/login.php'));
    } else {
        header('Location: ' . normal_url('/auth/login.php'));
    }
    exit;
}