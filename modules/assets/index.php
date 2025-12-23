<?php
define('APP_ACCESS', true);
session_start();
require_once '../../config/database.php';
require_once '../../config/auth.php';

$auth = new Auth();
$auth->requireLogin();

$db = Database::getInstance();
$db->setCompanyId($_SESSION['company_id']);
$conn = $db->getConnection();
$company_id = $_SESSION['company_id'];

// Statistics
$stats = ['total' => 0, 'purchase_cost' => 0, 'current_value' => 0, 'depreciation' => 0];
try {
    $stmt = $conn->prepare("SELECT COUNT(*) as total, COALESCE(SUM(total_cost), 0) as cost, 
        COALESCE(SUM(current_book_value), 0) as value,
        COALESCE(SUM(accumulated_depreciation), 0) as depreciation
        FROM fixed_assets WHERE company_id = ? AND status = 'active'");
    $stmt->execute([$company_id]);
    $row = $stmt->fetch();
    $stats['total'] = $row['total'];
    $stats['purchase_cost'] = $row['cost'];
    $stats['current_value'] = $row['value'];
    $stats['depreciation'] = $row['depreciation'];
} catch (Exception $e) {}

// Recent assets
$recent_assets = [];
try {
    $stmt = $conn->prepare("SELECT a.*, a.asset_number as asset_code, a.current_book_value as current_value,
        ac.category_name, d.department_name 
        FROM fixed_assets a
        LEFT JOIN asset_categories ac ON a.category_id = ac.category_id
        LEFT JOIN departments d ON a.department_id = d.department_id
        WHERE a.company_id = ? ORDER BY a.created_at DESC LIMIT 10");
    $stmt->execute([$company_id]);
    $recent_assets = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

// Categories
$categories = [];
try {
    $stmt = $conn->prepare("SELECT ac.*, COUNT(a.asset_id) as asset_count, COALESCE(SUM(a.current_book_value), 0) as total_value
        FROM asset_categories ac
        LEFT JOIN fixed_assets a ON ac.category_id = a.category_id AND a.company_id = ?
        WHERE ac.company_id = ? OR ac.company_id IS NULL
        GROUP BY ac.category_id ORDER BY asset_count DESC");
    $stmt->execute([$company_id, $company_id]);
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

$page_title = 'Asset Management';
require_once '../../includes/header.php';
?>

<style>
.stats-card{background:white;border-radius:12px;padding:1.5rem;box-shadow:0 2px 8px rgba(0,0,0,0.08);border-left:4px solid;transition:transform .2s}
.stats-card:hover{transform:translateY(-4px)}
.stats-card.primary{border-left-color:#007bff}.stats-card.success{border-left-color:#28a745}
.stats-card.info{border-left-color:#17a2b8}.stats-card.danger{border-left-color:#dc3545}
.stats-number{font-size:2rem;font-weight:700;color:#2c3e50}
.stats-label{color:#6c757d;font-size:.875rem;font-weight:500}
.action-card{background:white;border-radius:12px;padding:2rem;text-align:center;box-shadow:0 2px 8px rgba(0,0,0,0.08);transition:all .3s;text-decoration:none;color:inherit;display:block}
.action-card:hover{transform:translateY(-5px);box-shadow:0 8px 25px rgba(0,0,0,0.15)}
.action-card i{font-size:2.5rem;color:#007bff;margin-bottom:1rem}
.table-card{background:white;border-radius:12px;padding:1.5rem;box-shadow:0 2px 8px rgba(0,0,0,0.08)}
.status-badge{padding:.35rem .75rem;border-radius:20px;font-size:.8rem;font-weight:600}
.status-badge.active{background:#d4edda;color:#155724}
.status-badge.maintenance{background:#fff3cd;color:#856404}
.status-badge.disposed{background:#f8d7da;color:#721c24}
</style>

<div class="content-header mb-4">
    <div class="container-fluid">
        <div class="row align-items-center">
            <div class="col-sm-6"><h1 class="m-0 fw-bold"><i class="fas fa-warehouse me-2"></i>Asset Management</h1></div>
            <div class="col-sm-6 text-end">
                <a href="add.php" class="btn btn-primary"><i class="fas fa-plus-circle me-1"></i> Add Asset</a>
            </div>
        </div>
    </div>
</div>

<div class="container-fluid">
    <?php if (isset($_SESSION['success_message'])): ?>
    <div class="alert alert-success alert-dismissible fade show"><?= $_SESSION['success_message']; unset($_SESSION['success_message']); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <div class="row g-3 mb-4">
        <div class="col-lg-3 col-6">
            <div class="stats-card primary">
                <div class="stats-number"><?= number_format($stats['total']) ?></div>
                <div class="stats-label">Total Assets</div>
            </div>
        </div>
        <div class="col-lg-3 col-6">
            <div class="stats-card success">
                <div class="stats-number">TSH <?= number_format($stats['purchase_cost']/1000000, 1) ?>M</div>
                <div class="stats-label">Purchase Cost</div>
            </div>
        </div>
        <div class="col-lg-3 col-6">
            <div class="stats-card info">
                <div class="stats-number">TSH <?= number_format($stats['current_value']/1000000, 1) ?>M</div>
                <div class="stats-label">Current Value</div>
            </div>
        </div>
        <div class="col-lg-3 col-6">
            <div class="stats-card danger">
                <div class="stats-number">TSH <?= number_format($stats['depreciation']/1000000, 1) ?>M</div>
                <div class="stats-label">Total Depreciation</div>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-md-4"><a href="add.php" class="action-card"><i class="fas fa-plus-circle"></i><h5>Add Asset</h5><p class="text-muted small mb-0">Register new asset</p></a></div>
        <div class="col-md-4"><a href="list.php" class="action-card"><i class="fas fa-list"></i><h5>Asset Register</h5><p class="text-muted small mb-0">View all assets</p></a></div>
        <div class="col-md-4"><a href="depreciation.php" class="action-card"><i class="fas fa-calculator"></i><h5>Depreciation</h5><p class="text-muted small mb-0">Calculate & record</p></a></div>
    </div>

    <div class="row">
        <div class="col-lg-8">
            <div class="table-card">
                <h5 class="mb-3"><i class="fas fa-clock me-2"></i>Recent Assets</h5>
                <?php if (empty($recent_assets)): ?>
                <p class="text-muted text-center py-4">No assets registered yet.</p>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead class="table-light"><tr><th>Asset</th><th>Category</th><th>Value</th><th>Location</th><th>Status</th></tr></thead>
                        <tbody>
                            <?php foreach ($recent_assets as $a): ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($a['asset_name']) ?></strong><br><code><?= htmlspecialchars($a['asset_code']) ?></code></td>
                                <td><?= htmlspecialchars($a['category_name'] ?? 'N/A') ?></td>
                                <td>TSH <?= number_format($a['current_value']) ?></td>
                                <td><?= htmlspecialchars($a['department_name'] ?? $a['location'] ?? 'N/A') ?></td>
                                <td><span class="status-badge <?= strtolower($a['status']) ?>"><?= ucfirst(strtolower($a['status'])) ?></span></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="col-lg-4">
            <div class="table-card">
                <h5 class="mb-3"><i class="fas fa-chart-pie me-2"></i>Assets by Category</h5>
                <?php if (empty($categories)): ?>
                <p class="text-muted">No categories defined.</p>
                <?php else: ?>
                <?php foreach ($categories as $cat): ?>
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div>
                        <strong><?= htmlspecialchars($cat['category_name']) ?></strong>
                        <small class="d-block text-muted"><?= $cat['asset_count'] ?> assets</small>
                    </div>
                    <span class="text-primary fw-bold">TSH <?= number_format($cat['total_value']) ?></span>
                </div>
                <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>
