<?php
require_once 'config/database.php';
require_once 'includes/csrf.php';
require_once 'includes/header.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: auth/login.php");
    exit;
}

$user_id = $_SESSION['user_id'];

// Fetch current balance with error handling
try {
    $stmt = $conn->prepare("SELECT balance FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $balance = $user['balance'] ?? 0;
} catch (Exception $e) {
    error_log("Balance fetch error: " . $e->getMessage());
    $balance = 0;
    $error_msg = "Could not retrieve balance. Please try again later.";
}

// Handle success/error messages from topup.php
$success_msg = $_SESSION['topup_success'] ?? '';
$error_msg = isset($error_msg) ? $error_msg : ($_SESSION['topup_error'] ?? '');
unset($_SESSION['topup_success'], $_SESSION['topup_error']);

// Fetch recent transactions with error handling
try {
    $stmt = $conn->prepare("SELECT * FROM transactions WHERE user_id = ? ORDER BY created_at DESC LIMIT 10");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $transactions = $stmt->get_result();
} catch (Exception $e) {
    error_log("Transaction fetch error: " . $e->getMessage());
    $transactions = null;
    $error_msg = "Could not load transaction history.";
}
?>

<div class="checkout-container">
    <h1>My Wallet</h1>

    <?php if ($success_msg): ?>
        <div class="success-message"><?= htmlspecialchars($success_msg) ?></div>
    <?php endif; ?>
    <?php if ($error_msg): ?>
        <div class="error-message"><?= htmlspecialchars($error_msg) ?></div>
    <?php endif; ?>

    <div class="order-summary" style="text-align: center;">
        <h2>Current Balance</h2>
        <div style="font-size: 4rem; color: var(--primary-600);">$<?= number_format($balance, 2) ?></div>
    </div>

    <div class="card">
        <h3>Top Up Wallet</h3>
        <form method="post" action="topup.php">
            <input type="hidden" name="csrf_token" value="<?= generateToken() ?>">
            <div class="form-group">
                <label>Amount ($)</label>
                <input type="number" name="amount" step="0.01" min="1" max="1000" required>
            </div>
            <button type="submit" class="btn btn-primary">Proceed to Top Up</button>
        </form>
    </div>

    <?php if ($transactions && $transactions->num_rows > 0): ?>
    <div class="card">
        <h3>Recent Transactions</h3>
        表格
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Type</th>
                    <th>Amount</th>
                    <th>Description</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($tx = $transactions->fetch_assoc()): ?>
                <tr>
                    <td><?= date('M j, Y g:i a', strtotime($tx['created_at'])) ?></td>
                    <td><?= ucfirst($tx['type']) ?></td>
                    <td style="color: <?= $tx['type'] == 'topup' ? 'green' : 'red' ?>;">
                        <?= $tx['type'] == 'topup' ? '+' : '-' ?> $<?= number_format($tx['amount'], 2) ?>
                    </td>
                    <td><?= htmlspecialchars($tx['description'] ?? '') ?></td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        表格
    </div>
    <?php endif; ?>

    <div style="margin-top: 2rem;">
        <a href="<?= kiosk_url('menu.php') ?>" class="btn btn-accent">Continue Shopping</a>
    </div>
</div>

<?php include 'includes/footer.php'; ?>