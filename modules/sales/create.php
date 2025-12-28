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

// AJAX: Get plots by project
if (isset($_GET['action']) && $_GET['action'] === 'get_plots' && isset($_GET['project_id'])) {
    $project_id = intval($_GET['project_id']);
    
    try {
        $plots_sql = "SELECT plot_id, plot_number, block_number, area, 
                             price_per_sqm, selling_price, discount_amount,
                             (selling_price - COALESCE(discount_amount, 0)) as final_price,
                             status
                      FROM plots
                      WHERE project_id = ? AND company_id = ? AND status = 'available'
                      AND NOT EXISTS (
                          SELECT 1 FROM reservations r 
                          WHERE r.plot_id = plots.plot_id AND r.company_id = plots.company_id
                          AND r.status IN ('active', 'pending_approval', 'completed')
                      )
                      ORDER BY CAST(plot_number AS UNSIGNED), block_number";
        
        $plots_stmt = $conn->prepare($plots_sql);
        $plots_stmt->execute([$project_id, $company_id]);
        $plots = $plots_stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $available_plots = array_filter($plots, function($plot) {
            return $plot['status'] === 'available';
        });
        
        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'plots' => array_values($available_plots)]);
        exit;
    } catch (PDOException $e) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        exit;
    }
}

// AJAX: Get plot details
if (isset($_GET['action']) && $_GET['action'] === 'get_plot_details' && isset($_GET['plot_id'])) {
    $plot_id = intval($_GET['plot_id']);
    
    try {
        $plot_sql = "SELECT p.*, pr.project_name 
                     FROM plots p
                     JOIN projects pr ON p.project_id = pr.project_id
                     WHERE p.plot_id = ? AND p.company_id = ?";
        $plot_stmt = $conn->prepare($plot_sql);
        $plot_stmt->execute([$plot_id, $company_id]);
        $plot = $plot_stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($plot) {
            if ($plot['status'] !== 'available') {
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => false, 
                    'error' => 'This plot is currently ' . $plot['status'] . ' and cannot be reserved.',
                    'status' => $plot['status']
                ]);
                exit;
            }
            
            $check_reservation = $conn->prepare("
                SELECT COUNT(*) FROM reservations 
                WHERE plot_id = ? AND company_id = ? 
                AND status IN ('active', 'pending_approval', 'completed')
            ");
            $check_reservation->execute([$plot_id, $company_id]);
            $has_reservation = $check_reservation->fetchColumn();
            
            if ($has_reservation > 0) {
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => false, 
                    'error' => 'This plot already has an active reservation.',
                    'status' => 'reserved'
                ]);
                exit;
            }
        }
        
        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'plot' => $plot]);
        exit;
    } catch (PDOException $e) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        exit;
    }
}

// Fetch customers
try {
    $customers_sql = "SELECT customer_id, full_name, phone, email 
                      FROM customers 
                      WHERE company_id = ? AND is_active = 1 
                      ORDER BY full_name";
    $customers_stmt = $conn->prepare($customers_sql);
    $customers_stmt->execute([$company_id]);
    $customers = $customers_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $customers = [];
}

// Fetch projects
try {
    $projects_sql = "SELECT project_id, project_name, 
                            COALESCE(physical_location, '') as location,
                            available_plots
                     FROM projects 
                     WHERE company_id = ? AND is_active = 1 
                     ORDER BY project_name";
    $projects_stmt = $conn->prepare($projects_sql);
    $projects_stmt->execute([$company_id]);
    $projects = $projects_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $projects = [];
}

