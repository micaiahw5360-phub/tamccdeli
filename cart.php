<?php
session_start();
require 'config/database.php';
require 'includes/csrf.php';

// Initialize cart if not exists
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

// Handle AJAX add to cart
if (isset($_GET['action']) && $_GET['action'] == 'add' && $_SERVER['REQUEST_METHOD'] == 'POST') {
    if (!validateToken($_POST['csrf_token'])) {
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
            echo json_encode(['success' => false, 'error' => 'Invalid CSRF']);
            exit;
        } else {
            die('Invalid CSRF token');
        }
    }
    $item_id = intval($_POST['item_id']);
    $quantity = intval($_POST['quantity']);
    if ($item_id > 0 && $quantity > 0) {
        if (isset($_SESSION['cart'][$item_id])) {
            $_SESSION['cart'][$item_id] += $quantity;
        } else {
            $_SESSION['cart'][$item_id] = $quantity;
        }
    }
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
        echo json_encode(['success' => true, 'count' => array_sum($_SESSION['cart'])]);
        exit;
    } else {
        $redirect = 'cart.php';
        if (isset($_SESSION['kiosk_mode']) && $_SESSION['kiosk_mode']) {
            $redirect .= '?kiosk=1';
        }
        header("Location: $redirect");
        exit;
    }
}

// Handle update (POST with CSRF)
if (isset($_POST['update'])) {
    if (!validateToken($_POST['csrf_token'])) {
        die('Invalid CSRF token');
    }
    foreach ($_POST['quantity'] as $item_id => $qty) {
        $qty = intval($qty);
        if ($qty <= 0) {
            unset($_SESSION['cart'][$item_id]);
        } else {
            $_SESSION['cart'][$item_id] = $qty;
        }
    }
    $redirect = 'cart.php';
    if (isset($_SESSION['kiosk_mode']) && $_SESSION['kiosk_mode']) {
        $redirect .= '?kiosk=1';
    }
    header("Location: $redirect");
    exit;
}

// Handle remove (POST with CSRF)
if (isset($_POST['remove'])) {
    if (!validateToken($_POST['csrf_token'])) {
        die('Invalid CSRF token');
    }
    $item_id = intval($_POST['item_id']);
    unset($_SESSION['cart'][$item_id]);
    $redirect = 'cart.php';
    if (isset($_SESSION['kiosk_mode']) && $_SESSION['kiosk_mode']) {
        $redirect .= '?kiosk=1';
    }
    header("Location: $redirect");
    exit;
}

// Fetch cart items
$cart_items = [];
$total = 0;
if (!empty($_SESSION['cart'])) {
    $ids = array_keys($_SESSION['cart']);
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $conn->prepare("SELECT * FROM menu_items WHERE id IN ($placeholders)");
    $stmt->bind_param(str_repeat('i', count($ids)), ...$ids);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $row['quantity'] = $_SESSION['cart'][$row['id']];
        $row['subtotal'] = $row['price'] * $row['quantity'];
        $total += $row['subtotal'];
        $cart_items[] = $row;
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Shopping Cart | TAMCC Deli</title>
    <link rel="stylesheet" href="assets/css/global.css">
    <style>
        /* Ensure cart container has minimum height to push footer down */
        .cart-container {
            min-height: 60vh;
            display: flex;
            flex-direction: column;
        }
        .empty-cart-message {
            flex: 1;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: var(--space-xl) 0;
            text-align: center;
        }
        .empty-cart-message .dashicons {
            font-size: 5rem;
            width: auto;
            height: auto;
            color: var(--neutral-400);
            margin-bottom: var(--space);
        }
        .empty-cart-message h2 {
            color: var(--neutral-700);
            margin-bottom: var(--space);
        }
        .empty-cart-message .btn {
            margin-top: var(--space);
        }
    </style>
</head>
<body>
    <?php include 'includes/header.php'; ?>

    <div class="cart-container">
        <h1>Your Cart</h1>
        <?php if (empty($cart_items)): ?>
            <div class="empty-cart-message">
                <span class="dashicons dashicons-cart"></span>
                <h2>Your cart is empty</h2>
                <p>Looks like you haven't added anything yet.</p>
                <a href="<?= kiosk_url('menu.php') ?>" class="btn btn-primary">Browse Menu</a>
            </div>
        <?php else: ?>
            <form method="post">
                <input type="hidden" name="csrf_token" value="<?= generateToken() ?>">
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr><th>Item</th><th>Price</th><th>Quantity</th><th>Subtotal</th><th></th></tr>
                        </thead>
                        <tbody>
                            <?php foreach ($cart_items as $item): ?>
                            <tr>
                                <td><?= htmlspecialchars($item['name']) ?></td>
                                <td>$<?= number_format($item['price'], 2) ?></td>
                                <td>
                                    <input type="number" name="quantity[<?= $item['id'] ?>]" value="<?= $item['quantity'] ?>" min="0" max="10">
                                </td>
                                <td>$<?= number_format($item['subtotal'], 2) ?></td>
                                <td>
                                    <form method="post" style="display:inline;">
                                        <input type="hidden" name="csrf_token" value="<?= generateToken() ?>">
                                        <input type="hidden" name="item_id" value="<?= $item['id'] ?>">
                                        <button type="submit" name="remove" class="btn btn-danger btn-small">Remove</button>
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div class="total">Total: $<?= number_format($total, 2) ?></div>
                <button type="submit" name="update" class="btn">Update Cart</button>
                <a href="<?= kiosk_url('checkout.php') ?>" class="btn btn-accent">Proceed to Checkout</a>
            </form>
        <?php endif; ?>
    </div>

    <?php include 'includes/footer.php'; ?>
</body>
</html>