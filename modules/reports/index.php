<?php
/**
 * Reports & Analytics Dashboard
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

// Check permissions for different report types
$can_view_finance = hasPermission($conn, $user_id, ['FINANCE_OFFICER', 'ACCOUNTANT', 'COMPANY_ADMIN', 'SUPER_ADMIN']);
$can_view_hr = hasPermission($conn, $user_id, ['HR_OFFICER', 'COMPANY_ADMIN', 'SUPER_ADMIN']);
$can_view_sales = hasPermission($conn, $user_id, ['SALES_MANAGER', 'COMPANY_ADMIN', 'SUPER_ADMIN']);

// Quick stats for dashboard widgets
$stats = [];

// Employee count
$sql = "SELECT COUNT(*) as count FROM employees WHERE company_id = ? AND is_active = 1";
$stmt = $conn->prepare($sql);
$stmt->execute([$company_id]);
$stats['employees'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

// Active loans
$sql = "SELECT COUNT(*) as count, SUM(total_outstanding) as outstanding 
        FROM employee_loans WHERE company_id = ? AND status IN ('ACTIVE', 'DISBURSED')";
$stmt = $conn->prepare($sql);
$stmt->execute([$company_id]);
$loan_stats = $stmt->fetch(PDO::FETCH_ASSOC);
$stats['active_loans'] = $loan_stats['count'];
$stats['loan_outstanding'] = $loan_stats['outstanding'] ?? 0;

// Leave statistics this month
$sql = "SELECT COUNT(*) as count FROM leave_applications la
        JOIN employees e ON la.employee_id = e.employee_id
        WHERE e.company_id = ? AND MONTH(la.start_date) = MONTH(CURDATE()) AND YEAR(la.start_date) = YEAR(CURDATE())";
$stmt = $conn->prepare($sql);
$stmt->execute([$company_id]);
$stats['leave_requests'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

// Asset value
$sql = "SELECT SUM(current_value) as value FROM assets WHERE company_id = ? AND status = 'ACTIVE'";
$stmt = $conn->prepare($sql);
$stmt->execute([$company_id]);
$stats['asset_value'] = $stmt->fetch(PDO::FETCH_ASSOC)['value'] ?? 0;

$page_title = "Reports & Analytics";
require_once '../../includes/header.php';
?>

<style>
    .report-category {
        background: white;
        border-radius: 15px;
        box-shadow: 0 4px 15px rgba(0,0,0,0.08);
        padding: 25px;
        margin-bottom: 25px;
    }
    .report-category h5 {
        color: #667eea;
        margin-bottom: 20px;
        padding-bottom: 10px;
        border-bottom: 2px solid #f0f0f0;
    }
    .report-card {
        background: #f8f9fa;
        border-radius: 10px;
        padding: 20px;
        text-align: center;
        transition: all 0.3s;
        height: 100%;
        text-decoration: none;
        color: inherit;
        display: block;
    }
    .report-card:hover {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        transform: translateY(-5px);
    }
    .report-card:hover i { color: white !important; }
    .report-card i {
        font-size: 2.5rem;
        color: #667eea;
        margin-bottom: 15px;
    }
    .report-card h6 { margin-bottom: 5px; }
    .report-card small { opacity: 0.7; }
    
    .stat-widget {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        border-radius: 15px;
        color: white;
        padding: 20px;
        margin-bottom: 20px;
    }
    .stat-widget.success { background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%); }
    .stat-widget.warning { background: linear-gradient(135deg, #F2994A 0%, #F2C94C 100%); }
    .stat-widget.info { background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); }
    .stat-widget h3 { margin-bottom: 5px; }
    
    .quick-report {
        background: white;
        border-radius: 15px;
        box-shadow: 0 4px 15px rgba(0,0,0,0.08);
        padding: 20px;
    }
</style>

<div class="content-wrapper">
    <section class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1><i class="fas fa-chart-bar me-2"></i>Reports & Analytics</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-end">
                        <li class="breadcrumb-item"><a href="../../dashboard.php">Home</a></li>
                        <li class="breadcrumb-item active">Reports</li>
                    </ol>
                </div>
            </div>
        </div>
    </section>

    <section class="content">
        <div class="container-fluid">
            
            <!-- Quick Stats -->
            <div class="row mb-4">
                <div class="col-lg-3 col-md-6 mb-3">
                    <div class="stat-widget">
                        <h3><?php echo number_format($stats['employees']); ?></h3>
                        <p class="mb-0">Active Employees</p>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6 mb-3">
                    <div class="stat-widget success">
                        <h3><?php echo formatCurrency($stats['asset_value']); ?></h3>
                        <p class="mb-0">Asset Value</p>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6 mb-3">
                    <div class="stat-widget warning">
                        <h3><?php echo $stats['active_loans']; ?></h3>
                        <p class="mb-0">Active Loans</p>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6 mb-3">
                    <div class="stat-widget info">
                        <h3><?php echo $stats['leave_requests']; ?></h3>
                        <p class="mb-0">Leave Requests (This Month)</p>
                    </div>
                </div>
            </div>

            <?php if ($can_view_finance): ?>
            <!-- Financial Reports -->
            <div class="report-category">
                <h5><i class="fas fa-coins me-2"></i>Financial Reports</h5>
                <div class="row">
                    <div class="col-lg-3 col-md-4 col-6 mb-3">
                        <a href="income-statement.php" class="report-card">
                            <i class="fas fa-file-invoice-dollar"></i>
                            <h6>Income Statement</h6>
                            <small>Profit & Loss</small>
                        </a>
                    </div>
                    <div class="col-lg-3 col-md-4 col-6 mb-3">
                        <a href="balance-sheet.php" class="report-card">
                            <i class="fas fa-balance-scale"></i>
                            <h6>Balance Sheet</h6>
                            <small>Assets & Liabilities</small>
                        </a>
                    </div>
                    <div class="col-lg-3 col-md-4 col-6 mb-3">
                        <a href="cash-flow.php" class="report-card">
                            <i class="fas fa-money-bill-wave"></i>
                            <h6>Cash Flow</h6>
                            <small>Cash Movements</small>
                        </a>
                    </div>
                    <div class="col-lg-3 col-md-4 col-6 mb-3">
                        <a href="trial-balance.php" class="report-card">
                            <i class="fas fa-calculator"></i>
                            <h6>Trial Balance</h6>
                            <small>Account Balances</small>
                        </a>
                    </div>
                    <div class="col-lg-3 col-md-4 col-6 mb-3">
                        <a href="general-ledger.php" class="report-card">
                            <i class="fas fa-book"></i>
                            <h6>General Ledger</h6>
                            <small>All Transactions</small>
                        </a>
                    </div>
                    <div class="col-lg-3 col-md-4 col-6 mb-3">
                        <a href="expense-report.php" class="report-card">
                            <i class="fas fa-receipt"></i>
                            <h6>Expense Report</h6>
                            <small>By Category</small>
                        </a>
                    </div>
                    <div class="col-lg-3 col-md-4 col-6 mb-3">
                        <a href="tax-report.php" class="report-card">
                            <i class="fas fa-percentage"></i>
                            <h6>Tax Report</h6>
                            <small>VAT & Withholding</small>
                        </a>
                    </div>
                    <div class="col-lg-3 col-md-4 col-6 mb-3">
                        <a href="budget-variance.php" class="report-card">
                            <i class="fas fa-chart-pie"></i>
                            <h6>Budget Variance</h6>
                            <small>Actual vs Budget</small>
                        </a>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <?php if ($can_view_hr): ?>
            <!-- HR Reports -->
            <div class="report-category">
                <h5><i class="fas fa-users me-2"></i>Human Resources Reports</h5>
                <div class="row">
                    <div class="col-lg-3 col-md-4 col-6 mb-3">
                        <a href="payroll-summary.php" class="report-card">
                            <i class="fas fa-money-check-alt"></i>
                            <h6>Payroll Summary</h6>
                            <small>Monthly Payroll</small>
                        </a>
                    </div>
                    <div class="col-lg-3 col-md-4 col-6 mb-3">
                        <a href="leave-report.php" class="report-card">
                            <i class="fas fa-calendar-times"></i>
                            <h6>Leave Report</h6>
                            <small>Leave Analysis</small>
                        </a>
                    </div>
                    <div class="col-lg-3 col-md-4 col-6 mb-3">
                        <a href="employee-report.php" class="report-card">
                            <i class="fas fa-id-card"></i>
                            <h6>Employee Report</h6>
                            <small>Staff Directory</small>
                        </a>
                    </div>
                    <div class="col-lg-3 col-md-4 col-6 mb-3">
                        <a href="loan-report.php" class="report-card">
                            <i class="fas fa-hand-holding-usd"></i>
                            <h6>Loan Report</h6>
                            <small>Employee Loans</small>
                        </a>
                    </div>
                    <div class="col-lg-3 col-md-4 col-6 mb-3">
                        <a href="statutory-report.php" class="report-card">
                            <i class="fas fa-landmark"></i>
                            <h6>Statutory Reports</h6>
                            <small>NSSF, NHIF, PAYE</small>
                        </a>
                    </div>
                    <div class="col-lg-3 col-md-4 col-6 mb-3">
                        <a href="department-report.php" class="report-card">
                            <i class="fas fa-sitemap"></i>
                            <h6>Department Report</h6>
                            <small>By Department</small>
                        </a>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Asset Reports -->
            <div class="report-category">
                <h5><i class="fas fa-warehouse me-2"></i>Asset Reports</h5>
                <div class="row">
                    <div class="col-lg-3 col-md-4 col-6 mb-3">
                        <a href="asset-register.php" class="report-card">
                            <i class="fas fa-list-alt"></i>
                            <h6>Asset Register</h6>
                            <small>Complete List</small>
                        </a>
                    </div>
                    <div class="col-lg-3 col-md-4 col-6 mb-3">
                        <a href="depreciation-report.php" class="report-card">
                            <i class="fas fa-chart-line"></i>
                            <h6>Depreciation Report</h6>
                            <small>Monthly/Annual</small>
                        </a>
                    </div>
                    <div class="col-lg-3 col-md-4 col-6 mb-3">
                        <a href="asset-valuation.php" class="report-card">
                            <i class="fas fa-dollar-sign"></i>
                            <h6>Asset Valuation</h6>
                            <small>Current Values</small>
                        </a>
                    </div>
                    <div class="col-lg-3 col-md-4 col-6 mb-3">
                        <a href="maintenance-report.php" class="report-card">
                            <i class="fas fa-tools"></i>
                            <h6>Maintenance Report</h6>
                            <small>Service History</small>
                        </a>
                    </div>
                </div>
            </div>

            <?php if ($can_view_finance): ?>
            <!-- Petty Cash Reports -->
            <div class="report-category">
                <h5><i class="fas fa-wallet me-2"></i>Petty Cash Reports</h5>
                <div class="row">
                    <div class="col-lg-3 col-md-4 col-6 mb-3">
                        <a href="petty-cash-summary.php" class="report-card">
                            <i class="fas fa-file-alt"></i>
                            <h6>Petty Cash Summary</h6>
                            <small>Account Summary</small>
                        </a>
                    </div>
                    <div class="col-lg-3 col-md-4 col-6 mb-3">
                        <a href="petty-cash-transactions.php" class="report-card">
                            <i class="fas fa-exchange-alt"></i>
                            <h6>Transaction History</h6>
                            <small>All Transactions</small>
                        </a>
                    </div>
                    <div class="col-lg-3 col-md-4 col-6 mb-3">
                        <a href="petty-cash-by-category.php" class="report-card">
                            <i class="fas fa-tags"></i>
                            <h6>By Category</h6>
                            <small>Expense Breakdown</small>
                        </a>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Quick Report Generator -->
            <div class="quick-report">
                <h5><i class="fas fa-magic me-2"></i>Quick Report Generator</h5>
                <form method="GET" action="generate.php" class="row g-3 mt-2">
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
                        <input type="date" name="date_from" class="form-control" 
                               value="<?php echo date('Y-m-01'); ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Date To</label>
                        <input type="date" name="date_to" class="form-control" 
                               value="<?php echo date('Y-m-d'); ?>">
                    </div>
                    <div class="col-md-3 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-file-export me-2"></i>Generate Report
                        </button>
                    </div>
                </form>
            </div>

        </div>
    </section>
</div>

<?php require_once '../../includes/footer.php'; ?>
