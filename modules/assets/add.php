<?php
/**
 * Add/Edit Asset
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
if (!hasPermission($conn, $user_id, ['FINANCE_OFFICER', 'COMPANY_ADMIN', 'SUPER_ADMIN'])) {
    $_SESSION['error_message'] = "You don't have permission to manage assets.";
    header('Location: index.php');
    exit;
}

$asset_id = (int)($_GET['id'] ?? 0);
$asset = null;

if ($asset_id) {
    $sql = "SELECT * FROM fixed_assets WHERE asset_id = ? AND company_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->execute([$asset_id, $company_id]);
    $asset = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$asset) {
        $_SESSION['error_message'] = "Asset not found.";
        header('Location: list.php');
        exit;
    }
}

// Get categories
$sql = "SELECT * FROM asset_categories WHERE company_id = ? ORDER BY category_name";
$stmt = $conn->prepare($sql);
$stmt->execute([$company_id]);
$categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get departments
$sql = "SELECT department_id, department_name FROM departments WHERE company_id = ? ORDER BY department_name";
$stmt = $conn->prepare($sql);
$stmt->execute([$company_id]);
$departments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get employees for assignment
$sql = "SELECT e.employee_id, u.full_name, e.employee_number 
        FROM employees e
        INNER JOIN users u ON e.user_id = u.user_id
        WHERE e.company_id = ? AND e.is_active = 1 
        ORDER BY u.full_name";
$stmt = $conn->prepare($sql);
$stmt->execute([$company_id]);
$employees = $stmt->fetchAll(PDO::FETCH_ASSOC);

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $asset_name = sanitize($_POST['asset_name']);
    $asset_number = sanitize($_POST['asset_code'] ?? ''); // Form field is asset_code but DB column is asset_number
    $category_id = (int)$_POST['category_id'];
    $description = sanitize($_POST['description'] ?? '');
    $serial_number = sanitize($_POST['serial_number'] ?? '');
    $model_number = sanitize($_POST['model_number'] ?? '');
    $manufacturer = sanitize($_POST['manufacturer'] ?? '');
    $purchase_date = $_POST['purchase_date'];
    $purchase_cost = (float)$_POST['purchase_cost'];
    $installation_cost = (float)($_POST['installation_cost'] ?? 0);
    $total_cost = $purchase_cost + $installation_cost;
    $supplier_id = !empty($_POST['supplier_id']) ? (int)$_POST['supplier_id'] : null;
    $invoice_number = sanitize($_POST['invoice_number'] ?? '');
    $warranty_expiry_date = !empty($_POST['warranty_expiry']) ? $_POST['warranty_expiry'] : null;
    $location = sanitize($_POST['location'] ?? '');
    $department_id = !empty($_POST['department_id']) ? (int)$_POST['department_id'] : null;
    $custodian_id = !empty($_POST['assigned_to']) ? (int)$_POST['assigned_to'] : null;
    $status = sanitize($_POST['status'] ?? 'active');
    $account_code = sanitize($_POST['account_code'] ?? '1210');
    $depreciation_account_code = sanitize($_POST['depreciation_account_code'] ?? '1250');
    $depreciation_method = sanitize($_POST['depreciation_method'] ?? 'straight_line');
    $useful_life_years = (int)($_POST['useful_life_years'] ?? 5);
    $salvage_value = (float)($_POST['salvage_value'] ?? 0);
    $current_book_value = (float)($_POST['current_value'] ?? $total_cost);
    $notes = sanitize($_POST['notes'] ?? '');
    
    // Validation
    if (empty($asset_name)) {
        $errors[] = "Asset name is required.";
    }
    if (empty($asset_number)) {
        $errors[] = "Asset number is required.";
    }
    if (!$category_id) {
        $errors[] = "Please select a category.";
    }
    if ($purchase_cost < 0) {
        $errors[] = "Purchase cost cannot be negative.";
    }
    if (empty($purchase_date)) {
        $errors[] = "Purchase date is required.";
    }
    
    // Check for duplicate asset number
    $check_sql = "SELECT COUNT(*) as count FROM fixed_assets WHERE company_id = ? AND asset_number = ? AND asset_id != ?";
    $stmt = $conn->prepare($check_sql);
    $stmt->execute([$company_id, $asset_number, $asset_id]);
    if ($stmt->fetch(PDO::FETCH_ASSOC)['count'] > 0) {
        $errors[] = "An asset with this number already exists.";
    }
    
    if (empty($errors)) {
        try {
            if ($asset_id) {
                // Update existing asset
                $sql = "UPDATE fixed_assets SET 
                            asset_name = ?, asset_number = ?, category_id = ?, description = ?,
                            serial_number = ?, model_number = ?, manufacturer = ?,
                            purchase_date = ?, purchase_cost = ?, installation_cost = ?, total_cost = ?,
                            supplier_id = ?, invoice_number = ?, warranty_expiry_date = ?,
                            location = ?, department_id = ?, custodian_id = ?,
                            account_code = ?, depreciation_account_code = ?, depreciation_method = ?,
                            useful_life_years = ?, salvage_value = ?, current_book_value = ?,
                            status = ?, notes = ?, updated_at = NOW()
                        WHERE asset_id = ? AND company_id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->execute([
                    $asset_name, $asset_number, $category_id, $description,
                    $serial_number, $model_number, $manufacturer,
                    $purchase_date, $purchase_cost, $installation_cost, $total_cost,
                    $supplier_id, $invoice_number, $warranty_expiry_date,
                    $location, $department_id, $custodian_id,
                    $account_code, $depreciation_account_code, $depreciation_method,
                    $useful_life_years, $salvage_value, $current_book_value,
                    $status, $notes, $asset_id, $company_id
                ]);
                
                logAudit($conn, $company_id, $user_id, 'update', 'assets', 'fixed_assets', $asset_id, 
                         $asset, ['asset_name' => $asset_name]);
                
                $_SESSION['success_message'] = "Asset updated successfully.";
            } else {
                // Insert new asset
                $sql = "INSERT INTO fixed_assets (
                            company_id, asset_name, asset_number, category_id, description,
                            serial_number, model_number, manufacturer,
                            purchase_date, purchase_cost, installation_cost, total_cost,
                            supplier_id, invoice_number, warranty_expiry_date,
                            location, department_id, custodian_id,
                            account_code, depreciation_account_code, depreciation_method,
                            useful_life_years, salvage_value, current_book_value,
                            status, notes, created_by
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                $stmt = $conn->prepare($sql);
                $stmt->execute([
                    $company_id, $asset_name, $asset_number, $category_id, $description,
                    $serial_number, $model_number, $manufacturer,
                    $purchase_date, $purchase_cost, $installation_cost, $total_cost,
                    $supplier_id, $invoice_number, $warranty_expiry_date,
                    $location, $department_id, $custodian_id,
                    $account_code, $depreciation_account_code, $depreciation_method,
                    $useful_life_years, $salvage_value, $current_book_value,
                    $status, $notes, $user_id
                ]);
                $new_id = $conn->lastInsertId();
                
                logAudit($conn, $company_id, $user_id, 'create', 'assets', 'fixed_assets', $new_id, 
                         null, ['asset_name' => $asset_name, 'asset_number' => $asset_number]);
                
                $_SESSION['success_message'] = "Asset registered successfully.";
            }
            
            header('Location: list.php');
            exit;
            
        } catch (PDOException $e) {
            error_log("Asset error: " . $e->getMessage());
            $errors[] = "An error occurred. Please try again.";
        }
    }
}

$page_title = $asset_id ? "Edit Asset" : "Add Asset";
require_once '../../includes/header.php';
?>

<style>
    .form-card {
        background: white;
        border-radius: 15px;
        box-shadow: 0 4px 15px rgba(0,0,0,0.08);
        padding: 30px;
    }
    .form-section {
        margin-bottom: 30px;
        padding-bottom: 20px;
        border-bottom: 1px solid #eee;
    }
    .form-section:last-child { border-bottom: none; margin-bottom: 0; }
    .form-section h5 {
        color: #667eea;
        margin-bottom: 20px;
    }
    .preview-card {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        border-radius: 15px;
        padding: 25px;
    }
</style>

<div class="content-wrapper">
    <section class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1><i class="fas fa-<?php echo $asset_id ? 'edit' : 'plus-circle'; ?> me-2"></i><?php echo $page_title; ?></h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-end">
                        <li class="breadcrumb-item"><a href="../../dashboard.php">Home</a></li>
                        <li class="breadcrumb-item"><a href="index.php">Assets</a></li>
                        <li class="breadcrumb-item active"><?php echo $asset_id ? 'Edit' : 'Add'; ?></li>
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

            <form method="POST">
                <div class="row">
                    <div class="col-lg-8">
                        <div class="form-card">
                            
                            <!-- Basic Information -->
                            <div class="form-section">
                                <h5><i class="fas fa-info-circle me-2"></i>Basic Information</h5>
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Asset Name <span class="text-danger">*</span></label>
                                        <input type="text" name="asset_name" class="form-control" required
                                               value="<?php echo htmlspecialchars($asset['asset_name'] ?? ''); ?>"
                                               placeholder="e.g., Dell Laptop, Office Desk">
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Asset Number <span class="text-danger">*</span></label>
                                        <input type="text" name="asset_code" class="form-control" required
                                               value="<?php echo htmlspecialchars($asset['asset_number'] ?? ''); ?>"
                                               placeholder="e.g., IT-LAP-001">
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Category <span class="text-danger">*</span></label>
                                        <select name="category_id" class="form-select" required>
                                            <option value="">-- Select Category --</option>
                                            <?php foreach ($categories as $cat): ?>
                                            <option value="<?php echo $cat['category_id']; ?>" 
                                                    data-useful-life="<?php echo $cat['useful_life_years'] ?? 5; ?>"
                                                    data-salvage="<?php echo $cat['salvage_value_percentage'] ?? 10; ?>"
                                                    <?php echo ($asset['category_id'] ?? '') == $cat['category_id'] ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($cat['category_name']); ?>
                                            </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Serial Number</label>
                                        <input type="text" name="serial_number" class="form-control"
                                               value="<?php echo htmlspecialchars($asset['serial_number'] ?? ''); ?>"
                                               placeholder="Manufacturer serial number">
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Description</label>
                                    <textarea name="description" class="form-control" rows="2"
                                              placeholder="Brief description of the asset..."><?php echo htmlspecialchars($asset['description'] ?? ''); ?></textarea>
                                </div>
                            </div>

                            <!-- Purchase Information -->
                            <div class="form-section">
                                <h5><i class="fas fa-shopping-cart me-2"></i>Purchase Information</h5>
                                <div class="row">
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label">Purchase Date <span class="text-danger">*</span></label>
                                        <input type="date" name="purchase_date" class="form-control" required
                                               value="<?php echo $asset['purchase_date'] ?? date('Y-m-d'); ?>">
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label">Purchase Cost (TZS) <span class="text-danger">*</span></label>
                                        <input type="number" name="purchase_cost" class="form-control" required
                                               min="0" step="0.01"
                                               value="<?php echo $asset['purchase_cost'] ?? ''; ?>"
                                               placeholder="0.00">
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label">Current Value (TZS)</label>
                                        <input type="number" name="current_value" class="form-control"
                                               min="0" step="0.01"
                                               value="<?php echo $asset['current_book_value'] ?? ''; ?>"
                                               placeholder="Leave blank to use purchase cost">
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Supplier/Vendor</label>
                                        <input type="text" name="supplier_id" class="form-control"
                                               value="<?php echo htmlspecialchars($asset['supplier_id'] ?? ''); ?>"
                                               placeholder="Supplier ID (optional)">
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Warranty Expiry</label>
                                        <input type="date" name="warranty_expiry" class="form-control"
                                               value="<?php echo $asset['warranty_expiry_date'] ?? ''; ?>">
                                    </div>
                                </div>
                            </div>

                            <!-- Location & Assignment -->
                            <div class="form-section">
                                <h5><i class="fas fa-map-marker-alt me-2"></i>Location & Assignment</h5>
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Location</label>
                                        <input type="text" name="location" class="form-control"
                                               value="<?php echo htmlspecialchars($asset['location'] ?? ''); ?>"
                                               placeholder="e.g., Building A, Room 101">
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Department</label>
                                        <select name="department_id" class="form-select">
                                            <option value="">-- Select Department --</option>
                                            <?php foreach ($departments as $dept): ?>
                                            <option value="<?php echo $dept['department_id']; ?>"
                                                    <?php echo ($asset['department_id'] ?? '') == $dept['department_id'] ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($dept['department_name']); ?>
                                            </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Assigned To</label>
                                        <select name="assigned_to" class="form-select">
                                            <option value="">-- Select Employee --</option>
                                            <?php foreach ($employees as $emp): ?>
                                            <option value="<?php echo $emp['employee_id']; ?>"
                                                    <?php echo ($asset['custodian_id'] ?? '') == $emp['employee_id'] ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($emp['full_name']); ?>
                                                (<?php echo $emp['employee_number']; ?>)
                                            </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Status</label>
                                        <select name="status" class="form-select">
                                            <option value="active" <?php echo ($asset['status'] ?? 'active') === 'active' ? 'selected' : ''; ?>>Active</option>
                                            <option value="under_maintenance" <?php echo ($asset['status'] ?? '') === 'under_maintenance' ? 'selected' : ''; ?>>Under Maintenance</option>
                                            <option value="disposed" <?php echo ($asset['status'] ?? '') === 'disposed' ? 'selected' : ''; ?>>Disposed</option>
                                            <option value="stolen" <?php echo ($asset['status'] ?? '') === 'stolen' ? 'selected' : ''; ?>>Stolen</option>
                                            <option value="damaged" <?php echo ($asset['status'] ?? '') === 'damaged' ? 'selected' : ''; ?>>Damaged</option>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <!-- Notes -->
                            <div class="form-section">
                                <h5><i class="fas fa-sticky-note me-2"></i>Additional Notes</h5>
                                <textarea name="notes" class="form-control" rows="3"
                                          placeholder="Any additional notes about this asset..."><?php echo htmlspecialchars($asset['notes'] ?? ''); ?></textarea>
                            </div>

                            <div class="mt-4">
                                <button type="submit" class="btn btn-primary btn-lg">
                                    <i class="fas fa-save me-2"></i><?php echo $asset_id ? 'Update Asset' : 'Register Asset'; ?>
                                </button>
                                <a href="list.php" class="btn btn-outline-secondary btn-lg ms-2">Cancel</a>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-4">
                        <!-- Preview Card -->
                        <div class="preview-card mb-4">
                            <h6><i class="fas fa-eye me-2"></i>Preview</h6>
                            <hr style="border-color: rgba(255,255,255,0.3);">
                            <p class="mb-2"><strong id="previewName">Asset Name</strong></p>
                            <p class="mb-2"><code id="previewCode">Asset Number</code></p>
                            <p class="mb-0"><small>Value: <span id="previewValue">TZS 0</span></small></p>
                        </div>

                        <!-- Guidelines -->
                        <div class="card">
                            <div class="card-header">
                                <h6 class="mb-0"><i class="fas fa-info-circle me-2"></i>Guidelines</h6>
                            </div>
                            <div class="card-body">
                                <ul class="small mb-0">
                                    <li class="mb-2">Use consistent naming conventions for assets</li>
                                    <li class="mb-2">Asset codes should be unique and descriptive</li>
                                    <li class="mb-2">Keep purchase receipts for audit purposes</li>
                                    <li class="mb-2">Update current value periodically</li>
                                    <li>Assign to department or individual for accountability</li>
                                </ul>
                            </div>
                        </div>

                        <!-- Depreciation Info -->
                        <div class="card mt-4" id="depreciationInfo" style="display: none;">
                            <div class="card-header bg-warning text-dark">
                                <h6 class="mb-0"><i class="fas fa-chart-line me-2"></i>Depreciation</h6>
                            </div>
                            <div class="card-body">
                                <p class="mb-2">Annual Rate: <strong id="depRate">0</strong>%</p>
                                <p class="mb-2">Monthly: <strong id="depMonthly">TZS 0</strong></p>
                                <p class="mb-0">Yearly: <strong id="depYearly">TZS 0</strong></p>
                            </div>
                        </div>
                    </div>
                </div>
            </form>

        </div>
    </section>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const assetName = document.querySelector('[name="asset_name"]');
    const assetCode = document.querySelector('[name="asset_code"]');
    const purchaseCost = document.querySelector('[name="purchase_cost"]');
    const categorySelect = document.querySelector('[name="category_id"]');
    
    function updatePreview() {
        document.getElementById('previewName').textContent = assetName.value || 'Asset Name';
        document.getElementById('previewCode').textContent = assetCode.value || 'Asset Number';
        document.getElementById('previewValue').textContent = 'TZS ' + (parseFloat(purchaseCost.value) || 0).toLocaleString();
    }
    
    function updateDepreciation() {
        const selected = categorySelect.options[categorySelect.selectedIndex];
        const usefulLife = parseFloat(selected.dataset.usefulLife) || 5;
        const salvagePercent = parseFloat(selected.dataset.salvage) || 10;
        const cost = parseFloat(purchaseCost.value) || 0;
        const salvageValue = cost * (salvagePercent / 100);
        const depreciableCost = cost - salvageValue;
        const annualRate = usefulLife > 0 ? (100 / usefulLife) : 0;
        const monthlyDep = usefulLife > 0 ? (depreciableCost / (usefulLife * 12)) : 0;
        const yearlyDep = monthlyDep * 12;
        
        if (annualRate > 0 && cost > 0) {
            document.getElementById('depreciationInfo').style.display = 'block';
            document.getElementById('depRate').textContent = annualRate.toFixed(2) + '%';
            document.getElementById('depYearly').textContent = 'TZS ' + Math.round(yearlyDep).toLocaleString();
            document.getElementById('depMonthly').textContent = 'TZS ' + Math.round(monthlyDep).toLocaleString();
        } else {
            document.getElementById('depreciationInfo').style.display = 'none';
        }
    }
    
    assetName.addEventListener('input', updatePreview);
    assetCode.addEventListener('input', updatePreview);
    purchaseCost.addEventListener('input', function() {
        updatePreview();
        updateDepreciation();
    });
    categorySelect.addEventListener('change', updateDepreciation);
    
    updatePreview();
    updateDepreciation();
});
</script>

<?php require_once '../../includes/footer.php'; ?>
