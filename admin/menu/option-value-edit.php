<?php
require __DIR__ . '/../../middleware/admin_check.php';
require __DIR__ . '/../../config/database.php';
require __DIR__ . '/../../includes/csrf.php';

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if (!$id) {
    header('Location: ' . normal_url('index.php'));
    exit;
}

// Fetch value details
$stmt = $conn->prepare("SELECT v.*, o.menu_item_id FROM menu_item_option_values v JOIN menu_item_options o ON v.option_id = o.id WHERE v.id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$value = $stmt->get_result()->fetch_assoc();
if (!$value) {
    header('Location: ' . normal_url('index.php'));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateToken($_POST['csrf_token'])) {
        die('Invalid CSRF token');
    }

    $value_name = trim($_POST['value_name']);
    $price_modifier = floatval($_POST['price_modifier']);
    $sort_order = intval($_POST['sort_order']);

    if (empty($value_name)) {
        $error = "Value name is required";
    } else {
        $update = $conn->prepare("UPDATE menu_item_option_values SET value_name=?, price_modifier=?, sort_order=? WHERE id=?");
        $update->bind_param("sdii", $value_name, $price_modifier, $sort_order, $id);
        $update->execute();

        $redirect = "options.php?item_id=" . $value['menu_item_id'];
        if (isset($_SESSION['kiosk_mode']) && $_SESSION['kiosk_mode']) {
            $redirect .= '&kiosk=1';
        }
        header("Location: $redirect");
        exit;
    }
}

$page_title = "Edit Option Value";
include __DIR__ . '/../../includes/header.php';
?>

<div class="admin-container">
    <h1>Edit Option Value</h1>
    <?php if (isset($error)): ?><div class="error"><?= $error ?></div><?php endif; ?>
    <form method="post">
        <input type="hidden" name="csrf_token" value="<?= generateToken() ?>">
        <div class="form-group">
            <label>Value Name</label>
            <input type="text" name="value_name" value="<?= htmlspecialchars($value['value_name']) ?>" required>
        </div>
        <div class="form-group">
            <label>Price Modifier</label>
            <input type="number" step="0.01" name="price_modifier" value="<?= $value['price_modifier'] ?>">
            <small class="small-note">Use positive numbers for extra cost, negative for discount.</small>
        </div>
        <div class="form-group">
            <label>Sort Order</label>
            <input type="number" name="sort_order" value="<?= $value['sort_order'] ?>">
        </div>
        <button type="submit" class="btn btn-primary">Update Value</button>
        <a href="<?= normal_url('options.php?item_id=' . $value['menu_item_id']) ?>" class="btn btn-secondary">Cancel</a>
    </form>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>