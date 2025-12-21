<?php
/**
 * Petty Cash Management Dashboard
 * Mkumbi Investments ERP System
 */

define('APP_ACCESS', true);
require_once '../../config/database.php';
require_once '../../includes/functions.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../../login.php');
    exit;
}

$db = Database::getInstance();
$conn = $db->getConnection();
$company_id = $_SESSION['company_id'];
$user_id = $_SESSION['user_id'];

$is_finance = hasPermission($conn, $user_id, ['FINANCE_OFFICER', 'COMPANY_ADMIN', 'SUPER_ADMIN']);
$employee = getEmployeeByUserId($conn, $user_id, $company_id);

// Get petty cash accounts
$sql = "SELECT pc.*, 
               (SELECT SUM(amount) FROM petty_cash_transactions WHERE account_id = pc.account_id AND transaction_type = 'REPLENISHMENT') as total_replenished,
               (SELECT SUM(amount) FROM petty_cash_transactions WHERE account_id = pc.account_id AND transaction_type = 'DISBURSEMENT' AND status = 'APPROVED') as total_disbursed
        FROM petty_cash_accounts pc
        WHERE pc.company_id = ? AND pc.is_active = 1
        ORDER BY pc.account_name";
$stmt = $conn->prepare($sql);
$stmt->execute([$company_id]);
$accounts = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get pending requests
$pending_requests = [];
if ($is_finance) {
    $sql = "SELECT pct.*, pca.account_name, 
                   CONCAT(e.first_name, ' ', e.last_name) as requester_name
            FROM petty_cash_transactions pct
            JOIN petty_cash_accounts pca ON pct.account_id = pca.account_id
            JOIN employees e ON pct.requested_by = e.employee_id
            WHERE pca.company_id = ? AND pct.status = 'PENDING' AND pct.transaction_type = 'DISBURSEMENT'
            ORDER BY pct.created_at ASC";
    $stmt = $conn->prepare($sql);
    $stmt->execute([$company_id]);
    $pending_requests = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Get my recent requests
$my_requests = [];
if ($employee) {
    $sql = "SELECT pct.*, pca.account_name
            FROM petty_cash_transactions pct
            JOIN petty_cash_accounts pca ON pct.account_id = pca.account_id
            WHERE pct.requested_by = ?
            ORDER BY pct.created_at DESC LIMIT 10";
    $stmt = $conn->prepare($sql);
    $stmt->execute([$employee['employee_id']]);
    $my_requests = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Get recent transactions
$sql = "SELECT pct.*, pca.account_name, 
               CONCAT(e.first_name, ' ', e.last_name) as requester_name
        FROM petty_cash_transactions pct
        JOIN petty_cash_accounts pca ON pct.account_id = pca.account_id
        LEFT JOIN employees e ON pct.requested_by = e.employee_id
        WHERE pca.company_id = ? AND pct.status = 'APPROVED'
        ORDER BY pct.transaction_date DESC LIMIT 10";
$stmt = $conn->prepare($sql);
$stmt->execute([$company_id]);
$recent_transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate totals
$total_balance = array_sum(array_column($accounts, 'current_balance'));
$total_limit = array_sum(array_column($accounts, 'maximum_balance'));

$page_title = "Petty Cash Management";
require_once '../../includes/header.php';
?>

<style>
    .account-card {
        background: white;
        border-radius: 15px;
        box-shadow: 0 4px 15px rgba(0,0,0,0.08);
        padding: 25px;
        margin-bottom: 20px;
        border-left: 4px solid #667eea;
        transition: transform 0.2s;
    }
    .account-card:hover { transform: translateY(-3px); }
    .account-card.low-balance { border-left-color: #dc3545; }
    .account-card.ok-balance { border-left-color: #28a745; }
    
    .balance-display {
        font-size: 1.8rem;
        font-weight: 700;
        color: #667eea;
    }
    .balance-display.low { color: #dc3545; }
    
    .stat-card {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        border-radius: 15px;
        color: white;
        padding: 25px;
        position: relative;
        overflow: hidden;
    }
    .stat-card.success { background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%); }
    .stat-card.warning { background: linear-gradient(135deg, #F2994A 0%, #F2C94C 100%); }
    .stat-card::after {
        content: '';
        position: absolute;
        top: -50%;
        right: -50%;
        width: 100%;
        height: 100%;
        background: rgba(255,255,255,0.1);
        border-radius: 50%;
    }
    
    .quick-action-card {
        background: white;
        border-radius: 15px;
        padding: 25px;
        text-align: center;
        box-shadow: 0 4px 15px rgba(0,0,0,0.08);
        transition: transform 0.2s;
    }
    .quick-action-card:hover { transform: translateY(-5px); }
    .quick-action-card i {
        font-size: 2.5rem;
        background: linear-gradient(135deg, #667eea, #764ba2);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        margin-bottom: 15px;
    }
    
    .pending-badge {
        position: absolute;
        top: 10px;
        right: 10px;
        background: #dc3545;
        color: white;
        border-radius: 50%;
        width: 25px;
        height: 25px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 0.8rem;
        font-weight: bold;
    }
    
    .transaction-list {
        background: white;
        border-radius: 15px;
        box-shadow: 0 4px 15px rgba(0,0,0,0.08);
        overflow: hidden;
    }
    .transaction-item {
        padding: 15px 20px;
        border-bottom: 1px solid #eee;
        display: flex;
        align-items: center;
        justify-content: space-between;
    }
    .transaction-item:last-child { border-bottom: none; }
    .transaction-amount.in { color: #28a745; }
    .transaction-amount.out { color: #dc3545; }
</style>

<div class="content-wrapper">
    <section class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1><i class="fas fa-wallet me-2"></i>Petty Cash</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-end">
                        <li class="breadcrumb-item"><a href="../../dashboard.php">Home</a></li>
                        <li class="breadcrumb-item active">Petty Cash</li>
                    </ol>
                </div>
            </div>
        </div>
    </section>

    <section class="content">
        <div class="container-fluid">
            
            <?php if (isset($_SESSION['success_message'])): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <?php echo $_SESSION['success_message']; unset($_SESSION['success_message']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>

            <!-- Summary Cards -->
            <div class="row mb-4">
                <div class="col-lg-4 col-md-6 mb-3">
                    <div class="stat-card">
                        <i class="fas fa-coins" style="font-size:3rem; opacity:0.3; position:absolute; right:20px; top:20px;"></i>
                        <h3><?php echo formatCurrency($total_balance); ?></h3>
                        <p class="mb-0">Total Cash Balance</p>
                    </div>
                </div>
                <div class="col-lg-4 col-md-6 mb-3">
                    <div class="stat-card success">
                        <i class="fas fa-piggy-bank" style="font-size:3rem; opacity:0.3; position:absolute; right:20px; top:20px;"></i>
                        <h3><?php echo count($accounts); ?></h3>
                        <p class="mb-0">Active Accounts</p>
                    </div>
                </div>
                <div class="col-lg-4 col-md-6 mb-3">
                    <div class="stat-card warning">
                        <i class="fas fa-clock" style="font-size:3rem; opacity:0.3; position:absolute; right:20px; top:20px;"></i>
                        <h3><?php echo count($pending_requests); ?></h3>
                        <p class="mb-0">Pending Requests</p>
                    </div>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="row mb-4">
                <div class="col-md-3 mb-3">
                    <a href="request.php" class="text-decoration-none">
                        <div class="quick-action-card">
                            <i class="fas fa-hand-holding-usd"></i>
                            <h5>Request Cash</h5>
                            <p class="text-muted small mb-0">Submit expense request</p>
                        </div>
                    </a>
                </div>
                <?php if ($is_finance): ?>
                <div class="col-md-3 mb-3">
                    <a href="approvals.php" class="text-decoration-none">
                        <div class="quick-action-card position-relative">
                            <i class="fas fa-clipboard-check"></i>
                            <h5>Approvals</h5>
                            <p class="text-muted small mb-0">Review requests</p>
                            <?php if (count($pending_requests) > 0): ?>
                            <span class="pending-badge"><?php echo count($pending_requests); ?></span>
                            <?php endif; ?>
                        </div>
                    </a>
                </div>
                <div class="col-md-3 mb-3">
                    <a href="replenish.php" class="text-decoration-none">
                        <div class="quick-action-card">
                            <i class="fas fa-plus-circle"></i>
                            <h5>Replenish</h5>
                            <p class="text-muted small mb-0">Add funds</p>
                        </div>
                    </a>
                </div>
                <div class="col-md-3 mb-3">
                    <a href="accounts.php" class="text-decoration-none">
                        <div class="quick-action-card">
                            <i class="fas fa-cog"></i>
                            <h5>Accounts</h5>
                            <p class="text-muted small mb-0">Manage accounts</p>
                        </div>
                    </a>
                </div>
                <?php else: ?>
                <div class="col-md-3 mb-3">
                    <a href="my-requests.php" class="text-decoration-none">
                        <div class="quick-action-card">
                            <i class="fas fa-history"></i>
                            <h5>My Requests</h5>
                            <p class="text-muted small mb-0">View request history</p>
                        </div>
                    </a>
                </div>
                <?php endif; ?>
            </div>

            <div class="row">
                <!-- Petty Cash Accounts -->
                <div class="col-lg-6">
                    <h5 class="mb-3"><i class="fas fa-wallet me-2"></i>Cash Accounts</h5>
                    <?php if (empty($accounts)): ?>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>No petty cash accounts set up yet.
                        <?php if ($is_finance): ?>
                        <a href="accounts.php" class="alert-link">Create one now</a>
                        <?php endif; ?>
                    </div>
                    <?php else: ?>
                    <?php foreach ($accounts as $acc): 
                        $balance_percent = $acc['maximum_balance'] > 0 ? ($acc['current_balance'] / $acc['maximum_balance']) * 100 : 0;
                        $is_low = $balance_percent < 20;
                    ?>
                    <div class="account-card <?php echo $is_low ? 'low-balance' : 'ok-balance'; ?>">
                        <div class="d-flex justify-content-between align-items-start mb-3">
                            <div>
                                <h5 class="mb-1"><?php echo htmlspecialchars($acc['account_name']); ?></h5>
                                <small class="text-muted"><?php echo htmlspecialchars($acc['account_code'] ?? 'N/A'); ?></small>
                            </div>
                            <div class="text-end">
                                <div class="balance-display <?php echo $is_low ? 'low' : ''; ?>">
                                    <?php echo formatCurrency($acc['current_balance']); ?>
                                </div>
                                <small class="text-muted">of <?php echo formatCurrency($acc['maximum_balance']); ?></small>
                            </div>
                        </div>
                        <div class="progress" style="height: 8px; border-radius: 4px;">
                            <div class="progress-bar <?php echo $is_low ? 'bg-danger' : 'bg-success'; ?>" 
                                 style="width: <?php echo $balance_percent; ?>%"></div>
                        </div>
                        <?php if ($is_low && $is_finance): ?>
                        <div class="mt-3">
                            <a href="replenish.php?account=<?php echo $acc['account_id']; ?>" class="btn btn-sm btn-warning">
                                <i class="fas fa-plus me-1"></i>Replenish
                            </a>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <!-- Recent Transactions & Pending -->
                <div class="col-lg-6">
                    <?php if ($is_finance && !empty($pending_requests)): ?>
                    <!-- Pending Requests -->
                    <h5 class="mb-3"><i class="fas fa-clock me-2"></i>Pending Requests</h5>
                    <div class="transaction-list mb-4">
                        <?php foreach (array_slice($pending_requests, 0, 5) as $req): ?>
                        <div class="transaction-item">
                            <div>
                                <strong><?php echo htmlspecialchars($req['requester_name']); ?></strong>
                                <small class="d-block text-muted"><?php echo htmlspecialchars($req['description']); ?></small>
                            </div>
                            <div class="text-end">
                                <strong class="transaction-amount out"><?php echo formatCurrency($req['amount']); ?></strong>
                                <a href="approvals.php?id=<?php echo $req['transaction_id']; ?>" class="btn btn-sm btn-primary ms-2">
                                    Review
                                </a>
                            </div>
                        </div>
                        <?php endforeach; ?>
                        <?php if (count($pending_requests) > 5): ?>
                        <div class="p-3 text-center">
                            <a href="approvals.php" class="btn btn-outline-primary btn-sm">
                                View All (<?php echo count($pending_requests); ?>)
                            </a>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>

                    <!-- Recent Transactions -->
                    <h5 class="mb-3"><i class="fas fa-history me-2"></i>Recent Transactions</h5>
                    <div class="transaction-list">
                        <?php if (empty($recent_transactions)): ?>
                        <div class="text-center py-4">
                            <p class="text-muted mb-0">No transactions yet.</p>
                        </div>
                        <?php else: ?>
                        <?php foreach ($recent_transactions as $txn): ?>
                        <div class="transaction-item">
                            <div>
                                <strong>
                                    <?php if ($txn['transaction_type'] === 'REPLENISHMENT'): ?>
                                    <i class="fas fa-arrow-down text-success me-1"></i>
                                    <?php else: ?>
                                    <i class="fas fa-arrow-up text-danger me-1"></i>
                                    <?php endif; ?>
                                    <?php echo htmlspecialchars($txn['description']); ?>
                                </strong>
                                <small class="d-block text-muted">
                                    <?php echo htmlspecialchars($txn['account_name']); ?> • 
                                    <?php echo date('M d, Y', strtotime($txn['transaction_date'])); ?>
                                </small>
                            </div>
                            <span class="transaction-amount <?php echo $txn['transaction_type'] === 'REPLENISHMENT' ? 'in' : 'out'; ?>">
                                <?php echo ($txn['transaction_type'] === 'REPLENISHMENT' ? '+' : '-') . formatCurrency($txn['amount']); ?>
                            </span>
                        </div>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

        </div>
    </section>
</div>

<?php require_once '../../includes/footer.php'; ?>
