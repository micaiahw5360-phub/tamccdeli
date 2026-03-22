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
        display: flex;
        gap: 1rem;
    }
    .faq-icon {
        font-size: 1.8rem;
        color: var(--primary-600);
        flex-shrink: 0;
    }
    .faq-content h3 {
        margin-bottom: 0.5rem;
        color: var(--primary-600);
    }
    .faq-content p {
        margin: 0;
    }
    .contact-box {
        background: var(--neutral-100);
        border-radius: var(--radius);
        padding: 1.5rem;
        text-align: center;
        margin-top: 2rem;
    }
</style>

<div class="container">
    <h1>Help & Frequently Asked Questions</h1>
    <div class="card">
        <div class="faq-item">
            <div class="faq-icon"><span class="dashicons dashicons-cart"></span></div>
            <div class="faq-content">
                <h3>How do I place an order?</h3>
                <p>Browse our menu, add items to your cart, then proceed to checkout. You'll need to be logged in to complete the order.</p>
            </div>
        </div>
        <div class="faq-item">
            <div class="faq-icon"><span class="dashicons dashicons-money"></span></div>
            <div class="faq-content">
                <h3>What payment methods do you accept?</h3>
                <p>We accept cash on pickup, wallet balance, and online card payments (Stripe).</p>
            </div>
        </div>
        <div class="faq-item">
            <div class="faq-icon"><span class="dashicons dashicons-no-alt"></span></div>
            <div class="faq-content">
                <h3>Can I cancel my order?</h3>
                <p>You may cancel within 15 minutes of placing the order by contacting us directly. After that, it is final.</p>
            </div>
        </div>
        <div class="faq-item">
            <div class="faq-icon"><span class="dashicons dashicons-list-view"></span></div>
            <div class="faq-content">
                <h3>How do I view my order history?</h3>
                <p>Log in and go to your Dashboard → My Orders.</p>
            </div>
        </div>
        <div class="faq-item">
            <div class="faq-icon"><span class="dashicons dashicons-lock"></span></div>
            <div class="faq-content">
                <h3>I forgot my password. What should I do?</h3>
                <p>On the login page, click "Forgot Password" to receive a reset link via email.</p>
            </div>
        </div>
        <div class="faq-item">
            <div class="faq-icon"><span class="dashicons dashicons-location"></span></div>
            <div class="faq-content">
                <h3>Where are you located?</h3>
                <p>We are on the Tanteen campus of T.A. Marryshow Community College, Grenada.</p>
            </div>
        </div>
        <div class="faq-item">
            <div class="faq-icon"><span class="dashicons dashicons-calendar-alt"></span></div>
            <div class="faq-content">
                <h3>Can I order for pickup later?</h3>
                <p>Yes, you can specify a preferred pickup time during checkout.</p>
            </div>
        </div>

        <div class="contact-box">
            <span class="dashicons dashicons-email" style="font-size: 2rem;"></span>
            <h3>Still need help?</h3>
            <p><a href="mailto:deli@tamcc.edu.gd">Email us</a> or call +1 (473) 440-1234 ext. 789. We're happy to assist!</p>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>