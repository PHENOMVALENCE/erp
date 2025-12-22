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

// Quick stats
$stats = ['employees' => 0, 'assets' => 0, 'loans' => 0, 'leave_requests' => 0];
try {
    $stmt = $conn->prepare("SELECT COUNT(*) as c FROM employees WHERE company_id = ? AND is_active = 1");
    $stmt->execute([$company_id]);
    $stats['employees'] = $stmt->fetch()['c'];
} catch (Exception $e) {}

try {
    $stmt = $conn->prepare("SELECT COALESCE(SUM(current_value), 0) as v FROM assets WHERE company_id = ? AND status = 'ACTIVE'");
    $stmt->execute([$company_id]);
    $stats['assets'] = $stmt->fetch()['v'];
} catch (Exception $e) {}

try {
    $stmt = $conn->prepare("SELECT COUNT(*) as c FROM employee_loans WHERE company_id = ? AND status IN ('ACTIVE', 'DISBURSED')");
    $stmt->execute([$company_id]);
    $stats['loans'] = $stmt->fetch()['c'];
} catch (Exception $e) {}

try {
    $stmt = $conn->prepare("SELECT COUNT(*) as c FROM leave_applications WHERE company_id = ? AND MONTH(created_at) = MONTH(CURDATE())");
    $stmt->execute([$company_id]);
    $stats['leave_requests'] = $stmt->fetch()['c'];
} catch (Exception $e) {}

$page_title = 'Reports & Analytics';
require_once '../../includes/header.php';
?>

