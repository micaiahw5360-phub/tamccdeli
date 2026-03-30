<?php
require __DIR__ . '/includes/session.php';
require "config/database.php";
require "includes/functions.php";

$page_title = "TAMCC Deli | Marryshow Mealhouse";
$popular_items = getPopularItems($conn, 3);
include 'includes/header.php';
?>

<!-- Hero Section -->
<section class="hero" style="background-image: url('https://images.unsplash.com/photo-1641772094405-6a7db15e8291?crop=entropy&cs=tinysrgb&fit=max&fm=jpg&w=1920&h=1080');">
    <div class="hero-overlay"></div>
    <div class="hero-content">
        <h1>Welcome to Marryshow Mealhouse</h1>
        <p>Fresh, affordable meals made daily for the TAMCC community. Order online and skip the line!</p>
        <a href="<?= kiosk_url('menu.php') ?>" class="btn btn-accent btn-lg">View Our Menu</a>
    </div>
</section>

<!-- Features -->
<section class="features">
    <div class="container">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
            <div class="feature-card">
                <div class="feature-icon">🌿</div>
                <h3 class="text-xl font-bold mb-2">Fresh Ingredients</h3>
                <p class="text-gray-600">We use only the freshest local ingredients to prepare your meals daily.</p>
            </div>
            <div class="feature-card">
                <div class="feature-icon">💰</div>
                <h3 class="text-xl font-bold mb-2">Student Budget</h3>
                <p class="text-gray-600">Affordable prices designed with students in mind. Great value, great taste!</p>
            </div>
            <div class="feature-card">
                <div class="feature-icon">📍</div>
                <h3 class="text-xl font-bold mb-2">Right on Campus</h3>
                <p class="text-gray-600">Conveniently located in the heart of TAMCC. Quick pickup between classes.</p>
            </div>
        </div>
    </div>
</section>

<!-- Popular Items -->
<section class="py-12 bg-gray-50">
    <div class="container">
        <div class="text-center mb-8">
            <h2 class="text-3xl font-bold mb-2">Popular Menu Items</h2>
            <p class="text-gray-600">Try our customer favorites</p>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
            <?php if (!empty($popular_items)): ?>
                <?php foreach ($popular_items as $item): ?>
                    <div class="card">
                        <img src="<?= htmlspecialchars($item['image'] ?? 'https://images.unsplash.com/photo-1546069901-ba9599a7e63c?w=500') ?>" alt="<?= htmlspecialchars($item['name']) ?>" class="w-full h-48 object-cover">
                        <div class="card-content">
                            <h3 class="text-xl font-bold mb-2"><?= htmlspecialchars($item['name']) ?></h3>
                            <p class="text-gray-600 text-sm mb-4"><?= htmlspecialchars($item['description'] ?? '') ?></p>
                            <div class="flex justify-between items-center">
                                <span class="text-2xl font-bold text-primary">$<?= number_format($item['price'], 2) ?></span>
                                <a href="<?= kiosk_url('menu.php') ?>" class="btn btn-accent btn-sm">Order Now</a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <!-- Fallback items -->
                <div class="card">
                    <img src="https://images.unsplash.com/photo-1565299624946-b28f40a0ae38?w=500" alt="Pizza" class="w-full h-48 object-cover">
                    <div class="card-content">
                        <h3 class="text-xl font-bold mb-2">Chef's Special Pizza</h3>
                        <p class="text-gray-600 text-sm mb-4">Delicious pizza with fresh toppings</p>
                        <div class="flex justify-between items-center">
                            <span class="text-2xl font-bold text-primary">$12.99</span>
                            <a href="<?= kiosk_url('menu.php') ?>" class="btn btn-accent btn-sm">Order Now</a>
                        </div>
                    </div>
                </div>
                <!-- Add more fallback items if needed -->
            <?php endif; ?>
        </div>
        <div class="text-center mt-8">
            <a href="<?= kiosk_url('menu.php') ?>" class="btn btn-outline btn-lg">View Full Menu</a>
        </div>
    </div>
</section>

<!-- About -->
<section class="py-12">
    <div class="container">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-8 items-center">
            <div>
                <h2 class="text-3xl font-bold mb-4">About Marryshow Mealhouse</h2>
                <p class="text-gray-600 mb-4">Located in the heart of T.A. Marryshow Community College, our cafeteria has been serving the TAMCC community for over a decade. We're committed to providing nutritious, delicious, and affordable meals to students, staff, and faculty.</p>
                <p class="text-gray-600 mb-6">Our online ordering system makes it easy to skip the line and get your food when you need it. Simply browse our menu, place your order, and pick it up at your convenience.</p>
                <a href="<?= kiosk_url('auth/register.php') ?>" class="btn btn-primary">Get Started Today</a>
            </div>
            <div>
                <img src="https://images.unsplash.com/photo-1522071820081-009f0129c71c?w=600" alt="Cafeteria" class="rounded-lg shadow-lg w-full">
            </div>
        </div>
    </div>
</section>

<!-- Hours -->
<section class="bg-primary-600 text-white py-12">
    <div class="container text-center">
        <div class="flex justify-center mb-4">
            <span class="dashicons dashicons-clock" style="font-size: 3rem;"></span>
        </div>
        <h2 class="text-3xl font-bold mb-8">Opening Hours</h2>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-8 max-w-2xl mx-auto">
            <div>
                <h3 class="font-bold text-accent-500 mb-2">Weekdays</h3>
                <p>Monday - Friday</p>
                <p class="text-lg">7:00 AM - 6:00 PM</p>
            </div>
            <div>
                <h3 class="font-bold text-accent-500 mb-2">Weekends</h3>
                <p>Saturday</p>
                <p class="text-lg">8:00 AM - 2:00 PM</p>
                <p class="text-sm text-white/70 mt-2">Closed Sunday</p>
            </div>
        </div>
    </div>
</section>

<?php include 'includes/footer.php'; ?>