// Fetch active company accounts (both bank and mobile money)
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
    
    // Separate accounts by type
    $bank_accounts = array_filter($accounts, function($acc) { return $acc['account_category'] === 'bank'; });
    $mobile_accounts = array_filter($accounts, function($acc) { return $acc['account_category'] === 'mobile_money'; });
    
} catch (PDOException $e) {
    $accounts = [];
    $bank_accounts = [];
    $mobile_accounts = [];
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // DEBUG: Let's see what's being posted
    error_log("POST DATA: " . print_r($_POST, true));
    
    // Basic validation - only essential fields
    if (empty($_POST['customer_id'])) $errors[] = "Customer is required";
    if (empty($_POST['plot_id'])) $errors[] = "Plot is required";
    if (empty($_POST['reservation_date'])) $errors[] = "Reservation date is required";
    if (empty($_POST['total_amount'])) $errors[] = "Total amount is required";
    if (empty($_POST['down_payment'])) $errors[] = "Down payment is required";
    if (empty($_POST['payment_method'])) $errors[] = "Payment method is required";

    $payment_method = $_POST['payment_method'] ?? '';
    
    // Minimal validation - only check essential fields for each payment method
    if ($payment_method === 'cash') {
        if (empty($_POST['received_by'])) $errors[] = "Received by is required for cash payment";
    } elseif ($payment_method === 'cheque') {
        if (empty($_POST['cheque_number'])) $errors[] = "Cheque number is required";
        if (empty($_POST['cheque_bank'])) $errors[] = "Cheque bank is required";
        if (empty($_POST['cheque_date'])) $errors[] = "Cheque date is required";
    }

    // STRICT PLOT AVAILABILITY CHECK
    if (!empty($_POST['plot_id'])) {
        $check_plot_sql = "SELECT status FROM plots WHERE plot_id = ? AND company_id = ?";
        $check_plot_stmt = $conn->prepare($check_plot_sql);
        $check_plot_stmt->execute([$_POST['plot_id'], $company_id]);
        $plot_status = $check_plot_stmt->fetchColumn();
        
        if ($plot_status !== 'available') {
            $errors[] = "Selected plot is not available. Current status: " . ucfirst($plot_status);
        }
        
        $check_reservation_sql = "SELECT COUNT(*) FROM reservations 
                                  WHERE plot_id = ? AND company_id = ? 
                                  AND status IN ('active', 'pending_approval', 'completed')";
        $check_reservation_stmt = $conn->prepare($check_reservation_sql);
        $check_reservation_stmt->execute([$_POST['plot_id'], $company_id]);
        $has_reservation = $check_reservation_stmt->fetchColumn();
        
        if ($has_reservation > 0) {
            $errors[] = "This plot already has an active reservation. Please select another plot.";
        }
    }

    if (empty($errors)) {
        try {
            $conn->beginTransaction();

            // Generate reservation number
            $year = date('Y');
            $count_sql = "SELECT COUNT(*) FROM reservations WHERE company_id = ? AND YEAR(reservation_date) = ?";
            $count_stmt = $conn->prepare($count_sql);
            $count_stmt->execute([$company_id, $year]);
            $count = $count_stmt->fetchColumn() + 1;
            $reservation_number = 'RES-' . $year . '-' . str_pad($count, 4, '0', STR_PAD_LEFT);

            // Calculate amounts
            $payment_periods = intval($_POST['payment_periods'] ?? 20);
            $total_amount = floatval($_POST['total_amount']);
            $down_payment = floatval($_POST['down_payment']);
            $remaining_balance = $total_amount - $down_payment;
            $installment_amount = $payment_periods > 0 ? ($remaining_balance / $payment_periods) : 0;

            // Insert reservation
            $sql = "INSERT INTO reservations (
                company_id, customer_id, plot_id, reservation_date, reservation_number,
                total_amount, down_payment, payment_periods, installment_amount,
                discount_percentage, discount_amount, title_holder_name, 
                status, created_by, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending_approval', ?, NOW())";

            $stmt = $conn->prepare($sql);
            $stmt->execute([
                $company_id, 
                $_POST['customer_id'], 
                $_POST['plot_id'], 
                $_POST['reservation_date'],
                $reservation_number, 
                $total_amount, 
                $down_payment, 
                $payment_periods,
                $installment_amount, 
                $_POST['discount_percentage'] ?? 0,
                $_POST['discount_amount'] ?? 0, 
                $_POST['title_holder_name'] ?? null,
                $_SESSION['user_id']
            ]);

            $reservation_id = $conn->lastInsertId();

            // Create payment record
            if ($down_payment > 0) {
                $payment_year = date('Y', strtotime($_POST['payment_date']));
                $payment_count_sql = "SELECT COUNT(*) FROM payments 
                                     WHERE company_id = ? AND YEAR(payment_date) = ?";
                $payment_count_stmt = $conn->prepare($payment_count_sql);
                $payment_count_stmt->execute([$company_id, $payment_year]);
                $payment_count = $payment_count_stmt->fetchColumn() + 1;
                $payment_number = 'PAY-' . $payment_year . '-' . str_pad($payment_count, 4, '0', STR_PAD_LEFT);

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
                        $cash_count_sql = "SELECT COUNT(*) FROM cash_transactions 
                                          WHERE company_id = ? AND YEAR(transaction_date) = ?";
                        $cash_count_stmt = $conn->prepare($cash_count_sql);
                        $cash_count_stmt->execute([$company_id, $payment_year]);
                        $cash_count = $cash_count_stmt->fetchColumn() + 1;
                        $cash_number = 'CASH-' . $payment_year . '-' . str_pad($cash_count, 4, '0', STR_PAD_LEFT);
                        
                        $cash_sql = "INSERT INTO cash_transactions (
                            company_id, transaction_date, transaction_number, amount,
                            transaction_type, received_by, remarks, created_by
                        ) VALUES (?, ?, ?, ?, 'receipt', ?, ?, ?)";
                        
                        $cash_stmt = $conn->prepare($cash_sql);
                        $cash_stmt->execute([
                            $company_id,
                            $_POST['payment_date'],
                            $cash_number,
                            $down_payment,
                            $_POST['received_by'],
                            'Down payment for reservation ' . $reservation_number,
                            $_SESSION['user_id']
                        ]);
                        
                        $cash_transaction_id = $conn->lastInsertId();
                        break;
                    
                    case 'cheque':
                        $cheque_sql = "INSERT INTO cheque_transactions (
                            company_id, cheque_number, cheque_date, bank_name,
                            branch_name, amount, payee_name, status, remarks, created_by
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, 'pending', ?, ?)";
                        
                        $cheque_stmt = $conn->prepare($cheque_sql);
                        $cheque_stmt->execute([
                            $company_id,
                            $_POST['cheque_number'],
                            $_POST['cheque_date'],
                            $_POST['cheque_bank'],
                            $_POST['cheque_branch'] ?? null,
                            $down_payment,
                            $_POST['cheque_payee'] ?? null,
                            'Down payment for reservation ' . $reservation_number,
                            $_SESSION['user_id']
                        ]);
                        
                        $cheque_transaction_id = $conn->lastInsertId();
                        $bank_name = $_POST['cheque_bank'];
                        break;
                }

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
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending_approval', 'down_payment', ?, NOW(), ?, NOW())";

                $payment_stmt = $conn->prepare($payment_sql);
                $payment_stmt->execute([
                    $company_id, 
                    $reservation_id, 
                    $_POST['payment_date'], 
                    $payment_number,
                    $down_payment, 
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
                    'Down payment for reservation ' . $reservation_number,
                    $_SESSION['user_id'],
                    $_SESSION['user_id']
                ]);

                $payment_id = $conn->lastInsertId();
                
                // DEBUG LOG
                error_log("PAYMENT INSERTED WITH ID: $payment_id AND ACCOUNT: " . ($to_account_id ?? 'NULL'));
                
                if ($cash_transaction_id) {
                    $update_cash = $conn->prepare("UPDATE cash_transactions SET payment_id = ? WHERE cash_transaction_id = ?");
                    $update_cash->execute([$payment_id, $cash_transaction_id]);
                }
                
                if ($cheque_transaction_id) {
                    $update_cheque = $conn->prepare("UPDATE cheque_transactions SET payment_id = ? WHERE cheque_transaction_id = ?");
                    $update_cheque->execute([$payment_id, $cheque_transaction_id]);
                }
            }

            $conn->commit();
            
            $_SESSION['success'] = "Reservation created successfully! Reservation Number: <strong>" . $reservation_number . "</strong>. The reservation and down payment are now pending manager approval. Once approved, the plot status will be updated and amounts will be added to the selected account.";
            header("Location: index.php");
            exit;
            
        } catch (PDOException $e) {
            $conn->rollBack();
            $errors[] = "Database error: " . $e->getMessage();
            error_log("DATABASE ERROR: " . $e->getMessage());
        }
    }
}

