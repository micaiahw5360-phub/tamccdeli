<?php
require_once 'config/database.php';
require_once 'includes/session.php';
require_once 'includes/csrf.php';
require_once 'includes/header.php';
require_once 'includes/kiosk.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: auth/login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$error = '';
$success = '';

// Fetch user balance
$stmt = $conn->prepare("SELECT username, balance FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$balance = $user['balance'] ?? 0;
$username = $user['username'] ?? 'Guest';

// Fetch recent transactions
$stmt = $conn->prepare("SELECT * FROM transactions WHERE user_id = ? ORDER BY created_at DESC LIMIT 20");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$transactions = $stmt->get_result();

// Handle success/error messages from topup.php
$success_msg = $_SESSION['topup_success'] ?? '';
$error_msg = $_SESSION['topup_error'] ?? '';
unset($_SESSION['topup_success'], $_SESSION['topup_error']);

$page_title = "My Wallet | TAMCC Deli";
include 'includes/header.php';
?>

<style>
    /* Styles exactly as in the screenshot */
    .wallet-balance-card {
        background: linear-gradient(135deg, #074af2 0%, #0639c0 100%);
        color: white;
        border-radius: 1rem;
        padding: 2rem;
        text-align: center;
        box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1);
    }
    .balance-icon {
        width: 4rem;
        height: 4rem;
        background: rgba(255, 255, 255, 0.2);
        border-radius: 9999px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        margin-bottom: 1rem;
    }
    .balance-icon svg { width: 2rem; height: 2rem; }
    .balance-amount { font-size: 2.5rem; font-weight: 800; margin: 0.5rem 0; }
    .quick-amount-grid {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 0.5rem;
        margin: 1rem 0;
    }
    .quick-amount {
        background: #f3f4f6;
        border: 1px solid #e5e7eb;
        border-radius: 0.5rem;
        padding: 0.5rem;
        font-weight: 500;
        transition: all 0.2s;
        cursor: pointer;
    }
    .quick-amount:hover {
        background: #e5e7eb;
        transform: translateY(-2px);
    }
    .transaction-type {
        display: inline-flex;
        align-items: center;
        padding: 0.25rem 0.75rem;
        border-radius: 9999px;
        font-size: 0.75rem;
        font-weight: 600;
        text-transform: uppercase;
    }
    .transaction-type-credit { background: #dcfce7; color: #15803d; }
    .transaction-type-debit { background: #fee2e2; color: #b91c1c; }
    .amount-positive { color: #15803d; font-weight: 600; }
    .amount-negative { color: #b91c1c; font-weight: 600; }
    .info-list { list-style: none; padding-left: 0; }
    .info-list li {
        display: flex;
        align-items: flex-start;
        gap: 0.5rem;
        margin-bottom: 0.75rem;
        font-size: 0.875rem;
        color: #4b5563;
    }
    .info-list li::before {
        content: "•";
        color: #074af2;
        font-weight: bold;
        font-size: 1.25rem;
    }
    @media (max-width: 768px) {
        .wallet-balance-card { padding: 1.5rem; }
        .balance-amount { font-size: 2rem; }
    }
</style>

<div class="container mx-auto px-4 py-8 max-w-6xl">
    <!-- Header -->
    <div class="mb-8">
        <a href="<?= kiosk_url('index.php') ?>" class="inline-flex items-center text-gray-600 hover:text-gray-900 mb-4">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="mr-2"><path d="m15 18-6-6 6-6"/></svg>
            Back to Home
        </a>
        <h1 class="text-3xl font-bold text-gray-900">My Wallet</h1>
        <p class="text-gray-600 mt-1">Manage your TAMCC Deli wallet balance</p>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        <!-- Left Column: Balance + Top‑up -->
        <div class="lg:col-span-1">
            <!-- Balance Card -->
            <div class="wallet-balance-card">
                <div class="balance-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v4"/><path d="M21 12v5a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-5"/><path d="M15 12a3 3 0 1 0 6 0 3 3 0 0 0-6 0Z"/></svg>
                </div>
                <p class="text-white/80 text-sm">Current Balance</p>
                <div class="balance-amount">$<?= number_format($balance, 2) ?></div>
            </div>

            <!-- Top-up Form -->
            <div class="bg-white rounded-lg shadow-md p-6 mt-6 border border-gray-100">
                <h2 class="text-xl font-bold flex items-center gap-2 mb-4">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 5v14"/><path d="M5 12h14"/></svg>
                    Top Up Wallet
                </h2>
                <form method="post" action="topup.php" id="topupForm">
                    <input type="hidden" name="csrf_token" value="<?= generateToken() ?>">
                    <div class="mb-4">
                        <label for="amount" class="block text-sm font-medium text-gray-700 mb-1">Amount ($)</label>
                        <input type="number" name="amount" id="amount" step="0.01" min="1" max="500" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500">
                        <p class="text-xs text-gray-500 mt-1">Maximum $500 per transaction</p>
                    </div>

                    <!-- Quick Amount Buttons -->
                    <div class="quick-amount-grid">
                        <button type="button" class="quick-amount" data-amount="10">$10</button>
                        <button type="button" class="quick-amount" data-amount="25">$25</button>
                        <button type="button" class="quick-amount" data-amount="50">$50</button>
                    </div>

                    <button type="submit" class="w-full bg-orange-500 hover:bg-orange-600 text-white font-bold py-2 px-4 rounded-lg transition flex items-center justify-center gap-2">
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="5" width="20" height="14" rx="2"/><line x1="2" y1="10" x2="22" y2="10"/></svg>
                        Add Funds
                    </button>
                </form>
            </div>

            <!-- Continue Shopping Button -->
            <div class="mt-6">
                <a href="<?= kiosk_url('menu.php') ?>" class="block w-full text-center border border-gray-300 bg-white hover:bg-gray-50 text-gray-700 font-medium py-2 px-4 rounded-lg transition">
                    Continue Shopping
                </a>
            </div>
        </div>

        <!-- Right Column: Transaction History -->
        <div class="lg:col-span-2">
            <div class="bg-white rounded-lg shadow-md border border-gray-100">
                <div class="p-6 border-b border-gray-100">
                    <h2 class="text-xl font-bold">Recent Transactions</h2>
                </div>
                <div class="p-0 overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Type</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Description</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Amount</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php if ($transactions && $transactions->num_rows > 0): ?>
                                <?php while ($tx = $transactions->fetch_assoc()): ?>
                                    <tr>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            <?= date('M j, Y', strtotime($tx['created_at'])) ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <span class="transaction-type transaction-type-<?= $tx['type'] ?>">
                                                <?= $tx['type'] === 'topup' ? 'Credit' : 'Debit' ?>
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 text-sm text-gray-600">
                                            <?= htmlspecialchars($tx['description'] ?? ($tx['type'] === 'topup' ? 'Wallet Top‑up' : 'Order Payment')) ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium <?= $tx['type'] === 'topup' ? 'amount-positive' : 'amount-negative' ?>">
                                            <?= $tx['type'] === 'topup' ? '+' : '-' ?> $<?= number_format($tx['amount'], 2) ?>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="4" class="px-6 py-12 text-center text-gray-500">
                                        No transactions yet
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Info Card -->
            <div class="bg-white rounded-lg shadow-md p-6 mt-6 border border-gray-100">
                <h3 class="font-bold text-lg mb-3">How It Works</h3>
                <ul class="info-list">
                    <li>Add funds to your wallet using a credit/debit card</li>
                    <li>Use your wallet balance to pay for orders quickly at checkout</li>
                    <li>Your balance never expires and can be used anytime</li>
                    <li>Track all your transactions in the history above</li>
                </ul>
            </div>
        </div>
    </div>
</div>

<script>
    // Quick amount buttons
    const amountInput = document.getElementById('amount');
    document.querySelectorAll('.quick-amount').forEach(btn => {
        btn.addEventListener('click', () => {
            amountInput.value = btn.dataset.amount;
        });
    });
</script>

<?php include 'includes/footer.php'; ?>