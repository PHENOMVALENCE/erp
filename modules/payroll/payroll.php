<?php
session_start();
require_once '../../config/database.php';
require_once '../../config/auth.php';
require_once '../../includes/functions.php';

defined('APP_ACCESS') or die('Direct access not permitted');

// Require authentication
Auth::requireLogin();

$page_title = "Payroll Processing";
include '../../includes/header.php';

$company_id = $_SESSION['company_id'];
$user_id = $_SESSION['user_id'];

// Get current month and year
$current_month = isset($_GET['month']) ? intval($_GET['month']) : date('n');
$current_year = isset($_GET['year']) ? intval($_GET['year']) : date('Y');

// Get or create payroll for the month
$payroll_query = "SELECT p.*, COUNT(pd.payroll_detail_id) as employee_count 
                  FROM payroll p
                  LEFT JOIN payroll_details pd ON p.payroll_id = pd.payroll_id
                  WHERE p.company_id = ? AND p.payroll_month = ? AND p.payroll_year = ?
                  GROUP BY p.payroll_id";

$payroll_stmt = $conn->prepare($payroll_query);
$payroll_stmt->bind_param('iii', $company_id, $current_month, $current_year);
$payroll_stmt->execute();
$payroll_result = $payroll_stmt->get_result();
$payroll = $payroll_result->fetch_assoc();

// Get active employees
$emp_query = "SELECT e.*, d.department_name 
              FROM employees e
              LEFT JOIN departments d ON e.department_id = d.department_id
              WHERE e.company_id = ? AND e.is_active = 1 
              ORDER BY e.full_name";

$emp_stmt = $conn->prepare($emp_query);
$emp_stmt->bind_param('i', $company_id);
$emp_stmt->execute();
$employees = $emp_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get system settings for payroll
$settings_query = "SELECT setting_key, setting_value FROM system_settings 
                   WHERE company_id = ? AND setting_key IN ('nssf_employee_rate', 'nhif_rate', 'paye_threshold', 'wcf_rate', 'sdl_rate')";

$settings_stmt = $conn->prepare($settings_query);
$settings_stmt->bind_param('i', $company_id);
$settings_stmt->execute();
$settings_result = $settings_stmt->get_result();
$settings = [];
while ($row = $settings_result->fetch_assoc()) {
    $settings[$row['setting_key']] = $row['setting_value'];
}

