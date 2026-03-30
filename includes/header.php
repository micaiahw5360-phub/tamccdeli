<?php
require_once __DIR__ . '/session.php'; // Starts session with secure cookies
require_once __DIR__ . '/kiosk.php';   // Load kiosk functions and $kiosk_mode

// Determine if this page is an admin, staff, or dashboard panel
$is_admin_panel = strpos($_SERVER['SCRIPT_NAME'], '/admin/') !== false;
$is_staff_panel = strpos($_SERVER['SCRIPT_NAME'], '/staff/') !== false;
$is_dashboard = strpos($_SERVER['SCRIPT_NAME'], '/dashboard/') !== false;
$hide_header = $is_admin_panel || $is_staff_panel || $is_dashboard;

// Pass kiosk mode to JavaScript
echo '<script>var kioskMode = ' . ($kiosk_mode ? 'true' : 'false') . ';</script>';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? $page_title : 'TAMCC Deli'; ?></title>
    <link rel="manifest" href="/manifest.json">
    <meta http-equiv="Content-Security-Policy" content="default-src * 'unsafe-inline' 'unsafe-eval'; script-src * 'unsafe-inline' 'unsafe-eval'; style-src * 'unsafe-inline'; img-src * data:; font-src * data:;">
    <link rel="stylesheet" href="/assets/css/global.css">
    <?php if ($kiosk_mode): ?>
        <link rel="stylesheet" href="/assets/css/kiosk.css">
    <?php endif; ?>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/gh/WordPress/WordPress@master/wp-includes/css/dashicons.min.css">
    <meta name="mobile-web-app-capable" content="yes">
</head>
<body>
<?php if (!$hide_header): ?>
    <div class="navbar">
        <a href="<?= kiosk_url('/index.php') ?>" class="logo">
            <img src="/assets/images/ta-logo-1536x512.png" alt="TAMCC Deli" style="height: 50px;">
            <span class="logo-text" style="display: none;">TAMCC Deli</span>
        </a>
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
                    <a href="<?= normal_url('/staff/orders.php') ?>"><span class="dashicons dashicons-clipboard"></span> Staff Panel</a>
                <?php endif; ?>
                <?php if ($_SESSION['role'] === 'admin'): ?>
                    <a href="<?= normal_url('/admin/index.php') ?>"><span class="dashicons dashicons-admin-tools"></span> Admin Panel</a>
                <?php endif; ?>
            <?php endif; ?>

            <!-- User links with optional profile photo -->
<?php if (isset($_SESSION['user_id'])): ?>
    <?php if (isset($_SESSION['profile_photo'])): ?>
        <a href="<?= normal_url('/dashboard/index.php') ?>" style="display:flex; align-items:center;">
            <img src="<?= $_SESSION['profile_photo'] ?>" alt="Profile" style="width:32px; height:32px; border-radius:50%; margin-right:8px;">
            Dashboard
        </a>
    <?php else: ?>
        <a href="<?= normal_url('/dashboard/index.php') ?>"><span class="dashicons dashicons-dashboard"></span> Dashboard</a>
    <?php endif; ?>
    <a href="<?= normal_url('/wallet.php') ?>"><span class="dashicons dashicons-money"></span> Wallet</a>
    <a href="<?= normal_url('/auth/logout.php') ?>"><span class="dashicons dashicons-exit"></span> Logout</a>
<?php else: ?>
    <a href="<?= normal_url('/auth/login.php') ?>"><span class="dashicons dashicons-lock"></span> Login</a>
    <a href="<?= normal_url('/auth/register.php') ?>"><span class="dashicons dashicons-edit"></span> Register</a>
<?php endif; ?>
        </div>
    </div>
<?php endif; ?>