<style>
.stats-card{background:white;border-radius:12px;padding:1.5rem;box-shadow:0 2px 8px rgba(0,0,0,0.08);border-left:4px solid;transition:transform .2s}
.stats-card:hover{transform:translateY(-4px)}
.stats-card.primary{border-left-color:#007bff}.stats-card.success{border-left-color:#28a745}
.stats-card.warning{border-left-color:#ffc107}.stats-card.info{border-left-color:#17a2b8}
.stats-number{font-size:2rem;font-weight:700;color:#2c3e50}
.stats-label{color:#6c757d;font-size:.875rem;font-weight:500}
.report-category{background:white;border-radius:12px;padding:1.5rem;box-shadow:0 2px 8px rgba(0,0,0,0.08);margin-bottom:1.5rem}
.report-category h5{color:#007bff;margin-bottom:1rem;padding-bottom:.5rem;border-bottom:2px solid #f0f0f0}
.report-card{background:#f8f9fa;border-radius:10px;padding:1.5rem;text-align:center;transition:all .3s;text-decoration:none;color:inherit;display:block;height:100%}
.report-card:hover{background:linear-gradient(135deg,#007bff,#6f42c1);color:white;transform:translateY(-5px)}
.report-card:hover i{color:white!important}
.report-card i{font-size:2rem;color:#007bff;margin-bottom:.75rem}
</style>

<div class="content-header mb-4">
    <div class="container-fluid">
        <div class="row align-items-center">
            <div class="col-sm-6"><h1 class="m-0 fw-bold"><i class="fas fa-chart-bar me-2"></i>Reports & Analytics</h1></div>
        </div>
    </div>
</div>

<div class="container-fluid">
    <!-- Quick Stats -->
    <div class="row g-3 mb-4">
        <div class="col-lg-3 col-6">
            <div class="stats-card primary">
                <div class="stats-number"><?= number_format($stats['employees']) ?></div>
                <div class="stats-label">Active Employees</div>
            </div>
        </div>
        <div class="col-lg-3 col-6">
            <div class="stats-card success">
                <div class="stats-number">TSH <?= number_format($stats['assets']/1000000, 1) ?>M</div>
                <div class="stats-label">Asset Value</div>
            </div>
        </div>
        <div class="col-lg-3 col-6">
            <div class="stats-card warning">
                <div class="stats-number"><?= $stats['loans'] ?></div>
                <div class="stats-label">Active Loans</div>
            </div>
        </div>
        <div class="col-lg-3 col-6">
            <div class="stats-card info">
                <div class="stats-number"><?= $stats['leave_requests'] ?></div>
                <div class="stats-label">Leave Requests (Month)</div>
            </div>
        </div>
    </div>

    <!-- HR Reports -->
    <div class="report-category">
        <h5><i class="fas fa-users me-2"></i>Human Resources Reports</h5>
        <div class="row g-3">
            <div class="col-lg-2 col-md-4 col-6"><a href="payroll-summary.php" class="report-card"><i class="fas fa-money-check-alt"></i><h6>Payroll Summary</h6><small>Monthly Payroll</small></a></div>
            <div class="col-lg-2 col-md-4 col-6"><a href="leave-report.php" class="report-card"><i class="fas fa-calendar-times"></i><h6>Leave Report</h6><small>Leave Analysis</small></a></div>
            <div class="col-lg-2 col-md-4 col-6"><a href="employee-report.php" class="report-card"><i class="fas fa-id-card"></i><h6>Employee Report</h6><small>Staff Directory</small></a></div>
            <div class="col-lg-2 col-md-4 col-6"><a href="loan-report.php" class="report-card"><i class="fas fa-hand-holding-usd"></i><h6>Loan Report</h6><small>Employee Loans</small></a></div>
            <div class="col-lg-2 col-md-4 col-6"><a href="statutory-report.php" class="report-card"><i class="fas fa-landmark"></i><h6>Statutory Reports</h6><small>NSSF, NHIF, PAYE</small></a></div>
            <div class="col-lg-2 col-md-4 col-6"><a href="department-report.php" class="report-card"><i class="fas fa-sitemap"></i><h6>Department Report</h6><small>By Department</small></a></div>
        </div>
    </div>

    <!-- Asset Reports -->
    <div class="report-category">
        <h5><i class="fas fa-warehouse me-2"></i>Asset Reports</h5>
        <div class="row g-3">
            <div class="col-lg-2 col-md-4 col-6"><a href="asset-register.php" class="report-card"><i class="fas fa-list-alt"></i><h6>Asset Register</h6><small>Complete List</small></a></div>
            <div class="col-lg-2 col-md-4 col-6"><a href="depreciation-report.php" class="report-card"><i class="fas fa-chart-line"></i><h6>Depreciation Report</h6><small>Monthly/Annual</small></a></div>
            <div class="col-lg-2 col-md-4 col-6"><a href="asset-valuation.php" class="report-card"><i class="fas fa-dollar-sign"></i><h6>Asset Valuation</h6><small>Current Values</small></a></div>
            <div class="col-lg-2 col-md-4 col-6"><a href="maintenance-report.php" class="report-card"><i class="fas fa-tools"></i><h6>Maintenance Report</h6><small>Service History</small></a></div>
        </div>
    </div>

    <!-- Financial Reports -->
    <div class="report-category">
        <h5><i class="fas fa-coins me-2"></i>Financial Reports</h5>
        <div class="row g-3">
            <div class="col-lg-2 col-md-4 col-6"><a href="petty-cash-summary.php" class="report-card"><i class="fas fa-wallet"></i><h6>Petty Cash</h6><small>Account Summary</small></a></div>
            <div class="col-lg-2 col-md-4 col-6"><a href="expense-report.php" class="report-card"><i class="fas fa-receipt"></i><h6>Expense Report</h6><small>By Category</small></a></div>
            <div class="col-lg-2 col-md-4 col-6"><a href="tax-report.php" class="report-card"><i class="fas fa-percentage"></i><h6>Tax Report</h6><small>VAT & Withholding</small></a></div>
        </div>
    </div>

    <!-- Quick Report Generator -->
    <div class="report-category">
        <h5><i class="fas fa-magic me-2"></i>Quick Report Generator</h5>
        <form method="GET" action="generate.php" class="row g-3">
            <div class="col-md-3">
                <label class="form-label">Report Type</label>
                <select name="type" class="form-select" required>
                    <option value="">-- Select --</option>
                    <option value="payroll">Payroll Summary</option>
                    <option value="leave">Leave Report</option>
                    <option value="loans">Loan Report</option>
                    <option value="assets">Asset Report</option>
                    <option value="petty_cash">Petty Cash</option>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Date From</label>
                <input type="date" name="date_from" class="form-control" value="<?= date('Y-m-01') ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label">Date To</label>
                <input type="date" name="date_to" class="form-control" value="<?= date('Y-m-d') ?>">
            </div>
            <div class="col-md-3 d-flex align-items-end">
                <button type="submit" class="btn btn-primary"><i class="fas fa-file-export me-2"></i>Generate Report</button>
            </div>
        </form>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>
