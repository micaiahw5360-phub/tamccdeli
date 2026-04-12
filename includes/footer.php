<?php
// Determine if this page is an admin, staff, or dashboard panel
$is_admin_panel = strpos($_SERVER['SCRIPT_NAME'], '/admin/') !== false;
$is_staff_panel = strpos($_SERVER['SCRIPT_NAME'], '/staff/') !== false;
$is_dashboard = strpos($_SERVER['SCRIPT_NAME'], '/dashboard/') !== false;
$hide_footer = $is_admin_panel || $is_staff_panel || $is_dashboard;

if (!$hide_footer):
?>
<footer class="footer">
    <div class="footer-container">
        <div class="footer-section">
            <h3>About TAMCC Deli</h3>
            <img src="/assets/images/About-Us.png" alt="TAMCC Logo" style="max-width: 150px; margin-bottom: 10px;">
            <p>Fuel your studies with fresh, local, and affordable meals at T.A. Marryshow Community College. We're here to serve students, staff, and faculty.</p>
            <div class="social-links">
                <a href="#" aria-label="Facebook" class="dashicons dashicons-facebook-alt"></a>
                <a href="#" aria-label="Instagram" class="dashicons dashicons-instagram"></a>
                <a href="#" aria-label="Twitter" class="dashicons dashicons-twitter"></a>
            </div>
        </div>

        <div class="footer-section">
            <h3>Quick Links</h3>
            <ul>
                <li><a href="<?= kiosk_url('menu.php') ?>"><span class="dashicons dashicons-menu"></span> Our Menu</a></li>
                <li><a href="<?= kiosk_url('cart.php') ?>"><span class="dashicons dashicons-cart"></span> Cart</a></li>
                <?php if (isset($_SESSION['user_id'])): ?>
                    <li><a href="<?= kiosk_url('dashboard/index.php') ?>"><span class="dashicons dashicons-dashboard"></span> Dashboard</a></li>
                <?php else: ?>
                    <li><a href="<?= kiosk_url('auth/login.php') ?>"><span class="dashicons dashicons-lock"></span> Login</a></li>
                    <li><a href="<?= kiosk_url('auth/register.php') ?>"><span class="dashicons dashicons-edit"></span> Register</a></li>
                <?php endif; ?>
            </ul>
        </div>

        <div class="footer-section">
            <h3>Legal & Info</h3>
            <ul>
                <li><a href="<?= kiosk_url('terms.php') ?>"><span class="dashicons dashicons-media-text"></span> Terms & Conditions</a></li>
                <li><a href="<?= kiosk_url('privacy.php') ?>"><span class="dashicons dashicons-privacy"></span> Privacy Policy</a></li>
                <li><a href="<?= kiosk_url('cookies.php') ?>"><span class="dashicons dashicons-admin-generic"></span> Cookie Policy</a></li>
                <li><a href="<?= kiosk_url('accessibility.php') ?>"><span class="dashicons dashicons-universal-access"></span> Accessibility</a></li>
                <li><a href="<?= kiosk_url('help.php') ?>"><span class="dashicons dashicons-editor-help"></span> Help / FAQ</a></li>
            </ul>
        </div>

        <div class="footer-section">
            <h3>Contact Us</h3>
            <p><span class="dashicons dashicons-phone"></span> +1 (473) 440-1234 ext. 789</p>
            <p><span class="dashicons dashicons-email"></span> deli@tamcc.edu.gd</p>
            <p><span class="dashicons dashicons-location"></span> Tanteen Campus, Grenada</p>
        </div>
    </div>

    <div class="footer-bottom">
        <p>&copy; <?php echo date('Y'); ?> T.A. Marryshow Community College – Marryshow's Mealhouse. All rights reserved.</p>
        <p>
            <a href="<?= kiosk_url('terms.php') ?>">Terms</a> | 
            <a href="<?= kiosk_url('privacy.php') ?>">Privacy</a> | 
            <a href="<?= kiosk_url('cookies.php') ?>">Cookies</a> | 
            <a href="<?= kiosk_url('accessibility.php') ?>">Accessibility</a> | 
            <a href="<?= kiosk_url('help.php') ?>">Help</a>
        </p>
    </div>

    <?php if (isset($kiosk_mode) && $kiosk_mode): ?>
        <div style="text-align:center; margin:10px;">
            <a href="?kiosk=0" class="btn-small">Exit Kiosk Mode (Staff)</a>
        </div>
    <?php endif; ?>
</footer>
<?php endif; ?>

<!-- Always include toast container -->
<div id="toast-container"></div>

<?php if ($kiosk_mode): ?>
    <script src="/assets/js/kiosk.js"></script>
<?php else: ?>
    <script src="/assets/js/script.js"></script>
<?php endif; ?>

<?php if (!$hide_footer): ?>
    <!-- JavaScript fallback to ensure every internal link has a query string (only needed in normal mode) -->
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        var kiosk = typeof kioskMode !== 'undefined' ? kioskMode : false;
        document.querySelectorAll('a[href^="/"]').forEach(function(link) {
            var href = link.getAttribute('href');
            if (href.indexOf('?') === -1) {
                href += '?';
            }
            if (kiosk && href.indexOf('kiosk=') === -1) {
                href += (href.endsWith('?') ? '' : '&') + 'kiosk=1';
            }
            link.setAttribute('href', href);
        });
    });
    </script>
<?php endif; ?>
</body>
</html>-