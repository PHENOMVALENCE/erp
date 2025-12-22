<?php
/**
 * Payslip Generation and Viewing
 * Mkumbi Investments ERP System
 */

define('APP_ACCESS', true);
session_start();
require_once '../../config/database.php';
require_once '../../config/auth.php';
require_once '../../includes/functions.php';

$auth = new Auth();
$auth->requireLogin();

$db = Database::getInstance();
$db->setCompanyId($_SESSION['company_id']);
$conn = $db->getConnection();
$company_id = $_SESSION['company_id'];
$user_id = $_SESSION['user_id'];

$month = isset($_GET['month']) ? (int)$_GET['month'] : date('n');
$year = isset($_GET['year']) ? (int)$_GET['year'] : date('Y');
$employee_id = isset($_GET['employee']) ? (int)$_GET['employee'] : 0;
$action = isset($_GET['action']) ? $_GET['action'] : 'list';

// Fetch company info
$company_sql = "SELECT * FROM companies WHERE company_id = ?";
$stmt = $conn->prepare($company_sql);
$stmt->execute([$company_id]);
$company = $stmt->fetch(PDO::FETCH_ASSOC);

// Get payroll data
$payroll_sql = "SELECT p.*, 
                       pd.*, 
                       e.employee_number, e.bank_name, e.account_number,
                       u.full_name, u.email,
                       d.department_name, pos.position_title
                FROM payroll p
                JOIN payroll_details pd ON p.payroll_id = pd.payroll_id
                JOIN employees e ON pd.employee_id = e.employee_id
                JOIN users u ON e.user_id = u.user_id
                LEFT JOIN departments d ON e.department_id = d.department_id
                LEFT JOIN positions pos ON e.position_id = pos.position_id
                WHERE p.company_id = ? AND p.payroll_month = ? AND p.payroll_year = ?";

$params = [$company_id, $month, $year];

if ($employee_id > 0) {
    $payroll_sql .= " AND pd.employee_id = ?";
    $params[] = $employee_id;
}

$payroll_sql .= " ORDER BY u.full_name";

$stmt = $conn->prepare($payroll_sql);
$stmt->execute($params);
$payslips = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Handle PDF download
if ($action === 'download' && $employee_id > 0 && !empty($payslips)) {
    $payslip = $payslips[0];
    
    // Generate simple HTML for download
    header('Content-Type: text/html; charset=utf-8');
    header('Content-Disposition: attachment; filename="payslip_' . $payslip['employee_number'] . '_' . $month . '_' . $year . '.html"');
    
    echo generatePayslipHTML($payslip, $company, $month, $year);
    exit;
}

