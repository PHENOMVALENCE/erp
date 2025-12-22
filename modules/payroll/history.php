<?php
session_start();
require_once '../../config/database.php';
require_once '../../config/auth.php';
require_once '../../includes/functions.php';

defined('APP_ACCESS') or die('Direct access not permitted');

// Require authentication
Auth::requireLogin();

$page_title = "Payroll History";
include '../../includes/header.php';

$company_id = $_SESSION['company_id'];

// Get filter parameters
$filter_status = isset($_GET['status']) ? $_GET['status'] : '';
$filter_employee = isset($_GET['employee']) ? intval($_GET['employee']) : 0;
$filter_year = isset($_GET['year']) ? intval($_GET['year']) : '';

// Build query
$query = "SELECT p.*, COUNT(pd.payroll_detail_id) as employee_count, 
                 SUM(pd.net_pay) as total_net_pay,
                 u.full_name as processed_by
          FROM payroll p
          LEFT JOIN payroll_details pd ON p.payroll_id = pd.payroll_id
          LEFT JOIN users u ON p.created_by = u.user_id
          WHERE p.company_id = ?";

$params = [$company_id];

if ($filter_status) {
    $query .= " AND p.status = ?";
    $params[] = $filter_status;
}

if ($filter_employee) {
    $query .= " AND EXISTS (SELECT 1 FROM payroll_details WHERE payroll_id = p.payroll_id AND employee_id = ?)";
    $params[] = $filter_employee;
}

if ($filter_year) {
    $query .= " AND p.payroll_year = ?";
    $params[] = $filter_year;
}

$query .= " GROUP BY p.payroll_id ORDER BY p.payroll_year DESC, p.payroll_month DESC";

$stmt = $conn->prepare($query);
$types = str_repeat('i', count($params));
if (count($params) > 1) {
    $stmt->bind_param($types, ...$params);
} else {
    $stmt->bind_param($types, $params[0]);
}
$stmt->execute();
$payrolls = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get employees for filter
$emp_query = "SELECT DISTINCT e.employee_id, e.full_name 
              FROM employees e
              JOIN payroll_details pd ON e.employee_id = pd.employee_id
              WHERE e.company_id = ?
              ORDER BY e.full_name";

$emp_stmt = $conn->prepare($emp_query);
$emp_stmt->bind_param('i', $company_id);
$emp_stmt->execute();
$employees = $emp_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get years for filter
$year_query = "SELECT DISTINCT payroll_year FROM payroll WHERE company_id = ? ORDER BY payroll_year DESC";
$year_stmt = $conn->prepare($year_query);
$year_stmt->bind_param('i', $company_id);
$year_stmt->execute();
$years = $year_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>

<div class="container-fluid mt-4">
    <div class="row mb-4">
        <div class="col-md-12">
            <h2><i class="fas fa-history me-2"></i>Payroll History</h2>
        </div>
    </div>

    <!-- Filters -->
    <div class="card mb-4 border-0 shadow-sm">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-3">
                    <select name="status" class="form-select">
                        <option value="">All Status</option>
                        <option value="draft" <?php echo $filter_status == 'draft' ? 'selected' : ''; ?>>Draft</option>
                        <option value="processing" <?php echo $filter_status == 'processing' ? 'selected' : ''; ?>>Processing</option>
                        <option value="completed" <?php echo $filter_status == 'completed' ? 'selected' : ''; ?>>Completed</option>
                        <option value="paid" <?php echo $filter_status == 'paid' ? 'selected' : ''; ?>>Paid</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <select name="employee" class="form-select">
                        <option value="">All Employees</option>
                        <?php foreach ($employees as $emp): ?>
                            <option value="<?php echo $emp['employee_id']; ?>" <?php echo $filter_employee == $emp['employee_id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($emp['full_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <select name="year" class="form-select">
                        <option value="">All Years</option>
                        <?php foreach ($years as $y): ?>
                            <option value="<?php echo $y['payroll_year']; ?>" <?php echo $filter_year == $y['payroll_year'] ? 'selected' : ''; ?>>
                                <?php echo $y['payroll_year']; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fas fa-filter me-2"></i>Filter
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Payroll List -->
    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Month/Year</th>
                        <th>Employees</th>
                        <th>Total Net Pay</th>
                        <th>Status</th>
                        <th>Processed By</th>
                        <th>Date Created</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($payrolls) > 0): ?>
                        <?php foreach ($payrolls as $pr): ?>
                            <tr>
                                <td>
                                    <strong><?php echo date('F Y', mktime(0, 0, 0, $pr['payroll_month'], 1, $pr['payroll_year'])); ?></strong>
                                </td>
                                <td><?php echo $pr['employee_count'] ?? 0; ?></td>
                                <td><?php echo number_format($pr['total_net_pay'] ?? 0, 2); ?> TSH</td>
                                <td>
                                    <span class="badge bg-<?php 
                                        echo $pr['status'] == 'paid' ? 'success' : 
                                             ($pr['status'] == 'completed' ? 'info' : 
                                              ($pr['status'] == 'processing' ? 'warning' : 'secondary'));
                                    ?>">
                                        <?php echo ucfirst($pr['status']); ?>
                                    </span>
                                </td>
                                <td><?php echo htmlspecialchars($pr['processed_by'] ?? 'System'); ?></td>
                                <td><?php echo date('Y-m-d', strtotime($pr['created_at'])); ?></td>
                                <td>
                                    <a href="payslips.php?payroll_id=<?php echo $pr['payroll_id']; ?>" class="btn btn-sm btn-outline-primary">
                                        <i class="fas fa-file-pdf me-1"></i>Payslips
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" class="text-center py-4 text-muted">No payroll records found</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>
