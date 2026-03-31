<?php
require __DIR__ . '/../../middleware/admin_check.php';
require __DIR__ . '/../../config/database.php';
require __DIR__ . '/../../includes/csrf.php';
require __DIR__ . '/../../includes/kiosk.php';
require_once __DIR__ . '/../../includes/functions.php';

$action = $_GET['action'] ?? 'list';
$error = '';
$success = '';

$categories = ['Breakfast', 'A La Carte', 'Combo', 'Beverage', 'Dessert'];

// ======================== Handle POST actions ========================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateToken($_POST['csrf_token'])) die('Invalid CSRF token');

    // ---- Add / Edit Menu Item ----
    if ($action === 'add' || $action === 'edit') {
        $id = $action === 'edit' ? intval($_POST['id'] ?? 0) : 0;
        $name = trim($_POST['name']);
        $category = $_POST['category'];
        $price = floatval($_POST['price']);
        $image = trim($_POST['image']) ?: null;
        $sort_order = intval($_POST['sort_order']);

        if (empty($name) || $price <= 0 || empty($category)) {
            $error = 'Name, category, and a valid price are required.';
        } else {
            if ($id) {
                $stmt = $conn->prepare("UPDATE menu_items SET name=?, category=?, price=?, image=?, sort_order=? WHERE id=?");
                $stmt->bind_param("ssdsii", $name, $category, $price, $image, $sort_order, $id);
                if ($stmt->execute()) clearMenuCache();
                else $error = 'Database error: ' . $conn->error;
            } else {
                $stmt = $conn->prepare("INSERT INTO menu_items (name, category, price, image, sort_order) VALUES (?, ?, ?, ?, ?)");
                $stmt->bind_param("ssdsi", $name, $category, $price, $image, $sort_order);
                if ($stmt->execute()) clearMenuCache();
                else $error = 'Database error: ' . $conn->error;
            }
        }
        if (!$error) {
            header('Location: ?action=list' . (isset($_SESSION['kiosk_mode']) && $_SESSION['kiosk_mode'] ? '&kiosk=1' : ''));
            exit;
        }
    }

    // ---- Delete Menu Item ----
    if ($action === 'delete' && isset($_POST['id'])) {
        $id = intval($_POST['id']);
        $stmt = $conn->prepare("DELETE FROM menu_items WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        clearMenuCache();
        header('Location: ?action=list' . (isset($_SESSION['kiosk_mode']) && $_SESSION['kiosk_mode'] ? '&kiosk=1' : ''));
        exit;
    }

    // ---- Save Option (add/edit) ----
    if ($action === 'option_save') {
        $option_id = intval($_POST['option_id'] ?? 0);
        $menu_item_id = intval($_POST['menu_item_id']);
        $option_name = trim($_POST['option_name']);
        $option_type = $_POST['option_type'];
        $required = isset($_POST['required']) ? 1 : 0;
        $sort_order = intval($_POST['sort_order']);

        if (empty($option_name)) {
            $error = "Option name is required";
        } else {
            if ($option_id) {
                $stmt = $conn->prepare("UPDATE menu_item_options SET option_name=?, option_type=?, required=?, sort_order=? WHERE id=?");
                $stmt->bind_param("ssiii", $option_name, $option_type, $required, $sort_order, $option_id);
            } else {
                $stmt = $conn->prepare("INSERT INTO menu_item_options (menu_item_id, option_name, option_type, required, sort_order) VALUES (?, ?, ?, ?, ?)");
                $stmt->bind_param("issii", $menu_item_id, $option_name, $option_type, $required, $sort_order);
            }
            if ($stmt->execute()) clearMenuCache();
            else $error = 'Database error: ' . $conn->error;
        }
        if (!$error) {
            header("Location: ?action=options&item_id=$menu_item_id" . (isset($_SESSION['kiosk_mode']) && $_SESSION['kiosk_mode'] ? '&kiosk=1' : ''));
            exit;
        }
    }

    // ---- Delete Option ----
    if ($action === 'option_delete' && isset($_POST['id'])) {
        $id = intval($_POST['id']);
        $stmt = $conn->prepare("SELECT menu_item_id FROM menu_item_options WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $opt = $stmt->get_result()->fetch_assoc();
        $menu_item_id = $opt['menu_item_id'] ?? 0;
        $stmt = $conn->prepare("DELETE FROM menu_item_options WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        clearMenuCache();
        header("Location: ?action=options&item_id=$menu_item_id" . (isset($_SESSION['kiosk_mode']) && $_SESSION['kiosk_mode'] ? '&kiosk=1' : ''));
        exit;
    }

    // ---- Save Option Value ----
    if ($action === 'value_save') {
        $value_id = intval($_POST['value_id'] ?? 0);
        $option_id = intval($_POST['option_id']);
        $value_name = trim($_POST['value_name']);
        $price_modifier = floatval($_POST['price_modifier']);
        $sort_order = intval($_POST['sort_order']);

        if (empty($value_name)) {
            $error = "Value name is required";
        } else {
            if ($value_id) {
                $stmt = $conn->prepare("UPDATE menu_item_option_values SET value_name=?, price_modifier=?, sort_order=? WHERE id=?");
                $stmt->bind_param("sdii", $value_name, $price_modifier, $sort_order, $value_id);
            } else {
                $stmt = $conn->prepare("INSERT INTO menu_item_option_values (option_id, value_name, price_modifier, sort_order) VALUES (?, ?, ?, ?)");
                $stmt->bind_param("isdi", $option_id, $value_name, $price_modifier, $sort_order);
            }
            if ($stmt->execute()) clearMenuCache();
            else $error = 'Database error: ' . $conn->error;
        }
        if (!$error) {
            $opt_stmt = $conn->prepare("SELECT menu_item_id FROM menu_item_options WHERE id = ?");
            $opt_stmt->bind_param("i", $option_id);
            $opt_stmt->execute();
            $opt = $opt_stmt->get_result()->fetch_assoc();
            $menu_item_id = $opt['menu_item_id'] ?? 0;
            header("Location: ?action=options&item_id=$menu_item_id" . (isset($_SESSION['kiosk_mode']) && $_SESSION['kiosk_mode'] ? '&kiosk=1' : ''));
            exit;
        }
    }

    // ---- Delete Option Value ----
    if ($action === 'value_delete' && isset($_POST['id'])) {
        $id = intval($_POST['id']);
        $stmt = $conn->prepare("SELECT o.menu_item_id FROM menu_item_option_values v JOIN menu_item_options o ON v.option_id = o.id WHERE v.id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $val = $stmt->get_result()->fetch_assoc();
        $menu_item_id = $val['menu_item_id'] ?? 0;
        $stmt = $conn->prepare("DELETE FROM menu_item_option_values WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        clearMenuCache();
        header("Location: ?action=options&item_id=$menu_item_id" . (isset($_SESSION['kiosk_mode']) && $_SESSION['kiosk_mode'] ? '&kiosk=1' : ''));
        exit;
    }
}

// ======================== DISPLAY ========================
$page_title = "Menu Management";
include __DIR__ . '/../../includes/header.php';
?>

<div class="dashboard-wrapper">
    <div class="sidebar">
        <h2>⚙️ Admin Panel</h2>
        <ul>
            <!-- Use absolute paths for cross-directory links -->
            <li><a href="<?= normal_url('/admin/index.php') ?>">Dashboard</a></li>
            <li><a href="<?= normal_url('/admin/menu/index.php') ?>" class="active">Manage Menu</a></li>
            <li><a href="<?= normal_url('/admin/index.php?action=orders') ?>">Manage Orders</a></li>
            <li><a href="<?= normal_url('/admin/index.php?action=users') ?>">Manage Users</a></li>
            <li><a href="<?= kiosk_url('/menu.php') ?>">View Site</a></li>
            <li><a href="<?= normal_url('/auth/logout.php') ?>">Logout</a></li>
        </ul>
    </div>
    <div class="main-content">
        <?php
        // ------------------------------------------------------------------
        // 1. LIST MENU ITEMS (default)
        // ------------------------------------------------------------------
        if ($action === 'list' || ($action !== 'add' && $action !== 'edit' && $action !== 'options' && $action !== 'option_edit' && $action !== 'value_edit')) {
            $stmt = $conn->prepare("SELECT * FROM menu_items ORDER BY FIELD(category, 'Breakfast', 'A La Carte', 'Combo', 'Beverage', 'Dessert'), sort_order, name");
            $stmt->execute();
            $items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            ?>
            <div class="admin-header flex justify-between items-center mb-6">
                <h1>Menu Management</h1>
                <a href="?action=add" class="btn btn-primary">+ Add New Item</a>
            </div>
            <?php if (empty($items)): ?>
                <p>No menu items yet. <a href="?action=add">Add your first item</a>.</p>
            <?php else: ?>
                <div class="table-wrapper">
                    <table class="admin-table">
                        <thead>
                             <tr><th>ID</th><th>Image</th><th>Name</th><th>Category</th><th>Price</th><th>Sort</th><th>Actions</th> </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($items as $item): ?>
                              <tr>
                                <td><?= $item['id'] ?></td>
                                <td>
                                    <?php if (!empty($item['image'])): ?>
                                        <img src="<?= htmlspecialchars($item['image']) ?>" alt="<?= htmlspecialchars($item['name']) ?>" class="item-image" style="width:50px; height:50px; object-fit:cover;">
                                    <?php else: ?>—<?php endif; ?>
                                </td>
                                <td><?= htmlspecialchars($item['name']) ?></td>
                                <td><?= htmlspecialchars($item['category']) ?></td>
                                <td>$<?= number_format($item['price'], 2) ?></td>
                                <td><?= $item['sort_order'] ?></td>
                                <td>
                                    <a href="?action=options&item_id=<?= $item['id'] ?>" class="btn-small btn-options">Options</a>
                                    <a href="?action=edit&id=<?= $item['id'] ?>" class="btn-small btn-edit">Edit</a>
                                    <form method="post" style="display:inline;" onsubmit="return confirm('Delete this menu item?');">
                                        <input type="hidden" name="csrf_token" value="<?= generateToken() ?>">
                                        <input type="hidden" name="id" value="<?= $item['id'] ?>">
                                        <button type="submit" name="action" value="delete" class="btn-small btn-danger">Delete</button>
                                    </form>
                                </td>
                              </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
            <?php
        }

        // ------------------------------------------------------------------
        // 2. ADD / EDIT MENU ITEM FORM
        // ------------------------------------------------------------------
        elseif ($action === 'add' || $action === 'edit') {
            $id = isset($_GET['id']) ? intval($_GET['id']) : 0;
            $item = null;
            if ($id) {
                $stmt = $conn->prepare("SELECT * FROM menu_items WHERE id = ?");
                $stmt->bind_param("i", $id);
                $stmt->execute();
                $item = $stmt->get_result()->fetch_assoc();
                if (!$item) {
                    header("Location: ?action=list");
                    exit;
                }
            }
            ?>
            <div class="admin-container" style="max-width:600px; margin:0 auto;">
                <h1><?= $item ? 'Edit' : 'Add New' ?> Menu Item</h1>
                <?php if ($error): ?><div class="error"><?= $error ?></div><?php endif; ?>
                <form method="post">
                    <input type="hidden" name="csrf_token" value="<?= generateToken() ?>">
                    <?php if ($item): ?>
                        <input type="hidden" name="id" value="<?= $item['id'] ?>">
                    <?php endif; ?>
                    <div class="form-group">
                        <label>Name *</label>
                        <input type="text" name="name" value="<?= htmlspecialchars($item['name'] ?? '') ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Category *</label>
                        <select name="category" required>
                            <option value="">Select Category</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?= $cat ?>" <?= isset($item) && $item['category'] == $cat ? 'selected' : '' ?>><?= $cat ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Price *</label>
                        <input type="number" step="0.01" name="price" value="<?= $item['price'] ?? '' ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Image URL</label>
                        <input type="url" name="image" id="image-url" value="<?= htmlspecialchars($item['image'] ?? '') ?>" placeholder="https://...">
                        <img id="image-preview" src="<?= htmlspecialchars($item['image'] ?? '') ?>" style="max-width:200px; margin-top:10px; display:<?= !empty($item['image']) ? 'block' : 'none' ?>;">
                    </div>
                    <div class="form-group">
                        <label>Sort Order</label>
                        <input type="number" name="sort_order" value="<?= $item['sort_order'] ?? 0 ?>">
                    </div>
                    <button type="submit" name="action" value="<?= $action ?>" class="btn">Save Item</button>
                    <a href="?action=list" class="btn btn-secondary">Cancel</a>
                </form>
            </div>
            <script>
                document.getElementById('image-url')?.addEventListener('input', function() {
                    let preview = document.getElementById('image-preview');
                    if (this.value) { preview.src = this.value; preview.style.display = 'block'; }
                    else { preview.style.display = 'none'; }
                });
            </script>
            <?php
        }

        // ------------------------------------------------------------------
        // 3. MANAGE OPTIONS (list, add, edit, delete) for a given menu item
        // ------------------------------------------------------------------
        elseif ($action === 'options' || $action === 'option_edit' || $action === 'value_edit') {
            $menu_item_id = isset($_GET['item_id']) ? intval($_GET['item_id']) : 0;
            if (!$menu_item_id) {
                header("Location: ?action=list");
                exit;
            }
            $stmt = $conn->prepare("SELECT name FROM menu_items WHERE id = ?");
            $stmt->bind_param("i", $menu_item_id);
            $stmt->execute();
            $item = $stmt->get_result()->fetch_assoc();
            if (!$item) {
                header("Location: ?action=list");
                exit;
            }

            // Edit option
            if ($action === 'option_edit') {
                $option_id = isset($_GET['option_id']) ? intval($_GET['option_id']) : 0;
                $stmt = $conn->prepare("SELECT * FROM menu_item_options WHERE id = ? AND menu_item_id = ?");
                $stmt->bind_param("ii", $option_id, $menu_item_id);
                $stmt->execute();
                $option = $stmt->get_result()->fetch_assoc();
                if (!$option) {
                    header("Location: ?action=options&item_id=$menu_item_id");
                    exit;
                }
                ?>
                <div class="admin-container">
                    <h1>Edit Option: <?= htmlspecialchars($option['option_name']) ?></h1>
                    <?php if ($error): ?><div class="error"><?= $error ?></div><?php endif; ?>
                    <form method="post">
                        <input type="hidden" name="csrf_token" value="<?= generateToken() ?>">
                        <input type="hidden" name="option_id" value="<?= $option['id'] ?>">
                        <input type="hidden" name="menu_item_id" value="<?= $menu_item_id ?>">
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
                            <label><input type="checkbox" name="required" value="1" <?= $option['required'] ? 'checked' : '' ?>> Required?</label>
                        </div>
                        <div class="form-group">
                            <label>Sort Order</label>
                            <input type="number" name="sort_order" value="<?= $option['sort_order'] ?>">
                        </div>
                        <button type="submit" name="action" value="option_save" class="btn">Update Option</button>
                        <a href="?action=options&item_id=<?= $menu_item_id ?>" class="btn btn-secondary">Cancel</a>
                    </form>
                </div>
                <?php
            }
            // Edit option value
            elseif ($action === 'value_edit') {
                $value_id = isset($_GET['value_id']) ? intval($_GET['value_id']) : 0;
                $stmt = $conn->prepare("SELECT v.*, o.menu_item_id FROM menu_item_option_values v JOIN menu_item_options o ON v.option_id = o.id WHERE v.id = ?");
                $stmt->bind_param("i", $value_id);
                $stmt->execute();
                $value = $stmt->get_result()->fetch_assoc();
                if (!$value || $value['menu_item_id'] != $menu_item_id) {
                    header("Location: ?action=options&item_id=$menu_item_id");
                    exit;
                }
                ?>
                <div class="admin-container">
                    <h1>Edit Option Value</h1>
                    <?php if ($error): ?><div class="error"><?= $error ?></div><?php endif; ?>
                    <form method="post">
                        <input type="hidden" name="csrf_token" value="<?= generateToken() ?>">
                        <input type="hidden" name="value_id" value="<?= $value['id'] ?>">
                        <input type="hidden" name="option_id" value="<?= $value['option_id'] ?>">
                        <div class="form-group">
                            <label>Value Name</label>
                            <input type="text" name="value_name" value="<?= htmlspecialchars($value['value_name']) ?>" required>
                        </div>
                        <div class="form-group">
                            <label>Price Modifier</label>
                            <input type="number" step="0.01" name="price_modifier" value="<?= $value['price_modifier'] ?>">
                            <small>Use positive for extra cost, negative for discount.</small>
                        </div>
                        <div class="form-group">
                            <label>Sort Order</label>
                            <input type="number" name="sort_order" value="<?= $value['sort_order'] ?>">
                        </div>
                        <button type="submit" name="action" value="value_save" class="btn">Update Value</button>
                        <a href="?action=options&item_id=<?= $menu_item_id ?>" class="btn btn-secondary">Cancel</a>
                    </form>
                </div>
                <?php
            }
            // Default options view
            else {
                $opt_stmt = $conn->prepare("SELECT * FROM menu_item_options WHERE menu_item_id = ? ORDER BY sort_order");
                $opt_stmt->bind_param("i", $menu_item_id);
                $opt_stmt->execute();
                $options = $opt_stmt->get_result();
                ?>
                <div class="admin-container">
                    <div class="flex justify-between items-center mb-4">
                        <h1>Options for: <?= htmlspecialchars($item['name']) ?></h1>
                        <a href="?action=list" class="btn btn-secondary">← Back to Menu</a>
                    </div>

                    <!-- Add new option form -->
                    <div class="card mb-6">
                        <h2>Add New Option</h2>
                        <form method="post" class="grid grid-cols-1 md:grid-cols-4 gap-4">
                            <input type="hidden" name="csrf_token" value="<?= generateToken() ?>">
                            <input type="hidden" name="menu_item_id" value="<?= $menu_item_id ?>">
                            <div><input type="text" name="option_name" placeholder="Option name" required class="w-full"></div>
                            <div>
                                <select name="option_type" class="w-full">
                                    <option value="dropdown">Dropdown</option>
                                    <option value="radio">Radio Buttons</option>
                                </select>
                            </div>
                            <div><label><input type="checkbox" name="required" value="1"> Required</label></div>
                            <div><input type="number" name="sort_order" placeholder="Sort order" value="0" class="w-full"></div>
                            <div class="col-span-full"><button type="submit" name="action" value="option_save" class="btn btn-primary">Add Option</button></div>
                        </form>
                    </div>

                    <?php if ($options->num_rows === 0): ?>
                        <p>No options yet.</p>
                    <?php else: ?>
                        <?php while ($opt = $options->fetch_assoc()): ?>
                            <div class="card mb-6">
                                <div class="flex justify-between items-center">
                                    <h3><?= htmlspecialchars($opt['option_name']) ?> 
                                        <small>(<?= $opt['option_type'] ?>, <?= $opt['required'] ? 'Required' : 'Optional' ?>)</small>
                                    </h3>
                                    <div>
                                        <a href="?action=option_edit&item_id=<?= $menu_item_id ?>&option_id=<?= $opt['id'] ?>" class="btn-small btn-edit">Edit</a>
                                        <form method="post" style="display:inline;" onsubmit="return confirm('Delete this option?');">
                                            <input type="hidden" name="csrf_token" value="<?= generateToken() ?>">
                                            <input type="hidden" name="id" value="<?= $opt['id'] ?>">
                                            <button type="submit" name="action" value="option_delete" class="btn-small btn-danger">Delete</button>
                                        </form>
                                    </div>
                                </div>
                                <!-- Values table -->
                                <div class="table-wrapper mt-4">
                                    <table class="admin-table">
                                        <thead><tr><th>Value</th><th>Price Modifier</th><th>Sort</th><th>Actions</th></tr></thead>
                                        <tbody>
                                            <?php
                                            $val_stmt = $conn->prepare("SELECT * FROM menu_item_option_values WHERE option_id = ? ORDER BY sort_order");
                                            $val_stmt->bind_param("i", $opt['id']);
                                            $val_stmt->execute();
                                            $values = $val_stmt->get_result();
                                            if ($values->num_rows === 0):
                                            ?>
                                                <tr><td colspan="4" class="text-center">No values yet</td></tr>
                                            <?php else:
                                                while ($val = $values->fetch_assoc()):
                                            ?>
                                                <tr>
                                                    <td><?= htmlspecialchars($val['value_name']) ?></td>
                                                    <td>$<?= number_format($val['price_modifier'], 2) ?></td>
                                                    <td><?= $val['sort_order'] ?></td>
                                                    <td>
                                                        <a href="?action=value_edit&item_id=<?= $menu_item_id ?>&value_id=<?= $val['id'] ?>" class="btn-small btn-edit">Edit</a>
                                                        <form method="post" style="display:inline;" onsubmit="return confirm('Delete this value?');">
                                                            <input type="hidden" name="csrf_token" value="<?= generateToken() ?>">
                                                            <input type="hidden" name="id" value="<?= $val['id'] ?>">
                                                            <button type="submit" name="action" value="value_delete" class="btn-small btn-danger">Delete</button>
                                                        </form>
                                                    </td>
                                                </tr>
                                            <?php endwhile; endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                                <!-- Add value form -->
                                <form method="post" class="mt-4 grid grid-cols-1 md:grid-cols-4 gap-4">
                                    <input type="hidden" name="csrf_token" value="<?= generateToken() ?>">
                                    <input type="hidden" name="option_id" value="<?= $opt['id'] ?>">
                                    <div><input type="text" name="value_name" placeholder="Value name" required class="w-full"></div>
                                    <div><input type="number" step="0.01" name="price_modifier" placeholder="Price modifier" value="0.00" class="w-full"></div>
                                    <div><input type="number" name="sort_order" placeholder="Sort order" value="0" class="w-full"></div>
                                    <div><button type="submit" name="action" value="value_save" class="btn-small btn-success">Add Value</button></div>
                                </form>
                            </div>
                        <?php endwhile; ?>
                    <?php endif; ?>
                </div>
                <?php
            }
        }
        ?>
    </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>