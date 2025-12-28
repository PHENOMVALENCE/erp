<?php
define('APP_ACCESS', true);
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once '../../config/database.php';
require_once '../../config/auth.php';

$auth = new Auth();
$auth->requireLogin();

$db = Database::getInstance();
$db->setCompanyId($_SESSION['company_id']);
$conn = $db->getConnection();
$company_id = $_SESSION['company_id'];

$errors = [];
$success = '';

// Fetch active reservations with payment details
try {
    $reservations_sql = "SELECT 
                            r.reservation_id,
                            r.reservation_number,
                            r.total_amount,
                            r.down_payment,
                            r.payment_periods,
                            r.installment_amount,
                            r.status,
                            c.full_name as customer_name,
                            c.phone as customer_phone,
                            pl.plot_number,
                            pr.project_name,
                            COALESCE(SUM(CASE WHEN p.status = 'approved' THEN p.amount ELSE 0 END), 0) as total_paid,
                            (r.total_amount - COALESCE(SUM(CASE WHEN p.status = 'approved' THEN p.amount ELSE 0 END), 0)) as balance
                        FROM reservations r
                        JOIN customers c ON r.customer_id = c.customer_id
                        JOIN plots pl ON r.plot_id = pl.plot_id
                        JOIN projects pr ON pl.project_id = pr.project_id
                        LEFT JOIN payments p ON r.reservation_id = p.reservation_id AND p.company_id = r.company_id
                        WHERE r.status = 'active'
                        AND r.company_id = ?
                        GROUP BY r.reservation_id
                        HAVING balance > 0
                        ORDER BY r.reservation_date DESC";
    
    $stmt = $conn->prepare($reservations_sql);
    $stmt->execute([$company_id]);
    $reservations = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $errors[] = "Error fetching reservations: " . $e->getMessage();
    $reservations = [];
}

