<?php
/**
 * Leave Management Module - Main Dashboard
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

// Get employee info
$employee = getEmployeeByUserId($conn, $user_id);
$is_manager = hasPermission($conn, $user_id, ['MANAGER', 'HR_OFFICER', 'COMPANY_ADMIN', 'SUPER_ADMIN']);

// Get leave statistics
try {
    // My leave summary (for employees)
    if ($employee) {
        $my_leave_sql = "SELECT 
            lt.leave_type_name,
            lt.days_per_year,
            COALESCE(SUM(CASE WHEN la.status = 'approved' AND YEAR(la.start_date) = YEAR(CURDATE()) THEN la.total_days ELSE 0 END), 0) as days_taken,
            (lt.days_per_year - COALESCE(SUM(CASE WHEN la.status = 'approved' AND YEAR(la.start_date) = YEAR(CURDATE()) THEN la.total_days ELSE 0 END), 0)) as days_remaining
        FROM leave_types lt
        LEFT JOIN leave_applications la ON lt.leave_type_id = la.leave_type_id AND la.employee_id = ?
        WHERE lt.company_id = ? AND lt.is_active = 1
        GROUP BY lt.leave_type_id, lt.leave_type_name, lt.days_per_year";
        $stmt = $conn->prepare($my_leave_sql);
        $stmt->execute([$employee['employee_id'], $company_id]);
        $my_leave_balance = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // Recent applications (my own)
    $recent_sql = "SELECT la.*, lt.leave_type_name, u.full_name as approved_by_name
                   FROM leave_applications la
                   JOIN leave_types lt ON la.leave_type_id = lt.leave_type_id
                   LEFT JOIN users u ON la.approved_by = u.user_id
                   WHERE la.company_id = ? AND la.employee_id = ?
                   ORDER BY la.created_at DESC LIMIT 5";
    $stmt = $conn->prepare($recent_sql);
    $stmt->execute([$company_id, $employee['employee_id'] ?? 0]);
    $recent_applications = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Pending approvals (for managers)
    if ($is_manager) {
        $pending_sql = "SELECT la.*, lt.leave_type_name, u.full_name as employee_name, d.department_name
                        FROM leave_applications la
                        JOIN leave_types lt ON la.leave_type_id = lt.leave_type_id
                        JOIN employees e ON la.employee_id = e.employee_id
                        JOIN users u ON e.user_id = u.user_id
                        LEFT JOIN departments d ON e.department_id = d.department_id
                        WHERE la.company_id = ? AND la.status = 'pending'
                        ORDER BY la.application_date ASC";
        $stmt = $conn->prepare($pending_sql);
        $stmt->execute([$company_id]);
        $pending_approvals = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // Company-wide stats
    $stats_sql = "SELECT 
        COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending_count,
        COUNT(CASE WHEN status = 'approved' AND MONTH(start_date) = MONTH(CURDATE()) THEN 1 END) as approved_this_month,
        COUNT(CASE WHEN status = 'rejected' AND MONTH(created_at) = MONTH(CURDATE()) THEN 1 END) as rejected_this_month
    FROM leave_applications WHERE company_id = ?";
    $stmt = $conn->prepare($stats_sql);
    $stmt->execute([$company_id]);
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    error_log("Leave module error: " . $e->getMessage());
    $error = "An error occurred while loading leave data.";
}

$page_title = "Leave Management";
require_once '../../includes/header.php';
?>

<style>
    .leave-card {
        background: white;
        border-radius: 15px;
        padding: 25px;
        box-shadow: 0 4px 15px rgba(0,0,0,0.08);
        margin-bottom: 20px;
        transition: transform 0.2s;
    }
    .leave-card:hover {
        transform: translateY(-3px);
    }
    .stat-card {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        border-radius: 15px;
        padding: 25px;
        text-align: center;
    }
    .stat-card.success {
        background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
    }
    .stat-card.warning {
        background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
    }
    .stat-card.info {
        background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
    }
    .stat-number {
        font-size: 2.5rem;
        font-weight: 700;
        margin-bottom: 5px;
    }
    .balance-item {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 15px;
        border-bottom: 1px solid #eee;
    }
    .balance-item:last-child {
        border-bottom: none;
    }
    .balance-bar {
        height: 8px;
        background: #e9ecef;
        border-radius: 4px;
        overflow: hidden;
        width: 100px;
    }
    .balance-fill {
        height: 100%;
        background: linear-gradient(90deg, #28a745, #20c997);
        border-radius: 4px;
    }
    .quick-actions .btn {
        padding: 15px 25px;
        border-radius: 10px;
        font-weight: 600;
    }
</style>

<div class="content-wrapper">
    <section class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1><i class="fas fa-calendar-alt me-2"></i>Leave Management</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-end">
                        <li class="breadcrumb-item"><a href="../../dashboard.php">Home</a></li>
                        <li class="breadcrumb-item active">Leave Management</li>
                    </ol>
                </div>
            </div>
        </div>
    </section>

    <section class="content">
        <div class="container-fluid">
            
            <!-- Quick Actions -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="quick-actions">
                        <a href="apply.php" class="btn btn-primary me-2">
                            <i class="fas fa-plus-circle me-2"></i>Apply for Leave
                        </a>
                        <a href="my-leaves.php" class="btn btn-outline-primary me-2">
                            <i class="fas fa-list me-2"></i>My Leave History
                        </a>
                        <?php if ($is_manager): ?>
                        <a href="approvals.php" class="btn btn-warning me-2">
                            <i class="fas fa-check-circle me-2"></i>Pending Approvals
                            <?php if ($stats['pending_count'] > 0): ?>
                            <span class="badge bg-danger"><?php echo $stats['pending_count']; ?></span>
                            <?php endif; ?>
                        </a>
                        <a href="leave-types.php" class="btn btn-outline-secondary me-2">
                            <i class="fas fa-cog me-2"></i>Leave Types
                        </a>
                        <a href="reports.php" class="btn btn-outline-info">
                            <i class="fas fa-chart-bar me-2"></i>Reports
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Statistics Cards -->
            <div class="row mb-4">
                <div class="col-lg-3 col-md-6 mb-3">
                    <div class="stat-card">
                        <div class="stat-number"><?php echo $stats['pending_count'] ?? 0; ?></div>
                        <div>Pending Requests</div>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6 mb-3">
                    <div class="stat-card success">
                        <div class="stat-number"><?php echo $stats['approved_this_month'] ?? 0; ?></div>
                        <div>Approved This Month</div>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6 mb-3">
                    <div class="stat-card warning">
                        <div class="stat-number"><?php echo $stats['rejected_this_month'] ?? 0; ?></div>
                        <div>Rejected This Month</div>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6 mb-3">
                    <div class="stat-card info">
                        <div class="stat-number"><?php echo count($my_leave_balance ?? []); ?></div>
                        <div>Leave Types</div>
                    </div>
                </div>
            </div>

            <div class="row">
                <!-- Leave Balance -->
                <div class="col-lg-6 mb-4">
                    <div class="leave-card">
                        <h5 class="mb-4"><i class="fas fa-balance-scale me-2 text-primary"></i>My Leave Balance (<?php echo date('Y'); ?>)</h5>
                        <?php if (!empty($my_leave_balance)): ?>
                            <?php foreach ($my_leave_balance as $balance): ?>
                            <div class="balance-item">
                                <div>
                                    <strong><?php echo htmlspecialchars($balance['leave_type_name']); ?></strong>
                                    <br>
                                    <small class="text-muted">
                                        <?php echo $balance['days_taken']; ?> taken / <?php echo $balance['days_per_year']; ?> total
                                    </small>
                                </div>
                                <div class="text-end">
                                    <div class="mb-2">
                                        <span class="badge bg-<?php echo $balance['days_remaining'] > 0 ? 'success' : 'danger'; ?> fs-6">
                                            <?php echo $balance['days_remaining']; ?> days left
                                        </span>
                                    </div>
                                    <div class="balance-bar">
                                        <?php 
                                        $percentage = $balance['days_per_year'] > 0 
                                            ? min(100, ($balance['days_remaining'] / $balance['days_per_year']) * 100) 
                                            : 0;
                                        ?>
                                        <div class="balance-fill" style="width: <?php echo $percentage; ?>%"></div>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p class="text-muted text-center py-4">No leave types configured.</p>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Recent Applications -->
                <div class="col-lg-6 mb-4">
                    <div class="leave-card">
                        <h5 class="mb-4"><i class="fas fa-history me-2 text-info"></i>My Recent Applications</h5>
                        <?php if (!empty($recent_applications)): ?>
                            <div class="table-responsive">
                                <table class="table table-sm">
                                    <thead>
                                        <tr>
                                            <th>Type</th>
                                            <th>Dates</th>
                                            <th>Days</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($recent_applications as $app): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($app['leave_type_name']); ?></td>
                                            <td>
                                                <small>
                                                    <?php echo date('M d', strtotime($app['start_date'])); ?> - 
                                                    <?php echo date('M d, Y', strtotime($app['end_date'])); ?>
                                                </small>
                                            </td>
                                            <td><?php echo $app['total_days']; ?></td>
                                            <td><?php echo getStatusBadge($app['status']); ?></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <p class="text-muted text-center py-4">No leave applications yet.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <?php if ($is_manager && !empty($pending_approvals)): ?>
            <!-- Pending Approvals Section -->
            <div class="row">
                <div class="col-12">
                    <div class="leave-card">
                        <h5 class="mb-4">
                            <i class="fas fa-hourglass-half me-2 text-warning"></i>
                            Pending Approvals
                            <span class="badge bg-warning text-dark"><?php echo count($pending_approvals); ?></span>
                        </h5>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Employee</th>
                                        <th>Department</th>
                                        <th>Leave Type</th>
                                        <th>Period</th>
                                        <th>Days</th>
                                        <th>Applied On</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($pending_approvals as $approval): ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo htmlspecialchars($approval['employee_name']); ?></strong>
                                        </td>
                                        <td><?php echo htmlspecialchars($approval['department_name'] ?? 'N/A'); ?></td>
                                        <td><?php echo htmlspecialchars($approval['leave_type_name']); ?></td>
                                        <td>
                                            <?php echo date('M d', strtotime($approval['start_date'])); ?> - 
                                            <?php echo date('M d, Y', strtotime($approval['end_date'])); ?>
                                        </td>
                                        <td><span class="badge bg-primary"><?php echo $approval['total_days']; ?></span></td>
                                        <td><?php echo date('M d, Y', strtotime($approval['application_date'])); ?></td>
                                        <td>
                                            <a href="view.php?id=<?php echo $approval['leave_id']; ?>" 
                                               class="btn btn-sm btn-info" title="View Details">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <a href="process.php?id=<?php echo $approval['leave_id']; ?>&action=approve" 
                                               class="btn btn-sm btn-success" title="Approve"
                                               onclick="return confirm('Approve this leave request?');">
                                                <i class="fas fa-check"></i>
                                            </a>
                                            <a href="process.php?id=<?php echo $approval['leave_id']; ?>&action=reject" 
                                               class="btn btn-sm btn-danger" title="Reject">
                                                <i class="fas fa-times"></i>
                                            </a>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>

        </div>
    </section>
</div>

<?php require_once '../../includes/footer.php'; ?>
