<?php
// Session should already be started by session.php
// DO NOT call session_set_cookie_params() or session_start() here

// --- KIOSK MODE DETECTION ---
if (isset($_GET['kiosk'])) {
    $_SESSION['kiosk_mode'] = ($_GET['kiosk'] == '1');
} else {
    // No kiosk parameter: force normal mode
    unset($_SESSION['kiosk_mode']);
}

$kiosk_user_agents = ['KioskBrowser/1.0', 'YourKioskApp'];
if (!isset($_SESSION['kiosk_mode']) && isset($_SERVER['HTTP_USER_AGENT'])) {
    foreach ($kiosk_user_agents as $ua) {
        if (stripos($_SERVER['HTTP_USER_AGENT'], $ua) !== false) {
            $_SESSION['kiosk_mode'] = true;
            break;
        }
    }
}

$kiosk_mode = $_SESSION['kiosk_mode'] ?? false;

/**
 * Generate a URL that stays inside kiosk mode.
 * Always appends a query string (empty if not in kiosk mode).
 */
function kiosk_url($url) {
    global $kiosk_mode;
    $separator = (strpos($url, '?') === false) ? '?' : '&';
    if ($kiosk_mode) {
        return $url . $separator . 'kiosk=1';
    } else {
        // In normal mode, still add an empty query string
        return $url . $separator;
    }
}

/**
 * Generate a URL that exits kiosk mode (or stays in normal mode).
 * Always appends a query string (empty if not in kiosk mode).
 */
function normal_url($url) {
    global $kiosk_mode;
    $separator = (strpos($url, '?') === false) ? '?' : '&';
    if ($kiosk_mode) {
        return $url . $separator . 'kiosk=0';
    } else {
        // In normal mode, just an empty query string
        return $url . $separator;
    }
}

/**
 * Get the full site URL with scheme and domain
 */
function get_site_url() {
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'];
    return $protocol . '://' . $host;
}

/**
 * Generate a full absolute URL that stays inside kiosk mode
 */
function kiosk_absolute_url($path) {
    return get_site_url() . kiosk_url($path);
}