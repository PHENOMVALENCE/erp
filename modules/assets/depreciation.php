<?php
/**
 * Asset Depreciation
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
if (!hasPermission($conn, $user_id, ['FINANCE_OFFICER', 'COMPANY_ADMIN', 'SUPER_ADMIN'])) {
    $_SESSION['error_message'] = "You don't have permission to manage depreciation.";
    header('Location: index.php');
    exit;
}

$selected_month = $_GET['month'] ?? date('Y-m');
$selected_year = substr($selected_month, 0, 4);
$selected_month_num = substr($selected_month, 5, 2);

// Get assets with depreciation info
$sql = "SELECT a.*, ac.category_name, ac.depreciation_rate, ac.depreciation_method,
               (a.purchase_cost * ac.depreciation_rate / 100 / 12) as monthly_depreciation,
               (SELECT MAX(depreciation_date) FROM asset_depreciation WHERE asset_id = a.asset_id) as last_depreciation,
               (SELECT SUM(depreciation_amount) FROM asset_depreciation WHERE asset_id = a.asset_id) as total_depreciated
        FROM assets a
        JOIN asset_categories ac ON a.category_id = ac.category_id
        WHERE a.company_id = ? AND a.status = 'ACTIVE' AND a.current_value > 0
        ORDER BY ac.category_name, a.asset_name";
$stmt = $conn->prepare($sql);
$stmt->execute([$company_id]);
$assets = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Check if depreciation already run for selected month
$sql = "SELECT COUNT(*) as count FROM asset_depreciation 
        WHERE asset_id IN (SELECT asset_id FROM assets WHERE company_id = ?)
        AND YEAR(depreciation_date) = ? AND MONTH(depreciation_date) = ?";
$stmt = $conn->prepare($sql);
$stmt->execute([$company_id, $selected_year, $selected_month_num]);
$already_run = $stmt->fetch(PDO::FETCH_ASSOC)['count'] > 0;

// Get depreciation history
$sql = "SELECT ad.*, a.asset_name, a.asset_code, ac.category_name
        FROM asset_depreciation ad
        JOIN assets a ON ad.asset_id = a.asset_id
        LEFT JOIN asset_categories ac ON a.category_id = ac.category_id
        WHERE a.company_id = ?
        ORDER BY ad.depreciation_date DESC, a.asset_name
        LIMIT 50";
$stmt = $conn->prepare($sql);
$stmt->execute([$company_id]);
$history = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate totals
$total_monthly = 0;
$total_book_value = 0;
foreach ($assets as $a) {
    $total_monthly += $a['monthly_depreciation'];
    $total_book_value += $a['current_value'];
}

$page_title = "Asset Depreciation";
require_once '../../includes/header.php';
?>

<style>
    .summary-cards { display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px; margin-bottom: 30px; }
    .summary-card {
        background: white;
        border-radius: 15px;
        box-shadow: 0 4px 15px rgba(0,0,0,0.08);
        padding: 20px;
        text-align: center;
    }
    .summary-card h3 { color: #667eea; margin-bottom: 5px; }
    .summary-card.warning h3 { color: #ffc107; }
    .summary-card.success h3 { color: #28a745; }
    
    .depreciation-card {
        background: white;
        border-radius: 15px;
        box-shadow: 0 4px 15px rgba(0,0,0,0.08);
        overflow: hidden;
    }
    
    .run-depreciation-card {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        border-radius: 15px;
        padding: 25px;
        margin-bottom: 20px;
    }
</style>

<div class="content-wrapper">
    <section class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1><i class="fas fa-chart-line me-2"></i>Depreciation</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-end">
                        <li class="breadcrumb-item"><a href="../../dashboard.php">Home</a></li>
                        <li class="breadcrumb-item"><a href="index.php">Assets</a></li>
                        <li class="breadcrumb-item active">Depreciation</li>
                    </ol>
                </div>
            </div>
        </div>
    </section>

    <section class="content">
        <div class="container-fluid">
            
            <?php if (isset($_SESSION['success_message'])): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <?php echo $_SESSION['success_message']; unset($_SESSION['success_message']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>

            <!-- Summary Cards -->
            <div class="summary-cards">
                <div class="summary-card">
                    <h3><?php echo count($assets); ?></h3>
                    <p class="text-muted mb-0">Depreciating Assets</p>
                </div>
                <div class="summary-card">
                    <h3><?php echo formatCurrency($total_book_value); ?></h3>
                    <p class="text-muted mb-0">Total Book Value</p>
                </div>
                <div class="summary-card warning">
                    <h3><?php echo formatCurrency($total_monthly); ?></h3>
                    <p class="text-muted mb-0">Monthly Depreciation</p>
                </div>
                <div class="summary-card success">
                    <h3><?php echo formatCurrency($total_monthly * 12); ?></h3>
                    <p class="text-muted mb-0">Annual Depreciation</p>
                </div>
            </div>

            <!-- Run Depreciation -->
            <div class="run-depreciation-card">
                <div class="row align-items-center">
                    <div class="col-md-6">
                        <h5><i class="fas fa-calculator me-2"></i>Run Monthly Depreciation</h5>
                        <p class="mb-0 opacity-75">Calculate and record depreciation for all active assets</p>
                    </div>
                    <div class="col-md-6">
                        <form method="POST" action="process.php" class="d-flex gap-3 justify-content-end">
                            <input type="hidden" name="action" value="run_depreciation">
                            <input type="month" name="depreciation_month" class="form-control" 
                                   style="max-width: 200px;"
                                   value="<?php echo $selected_month; ?>" max="<?php echo date('Y-m'); ?>">
                            <button type="submit" class="btn btn-light" <?php echo $already_run ? 'disabled' : ''; ?>>
                                <i class="fas fa-play me-2"></i>
                                <?php echo $already_run ? 'Already Run' : 'Run Depreciation'; ?>
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <div class="row">
                <!-- Assets to Depreciate -->
                <div class="col-lg-7">
                    <div class="depreciation-card">
                        <div class="card-header bg-white p-3">
                            <h5 class="mb-0"><i class="fas fa-list me-2"></i>Assets Schedule</h5>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="bg-light">
                                    <tr>
                                        <th>Asset</th>
                                        <th>Category</th>
                                        <th>Purchase Cost</th>
                                        <th>Current Value</th>
                                        <th>Monthly Dep.</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($assets)): ?>
                                    <tr>
                                        <td colspan="5" class="text-center py-4">
                                            <p class="text-muted mb-0">No depreciating assets found.</p>
                                        </td>
                                    </tr>
                                    <?php else: ?>
                                    <?php 
                                    $current_category = '';
                                    foreach ($assets as $a): 
                                        if ($current_category !== $a['category_name']):
                                            $current_category = $a['category_name'];
                                    ?>
                                    <tr class="table-secondary">
                                        <td colspan="5"><strong><?php echo htmlspecialchars($current_category); ?></strong></td>
                                    </tr>
                                    <?php endif; ?>
                                    <tr>
                                        <td>
                                            <?php echo htmlspecialchars($a['asset_name']); ?>
                                            <br><code><?php echo htmlspecialchars($a['asset_code']); ?></code>
                                        </td>
                                        <td>
                                            <?php echo $a['depreciation_rate']; ?>% / year
                                            <br><small class="text-muted"><?php echo $a['depreciation_method'] ?? 'Straight Line'; ?></small>
                                        </td>
                                        <td><?php echo formatCurrency($a['purchase_cost']); ?></td>
                                        <td>
                                            <?php echo formatCurrency($a['current_value']); ?>
                                            <?php if ($a['total_depreciated']): ?>
                                            <br><small class="text-danger">-<?php echo formatCurrency($a['total_depreciated']); ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-warning">
                                            <strong><?php echo formatCurrency($a['monthly_depreciation']); ?></strong>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                                <tfoot class="table-dark">
                                    <tr>
                                        <td colspan="3"><strong>Total Monthly Depreciation</strong></td>
                                        <td colspan="2"><strong><?php echo formatCurrency($total_monthly); ?></strong></td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Depreciation History -->
                <div class="col-lg-5">
                    <div class="depreciation-card">
                        <div class="card-header bg-white p-3">
                            <h5 class="mb-0"><i class="fas fa-history me-2"></i>Recent History</h5>
                        </div>
                        <div class="table-responsive" style="max-height: 500px; overflow-y: auto;">
                            <table class="table table-sm mb-0">
                                <thead class="bg-light sticky-top">
                                    <tr>
                                        <th>Date</th>
                                        <th>Asset</th>
                                        <th>Amount</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($history)): ?>
                                    <tr>
                                        <td colspan="3" class="text-center py-4">
                                            <p class="text-muted mb-0">No depreciation recorded yet.</p>
                                        </td>
                                    </tr>
                                    <?php else: ?>
                                    <?php foreach ($history as $h): ?>
                                    <tr>
                                        <td><?php echo date('M Y', strtotime($h['depreciation_date'])); ?></td>
                                        <td>
                                            <?php echo htmlspecialchars($h['asset_name']); ?>
                                            <br><small class="text-muted"><?php echo htmlspecialchars($h['asset_code']); ?></small>
                                        </td>
                                        <td class="text-danger">-<?php echo formatCurrency($h['depreciation_amount']); ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Depreciation Methods Info -->
                    <div class="card mt-4">
                        <div class="card-header">
                            <h6 class="mb-0"><i class="fas fa-info-circle me-2"></i>Depreciation Methods</h6>
                        </div>
                        <div class="card-body">
                            <p class="small mb-2">
                                <strong>Straight Line:</strong> Equal amount each period. 
                                <br>Formula: (Cost - Salvage) / Useful Life
                            </p>
                            <p class="small mb-0">
                                <strong>Declining Balance:</strong> Higher in early years.
                                <br>Formula: Book Value × Rate
                            </p>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </section>
</div>

<?php require_once '../../includes/footer.php'; ?>
