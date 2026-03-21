<?php
require __DIR__ . '/includes/session.php';
$page_title = "Help & FAQ | TAMCC Deli";
include 'includes/header.php';
?>

<style>
    .faq-item {
        margin-bottom: 1.5rem;
        border-bottom: 1px solid var(--neutral-200);
        padding-bottom: 1.5rem;
    }
    .faq-item h3 {
        margin-bottom: 0.5rem;
        color: var(--primary-600);
    }
    .faq-item p {
        margin-left: 1rem;
    }
</style>

<div class="container">
    <h1>Help & Frequently Asked Questions</h1>
    <div class="card">
        <div class="faq-item">
            <h3>How do I place an order?</h3>
            <p>Browse our menu, add items to your cart, then proceed to checkout. You'll need to be logged in to complete the order.</p>
        </div>
        <div class="faq-item">
            <h3>What payment methods do you accept?</h3>
            <p>We accept cash on pickup and simulated online payment (for demonstration purposes).</p>
        </div>
        <div class="faq-item">
            <h3>Can I cancel my order?</h3>
            <p>You may cancel within 15 minutes of placing the order by contacting us directly. After that, it is final.</p>
        </div>
        <div class="faq-item">
            <h3>How do I view my order history?</h3>
            <p>Log in and go to your Dashboard → My Orders.</p>
        </div>
        <div class="faq-item">
            <h3>I forgot my password. What should I do?</h3>
            <p>On the login page, click "Forgot Password" (if implemented). Currently, please contact us to reset it manually.</p>
        </div>
        <div class="faq-item">
            <h3>Where are you located?</h3>
            <p>We are on the Tanteen campus of T.A. Marryshow Community College, Grenada.</p>
        </div>
        <div class="faq-item">
            <h3>Can I order for pickup later?</h3>
            <p>Yes, you can specify a preferred pickup time during checkout.</p>
        </div>
    </div>
    <p>Still need help? <a href="mailto:deli@tamcc.edu.gd">Email us</a> or call +1 (473) 440-1234 ext. 789.</p>
</div>

<?php include 'includes/footer.php'; ?>