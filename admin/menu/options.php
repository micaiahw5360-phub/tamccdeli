<?php
require __DIR__ . '/../../middleware/admin_check.php';
require __DIR__ . '/../../config/database.php';
require __DIR__ . '/../../includes/csrf.php';
require __DIR__ . '/includes/kiosk.php';

$menu_item_id = isset($_GET['item_id']) ? intval($_GET['item_id']) : 0;
if (!$menu_item_id) {
    header('Location: ' . normal_url('index.php'));
    exit;
}

// Fetch item name for display
$stmt = $conn->prepare("SELECT name FROM menu_items WHERE id = ?");
$stmt->bind_param("i", $menu_item_id);
$stmt->execute();
$item = $stmt->get_result()->fetch_assoc();
if (!$item) {
    header('Location: ' . normal_url('index.php'));
    exit;
}

// Fetch options with their values
$options = [];
$opt_stmt = $conn->prepare("SELECT * FROM menu_item_options WHERE menu_item_id = ? ORDER BY sort_order");
$opt_stmt->bind_param("i", $menu_item_id);
$opt_stmt->execute();
$opt_res = $opt_stmt->get_result();
while ($opt = $opt_res->fetch_assoc()) {
    $val_stmt = $conn->prepare("SELECT * FROM menu_item_option_values WHERE option_id = ? ORDER BY sort_order");
    $val_stmt->bind_param("i", $opt['id']);
    $val_stmt->execute();
    $opt['values'] = $val_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $options[] = $opt;
}

$page_title = "Manage Options - " . htmlspecialchars($item['name']);
include __DIR__ . '/../../includes/header.php';
?>

<div class="admin-container">
    <h1>Options for: <?= htmlspecialchars($item['name']) ?></h1>
    <p><a href="<?= normal_url('index.php') ?>" class="btn btn-secondary">← Back to Menu</a></p>

    <h2>Add New Option</h2>
    <form method="post" action="option-save.php" class="form-inline">
        <input type="hidden" name="csrf_token" value="<?= generateToken() ?>">
        <input type="hidden" name="menu_item_id" value="<?= $menu_item_id ?>">
        <div class="form-group">
            <label>Option Name</label>
            <input type="text" name="option_name" required>
        </div>
        <div class="form-group">
            <label>Type</label>
            <select name="option_type">
                <option value="dropdown">Dropdown</option>
                <option value="radio">Radio Buttons</option>
            </select>
        </div>
        <div class="form-group">
            <label>Required?</label>
            <input type="checkbox" name="required" value="1" checked>
        </div>
        <div class="form-group">
            <label>Sort Order</label>
            <input type="number" name="sort_order" value="0">
        </div>
        <button type="submit" class="btn btn-primary">Add Option</button>
    </form>

    <hr>

    <?php foreach ($options as $opt): ?>
        <div class="card" style="margin-top:20px;">
            <h3><?= htmlspecialchars($opt['option_name']) ?>
                <small>(<?= $opt['option_type'] ?>, <?= $opt['required'] ? 'Required' : 'Optional' ?>)</small>
                <a href="option-edit.php?id=<?= $opt['id'] ?>" class="btn-small btn-edit">Edit</a>
                <form method="post" action="option-delete.php" style="display:inline;" onsubmit="return confirm('Delete this option?');">
                    <input type="hidden" name="csrf_token" value="<?= generateToken() ?>">
                    <input type="hidden" name="id" value="<?= $opt['id'] ?>">
                    <button type="submit" class="btn-small btn-danger">Delete</button>
                </form>
            </h3>
            <table class="table">
                <thead>
                    <tr>
                        <th>Value</th>
                        <th>Price Modifier</th>
                        <th>Sort</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($opt['values'] as $val): ?>
                    <tr>
                        <td><?= htmlspecialchars($val['value_name']) ?></td>
                        <td>$<?= number_format($val['price_modifier'], 2) ?></td>
                        <td><?= $val['sort_order'] ?></td>
                        <td>
                            <a href="option-value-edit.php?id=<?= $val['id'] ?>" class="btn-small btn-edit">Edit</a>
                            <form method="post" action="option-value-delete.php" style="display:inline;" onsubmit="return confirm('Delete this value?');">
                                <input type="hidden" name="csrf_token" value="<?= generateToken() ?>">
                                <input type="hidden" name="id" value="<?= $val['id'] ?>">
                                <button type="submit" class="btn-small btn-danger">Delete</button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <h4>Add Value</h4>
            <form method="post" action="option-value-save.php" class="form-inline">
                <input type="hidden" name="csrf_token" value="<?= generateToken() ?>">
                <input type="hidden" name="option_id" value="<?= $opt['id'] ?>">
                <div class="form-group">
                    <label>Value Name</label>
                    <input type="text" name="value_name" required>
                </div>
                <div class="form-group">
                    <label>Price Modifier</label>
                    <input type="number" step="0.01" name="price_modifier" value="0.00">
                </div>
                <div class="form-group">
                    <label>Sort Order</label>
                    <input type="number" name="sort_order" value="0">
                </div>
                <button type="submit" class="btn-small btn-success">Add</button>
            </form>
        </div>
    <?php endforeach; ?>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>