$page_title = 'New Reservation';
ob_start();
require_once '../../includes/header.php';
$header_content = ob_get_clean();

if (strpos($header_content, 'jquery') === false) {
    echo '<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>';
}
echo $header_content;

if (strpos($header_content, 'select2') === false) {
    echo '<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />';
}
?>

<style>
.step-card {
    background: white;
    border-radius: 10px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.08);
    padding: 25px;
    margin-bottom: 20px;
    border-left: 4px solid #667eea;
}

.step-card .step-number {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 35px;
    height: 35px;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    border-radius: 50%;
    font-weight: bold;
    margin-right: 10px;
}

.step-card .step-title {
    font-size: 18px;
    font-weight: 600;
    color: #333;
    margin-bottom: 15px;
    display: flex;
    align-items: center;
}

.form-group label {
    font-weight: 500;
    color: #495057;
    margin-bottom: 8px;
}

.form-control, .form-select {
    border-radius: 6px;
    border: 1px solid #dee2e6;
    padding: 10px 15px;
    font-size: 14px;
}

.form-control:focus, .form-select:focus {
    border-color: #667eea;
    box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
}

.plot-info-card {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 20px;
    border-radius: 10px;
    margin-top: 20px;
}

.plot-info-card .info-row {
    display: flex;
    justify-content: space-between;
    padding: 10px 0;
    border-bottom: 1px solid rgba(255,255,255,0.2);
}

