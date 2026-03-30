<?php
require __DIR__ . '/includes/session.php';
require "config/database.php";
require "includes/functions.php"; // Required for getPopularItems()

$page_title = "TAMCC Deli | Marryshow Mealhouse";
include 'includes/header.php';

// Fetch popular items from cache (top 3 items by sales in last 30 days)
$popular_items = getPopularItems($conn, 3);
?>

<style>
    /* Additional homepage styles (hero, features, etc.) – can be moved to global.css if preferred */
    .hero {
        background: linear-gradient(rgba(0, 0, 0, 0.5), rgba(0, 0, 0, 0.5)),
                    url('https://www.tamcc.edu.gd/wp-content/uploads/2024/01/Drone-Shot-Tanteen-scaled.jpg');
        background-size: cover;
        background-position: center;
        height: 80vh;
        display: flex;
        align-items: center;
        justify-content: center;
        text-align: center;
        color: white;
        padding: 20px;
    }
    .hero h1 { font-size: clamp(2.5rem, 8vw, 4rem); text-shadow: 2px 2px 4px rgba(255, 255, 255, 0.7); }
    .hero p { font-size: clamp(1.2rem, 3vw, 1.8rem); max-width: 800px; margin: 0 auto 2rem; }
    .section { padding: 60px 20px; max-width: 1200px; margin: 0 auto; }
    .section h2 { text-align: center; margin-bottom: 40px; position: relative; }
    .section h2:after {
        content: '';
        display: block;
        width: 80px;
        height: 4px;
        background: var(--primary-600);
        margin: 15px auto 0;
        border-radius: 2px;
    }
    .features {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
        gap: 30px;
        margin-top: 40px;
    }
    .feature {
        text-align: center;
        padding: 30px 20px;
        background: white;
        border-radius: var(--radius-lg);
        box-shadow: var(--shadow);
        transition: var(--transition);
    }
    .feature:hover { transform: translateY(-5px); box-shadow: var(--shadow-lg); }
    .feature img { width: 80px; height: 80px; margin-bottom: 20px; }
    .menu-preview { background: var(--neutral-100); }
    .menu-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
        gap: 30px;
        margin-top: 30px;
    }
    .menu-card {
        background: white; border-radius: var(--radius-lg); overflow: hidden; box-shadow: var(--shadow); transition: var(--transition);
    }
    .menu-card:hover { transform: translateY(-5px); box-shadow: var(--shadow-lg); }
    .menu-card img { width: 100%; height: 180px; object-fit: cover; }
    .menu-card .card-content { padding: 20px; }
    .menu-card .price { font-size: 1.5rem; color: var(--primary-600); font-weight: 700; }
    .about-content {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 50px;
        align-items: center;
    }
    .about-image img { width: 100%; border-radius: var(--radius-lg); }
    .hours {
        background: var(--neutral-900);
        color: white;
        text-align: center;
        padding: 60px 20px;
    }
    .hours h2 { color: white; }
    .hours-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
        gap: 20px;
        max-width: 800px;
        margin: 30px auto 0;
    }
    .hours-item { background: rgba(255,255,255,0.1); padding: 20px; border-radius: var(--radius); }
    .hours-item h4 { color: var(--primary-400); margin-bottom: 10px; }
    @media (max-width:768px) {
        .hero h1 { font-size: 2.5rem; }
        .hero p { font-size: 1.2rem; }
        .about-content { grid-template-columns:1fr; }
        .about-image { order:-1; }
    }
</style>

<!-- Hero Section -->
<div class="hero">
    <div>
        <h1>Marryshow Mealhouse</h1>
        <p>Fresh. Local. Affordable.<br>Serving the T.A. Marryshow Community College since 2024.</p>
        <a href="<?= kiosk_url('menu.php') ?>" class="btn btn-accent">View Our Menu</a>
    </div>
</div>

<!-- Why Choose Us -->
<section class="section">
    <h2>Why Students Love Us</h2>
    <div class="features">
        <div class="feature">
            <img src="https://cdn-icons-png.flaticon.com/512/1046/1046857.png" alt="Fresh">
            <h3>Fresh Ingredients</h3>
            <p>We source locally from Grenadian farmers for the freshest meals on campus.</p>
        </div>
        <div class="feature">
            <img src="https://cdn-icons-png.flaticon.com/512/2331/2331966.png" alt="Affordable">
            <h3>Student Budget</h3>
            <p>Delicious meals from $0.50 breakfast bakes to hearty combos.</p>
        </div>
        <div class="feature">
            <img src="https://cdn-icons-png.flaticon.com/512/1903/1903162.png" alt="Convenient">
            <h3>Right on Campus</h3>
            <p>Located in the heart of Tanteen, perfect for a quick bite between classes.</p>
        </div>
    </div>
</section>

