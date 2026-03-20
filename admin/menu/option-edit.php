<?php
require __DIR__ . '/../../middleware/admin_check.php';
require __DIR__ . '/../../config/database.php';
require __DIR__ . '/../../includes/csrf.php';

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if (!$id) {
    header('Location: ' . normal_url('index.php'));
    exit;
}

// Fetch option details
$stmt = $conn->prepare("SELECT * FROM menu_item_options WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$option = $stmt->get_result()->fetch_assoc();
if (!$option) {
    header('Location: ' . normal_url('index.php'));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateToken($_POST['csrf_token'])) {
        die('Invalid CSRF token');
    }

    $option_name = trim($_POST['option_name']);
    $option_type = $_POST['option_type'];
    $required = isset($_POST['required']) ? 1 : 0;
    $sort_order = intval($_POST['sort_order']);

    if (empty($option_name)) {
        $error = "Option name is required";
    } else {
        $update = $conn->prepare("UPDATE menu_item_options SET option_name=?, option_type=?, required=?, sort_order=? WHERE id=?");
        $update->bind_param("ssiii", $option_name, $option_type, $required, $sort_order, $id);
        $update->execute();

        $redirect = "options.php?item_id=" . $option['menu_item_id'];
        if (isset($_SESSION['kiosk_mode']) && $_SESSION['kiosk_mode']) {
            $redirect .= '&kiosk=1';
        }
        header("Location: $redirect");
        exit;
    }
}

$page_title = "Edit Option";
include __DIR__ . '/../../includes/header.php';
?>

<div class="admin-container">
    <h1>Edit Option</h1>
    <?php if (isset($error)): ?><div class="error"><?= $error ?></div><?php endif; ?>
    <form method="post">
        <input type="hidden" name="csrf_token" value="<?= generateToken() ?>">
        <div class="form-group">
            <label>Option Name</label>
            <input type="text" name="option_name" value="<?= htmlspecialchars($option['option_name']) ?>" required>
        </div>
        <div class="form-group">
            <label>Type</label>
            <select name="option_type">
                <option value="dropdown" <?= $option['option_type'] == 'dropdown' ? 'selected' : '' ?>>Dropdown</option>
                <option value="radio" <?= $option['option_type'] == 'radio' ? 'selected' : '' ?>>Radio Buttons</option>
            </select>
        </div>
        <div class="form-group">
            <label>
                <input type="checkbox" name="required" value="1" <?= $option['required'] ? 'checked' : '' ?>>
                Required?
            </label>
        </div>
        <div class="form-group">
            <label>Sort Order</label>
            <input type="number" name="sort_order" value="<?= $option['sort_order'] ?>">
        </div>
        <button type="submit" class="btn btn-primary">Update Option</button>
        <a href="<?= normal_url('options.php?item_id=' . $option['menu_item_id']) ?>" class="btn btn-secondary">Cancel</a>
    </form>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>