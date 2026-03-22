<?php
require __DIR__ . '/includes/session.php';
$page_title = "Privacy Policy | TAMCC Deli";
include 'includes/header.php';
?>

<div class="container">
    <h1>Privacy Policy</h1>
    <p class="text-muted">Last updated: <?php echo date('F j, Y'); ?></p>
    <div class="card">
        <div class="card-icon" style="font-size: 4rem; text-align: center; margin-bottom: 1rem;">🔒</div>

        <div class="privacy-highlight" style="background: var(--neutral-100); padding: 1rem; border-radius: var(--radius); margin: 1rem 0;">
            <span class="dashicons dashicons-shield-alt" style="font-size: 1.5rem;"></span>
            <strong>We respect your privacy.</strong> Your data is safe with us.
        </div>

        <h2>1. Information We Collect</h2>
        <p>We collect personal information such as your name, email address, and order history when you register or place an order through our website. This information is used solely to provide and improve our services.</p>

        <h2>2. How We Use Your Information</h2>
        <p>Your information helps us process orders, communicate with you about your orders, and enhance your experience on our site. We do not sell or share your personal data with third parties except as required by law.</p>

        <h2>3. Data Security</h2>
        <p>We implement reasonable security measures to protect your personal information. However, no method of transmission over the Internet is 100% secure.</p>

        <h2>4. Cookies</h2>
        <p>We use cookies to remember your cart items and login sessions. By using our website, you consent to our use of cookies as described in our <a href="cookies.php">Cookie Policy</a>.</p>

        <h2>5. Changes to This Policy</h2>
        <p>We may update this policy from time to time. We will notify you of any changes by posting the new policy on this page.</p>

        <h2>6. Contact Us</h2>
        <p>If you have any questions about this privacy policy, please contact us at <a href="mailto:deli@tamcc.edu.gd">deli@tamcc.edu.gd</a>.</p>
    </div>
</div>

<?php include 'includes/footer.php'; ?>