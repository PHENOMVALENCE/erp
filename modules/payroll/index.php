<?php
/**
 * Payroll Management Module - Main Dashboard
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

// Check permission
if (!hasPermission($conn, $user_id, ['ACCOUNTANT', 'HR_OFFICER', 'FINANCE_OFFICER', 'MANAGER', 'COMPANY_ADMIN', 'SUPER_ADMIN'])) {
    $_SESSION['error_message'] = "You don't have permission to access payroll.";
    header('Location: ../../dashboard.php');
    exit;
}

// Get current month/year
$current_month = date('n');
$current_year = date('Y');
$selected_month = isset($_GET['month']) ? (int)$_GET['month'] : $current_month;
$selected_year = isset($_GET['year']) ? (int)$_GET['year'] : $current_year;

try {
    // Fetch payroll statistics
    $stats_sql = "SELECT 
        COUNT(DISTINCT pd.employee_id) as employee_count,
        SUM(pd.gross_salary) as total_gross,
        SUM(pd.total_deductions) as total_deductions,
        SUM(pd.net_salary) as total_net,
        p.status as payroll_status,
        p.payroll_id
    FROM payroll p
    LEFT JOIN payroll_details pd ON p.payroll_id = pd.payroll_id
    WHERE p.company_id = ? AND p.payroll_month = ? AND p.payroll_year = ?
    GROUP BY p.payroll_id, p.status";
    
    $stmt = $conn->prepare($stats_sql);
    $stmt->execute([$company_id, $selected_month, $selected_year]);
    $payroll_data = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Get employee count
    $emp_count_sql = "SELECT COUNT(*) as count FROM employees WHERE company_id = ? AND employment_status = 'active'";
    $stmt = $conn->prepare($emp_count_sql);
    $stmt->execute([$company_id]);
    $total_employees = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    // Recent payroll records
    $recent_sql = "SELECT p.*, 
                   COUNT(pd.payroll_detail_id) as employee_count,
                   SUM(pd.net_salary) as total_net
                   FROM payroll p
                   LEFT JOIN payroll_details pd ON p.payroll_id = pd.payroll_id
                   WHERE p.company_id = ?
                   GROUP BY p.payroll_id
                   ORDER BY p.payroll_year DESC, p.payroll_month DESC
                   LIMIT 6";
    $stmt = $conn->prepare($recent_sql);
    $stmt->execute([$company_id]);
    $recent_payrolls = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Monthly payroll trend (last 6 months)
    $trend_sql = "SELECT 
        p.payroll_month, p.payroll_year,
        SUM(pd.gross_salary) as gross,
        SUM(pd.net_salary) as net
    FROM payroll p
    JOIN payroll_details pd ON p.payroll_id = pd.payroll_id
    WHERE p.company_id = ? AND p.status IN ('processed', 'paid')
    GROUP BY p.payroll_year, p.payroll_month
    ORDER BY p.payroll_year DESC, p.payroll_month DESC
    LIMIT 6";
    $stmt = $conn->prepare($trend_sql);
    $stmt->execute([$company_id]);
    $trend_data = array_reverse($stmt->fetchAll(PDO::FETCH_ASSOC));
    
} catch (PDOException $e) {
    error_log("Payroll dashboard error: " . $e->getMessage());
    $error = "An error occurred loading payroll data.";
}

$page_title = "Payroll Management";
require_once '../../includes/header.php';
?>

<style>
    .payroll-card {
        background: white;
        border-radius: 15px;
        padding: 25px;
        box-shadow: 0 4px 15px rgba(0,0,0,0.08);
        margin-bottom: 20px;
        transition: transform 0.2s;
    }
    .payroll-card:hover {
        transform: translateY(-3px);
    }
    .stat-card {
        border-radius: 15px;
        padding: 25px;
        color: white;
        text-align: center;
        height: 100%;
    }
    .stat-card.primary { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); }
    .stat-card.success { background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%); }
    .stat-card.warning { background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); }
    .stat-card.info { background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); }
    .stat-number {
        font-size: 1.8rem;
        font-weight: 700;
        margin-bottom: 5px;
    }
    .period-selector {
        background: #f8f9fa;
        border-radius: 10px;
        padding: 20px;
        margin-bottom: 20px;
    }
    .quick-actions .btn {
        padding: 12px 20px;
        border-radius: 10px;
        font-weight: 600;
        margin-right: 10px;
        margin-bottom: 10px;
    }
    .payroll-status {
        display: inline-block;
        padding: 5px 15px;
        border-radius: 20px;
        font-weight: 600;
        font-size: 0.85rem;
    }
    .payroll-status.draft { background: #e2e3e5; color: #383d41; }
    .payroll-status.processed { background: #cce5ff; color: #004085; }
    .payroll-status.paid { background: #d4edda; color: #155724; }
    .recent-item {
        padding: 15px;
        border-bottom: 1px solid #eee;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    .recent-item:last-child { border-bottom: none; }
    .chart-container {
        height: 250px;
    }
</style>

<div class="content-wrapper">
    <section class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1><i class="fas fa-money-check-alt me-2"></i>Payroll Management</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-end">
                        <li class="breadcrumb-item"><a href="../../dashboard.php">Home</a></li>
                        <li class="breadcrumb-item active">Payroll</li>
                    </ol>
                </div>
            </div>
        </div>
    </section>

    <section class="content">
        <div class="container-fluid">
            
            <?php if (isset($_SESSION['success_message'])): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <i class="fas fa-check-circle me-2"></i>
                <?php echo $_SESSION['success_message']; unset($_SESSION['success_message']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>

            <!-- Period Selector -->
            <div class="period-selector">
                <form method="GET" class="row g-3 align-items-end">
                    <div class="col-md-3">
                        <label class="form-label fw-semibold">Month</label>
                        <select name="month" class="form-select">
                            <?php for ($m = 1; $m <= 12; $m++): ?>
                            <option value="<?php echo $m; ?>" <?php echo $selected_month == $m ? 'selected' : ''; ?>>
                                <?php echo date('F', mktime(0, 0, 0, $m, 1)); ?>
                            </option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label fw-semibold">Year</label>
                        <select name="year" class="form-select">
                            <?php for ($y = date('Y'); $y >= date('Y') - 3; $y--): ?>
                            <option value="<?php echo $y; ?>" <?php echo $selected_year == $y ? 'selected' : ''; ?>>
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

            <!-- Quick Actions -->
            <div class="quick-actions mb-4">
                <?php if (!$payroll_data): ?>
                <a href="generate.php?month=<?php echo $selected_month; ?>&year=<?php echo $selected_year; ?>" class="btn btn-success">
                    <i class="fas fa-calculator me-2"></i>Generate Payroll
                </a>
                <?php elseif ($payroll_data['payroll_status'] === 'draft'): ?>
                <a href="process.php?id=<?php echo $payroll_data['payroll_id']; ?>&action=process" class="btn btn-primary">
                    <i class="fas fa-check-circle me-2"></i>Process Payroll
                </a>
                <a href="edit.php?id=<?php echo $payroll_data['payroll_id']; ?>" class="btn btn-outline-primary">
                    <i class="fas fa-edit me-2"></i>Edit Payroll
                </a>
                <?php elseif ($payroll_data['payroll_status'] === 'processed'): ?>
                <a href="process.php?id=<?php echo $payroll_data['payroll_id']; ?>&action=pay" class="btn btn-success">
                    <i class="fas fa-wallet me-2"></i>Mark as Paid
                </a>
                <?php endif; ?>
                
                <a href="payslips.php?month=<?php echo $selected_month; ?>&year=<?php echo $selected_year; ?>" class="btn btn-outline-info">
                    <i class="fas fa-file-invoice me-2"></i>View Payslips
                </a>
                <a href="reports.php" class="btn btn-outline-secondary">
                    <i class="fas fa-chart-bar me-2"></i>Reports
                </a>
                <a href="settings.php" class="btn btn-outline-dark">
                    <i class="fas fa-cog me-2"></i>Settings
                </a>
            </div>

            <!-- Statistics Cards -->
            <div class="row mb-4">
                <div class="col-lg-3 col-md-6 mb-3">
                    <div class="stat-card primary">
                        <div class="stat-number"><?php echo $payroll_data['employee_count'] ?? 0; ?>/<?php echo $total_employees; ?></div>
                        <div>Employees Processed</div>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6 mb-3">
                    <div class="stat-card info">
                        <div class="stat-number"><?php echo formatCurrency($payroll_data['total_gross'] ?? 0, ''); ?></div>
                        <div>Total Gross Salary</div>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6 mb-3">
                    <div class="stat-card warning">
                        <div class="stat-number"><?php echo formatCurrency($payroll_data['total_deductions'] ?? 0, ''); ?></div>
                        <div>Total Deductions</div>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6 mb-3">
                    <div class="stat-card success">
                        <div class="stat-number"><?php echo formatCurrency($payroll_data['total_net'] ?? 0, ''); ?></div>
                        <div>Total Net Pay</div>
                    </div>
                </div>
            </div>

            <div class="row">
                <!-- Current Period Status -->
                <div class="col-lg-6 mb-4">
                    <div class="payroll-card">
                        <h5 class="mb-4">
                            <i class="fas fa-calendar-check me-2 text-primary"></i>
                            <?php echo date('F Y', mktime(0, 0, 0, $selected_month, 1, $selected_year)); ?> Payroll
                        </h5>
                        
                        <?php if ($payroll_data): ?>
                        <div class="text-center py-4">
                            <div class="mb-3">
                                <span class="payroll-status <?php echo $payroll_data['payroll_status']; ?>">
                                    <?php echo ucfirst($payroll_data['payroll_status']); ?>
                                </span>
                            </div>
                            <h2 class="text-success mb-2"><?php echo formatCurrency($payroll_data['total_net'] ?? 0); ?></h2>
                            <p class="text-muted mb-4">Net Payroll for <?php echo $payroll_data['employee_count']; ?> employees</p>
                            
                            <div class="row text-center">
                                <div class="col-4">
                                    <h5><?php echo formatCurrency($payroll_data['total_gross'] ?? 0, ''); ?></h5>
                                    <small class="text-muted">Gross</small>
                                </div>
                                <div class="col-4">
                                    <h5 class="text-danger"><?php echo formatCurrency($payroll_data['total_deductions'] ?? 0, ''); ?></h5>
                                    <small class="text-muted">Deductions</small>
                                </div>
                                <div class="col-4">
                                    <h5 class="text-success"><?php echo formatCurrency($payroll_data['total_net'] ?? 0, ''); ?></h5>
                                    <small class="text-muted">Net</small>
                                </div>
                            </div>
                        </div>
                        <?php else: ?>
                        <div class="text-center py-5">
                            <i class="fas fa-inbox fa-4x text-muted mb-3"></i>
                            <h5>No Payroll Generated</h5>
                            <p class="text-muted mb-4">Payroll for this period has not been generated yet.</p>
                            <a href="generate.php?month=<?php echo $selected_month; ?>&year=<?php echo $selected_year; ?>" 
                               class="btn btn-success btn-lg">
                                <i class="fas fa-calculator me-2"></i>Generate Now
                            </a>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Recent Payrolls -->
                <div class="col-lg-6 mb-4">
                    <div class="payroll-card">
                        <h5 class="mb-4"><i class="fas fa-history me-2 text-info"></i>Recent Payrolls</h5>
                        
                        <?php if (!empty($recent_payrolls)): ?>
                        <?php foreach ($recent_payrolls as $pr): ?>
                        <div class="recent-item">
                            <div>
                                <strong><?php echo date('F Y', mktime(0, 0, 0, $pr['payroll_month'], 1, $pr['payroll_year'])); ?></strong>
                                <br>
                                <small class="text-muted"><?php echo $pr['employee_count']; ?> employees</small>
                            </div>
                            <div class="text-end">
                                <div class="mb-1">
                                    <span class="payroll-status <?php echo $pr['status']; ?>">
                                        <?php echo ucfirst($pr['status']); ?>
                                    </span>
                                </div>
                                <strong class="text-success"><?php echo formatCurrency($pr['total_net'] ?? 0); ?></strong>
                            </div>
                        </div>
                        <?php endforeach; ?>
                        <?php else: ?>
                        <p class="text-muted text-center py-4">No payroll history available.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Payroll Trend Chart -->
            <?php if (!empty($trend_data)): ?>
            <div class="row">
                <div class="col-12">
                    <div class="payroll-card">
                        <h5 class="mb-4"><i class="fas fa-chart-line me-2 text-primary"></i>Payroll Trend (Last 6 Months)</h5>
                        <div class="chart-container">
                            <canvas id="payrollChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>

        </div>
    </section>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
<?php if (!empty($trend_data)): ?>
const ctx = document.getElementById('payrollChart').getContext('2d');
new Chart(ctx, {
    type: 'line',
    data: {
        labels: [<?php echo implode(',', array_map(function($d) { 
            return "'" . date('M Y', mktime(0, 0, 0, $d['payroll_month'], 1, $d['payroll_year'])) . "'"; 
        }, $trend_data)); ?>],
        datasets: [{
            label: 'Gross Salary',
            data: [<?php echo implode(',', array_column($trend_data, 'gross')); ?>],
            borderColor: '#4facfe',
            backgroundColor: 'rgba(79, 172, 254, 0.1)',
            tension: 0.4,
            fill: true
        }, {
            label: 'Net Salary',
            data: [<?php echo implode(',', array_column($trend_data, 'net')); ?>],
            borderColor: '#38ef7d',
            backgroundColor: 'rgba(56, 239, 125, 0.1)',
            tension: 0.4,
            fill: true
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                position: 'top'
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                ticks: {
                    callback: function(value) {
                        return 'TZS ' + value.toLocaleString();
                    }
                }
            }
        }
    }
});
<?php endif; ?>
</script>

<?php require_once '../../includes/footer.php'; ?>