<!-- Menu Preview -->
<section class="section menu-preview">
    <h2>Popular Picks</h2>
    <div class="menu-grid">
        <?php if (!empty($popular_items)): ?>
            <?php foreach ($popular_items as $item): ?>
            <div class="menu-card">
                <?php if (!empty($item['image'])): ?>
                    <img src="<?= htmlspecialchars($item['image']) ?>" alt="<?= htmlspecialchars($item['name']) ?>">
                <?php else: ?>
                    <img src="https://images.unsplash.com/photo-1546069901-ba9599a7e63c?w=500&auto=format" alt="Food">
                <?php endif; ?>
                <div class="card-content">
                    <h3><?= htmlspecialchars($item['name']) ?></h3>
                    <div class="price">$<?= number_format($item['price'], 2) ?></div>
                    <a href="<?= kiosk_url('menu.php') ?>" class="btn btn-small">Order Now</a>
                </div>
            </div>
            <?php endforeach; ?>
        <?php else: ?>
            <!-- Fallback items when no popular data exists -->
            <div class="menu-card">
                <img src="https://images.unsplash.com/photo-1565299624946-b28f40a0ae38?w=500&auto=format" alt="Pizza">
                <div class="card-content">
                    <h3>Chef's Special Pizza</h3>
                    <div class="price">$12.99</div>
                    <a href="<?= kiosk_url('menu.php') ?>" class="btn btn-small">Order Now</a>
                </div>
            </div>
            <div class="menu-card">
                <img src="https://images.unsplash.com/photo-1546069901-ba9599a7e63c?w=500&auto=format" alt="Salad">
                <div class="card-content">
                    <h3>Island Salad Bowl</h3>
                    <div class="price">$8.50</div>
                    <a href="<?= kiosk_url('menu.php') ?>" class="btn btn-small">Order Now</a>
                </div>
            </div>
            <div class="menu-card">
                <img src="https://images.unsplash.com/photo-1606755962773-d324e0c130d2?w=500&auto=format" alt="Burger">
                <div class="card-content">
                    <h3>Marryshow Burger</h3>
                    <div class="price">$10.75</div>
                    <a href="<?= kiosk_url('menu.php') ?>" class="btn btn-small">Order Now</a>
                </div>
            </div>
        <?php endif; ?>
    </div>
    <div style="text-align: center; margin-top: 40px;">
        <a href="<?= kiosk_url('menu.php') ?>" class="btn">View Full Menu</a>
    </div>
</section>

<!-- About -->
<section class="section">
    <h2>About Marryshow Mealhouse</h2>
    <div class="about-content">
        <div class="about-text">
            <p>Welcome to TAMCC Deli – your on‑campus dining destination at T.A. Marryshow Community College. We believe that good food fuels great minds, and we're dedicated to providing students, faculty, and staff with delicious, nutritious, and affordable meals.</p>
            <p>Our menu features a mix of local favourites and student‑tested classics, from hearty breakfast bakes to satisfying lunch combos. We use fresh ingredients sourced from Grenadian farmers whenever possible, and we're always open to your feedback.</p>
            <p>Stop by the Tanteen campus to grab a bite, or order online for pickup between classes. We can't wait to serve you!</p>
        </div>
        <div class="about-image">
            <img src="https://images.unsplash.com/photo-1522071820081-009f0129c71c?ixlib=rb-1.2.1&auto=format&fit=crop&w=1350&q=80" alt="TAMCC Campus">
        </div>
    </div>
</section>

<!-- Hours -->
<section class="hours">
    <h2>Opening Hours</h2>
    <div class="hours-grid">
        <div class="hours-item"><h4>Monday–Friday</h4><p>7:30 AM – 4:00 PM</p></div>
        <div class="hours-item"><h4>Saturday & Sunday</h4><p>Closed</p></div>
        <div class="hours-item"><h4>Holidays</h4><p>Check Facebook</p></div>
    </div>
</section>

<!-- Install App Button (only in normal mode) -->
<?php if (!$kiosk_mode): ?>
    <div id="install-container" style="display: none; position: fixed; bottom: 20px; left: 20px; z-index: 1000;">
        <button id="install-app" class="btn btn-primary">📱 Install App</button>
    </div>
    <script>
        let deferredPrompt;
        window.addEventListener('beforeinstallprompt', (e) => {
            e.preventDefault();
            deferredPrompt = e;
            document.getElementById('install-container').style.display = 'block';
        });
        document.getElementById('install-app').addEventListener('click', () => {
            deferredPrompt.prompt();
            deferredPrompt.userChoice.then((choiceResult) => {
                if (choiceResult.outcome === 'accepted') {
                    console.log('User accepted the install prompt');
                }
                deferredPrompt = null;
                document.getElementById('install-container').style.display = 'none';
            });
        });
    </script>
<?php endif; ?>

<?php include 'includes/footer.php'; ?>