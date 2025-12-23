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

// Statistics
$stats = ['employees' => 0, 'gross' => 0, 'deductions' => 0, 'net' => 0];
try {
    $stmt = $conn->prepare("SELECT COUNT(*) as c FROM employees WHERE company_id = ? AND employment_status = 'active'");
    $stmt->execute([$company_id]);
    $stats['employees'] = $stmt->fetch()['c'];
    
    $current_month = date('n');
    $current_year = date('Y');
    $stmt = $conn->prepare("SELECT 
        COALESCE(SUM(pd.gross_salary), 0) as gross,
        COALESCE(SUM(pd.total_deductions), 0) as deductions,
        COALESCE(SUM(pd.net_salary), 0) as net
        FROM payroll p
        JOIN payroll_details pd ON p.payroll_id = pd.payroll_id
        WHERE p.company_id = ? AND p.payroll_month = ? AND p.payroll_year = ?");
    $stmt->execute([$company_id, $current_month, $current_year]);
    $row = $stmt->fetch();
    $stats['gross'] = $row['gross'];
    $stats['deductions'] = $row['deductions'];
    $stats['net'] = $row['net'];
} catch (Exception $e) {}

// Recent payroll runs
$recent_payrolls = [];
try {
    $stmt = $conn->prepare("SELECT CONCAT(payroll_year, '-', LPAD(payroll_month, 2, '0')) as period, 
        payroll_month, payroll_year,
        status, created_at as run_date
        FROM payroll WHERE company_id = ?
        GROUP BY payroll_year, payroll_month, status
        ORDER BY payroll_year DESC, payroll_month DESC LIMIT 6");
    $stmt->execute([$company_id]);
    $recent_payrolls = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

$page_title = 'Payroll Management';
require_once '../../includes/header.php';
?>

<style>
.stats-card{background:white;border-radius:12px;padding:1.5rem;box-shadow:0 2px 8px rgba(0,0,0,0.08);border-left:4px solid;transition:transform .2s}
.stats-card:hover{transform:translateY(-4px)}
.stats-card.primary{border-left-color:#007bff}.stats-card.success{border-left-color:#28a745}
.stats-card.danger{border-left-color:#dc3545}.stats-card.info{border-left-color:#17a2b8}
.stats-number{font-size:2rem;font-weight:700;color:#2c3e50}
.stats-label{color:#6c757d;font-size:.875rem;font-weight:500}
.action-card{background:white;border-radius:12px;padding:2rem;text-align:center;box-shadow:0 2px 8px rgba(0,0,0,0.08);transition:all .3s;text-decoration:none;color:inherit;display:block}
.action-card:hover{transform:translateY(-5px);box-shadow:0 8px 25px rgba(0,0,0,0.15)}
.action-card i{font-size:2.5rem;color:#007bff;margin-bottom:1rem}
.table-card{background:white;border-radius:12px;padding:1.5rem;box-shadow:0 2px 8px rgba(0,0,0,0.08)}
.status-badge{padding:.35rem .75rem;border-radius:20px;font-size:.8rem;font-weight:600}
.status-badge.draft{background:#e9ecef;color:#495057}
.status-badge.processing{background:#fff3cd;color:#856404}
.status-badge.approved{background:#cce5ff;color:#004085}
.status-badge.paid{background:#d4edda;color:#155724}
</style>

<div class="content-header mb-4">
    <div class="container-fluid">
        <div class="row align-items-center">
            <div class="col-sm-6"><h1 class="m-0 fw-bold"><i class="fas fa-money-check-alt me-2"></i>Payroll Management</h1></div>
            <div class="col-sm-6 text-end">
                <a href="generate.php" class="btn btn-primary"><i class="fas fa-play-circle me-1"></i> Run Payroll</a>
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
        <div class="col-lg-3 col-6">
            <div class="stats-card primary">
                <div class="stats-number"><?= number_format($stats['employees']) ?></div>
                <div class="stats-label">Active Employees</div>
            </div>
        </div>
        <div class="col-lg-3 col-6">
            <div class="stats-card success">
                <div class="stats-number">TSH <?= number_format($stats['gross']/1000000, 1) ?>M</div>
                <div class="stats-label">Gross Salary (This Month)</div>
            </div>
        </div>
        <div class="col-lg-3 col-6">
            <div class="stats-card danger">
                <div class="stats-number">TSH <?= number_format($stats['deductions']/1000000, 1) ?>M</div>
                <div class="stats-label">Total Deductions</div>
            </div>
        </div>
        <div class="col-lg-3 col-6">
            <div class="stats-card info">
                <div class="stats-number">TSH <?= number_format($stats['net']/1000000, 1) ?>M</div>
                <div class="stats-label">Net Payable</div>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-md-4"><a href="generate.php" class="action-card"><i class="fas fa-play-circle"></i><h5>Run Payroll</h5><p class="text-muted small mb-0">Process monthly payroll</p></a></div>
        <div class="col-md-4"><a href="payslips.php" class="action-card"><i class="fas fa-file-invoice"></i><h5>Payslips</h5><p class="text-muted small mb-0">Generate payslips</p></a></div>
        <div class="col-md-4"><a href="history.php" class="action-card"><i class="fas fa-history"></i><h5>History</h5><p class="text-muted small mb-0">Previous payrolls</p></a></div>
    </div>

    <div class="row">
        <div class="col-lg-8">
            <div class="table-card">
                <h5 class="mb-3"><i class="fas fa-history me-2"></i>Recent Payroll Runs</h5>
                <?php if (empty($recent_payrolls)): ?>
                <p class="text-muted text-center py-4">No payroll runs yet.</p>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead class="table-light"><tr><th>Period</th><th>Employees</th><th>Run Date</th><th>Status</th></tr></thead>
                        <tbody>
                            <?php foreach ($recent_payrolls as $pr): ?>
                            <tr>
                                <td><strong><?= date('F Y', strtotime($pr['payroll_year'] . '-' . $pr['payroll_month'] . '-01')) ?></strong></td>
                                <td>-</td>
                                <td><?= date('M d, Y', strtotime($pr['run_date'])) ?></td>
                                <td><span class="status-badge <?= strtolower($pr['status']) ?>"><?= ucfirst(strtolower($pr['status'])) ?></span></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="col-lg-4">
            <div class="table-card">
                <h5 class="mb-3"><i class="fas fa-info-circle me-2"></i>Payroll Calendar</h5>
                <div class="border rounded p-3 mb-3 bg-light">
                    <h6 class="text-primary mb-2"><?= date('F Y') ?></h6>
                    <p class="mb-1"><strong>Pay Period:</strong> <?= date('M 1') ?> - <?= date('M t') ?></p>
                    <p class="mb-0"><strong>Payment Date:</strong> <?= date('M t') ?> (End of month)</p>
                </div>
                <div class="alert alert-info mb-0">
                    <i class="fas fa-lightbulb me-2"></i>
                    <small>Run payroll before the 25th to allow time for approvals and bank processing.</small>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>
