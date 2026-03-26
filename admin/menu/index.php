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

$page_title = "Manage Menu";
include __DIR__ . '/../../includes/header.php';
?>

<div class="admin-container">
    <div class="admin-header">
        <h1>Menu Management</h1>
        <a href="<?= normal_url('create.php') ?>" class="btn btn-primary">+ Add New Item</a>
    </div>

    <?php if (empty($items)): ?>
        <p>No menu items yet. <a href="<?= normal_url('create.php') ?>">Add your first item</a>.</p>
    <?php else: ?>
        <div class="table-responsive">
            <table class="admin-table">
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
                                <a href="<?= normal_url('options.php?item_id=' . $item['id']) ?>" class="btn-small btn-options">Options</a>
                                <a href="<?= normal_url('edit.php?id=' . $item['id']) ?>" class="btn-small btn-edit">Edit</a>
                                <button class="btn-small btn-delete" data-action="delete-item" data-item-id="<?= $item['id'] ?>" data-csrf="<?= generateToken() ?>">Delete</button>
                                <form id="delete-item-form-<?= $item['id'] ?>" method="post" action="<?= normal_url('delete.php') ?>" style="display:none;">
                                    <input type="hidden" name="csrf_token" value="<?= generateToken() ?>">
                                    <input type="hidden" name="id" value="<?= $item['id'] ?>">
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
    <p style="margin-top:20px;"><a href="<?= normal_url('../../dashboard/index.php') ?>">← Back to Dashboard</a></p>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const deleteBtns = document.querySelectorAll('[data-action="delete-item"]');
    deleteBtns.forEach(btn => {
        btn.addEventListener('click', async (e) => {
            e.preventDefault();
            const itemId = btn.dataset.itemId;
            const confirmed = await showConfirmModal({
                message: `Are you sure you want to delete this menu item?`
            });
            if (confirmed) {
                document.getElementById(`delete-item-form-${itemId}`).submit();
            }
        });
    });
});
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>