// Fetch company accounts (bank + mobile money)
try {
    $accounts_sql = "SELECT bank_account_id, account_name, account_category, 
                            bank_name, mobile_provider, account_number, mobile_number,
                            current_balance
                     FROM bank_accounts 
                     WHERE company_id = ? AND is_active = 1 
                     ORDER BY is_default DESC, account_category, account_name";
    $accounts_stmt = $conn->prepare($accounts_sql);
    $accounts_stmt->execute([$company_id]);
    $accounts = $accounts_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $bank_accounts = array_filter($accounts, fn($acc) => $acc['account_category'] === 'bank');
    $mobile_accounts = array_filter($accounts, fn($acc) => $acc['account_category'] === 'mobile_money');
    
} catch (PDOException $e) {
    $accounts = [];
    $bank_accounts = [];
    $mobile_accounts = [];
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // Validation
    if (empty($_POST['reservation_id'])) $errors[] = "Reservation is required";
    if (empty($_POST['payment_date'])) $errors[] = "Payment date is required";
    if (empty($_POST['amount'])) $errors[] = "Amount is required";
    if (empty($_POST['payment_method'])) $errors[] = "Payment method is required";
    
    $payment_method = $_POST['payment_method'] ?? '';
    
    // Payment method specific validation
    if ($payment_method === 'cash' && empty($_POST['received_by'])) {
        $errors[] = "Received by is required for cash payment";
    }
    if ($payment_method === 'cheque') {
        if (empty($_POST['cheque_number'])) $errors[] = "Cheque number is required";
        if (empty($_POST['cheque_bank'])) $errors[] = "Cheque bank is required";
        if (empty($_POST['cheque_date'])) $errors[] = "Cheque date is required";
    }
    
    if (empty($errors)) {
        try {
            $conn->beginTransaction();
            
            // Generate payment number
            $payment_year = date('Y', strtotime($_POST['payment_date']));
            $payment_count_sql = "SELECT COUNT(*) FROM payments WHERE company_id = ? AND YEAR(payment_date) = ?";
            $payment_count_stmt = $conn->prepare($payment_count_sql);
            $payment_count_stmt->execute([$company_id, $payment_year]);
            $payment_count = $payment_count_stmt->fetchColumn() + 1;
            $payment_number = 'PAY-' . $payment_year . '-' . str_pad($payment_count, 4, '0', STR_PAD_LEFT);
            
            // Get reservation details
            $res_sql = "SELECT reservation_number FROM reservations WHERE reservation_id = ? AND company_id = ?";
            $res_stmt = $conn->prepare($res_sql);
            $res_stmt->execute([$_POST['reservation_id'], $company_id]);
            $reservation = $res_stmt->fetch(PDO::FETCH_ASSOC);
            
            // Prepare payment method specific data
            $bank_name = null;
            $depositor_name = null;
            $deposit_bank = null;
            $deposit_account = null;
            $transfer_from_bank = null;
            $transfer_from_account = null;
            $mobile_money_provider = null;
            $mobile_money_number = null;
            $mobile_money_name = null;
            $to_account_id = !empty($_POST['to_account_id']) ? intval($_POST['to_account_id']) : null;
            $cash_transaction_id = null;
            $cheque_transaction_id = null;
            
            // DEBUG LOG
            error_log("SELECTED ACCOUNT ID: " . ($to_account_id ?? 'NULL'));
            
            switch ($payment_method) {
                case 'bank_transfer':
                    $transfer_from_bank = $_POST['client_bank_name'] ?? null;
                    $transfer_from_account = $_POST['client_account_number'] ?? null;
                    $bank_name = $_POST['client_account_name'] ?? null;
                    break;
                
                case 'bank_deposit':
                    $deposit_bank = $_POST['deposit_bank'] ?? null;
                    $deposit_account = $_POST['deposit_account'] ?? null;
                    $depositor_name = $_POST['depositor_name'] ?? null;
                    break;
                
                case 'mobile_money':
                    $mobile_money_provider = $_POST['mobile_money_provider'] ?? null;
                    $mobile_money_number = $_POST['mobile_money_number'] ?? null;
                    $mobile_money_name = $_POST['mobile_money_name'] ?? null;
                    break;
                
                case 'cash':
                    $cash_count_sql = "SELECT COUNT(*) FROM cash_transactions WHERE company_id = ? AND YEAR(transaction_date) = ?";
                    $cash_count_stmt = $conn->prepare($cash_count_sql);
                    $cash_count_stmt->execute([$company_id, $payment_year]);
                    $cash_count = $cash_count_stmt->fetchColumn() + 1;
                    $cash_number = 'CASH-' . $payment_year . '-' . str_pad($cash_count, 4, '0', STR_PAD_LEFT);
                    
                    $cash_sql = "INSERT INTO cash_transactions (company_id, transaction_date, transaction_number, amount, transaction_type, received_by, remarks, created_by) VALUES (?, ?, ?, ?, 'receipt', ?, ?, ?)";
                    $cash_stmt = $conn->prepare($cash_sql);
                    $cash_stmt->execute([
                        $company_id,
                        $_POST['payment_date'],
                        $cash_number,
                        $_POST['amount'],
                        $_POST['received_by'],
                        'Installment payment for reservation ' . $reservation['reservation_number'],
                        $_SESSION['user_id']
                    ]);
                    
                    $cash_transaction_id = $conn->lastInsertId();
                    break;
                
                case 'cheque':
                    $cheque_sql = "INSERT INTO cheque_transactions (company_id, cheque_number, cheque_date, bank_name, branch_name, amount, payee_name, status, remarks, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, 'pending', ?, ?)";
                    $cheque_stmt = $conn->prepare($cheque_sql);
                    $cheque_stmt->execute([
                        $company_id,
                        $_POST['cheque_number'],
                        $_POST['cheque_date'],
                        $_POST['cheque_bank'],
                        $_POST['cheque_branch'] ?? null,
                        $_POST['amount'],
                        $_POST['cheque_payee'] ?? null,
                        'Installment payment for reservation ' . $reservation['reservation_number'],
                        $_SESSION['user_id']
                    ]);
                    
                    $cheque_transaction_id = $conn->lastInsertId();
                    $bank_name = $_POST['cheque_bank'];
                    break;
            }
            
            // Insert payment record
            $payment_sql = "INSERT INTO payments (
                company_id, reservation_id, payment_date, payment_number, amount,
                payment_method, bank_name, transaction_reference,
                depositor_name, deposit_bank, deposit_account,
                transfer_from_bank, transfer_from_account,
                mobile_money_provider, mobile_money_number, mobile_money_name,
                to_account_id,
                cash_transaction_id, cheque_transaction_id,
                remarks, status, payment_type,
                submitted_by, submitted_at, created_by, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending_approval', 'installment', ?, NOW(), ?, NOW())";
            
            $payment_stmt = $conn->prepare($payment_sql);
            $payment_stmt->execute([
                $company_id,
                $_POST['reservation_id'],
                $_POST['payment_date'],
                $payment_number,
                $_POST['amount'],
                $payment_method,
                $bank_name,
                $_POST['transaction_reference'] ?? null,
                $depositor_name,
                $deposit_bank,
                $deposit_account,
                $transfer_from_bank,
                $transfer_from_account,
                $mobile_money_provider,
                $mobile_money_number,
                $mobile_money_name,
                $to_account_id,
                $cash_transaction_id,
                $cheque_transaction_id,
                $_POST['remarks'] ?? 'Installment payment for reservation ' . $reservation['reservation_number'],
                $_SESSION['user_id'],
                $_SESSION['user_id']
            ]);
            
            $payment_id = $conn->lastInsertId();
            
            // DEBUG LOG
            error_log("PAYMENT INSERTED WITH ID: $payment_id AND ACCOUNT: " . ($to_account_id ?? 'NULL'));
            
            // Update cash/cheque transaction with payment_id
            if ($cash_transaction_id) {
                $update_cash = $conn->prepare("UPDATE cash_transactions SET payment_id = ? WHERE cash_transaction_id = ?");
                $update_cash->execute([$payment_id, $cash_transaction_id]);
            }
            
            if ($cheque_transaction_id) {
                $update_cheque = $conn->prepare("UPDATE cheque_transactions SET payment_id = ? WHERE cheque_transaction_id = ?");
                $update_cheque->execute([$payment_id, $cheque_transaction_id]);
            }
            
            $conn->commit();
            
            $_SESSION['success'] = "Payment recorded successfully! Payment Number: <strong>" . $payment_number . "</strong>. The payment is pending manager approval. Once approved, the amount will be added to the selected account and plot status will be updated if fully paid.";
            header("Location: record.php");
            exit;
            
        } catch (PDOException $e) {
            $conn->rollBack();
            $errors[] = "Database error: " . $e->getMessage();
            error_log("DATABASE ERROR: " . $e->getMessage());
        }
    }
}

