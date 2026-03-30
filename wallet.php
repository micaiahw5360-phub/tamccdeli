<?php
require_once 'config/database.php';
require_once 'includes/csrf.php';
require_once 'includes/header.php';
require_once 'includes/session.php';

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
    $stmt = $conn->prepare("SELECT * FROM transactions WHERE user_id = ? ORDER BY created_at DESC LIMIT 20");
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
        <div style="font-size: 4rem; color: var(--primary-600); font-weight: 800;">$<?= number_format($balance, 2) ?></div>
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
        <div class="table-responsive">
            <table class="transactions-table">
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
                        <td class="transaction-date"><?= date('M j, Y g:i a', strtotime($tx['created_at'])) ?></td>
                        <td>
                            <span class="transaction-type transaction-type-<?= $tx['type'] ?>">
                                <?= ucfirst($tx['type']) ?>
                            </span>
                        </td>
                        <td class="transaction-amount <?= $tx['type'] == 'topup' ? 'amount-positive' : 'amount-negative' ?>">
                            <?= $tx['type'] == 'topup' ? '+' : '-' ?> $<?= number_format($tx['amount'], 2) ?>
                        </td>
                        <td class="transaction-description"><?= htmlspecialchars($tx['description'] ?? '—') ?></td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

    <div style="margin-top: 2rem; text-align: center;">
        <a href="<?= kiosk_url('menu.php') ?>" class="btn btn-accent">Continue Shopping</a>
    </div>
</div>

<style>
.transactions-table {
    width: 100%;
    border-collapse: collapse;
}

.transactions-table th {
    background: var(--neutral-100);
    padding: 1rem;
    text-align: left;
    font-weight: 700;
    color: var(--neutral-700);
    border-bottom: 2px solid var(--neutral-200);
}

.transactions-table td {
    padding: 1rem;
    border-bottom: 1px solid var(--neutral-200);
    vertical-align: middle;
}

.transactions-table tr:hover td {
    background: var(--neutral-50);
}

.transaction-date {
    color: var(--neutral-600);
    font-size: 0.9rem;
    white-space: nowrap;
}

.transaction-type {
    display: inline-block;
    padding: 0.25rem 0.75rem;
    border-radius: 20px;
    font-size: 0.85rem;
    font-weight: 600;
    text-transform: uppercase;
}

.transaction-type-topup {
    background: #dcfce7;
    color: #15803d;
}

.transaction-type-payment {
    background: #fee2e2;
    color: #b91c1c;
}

.transaction-type-refund {
    background: #fff3e0;
    color: #c2410c;
}

.transaction-amount {
    font-weight: 700;
    font-size: 1rem;
    white-space: nowrap;
}

.amount-positive {
    color: #15803d;
}

.amount-negative {
    color: #b91c1c;
}

.transaction-description {
    color: var(--neutral-600);
}

@media (max-width: 768px) {
    .transactions-table th,
    .transactions-table td {
        padding: 0.75rem 0.5rem;
        font-size: 0.85rem;
    }
    
    .transaction-date {
        font-size: 0.75rem;
    }
    
    .transaction-type {
        font-size: 0.7rem;
        padding: 0.2rem 0.5rem;
    }
}
</style>

<?php include 'includes/footer.php'; ?>