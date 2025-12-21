<?php
/**
 * Loan Management Dashboard
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

$employee = getEmployeeByUserId($conn, $user_id, $company_id);
$is_hr = hasPermission($conn, $user_id, ['HR_OFFICER', 'FINANCE_OFFICER', 'COMPANY_ADMIN', 'SUPER_ADMIN']);

// Get employee's active loans
$my_loans = [];
if ($employee) {
    $sql = "SELECT el.*, lt.loan_type_name, lt.interest_rate,
                   (SELECT COUNT(*) FROM loan_payments lp WHERE lp.loan_id = el.loan_id) as payments_made
            FROM employee_loans el
            JOIN loan_types lt ON el.loan_type_id = lt.loan_type_id
            WHERE el.employee_id = ? AND el.status IN ('APPROVED', 'DISBURSED', 'ACTIVE')
            ORDER BY el.created_at DESC";
    $stmt = $conn->prepare($sql);
    $stmt->execute([$employee['employee_id']]);
    $my_loans = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Get pending loan applications for HR/Finance
$pending_loans = [];
if ($is_hr) {
    $sql = "SELECT el.*, lt.loan_type_name, e.first_name, e.last_name, d.department_name
            FROM employee_loans el
            JOIN loan_types lt ON el.loan_type_id = lt.loan_type_id
            JOIN employees e ON el.employee_id = e.employee_id
            LEFT JOIN departments d ON e.department_id = d.department_id
            WHERE el.company_id = ? AND el.status = 'PENDING'
            ORDER BY el.created_at ASC";
    $stmt = $conn->prepare($sql);
    $stmt->execute([$company_id]);
    $pending_loans = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Get loan statistics for HR/Finance
$stats = [];
if ($is_hr) {
    $sql = "SELECT 
                COUNT(CASE WHEN status = 'PENDING' THEN 1 END) as pending_count,
                COUNT(CASE WHEN status IN ('APPROVED', 'DISBURSED', 'ACTIVE') THEN 1 END) as active_count,
                SUM(CASE WHEN status IN ('APPROVED', 'DISBURSED', 'ACTIVE') THEN total_outstanding ELSE 0 END) as total_outstanding,
                SUM(CASE WHEN status IN ('APPROVED', 'DISBURSED', 'ACTIVE') THEN loan_amount ELSE 0 END) as total_disbursed
            FROM employee_loans 
            WHERE company_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->execute([$company_id]);
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);
}

// Get loan types for quick reference
$sql = "SELECT * FROM loan_types WHERE company_id = ? AND is_active = 1 ORDER BY loan_type_name";
$stmt = $conn->prepare($sql);
$stmt->execute([$company_id]);
$loan_types = $stmt->fetchAll(PDO::FETCH_ASSOC);

$page_title = "Loan Management";
require_once '../../includes/header.php';
?>

<style>
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
    .stat-card.danger { background: linear-gradient(135deg, #f5576c 0%, #f093fb 100%); }
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
    .stat-card h3 { font-size: 2rem; margin-bottom: 5px; }
    .stat-card p { opacity: 0.9; margin: 0; }
    .stat-card i { font-size: 3rem; opacity: 0.3; position: absolute; right: 20px; top: 20px; }
    
    .loan-card {
        background: white;
        border-radius: 15px;
        box-shadow: 0 4px 15px rgba(0,0,0,0.08);
        padding: 20px;
        margin-bottom: 20px;
        border-left: 4px solid #667eea;
        transition: transform 0.2s, box-shadow 0.2s;
    }
    .loan-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 25px rgba(0,0,0,0.12);
    }
    .loan-card.active { border-left-color: #28a745; }
    .loan-card.pending { border-left-color: #ffc107; }
    
    .progress-thin {
        height: 8px;
        border-radius: 4px;
    }
    
    .quick-action-card {
        background: white;
        border-radius: 15px;
        padding: 25px;
        text-align: center;
        box-shadow: 0 4px 15px rgba(0,0,0,0.08);
        transition: transform 0.2s;
    }
    .quick-action-card:hover {
        transform: translateY(-5px);
    }
    .quick-action-card i {
        font-size: 2.5rem;
        background: linear-gradient(135deg, #667eea, #764ba2);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        margin-bottom: 15px;
    }
    
    .pending-list {
        background: white;
        border-radius: 15px;
        box-shadow: 0 4px 15px rgba(0,0,0,0.08);
        overflow: hidden;
    }
    .pending-item {
        padding: 15px 20px;
        border-bottom: 1px solid #eee;
        display: flex;
        align-items: center;
        justify-content: space-between;
    }
    .pending-item:last-child { border-bottom: none; }
</style>

<div class="content-wrapper">
    <section class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1><i class="fas fa-hand-holding-usd me-2"></i>Loan Management</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-end">
                        <li class="breadcrumb-item"><a href="../../dashboard.php">Home</a></li>
                        <li class="breadcrumb-item active">Loans</li>
                    </ol>
                </div>
            </div>
        </div>
    </section>

    <section class="content">
        <div class="container-fluid">
            
            <?php if ($is_hr): ?>
            <!-- Statistics Cards for HR/Finance -->
            <div class="row mb-4">
                <div class="col-lg-3 col-md-6 mb-3">
                    <div class="stat-card warning">
                        <i class="fas fa-clock"></i>
                        <h3><?php echo $stats['pending_count'] ?? 0; ?></h3>
                        <p>Pending Applications</p>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6 mb-3">
                    <div class="stat-card success">
                        <i class="fas fa-check-circle"></i>
                        <h3><?php echo $stats['active_count'] ?? 0; ?></h3>
                        <p>Active Loans</p>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6 mb-3">
                    <div class="stat-card">
                        <i class="fas fa-money-bill-wave"></i>
                        <h3><?php echo formatCurrency($stats['total_disbursed'] ?? 0); ?></h3>
                        <p>Total Disbursed</p>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6 mb-3">
                    <div class="stat-card danger">
                        <i class="fas fa-balance-scale"></i>
                        <h3><?php echo formatCurrency($stats['total_outstanding'] ?? 0); ?></h3>
                        <p>Outstanding Balance</p>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Quick Actions -->
            <div class="row mb-4">
                <div class="col-md-3 mb-3">
                    <a href="apply.php" class="text-decoration-none">
                        <div class="quick-action-card">
                            <i class="fas fa-plus-circle"></i>
                            <h5>Apply for Loan</h5>
                            <p class="text-muted small mb-0">Submit new loan application</p>
                        </div>
                    </a>
                </div>
                <div class="col-md-3 mb-3">
                    <a href="my-loans.php" class="text-decoration-none">
                        <div class="quick-action-card">
                            <i class="fas fa-list-alt"></i>
                            <h5>My Loans</h5>
                            <p class="text-muted small mb-0">View loan history</p>
                        </div>
                    </a>
                </div>
                <?php if ($is_hr): ?>
                <div class="col-md-3 mb-3">
                    <a href="approvals.php" class="text-decoration-none">
                        <div class="quick-action-card">
                            <i class="fas fa-tasks"></i>
                            <h5>Approvals</h5>
                            <p class="text-muted small mb-0">Review applications</p>
                        </div>
                    </a>
                </div>
                <div class="col-md-3 mb-3">
                    <a href="loan-types.php" class="text-decoration-none">
                        <div class="quick-action-card">
                            <i class="fas fa-cogs"></i>
                            <h5>Loan Types</h5>
                            <p class="text-muted small mb-0">Configure loan products</p>
                        </div>
                    </a>
                </div>
                <?php endif; ?>
            </div>

            <div class="row">
                <!-- My Active Loans -->
                <div class="col-lg-<?php echo $is_hr ? '6' : '12'; ?>">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="fas fa-file-invoice-dollar me-2"></i>My Active Loans</h5>
                        </div>
                        <div class="card-body">
                            <?php if (empty($my_loans)): ?>
                            <div class="text-center py-4">
                                <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                                <p class="text-muted">You have no active loans.</p>
                                <a href="apply.php" class="btn btn-primary">
                                    <i class="fas fa-plus me-2"></i>Apply for Loan
                                </a>
                            </div>
                            <?php else: ?>
                            <?php foreach ($my_loans as $loan): 
                                $paid_percent = $loan['loan_amount'] > 0 ? 
                                    (($loan['loan_amount'] - $loan['total_outstanding']) / $loan['loan_amount']) * 100 : 0;
                            ?>
                            <div class="loan-card active">
                                <div class="d-flex justify-content-between align-items-start mb-2">
                                    <div>
                                        <h6 class="mb-1"><?php echo htmlspecialchars($loan['loan_type_name']); ?></h6>
                                        <small class="text-muted">Ref: <?php echo htmlspecialchars($loan['loan_reference']); ?></small>
                                    </div>
                                    <?php echo getStatusBadge($loan['status']); ?>
                                </div>
                                <div class="row mb-2">
                                    <div class="col-6">
                                        <small class="text-muted d-block">Loan Amount</small>
                                        <strong><?php echo formatCurrency($loan['loan_amount']); ?></strong>
                                    </div>
                                    <div class="col-6">
                                        <small class="text-muted d-block">Outstanding</small>
                                        <strong class="text-danger"><?php echo formatCurrency($loan['total_outstanding']); ?></strong>
                                    </div>
                                </div>
                                <div class="progress progress-thin mb-2">
                                    <div class="progress-bar bg-success" style="width: <?php echo $paid_percent; ?>%"></div>
                                </div>
                                <div class="d-flex justify-content-between">
                                    <small class="text-muted"><?php echo round($paid_percent); ?>% paid</small>
                                    <a href="view.php?id=<?php echo $loan['loan_id']; ?>" class="btn btn-sm btn-outline-primary">
                                        View Details
                                    </a>
                                </div>
                            </div>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <?php if ($is_hr): ?>
                <!-- Pending Approvals -->
                <div class="col-lg-6">
                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="mb-0"><i class="fas fa-clock me-2"></i>Pending Approvals</h5>
                            <a href="approvals.php" class="btn btn-sm btn-outline-primary">View All</a>
                        </div>
                        <div class="card-body p-0">
                            <?php if (empty($pending_loans)): ?>
                            <div class="text-center py-4">
                                <i class="fas fa-check-circle fa-3x text-success mb-3"></i>
                                <p class="text-muted mb-0">No pending applications</p>
                            </div>
                            <?php else: ?>
                            <div class="pending-list">
                                <?php foreach (array_slice($pending_loans, 0, 5) as $loan): ?>
                                <div class="pending-item">
                                    <div>
                                        <strong><?php echo htmlspecialchars($loan['first_name'] . ' ' . $loan['last_name']); ?></strong>
                                        <small class="d-block text-muted">
                                            <?php echo htmlspecialchars($loan['loan_type_name']); ?> - 
                                            <?php echo formatCurrency($loan['loan_amount']); ?>
                                        </small>
                                    </div>
                                    <a href="view.php?id=<?php echo $loan['loan_id']; ?>" class="btn btn-sm btn-primary">
                                        Review
                                    </a>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Loan Types -->
                    <div class="card mt-4">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="fas fa-tags me-2"></i>Available Loan Types</h5>
                        </div>
                        <div class="card-body">
                            <?php if (empty($loan_types)): ?>
                            <p class="text-muted text-center mb-0">No loan types configured.</p>
                            <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-sm mb-0">
                                    <thead>
                                        <tr>
                                            <th>Type</th>
                                            <th>Interest</th>
                                            <th>Max Amount</th>
                                            <th>Max Term</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($loan_types as $lt): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($lt['loan_type_name']); ?></td>
                                            <td><?php echo $lt['interest_rate']; ?>%</td>
                                            <td><?php echo formatCurrency($lt['max_amount']); ?></td>
                                            <td><?php echo $lt['max_term_months']; ?> months</td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>

        </div>
    </section>
</div>

<?php require_once '../../includes/footer.php'; ?>
