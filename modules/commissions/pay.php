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
$user_id = $_SESSION['user_id'];

// Get commission_id
$commission_id = $_GET['commission_id'] ?? null;

if (!$commission_id) {
    header('Location: index.php');
    exit;
}

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax'])) {
    header('Content-Type: application/json');
    
    try {
        if ($_POST['action'] === 'process_payment') {
            // Validate required fields
            if (empty($_POST['commission_id']) || empty($_POST['payment_amount']) || empty($_POST['payment_method'])) {
                throw new Exception('Commission, payment amount, and method are required');
            }
            
            // Check if commission is approved
            $check = $conn->prepare("
                SELECT * FROM commissions 
                WHERE commission_id = ? AND company_id = ? AND status = 'approved'
            ");
            $check->execute([$_POST['commission_id'], $company_id]);
            $commission = $check->fetch(PDO::FETCH_ASSOC);
            
            if (!$commission) {
                throw new Exception('Commission not found or not approved');
            }
            
            // Check if already paid
            $paid_check = $conn->prepare("
                SELECT commission_payment_id FROM commission_payments 
                WHERE commission_id = ?
            ");
            $paid_check->execute([$_POST['commission_id']]);
            
            if ($paid_check->rowCount() > 0) {
                throw new Exception('This commission has already been paid');
            }
            
            // Start transaction
            $conn->beginTransaction();
            
            try {
                // Handle file upload
                $attachment_path = null;
                if (isset($_FILES['payment_proof']) && $_FILES['payment_proof']['error'] === UPLOAD_ERR_OK) {
                    $upload_dir = '../../uploads/commission_payments/';
                    if (!is_dir($upload_dir)) {
                        mkdir($upload_dir, 0755, true);
                    }
                    
                    $file_extension = pathinfo($_FILES['payment_proof']['name'], PATHINFO_EXTENSION);
                    $file_name = 'payment_' . time() . '_' . uniqid() . '.' . $file_extension;
                    $target_file = $upload_dir . $file_name;
                    
                    if (move_uploaded_file($_FILES['payment_proof']['tmp_name'], $target_file)) {
                        $attachment_path = 'uploads/commission_payments/' . $file_name;
                    }
                }
                
                // Insert payment record
                $stmt = $conn->prepare("
                    INSERT INTO commission_payments (
                        commission_id, payment_date, payment_amount, payment_method,
                        reference_number, bank_name, account_number,
                        payment_proof, remarks, paid_by, created_at
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
                ");
                
                $stmt->execute([
                    $_POST['commission_id'],
                    $_POST['payment_date'],
                    $_POST['payment_amount'],
                    $_POST['payment_method'],
                    $_POST['reference_number'] ?? null,
                    $_POST['bank_name'] ?? null,
                    $_POST['account_number'] ?? null,
                    $attachment_path,
                    $_POST['remarks'] ?? null,
                    $user_id
                ]);
                
                // Update commission status to paid
                $update = $conn->prepare("
                    UPDATE commissions 
                    SET status = 'paid',
                        paid_at = NOW()
                    WHERE commission_id = ?
                ");
                $update->execute([$_POST['commission_id']]);
                
                // Record transaction in accounting (if chart of accounts exists)
                $account_check = $conn->prepare("
                    SELECT account_id FROM chart_of_accounts 
                    WHERE company_id = ? AND account_name LIKE '%commission%' 
                    AND account_type = 'expense'
                    LIMIT 1
                ");
                $account_check->execute([$company_id]);
                $expense_account = $account_check->fetch(PDO::FETCH_ASSOC);
                
                if ($expense_account) {
                    $trans_stmt = $conn->prepare("
                        INSERT INTO journal_entries (
                            company_id, entry_date, entry_type, reference_number,
                            description, total_debit, total_credit, created_by, created_at
                        ) VALUES (?, ?, 'commission_payment', ?, ?, ?, ?, ?, NOW())
                    ");
                    
                    $trans_stmt->execute([
                        $company_id,
                        $_POST['payment_date'],
                        $_POST['reference_number'] ?? 'COM-' . $_POST['commission_id'],
                        'Commission Payment',
                        $_POST['payment_amount'],
                        $_POST['payment_amount'],
                        $user_id
                    ]);
                    
                    $journal_entry_id = $conn->lastInsertId();
                    
                    // Debit: Commission Expense
                    $line_stmt = $conn->prepare("
                        INSERT INTO journal_entry_lines (
                            journal_entry_id, account_id, debit_amount, credit_amount, description
                        ) VALUES (?, ?, ?, 0, 'Commission Payment')
                    ");
                    $line_stmt->execute([
                        $journal_entry_id,
                        $expense_account['account_id'],
                        $_POST['payment_amount']
                    ]);
                    
                    // Credit: Bank/Cash (you may want to make this configurable)
                    // For now, we'll just record the debit side
                }
                
                $conn->commit();
                
                echo json_encode(['success' => true, 'message' => 'Payment processed successfully']);
                
            } catch (Exception $e) {
                $conn->rollBack();
                throw $e;
            }
        }
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

// Fetch commission details
try {
    $stmt = $conn->prepare("
        SELECT 
            c.*,
            s.sale_date,
            s.selling_price as sale_amount,
            cu.customer_name,
            cu.phone_number,
            cu.email as customer_email,
            p.plot_number,
            pr.project_name,
            u.first_name, u.last_name, u.email, u.phone_number as user_phone,
            approver.first_name as approver_first_name,
            approver.last_name as approver_last_name
        FROM commissions c
        INNER JOIN sales s ON c.sale_id = s.sale_id
        INNER JOIN customers cu ON s.customer_id = cu.customer_id
        INNER JOIN plots p ON s.plot_id = p.plot_id
        INNER JOIN projects pr ON p.project_id = pr.project_id
        INNER JOIN users u ON c.user_id = u.user_id
        LEFT JOIN users approver ON c.approved_by = approver.user_id
        WHERE c.commission_id = ? AND c.company_id = ?
    ");
    $stmt->execute([$commission_id, $company_id]);
    $commission = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$commission) {
        header('Location: index.php');
        exit;
    }
    
    // Check if commission is approved
    if ($commission['status'] !== 'approved') {
        $_SESSION['error'] = 'Only approved commissions can be paid';
        header('Location: index.php');
        exit;
    }
    
    // Check if already paid
    $paid_check = $conn->prepare("
        SELECT * FROM commission_payments WHERE commission_id = ?
    ");
    $paid_check->execute([$commission_id]);
    $existing_payment = $paid_check->fetch(PDO::FETCH_ASSOC);
    
    if ($existing_payment) {
        $_SESSION['error'] = 'This commission has already been paid';
        header('Location: index.php');
        exit;
    }
    
} catch (PDOException $e) {
    $error_message = "Error fetching commission: " . $e->getMessage();
}

$page_title = 'Pay Commission';
require_once '../../includes/header.php';
?>

<style>
.payment-card {
    border-radius: 12px;
    border: none;
    box-shadow: 0 2px 8px rgba(0,0,0,0.08);
}

.card {
    border: none;
    border-radius: 12px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.08);
}

.card-header {
    background: #fff;
    border-bottom: 2px solid #f3f4f6;
    border-radius: 12px 12px 0 0 !important;
    padding: 1.25rem 1.5rem;
}

.form-label {
    font-weight: 600;
    color: #374151;
    margin-bottom: 0.5rem;
}

.form-control, .form-select {
    border-radius: 8px;
    border: 1px solid #d1d5db;
    padding: 0.625rem 0.875rem;
}

.btn {
    border-radius: 8px;
    padding: 0.625rem 1.25rem;
    font-weight: 500;
}

.commission-info-card {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: #fff;
    border-radius: 12px;
    padding: 1.5rem;
}

.info-item {
    padding: 0.75rem;
    background: #f9fafb;
    border-radius: 8px;
    margin-bottom: 0.75rem;
}

.commission-amount-display {
    font-size: 2rem;
    font-weight: 700;
    color: #059669;
}

.payment-method-option {
    padding: 1rem;
    border: 2px solid #e5e7eb;
    border-radius: 8px;
    cursor: pointer;
    transition: all 0.2s;
}

.payment-method-option:hover {
    border-color: #667eea;
    background: #f9fafb;
}

.payment-method-option.active {
    border-color: #667eea;
    background: #ede9fe;
}

.payment-method-option input[type="radio"] {
    display: none;
}
</style>

<!-- Content Header -->
<div class="content-header">
    <div class="container-fluid">
        <div class="row mb-4">
            <div class="col-sm-6">
                <h1 class="m-0 fw-bold">
                    <i class="fas fa-money-bill-wave text-success me-2"></i>Pay Commission
                </h1>
                <p class="text-muted small mb-0 mt-1">Process commission payment</p>
            </div>
            <div class="col-sm-6">
                <div class="float-sm-end">
                    <a href="index.php" class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-left me-2"></i>Back to Commissions
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Main content -->
<section class="content">
    <div class="container-fluid">
        
        <?php if (isset($error_message)): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <i class="fas fa-exclamation-triangle me-2"></i><?php echo $error_message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>
        
        <div class="row">
            <!-- Commission Details -->
            <div class="col-lg-5">
                <div class="commission-info-card mb-4">
                    <h4 class="mb-3">Commission Details</h4>
                    <div class="row">
                        <div class="col-12 mb-3">
                            <small class="opacity-75">COMMISSION AMOUNT</small>
                            <div class="commission-amount-display">
                                TZS <?php echo number_format((float)$commission['commission_amount'], 2); ?>
                            </div>
                        </div>
                        <div class="col-6">
                            <small class="opacity-75">Commission Rate</small>
                            <div class="h5"><?php echo number_format((float)$commission['commission_rate'], 2); ?>%</div>
                        </div>
                        <div class="col-6">
                            <small class="opacity-75">Sale Amount</small>
                            <div class="h5">TZS <?php echo number_format((float)$commission['sale_amount'], 0); ?></div>
                        </div>
                    </div>
                </div>
                
                <div class="card mb-3">
                    <div class="card-header">
                        <h6 class="mb-0 fw-bold">
                            <i class="fas fa-user me-2"></i>Agent Information
                        </h6>
                    </div>
                    <div class="card-body">
                        <div class="info-item">
                            <small class="text-muted d-block">Name</small>
                            <strong><?php echo htmlspecialchars($commission['first_name'] . ' ' . $commission['last_name']); ?></strong>
                        </div>
                        <div class="info-item">
                            <small class="text-muted d-block">Email</small>
                            <strong><?php echo htmlspecialchars($commission['email']); ?></strong>
                        </div>
                        <?php if ($commission['user_phone']): ?>
                        <div class="info-item">
                            <small class="text-muted d-block">Phone</small>
                            <strong><?php echo htmlspecialchars($commission['user_phone']); ?></strong>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="card">
                    <div class="card-header">
                        <h6 class="mb-0 fw-bold">
                            <i class="fas fa-file-invoice me-2"></i>Sale Information
                        </h6>
                    </div>
                    <div class="card-body">
                        <div class="info-item">
                            <small class="text-muted d-block">Customer</small>
                            <strong><?php echo htmlspecialchars($commission['customer_name']); ?></strong>
                        </div>
                        <div class="info-item">
                            <small class="text-muted d-block">Property</small>
                            <strong><?php echo htmlspecialchars($commission['project_name']); ?> - Plot <?php echo htmlspecialchars($commission['plot_number']); ?></strong>
                        </div>
                        <div class="info-item">
                            <small class="text-muted d-block">Sale Date</small>
                            <strong><?php echo date('M d, Y', strtotime($commission['sale_date'])); ?></strong>
                        </div>
                        <div class="info-item">
                            <small class="text-muted d-block">Approved By</small>
                            <strong><?php echo htmlspecialchars($commission['approver_first_name'] . ' ' . $commission['approver_last_name']); ?></strong>
                            <br><small class="text-muted">on <?php echo date('M d, Y', strtotime($commission['approved_at'])); ?></small>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Payment Form -->
            <div class="col-lg-7">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0 fw-bold">
                            <i class="fas fa-credit-card me-2"></i>Payment Information
                        </h5>
                    </div>
                    <div class="card-body">
                        <form id="paymentForm" enctype="multipart/form-data">
                            <input type="hidden" name="ajax" value="1">
                            <input type="hidden" name="action" value="process_payment">
                            <input type="hidden" name="commission_id" value="<?php echo $commission_id; ?>">
                            
                            <div class="row g-3">
                                <div class="col-12">
                                    <label class="form-label">Payment Method <span class="text-danger">*</span></label>
                                    <div class="row g-2">
                                        <div class="col-md-4">
                                            <label class="payment-method-option">
                                                <input type="radio" name="payment_method" value="bank_transfer" required onchange="togglePaymentFields()">
                                                <div class="text-center">
                                                    <i class="fas fa-university fa-2x mb-2 text-primary"></i>
                                                    <div class="fw-bold">Bank Transfer</div>
                                                </div>
                                            </label>
                                        </div>
                                        <div class="col-md-4">
                                            <label class="payment-method-option">
                                                <input type="radio" name="payment_method" value="mobile_money" onchange="togglePaymentFields()">
                                                <div class="text-center">
                                                    <i class="fas fa-mobile-alt fa-2x mb-2 text-success"></i>
                                                    <div class="fw-bold">Mobile Money</div>
                                                </div>
                                            </label>
                                        </div>
                                        <div class="col-md-4">
                                            <label class="payment-method-option">
                                                <input type="radio" name="payment_method" value="cash" onchange="togglePaymentFields()">
                                                <div class="text-center">
                                                    <i class="fas fa-money-bill-wave fa-2x mb-2 text-info"></i>
                                                    <div class="fw-bold">Cash</div>
                                                </div>
                                            </label>
                                        </div>
                                        <div class="col-md-4">
                                            <label class="payment-method-option">
                                                <input type="radio" name="payment_method" value="cheque" onchange="togglePaymentFields()">
                                                <div class="text-center">
                                                    <i class="fas fa-money-check fa-2x mb-2 text-warning"></i>
                                                    <div class="fw-bold">Cheque</div>
                                                </div>
                                            </label>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="col-md-6">
                                    <label class="form-label">Payment Date <span class="text-danger">*</span></label>
                                    <input type="date" class="form-control" name="payment_date" 
                                           value="<?php echo date('Y-m-d'); ?>" required>
                                </div>
                                
                                <div class="col-md-6">
                                    <label class="form-label">Payment Amount (TZS) <span class="text-danger">*</span></label>
                                    <input type="number" class="form-control" name="payment_amount" 
                                           value="<?php echo $commission['commission_amount']; ?>" 
                                           step="0.01" min="0" required>
                                </div>
                                
                                <div class="col-12">
                                    <label class="form-label">Reference Number</label>
                                    <input type="text" class="form-control" name="reference_number" 
                                           placeholder="e.g., TRX123456789">
                                </div>
                                
                                <div class="col-md-6" id="bank_name_field" style="display: none;">
                                    <label class="form-label">Bank Name</label>
                                    <input type="text" class="form-control" name="bank_name" 
                                           placeholder="e.g., CRDB Bank">
                                </div>
                                
                                <div class="col-md-6" id="account_number_field" style="display: none;">
                                    <label class="form-label">Account Number</label>
                                    <input type="text" class="form-control" name="account_number" 
                                           placeholder="e.g., 0150xxxxxxxx">
                                </div>
                                
                                <div class="col-12">
                                    <label class="form-label">Payment Proof/Receipt</label>
                                    <input type="file" class="form-control" name="payment_proof" 
                                           accept=".pdf,.jpg,.jpeg,.png">
                                    <small class="text-muted">Upload payment receipt or proof (PDF, JPG, PNG - Max 5MB)</small>
                                </div>
                                
                                <div class="col-12">
                                    <label class="form-label">Remarks</label>
                                    <textarea class="form-control" name="remarks" rows="3" 
                                              placeholder="Additional notes about this payment"></textarea>
                                </div>
                                
                                <div class="col-12">
                                    <div class="alert alert-warning">
                                        <i class="fas fa-exclamation-triangle me-2"></i>
                                        <strong>Important:</strong> Once payment is processed, this action cannot be undone. 
                                        Please verify all payment details before proceeding.
                                    </div>
                                </div>
                                
                                <div class="col-12">
                                    <button type="submit" class="btn btn-success btn-lg w-100">
                                        <i class="fas fa-check me-2"></i>Process Payment
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
        
    </div>
</section>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

<script>
function togglePaymentFields() {
    const method = document.querySelector('input[name="payment_method"]:checked').value;
    
    // Remove active class from all options
    document.querySelectorAll('.payment-method-option').forEach(opt => {
        opt.classList.remove('active');
    });
    
    // Add active class to selected option
    document.querySelector('input[name="payment_method"]:checked').closest('.payment-method-option').classList.add('active');
    
    // Show/hide fields based on payment method
    if (method === 'bank_transfer') {
        document.getElementById('bank_name_field').style.display = 'block';
        document.getElementById('account_number_field').style.display = 'block';
    } else {
        document.getElementById('bank_name_field').style.display = 'none';
        document.getElementById('account_number_field').style.display = 'none';
    }
}

// Process payment
document.getElementById('paymentForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    if (!confirm('Are you sure you want to process this payment? This action cannot be undone.')) {
        return;
    }
    
    const formData = new FormData(this);
    const submitBtn = this.querySelector('button[type="submit"]');
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Processing...';
    
    $.ajax({
        url: '',
        type: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                alert(response.message);
                window.location.href = 'index.php?status=paid';
            } else {
                alert('Error: ' + response.message);
                submitBtn.disabled = false;
                submitBtn.innerHTML = '<i class="fas fa-check me-2"></i>Process Payment';
            }
        },
        error: function() {
            alert('Error processing payment');
            submitBtn.disabled = false;
            submitBtn.innerHTML = '<i class="fas fa-check me-2"></i>Process Payment';
        }
    });
});
</script>

<?php require_once '../../includes/footer.php'; ?>