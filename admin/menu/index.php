<?php
require __DIR__ . '/../../middleware/admin_check.php';
require __DIR__ . '/../../config/database.php';
require __DIR__ . '/../../includes/csrf.php';
require __DIR__ . '/../../includes/kiosk.php';

// Fetch all menu items
$stmt = $conn->prepare("SELECT * FROM menu_items ORDER BY FIELD(category, 'Breakfast', 'A La Carte', 'Combo', 'Beverage', 'Dessert'), sort_order, name");
$stmt->execute();
$result = $stmt->get_result();
$items = $result->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Menu | TAMCC Deli</title>
    <link rel="stylesheet" href="../../assets/css/global.css">
    <style>
        .admin-container { max-width: 1200px; margin: 30px auto; padding: 20px; background: white; border-radius: 10px; box-shadow: var(--shadow); }
        .admin-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        .btn { padding: 10px 20px; border: none; border-radius: 6px; cursor: pointer; text-decoration: none; display: inline-block; font-weight: 600; }
        .btn-primary { background: var(--primary-600); color: white; }
        .btn-edit { background: #f59e0b; color: white; padding: 5px 10px; font-size: 0.9rem; }
        .btn-delete { background: var(--danger); color: white; padding: 5px 10px; font-size: 0.9rem; border: none; }
        /* New Options button style */
        .btn-options { background: #17a2b8; color: white; padding: 5px 10px; font-size: 0.9rem; text-decoration: none; display: inline-block; border: none; border-radius: 4px; cursor: pointer; }
        .btn-options:hover { background: #138496; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid var(--neutral-200); vertical-align: middle; }
        th { background: var(--neutral-100); }
        .category-breakfast { background-color: #fef9e7; }
        .category-alacarte { background-color: #e8f0fe; }
        .category-combo { background-color: #e6f7e6; }
        .category-beverage { background-color: #e6f3ff; }
        .category-dessert { background-color: #f9ebff; }
        .item-image { width: 50px; height: 50px; object-fit: cover; border-radius: 4px; }
        .no-image { color: var(--neutral-400); font-style: italic; }
        .delete-form { display: inline; }
    </style>
</head>
<body>
<div class="admin-container">
    <div class="admin-header">
        <h1>Menu Management</h1>
        <a href="<?= normal_url('create.php') ?>" class="btn btn-primary">+ Add New Item</a>
    </div>

    <?php if (empty($items)): ?>
        <p>No menu items yet. <a href="<?= normal_url('create.php') ?>">Add your first item</a>.</p>
    <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Image</th>
                    <th>Name</th>
                    <th>Category</th>
                    <th>Price</th>
                    <th>Sort Order</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($items as $item): 
                    $rowClass = 'category-' . strtolower(str_replace(' ', '', $item['category']));
                ?>
                <tr class="<?= $rowClass ?>">
                    <td><?= $item['id'] ?></td>
                    <td>
                        <?php if (!empty($item['image'])): ?>
                            <img src="<?= htmlspecialchars($item['image']) ?>" alt="<?= htmlspecialchars($item['name']) ?>" class="item-image">
                        <?php else: ?>
                            <span class="no-image">—</span>
                        <?php endif; ?>
                    </td>
                    <td><?= htmlspecialchars($item['name']) ?></td>
                    <td><?= htmlspecialchars($item['category']) ?></td>
                    <td>$<?= number_format($item['price'], 2) ?></td>
                    <td><?= $item['sort_order'] ?></td>
                    <td>
                        <!-- Options button -->
                        <a href="<?= normal_url('options.php?item_id=' . $item['id']) ?>" class="btn-small btn-options">Options</a>
                        <a href="<?= normal_url('edit.php?id=' . $item['id']) ?>" class="btn-small btn-edit">Edit</a>
                        <form method="post" action="<?= normal_url('delete.php') ?>" class="delete-form" onsubmit="return confirm('Are you sure you want to delete this item?');">
                            <input type="hidden" name="csrf_token" value="<?= generateToken() ?>">
                            <input type="hidden" name="id" value="<?= $item['id'] ?>">
                            <button type="submit" class="btn-small btn-delete">Delete</button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
    <p style="margin-top:20px;"><a href="<?= normal_url('../../dashboard/index.php') ?>">← Back to Dashboard</a></p>
</div>
</body>
</html>