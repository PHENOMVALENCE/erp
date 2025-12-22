<?php
session_start();
require_once '../../config/database.php';
require_once '../../config/auth.php';
require_once '../../includes/functions.php';

defined('APP_ACCESS') or die('Direct access not permitted');

// Require authentication
Auth::requireLogin();

$page_title = "Leave Balance";
include '../../includes/header.php';

$company_id = $_SESSION['company_id'];
$user_id = $_SESSION['user_id'];

// Get current fiscal/leave year
$settings_query = "SELECT setting_value FROM system_settings WHERE company_id = ? AND setting_key = 'leave_year_start'";
$settings_stmt = $conn->prepare($settings_query);
$settings_stmt->bind_param('i', $company_id);
$settings_stmt->execute();
$settings_result = $settings_stmt->get_result();
$settings = $settings_result->fetch_assoc();

$leave_year_start = isset($settings['setting_value']) ? intval($settings['setting_value']) : 1;
$current_month = date('n');
$current_year = date('Y');

// Calculate fiscal year
if ($current_month >= $leave_year_start) {
    $fiscal_year = $current_year;
} else {
    $fiscal_year = $current_year - 1;
}

// Get filter parameters
$filter_employee = isset($_GET['employee']) ? intval($_GET['employee']) : 0;
$filter_department = isset($_GET['department']) ? intval($_GET['department']) : 0;

// Get leave balances
$query = "SELECT lb.*, lt.leave_type_name, lt.days_per_year, e.full_name, e.employee_number, d.department_name
          FROM leave_balances lb
          JOIN leave_types lt ON lb.leave_type_id = lt.leave_type_id
          JOIN employees e ON lb.employee_id = e.employee_id
          LEFT JOIN departments d ON e.department_id = d.department_id
          WHERE lb.company_id = ? AND lb.fiscal_year = ?";

$params = [$company_id, $fiscal_year];

if ($filter_employee) {
    $query .= " AND lb.employee_id = ?";
    $params[] = $filter_employee;
}

if ($filter_department) {
    $query .= " AND e.department_id = ?";
    $params[] = $filter_department;
}

$query .= " ORDER BY e.full_name, lt.leave_type_name";

$stmt = $conn->prepare($query);
if (count($params) == 2) {
    $stmt->bind_param('ii', $params[0], $params[1]);
} else {
    $types = 'ii' . str_repeat('i', count($params) - 2);
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$balances = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get employees for filter
$emp_query = "SELECT employee_id, full_name, employee_number FROM employees WHERE company_id = ? AND is_active = 1 ORDER BY full_name";
$emp_stmt = $conn->prepare($emp_query);
$emp_stmt->bind_param('i', $company_id);
$emp_stmt->execute();
$employees = $emp_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get departments for filter
$dept_query = "SELECT department_id, department_name FROM departments WHERE company_id = ? AND is_active = 1 ORDER BY department_name";
$dept_stmt = $conn->prepare($dept_query);
$dept_stmt->bind_param('i', $company_id);
$dept_stmt->execute();
$departments = $dept_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>

<div class="container-fluid mt-4">
    <div class="row mb-4">
        <div class="col-md-12">
            <h2><i class="fas fa-chart-bar me-2"></i>Leave Balance Report</h2>
            <p class="text-muted">Fiscal Year: <?php echo $fiscal_year; ?></p>
        </div>
    </div>

    <!-- Filters -->
    <div class="card mb-4 border-0 shadow-sm">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">Employee</label>
                    <select name="employee" class="form-select">
                        <option value="">All Employees</option>
                        <?php foreach ($employees as $emp): ?>
                            <option value="<?php echo $emp['employee_id']; ?>" <?php echo $filter_employee == $emp['employee_id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($emp['full_name'] . ' (' . $emp['employee_number'] . ')'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Department</label>
                    <select name="department" class="form-select">
                        <option value="">All Departments</option>
                        <?php foreach ($departments as $dept): ?>
                            <option value="<?php echo $dept['department_id']; ?>" <?php echo $filter_department == $dept['department_id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($dept['department_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fas fa-filter me-2"></i>Filter
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Balance Table -->
    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Employee</th>
                        <th>Department</th>
                        <th>Leave Type</th>
                        <th>Entitled Days</th>
                        <th>Used Days</th>
                        <th>Carried Forward</th>
                        <th>Balance</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($balances) > 0): ?>
                        <?php 
                        $last_employee = '';
                        foreach ($balances as $balance): 
                        ?>
                            <tr>
                                <td>
                                    <?php 
                                    if ($balance['full_name'] != $last_employee) {
                                        echo htmlspecialchars($balance['full_name']);
                                        $last_employee = $balance['full_name'];
                                    }
                                    ?>
                                </td>
                                <td><?php echo htmlspecialchars($balance['department_name'] ?? 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($balance['leave_type_name']); ?></td>
                                <td class="text-center"><?php echo number_format($balance['entitled_days'] ?? 0, 1); ?></td>
                                <td class="text-center"><?php echo number_format($balance['used_days'] ?? 0, 1); ?></td>
                                <td class="text-center"><?php echo number_format($balance['carried_forward'] ?? 0, 1); ?></td>
                                <td class="text-center">
                                    <strong><?php echo number_format($balance['balance'] ?? 0, 1); ?> days</strong>
                                </td>
                                <td>
                                    <?php 
                                    $remaining = floatval($balance['balance'] ?? 0);
                                    if ($remaining > 0) {
                                        $status = 'success';
                                        $text = 'Available';
                                    } elseif ($remaining == 0) {
                                        $status = 'warning';
                                        $text = 'Exhausted';
                                    } else {
                                        $status = 'danger';
                                        $text = 'Overdrawn';
                                    }
                                    ?>
                                    <span class="badge bg-<?php echo $status; ?>"><?php echo $text; ?></span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="8" class="text-center py-4 text-muted">No leave balance records found</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Export Options -->
    <div class="row mt-4">
        <div class="col-md-12">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <button class="btn btn-outline-primary" onclick="window.print()">
                        <i class="fas fa-print me-2"></i>Print Report
                    </button>
                    <button class="btn btn-outline-success">
                        <i class="fas fa-download me-2"></i>Export Excel
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>