function generatePayslipHTML($payslip, $company, $month, $year) {
    $period = date('F Y', mktime(0, 0, 0, $month, 1, $year));
    
    return '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Payslip - ' . htmlspecialchars($payslip['full_name']) . '</title>
    <style>
        body { font-family: Arial, sans-serif; font-size: 12px; margin: 20px; }
        .header { text-align: center; border-bottom: 2px solid #333; padding-bottom: 10px; margin-bottom: 20px; }
        .company-name { font-size: 18px; font-weight: bold; }
        .payslip-title { font-size: 14px; color: #666; margin-top: 5px; }
        .info-section { display: flex; justify-content: space-between; margin-bottom: 20px; }
        .info-box { width: 48%; }
        .info-row { display: flex; justify-content: space-between; padding: 5px 0; border-bottom: 1px dotted #ccc; }
        .earnings-deductions { display: flex; justify-content: space-between; margin-top: 20px; }
        .column { width: 48%; }
        .column-header { background: #333; color: white; padding: 8px; font-weight: bold; }
        .column-row { display: flex; justify-content: space-between; padding: 8px; border-bottom: 1px solid #eee; }
        .total-row { background: #f5f5f5; font-weight: bold; }
        .net-pay { text-align: center; margin-top: 20px; padding: 15px; background: #e8f5e9; border-radius: 5px; }
        .net-amount { font-size: 24px; color: #2e7d32; font-weight: bold; }
        .footer { margin-top: 30px; text-align: center; font-size: 10px; color: #666; }
    </style>
</head>
<body>
    <div class="header">
        <div class="company-name">' . htmlspecialchars($company['company_name']) . '</div>
        <div class="payslip-title">PAYSLIP FOR ' . strtoupper($period) . '</div>
    </div>
    
    <div class="info-section">
        <div class="info-box">
            <div class="info-row"><span>Employee Name:</span><span><strong>' . htmlspecialchars($payslip['full_name']) . '</strong></span></div>
            <div class="info-row"><span>Employee No:</span><span>' . htmlspecialchars($payslip['employee_number']) . '</span></div>
            <div class="info-row"><span>Department:</span><span>' . htmlspecialchars($payslip['department_name'] ?? 'N/A') . '</span></div>
            <div class="info-row"><span>Position:</span><span>' . htmlspecialchars($payslip['position_title'] ?? 'N/A') . '</span></div>
        </div>
        <div class="info-box">
            <div class="info-row"><span>Pay Period:</span><span>' . $period . '</span></div>
            <div class="info-row"><span>Payment Date:</span><span>' . ($payslip['payment_date'] ? date('d/m/Y', strtotime($payslip['payment_date'])) : 'Pending') . '</span></div>
            <div class="info-row"><span>Bank:</span><span>' . htmlspecialchars($payslip['bank_name'] ?? 'N/A') . '</span></div>
            <div class="info-row"><span>Account:</span><span>' . htmlspecialchars($payslip['account_number'] ?? 'N/A') . '</span></div>
        </div>
    </div>
    
    <div class="earnings-deductions">
        <div class="column">
            <div class="column-header">EARNINGS</div>
            <div class="column-row"><span>Basic Salary</span><span>TZS ' . number_format($payslip['basic_salary'], 2) . '</span></div>
            <div class="column-row"><span>Allowances</span><span>TZS ' . number_format($payslip['allowances'], 2) . '</span></div>
            <div class="column-row"><span>Overtime</span><span>TZS ' . number_format($payslip['overtime_pay'], 2) . '</span></div>
            <div class="column-row"><span>Bonus</span><span>TZS ' . number_format($payslip['bonus'], 2) . '</span></div>
            <div class="column-row total-row"><span>GROSS SALARY</span><span>TZS ' . number_format($payslip['gross_salary'], 2) . '</span></div>
        </div>
        <div class="column">
            <div class="column-header">DEDUCTIONS</div>
            <div class="column-row"><span>PAYE Tax</span><span>TZS ' . number_format($payslip['tax_amount'], 2) . '</span></div>
            <div class="column-row"><span>NSSF</span><span>TZS ' . number_format($payslip['nssf_amount'], 2) . '</span></div>
            <div class="column-row"><span>NHIF</span><span>TZS ' . number_format($payslip['nhif_amount'], 2) . '</span></div>
            <div class="column-row"><span>Loan Deduction</span><span>TZS ' . number_format($payslip['loan_deduction'], 2) . '</span></div>
            <div class="column-row"><span>Other Deductions</span><span>TZS ' . number_format($payslip['other_deductions'], 2) . '</span></div>
            <div class="column-row total-row"><span>TOTAL DEDUCTIONS</span><span>TZS ' . number_format($payslip['total_deductions'], 2) . '</span></div>
        </div>
    </div>
    
    <div class="net-pay">
        <div>NET PAY</div>
        <div class="net-amount">TZS ' . number_format($payslip['net_salary'], 2) . '</div>
    </div>
    
    <div class="footer">
        <p>This is a computer-generated payslip and does not require a signature.</p>
        <p>Generated on ' . date('d/m/Y H:i:s') . '</p>
    </div>
</body>
</html>';
}

$page_title = "Payslips";
require_once '../../includes/header.php';
?>

<style>
    .payslip-card {
        background: white;
        border-radius: 15px;
        box-shadow: 0 4px 15px rgba(0,0,0,0.08);
        margin-bottom: 20px;
        overflow: hidden;
    }
    .payslip-header {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        padding: 20px;
    }
    .payslip-body {
        padding: 25px;
    }
    .info-grid {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 15px;
        margin-bottom: 25px;
    }
    .info-item {
        display: flex;
        justify-content: space-between;
        padding: 8px 0;
        border-bottom: 1px dotted #ddd;
    }
    .earnings-section, .deductions-section {
        border-radius: 10px;
        overflow: hidden;
    }
    .earnings-section { background: #e8f5e9; }
    .deductions-section { background: #ffebee; }
    .section-header {
        padding: 12px 15px;
        font-weight: 600;
    }
    .earnings-section .section-header { background: #c8e6c9; color: #2e7d32; }
    .deductions-section .section-header { background: #ffcdd2; color: #c62828; }
    .section-row {
        display: flex;
        justify-content: space-between;
        padding: 10px 15px;
        border-bottom: 1px solid rgba(0,0,0,0.05);
    }
    .section-row:last-child { border-bottom: none; }
    .section-total {
        font-weight: 700;
        background: rgba(0,0,0,0.05);
    }
    .net-pay-box {
        background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
        color: white;
        border-radius: 15px;
        padding: 25px;
        text-align: center;
        margin-top: 20px;
    }
    .net-amount {
        font-size: 2.5rem;
        font-weight: 700;
    }
    .list-view-item {
        padding: 20px;
        border-bottom: 1px solid #eee;
        transition: background 0.2s;
    }
    .list-view-item:hover {
        background: #f8f9fa;
    }
</style>

<div class="content-wrapper">
    <section class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1><i class="fas fa-file-invoice me-2"></i>Payslips</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-end">
                        <li class="breadcrumb-item"><a href="../../dashboard.php">Home</a></li>
                        <li class="breadcrumb-item"><a href="index.php">Payroll</a></li>
                        <li class="breadcrumb-item active">Payslips</li>
                    </ol>
                </div>
            </div>
        </div>
    </section>

    <section class="content">
        <div class="container-fluid">
            
            <!-- Period Selector -->
            <div class="payslip-card">
                <div class="p-3 bg-light">
                    <form method="GET" class="row g-3 align-items-end">
                        <div class="col-md-3">
                            <label class="form-label">Month</label>
                            <select name="month" class="form-select">
                                <?php for ($m = 1; $m <= 12; $m++): ?>
                                <option value="<?php echo $m; ?>" <?php echo $month == $m ? 'selected' : ''; ?>>
                                    <?php echo date('F', mktime(0, 0, 0, $m, 1)); ?>
                                </option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Year</label>
                            <select name="year" class="form-select">
                                <?php for ($y = date('Y'); $y >= date('Y') - 3; $y--): ?>
                                <option value="<?php echo $y; ?>" <?php echo $year == $y ? 'selected' : ''; ?>>
                                    <?php echo $y; ?>
                                </option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="fas fa-search me-2"></i>View
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <?php if (empty($payslips)): ?>
            <div class="payslip-card">
                <div class="text-center py-5">
                    <i class="fas fa-file-invoice fa-4x text-muted mb-3"></i>
                    <h5>No Payslips Available</h5>
                    <p class="text-muted">No payroll has been processed for <?php echo date('F Y', mktime(0, 0, 0, $month, 1, $year)); ?>.</p>
                </div>
            </div>
            <?php elseif ($employee_id > 0): ?>
            
            <!-- Single Payslip View -->
            <?php $ps = $payslips[0]; ?>
            <div class="row">
                <div class="col-lg-8 mx-auto">
                    <div class="payslip-card">
                        <div class="payslip-header">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h4 class="mb-1"><?php echo htmlspecialchars($company['company_name']); ?></h4>
                                    <p class="mb-0 opacity-75">Payslip for <?php echo date('F Y', mktime(0, 0, 0, $month, 1, $year)); ?></p>
                                </div>
                                <div>
                                    <a href="?month=<?php echo $month; ?>&year=<?php echo $year; ?>&employee=<?php echo $employee_id; ?>&action=download" 
                                       class="btn btn-light btn-sm">
                                        <i class="fas fa-download me-2"></i>Download
                                    </a>
                                    <a href="?month=<?php echo $month; ?>&year=<?php echo $year; ?>" class="btn btn-outline-light btn-sm ms-2">
                                        <i class="fas fa-arrow-left me-2"></i>Back
                                    </a>
                                </div>
                            </div>
                        </div>
                        
                        <div class="payslip-body">
                            <div class="info-grid">
                                <div>
                                    <div class="info-item">
                                        <span class="text-muted">Employee Name</span>
                                        <strong><?php echo htmlspecialchars($ps['full_name']); ?></strong>
                                    </div>
                                    <div class="info-item">
                                        <span class="text-muted">Employee No</span>
                                        <span><?php echo htmlspecialchars($ps['employee_number']); ?></span>
                                    </div>
                                    <div class="info-item">
                                        <span class="text-muted">Department</span>
                                        <span><?php echo htmlspecialchars($ps['department_name'] ?? 'N/A'); ?></span>
                                    </div>
                                </div>
                                <div>
                                    <div class="info-item">
                                        <span class="text-muted">Position</span>
                                        <span><?php echo htmlspecialchars($ps['position_title'] ?? 'N/A'); ?></span>
                                    </div>
                                    <div class="info-item">
                                        <span class="text-muted">Bank</span>
                                        <span><?php echo htmlspecialchars($ps['bank_name'] ?? 'N/A'); ?></span>
                                    </div>
                                    <div class="info-item">
                                        <span class="text-muted">Account</span>
                                        <span><?php echo htmlspecialchars($ps['account_number'] ?? 'N/A'); ?></span>
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <div class="earnings-section">
                                        <div class="section-header">EARNINGS</div>
                                        <div class="section-row">
                                            <span>Basic Salary</span>
                                            <span><?php echo formatCurrency($ps['basic_salary']); ?></span>
                                        </div>
                                        <div class="section-row">
                                            <span>Allowances</span>
                                            <span><?php echo formatCurrency($ps['allowances']); ?></span>
                                        </div>
                                        <div class="section-row">
                                            <span>Overtime</span>
                                            <span><?php echo formatCurrency($ps['overtime_pay']); ?></span>
                                        </div>
                                        <div class="section-row">
                                            <span>Bonus</span>
                                            <span><?php echo formatCurrency($ps['bonus']); ?></span>
                                        </div>
                                        <div class="section-row section-total">
                                            <span>GROSS SALARY</span>
                                            <span><?php echo formatCurrency($ps['gross_salary']); ?></span>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <div class="deductions-section">
                                        <div class="section-header">DEDUCTIONS</div>
                                        <div class="section-row">
                                            <span>PAYE Tax</span>
                                            <span><?php echo formatCurrency($ps['tax_amount']); ?></span>
                                        </div>
                                        <div class="section-row">
                                            <span>NSSF</span>
                                            <span><?php echo formatCurrency($ps['nssf_amount']); ?></span>
                                        </div>
                                        <div class="section-row">
                                            <span>NHIF</span>
                                            <span><?php echo formatCurrency($ps['nhif_amount']); ?></span>
                                        </div>
                                        <div class="section-row">
                                            <span>Loan Deduction</span>
                                            <span><?php echo formatCurrency($ps['loan_deduction']); ?></span>
                                        </div>
                                        <div class="section-row">
                                            <span>Other Deductions</span>
                                            <span><?php echo formatCurrency($ps['other_deductions']); ?></span>
                                        </div>
                                        <div class="section-row section-total">
                                            <span>TOTAL DEDUCTIONS</span>
                                            <span><?php echo formatCurrency($ps['total_deductions']); ?></span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="net-pay-box">
                                <div class="mb-2">NET PAY</div>
                                <div class="net-amount"><?php echo formatCurrency($ps['net_salary']); ?></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <?php else: ?>
            
            <!-- List View -->
            <div class="payslip-card">
                <div class="p-3 bg-light border-bottom">
                    <h5 class="mb-0">
                        <i class="fas fa-users me-2"></i>
                        Payslips for <?php echo date('F Y', mktime(0, 0, 0, $month, 1, $year)); ?>
                        <span class="badge bg-primary"><?php echo count($payslips); ?> employees</span>
                    </h5>
                </div>
                
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="bg-light">
                            <tr>
                                <th>Employee</th>
                                <th>Department</th>
                                <th class="text-end">Gross</th>
                                <th class="text-end">Deductions</th>
                                <th class="text-end">Net Pay</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($payslips as $ps): ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars($ps['full_name']); ?></strong>
                                    <br>
                                    <small class="text-muted"><?php echo htmlspecialchars($ps['employee_number']); ?></small>
                                </td>
                                <td><?php echo htmlspecialchars($ps['department_name'] ?? 'N/A'); ?></td>
                                <td class="text-end"><?php echo formatCurrency($ps['gross_salary']); ?></td>
                                <td class="text-end text-danger"><?php echo formatCurrency($ps['total_deductions']); ?></td>
                                <td class="text-end text-success fw-bold"><?php echo formatCurrency($ps['net_salary']); ?></td>
                                <td><?php echo getStatusBadge($ps['payment_status']); ?></td>
                                <td>
                                    <a href="?month=<?php echo $month; ?>&year=<?php echo $year; ?>&employee=<?php echo $ps['employee_id']; ?>" 
                                       class="btn btn-sm btn-outline-info" title="View Payslip">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <a href="?month=<?php echo $month; ?>&year=<?php echo $year; ?>&employee=<?php echo $ps['employee_id']; ?>&action=download" 
                                       class="btn btn-sm btn-outline-success" title="Download">
                                        <i class="fas fa-download"></i>
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot class="bg-light">
                            <tr>
                                <th colspan="2">TOTALS</th>
                                <th class="text-end"><?php echo formatCurrency(array_sum(array_column($payslips, 'gross_salary'))); ?></th>
                                <th class="text-end text-danger"><?php echo formatCurrency(array_sum(array_column($payslips, 'total_deductions'))); ?></th>
                                <th class="text-end text-success"><?php echo formatCurrency(array_sum(array_column($payslips, 'net_salary'))); ?></th>
                                <th colspan="2"></th>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
            
            <?php endif; ?>

        </div>
    </section>
</div>

<?php require_once '../../includes/footer.php'; ?>
