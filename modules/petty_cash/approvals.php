<?php
/**
 * Petty Cash Approvals
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

// Check permission
if (!hasPermission($conn, $user_id, ['FINANCE_OFFICER', 'COMPANY_ADMIN', 'SUPER_ADMIN'])) {
    $_SESSION['error_message'] = "You don't have permission to approve petty cash requests.";
    header('Location: index.php');
    exit;
}

$status_filter = $_GET['status'] ?? 'PENDING';

// Get transactions
$sql = "SELECT pct.*, pca.account_name, pca.current_balance,
               CONCAT(e.first_name, ' ', e.last_name) as requester_name, e.employee_number,
               d.department_name,
               (SELECT CONCAT(u.first_name, ' ', u.last_name) FROM users u WHERE u.id = pct.approved_by) as approver_name
        FROM petty_cash_transactions pct
        JOIN petty_cash_accounts pca ON pct.account_id = pca.account_id
        JOIN employees e ON pct.requested_by = e.employee_id
        LEFT JOIN departments d ON e.department_id = d.department_id
        WHERE pca.company_id = ? AND pct.transaction_type = 'DISBURSEMENT'";
$params = [$company_id];

if ($status_filter !== 'ALL') {
    $sql .= " AND pct.status = ?";
    $params[] = $status_filter;
}

$sql .= " ORDER BY pct.created_at DESC";
$stmt = $conn->prepare($sql);
$stmt->execute($params);
$transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Count by status
$counts = [];
$count_sql = "SELECT status, COUNT(*) as count 
              FROM petty_cash_transactions pct
              JOIN petty_cash_accounts pca ON pct.account_id = pca.account_id
              WHERE pca.company_id = ? AND pct.transaction_type = 'DISBURSEMENT'
              GROUP BY status";
$stmt = $conn->prepare($count_sql);
$stmt->execute([$company_id]);
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $counts[$row['status']] = $row['count'];
}

$page_title = "Petty Cash Approvals";
require_once '../../includes/header.php';
?>

<style>
    .status-tabs {
        display: flex;
        gap: 10px;
        margin-bottom: 20px;
    }
    .status-tab {
        padding: 10px 20px;
        border-radius: 25px;
        text-decoration: none;
        color: #6c757d;
        background: #f8f9fa;
        transition: all 0.2s;
    }
    .status-tab:hover { background: #e9ecef; color: #495057; }
    .status-tab.active {
        background: linear-gradient(135deg, #667eea, #764ba2);
        color: white;
    }
    .approval-card {
        background: white;
        border-radius: 15px;
        box-shadow: 0 4px 15px rgba(0,0,0,0.08);
        overflow: hidden;
    }
    .request-row {
        padding: 20px;
        border-bottom: 1px solid #eee;
        transition: background 0.2s;
    }
    .request-row:hover { background: #f8f9fe; }
    .request-row:last-child { border-bottom: none; }
    .amount-display {
        font-size: 1.3rem;
        font-weight: 600;
        color: #667eea;
    }
</style>

<div class="content-wrapper">
    <section class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1><i class="fas fa-clipboard-check me-2"></i>Petty Cash Approvals</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-end">
                        <li class="breadcrumb-item"><a href="../../dashboard.php">Home</a></li>
                        <li class="breadcrumb-item"><a href="index.php">Petty Cash</a></li>
                        <li class="breadcrumb-item active">Approvals</li>
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
            
            <?php if (isset($_SESSION['error_message'])): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <?php echo $_SESSION['error_message']; unset($_SESSION['error_message']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>

            <!-- Status Tabs -->
            <div class="status-tabs">
                <a href="?status=PENDING" class="status-tab <?php echo $status_filter === 'PENDING' ? 'active' : ''; ?>">
                    Pending <span class="badge bg-warning ms-1"><?php echo $counts['PENDING'] ?? 0; ?></span>
                </a>
                <a href="?status=APPROVED" class="status-tab <?php echo $status_filter === 'APPROVED' ? 'active' : ''; ?>">
                    Approved <span class="badge bg-success ms-1"><?php echo $counts['APPROVED'] ?? 0; ?></span>
                </a>
                <a href="?status=REJECTED" class="status-tab <?php echo $status_filter === 'REJECTED' ? 'active' : ''; ?>">
                    Rejected <span class="badge bg-danger ms-1"><?php echo $counts['REJECTED'] ?? 0; ?></span>
                </a>
                <a href="?status=ALL" class="status-tab <?php echo $status_filter === 'ALL' ? 'active' : ''; ?>">
                    All <span class="badge bg-secondary ms-1"><?php echo array_sum($counts); ?></span>
                </a>
            </div>

            <!-- Requests List -->
            <div class="approval-card">
                <?php if (empty($transactions)): ?>
                <div class="text-center py-5">
                    <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                    <p class="text-muted">No requests found.</p>
                </div>
                <?php else: ?>
                <?php foreach ($transactions as $txn): ?>
                <div class="request-row">
                    <div class="row align-items-center">
                        <div class="col-md-4">
                            <h6 class="mb-1"><?php echo htmlspecialchars($txn['requester_name']); ?></h6>
                            <small class="text-muted">
                                <?php echo htmlspecialchars($txn['employee_number']); ?> • 
                                <?php echo htmlspecialchars($txn['department_name'] ?? 'N/A'); ?>
                            </small>
                            <br>
                            <code><?php echo htmlspecialchars($txn['transaction_reference']); ?></code>
                        </div>
                        <div class="col-md-3">
                            <strong><?php echo htmlspecialchars($txn['description']); ?></strong>
                            <br>
                            <small class="text-muted">
                                <?php echo htmlspecialchars($txn['account_name']); ?> • 
                                <?php echo str_replace('_', ' ', $txn['category']); ?>
                            </small>
                        </div>
                        <div class="col-md-2 text-center">
                            <span class="amount-display"><?php echo formatCurrency($txn['amount']); ?></span>
                            <br>
                            <small class="text-muted"><?php echo date('M d, Y', strtotime($txn['created_at'])); ?></small>
                        </div>
                        <div class="col-md-3 text-end">
                            <?php echo getStatusBadge($txn['status']); ?>
                            
                            <?php if ($txn['status'] === 'PENDING'): ?>
                            <div class="mt-2">
                                <button type="button" class="btn btn-sm btn-success approve-btn"
                                        data-id="<?php echo $txn['transaction_id']; ?>"
                                        data-ref="<?php echo htmlspecialchars($txn['transaction_reference']); ?>"
                                        data-name="<?php echo htmlspecialchars($txn['requester_name']); ?>"
                                        data-amount="<?php echo formatCurrency($txn['amount']); ?>"
                                        data-balance="<?php echo $txn['current_balance']; ?>">
                                    <i class="fas fa-check"></i> Approve
                                </button>
                                <button type="button" class="btn btn-sm btn-danger reject-btn"
                                        data-id="<?php echo $txn['transaction_id']; ?>"
                                        data-ref="<?php echo htmlspecialchars($txn['transaction_reference']); ?>">
                                    <i class="fas fa-times"></i> Reject
                                </button>
                            </div>
                            <?php elseif ($txn['approver_name']): ?>
                            <br><small class="text-muted">By: <?php echo htmlspecialchars($txn['approver_name']); ?></small>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <?php if ($txn['status'] === 'REJECTED' && $txn['rejection_reason']): ?>
                    <div class="alert alert-danger mt-3 mb-0 py-2">
                        <small><strong>Reason:</strong> <?php echo htmlspecialchars($txn['rejection_reason']); ?></small>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
                <?php endif; ?>
            </div>

        </div>
    </section>
</div>

<!-- Approve Modal -->
<div class="modal fade" id="approveModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="process.php">
                <input type="hidden" name="action" value="approve">
                <input type="hidden" name="transaction_id" id="approveTransactionId">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title"><i class="fas fa-check-circle me-2"></i>Approve Request</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Approve petty cash request <strong id="approveRef"></strong>?</p>
                    <p>Requester: <strong id="approveName"></strong></p>
                    <p>Amount: <strong id="approveAmount"></strong></p>
                    <div class="alert alert-info" id="balanceAlert"></div>
                    <div class="mb-3">
                        <label class="form-label">Comments (optional)</label>
                        <textarea name="comments" class="form-control" rows="2"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-check me-2"></i>Approve & Disburse
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Reject Modal -->
<div class="modal fade" id="rejectModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="process.php">
                <input type="hidden" name="action" value="reject">
                <input type="hidden" name="transaction_id" id="rejectTransactionId">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title"><i class="fas fa-times-circle me-2"></i>Reject Request</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Reject request <strong id="rejectRef"></strong>?</p>
                    <div class="mb-3">
                        <label class="form-label">Rejection Reason <span class="text-danger">*</span></label>
                        <textarea name="rejection_reason" class="form-control" rows="3" required
                                  placeholder="Please provide a reason..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">
                        <i class="fas fa-times me-2"></i>Reject
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Approve button
    document.querySelectorAll('.approve-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            document.getElementById('approveTransactionId').value = this.dataset.id;
            document.getElementById('approveRef').textContent = this.dataset.ref;
            document.getElementById('approveName').textContent = this.dataset.name;
            document.getElementById('approveAmount').textContent = this.dataset.amount;
            document.getElementById('balanceAlert').textContent = 'Account balance after: TZS ' + 
                (parseFloat(this.dataset.balance) - parseFloat(this.dataset.amount.replace(/[^0-9.-]+/g,''))).toLocaleString();
            new bootstrap.Modal(document.getElementById('approveModal')).show();
        });
    });
    
    // Reject button
    document.querySelectorAll('.reject-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            document.getElementById('rejectTransactionId').value = this.dataset.id;
            document.getElementById('rejectRef').textContent = this.dataset.ref;
            new bootstrap.Modal(document.getElementById('rejectModal')).show();
        });
    });
});
</script>

<?php require_once '../../includes/footer.php'; ?>
