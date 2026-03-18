<?php
// Start output buffering to discard any accidental output (e.g., BOM)
ob_start();

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// --- KIOSK MODE DETECTION ---
if (isset($_GET['kiosk'])) {
    $_SESSION['kiosk_mode'] = ($_GET['kiosk'] == '1');
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

function kiosk_url($url) {
    global $kiosk_mode;
    if ($kiosk_mode) {
        $separator = (strpos($url, '?') === false) ? '?' : '&';
        return $url . $separator . 'kiosk=1';
    }
    return $url;
}

// Pass kiosk mode to JavaScript
echo '<script>var kioskMode = ' . ($kiosk_mode ? 'true' : 'false') . ';</script>';

// Flush the buffer and send output
ob_end_flush();
?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? $page_title : 'TAMCC Deli'; ?></title>
    <link rel="stylesheet" href="/assets/css/global.css">
    <?php if ($kiosk_mode): ?>
        <link rel="stylesheet" href="/assets/css/kiosk.css">
    <?php endif; ?>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/gh/WordPress/WordPress@master/wp-includes/css/dashicons.min.css">
    <meta name="mobile-web-app-capable" content="yes">
</head>
<body>
    <div class="navbar">
        <a href="<?= kiosk_url('/index.php') ?>" class="logo">TAMCC Deli</a>
        <button class="menu-toggle" aria-label="Toggle menu">☰</button>
        <div class="nav-links">
            <a href="<?= kiosk_url('/index.php') ?>">Home</a>
            <div class="dropdown">
                <a href="<?= kiosk_url('/menu.php') ?>">Menu ▾</a>
                <div class="dropdown-content">
                    <a href="<?= kiosk_url('/menu.php#breakfast') ?>">Breakfast</a>
                    <a href="<?= kiosk_url('/menu.php#alacarte') ?>">A La Carte</a>
                    <a href="<?= kiosk_url('/menu.php#combo') ?>">Combo</a>
                    <a href="<?= kiosk_url('/menu.php#beverage') ?>">Beverage</a>
                    <a href="<?= kiosk_url('/menu.php#dessert') ?>">Dessert</a>
                </div>
            </div>
            <a href="<?= kiosk_url('/cart.php') ?>"><span class="dashicons dashicons-cart"></span> Cart <span id="cart-count" class="cart-count">0</span></a>

            <?php if (isset($_SESSION['user_id']) && ($_SESSION['role'] === 'admin' || $_SESSION['role'] === 'staff') && !$kiosk_mode): ?>
                <a href="?kiosk=1" class="btn-small" style="background: #ff66c4; color: white;">🎮 Enter Kiosk Mode</a>
            <?php endif; ?>

            <?php if (isset($_SESSION['user_id']) && isset($_SESSION['role'])): ?>
                <?php if ($_SESSION['role'] === 'admin' || $_SESSION['role'] === 'staff'): ?>
                    <a href="<?= kiosk_url('/staff/orders.php') ?>"><span class="dashicons dashicons-clipboard"></span> Staff Panel</a>
                <?php endif; ?>
                <?php if ($_SESSION['role'] === 'admin'): ?>
                    <a href="<?= kiosk_url('/admin/index.php') ?>"><span class="dashicons dashicons-admin-tools"></span> Admin Panel</a>
                <?php endif; ?>
            <?php endif; ?>

            <!-- User links -->
<?php if (isset($_SESSION['user_id'])): ?>
    <a href="<?= kiosk_url('/dashboard/index.php') ?>"><span class="dashicons dashicons-dashboard"></span> Dashboard</a>
    <a href="<?= kiosk_url('/wallet.php') ?>"><span class="dashicons dashicons-money"></span> Wallet</a>
    <a href="<?= kiosk_url('/auth/logout.php') ?>"><span class="dashicons dashicons-exit"></span> Logout</a>
<?php else: ?>
    <a href="<?= kiosk_url('/auth/login.php') ?>"><span class="dashicons dashicons-lock"></span> Login</a>
    <a href="<?= kiosk_url('/auth/register.php') ?>"><span class="dashicons dashicons-edit"></span> Register</a>
<?php endif; ?>
        </div>
    </div>