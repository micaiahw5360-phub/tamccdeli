<?php
require __DIR__ . '/includes/session.php';
$page_title = "Accessibility | TAMCC Deli";
include 'includes/header.php';
?>

<div class="container">
    <h1>Accessibility Statement</h1>
    <p class="text-muted">Last updated: <?php echo date('F j, Y'); ?></p>
    <div class="card">
        <div class="card-icon" style="font-size: 4rem; text-align: center; margin-bottom: 1rem;">♿</div>
        <h2>Our Commitment</h2>
        <p>TAMCC Deli is committed to ensuring digital accessibility for all users, including individuals with disabilities. We are continuously improving the user experience and applying relevant accessibility standards.</p>

        <h2>Conformance Status</h2>
        <p>We aim to conform to the Web Content Accessibility Guidelines (WCAG) 2.1 Level AA. These guidelines explain how to make web content more accessible for people with disabilities.</p>

        <div class="row" style="display: flex; gap: 1.5rem; flex-wrap: wrap; margin: 1.5rem 0;">
            <div style="flex: 1; background: var(--neutral-100); padding: 1rem; border-radius: var(--radius);">
                <span class="dashicons dashicons-visibility" style="font-size: 2rem;"></span>
                <h3>Accessible Design</h3>
                <p>We use high contrast, scalable fonts, and clear navigation.</p>
            </div>
            <div style="flex: 1; background: var(--neutral-100); padding: 1rem; border-radius: var(--radius);">
                <span class="dashicons dashicons-smartphone" style="font-size: 2rem;"></span>
                <h3>Responsive Layout</h3>
                <p>Our site works on all devices and screen sizes.</p>
            </div>
            <div style="flex: 1; background: var(--neutral-100); padding: 1rem; border-radius: var(--radius);">
                <span class="dashicons dashicons-keyboard" style="font-size: 2rem;"></span>
                <h3>Keyboard Navigation</h3>
                <p>All interactive elements can be accessed via keyboard.</p>
            </div>
        </div>

        <h2>Feedback</h2>
        <p>We welcome your feedback. If you encounter any accessibility barriers, please contact us:</p>
        <ul>
            <li>Email: <a href="mailto:deli@tamcc.edu.gd">deli@tamcc.edu.gd</a></li>
            <li>Phone: +1 (473) 440-1234 ext. 789</li>
        </ul>

        <h2>Technical Specifications</h2>
        <p>Our site relies on the following technologies to work with assistive technologies:</p>
        <ul>
            <li>HTML5</li>
            <li>CSS3</li>
            <li>PHP</li>
            <li>JavaScript (limited)</li>
        </ul>
    </div>
</div>

<?php include 'includes/footer.php'; ?>