<?php
require_once 'config/database.php';
require_once 'includes/csrf.php';
require_once 'includes/session.php';
require_once 'includes/kiosk.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: auth/login.php");
    exit;
}

$user_id = $_SESSION['user_id'];

// Fetch balance
$stmt = $conn->prepare("SELECT balance FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$balance = $user['balance'] ?? 0;

// Fetch transactions
$stmt = $conn->prepare("SELECT * FROM transactions WHERE user_id = ? ORDER BY created_at DESC LIMIT 20");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$transactions = $stmt->get_result();

$page_title = "My Wallet | TAMCC Deli";
include 'includes/header.php';
?>

<div class="container">
    <h1 class="text-3xl font-bold mb-8">My Wallet</h1>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        <!-- Balance Card -->
        <div class="lg:col-span-1">
            <div class="card bg-gradient-to-br from-primary-600 to-primary-700 text-white">
                <div class="card-content text-center">
                    <div class="inline-flex items-center justify-center w-16 h-16 bg-white/20 rounded-full mb-4">
                        <span class="dashicons dashicons-money" style="font-size: 2rem;"></span>
                    </div>
                    <p class="text-white/80 mb-2">Current Balance</p>
                    <p class="text-4xl font-bold">$<?= number_format($balance, 2) ?></p>
                </div>
            </div>

            <!-- Top-up Form -->
            <div class="card mt-6">
                <div class="card-header">
                    <h3 class="card-title flex items-center gap-2">
                        <span class="dashicons dashicons-plus-alt"></span> Top Up Wallet
                    </h3>
                </div>
                <div class="card-content">
                    <form method="post" action="topup.php">
                        <input type="hidden" name="csrf_token" value="<?= generateToken() ?>">
                        <div class="form-group">
                            <label class="form-label">Amount ($)</label>
                            <input type="number" name="amount" step="0.01" min="1" max="1000" class="form-input" required>
                            <p class="text-sm text-gray-500 mt-1">Maximum $1000 per transaction</p>
                        </div>
                        <div class="grid grid-cols-3 gap-2 mb-4">
                            <?php foreach ([10, 25, 50] as $amt): ?>
                                <button type="button" class="btn btn-outline btn-sm" onclick="this.form.amount.value=<?= $amt ?>">$<?= $amt ?></button>
                            <?php endforeach; ?>
                        </div>
                        <button type="submit" class="btn btn-accent w-full">Add Funds</button>
                    </form>
                </div>
            </div>

            <div class="mt-6">
                <a href="<?= kiosk_url('menu.php') ?>" class="btn btn-outline w-full">Continue Shopping</a>
            </div>
        </div>

        <!-- Transaction History -->
        <div class="lg:col-span-2">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Recent Transactions</h3>
                </div>
                <div class="card-content">
                    <div class="table-wrapper">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Type</th>
                                    <th>Description</th>
                                    <th class="text-right">Amount</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($transactions && $transactions->num_rows > 0): ?>
                                    <?php while ($tx = $transactions->fetch_assoc()): ?>
                                    <tr>
                                        <td><?= date('M j, Y g:i a', strtotime($tx['created_at'])) ?></td>
                                        <td>
                                            <span class="inline-block px-2 py-1 rounded-full text-xs font-medium <?= $tx['type'] === 'topup' ? 'bg-green-100 text-green-800' : ($tx['type'] === 'payment' ? 'bg-red-100 text-red-800' : 'bg-gray-100 text-gray-800') ?>">
                                                <?= ucfirst($tx['type']) ?>
                                            </span>
                                        </td>
                                        <td><?= htmlspecialchars($tx['description'] ?? '—') ?></td>
                                        <td class="text-right <?= $tx['type'] === 'topup' ? 'text-green-600' : 'text-red-600' ?>">
                                            <?= $tx['type'] === 'topup' ? '+' : '-' ?> $<?= number_format($tx['amount'], 2) ?>
                                        </td>
                                    </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="4" class="text-center text-gray-500">No transactions yet</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Wallet Info -->
            <div class="card mt-6">
                <div class="card-content">
                    <h3 class="text-lg font-bold mb-3">How It Works</h3>
                    <ul class="space-y-2 text-sm text-gray-600">
                        <li class="flex items-start gap-2">• Add funds to your wallet using a credit/debit card</li>
                        <li class="flex items-start gap-2">• Use your wallet balance to pay for orders quickly at checkout</li>
                        <li class="flex items-start gap-2">• Your balance never expires and can be used anytime</li>
                        <li class="flex items-start gap-2">• Track all your transactions in the history above</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>