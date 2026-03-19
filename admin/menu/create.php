<?php
require __DIR__ . '/../../middleware/admin_check.php';
require __DIR__ . '/../../config/database.php';
require __DIR__ . '/../../includes/csrf.php';

$error = '';
$success = '';
$categories = ['Breakfast', 'A La Carte', 'Combo', 'Beverage', 'Dessert'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateToken($_POST['csrf_token'])) {
        die('Invalid CSRF token');
    }

    $name = trim($_POST['name']);
    $category = $_POST['category'];
    $price = floatval($_POST['price']);
    $image = trim($_POST['image']) ?: null;
    $sort_order = intval($_POST['sort_order']);

    if (empty($name) || $price <= 0 || empty($category)) {
        $error = 'Name, category, and a valid price are required.';
    } else {
        $stmt = $conn->prepare("INSERT INTO menu_items (name, category, price, image, sort_order) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("ssdsi", $name, $category, $price, $image, $sort_order);
        if ($stmt->execute()) {
            $redirect = "index.php";
            if (isset($_SESSION['kiosk_mode']) && $_SESSION['kiosk_mode']) {
                $redirect .= '?kiosk=1';
            }
            header("Location: $redirect");
            exit;
        } else {
            $error = 'Database error: ' . $conn->error;
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Add Menu Item</title>
    <link rel="stylesheet" href="../../assets/css/global.css">
    <style>
        .admin-container { max-width: 600px; margin: 30px auto; background: white; padding: 30px; border-radius: 10px; box-shadow: var(--shadow); }
        .form-group { margin-bottom: 15px; }
        label { display: block; font-weight: 600; margin-bottom: 5px; }
        input, select, textarea { width: 100%; padding: 10px; border: 1px solid var(--neutral-300); border-radius: var(--radius); font-size: 1rem; }
        .btn { background: var(--primary-600); color: white; padding: 10px 20px; border: none; border-radius: var(--radius); cursor: pointer; }
        .btn:hover { background: var(--primary-700); }
        .btn-secondary { background: var(--neutral-500); }
        .btn-secondary:hover { background: var(--neutral-600); }
        .error { color: var(--danger); }
        .success { color: var(--success); }
        .small-note { font-size: 0.85rem; color: var(--neutral-500); margin-top: 5px; }
        #image-preview { max-width: 200px; max-height: 200px; margin-top: 10px; border: 1px solid var(--neutral-200); border-radius: var(--radius); display: none; }
    </style>
</head>
<body>
<div class="admin-container">
    <h1>Add New Menu Item</h1>
    <?php if ($error): ?><div class="error"><?= $error ?></div><?php endif; ?>
    <?php if ($success): ?><div class="success"><?= $success ?></div><?php endif; ?>
    <form method="POST" id="menu-form">
        <input type="hidden" name="csrf_token" value="<?= generateToken() ?>">
        
        <div class="form-group">
            <label>Name *</label>
            <input type="text" name="name" id="name" required>
        </div>
        
        <div class="form-group">
            <label>Category *</label>
            <select name="category" required>
                <option value="">Select Category</option>
                <?php foreach ($categories as $cat): ?>
                    <option value="<?= $cat ?>"><?= $cat ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        
        <div class="form-group">
            <label>Price *</label>
            <input type="number" step="0.01" name="price" id="price" required>
        </div>
        
        <div class="form-group">
            <label>Image URL (optional)</label>
            <input type="url" name="image" id="image-url" placeholder="https://example.com/image.jpg">
            <div class="small-note">Provide a direct link to an image (e.g., from Unsplash or your own uploads).</div>
            <img id="image-preview" src="#" alt="Image preview">
        </div>
        
        <div class="form-group">
            <label>Sort Order</label>
            <input type="number" name="sort_order" value="0">
        </div>
        
        <button type="submit" class="btn">Save Item</button>
        <a href="<?= normal_url('index.php') ?>" class="btn btn-secondary">Cancel</a>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Image preview
    const imageUrlInput = document.getElementById('image-url');
    const preview = document.getElementById('image-preview');
    if (imageUrlInput) {
        imageUrlInput.addEventListener('input', function() {
            const url = this.value.trim();
            if (url) {
                preview.src = url;
                preview.style.display = 'block';
                preview.onerror = function() {
                    preview.style.display = 'none';
                };
            } else {
                preview.style.display = 'none';
            }
        });
    }

    // Client-side validation
    const form = document.getElementById('menu-form');
    form.addEventListener('submit', function(e) {
        const name = document.getElementById('name').value.trim();
        const price = parseFloat(document.getElementById('price').value);
        if (!name) {
            alert('Please enter a name.');
            e.preventDefault();
            return;
        }
        if (isNaN(price) || price <= 0) {
            alert('Please enter a valid price greater than 0.');
            e.preventDefault();
            return;
        }
    });
});
</script>
</body>
</html>