.plot-info-card .info-row:last-child {
    border-bottom: none;
}

.plot-info-card .info-label {
    font-size: 13px;
    opacity: 0.9;
}

.plot-info-card .info-value {
    font-size: 16px;
    font-weight: 600;
}

.calculation-summary {
    background: #f8f9fa;
    border: 2px solid #e9ecef;
    border-radius: 10px;
    padding: 20px;
    margin-top: 20px;
}

.calculation-row {
    display: flex;
    justify-content: space-between;
    padding: 12px 0;
    font-size: 15px;
    border-bottom: 1px solid #dee2e6;
}

.calculation-row:last-child {
    border-bottom: none;
    font-size: 18px;
    font-weight: 700;
    color: #28a745;
    padding-top: 15px;
    margin-top: 10px;
    border-top: 2px solid #28a745;
}

.required {
    color: #dc3545;
    margin-left: 3px;
}

.btn-submit {
    background: linear-gradient(135deg, #28a745 0%, #218838 100%);
    border: none;
    padding: 12px 40px;
    font-size: 16px;
    font-weight: 600;
    border-radius: 8px;
    color: white;
    transition: all 0.3s;
}

.btn-submit:hover {
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(40, 167, 69, 0.3);
}

.alert-pending {
    background: #fff3cd;
    border: 2px solid #ffc107;
    border-left: 5px solid #ffc107;
    color: #856404;
}

.payment-method-fields {
    display: none;
    margin-top: 15px;
    padding: 15px;
    background: #f8f9fa;
    border-radius: 8px;
    border-left: 4px solid #007bff;
}

.payment-method-fields.active {
    display: block;
}

.client-bank-section {
    background: #e7f3ff;
    padding: 15px;
    border-radius: 8px;
    border: 1px solid #b3d9ff;
    margin-bottom: 15px;
}

.company-account-section {
    background: #e8f5e9;
    padding: 15px;
    border-radius: 8px;
    border: 1px solid #a5d6a7;
}
</style>

<div class="content-header">
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-6">
                <h1><i class="fas fa-plus-circle"></i> New Reservation</h1>
            </div>
            <div class="col-sm-6">
                <ol class="breadcrumb float-sm-right">
                    <li class="breadcrumb-item"><a href="../../dashboard.php">Home</a></li>
                    <li class="breadcrumb-item"><a href="index.php">Reservations</a></li>
                    <li class="breadcrumb-item active">New</li>
                </ol>
            </div>
        </div>
    </div>
</div>

<section class="content">
    <div class="container-fluid">

        <?php if (!empty($errors)): ?>
        <div class="alert alert-danger alert-dismissible">
            <button type="button" class="close" data-dismiss="alert">&times;</button>
            <h5><i class="fas fa-ban"></i> Errors!</h5>
            <ul class="mb-0">
                <?php foreach ($errors as $error): ?>
                    <li><?php echo htmlspecialchars($error); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php endif; ?>

        <div class="alert alert-pending">
            <i class="fas fa-info-circle"></i>
            <strong>Approval Process:</strong> All new reservations and down payments require manager approval before activation. 
            Once approved, the plot status will automatically update (available → reserved → sold) and payment amounts will be added to the selected company account.
        </div>

        <form method="POST" id="reservationForm">
            
            <!-- HIDDEN FIELD TO STORE SELECTED ACCOUNT -->
            <input type="hidden" name="to_account_id" id="hidden_to_account_id" value="">
            
            <!-- STEP 1: Customer & Plot Selection -->
            <div class="step-card">
                <div class="step-title">
                    <span class="step-number">1</span>
                    <span>Customer & Plot Selection</span>
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Select Customer<span class="required">*</span></label>
                            <select name="customer_id" id="customer_id" class="form-control select2" required>
                                <option value="">-- Choose Customer --</option>
                                <?php foreach ($customers as $customer): ?>
                                    <option value="<?php echo $customer['customer_id']; ?>">
                                        <?php echo htmlspecialchars($customer['full_name']); ?>
                                        <?php if ($customer['phone']): ?>
                                            (<?php echo htmlspecialchars($customer['phone']); ?>)
                                        <?php endif; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Select Project<span class="required">*</span></label>
                            <select name="project_id" id="project_id" class="form-control select2" required>
                                <option value="">-- Choose Project --</option>
                                <?php foreach ($projects as $project): ?>
                                    <option value="<?php echo $project['project_id']; ?>">
                                        <?php echo htmlspecialchars($project['project_name']); ?>
                                        (<?php echo $project['available_plots']; ?> plots available)
                                        <?php if ($project['location']): ?>
                                            - <?php echo htmlspecialchars($project['location']); ?>
                                        <?php endif; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="col-md-12">
                        <div class="form-group">
                            <label>Select Plot<span class="required">*</span></label>
                            <select name="plot_id" id="plot_id" class="form-control select2" required disabled>
                                <option value="">-- First Select a Project --</option>
                            </select>
                            <small id="plotsLoading" style="display: none; color: #17a2b8;">
                                <i class="fas fa-spinner fa-spin"></i> Loading available plots only...
                            </small>
                        </div>
                    </div>
                </div>

                <div id="plotInfoCard" class="plot-info-card" style="display: none;">
                    <h5 style="margin-bottom: 15px;"><i class="fas fa-map-marked-alt"></i> Selected Plot Information</h5>
                    <div class="info-row">
                        <span class="info-label">Plot Number:</span>
                        <span class="info-value" id="plotNumber">-</span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Block:</span>
                        <span class="info-value" id="blockNumber">-</span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Area:</span>
                        <span class="info-value" id="plotArea">-</span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Price per m²:</span>
                        <span class="info-value" id="pricePerSqm">-</span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Total Price:</span>
                        <span class="info-value" id="plotTotalPrice">-</span>
                    </div>
                </div>
            </div>

            <!-- STEP 2: Reservation Details -->
            <div class="step-card">
                <div class="step-title">
                    <span class="step-number">2</span>
                    <span>Reservation Details</span>
                </div>

                <div class="row">
                    <div class="col-md-4">
                        <div class="form-group">
                            <label>Reservation Date<span class="required">*</span></label>
                            <input type="date" name="reservation_date" class="form-control" 
                                   value="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label>Total Amount (TZS)<span class="required">*</span></label>
                            <input type="number" name="total_amount" id="total_amount" 
                                   class="form-control" step="0.01" required>
                            <small class="form-text text-muted">You can adjust this amount if needed</small>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label>Discount (%)</label>
                            <input type="number" name="discount_percentage" id="discount_percentage" 
                                   class="form-control" step="0.01" value="0" min="0" max="100">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Discount Amount (TZS)</label>
                            <input type="number" name="discount_amount" id="discount_amount" 
                                   class="form-control" step="0.01" value="0" readonly>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Title Holder Name</label>
                            <input type="text" name="title_holder_name" class="form-control" 
                                   placeholder="Leave blank if same as customer">
                        </div>
                    </div>
                </div>
            </div>

            <!-- STEP 3: Payment Terms -->
            <div class="step-card">
                <div class="step-title">
                    <span class="step-number">3</span>
                    <span>Payment Terms</span>
                </div>

                <div class="row">
                    <div class="col-md-4">
                        <div class="form-group">
                            <label>Down Payment (TZS)<span class="required">*</span></label>
                            <input type="number" name="down_payment" id="down_payment" 
                                   class="form-control" step="0.01" value="0" min="0" required>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label>Payment Periods (Months)</label>
                            <input type="number" name="payment_periods" id="payment_periods" 
                                   class="form-control" value="20" min="1">
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label>Monthly Installment (TZS)</label>
                            <input type="number" name="installment_amount" id="installment_amount" 
                                   class="form-control" step="0.01" readonly>
                        </div>
                    </div>
                </div>

                <div class="calculation-summary">
                    <h6 style="margin-bottom: 15px; font-weight: 600;">Payment Summary</h6>
                    <div class="calculation-row">
                        <span>Plot Price:</span>
                        <span id="displayBaseAmount">TZS 0.00</span>
                    </div>
                    <div class="calculation-row">
                        <span>Discount:</span>
                        <span id="displayDiscount">TZS 0.00</span>
                    </div>
                    <div class="calculation-row">
                        <span>Total Amount:</span>
                        <span id="displayTotal">TZS 0.00</span>
                    </div>
                    <div class="calculation-row">
                        <span>Down Payment:</span>
                        <span id="displayDownPayment">TZS 0.00</span>
                    </div>
                    <div class="calculation-row">
                        <span>Remaining Balance:</span>
                        <span id="displayBalance">TZS 0.00</span>
                    </div>
                </div>
            </div>

            <!-- STEP 4: Down Payment Details -->
            <div class="step-card">
                <div class="step-title">
                    <span class="step-number">4</span>
                    <span>Down Payment Details</span>
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Payment Date<span class="required">*</span></label>
                            <input type="date" name="payment_date" class="form-control" 
                                   value="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Payment Method<span class="required">*</span></label>
                            <select name="payment_method" id="payment_method" class="form-control" required>
                                <option value="">-- Select Payment Method --</option>
                                <option value="cash">Cash</option>
                                <option value="bank_transfer">Bank Transfer</option>
                                <option value="bank_deposit">Bank Deposit</option>
                                <option value="mobile_money">Mobile Money</option>
                                <option value="cheque">Cheque</option>
                            </select>
                        </div>
                    </div>

                    <div class="col-md-12">
                        <div class="form-group">
                            <label>Transaction Reference</label>
                            <input type="text" name="transaction_reference" class="form-control" 
                                   placeholder="e.g., TRX123456789">
                        </div>
                    </div>
                </div>

                <!-- Cash Fields -->
                <div id="cash_fields" class="payment-method-fields">
                    <h6 class="mb-3"><i class="fas fa-money-bill-wave me-2"></i>Cash Payment Details</h6>
                    <div class="row">
                        <div class="col-md-12">
                            <div class="form-group">
                                <label>Received By<span class="required">*</span></label>
                                <input type="text" name="received_by" class="form-control" 
                                       placeholder="Name of person receiving cash">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Bank Transfer Fields -->
                <div id="transfer_fields" class="payment-method-fields">
                    <h6 class="mb-3"><i class="fas fa-exchange-alt me-2"></i>Bank Transfer Details</h6>
                    
                    <!-- Client Bank Details -->
                    <div class="client-bank-section">
                        <h6 class="mb-3 text-primary"><i class="fas fa-user me-2"></i>Client Bank Account (From)</h6>
                        <div class="row">
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label>Bank Name</label>
                                    <input type="text" name="client_bank_name" class="form-control" 
                                           placeholder="e.g., CRDB Bank">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label>Account Number</label>
                                    <input type="text" name="client_account_number" class="form-control" 
                                           placeholder="e.g., 0150123456789">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label>Account Name</label>
                                    <input type="text" name="client_account_name" class="form-control" 
                                           placeholder="Account holder name">
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

                <!-- Bank Deposit Fields -->
                <div id="deposit_fields" class="payment-method-fields">
                    <h6 class="mb-3"><i class="fas fa-building me-2"></i>Bank Deposit Details</h6>
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
                                <input type="text" name="depositor_name" class="form-control" 
                                       placeholder="Name of person making the deposit">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Deposit Bank</label>
                                <input type="text" name="deposit_bank" class="form-control" 
                                       placeholder="e.g., CRDB Bank">
                            </div>
                        </div>
                        <div class="col-md-12">
                            <div class="form-group">
                                <label>Deposit Slip Number</label>
                                <input type="text" name="deposit_account" class="form-control" 
                                       placeholder="Deposit slip reference number">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Mobile Money Fields -->
                <div id="mobile_money_fields" class="payment-method-fields">
                    <h6 class="mb-3"><i class="fas fa-mobile-alt me-2"></i>Mobile Money Details</h6>
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
                                <input type="text" name="mobile_money_number" class="form-control" 
                                       placeholder="e.g., 0755123456">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>Account Holder Name</label>
                                <input type="text" name="mobile_money_name" class="form-control" 
                                       placeholder="Name registered on mobile money">
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

                <!-- Cheque Fields -->
                <div id="cheque_fields" class="payment-method-fields">
                    <h6 class="mb-3"><i class="fas fa-money-check me-2"></i>Cheque Details</h6>
                    <div class="row">
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>Cheque Number<span class="required">*</span></label>
                                <input type="text" name="cheque_number" class="form-control" 
                                       placeholder="e.g., 123456">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>Cheque Date<span class="required">*</span></label>
                                <input type="date" name="cheque_date" class="form-control">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>Cheque Bank<span class="required">*</span></label>
                                <input type="text" name="cheque_bank" class="form-control" 
                                       placeholder="e.g., CRDB Bank">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Branch Name</label>
                                <input type="text" name="cheque_branch" class="form-control" 
                                       placeholder="Bank branch">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Payee Name</label>
                                <input type="text" name="cheque_payee" class="form-control" 
                                       placeholder="Name on cheque">
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Submit Buttons -->
            <div class="row">
                <div class="col-12" style="margin-top: 20px;">
                    <a href="index.php" class="btn btn-secondary">
                        <i class="fas fa-times"></i> Cancel
                    </a>
                    <button type="submit" class="btn btn-submit float-right">
                        <i class="fas fa-check"></i> Submit for Approval
                    </button>
                </div>
            </div>

        </form>

    </div>
</section>

<script>
(function checkJQuery() {
    if (typeof jQuery === 'undefined') {
        var script = document.createElement('script');
        script.src = 'https://code.jquery.com/jquery-3.6.0.min.js';
        script.onload = initializePage;
        document.head.appendChild(script);
    } else {
        initializePage();
    }
})();

function initializePage() {
    if (typeof $.fn.select2 === 'undefined') {
        var link = document.createElement('link');
        link.rel = 'stylesheet';
        link.href = 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css';
        document.head.appendChild(link);
        
        var script = document.createElement('script');
        script.src = 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js';
        script.onload = startApp;
        document.body.appendChild(script);
    } else {
        startApp();
    }
}

let basePlotPrice = 0;

function startApp() {
    $(document).ready(function() {
        $('.select2').select2({ theme: 'bootstrap4', width: '100%' });
        
        $('#project_id').on('change', loadPlots);
        $('#plot_id').on('change', loadPlotDetails);
        $('#discount_percentage, #down_payment, #payment_periods, #total_amount').on('input change', calculateAmounts);
        $('#payment_method').on('change', togglePaymentFields);
        
        // CRITICAL FIX: Sync all account selectors to hidden field
        $('.account-selector').on('change', function() {
            var selectedAccountId = $(this).val();
            $('#hidden_to_account_id').val(selectedAccountId);
            console.log('Account selected:', selectedAccountId);
        });
        
        // Before form submit, make sure hidden field has value
        $('#reservationForm').on('submit', function(e) {
            var accountId = $('#hidden_to_account_id').val();
            console.log('Submitting with account ID:', accountId);
        });
    });
}

function togglePaymentFields() {
    const paymentMethod = $('#payment_method').val();
    
    $('.payment-method-fields').removeClass('active');
    $('input[name="received_by"]').prop('required', false);
    $('input[name="cheque_number"], input[name="cheque_bank"], input[name="cheque_date"]').prop('required', false);
    
    // Clear hidden account field when switching methods
    $('#hidden_to_account_id').val('');
    $('.account-selector').val('');
    
    switch(paymentMethod) {
        case 'cash':
            $('#cash_fields').addClass('active');
            $('input[name="received_by"]').prop('required', true);
            break;
        case 'bank_transfer':
            $('#transfer_fields').addClass('active');
            break;
        case 'bank_deposit':
            $('#deposit_fields').addClass('active');
            break;
        case 'mobile_money':
            $('#mobile_money_fields').addClass('active');
            break;
        case 'cheque':
            $('#cheque_fields').addClass('active');
            $('input[name="cheque_number"], input[name="cheque_bank"], input[name="cheque_date"]').prop('required', true);
            break;
    }
}

function loadPlots() {
    const projectId = $('#project_id').val();
    const $plotSelect = $('#plot_id');
    const $loadingMsg = $('#plotsLoading');
    
    $plotSelect.prop('disabled', true).html('<option value="">-- Loading... --</option>');
    $loadingMsg.show();
    $('#plotInfoCard').hide();
    
    if (!projectId) {
        $plotSelect.html('<option value="">-- First Select a Project --</option>');
        $loadingMsg.hide();
        return;
    }
    
    $.ajax({
        url: 'create.php?action=get_plots&project_id=' + projectId,
        method: 'GET',
        dataType: 'json',
        success: function(response) {
            $plotSelect.html('<option value="">-- Select Available Plot --</option>');
            
            if (response.success && response.plots && response.plots.length > 0) {
                const availablePlots = response.plots.filter(plot => plot.status === 'available');
                
                if (availablePlots.length > 0) {
                    $.each(availablePlots, function(i, plot) {
                        const blockInfo = plot.block_number ? ` (Block ${plot.block_number})` : '';
                        const area = parseFloat(plot.area || 0).toFixed(2);
                        const price = parseFloat(plot.final_price || plot.selling_price || 0);
                        const optionText = `Plot ${plot.plot_number}${blockInfo} - ${area} m² - TZS ${formatNumber(price)}`;
                        
                        $plotSelect.append($('<option>', {
                            value: plot.plot_id,
                            text: optionText
                        }));
                    });
                    $plotSelect.prop('disabled', false);
                } else {
                    $plotSelect.html('<option value="">-- No Available Plots (All Reserved/Sold) --</option>');
                }
            } else {
                $plotSelect.html('<option value="">-- No Available Plots --</option>');
            }
            $loadingMsg.hide();
        },
        error: function(xhr, status, error) {
            console.error('Error loading plots:', error);
            $plotSelect.html('<option value="">-- Error Loading Plots --</option>');
            $loadingMsg.hide();
        }
    });
}

function loadPlotDetails() {
    const plotId = $('#plot_id').val();
    const $plotInfoCard = $('#plotInfoCard');
    
    if (!plotId) {
        $plotInfoCard.hide();
        $('#total_amount').val('');
        basePlotPrice = 0;
        calculateAmounts();
        return;
    }
    
    $.ajax({
        url: 'create.php?action=get_plot_details&plot_id=' + plotId,
        method: 'GET',
        dataType: 'json',
        success: function(response) {
            if (response.success && response.plot) {
                const plot = response.plot;
                
                if (plot.status !== 'available') {
                    alert('ERROR: This plot is ' + plot.status + ' and cannot be reserved. Please select another plot.');
                    $('#plot_id').val('').trigger('change');
                    loadPlots();
                    return;
                }
                
                $('#plotNumber').text(plot.plot_number);
                $('#blockNumber').text(plot.block_number || 'N/A');
                $('#plotArea').text(formatNumber(plot.area) + ' m²');
                $('#pricePerSqm').text('TZS ' + formatNumber(plot.price_per_sqm));
                
                basePlotPrice = parseFloat(plot.final_price || plot.selling_price || 0);
                $('#plotTotalPrice').text('TZS ' + formatNumber(basePlotPrice));
                $('#total_amount').val(basePlotPrice.toFixed(2));
                
                $plotInfoCard.fadeIn();
                calculateAmounts();
            } else if (response.error) {
                alert('Plot Unavailable: ' + response.error);
                $('#plot_id').val('').trigger('change');
                loadPlots();
            }
        },
        error: function(xhr, status, error) {
            console.error('Error loading plot details:', error);
            alert('Error loading plot details. Please try again.');
            $('#plot_id').val('').trigger('change');
        }
    });
}

function calculateAmounts() {
    const totalAmount = parseFloat($('#total_amount').val()) || 0;
    const discountPercentage = parseFloat($('#discount_percentage').val()) || 0;
    const downPayment = parseFloat($('#down_payment').val()) || 0;
    const paymentPeriods = parseInt($('#payment_periods').val()) || 1;
    
    const discountAmount = (basePlotPrice * discountPercentage) / 100;
    $('#discount_amount').val(discountAmount.toFixed(2));
    
    const remainingBalance = totalAmount - downPayment;
    const installmentAmount = paymentPeriods > 0 ? (remainingBalance / paymentPeriods) : 0;
    $('#installment_amount').val(installmentAmount.toFixed(2));
    
    $('#displayBaseAmount').text('TZS ' + formatNumber(basePlotPrice));
    $('#displayDiscount').text('TZS ' + formatNumber(discountAmount));
    $('#displayTotal').text('TZS ' + formatNumber(totalAmount));
    $('#displayDownPayment').text('TZS ' + formatNumber(downPayment));
    $('#displayBalance').text('TZS ' + formatNumber(remainingBalance));
}

function formatNumber(num) {
    return parseFloat(num || 0).toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
}
</script>

<?php require_once '../../includes/footer.php'; ?>