// Handle session success message
if (isset($_SESSION['success'])) {
    $success = $_SESSION['success'];
    unset($_SESSION['success']);
}

$page_title = 'Record Payment';
require_once '../../includes/header.php';
?>

<style>
.reservation-card{background:white;border-radius:10px;padding:20px;margin-bottom:15px;box-shadow:0 2px 8px rgba(0,0,0,0.08);border-left:4px solid #007bff;cursor:pointer;transition:all 0.3s}
.reservation-card:hover{transform:translateX(5px);box-shadow:0 4px 12px rgba(0,0,0,0.12)}
.reservation-card.selected{border-left-color:#28a745;background:#f0fff4}
.payment-form{background:white;border-radius:10px;padding:25px;box-shadow:0 2px 12px rgba(0,0,0,0.1)}
.form-section{margin-bottom:25px;padding-bottom:20px;border-bottom:2px solid #e9ecef}
.form-section:last-child{border-bottom:none}
.form-section-title{font-size:16px;font-weight:700;color:#333;margin-bottom:15px;display:flex;align-items:center}
.payment-method-fields{display:none;margin-top:15px;padding:15px;background:#f8f9fa;border-radius:8px;border-left:4px solid #007bff}
.payment-method-fields.active{display:block}
.client-bank-section{background:#e7f3ff;padding:15px;border-radius:8px;border:1px solid #b3d9ff;margin-bottom:15px}
.company-account-section{background:#e8f5e9;padding:15px;border-radius:8px;border:1px solid #a5d6a7}
</style>

<div class="content-header">
<div class="container-fluid">
<div class="row mb-2">
<div class="col-sm-6"><h1><i class="fas fa-money-bill-wave"></i> Record Payment</h1></div>
<div class="col-sm-6"><ol class="breadcrumb float-sm-end"><li class="breadcrumb-item"><a href="../../dashboard.php">Home</a></li><li class="breadcrumb-item"><a href="index.php">Payments</a></li><li class="breadcrumb-item active">Record</li></ol></div>
</div>
</div>
</div>

<section class="content">
<div class="container-fluid">

<?php if (!empty($errors)): ?>
<div class="alert alert-danger alert-dismissible">
<button type="button" class="btn-close" data-bs-dismiss="alert"></button>
<h5><i class="fas fa-ban"></i> Errors!</h5>
<ul class="mb-0">
<?php foreach ($errors as $error): ?>
<li><?php echo htmlspecialchars($error); ?></li>
<?php endforeach; ?>
</ul>
</div>
<?php endif; ?>

<?php if ($success): ?>
<div class="alert alert-success alert-dismissible">
<button type="button" class="btn-close" data-bs-dismiss="alert"></button>
<i class="fas fa-check-circle"></i> <?php echo $success; ?>
</div>
<?php endif; ?>

<div class="alert alert-info">
<i class="fas fa-info-circle"></i>
<strong>Note:</strong> All payments require manager approval. Once approved, amounts will be added to selected accounts and plot status will automatically update to SOLD when fully paid (100%). Only ACTIVE reservations are shown here - pending reservations must be approved first in the Pending Approvals section.
</div>

<div class="row">
<div class="col-md-4">
<h5 class="mb-3"><i class="fas fa-list"></i> Select Reservation</h5>

<?php if (empty($reservations)): ?>
<div class="alert alert-warning">
<i class="fas fa-exclamation-triangle"></i> No active reservations with balance found.
<hr>
<p class="mb-0"><strong>Tip:</strong> If you just created a reservation, it needs to be <strong>approved first</strong> in the <a href="../approvals/pending.php">Pending Approvals</a> section before you can record additional payments.</p>
</div>
<?php else: ?>
<?php foreach ($reservations as $res): 
$percentage = ($res['total_paid'] / $res['total_amount']) * 100;
?>
<div class="reservation-card" onclick="selectReservation(<?php echo htmlspecialchars(json_encode($res)); ?>)">
<h6 class="mb-2"><strong><?php echo htmlspecialchars($res['reservation_number']); ?></strong></h6>
<p class="mb-1 small"><i class="fas fa-user"></i> <?php echo htmlspecialchars($res['customer_name']); ?></p>
<p class="mb-1 small"><i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($res['project_name']); ?> - Plot <?php echo htmlspecialchars($res['plot_number']); ?></p>
<div class="progress mb-2" style="height:20px">
<div class="progress-bar bg-success" style="width:<?php echo $percentage; ?>%"><?php echo number_format($percentage, 1); ?>%</div>
</div>
<p class="mb-0 small"><strong>Balance:</strong> TZS <?php echo number_format($res['balance'], 2); ?></p>
<p class="mb-0 small"><strong>Installment:</strong> TZS <?php echo number_format($res['installment_amount'], 2); ?></p>
</div>
<?php endforeach; ?>
<?php endif; ?>

</div>

<div class="col-md-8">
<div class="payment-form">
<h5 class="mb-4"><i class="fas fa-money-check-alt"></i> Payment Details</h5>

<form method="POST" id="paymentForm">
<input type="hidden" name="reservation_id" id="reservation_id" required>
<!-- HIDDEN FIELD TO STORE SELECTED ACCOUNT -->
<input type="hidden" name="to_account_id" id="hidden_to_account_id" value="">

<div class="form-section">
<div class="form-section-title"><i class="fas fa-info-circle me-2"></i>Selected Reservation</div>
<div id="selectedReservationInfo">
<p class="text-muted">Please select a reservation from the list</p>
</div>
</div>

<div class="form-section">
<div class="form-section-title"><i class="fas fa-calendar me-2"></i>Payment Information</div>
<div class="row">
<div class="col-md-6 mb-3">
<label class="form-label">Payment Date<span class="text-danger">*</span></label>
<input type="date" name="payment_date" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
</div>
<div class="col-md-6 mb-3">
<label class="form-label">Amount (TZS)<span class="text-danger">*</span></label>
<input type="number" name="amount" id="amount" class="form-control" step="0.01" required>
</div>
<div class="col-md-12 mb-3">
<label class="form-label">Payment Method<span class="text-danger">*</span></label>
<select name="payment_method" id="payment_method" class="form-control" required>
<option value="">-- Select Method --</option>
<option value="cash">Cash</option>
<option value="bank_transfer">Bank Transfer</option>
<option value="bank_deposit">Bank Deposit</option>
<option value="mobile_money">Mobile Money</option>
<option value="cheque">Cheque</option>
</select>
</div>
<div class="col-md-12 mb-3">
<label class="form-label">Transaction Reference</label>
<input type="text" name="transaction_reference" class="form-control" placeholder="Optional">
</div>
</div>
</div>

<!-- Cash Fields -->
<div id="cash_fields" class="payment-method-fields">
<h6 class="mb-3"><i class="fas fa-money-bill-wave me-2"></i> Cash Payment Details</h6>
<div class="row">
<div class="col-md-12">
<div class="form-group">
<label>Received By<span class="text-danger">*</span></label>
<input type="text" name="received_by" class="form-control" placeholder="Name of person receiving cash">
</div>
</div>
</div>
</div>

<!-- Bank Transfer -->
<div id="transfer_fields" class="payment-method-fields">
<h6 class="mb-3"><i class="fas fa-exchange-alt me-2"></i> Bank Transfer Details</h6>

<!-- Client Bank Details -->
<div class="client-bank-section">
<h6 class="mb-3 text-primary"><i class="fas fa-user me-2"></i>Client Bank Account (From)</h6>
<div class="row">
<div class="col-md-4">
<div class="form-group">
<label>Bank Name</label>
<input type="text" name="client_bank_name" class="form-control" placeholder="e.g., CRDB Bank">
</div>
</div>
<div class="col-md-4">
<div class="form-group">
<label>Account Number</label>
<input type="text" name="client_account_number" class="form-control" placeholder="e.g., 0150123456789">
</div>
</div>
<div class="col-md-4">
<div class="form-group">
<label>Account Name</label>
<input type="text" name="client_account_name" class="form-control" placeholder="Account holder name">
</div>
</div>
</div>
</div>

<!-- Company Bank Account -->
<div class="company-account-section">
<h6 class="mb-3 text-success"><i class="fas fa-building me-2"></i>Company Bank Account (To)</h6>
<div class="row">
<div class="col-md-12">
<div class="form-group">
<label>Receive To Account</label>
<select id="transfer_to_account" class="form-control account-selector">
<option value="">-- Select Company Bank Account (Optional) --</option>
<?php foreach ($bank_accounts as $account): ?>
<option value="<?php echo $account['bank_account_id']; ?>">
<?php echo htmlspecialchars($account['bank_name'] . ' - ' . $account['account_name']); ?>
| A/C: <?php echo htmlspecialchars($account['account_number']); ?>
| Balance: TZS <?php echo number_format($account['current_balance'], 2); ?>
</option>
<?php endforeach; ?>
</select>
<small class="form-text text-muted">Payment amount will be added to this account upon approval (if selected)</small>
</div>
</div>
</div>
</div>
</div>

<!-- Bank Deposit -->
<div id="deposit_fields" class="payment-method-fields">
<h6 class="mb-3"><i class="fas fa-building me-2"></i> Bank Deposit Details</h6>
<div class="row">
<div class="col-md-12">
<div class="form-group">
<label>Deposit To Account (Company)</label>
<select id="deposit_to_account" class="form-control account-selector">
<option value="">-- Select Company Bank Account (Optional) --</option>
<?php foreach ($bank_accounts as $account): ?>
<option value="<?php echo $account['bank_account_id']; ?>">
<?php echo htmlspecialchars($account['bank_name'] . ' - ' . $account['account_name']); ?>
| A/C: <?php echo htmlspecialchars($account['account_number']); ?>
| Balance: TZS <?php echo number_format($account['current_balance'], 2); ?>
</option>
<?php endforeach; ?>
</select>
<small class="form-text text-muted">Payment amount will be added to this account upon approval (if selected)</small>
</div>
</div>
<div class="col-md-6">
<div class="form-group">
<label>Depositor Name</label>
<input type="text" name="depositor_name" class="form-control" placeholder="Name of person making deposit">
</div>
</div>
<div class="col-md-6">
<div class="form-group">
<label>Deposit Bank</label>
<input type="text" name="deposit_bank" class="form-control" placeholder="e.g., CRDB Bank">
</div>
</div>
<div class="col-md-12">
<div class="form-group">
<label>Deposit Slip Number</label>
<input type="text" name="deposit_account" class="form-control" placeholder="Deposit slip reference number">
</div>
</div>
</div>
</div>

<!-- Mobile Money -->
<div id="mobile_money_fields" class="payment-method-fields">
<h6 class="mb-3"><i class="fas fa-mobile-alt me-2"></i> Mobile Money Details</h6>
<div class="row">
<div class="col-md-4">
<div class="form-group">
<label>Provider</label>
<select name="mobile_money_provider" class="form-control">
<option value="">-- Select Provider --</option>
<option value="M-Pesa">M-Pesa (Vodacom)</option>
<option value="Tigo Pesa">Tigo Pesa</option>
<option value="Airtel Money">Airtel Money</option>
<option value="Halopesa">Halopesa (Halotel)</option>
<option value="T-Pesa">T-Pesa (TTCL)</option>
</select>
</div>
</div>
<div class="col-md-4">
<div class="form-group">
<label>Mobile Money Number</label>
<input type="text" name="mobile_money_number" class="form-control" placeholder="e.g., 0755123456">
</div>
</div>
<div class="col-md-4">
<div class="form-group">
<label>Account Holder Name</label>
<input type="text" name="mobile_money_name" class="form-control" placeholder="Name registered on mobile money">
</div>
</div>
<div class="col-md-12">
<div class="form-group">
<label>Receive To Account (Company)</label>
<select id="mobile_to_account" class="form-control account-selector">
<option value="">-- Select Company Mobile Money Account (Optional) --</option>
<?php foreach ($mobile_accounts as $account): ?>
<option value="<?php echo $account['bank_account_id']; ?>">
<?php echo htmlspecialchars($account['mobile_provider'] . ' - ' . $account['account_name']); ?>
| <?php echo htmlspecialchars($account['mobile_number']); ?>
| Balance: TZS <?php echo number_format($account['current_balance'], 2); ?>
</option>
<?php endforeach; ?>
</select>
<small class="form-text text-muted">Payment amount will be added to this account upon approval (if selected)</small>
</div>
</div>
</div>
</div>

<!-- Cheque -->
<div id="cheque_fields" class="payment-method-fields">
<h6 class="mb-3"><i class="fas fa-money-check me-2"></i> Cheque Details</h6>
<div class="row">
<div class="col-md-4">
<div class="form-group">
<label>Cheque Number<span class="text-danger">*</span></label>
<input type="text" name="cheque_number" class="form-control" placeholder="e.g., 123456">
</div>
</div>
<div class="col-md-4">
<div class="form-group">
<label>Cheque Date<span class="text-danger">*</span></label>
<input type="date" name="cheque_date" class="form-control">
</div>
</div>
<div class="col-md-4">
<div class="form-group">
<label>Cheque Bank<span class="text-danger">*</span></label>
<input type="text" name="cheque_bank" class="form-control" placeholder="e.g., CRDB Bank">
</div>
</div>
<div class="col-md-6">
<div class="form-group">
<label>Branch Name</label>
<input type="text" name="cheque_branch" class="form-control" placeholder="Bank branch">
</div>
</div>
<div class="col-md-6">
<div class="form-group">
<label>Payee Name</label>
<input type="text" name="cheque_payee" class="form-control" placeholder="Name on cheque">
</div>
</div>
</div>
</div>

<div class="form-section">
<label class="form-label">Remarks</label>
<textarea name="remarks" class="form-control" rows="3"></textarea>
</div>

<button type="submit" class="btn btn-primary btn-lg">
<i class="fas fa-check"></i> Submit Payment for Approval
</button>
</form>

</div>
</div>

</div>

</div>
</section>

<script>
// CRITICAL FIX: Sync all account selectors to hidden field
document.addEventListener('DOMContentLoaded', function() {
    const accountSelectors = document.querySelectorAll('.account-selector');
    accountSelectors.forEach(function(selector) {
        selector.addEventListener('change', function() {
            const selectedAccountId = this.value;
            document.getElementById('hidden_to_account_id').value = selectedAccountId;
            console.log('Account selected:', selectedAccountId);
        });
    });
    
    // Before form submit, make sure hidden field has value
    document.getElementById('paymentForm').addEventListener('submit', function(e) {
        const accountId = document.getElementById('hidden_to_account_id').value;
        console.log('Submitting with account ID:', accountId);
    });
});

function selectReservation(res) {
    document.getElementById('reservation_id').value = res.reservation_id;
    document.getElementById('amount').value = res.installment_amount;
    
    const info = `
        <div class="alert alert-success">
        <h6><strong>${res.reservation_number}</strong></h6>
        <p class="mb-1"><strong>Customer:</strong> ${res.customer_name}</p>
        <p class="mb-1"><strong>Plot:</strong> ${res.project_name} - Plot ${res.plot_number}</p>
        <p class="mb-1"><strong>Total Amount:</strong> TZS ${parseFloat(res.total_amount).toLocaleString('en-US', {minimumFractionDigits: 2})}</p>
        <p class="mb-1"><strong>Paid:</strong> TZS ${parseFloat(res.total_paid).toLocaleString('en-US', {minimumFractionDigits: 2})}</p>
        <p class="mb-0"><strong>Balance:</strong> TZS ${parseFloat(res.balance).toLocaleString('en-US', {minimumFractionDigits: 2})}</p>
        </div>
    `;
    
    document.getElementById('selectedReservationInfo').innerHTML = info;
    
    document.querySelectorAll('.reservation-card').forEach(card => card.classList.remove('selected'));
    event.currentTarget.classList.add('selected');
}

document.getElementById('payment_method').addEventListener('change', function() {
    document.querySelectorAll('.payment-method-fields').forEach(field => field.classList.remove('active'));
    
    // Clear hidden account field when switching methods
    document.getElementById('hidden_to_account_id').value = '';
    document.querySelectorAll('.account-selector').forEach(s => s.value = '');
    
    const method = this.value;
    if (method === 'cash') document.getElementById('cash_fields').classList.add('active');
    if (method === 'bank_transfer') document.getElementById('transfer_fields').classList.add('active');
    if (method === 'bank_deposit') document.getElementById('deposit_fields').classList.add('active');
    if (method === 'mobile_money') document.getElementById('mobile_money_fields').classList.add('active');
    if (method === 'cheque') document.getElementById('cheque_fields').classList.add('active');
});
</script>

<?php require_once '../../includes/footer.php'; ?>