<?php
require __DIR__ . '/../includes/session.php';
require __DIR__ . '/../config/database.php';
require __DIR__ . '/../includes/kiosk.php';
require __DIR__ . '/../includes/functions.php';

$kiosk_mode = true;

$hour = date('H');
if ($hour < 12) $greeting = "Good Morning";
elseif ($hour < 18) $greeting = "Good Afternoon";
else $greeting = "Good Evening";

$page_title = "TAMCC Deli Kiosk";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
    <title><?= $page_title ?></title>
    <link rel="stylesheet" href="/assets/css/global.css">
    <link rel="stylesheet" href="/assets/css/kiosk.css">
    <style>
        .hero-landing {
            text-align: center;
            padding: var(--space-xl) var(--space);
            background: linear-gradient(135deg, rgba(7,74,242,0.85), rgba(249,115,22,0.85)), url('/assets/images/campus-bg.jpg');
            background-size: cover;
            background-position: center;
            color: white;
            border-radius: var(--radius-xl);
            margin-bottom: var(--space-lg);
            box-shadow: var(--shadow-lg);
        }
        .hero-landing h1 {
            color: white;
            font-size: clamp(2.5rem, 8vw, 4rem);
            text-shadow: 2px 2px 4px rgba(0,0,0,0.2);
            margin-bottom: var(--space);
        }
        .hero-landing p {
            font-size: clamp(1.2rem, 4vw, 1.8rem);
            opacity: 0.95;
        }
        .time-large {
            font-size: clamp(1.5rem, 5vw, 2rem);
            font-weight: 600;
            background: rgba(0,0,0,0.2);
            display: inline-block;
            padding: 0.3rem 1rem;
            border-radius: 60px;
            margin-top: var(--space);
        }
        .cta-button {
            display: inline-block;
            background: var(--accent-500);
            color: white;
            font-size: clamp(1.5rem, 5vw, 2.2rem);
            font-weight: 700;
            padding: var(--space) var(--space-xl);
            border-radius: 60px;
            text-decoration: none;
            transition: var(--transition);
            box-shadow: var(--shadow-lg);
            margin-top: var(--space);
        }
        .cta-button:hover {
            background: var(--accent-600);
            transform: scale(1.02);
            box-shadow: var(--shadow-xl);
        }
        .info-cards {
            display: flex;
            flex-wrap: wrap;
            gap: var(--space);
            justify-content: center;
            margin: var(--space-xl) 0;
        }
        .info-card {
            background: rgba(255,255,255,0.9);
            backdrop-filter: blur(8px);
            border-radius: var(--radius-lg);
            padding: var(--space);
            flex: 1;
            min-width: 160px;
            text-align: center;
            box-shadow: var(--shadow);
            border: 1px solid rgba(255,255,255,0.3);
        }
        .info-card .emoji {
            font-size: 2.5rem;
        }
        .info-card h4 {
            margin: var(--space-xs) 0;
            color: var(--primary-700);
        }
        @media (max-width: 768px) {
            .cta-button {
                width: 90%;
                text-align: center;
            }
        }
    </style>
</head>
<body>
    <?php include __DIR__ . '/../includes/header.php'; ?>

    <div class="kiosk-container" style="max-width: 1200px; margin: 0 auto; padding: var(--space);">
        
        <!-- Hero Section -->
        <div class="hero-landing">
            <h1>Marryshow Mealhouse</h1>
            <p><?= $greeting ?>! Ready for a delicious meal?</p>
            <div class="time-large" id="live-time"></div>
        </div>

        <!-- Main CTA -->
        <div style="text-align: center;">
            <a href="<?= kiosk_url('/kiosk/categories.php') ?>" class="cta-button">
                🍽️ Start Your Order
            </a>
        </div>

        <!-- Info / Promo Cards -->
        <div class="info-cards">
            <div class="info-card">
                <div class="emoji">💰</div>
                <h4>Wallet Friendly</h4>
                <p>Top up & pay with student wallet</p>
            </div>
            <div class="info-card">
                <div class="emoji">⚡</div>
                <h4>Fast Pickup</h4>
                <p>Ready in 10-15 minutes</p>
            </div>
            <div class="info-card">
                <div class="emoji">🌱</div>
                <h4>Fresh Local</h4>
                <p>Sourced from Grenada</p>
            </div>
        </div>

        <div style="text-align: center; margin-top: var(--space-xl); font-size: var(--text-sm); color: var(--neutral-500);">
            <p>T.A. Marryshow Community College – Tanteen Campus</p>
        </div>
    </div>

    <a href="<?= kiosk_url('/cart.php') ?>" class="floating-cart">
        🛒 Cart <span class="cart-count" id="cart-count-kiosk">0</span>
    </a>

    <?php include __DIR__ . '/../includes/footer.php'; ?>

    <script>
        function updateTime() {
            const now = new Date();
            const timeStr = now.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
            const timeEl = document.getElementById('live-time');
            if (timeEl) timeEl.textContent = timeStr;
        }
        setInterval(updateTime, 1000);
        updateTime();

        function updateCartDisplay() {
            fetch('<?= kiosk_url('/get-cart-count.php') ?>')
                .then(r => r.json())
                .then(data => {
                    document.querySelectorAll('.cart-count').forEach(el => el.textContent = data.count);
                })
                .catch(console.error);
        }
        updateCartDisplay();
        setInterval(updateCartDisplay, 5000);
    </script>
</body>
</html>