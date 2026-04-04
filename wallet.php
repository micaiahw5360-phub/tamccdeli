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
    /* Custom wallet styles – replaces Tailwind */
    .wallet-container {
        max-width: 1200px;
        margin: 0 auto;
        padding: 2rem 1rem;
    }
    .wallet-grid {
        display: grid;
        grid-template-columns: 1fr;
        gap: 2rem;
    }
    @media (min-width: 1024px) {
        .wallet-grid {
            grid-template-columns: 1fr 2fr;
        }
    }
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
    .balance-icon svg {
        width: 2rem;
        height: 2rem;
    }
    .balance-amount {
        font-size: 2.5rem;
        font-weight: 800;
        margin: 0.5rem 0;
    }
    .topup-card, .transaction-card, .info-card {
        background: white;
        border-radius: 1rem;
        box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1);
        border: 1px solid #e5e7eb;
        padding: 1.5rem;
        margin-bottom: 1.5rem;
    }
    .topup-card h2, .transaction-card h2, .info-card h3 {
        font-size: 1.25rem;
        font-weight: 700;
        margin-bottom: 1rem;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }
    .form-group {
        margin-bottom: 1rem;
    }
    .form-group label {
        display: block;
        font-size: 0.875rem;
        font-weight: 500;
        color: #374151;
        margin-bottom: 0.25rem;
    }
    .form-group input {
        width: 100%;
        padding: 0.5rem 0.75rem;
        border: 1px solid #d1d5db;
        border-radius: 0.5rem;
        font-size: 1rem;
    }
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
        text-align: center;
    }
    .quick-amount:hover {
        background: #e5e7eb;
        transform: translateY(-2px);
    }
    .btn-add-funds {
        width: 100%;
        background: #f97316;
        color: white;
        font-weight: 700;
        padding: 0.5rem 1rem;
        border-radius: 0.5rem;
        border: none;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 0.5rem;
        transition: background 0.2s;
    }
    .btn-add-funds:hover {
        background: #ea580c;
    }
    .btn-continue {
        display: block;
        width: 100%;
        text-align: center;
        border: 1px solid #d1d5db;
        background: white;
        padding: 0.5rem 1rem;
        border-radius: 0.5rem;
        color: #374151;
        text-decoration: none;
        transition: background 0.2s;
    }
    .btn-continue:hover {
        background: #f9fafb;
    }
    .transactions-table {
        width: 100%;
        border-collapse: collapse;
    }
    .transactions-table th {
        text-align: left;
        padding: 0.75rem;
        background: #f9fafb;
        font-size: 0.75rem;
        font-weight: 600;
        text-transform: uppercase;
        color: #6b7280;
        border-bottom: 1px solid #e5e7eb;
    }
    .transactions-table td {
        padding: 0.75rem;
        border-bottom: 1px solid #e5e7eb;
        font-size: 0.875rem;
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
    .transaction-type-credit {
        background: #dcfce7;
        color: #15803d;
    }
    .transaction-type-debit {
        background: #fee2e2;
        color: #b91c1c;
    }
    .amount-positive {
        color: #15803d;
        font-weight: 600;
    }
    .amount-negative {
        color: #b91c1c;
        font-weight: 600;
    }
    .info-list {
        list-style: none;
        padding-left: 0;
    }
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
        .wallet-container {
            padding: 1rem;
        }
        .balance-amount {
            font-size: 2rem;
        }
        .transactions-table th, .transactions-table td {
            padding: 0.5rem;
        }
    }
</style>

<div class="wallet-container">
    <!-- Back link -->
    <div class="mb-6">
        <a href="<?= kiosk_url('index.php') ?>" class="inline-flex items-center text-gray-600 hover:text-gray-900">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-right: 0.5rem;"><path d="m15 18-6-6 6-6"/></svg>
            Back to Home
        </a>
    </div>

    <h1 class="text-3xl font-bold text-gray-900 mb-2">My Wallet</h1>
    <p class="text-gray-600 mb-6">Manage your TAMCC Deli wallet balance</p>

    <div class="wallet-grid">
        <!-- Left Column: Balance + Top-up -->
        <div>
            <!-- Balance Card -->
            <div class="wallet-balance-card">
                <div class="balance-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v4"/><path d="M21 12v5a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-5"/><path d="M15 12a3 3 0 1 0 6 0 3 3 0 0 0-6 0Z"/></svg>
                </div>
                <p class="text-white/80 text-sm">Current Balance</p>
                <div class="balance-amount">$<?= number_format($balance, 2) ?></div>
            </div>

            <!-- Top-up Form -->
            <div class="topup-card">
                <h2>
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 5v14"/><path d="M5 12h14"/></svg>
                    Top Up Wallet
                </h2>
                <form method="post" action="topup.php" id="topupForm">
                    <input type="hidden" name="csrf_token" value="<?= generateToken() ?>">
                    <div class="form-group">
                        <label for="amount">Amount ($)</label>
                        <input type="number" name="amount" id="amount" step="0.01" min="1" max="500" required>
                        <small class="text-gray-500 text-xs">Maximum $500 per transaction</small>
                    </div>

                    <!-- Quick Amount Buttons -->
                    <div class="quick-amount-grid">
                        <button type="button" class="quick-amount" data-amount="10">$10</button>
                        <button type="button" class="quick-amount" data-amount="25">$25</button>
                        <button type="button" class="quick-amount" data-amount="50">$50</button>
                    </div>

                    <button type="submit" class="btn-add-funds">
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="5" width="20" height="14" rx="2"/><line x1="2" y1="10" x2="22" y2="10"/></svg>
                        Add Funds
                    </button>
                </form>
            </div>

            <!-- Continue Shopping Button -->
            <a href="<?= kiosk_url('menu.php') ?>" class="btn-continue">Continue Shopping</a>
        </div>

        <!-- Right Column: Transactions & Info -->
        <div>
            <div class="transaction-card">
                <h2>Recent Transactions</h2>
                <div class="overflow-x-auto">
                    <table class="transactions-table">
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
                                        <td><?= date('M j, Y', strtotime($tx['created_at'])) ?></td>
                                        <td>
                                            <span class="transaction-type transaction-type-<?= $tx['type'] ?>">
                                                <?= $tx['type'] === 'topup' ? 'Credit' : 'Debit' ?>
                                            </span>
                                        </td>
                                        <td><?= htmlspecialchars($tx['description'] ?? ($tx['type'] === 'topup' ? 'Wallet Top‑up' : 'Order Payment')) ?></td>
                                        <td class="<?= $tx['type'] === 'topup' ? 'amount-positive' : 'amount-negative' ?> text-right">
                                            <?= $tx['type'] === 'topup' ? '+' : '-' ?> $<?= number_format($tx['amount'], 2) ?>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="4" class="text-center py-6 text-gray-500">No transactions yet</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="info-card">
                <h3>How It Works</h3>
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
    // Quick amount buttons – set the amount field value
    document.addEventListener('DOMContentLoaded', function() {
        const amountInput = document.getElementById('amount');
        const quickButtons = document.querySelectorAll('.quick-amount');
        quickButtons.forEach(btn => {
            btn.addEventListener('click', function(e) {
                e.preventDefault();
                const amount = this.getAttribute('data-amount');
                if (amountInput) {
                    amountInput.value = amount;
                }
            });
        });
    });
</script>

<?php include 'includes/footer.php'; ?>