// Handle payroll generation
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] == 'generate') {
        // Create payroll record if not exists
        if (!$payroll) {
            $insert_query = "INSERT INTO payroll (company_id, payroll_month, payroll_year, status, created_by, created_at) 
                            VALUES (?, ?, ?, 'draft', ?, NOW())";
            $insert_stmt = $conn->prepare($insert_query);
            $insert_stmt->bind_param('iiii', $company_id, $current_month, $current_year, $user_id);
            $insert_stmt->execute();
            $payroll_id = $insert_stmt->insert_id;
        } else {
            $payroll_id = $payroll['payroll_id'];
        }
        
        // Generate payroll details for each active employee
        foreach ($employees as $emp) {
            $basic_salary = floatval($emp['basic_salary'] ?? 0);
            
            if ($basic_salary > 0) {
                // Get loan deductions
                $loan_query = "SELECT SUM(monthly_deduction) as loan_deduction 
                              FROM employee_loans 
                              WHERE employee_id = ? AND status IN ('disbursed', 'completed')";
                $loan_stmt = $conn->prepare($loan_query);
                $loan_stmt->bind_param('i', $emp['employee_id']);
                $loan_stmt->execute();
                $loan_result = $loan_stmt->get_result()->fetch_assoc();
                $loan_deduction = floatval($loan_result['loan_deduction'] ?? 0);
                
                // Calculate deductions
                $nssf_rate = floatval($settings['nssf_employee_rate'] ?? 10) / 100;
                $nhif_rate = floatval($settings['nhif_rate'] ?? 3) / 100;
                $wcf_rate = floatval($settings['wcf_rate'] ?? 1) / 100;
                $sdl_rate = floatval($settings['sdl_rate'] ?? 4.5) / 100;
                $paye_threshold = floatval($settings['paye_threshold'] ?? 270000);
                
                $gross_pay = $basic_salary;
                $nssf = $gross_pay * $nssf_rate;
                $nhif = $gross_pay * $nhif_rate;
                $wcf = $gross_pay * $wcf_rate;
                $sdl = $gross_pay * $sdl_rate;
                
                $taxable_income = $gross_pay - $nssf - $paye_threshold;
                $paye = $taxable_income > 0 ? $taxable_income * 0.09 : 0; // 9% PAYE
                
                $total_deductions = $nssf + $nhif + $wcf + $sdl + $paye + $loan_deduction;
                $net_pay = $gross_pay - $total_deductions;
                
                // Insert or update payroll detail
                $detail_check = "SELECT payroll_detail_id FROM payroll_details WHERE payroll_id = ? AND employee_id = ?";
                $detail_check_stmt = $conn->prepare($detail_check);
                $detail_check_stmt->bind_param('ii', $payroll_id, $emp['employee_id']);
                $detail_check_stmt->execute();
                $detail_exists = $detail_check_stmt->get_result()->fetch_assoc();
                
                if (!$detail_exists) {
                    $detail_insert = "INSERT INTO payroll_details 
                                     (payroll_id, company_id, employee_id, basic_salary, gross_pay, paye, nhif, wcf_amount, sdl_amount, loan_deduction, total_deductions, net_pay, created_at)
                                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
                    $detail_stmt = $conn->prepare($detail_insert);
                    $detail_stmt->bind_param('iiddddddddd', 
                        $payroll_id, $company_id, $emp['employee_id'], 
                        $basic_salary, $gross_pay, $paye, $nhif, $wcf, $sdl, 
                        $loan_deduction, $total_deductions, $net_pay);
                    $detail_stmt->execute();
                }
            }
        }
        
        logActivity($conn, $company_id, $user_id, 'GENERATED', 'payroll', 'payroll', $payroll_id);
        echo '<div class="alert alert-success">Payroll generated successfully for ' . count($employees) . ' employees.</div>';
    } elseif ($_POST['action'] == 'approve') {
        $payroll_id = intval($_POST['payroll_id']);
        $update_query = "UPDATE payroll SET status = 'completed', approved_by = ?, approved_at = NOW() WHERE payroll_id = ? AND company_id = ?";
        $update_stmt = $conn->prepare($update_query);
        $update_stmt->bind_param('iii', $user_id, $payroll_id, $company_id);
        $update_stmt->execute();
        
        logActivity($conn, $company_id, $user_id, 'APPROVED', 'payroll', 'payroll', $payroll_id);
        echo '<div class="alert alert-success">Payroll approved successfully.</div>';
    }
}

// Get payroll details if exists
$details = [];
if ($payroll) {
    $details_query = "SELECT pd.*, e.full_name, e.employee_number 
                     FROM payroll_details pd
                     JOIN employees e ON pd.employee_id = e.employee_id
                     WHERE pd.payroll_id = ?
                     ORDER BY e.full_name";
    $details_stmt = $conn->prepare($details_query);
    $details_stmt->bind_param('i', $payroll['payroll_id']);
    $details_stmt->execute();
    $details = $details_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}
?>

