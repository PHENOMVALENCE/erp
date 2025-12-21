<?php
/**
 * Payroll Generation
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

if (!hasPermission($conn, $user_id, ['ACCOUNTANT', 'HR_OFFICER', 'FINANCE_OFFICER', 'COMPANY_ADMIN', 'SUPER_ADMIN'])) {
    $_SESSION['error_message'] = "Permission denied.";
    header('Location: index.php');
    exit;
}

$month = isset($_GET['month']) ? (int)$_GET['month'] : date('n');
$year = isset($_GET['year']) ? (int)$_GET['year'] : date('Y');

// Check if payroll already exists
$check_sql = "SELECT payroll_id FROM payroll WHERE company_id = ? AND payroll_month = ? AND payroll_year = ?";
$stmt = $conn->prepare($check_sql);
$stmt->execute([$company_id, $month, $year]);
if ($stmt->fetch()) {
    $_SESSION['error_message'] = "Payroll for this period already exists.";
    header("Location: index.php?month=$month&year=$year");
    exit;
}

$errors = [];
$success = '';

// Fetch active employees
$employees_sql = "SELECT 
    e.employee_id, e.employee_number, e.basic_salary, e.allowances,
    u.full_name, u.email, u.phone1,
    d.department_name, p.position_title,
    e.bank_name, e.account_number
FROM employees e
JOIN users u ON e.user_id = u.user_id
LEFT JOIN departments d ON e.department_id = d.department_id
LEFT JOIN positions p ON e.position_id = p.position_id
WHERE e.company_id = ? AND e.employment_status = 'active' AND e.is_active = 1
ORDER BY u.full_name";

$stmt = $conn->prepare($employees_sql);
$stmt->execute([$company_id]);
$employees = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch active employee loans for deductions
$loans_sql = "SELECT employee_id, SUM(monthly_deduction) as total_loan_deduction
              FROM employee_loans 
              WHERE company_id = ? AND status IN ('active', 'disbursed')
              GROUP BY employee_id";
$stmt = $conn->prepare($loans_sql);
$stmt->execute([$company_id]);
$loan_deductions = [];
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $loan_deductions[$row['employee_id']] = $row['total_loan_deduction'];
}

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $selected_employees = $_POST['employees'] ?? [];
    
    if (empty($selected_employees)) {
        $errors[] = "Please select at least one employee.";
    }
    
    if (empty($errors)) {
        try {
            $conn->beginTransaction();
            
            // Create payroll header
            $payroll_sql = "INSERT INTO payroll (company_id, payroll_month, payroll_year, status, created_by)
                            VALUES (?, ?, ?, 'draft', ?)";
            $stmt = $conn->prepare($payroll_sql);
            $stmt->execute([$company_id, $month, $year, $user_id]);
            $payroll_id = $conn->lastInsertId();
            
            // Insert payroll details
            $detail_sql = "INSERT INTO payroll_details 
                (payroll_id, employee_id, basic_salary, allowances, overtime_pay, bonus, 
                 tax_amount, nssf_amount, nhif_amount, loan_deduction, other_deductions)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($detail_sql);
            
            foreach ($selected_employees as $emp_id) {
                $emp_id = (int)$emp_id;
                $basic = floatval($_POST["basic_$emp_id"] ?? 0);
                $allowances = floatval($_POST["allowances_$emp_id"] ?? 0);
                $overtime = floatval($_POST["overtime_$emp_id"] ?? 0);
                $bonus = floatval($_POST["bonus_$emp_id"] ?? 0);
                
                $gross = $basic + $allowances + $overtime + $bonus;
                
                // Calculate statutory deductions
                $paye = calculatePAYE($gross);
                $nssf = calculateNSSF($gross);
                $nhif = calculateNHIF($gross);
                $loan = floatval($_POST["loan_$emp_id"] ?? 0);
                $other = floatval($_POST["other_$emp_id"] ?? 0);
                
                $stmt->execute([
                    $payroll_id, $emp_id, $basic, $allowances, $overtime, $bonus,
                    $paye, $nssf, $nhif, $loan, $other
                ]);
            }
            
            // Log audit
            logAudit($conn, $company_id, $user_id, 'create', 'payroll', 'payroll', $payroll_id, null, [
                'month' => $month,
                'year' => $year,
                'employees' => count($selected_employees)
            ]);
            
            $conn->commit();
            
            $_SESSION['success_message'] = "Payroll generated successfully for " . count($selected_employees) . " employees.";
            header("Location: index.php?month=$month&year=$year");
            exit;
            
        } catch (PDOException $e) {
            $conn->rollBack();
            error_log("Payroll generation error: " . $e->getMessage());
            $errors[] = "An error occurred. Please try again.";
        }
    }
}

$page_title = "Generate Payroll";
require_once '../../includes/header.php';
?>

<style>
    .generate-card {
        background: white;
        border-radius: 15px;
        box-shadow: 0 4px 15px rgba(0,0,0,0.08);
        overflow: hidden;
    }
    .period-header {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        padding: 25px;
    }
    .employee-row {
        padding: 20px;
        border-bottom: 1px solid #eee;
        transition: background 0.2s;
    }
    .employee-row:hover {
        background: #f8f9fa;
    }
    .employee-row.selected {
        background: #e3f2fd;
    }
    .salary-input {
        width: 100%;
        text-align: right;
    }
    .calculated-field {
        background: #e9ecef;
        font-weight: 600;
    }
    .summary-box {
        background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
        color: white;
        border-radius: 10px;
        padding: 20px;
        position: sticky;
        top: 80px;
    }
    .summary-item {
        display: flex;
        justify-content: space-between;
        padding: 10px 0;
        border-bottom: 1px solid rgba(255,255,255,0.2);
    }
    .summary-item:last-child {
        border-bottom: none;
    }
    .check-all-box {
        background: #f8f9fa;
        padding: 15px;
        border-bottom: 1px solid #dee2e6;
    }
</style>

<div class="content-wrapper">
    <section class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1><i class="fas fa-calculator me-2"></i>Generate Payroll</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-end">
                        <li class="breadcrumb-item"><a href="../../dashboard.php">Home</a></li>
                        <li class="breadcrumb-item"><a href="index.php">Payroll</a></li>
                        <li class="breadcrumb-item active">Generate</li>
                    </ol>
                </div>
            </div>
        </div>
    </section>

    <section class="content">
        <div class="container-fluid">
            
            <?php if (!empty($errors)): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <ul class="mb-0">
                    <?php foreach ($errors as $error): ?>
                    <li><?php echo htmlspecialchars($error); ?></li>
                    <?php endforeach; ?>
                </ul>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>

            <form method="POST" id="payrollForm">
                <div class="row">
                    <div class="col-lg-9">
                        <div class="generate-card">
                            <div class="period-header">
                                <h4 class="mb-0">
                                    <i class="fas fa-calendar me-2"></i>
                                    <?php echo date('F Y', mktime(0, 0, 0, $month, 1, $year)); ?> Payroll
                                </h4>
                                <p class="mb-0 mt-2 opacity-75"><?php echo count($employees); ?> active employees</p>
                            </div>

                            <?php if (empty($employees)): ?>
                            <div class="text-center py-5">
                                <i class="fas fa-users-slash fa-3x text-muted mb-3"></i>
                                <h5>No Active Employees</h5>
                                <p class="text-muted">There are no active employees to process payroll for.</p>
                            </div>
                            <?php else: ?>
                            
                            <div class="check-all-box">
                                <div class="form-check">
                                    <input type="checkbox" class="form-check-input" id="selectAll" checked>
                                    <label class="form-check-label fw-bold" for="selectAll">Select All Employees</label>
                                </div>
                            </div>

                            <?php foreach ($employees as $emp): 
                                $loan_amount = $loan_deductions[$emp['employee_id']] ?? 0;
                                $gross = ($emp['basic_salary'] ?? 0) + ($emp['allowances'] ?? 0);
                                $paye = calculatePAYE($gross);
                                $nssf = calculateNSSF($gross);
                                $nhif = calculateNHIF($gross);
                            ?>
                            <div class="employee-row" id="row_<?php echo $emp['employee_id']; ?>">
                                <div class="row align-items-start">
                                    <div class="col-md-3">
                                        <div class="form-check">
                                            <input type="checkbox" class="form-check-input employee-check" 
                                                   name="employees[]" value="<?php echo $emp['employee_id']; ?>" 
                                                   id="emp_<?php echo $emp['employee_id']; ?>" checked
                                                   onchange="toggleEmployee(<?php echo $emp['employee_id']; ?>)">
                                            <label class="form-check-label" for="emp_<?php echo $emp['employee_id']; ?>">
                                                <strong><?php echo htmlspecialchars($emp['full_name']); ?></strong>
                                                <br>
                                                <small class="text-muted">
                                                    <?php echo htmlspecialchars($emp['employee_number']); ?> • 
                                                    <?php echo htmlspecialchars($emp['department_name'] ?? 'N/A'); ?>
                                                </small>
                                            </label>
                                        </div>
                                    </div>
                                    <div class="col-md-9">
                                        <div class="row g-2">
                                            <div class="col-md-2">
                                                <label class="form-label small">Basic Salary</label>
                                                <input type="number" step="0.01" class="form-control form-control-sm salary-input"
                                                       name="basic_<?php echo $emp['employee_id']; ?>"
                                                       id="basic_<?php echo $emp['employee_id']; ?>"
                                                       value="<?php echo $emp['basic_salary'] ?? 0; ?>"
                                                       onchange="calculateRow(<?php echo $emp['employee_id']; ?>)">
                                            </div>
                                            <div class="col-md-2">
                                                <label class="form-label small">Allowances</label>
                                                <input type="number" step="0.01" class="form-control form-control-sm salary-input"
                                                       name="allowances_<?php echo $emp['employee_id']; ?>"
                                                       id="allowances_<?php echo $emp['employee_id']; ?>"
                                                       value="<?php echo $emp['allowances'] ?? 0; ?>"
                                                       onchange="calculateRow(<?php echo $emp['employee_id']; ?>)">
                                            </div>
                                            <div class="col-md-2">
                                                <label class="form-label small">Overtime</label>
                                                <input type="number" step="0.01" class="form-control form-control-sm salary-input"
                                                       name="overtime_<?php echo $emp['employee_id']; ?>"
                                                       id="overtime_<?php echo $emp['employee_id']; ?>"
                                                       value="0"
                                                       onchange="calculateRow(<?php echo $emp['employee_id']; ?>)">
                                            </div>
                                            <div class="col-md-2">
                                                <label class="form-label small">Bonus</label>
                                                <input type="number" step="0.01" class="form-control form-control-sm salary-input"
                                                       name="bonus_<?php echo $emp['employee_id']; ?>"
                                                       id="bonus_<?php echo $emp['employee_id']; ?>"
                                                       value="0"
                                                       onchange="calculateRow(<?php echo $emp['employee_id']; ?>)">
                                            </div>
                                            <div class="col-md-2">
                                                <label class="form-label small">Gross</label>
                                                <input type="text" class="form-control form-control-sm calculated-field text-success"
                                                       id="gross_<?php echo $emp['employee_id']; ?>"
                                                       value="<?php echo number_format($gross, 2); ?>" readonly>
                                            </div>
                                            <div class="col-md-2">
                                                <label class="form-label small">PAYE</label>
                                                <input type="text" class="form-control form-control-sm calculated-field"
                                                       id="paye_<?php echo $emp['employee_id']; ?>"
                                                       value="<?php echo number_format($paye, 2); ?>" readonly>
                                            </div>
                                            <div class="col-md-2">
                                                <label class="form-label small">NSSF</label>
                                                <input type="text" class="form-control form-control-sm calculated-field"
                                                       id="nssf_<?php echo $emp['employee_id']; ?>"
                                                       value="<?php echo number_format($nssf, 2); ?>" readonly>
                                            </div>
                                            <div class="col-md-2">
                                                <label class="form-label small">NHIF</label>
                                                <input type="text" class="form-control form-control-sm calculated-field"
                                                       id="nhif_<?php echo $emp['employee_id']; ?>"
                                                       value="<?php echo number_format($nhif, 2); ?>" readonly>
                                            </div>
                                            <div class="col-md-2">
                                                <label class="form-label small">Loans</label>
                                                <input type="number" step="0.01" class="form-control form-control-sm salary-input"
                                                       name="loan_<?php echo $emp['employee_id']; ?>"
                                                       id="loan_<?php echo $emp['employee_id']; ?>"
                                                       value="<?php echo $loan_amount; ?>"
                                                       onchange="calculateRow(<?php echo $emp['employee_id']; ?>)">
                                            </div>
                                            <div class="col-md-2">
                                                <label class="form-label small">Other Ded.</label>
                                                <input type="number" step="0.01" class="form-control form-control-sm salary-input"
                                                       name="other_<?php echo $emp['employee_id']; ?>"
                                                       id="other_<?php echo $emp['employee_id']; ?>"
                                                       value="0"
                                                       onchange="calculateRow(<?php echo $emp['employee_id']; ?>)">
                                            </div>
                                            <div class="col-md-2">
                                                <label class="form-label small fw-bold">Net Pay</label>
                                                <input type="text" class="form-control form-control-sm calculated-field text-primary fw-bold"
                                                       id="net_<?php echo $emp['employee_id']; ?>"
                                                       value="<?php echo number_format($gross - $paye - $nssf - $nhif - $loan_amount, 2); ?>" readonly>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="col-lg-3">
                        <div class="summary-box">
                            <h5 class="mb-3"><i class="fas fa-calculator me-2"></i>Summary</h5>
                            <div class="summary-item">
                                <span>Employees</span>
                                <span id="summaryCount"><?php echo count($employees); ?></span>
                            </div>
                            <div class="summary-item">
                                <span>Total Gross</span>
                                <span id="summaryGross">TZS 0</span>
                            </div>
                            <div class="summary-item">
                                <span>Total Deductions</span>
                                <span id="summaryDeductions">TZS 0</span>
                            </div>
                            <div class="summary-item">
                                <span><strong>Total Net</strong></span>
                                <span id="summaryNet"><strong>TZS 0</strong></span>
                            </div>
                            
                            <div class="mt-4">
                                <button type="submit" class="btn btn-light btn-lg w-100">
                                    <i class="fas fa-save me-2"></i>Generate Payroll
                                </button>
                            </div>
                            <div class="mt-2">
                                <a href="index.php" class="btn btn-outline-light w-100">
                                    <i class="fas fa-times me-2"></i>Cancel
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </form>

        </div>
    </section>
</div>

<script>
// Tanzania tax calculation functions
function calculatePAYE(gross) {
    if (gross <= 270000) return 0;
    if (gross <= 520000) return (gross - 270000) * 0.08;
    if (gross <= 760000) return 20000 + (gross - 520000) * 0.20;
    if (gross <= 1000000) return 68000 + (gross - 760000) * 0.25;
    return 128000 + (gross - 1000000) * 0.30;
}

function calculateNSSF(gross) {
    const cap = 2000000;
    const rate = 0.10;
    return Math.min(gross, cap) * rate;
}

function calculateNHIF(gross) {
    if (gross <= 150000) return 5000;
    if (gross <= 250000) return 7500;
    if (gross <= 350000) return 10000;
    if (gross <= 450000) return 12500;
    if (gross <= 600000) return 15000;
    if (gross <= 800000) return 20000;
    if (gross <= 1000000) return 25000;
    if (gross <= 1500000) return 30000;
    return 40000;
}

function calculateRow(empId) {
    const basic = parseFloat(document.getElementById('basic_' + empId).value) || 0;
    const allowances = parseFloat(document.getElementById('allowances_' + empId).value) || 0;
    const overtime = parseFloat(document.getElementById('overtime_' + empId).value) || 0;
    const bonus = parseFloat(document.getElementById('bonus_' + empId).value) || 0;
    
    const gross = basic + allowances + overtime + bonus;
    
    const paye = calculatePAYE(gross);
    const nssf = calculateNSSF(gross);
    const nhif = calculateNHIF(gross);
    const loan = parseFloat(document.getElementById('loan_' + empId).value) || 0;
    const other = parseFloat(document.getElementById('other_' + empId).value) || 0;
    
    const totalDeductions = paye + nssf + nhif + loan + other;
    const net = gross - totalDeductions;
    
    document.getElementById('gross_' + empId).value = gross.toFixed(2);
    document.getElementById('paye_' + empId).value = paye.toFixed(2);
    document.getElementById('nssf_' + empId).value = nssf.toFixed(2);
    document.getElementById('nhif_' + empId).value = nhif.toFixed(2);
    document.getElementById('net_' + empId).value = net.toFixed(2);
    
    updateSummary();
}

function toggleEmployee(empId) {
    const checkbox = document.getElementById('emp_' + empId);
    const row = document.getElementById('row_' + empId);
    row.classList.toggle('selected', checkbox.checked);
    updateSummary();
}

function updateSummary() {
    let count = 0, totalGross = 0, totalDeductions = 0, totalNet = 0;
    
    document.querySelectorAll('.employee-check:checked').forEach(cb => {
        const empId = cb.value;
        count++;
        
        const gross = parseFloat(document.getElementById('gross_' + empId).value.replace(/,/g, '')) || 0;
        const paye = parseFloat(document.getElementById('paye_' + empId).value.replace(/,/g, '')) || 0;
        const nssf = parseFloat(document.getElementById('nssf_' + empId).value.replace(/,/g, '')) || 0;
        const nhif = parseFloat(document.getElementById('nhif_' + empId).value.replace(/,/g, '')) || 0;
        const loan = parseFloat(document.getElementById('loan_' + empId).value) || 0;
        const other = parseFloat(document.getElementById('other_' + empId).value) || 0;
        const net = parseFloat(document.getElementById('net_' + empId).value.replace(/,/g, '')) || 0;
        
        totalGross += gross;
        totalDeductions += paye + nssf + nhif + loan + other;
        totalNet += net;
    });
    
    document.getElementById('summaryCount').textContent = count;
    document.getElementById('summaryGross').textContent = 'TZS ' + totalGross.toLocaleString();
    document.getElementById('summaryDeductions').textContent = 'TZS ' + totalDeductions.toLocaleString();
    document.getElementById('summaryNet').innerHTML = '<strong>TZS ' + totalNet.toLocaleString() + '</strong>';
}

// Select all functionality
document.getElementById('selectAll').addEventListener('change', function() {
    document.querySelectorAll('.employee-check').forEach(cb => {
        cb.checked = this.checked;
        toggleEmployee(cb.value);
    });
});

// Initialize
document.addEventListener('DOMContentLoaded', function() {
    updateSummary();
});
</script>

<?php require_once '../../includes/footer.php'; ?>
