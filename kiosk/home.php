<?php
require __DIR__ . '/../includes/session.php';
require __DIR__ . '/../config/database.php';
require __DIR__ . '/../includes/kiosk.php';
require __DIR__ . '/../includes/functions.php';

$kiosk_mode = true;

// Greeting based on time
$hour = date('H');
if ($hour < 12) $greeting = "Good Morning";
elseif ($hour < 18) $greeting = "Good Afternoon";
else $greeting = "Good Evening";

// Fetch a few popular items for display (optional)
$popular_items = getPopularItems($conn, 3);

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
        /* Additional inline styles for the landing page (keeps everything in one file) */
        .hero-landing {
            text-align: center;
            padding: var(--space-xl) var(--space);
            background: linear-gradient(135deg, var(--primary-600) 0%, var(--accent-500) 100%);
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
        .featured-section {
            margin: var(--space-xl) 0;
        }
        .featured-title {
            text-align: center;
            font-size: var(--text-2xl);
            margin-bottom: var(--space-lg);
            color: var(--primary-700);
            position: relative;
        }
        .featured-title:after {
            content: '';
            display: block;
            width: 80px;
            height: 4px;
            background: var(--accent-500);
            margin: var(--space-sm) auto 0;
            border-radius: 2px;
        }
        .featured-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: var(--space-md);
        }
        .featured-item {
            background: white;
            border-radius: var(--radius-lg);
            overflow: hidden;
            box-shadow: var(--shadow);
            transition: var(--transition);
            text-align: center;
            padding: var(--space);
        }
        .featured-item:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-xl);
        }
        .featured-item img {
            width: 100%;
            height: 140px;
            object-fit: cover;
            border-radius: var(--radius-md);
        }
        .featured-item h3 {
            margin: var(--space) 0 var(--space-xs);
            font-size: var(--text-lg);
        }
        .featured-item .price {
            font-size: var(--text-xl);
            font-weight: 700;
            color: var(--primary-600);
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
            .featured-grid {
                grid-template-columns: 1fr;
            }
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

        <!-- Featured / Popular Items (if any) -->
        <?php if (!empty($popular_items)): ?>
        <div class="featured-section">
            <div class="featured-title">
                <h2>🔥 Popular Picks</h2>
            </div>
            <div class="featured-grid">
                <?php foreach ($popular_items as $item): ?>
                <div class="featured-item">
                    <?php if ($item['image']): ?>
                        <img src="<?= htmlspecialchars($item['image']) ?>" alt="<?= htmlspecialchars($item['name']) ?>">
                    <?php else: ?>
                        <img src="https://images.unsplash.com/photo-1546069901-ba9599a7e63c?w=400&auto=format" alt="Food">
                    <?php endif; ?>
                    <h3><?= htmlspecialchars($item['name']) ?></h3>
                    <div class="price">$<?= number_format($item['price'], 2) ?></div>
                    <a href="<?= kiosk_url('/kiosk/items.php?cat=' . urlencode($item['category'])) ?>" class="btn btn-small" style="margin-top: var(--space-sm);">Order Now →</a>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

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

        <!-- Footer note (optional) -->
        <div style="text-align: center; margin-top: var(--space-xl); font-size: var(--text-sm); color: var(--neutral-500);">
            <p>T.A. Marryshow Community College – Tanteen Campus</p>
        </div>
    </div>

    <!-- Floating Cart (same as other kiosk pages) -->
    <a href="<?= kiosk_url('/cart.php') ?>" class="floating-cart">
        🛒 Cart <span class="cart-count" id="cart-count-kiosk">0</span>
    </a>

    <?php include __DIR__ . '/../includes/footer.php'; ?>

    <script>
        // Live clock update
        function updateTime() {
            const now = new Date();
            const timeStr = now.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
            const timeEl = document.getElementById('live-time');
            if (timeEl) timeEl.textContent = timeStr;
        }
        setInterval(updateTime, 1000);
        updateTime();

        // Cart count updater
        function updateCartDisplay() {
            fetch('<?= kiosk_url('/get-cart-count.php') ?>')
                .then(r => r.json())
                .then(data => {
                    document.querySelectorAll('.cart-count').forEach(el => el.textContent = data.count);
                })
                .catch(console.error);
        }
        updateCartDisplay();
        setInterval(updateCartDisplay, 5000); // refresh every 5 sec
    </script>
</body>
</html>