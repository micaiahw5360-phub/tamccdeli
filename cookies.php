<?php
require __DIR__ . '/includes/session.php';
$page_title = "Cookie Policy | TAMCC Deli";
include 'includes/header.php';
?>

<div class="container">
    <h1>Cookie Policy</h1>
    <p class="text-muted">Last updated: <?php echo date('F j, Y'); ?></p>
    <div class="card">
        <div class="card-icon" style="font-size: 4rem; text-align: center; margin-bottom: 1rem;">🍪</div>
        
        <h2>What Are Cookies</h2>
        <p>Cookies are small text files stored on your device when you visit a website. They help us remember your preferences and improve your browsing experience.</p>

        <h2>How We Use Cookies</h2>
        <p>We use only essential cookies to:</p>
        <ul style="margin-left: 20px; margin-bottom: 15px;">
            <li>Keep you logged in</li>
            <li>Remember items in your shopping cart</li>
            <li>Maintain session security</li>
        </ul>
        <p>We do not use tracking or advertising cookies.</p>

        <div class="cookie-types" style="background: var(--neutral-100); padding: 1rem; border-radius: var(--radius); margin: 1rem 0;">
            <span class="dashicons dashicons-shield-alt" style="font-size: 2rem;"></span>
            <h3>Your Privacy Matters</h3>
            <p>We never sell your data. Our cookies are strictly necessary for the website to function properly.</p>
        </div>

        <h2>Managing Cookies</h2>
        <p>You can disable cookies through your browser settings. However, some features of our site (such as the shopping cart) may not function properly without them.</p>

        <h2>More Information</h2>
        <p>For questions about our cookie use, please contact us at <a href="mailto:deli@tamcc.edu.gd">deli@tamcc.edu.gd</a>.</p>
    </div>
</div>

<?php include 'includes/footer.php'; ?>