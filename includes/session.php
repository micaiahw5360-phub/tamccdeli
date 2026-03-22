<?php
// Set secure cookie parameters BEFORE starting the session
session_set_cookie_params([
    'httponly' => true,
    'samesite' => 'Strict',
    'secure' => isset($_SERVER['HTTPS']) // auto‑detect HTTPS
]);

// Start session only if not already active
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Check if the session is secure (HTTPS and valid)
 * @return bool
 */
function session_is_secure() {
    return !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
}