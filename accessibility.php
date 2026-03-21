<?php
require __DIR__ . '/includes/session.php';
$page_title = "Accessibility | TAMCC Deli";
include 'includes/header.php';
?>

<div class="container">
    <h1>Accessibility Statement</h1>
    <p class="text-muted">Last updated: <?php echo date('F j, Y'); ?></p>
    <div class="card">
        <h2>Our Commitment</h2>
        <p>TAMCC Deli is committed to ensuring digital accessibility for all users, including individuals with disabilities. We are continuously improving the user experience and applying relevant accessibility standards.</p>

        <h2>Conformance Status</h2>
        <p>We aim to conform to the Web Content Accessibility Guidelines (WCAG) 2.1 Level AA. These guidelines explain how to make web content more accessible for people with disabilities.</p>

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