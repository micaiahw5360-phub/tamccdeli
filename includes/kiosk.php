<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

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
 * Append ?kiosk=1 to a URL when in kiosk mode.
 * Use for pages that should stay inside the kiosk interface.
 */
function kiosk_url($url) {
    global $kiosk_mode;
    if ($kiosk_mode) {
        $separator = (strpos($url, '?') === false) ? '?' : '&';
        return $url . $separator . 'kiosk=1';
    }
    return $url;
}

/**
 * Append ?kiosk=0 to a URL when in kiosk mode.
 * Use for links that should exit kiosk mode (e.g., dashboard, login, admin).
 */
function normal_url($url) {
    global $kiosk_mode;
    if ($kiosk_mode) {
        $separator = (strpos($url, '?') === false) ? '?' : '&';
        return $url . $separator . 'kiosk=0';
    }
    return $url;
}