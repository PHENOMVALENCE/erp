<?php
define('APP_ACCESS', true);
session_start();
require_once '../../config/database.php';
require_once '../../config/auth.php';

$auth = new Auth();
$auth->requireLogin();

$db = Database::getInstance();
$db->setCompanyId($_SESSION['company_id']);
$conn = $db->getConnection();
$company_id = $_SESSION['company_id'];
$user_id = $_SESSION['user_id'];

// Get employee info
$stmt = $conn->prepare("SELECT * FROM employees WHERE user_id = ? AND company_id = ? AND is_active = 1");
$stmt->execute([$user_id, $company_id]);
$employee = $stmt->fetch(PDO::FETCH_ASSOC);

// Check if Finance
$is_finance = isset($_SESSION['role']) && in_array($_SESSION['role'], ['admin', 'finance', 'super_admin']);

// Statistics
$stats = ['total_balance' => 0, 'accounts' => 0, 'pending' => 0];
try {
    $stmt = $conn->prepare("SELECT COUNT(*) as accounts, COALESCE(SUM(current_balance), 0) as balance 
        FROM petty_cash_accounts WHERE company_id = ? AND status = 'active'");
    $stmt->execute([$company_id]);
    $row = $stmt->fetch();
    $stats['accounts'] = $row['accounts'];
    $stats['total_balance'] = $row['balance'];
    
    if ($is_finance) {
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM petty_cash_transactions pct
            JOIN petty_cash_accounts pca ON pct.petty_cash_id = pca.petty_cash_id
            WHERE pca.company_id = ? AND pct.status = 'pending' AND pct.transaction_type = 'disbursement'");
        $stmt->execute([$company_id]);
        $stats['pending'] = $stmt->fetch()['count'];
    }
} catch (Exception $e) {}

// Accounts
$accounts = [];
try {
    $stmt = $conn->prepare("SELECT *, maximum_limit as maximum_balance FROM petty_cash_accounts WHERE company_id = ? AND status = 'active'");
    $stmt->execute([$company_id]);
    $accounts = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

// Recent transactions
$recent_transactions = [];
try {
    $stmt = $conn->prepare("SELECT pct.*, pca.account_name, e.full_name as requester_name
        FROM petty_cash_transactions pct
        JOIN petty_cash_accounts pca ON pct.petty_cash_id = pca.petty_cash_id
        LEFT JOIN employees e ON pct.requested_by = e.employee_id
        WHERE pca.company_id = ? AND pct.status = 'approved'
        ORDER BY pct.transaction_date DESC LIMIT 10");
    $stmt->execute([$company_id]);
    $recent_transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

$page_title = 'Petty Cash Management';
require_once '../../includes/header.php';
?>

<style>
.stats-card{background:white;border-radius:12px;padding:1.5rem;box-shadow:0 2px 8px rgba(0,0,0,0.08);border-left:4px solid;transition:transform .2s}
.stats-card:hover{transform:translateY(-4px)}
.stats-card.primary{border-left-color:#007bff}.stats-card.success{border-left-color:#28a745}
.stats-card.warning{border-left-color:#ffc107}
.stats-number{font-size:2rem;font-weight:700;color:#2c3e50}
.stats-label{color:#6c757d;font-size:.875rem;font-weight:500}
.action-card{background:white;border-radius:12px;padding:2rem;text-align:center;box-shadow:0 2px 8px rgba(0,0,0,0.08);transition:all .3s;text-decoration:none;color:inherit;display:block}
.action-card:hover{transform:translateY(-5px);box-shadow:0 8px 25px rgba(0,0,0,0.15)}
.action-card i{font-size:2.5rem;color:#007bff;margin-bottom:1rem}
.table-card{background:white;border-radius:12px;padding:1.5rem;box-shadow:0 2px 8px rgba(0,0,0,0.08)}
.account-card{background:white;border-radius:12px;padding:1.5rem;box-shadow:0 2px 8px rgba(0,0,0,0.08);border-left:4px solid #28a745;margin-bottom:1rem}
.account-card.low{border-left-color:#dc3545}
.balance-display{font-size:1.5rem;font-weight:700;color:#28a745}
.balance-display.low{color:#dc3545}
</style>

<div class="content-header mb-4">
    <div class="container-fluid">
        <div class="row align-items-center">
            <div class="col-sm-6"><h1 class="m-0 fw-bold"><i class="fas fa-wallet me-2"></i>Petty Cash</h1></div>
            <div class="col-sm-6 text-end">
                <a href="request.php" class="btn btn-primary"><i class="fas fa-plus-circle me-1"></i> Request Cash</a>
            </div>
        </div>
    </div>
</div>

<div class="container-fluid">
    <?php if (isset($_SESSION['success_message'])): ?>
    <div class="alert alert-success alert-dismissible fade show"><?= $_SESSION['success_message']; unset($_SESSION['success_message']); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <div class="row g-3 mb-4">
        <div class="col-lg-4 col-6">
            <div class="stats-card primary">
                <div class="stats-number">TSH <?= number_format($stats['total_balance']) ?></div>
                <div class="stats-label">Total Balance</div>
            </div>
        </div>
        <div class="col-lg-4 col-6">
            <div class="stats-card success">
                <div class="stats-number"><?= $stats['accounts'] ?></div>
                <div class="stats-label">Active Accounts</div>
            </div>
        </div>
        <?php if ($is_finance): ?>
        <div class="col-lg-4 col-6">
            <div class="stats-card warning">
                <div class="stats-number"><?= $stats['pending'] ?></div>
                <div class="stats-label">Pending Requests</div>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-md-3"><a href="request.php" class="action-card"><i class="fas fa-hand-holding-usd"></i><h5>Request Cash</h5><p class="text-muted small mb-0">Submit expense request</p></a></div>
        <?php if ($is_finance): ?>
        <div class="col-md-3"><a href="approvals.php" class="action-card"><i class="fas fa-clipboard-check"></i><h5>Approvals</h5><p class="text-muted small mb-0">Review requests</p></a></div>
        <div class="col-md-3"><a href="replenish.php" class="action-card"><i class="fas fa-plus-circle"></i><h5>Replenish</h5><p class="text-muted small mb-0">Add funds</p></a></div>
        <div class="col-md-3"><a href="accounts.php" class="action-card"><i class="fas fa-cog"></i><h5>Accounts</h5><p class="text-muted small mb-0">Manage accounts</p></a></div>
        <?php else: ?>
        <div class="col-md-3"><a href="my-requests.php" class="action-card"><i class="fas fa-history"></i><h5>My Requests</h5><p class="text-muted small mb-0">View request history</p></a></div>
        <?php endif; ?>
    </div>

    <div class="row">
        <div class="col-lg-6">
            <h5 class="mb-3"><i class="fas fa-wallet me-2"></i>Cash Accounts</h5>
            <?php if (empty($accounts)): ?>
            <div class="alert alert-info">No petty cash accounts set up yet.</div>
            <?php else: ?>
            <?php foreach ($accounts as $acc): 
                $percent = $acc['maximum_balance'] > 0 ? ($acc['current_balance'] / $acc['maximum_balance']) * 100 : 0;
                $is_low = $percent < 20;
            ?>
            <div class="account-card <?= $is_low ? 'low' : '' ?>">
                <div class="d-flex justify-content-between align-items-start mb-2">
                    <div>
                        <h6 class="mb-0"><?= htmlspecialchars($acc['account_name']) ?></h6>
                        <small class="text-muted"><?= htmlspecialchars($acc['account_code'] ?? '') ?></small>
                    </div>
                    <span class="balance-display <?= $is_low ? 'low' : '' ?>">TSH <?= number_format($acc['current_balance']) ?></span>
                </div>
                <div class="progress" style="height:8px">
                    <div class="progress-bar <?= $is_low ? 'bg-danger' : 'bg-success' ?>" style="width:<?= $percent ?>%"></div>
                </div>
                <small class="text-muted">of TSH <?= number_format($acc['maximum_balance']) ?></small>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>
        
        <div class="col-lg-6">
            <div class="table-card">
                <h5 class="mb-3"><i class="fas fa-history me-2"></i>Recent Transactions</h5>
                <?php if (empty($recent_transactions)): ?>
                <p class="text-muted text-center py-4">No transactions yet.</p>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover table-sm">
                        <thead class="table-light"><tr><th>Description</th><th>Amount</th><th>Date</th></tr></thead>
                        <tbody>
                            <?php foreach ($recent_transactions as $txn): ?>
                            <tr>
                                <td>
                                    <?php if ($txn['transaction_type'] === 'REPLENISHMENT'): ?>
                                    <i class="fas fa-arrow-down text-success me-1"></i>
                                    <?php else: ?>
                                    <i class="fas fa-arrow-up text-danger me-1"></i>
                                    <?php endif; ?>
                                    <?= htmlspecialchars($txn['description']) ?>
                                </td>
                                <td class="<?= $txn['transaction_type'] === 'REPLENISHMENT' ? 'text-success' : 'text-danger' ?>">
                                    <?= $txn['transaction_type'] === 'REPLENISHMENT' ? '+' : '-' ?>TSH <?= number_format($txn['amount']) ?>
                                </td>
                                <td><?= date('M d', strtotime($txn['transaction_date'])) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>
