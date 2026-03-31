<?php
// Ensure functions are not redeclared
if (!function_exists('kiosk_url')) {

// --- KIOSK MODE DETECTION ---
if (isset($_GET['kiosk'])) {
    $_SESSION['kiosk_mode'] = ($_GET['kiosk'] == '1');
} else {
    unset($_SESSION['kiosk_mode']);
    unset($_SESSION['kiosk_user_id']);
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

// Redirect to kiosk login if in kiosk mode and not logged in
if ($kiosk_mode && !isset($_SESSION['user_id'])) {
    header('Location: ' . kiosk_url('/kiosk/login.php'));
    exit;
}

/**
 * Generate a URL that stays inside kiosk mode.
 */
function kiosk_url($url) {
    global $kiosk_mode;
    $separator = (strpos($url, '?') === false) ? '?' : '&';
    if ($kiosk_mode) {
        return $url . $separator . 'kiosk=1';
    } else {
        return $url . $separator;
    }
}

/**
 * Generate a URL that exits kiosk mode (or stays in normal mode).
 */
function normal_url($url) {
    global $kiosk_mode;
    $separator = (strpos($url, '?') === false) ? '?' : '&';
    if ($kiosk_mode) {
        return $url . $separator . 'kiosk=0';
    } else {
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

/**
 * Force kiosk mode on (or off) for the current session.
 */
function set_kiosk_mode($enable = true) {
    $_SESSION['kiosk_mode'] = $enable;
}

/**
 * Check if the current session is in kiosk mode.
 */
function is_kiosk_mode() {
    global $kiosk_mode;
    return $kiosk_mode;
}

} // end if (!function_exists('kiosk_url'))