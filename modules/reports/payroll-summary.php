<?php
/**
 * Payroll Summary Report
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
if (!hasPermission($conn, $user_id, ['HR_OFFICER', 'FINANCE_OFFICER', 'COMPANY_ADMIN', 'SUPER_ADMIN'])) {
    $_SESSION['error_message'] = "You don't have permission to view this report.";
    header('Location: index.php');
    exit;
}

// Filter parameters
$month = $_GET['month'] ?? date('Y-m');
$department_filter = $_GET['department'] ?? '';

$year = substr($month, 0, 4);
$month_num = substr($month, 5, 2);

// Get payroll data
$sql = "SELECT p.*, e.first_name, e.last_name, e.employee_number, d.department_name,
               pd.basic_salary, pd.allowances, pd.overtime_pay, pd.bonus, pd.gross_salary,
               pd.tax_amount, pd.nssf_employee, pd.nhif_amount, pd.loan_deduction, 
               pd.other_deductions, pd.total_deductions, pd.net_salary
        FROM payroll p
        JOIN employees e ON p.employee_id = e.employee_id
        LEFT JOIN departments d ON e.department_id = d.department_id
        LEFT JOIN payroll_details pd ON p.payroll_id = pd.payroll_id
        WHERE p.company_id = ? AND YEAR(p.pay_period_start) = ? AND MONTH(p.pay_period_start) = ?";
$params = [$company_id, $year, $month_num];

if ($department_filter) {
    $sql .= " AND e.department_id = ?";
    $params[] = $department_filter;
}

$sql .= " ORDER BY d.department_name, e.first_name";
$stmt = $conn->prepare($sql);
$stmt->execute($params);
$payroll_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate totals
$totals = [
    'basic_salary' => 0, 'allowances' => 0, 'overtime_pay' => 0, 'bonus' => 0, 'gross_salary' => 0,
    'tax_amount' => 0, 'nssf_employee' => 0, 'nhif_amount' => 0, 'loan_deduction' => 0,
    'other_deductions' => 0, 'total_deductions' => 0, 'net_salary' => 0
];
foreach ($payroll_data as $p) {
    foreach ($totals as $key => $val) {
        $totals[$key] += $p[$key] ?? 0;
    }
}

// Get departments for filter
$departments = $conn->query("SELECT department_id, department_name FROM departments WHERE company_id = $company_id ORDER BY department_name")->fetchAll(PDO::FETCH_ASSOC);

$page_title = "Payroll Summary Report";
require_once '../../includes/header.php';
?>

<style>
    .report-header {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        border-radius: 15px;
        padding: 25px;
        margin-bottom: 20px;
    }
    .filter-card {
        background: white;
        border-radius: 15px;
        box-shadow: 0 4px 15px rgba(0,0,0,0.08);
        padding: 20px;
        margin-bottom: 20px;
    }
    .report-table {
        background: white;
        border-radius: 15px;
        box-shadow: 0 4px 15px rgba(0,0,0,0.08);
        overflow: hidden;
    }
    .report-table th {
        background: #f8f9fa;
        font-weight: 600;
        font-size: 0.85rem;
        white-space: nowrap;
    }
    .report-table td { font-size: 0.9rem; }
    .report-table tfoot { background: #343a40; color: white; font-weight: 600; }
    .summary-cards { display: grid; grid-template-columns: repeat(4, 1fr); gap: 15px; margin-bottom: 20px; }
    .summary-card {
        background: white;
        border-radius: 10px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        padding: 15px;
        text-align: center;
    }
    .summary-card h4 { color: #667eea; margin-bottom: 5px; }
    .summary-card.success h4 { color: #28a745; }
    .summary-card.danger h4 { color: #dc3545; }
    @media print {
        .no-print { display: none !important; }
        .report-table { box-shadow: none; }
    }
</style>

<div class="content-wrapper">
    <section class="content-header no-print">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1><i class="fas fa-money-check-alt me-2"></i>Payroll Summary</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-end">
                        <li class="breadcrumb-item"><a href="../../dashboard.php">Home</a></li>
                        <li class="breadcrumb-item"><a href="index.php">Reports</a></li>
                        <li class="breadcrumb-item active">Payroll Summary</li>
                    </ol>
                </div>
            </div>
        </div>
    </section>

    <section class="content">
        <div class="container-fluid">
            
            <!-- Report Header -->
            <div class="report-header">
                <div class="row align-items-center">
                    <div class="col-md-8">
                        <h4 class="mb-1">Payroll Summary Report</h4>
                        <p class="mb-0">
                            Period: <?php echo date('F Y', strtotime($month . '-01')); ?>
                            <?php if ($department_filter): ?>
                            | Department: <?php 
                                foreach ($departments as $d) {
                                    if ($d['department_id'] == $department_filter) echo htmlspecialchars($d['department_name']);
                                }
                            ?>
                            <?php endif; ?>
                        </p>
                    </div>
                    <div class="col-md-4 text-end no-print">
                        <button onclick="window.print()" class="btn btn-light">
                            <i class="fas fa-print me-2"></i>Print
                        </button>
                        <a href="export.php?type=payroll&month=<?php echo $month; ?>" class="btn btn-light">
                            <i class="fas fa-download me-2"></i>Export
                        </a>
                    </div>
                </div>
            </div>

            <!-- Filters -->
            <div class="filter-card no-print">
                <form method="GET" class="row g-3 align-items-end">
                    <div class="col-md-3">
                        <label class="form-label">Month</label>
                        <input type="month" name="month" class="form-control" value="<?php echo $month; ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Department</label>
                        <select name="department" class="form-select">
                            <option value="">All Departments</option>
                            <?php foreach ($departments as $d): ?>
                            <option value="<?php echo $d['department_id']; ?>" <?php echo $department_filter == $d['department_id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($d['department_name']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-filter me-2"></i>Filter
                        </button>
                        <a href="payroll-summary.php" class="btn btn-outline-secondary">Reset</a>
                    </div>
                </form>
            </div>

            <!-- Summary Cards -->
            <div class="summary-cards">
                <div class="summary-card">
                    <h4><?php echo count($payroll_data); ?></h4>
                    <small class="text-muted">Employees</small>
                </div>
                <div class="summary-card success">
                    <h4><?php echo formatCurrency($totals['gross_salary']); ?></h4>
                    <small class="text-muted">Gross Salary</small>
                </div>
                <div class="summary-card danger">
                    <h4><?php echo formatCurrency($totals['total_deductions']); ?></h4>
                    <small class="text-muted">Total Deductions</small>
                </div>
                <div class="summary-card">
                    <h4><?php echo formatCurrency($totals['net_salary']); ?></h4>
                    <small class="text-muted">Net Payable</small>
                </div>
            </div>

            <!-- Payroll Table -->
            <div class="report-table">
                <div class="table-responsive">
                    <table class="table table-sm table-hover mb-0">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Employee</th>
                                <th>Department</th>
                                <th class="text-end">Basic</th>
                                <th class="text-end">Allowances</th>
                                <th class="text-end">Overtime</th>
                                <th class="text-end">Gross</th>
                                <th class="text-end">PAYE</th>
                                <th class="text-end">NSSF</th>
                                <th class="text-end">NHIF</th>
                                <th class="text-end">Loans</th>
                                <th class="text-end">Net Pay</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($payroll_data)): ?>
                            <tr>
                                <td colspan="12" class="text-center py-4">
                                    <p class="text-muted mb-0">No payroll data found for this period.</p>
                                </td>
                            </tr>
                            <?php else: ?>
                            <?php $i = 1; foreach ($payroll_data as $p): ?>
                            <tr>
                                <td><?php echo $i++; ?></td>
                                <td>
                                    <?php echo htmlspecialchars($p['first_name'] . ' ' . $p['last_name']); ?>
                                    <br><small class="text-muted"><?php echo htmlspecialchars($p['employee_number']); ?></small>
                                </td>
                                <td><?php echo htmlspecialchars($p['department_name'] ?? 'N/A'); ?></td>
                                <td class="text-end"><?php echo number_format($p['basic_salary'] ?? 0); ?></td>
                                <td class="text-end"><?php echo number_format($p['allowances'] ?? 0); ?></td>
                                <td class="text-end"><?php echo number_format($p['overtime_pay'] ?? 0); ?></td>
                                <td class="text-end"><strong><?php echo number_format($p['gross_salary'] ?? 0); ?></strong></td>
                                <td class="text-end text-danger"><?php echo number_format($p['tax_amount'] ?? 0); ?></td>
                                <td class="text-end text-danger"><?php echo number_format($p['nssf_employee'] ?? 0); ?></td>
                                <td class="text-end text-danger"><?php echo number_format($p['nhif_amount'] ?? 0); ?></td>
                                <td class="text-end text-danger"><?php echo number_format($p['loan_deduction'] ?? 0); ?></td>
                                <td class="text-end"><strong><?php echo number_format($p['net_salary'] ?? 0); ?></strong></td>
                            </tr>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                        <tfoot>
                            <tr>
                                <td colspan="3"><strong>TOTAL</strong></td>
                                <td class="text-end"><?php echo number_format($totals['basic_salary']); ?></td>
                                <td class="text-end"><?php echo number_format($totals['allowances']); ?></td>
                                <td class="text-end"><?php echo number_format($totals['overtime_pay']); ?></td>
                                <td class="text-end"><?php echo number_format($totals['gross_salary']); ?></td>
                                <td class="text-end"><?php echo number_format($totals['tax_amount']); ?></td>
                                <td class="text-end"><?php echo number_format($totals['nssf_employee']); ?></td>
                                <td class="text-end"><?php echo number_format($totals['nhif_amount']); ?></td>
                                <td class="text-end"><?php echo number_format($totals['loan_deduction']); ?></td>
                                <td class="text-end"><?php echo number_format($totals['net_salary']); ?></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>

            <!-- Statutory Summary -->
            <div class="row mt-4">
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <h6 class="mb-0"><i class="fas fa-landmark me-2"></i>Statutory Deductions Summary</h6>
                        </div>
                        <div class="card-body">
                            <table class="table table-sm mb-0">
                                <tr>
                                    <td>PAYE (Pay As You Earn)</td>
                                    <td class="text-end"><strong><?php echo formatCurrency($totals['tax_amount']); ?></strong></td>
                                </tr>
                                <tr>
                                    <td>NSSF (Employee Contribution)</td>
                                    <td class="text-end"><strong><?php echo formatCurrency($totals['nssf_employee']); ?></strong></td>
                                </tr>
                                <tr>
                                    <td>NHIF</td>
                                    <td class="text-end"><strong><?php echo formatCurrency($totals['nhif_amount']); ?></strong></td>
                                </tr>
                                <tr class="table-dark">
                                    <td>Total Statutory</td>
                                    <td class="text-end"><strong><?php echo formatCurrency($totals['tax_amount'] + $totals['nssf_employee'] + $totals['nhif_amount']); ?></strong></td>
                                </tr>
                            </table>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <h6 class="mb-0"><i class="fas fa-info-circle me-2"></i>Report Information</h6>
                        </div>
                        <div class="card-body">
                            <p class="mb-2"><strong>Generated:</strong> <?php echo date('M d, Y H:i'); ?></p>
                            <p class="mb-2"><strong>Generated By:</strong> <?php echo htmlspecialchars($_SESSION['user_name'] ?? 'System'); ?></p>
                            <p class="mb-0"><strong>Total Records:</strong> <?php echo count($payroll_data); ?> employees</p>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </section>
</div>

<?php require_once '../../includes/footer.php'; ?>
