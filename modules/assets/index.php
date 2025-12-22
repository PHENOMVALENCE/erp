<?php
/**
 * Asset Management Dashboard
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
$is_admin = hasPermission($conn, $user_id, ['FINANCE_OFFICER', 'COMPANY_ADMIN', 'SUPER_ADMIN']);

// Get asset statistics
$stats = [];
$sql = "SELECT 
            COUNT(*) as total_assets,
            SUM(purchase_cost) as total_cost,
            SUM(current_value) as total_value,
            SUM(purchase_cost - current_value) as total_depreciation,
            COUNT(CASE WHEN status = 'ACTIVE' THEN 1 END) as active_assets,
            COUNT(CASE WHEN status = 'MAINTENANCE' THEN 1 END) as maintenance_assets,
            COUNT(CASE WHEN status = 'DISPOSED' THEN 1 END) as disposed_assets
        FROM assets WHERE company_id = ?";
$stmt = $conn->prepare($sql);
$stmt->execute([$company_id]);
$stats = $stmt->fetch(PDO::FETCH_ASSOC);

// Get assets by category
$sql = "SELECT ac.category_name, COUNT(a.asset_id) as count, SUM(a.current_value) as value
        FROM asset_categories ac
        LEFT JOIN assets a ON ac.category_id = a.category_id AND a.company_id = ?
        WHERE ac.company_id = ? OR ac.company_id IS NULL
        GROUP BY ac.category_id, ac.category_name
        ORDER BY count DESC";
$stmt = $conn->prepare($sql);
$stmt->execute([$company_id, $company_id]);
$categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get recent assets
$sql = "SELECT a.*, ac.category_name, d.department_name,
               CONCAT(e.first_name, ' ', e.last_name) as assigned_to_name
        FROM assets a
        LEFT JOIN asset_categories ac ON a.category_id = ac.category_id
        LEFT JOIN departments d ON a.department_id = d.department_id
        LEFT JOIN employees e ON a.assigned_to = e.employee_id
        WHERE a.company_id = ?
        ORDER BY a.created_at DESC LIMIT 10";
$stmt = $conn->prepare($sql);
$stmt->execute([$company_id]);
$recent_assets = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get upcoming maintenance
$sql = "SELECT am.*, a.asset_name, a.asset_code
        FROM asset_maintenance am
        JOIN assets a ON am.asset_id = a.asset_id
        WHERE a.company_id = ? AND am.status = 'SCHEDULED' AND am.scheduled_date >= CURDATE()
        ORDER BY am.scheduled_date ASC LIMIT 5";
$stmt = $conn->prepare($sql);
$stmt->execute([$company_id]);
$upcoming_maintenance = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get depreciation due this month
$sql = "SELECT a.*, ac.category_name,
               (a.purchase_cost - a.current_value) as depreciated,
               (a.purchase_cost * ac.depreciation_rate / 100 / 12) as monthly_depreciation
        FROM assets a
        JOIN asset_categories ac ON a.category_id = ac.category_id
        WHERE a.company_id = ? AND a.status = 'ACTIVE' AND a.current_value > 0
        ORDER BY monthly_depreciation DESC LIMIT 5";
$stmt = $conn->prepare($sql);
$stmt->execute([$company_id]);
$depreciation_due = $stmt->fetchAll(PDO::FETCH_ASSOC);

$page_title = "Asset Management";
require_once '../../includes/header.php';
?>

<style>
    .stat-card {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        border-radius: 15px;
        color: white;
        padding: 25px;
        position: relative;
        overflow: hidden;
    }
    .stat-card.success { background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%); }
    .stat-card.warning { background: linear-gradient(135deg, #F2994A 0%, #F2C94C 100%); }
    .stat-card.info { background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); }
    .stat-card.danger { background: linear-gradient(135deg, #f5576c 0%, #f093fb 100%); }
    .stat-card::after {
        content: '';
        position: absolute;
        top: -50%;
        right: -50%;
        width: 100%;
        height: 100%;
        background: rgba(255,255,255,0.1);
        border-radius: 50%;
    }
    .stat-card h3 { font-size: 2rem; margin-bottom: 5px; }
    .stat-card p { opacity: 0.9; margin: 0; }
    .stat-card i { font-size: 3rem; opacity: 0.3; position: absolute; right: 20px; top: 20px; }
    
    .quick-action-card {
        background: white;
        border-radius: 15px;
        padding: 25px;
        text-align: center;
        box-shadow: 0 4px 15px rgba(0,0,0,0.08);
        transition: transform 0.2s;
    }
    .quick-action-card:hover { transform: translateY(-5px); }
    .quick-action-card i {
        font-size: 2.5rem;
        background: linear-gradient(135deg, #667eea, #764ba2);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        margin-bottom: 15px;
    }
    
    .asset-card {
        background: white;
        border-radius: 15px;
        box-shadow: 0 4px 15px rgba(0,0,0,0.08);
        overflow: hidden;
    }
    
    .category-bar {
        height: 8px;
        border-radius: 4px;
        background: #e9ecef;
        overflow: hidden;
    }
    .category-bar .fill {
        height: 100%;
        border-radius: 4px;
        background: linear-gradient(90deg, #667eea, #764ba2);
    }
    
    .maintenance-item {
        padding: 15px;
        border-bottom: 1px solid #eee;
    }
    .maintenance-item:last-child { border-bottom: none; }
</style>

<div class="content-wrapper">
    <section class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1><i class="fas fa-warehouse me-2"></i>Asset Management</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-end">
                        <li class="breadcrumb-item"><a href="../../dashboard.php">Home</a></li>
                        <li class="breadcrumb-item active">Assets</li>
                    </ol>
                </div>
            </div>
        </div>
    </section>

    <section class="content">
        <div class="container-fluid">
            
            <!-- Statistics Cards -->
            <div class="row mb-4">
                <div class="col-lg-3 col-md-6 mb-3">
                    <div class="stat-card">
                        <i class="fas fa-boxes"></i>
                        <h3><?php echo number_format($stats['total_assets'] ?? 0); ?></h3>
                        <p>Total Assets</p>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6 mb-3">
                    <div class="stat-card success">
                        <i class="fas fa-coins"></i>
                        <h3><?php echo formatCurrency($stats['total_cost'] ?? 0); ?></h3>
                        <p>Purchase Cost</p>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6 mb-3">
                    <div class="stat-card info">
                        <i class="fas fa-chart-line"></i>
                        <h3><?php echo formatCurrency($stats['total_value'] ?? 0); ?></h3>
                        <p>Current Value</p>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6 mb-3">
                    <div class="stat-card danger">
                        <i class="fas fa-chart-area"></i>
                        <h3><?php echo formatCurrency($stats['total_depreciation'] ?? 0); ?></h3>
                        <p>Total Depreciation</p>
                    </div>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="row mb-4">
                <div class="col-md-3 mb-3">
                    <a href="add.php" class="text-decoration-none">
                        <div class="quick-action-card">
                            <i class="fas fa-plus-circle"></i>
                            <h5>Add Asset</h5>
                            <p class="text-muted small mb-0">Register new asset</p>
                        </div>
                    </a>
                </div>
                <div class="col-md-3 mb-3">
                    <a href="list.php" class="text-decoration-none">
                        <div class="quick-action-card">
                            <i class="fas fa-list"></i>
                            <h5>Asset Register</h5>
                            <p class="text-muted small mb-0">View all assets</p>
                        </div>
                    </a>
                </div>
                <div class="col-md-3 mb-3">
                    <a href="depreciation.php" class="text-decoration-none">
                        <div class="quick-action-card">
                            <i class="fas fa-calculator"></i>
                            <h5>Depreciation</h5>
                            <p class="text-muted small mb-0">Calculate & record</p>
                        </div>
                    </a>
                </div>
                <div class="col-md-3 mb-3">
                    <a href="categories.php" class="text-decoration-none">
                        <div class="quick-action-card">
                            <i class="fas fa-tags"></i>
                            <h5>Categories</h5>
                            <p class="text-muted small mb-0">Manage categories</p>
                        </div>
                    </a>
                </div>
            </div>

            <div class="row">
                <!-- Recent Assets -->
                <div class="col-lg-8">
                    <div class="asset-card">
                        <div class="card-header bg-white p-3 d-flex justify-content-between align-items-center">
                            <h5 class="mb-0"><i class="fas fa-clock me-2"></i>Recent Assets</h5>
                            <a href="list.php" class="btn btn-sm btn-outline-primary">View All</a>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="bg-light">
                                    <tr>
                                        <th>Asset</th>
                                        <th>Category</th>
                                        <th>Value</th>
                                        <th>Location</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($recent_assets)): ?>
                                    <tr>
                                        <td colspan="5" class="text-center py-4">
                                            <i class="fas fa-inbox fa-2x text-muted mb-2"></i>
                                            <p class="text-muted mb-0">No assets registered yet.</p>
                                        </td>
                                    </tr>
                                    <?php else: ?>
                                    <?php foreach ($recent_assets as $asset): ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo htmlspecialchars($asset['asset_name']); ?></strong>
                                            <br><code><?php echo htmlspecialchars($asset['asset_code']); ?></code>
                                        </td>
                                        <td><?php echo htmlspecialchars($asset['category_name'] ?? 'N/A'); ?></td>
                                        <td><?php echo formatCurrency($asset['current_value']); ?></td>
                                        <td>
                                            <?php echo htmlspecialchars($asset['department_name'] ?? $asset['location'] ?? 'N/A'); ?>
                                            <?php if ($asset['assigned_to_name']): ?>
                                            <br><small class="text-muted"><?php echo htmlspecialchars($asset['assigned_to_name']); ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo getStatusBadge($asset['status']); ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Assets by Category -->
                    <div class="asset-card mt-4">
                        <div class="card-header bg-white p-3">
                            <h5 class="mb-0"><i class="fas fa-chart-pie me-2"></i>Assets by Category</h5>
                        </div>
                        <div class="card-body">
                            <?php 
                            $max_count = max(array_column($categories, 'count') ?: [1]);
                            foreach ($categories as $cat): 
                                $percent = $max_count > 0 ? ($cat['count'] / $max_count) * 100 : 0;
                            ?>
                            <div class="mb-3">
                                <div class="d-flex justify-content-between mb-1">
                                    <span><?php echo htmlspecialchars($cat['category_name']); ?></span>
                                    <span><?php echo $cat['count']; ?> assets (<?php echo formatCurrency($cat['value'] ?? 0); ?>)</span>
                                </div>
                                <div class="category-bar">
                                    <div class="fill" style="width: <?php echo $percent; ?>%"></div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <!-- Right Column -->
                <div class="col-lg-4">
                    <!-- Asset Status Summary -->
                    <div class="asset-card mb-4">
                        <div class="card-header bg-white p-3">
                            <h5 class="mb-0"><i class="fas fa-chart-bar me-2"></i>Status Summary</h5>
                        </div>
                        <div class="card-body">
                            <div class="d-flex justify-content-between mb-3">
                                <span><i class="fas fa-check-circle text-success me-2"></i>Active</span>
                                <strong><?php echo $stats['active_assets'] ?? 0; ?></strong>
                            </div>
                            <div class="d-flex justify-content-between mb-3">
                                <span><i class="fas fa-tools text-warning me-2"></i>Under Maintenance</span>
                                <strong><?php echo $stats['maintenance_assets'] ?? 0; ?></strong>
                            </div>
                            <div class="d-flex justify-content-between">
                                <span><i class="fas fa-trash text-danger me-2"></i>Disposed</span>
                                <strong><?php echo $stats['disposed_assets'] ?? 0; ?></strong>
                            </div>
                        </div>
                    </div>

                    <!-- Upcoming Maintenance -->
                    <div class="asset-card mb-4">
                        <div class="card-header bg-white p-3">
                            <h5 class="mb-0"><i class="fas fa-wrench me-2"></i>Upcoming Maintenance</h5>
                        </div>
                        <div class="card-body p-0">
                            <?php if (empty($upcoming_maintenance)): ?>
                            <div class="text-center py-4">
                                <i class="fas fa-check-circle fa-2x text-success mb-2"></i>
                                <p class="text-muted mb-0">No scheduled maintenance</p>
                            </div>
                            <?php else: ?>
                            <?php foreach ($upcoming_maintenance as $m): ?>
                            <div class="maintenance-item">
                                <strong><?php echo htmlspecialchars($m['asset_name']); ?></strong>
                                <small class="d-block text-muted"><?php echo htmlspecialchars($m['asset_code']); ?></small>
                                <div class="d-flex justify-content-between mt-2">
                                    <small><?php echo htmlspecialchars($m['maintenance_type']); ?></small>
                                    <span class="badge bg-warning">
                                        <?php echo date('M d', strtotime($m['scheduled_date'])); ?>
                                    </span>
                                </div>
                            </div>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Depreciation Summary -->
                    <div class="asset-card">
                        <div class="card-header bg-white p-3">
                            <h5 class="mb-0"><i class="fas fa-chart-line me-2"></i>Monthly Depreciation</h5>
                        </div>
                        <div class="card-body p-0">
                            <?php if (empty($depreciation_due)): ?>
                            <div class="text-center py-4">
                                <p class="text-muted mb-0">No depreciating assets</p>
                            </div>
                            <?php else: ?>
                            <?php foreach ($depreciation_due as $d): ?>
                            <div class="maintenance-item">
                                <div class="d-flex justify-content-between">
                                    <strong><?php echo htmlspecialchars($d['asset_name']); ?></strong>
                                    <span class="text-danger">-<?php echo formatCurrency($d['monthly_depreciation']); ?></span>
                                </div>
                                <small class="text-muted"><?php echo htmlspecialchars($d['category_name']); ?></small>
                            </div>
                            <?php endforeach; ?>
                            <div class="p-3 bg-light">
                                <a href="depreciation.php" class="btn btn-primary btn-sm w-100">
                                    <i class="fas fa-calculator me-2"></i>Run Depreciation
                                </a>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </section>
</div>

<?php require_once '../../includes/footer.php'; ?>