<div class="container-fluid mt-4">
    <div class="row mb-4">
        <div class="col-md-12">
            <h2><i class="fas fa-calculator me-2"></i>Payroll Processing</h2>
        </div>
    </div>

    <!-- Month/Year Selector -->
    <div class="card mb-4 border-0 shadow-sm">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">Month</label>
                    <select name="month" class="form-select" onchange="this.form.submit()">
                        <?php for ($m = 1; $m <= 12; $m++): ?>
                            <option value="<?php echo $m; ?>" <?php echo $current_month == $m ? 'selected' : ''; ?>>
                                <?php echo date('F', mktime(0, 0, 0, $m, 1)); ?>
                            </option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Year</label>
                    <select name="year" class="form-select" onchange="this.form.submit()">
                        <?php for ($y = date('Y') - 2; $y <= date('Y') + 1; $y++): ?>
                            <option value="<?php echo $y; ?>" <?php echo $current_year == $y ? 'selected' : ''; ?>>
                                <?php echo $y; ?>
                            </option>
                        <?php endfor; ?>
                    </select>
                </div>
            </form>
        </div>
    </div>

    <!-- Payroll Status -->
    <?php if ($payroll): ?>
    <div class="card mb-4 border-0 shadow-sm">
        <div class="card-body">
            <h5>Payroll Status: <span class="badge bg-<?php echo $payroll['status'] == 'paid' ? 'success' : ($payroll['status'] == 'completed' ? 'info' : 'warning'); ?>">
                <?php echo ucfirst($payroll['status']); ?>
            </span></h5>
            <p>Employees Processed: <?php echo $payroll['employee_count']; ?></p>
            <?php if ($payroll['status'] == 'draft'): ?>
                <form method="POST" class="d-inline">
                    <input type="hidden" name="action" value="approve">
                    <input type="hidden" name="payroll_id" value="<?php echo $payroll['payroll_id']; ?>">
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-check me-2"></i>Approve Payroll
                    </button>
                </form>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Generate Button -->
    <?php if (!$payroll || $payroll['employee_count'] == 0): ?>
    <div class="card mb-4 border-0 shadow-sm">
        <div class="card-body">
            <form method="POST">
                <input type="hidden" name="action" value="generate">
                <button type="submit" class="btn btn-primary" onclick="return confirm('Generate payroll for all active employees?')">
                    <i class="fas fa-cogs me-2"></i>Generate Payroll
                </button>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <!-- Payroll Details Table -->
    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table table-hover mb-0 small">
                <thead class="table-light">
                    <tr>
                        <th>Employee</th>
                        <th>Employee #</th>
                        <th>Basic</th>
                        <th>NSSF</th>
                        <th>NHIF</th>
                        <th>PAYE</th>
                        <th>Loan</th>
                        <th>Deductions</th>
                        <th>Net Pay</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($details) > 0): ?>
                        <?php 
                        $totals = ['basic' => 0, 'nssf' => 0, 'nhif' => 0, 'paye' => 0, 'loan' => 0, 'deductions' => 0, 'net' => 0];
                        foreach ($details as $detail): 
                            $totals['basic'] += $detail['basic_salary'];
                            $totals['nssf'] += floatval($detail['basic_salary'] ?? 0) * 0.10;
                            $totals['nhif'] += $detail['nhif'] ?? 0;
                            $totals['paye'] += $detail['paye'] ?? 0;
                            $totals['loan'] += $detail['loan_deduction'] ?? 0;
                            $totals['deductions'] += $detail['total_deductions'] ?? 0;
                            $totals['net'] += $detail['net_pay'] ?? 0;
                        ?>
                            <tr>
                                <td><?php echo htmlspecialchars($detail['full_name']); ?></td>
                                <td><?php echo htmlspecialchars($detail['employee_number']); ?></td>
                                <td><?php echo number_format($detail['basic_salary'] ?? 0, 2); ?></td>
                                <td><?php echo number_format(floatval($detail['basic_salary'] ?? 0) * 0.10, 2); ?></td>
                                <td><?php echo number_format($detail['nhif'] ?? 0, 2); ?></td>
                                <td><?php echo number_format($detail['paye'] ?? 0, 2); ?></td>
                                <td><?php echo number_format($detail['loan_deduction'] ?? 0, 2); ?></td>
                                <td><?php echo number_format($detail['total_deductions'] ?? 0, 2); ?></td>
                                <td><strong><?php echo number_format($detail['net_pay'] ?? 0, 2); ?></strong></td>
                            </tr>
                        <?php endforeach; ?>
                        <tr class="table-light fw-bold">
                            <td colspan="2">TOTAL</td>
                            <td><?php echo number_format($totals['basic'], 2); ?></td>
                            <td><?php echo number_format($totals['nssf'], 2); ?></td>
                            <td><?php echo number_format($totals['nhif'], 2); ?></td>
                            <td><?php echo number_format($totals['paye'], 2); ?></td>
                            <td><?php echo number_format($totals['loan'], 2); ?></td>
                            <td><?php echo number_format($totals['deductions'], 2); ?></td>
                            <td><?php echo number_format($totals['net'], 2); ?></td>
                        </tr>
                    <?php else: ?>
                        <tr>
                            <td colspan="9" class="text-center py-4 text-muted">No payroll data for this period</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>
