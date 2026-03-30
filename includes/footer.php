<?php
$is_admin_panel = strpos($_SERVER['SCRIPT_NAME'], '/admin/') !== false;
$is_staff_panel = strpos($_SERVER['SCRIPT_NAME'], '/staff/') !== false;
$is_dashboard = strpos($_SERVER['SCRIPT_NAME'], '/dashboard/') !== false;
$hide_footer = $is_admin_panel || $is_staff_panel || $is_dashboard;

if (!$hide_footer):
?>
<footer class="footer">
    <div class="container">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-8 py-12">
            <div>
                <h3 class="text-lg font-bold mb-4">About TAMCC Deli</h3>
                <img src="/assets/images/About-Us.png" alt="TAMCC Logo" style="max-width: 150px; margin-bottom: 10px;">
                <p class="text-gray-600 text-sm">Fuel your studies with fresh, local, and affordable meals at T.A. Marryshow Community College.</p>
                <div class="flex gap-4 mt-4">
                    <a href="#" class="text-gray-500 hover:text-primary"><span class="dashicons dashicons-facebook-alt"></span></a>
                    <a href="#" class="text-gray-500 hover:text-primary"><span class="dashicons dashicons-instagram"></span></a>
                    <a href="#" class="text-gray-500 hover:text-primary"><span class="dashicons dashicons-twitter"></span></a>
                </div>
            </div>
            <div>
                <h3 class="text-lg font-bold mb-4">Quick Links</h3>
                <ul class="space-y-2">
                    <li><a href="<?= kiosk_url('menu.php') ?>" class="text-gray-600 hover:text-primary">Our Menu</a></li>
                    <li><a href="<?= kiosk_url('cart.php') ?>" class="text-gray-600 hover:text-primary">Cart</a></li>
                    <?php if (isset($_SESSION['user_id'])): ?>
                        <li><a href="<?= kiosk_url('dashboard/index.php') ?>" class="text-gray-600 hover:text-primary">Dashboard</a></li>
                    <?php else: ?>
                        <li><a href="<?= kiosk_url('auth/login.php') ?>" class="text-gray-600 hover:text-primary">Login</a></li>
                        <li><a href="<?= kiosk_url('auth/register.php') ?>" class="text-gray-600 hover:text-primary">Register</a></li>
                    <?php endif; ?>
                </ul>
            </div>
            <div>
                <h3 class="text-lg font-bold mb-4">Legal & Info</h3>
                <ul class="space-y-2">
                    <li><a href="<?= kiosk_url('terms.php') ?>" class="text-gray-600 hover:text-primary">Terms & Conditions</a></li>
                    <li><a href="<?= kiosk_url('privacy.php') ?>" class="text-gray-600 hover:text-primary">Privacy Policy</a></li>
                    <li><a href="<?= kiosk_url('cookies.php') ?>" class="text-gray-600 hover:text-primary">Cookie Policy</a></li>
                    <li><a href="<?= kiosk_url('accessibility.php') ?>" class="text-gray-600 hover:text-primary">Accessibility</a></li>
                    <li><a href="<?= kiosk_url('help.php') ?>" class="text-gray-600 hover:text-primary">Help / FAQ</a></li>
                </ul>
            </div>
            <div>
                <h3 class="text-lg font-bold mb-4">Contact Us</h3>
                <ul class="space-y-2 text-gray-600">
                    <li class="flex items-center gap-2"><span class="dashicons dashicons-phone"></span> +1 (473) 440-1234 ext. 789</li>
                    <li class="flex items-center gap-2"><span class="dashicons dashicons-email"></span> deli@tamcc.edu.gd</li>
                    <li class="flex items-center gap-2"><span class="dashicons dashicons-location"></span> Tanteen Campus, Grenada</li>
                </ul>
            </div>
        </div>
        <div class="border-t border-gray-200 py-6 text-center text-gray-500 text-sm">
            <p>&copy; <?php echo date('Y'); ?> T.A. Marryshow Community College – Marryshow Mealhouse. All rights reserved.</p>
            <?php if (isset($kiosk_mode) && $kiosk_mode): ?>
                <div class="mt-2">
                    <a href="?kiosk=0" class="text-primary hover:underline">Exit Kiosk Mode (Staff)</a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</footer>
<?php endif; ?>

<div id="toast-container" class="toast-container"></div>
<script src="/assets/js/script.js"></script>
</body>
</html>