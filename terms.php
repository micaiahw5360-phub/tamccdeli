<?php
require __DIR__ . '/includes/session.php';
$page_title = "Terms & Conditions | TAMCC Deli";
include 'includes/header.php';
?>

<div class="container">
    <h1>Terms and Conditions</h1>
    <p class="text-muted">Last updated: <?php echo date('F j, Y'); ?></p>
    <div class="card">
        <div class="card-icon" style="font-size: 4rem; text-align: center; margin-bottom: 1rem;">⚖️</div>
        
        <h2>1. Introduction</h2>
        <p>Welcome to Marryshow's Mealhouse. By using our website and services, you agree to these terms.</p>

        <h2>2. Eligibility</h2>
        <p>You must be a student, staff, or faculty member of T.A. Marryshow Community College to place orders. Orders are subject to availability.</p>

        <h2>3. Payments</h2>
        <p>Payments can be made via cash on pickup, wallet balance, or online card payment (Stripe). All payments are final once processed.</p>

        <h2>4. Order Cancellation</h2>
        <p>Orders may be cancelled within 15 minutes of placement by contacting us directly. After that, they are considered final.</p>

        <h2>5. Contact</h2>
        <p>For any questions, please contact us at <a href="mailto:deli@tamcc.edu.gd">deli@tamcc.edu.gd</a> or call +1 (473) 440-1234 ext. 789.</p>

        <div style="background: var(--neutral-100); padding: 1rem; border-radius: var(--radius); margin-top: 2rem;">
            <span class="dashicons dashicons-warning" style="font-size: 1.5rem;"></span>
            <p style="margin: 0;"><strong>Note:</strong> We reserve the right to update these terms at any time. Continued use of the site constitutes acceptance of the latest version.</p>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>