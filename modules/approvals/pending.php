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
$user_id = $_SESSION['user_id'];

/**
 * Update project plot counts
 */
function updateProjectCounts($conn, $plot_id, $company_id) {
    try {
        $project_sql = "SELECT project_id FROM plots WHERE plot_id = ? AND company_id = ?";
        $project_stmt = $conn->prepare($project_sql);
        $project_stmt->execute([$plot_id, $company_id]);
        $project_id = $project_stmt->fetchColumn();
        
        if ($project_id) {
            $counts_sql = "SELECT 
                              SUM(CASE WHEN status = 'available' THEN 1 ELSE 0 END) as available,
                              SUM(CASE WHEN status = 'reserved' THEN 1 ELSE 0 END) as reserved,
                              SUM(CASE WHEN status = 'sold' THEN 1 ELSE 0 END) as sold
                          FROM plots WHERE project_id = ? AND company_id = ?";
            
            $counts_stmt = $conn->prepare($counts_sql);
            $counts_stmt->execute([$project_id, $company_id]);
            $counts = $counts_stmt->fetch(PDO::FETCH_ASSOC);
            
            $update_project_sql = "UPDATE projects 
                                  SET available_plots = ?,
                                      reserved_plots = ?,
                                      sold_plots = ?,
                                      updated_at = NOW() 
                                  WHERE project_id = ? AND company_id = ?";
            
            $update_project_stmt = $conn->prepare($update_project_sql);
            $update_project_stmt->execute([
                $counts['available'] ?? 0,
                $counts['reserved'] ?? 0,
                $counts['sold'] ?? 0,
                $project_id,
                $company_id
            ]);
        }
        
        return true;
    } catch (PDOException $e) {
        error_log("Error updating project counts: " . $e->getMessage());
        return false;
    }
}

/**
 * Create journal entry for payment
 */
function createPaymentJournalEntry($conn, $payment, $company_id, $user_id) {
    try {
        $year = date('Y');
        $count_sql = "SELECT COUNT(*) FROM journal_entries WHERE company_id = ? AND YEAR(journal_date) = ?";
        $count_stmt = $conn->prepare($count_sql);
        $count_stmt->execute([$company_id, $year]);
        $count = $count_stmt->fetchColumn() + 1;
        $journal_number = 'JE-' . $year . '-' . str_pad($count, 5, '0', STR_PAD_LEFT);
        
        $je_sql = "INSERT INTO journal_entries (
            company_id, journal_number, journal_date, journal_type, 
            description, reference_type, reference_id, status, created_by, created_at
        ) VALUES (?, ?, ?, 'sales', ?, 'payment', ?, 'posted', ?, NOW())";
        
        $je_stmt = $conn->prepare($je_sql);
        $je_stmt->execute([
            $company_id,
            $journal_number,
            $payment['payment_date'],
            'Payment received - ' . $payment['payment_number'],
            $payment['payment_id'],
            $user_id
        ]);
        
        $journal_id = $conn->lastInsertId();
        
        $debit_account = 1111;
        $credit_account = 1132;
        
        $line1_sql = "INSERT INTO journal_entry_lines (
            journal_id, account_code, description, debit_amount, credit_amount
        ) VALUES (?, ?, ?, ?, 0)";
        
        $line1_stmt = $conn->prepare($line1_sql);
        $line1_stmt->execute([
            $journal_id,
            $debit_account,
            'Payment received - ' . $payment['payment_number'],
            $payment['amount']
        ]);
        
        $line2_sql = "INSERT INTO journal_entry_lines (
            journal_id, account_code, description, debit_amount, credit_amount
        ) VALUES (?, ?, ?, 0, ?)";
        
        $line2_stmt = $conn->prepare($line2_sql);
        $line2_stmt->execute([
            $journal_id,
            $credit_account,
            'Payment received - ' . $payment['payment_number'],
            $payment['amount']
        ]);
        
        $update_debit = "UPDATE chart_of_accounts 
                        SET current_balance = current_balance + ?,
                            updated_at = NOW()
                        WHERE account_code = ? AND company_id = ?";
        $stmt_debit = $conn->prepare($update_debit);
        $stmt_debit->execute([$payment['amount'], $debit_account, $company_id]);
        
        $update_credit = "UPDATE chart_of_accounts 
                         SET current_balance = current_balance - ?,
                             updated_at = NOW()
                         WHERE account_code = ? AND company_id = ?";
        $stmt_credit = $conn->prepare($update_credit);
        $stmt_credit->execute([$payment['amount'], $credit_account, $company_id]);
        
        return true;
        
    } catch (PDOException $e) {
        error_log("Error creating journal entry: " . $e->getMessage());
        return false;
    }
}

// Handle approval/rejection actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    $record_id = intval($_POST['record_id']);
    $approval_type = $_POST['approval_type'];
    
    try {
        $conn->beginTransaction();
        
        if ($action === 'approve') {
            
            if ($approval_type === 'payment') {
                
                $payment_sql = "SELECT p.*, r.plot_id, r.total_amount as reservation_total,
                                       r.reservation_id, r.status as reservation_status
                                FROM payments p
                                JOIN reservations r ON p.reservation_id = r.reservation_id
                                WHERE p.payment_id = ? AND p.company_id = ?";
                $payment_stmt = $conn->prepare($payment_sql);
                $payment_stmt->execute([$record_id, $company_id]);
                $payment = $payment_stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$payment) {
                    throw new Exception("Payment not found");
                }
                
                $update_payment = "UPDATE payments 
                                  SET status = 'approved',
                                      approved_by = ?,
                                      approved_at = NOW()
                                  WHERE payment_id = ? AND company_id = ?";
                $stmt = $conn->prepare($update_payment);
                $stmt->execute([$user_id, $record_id, $company_id]);
                
                if (!empty($payment['to_account_id'])) {
                    $update_account = "UPDATE bank_accounts 
                                      SET current_balance = current_balance + ?,
                                          updated_at = NOW()
                                      WHERE bank_account_id = ? AND company_id = ?";
                    $acc_stmt = $conn->prepare($update_account);
                    $acc_stmt->execute([
                        $payment['amount'],
                        $payment['to_account_id'],
                        $company_id
                    ]);
                }
                
                $total_paid_sql = "SELECT COALESCE(SUM(amount), 0) as total_paid
                                  FROM payments
                                  WHERE reservation_id = ? AND company_id = ? AND status = 'approved'";
                $total_stmt = $conn->prepare($total_paid_sql);
                $total_stmt->execute([$payment['reservation_id'], $company_id]);
                $total_paid = $total_stmt->fetchColumn();
                
                $outstanding = $payment['reservation_total'] - $total_paid;
                
                $reservation_status = 'active';
                $completed_at = null;
                
                if ($payment['reservation_status'] === 'pending_approval') {
                    $reservation_status = 'active';
                } elseif ($outstanding <= 0) {
                    $reservation_status = 'completed';
                    $completed_at = date('Y-m-d H:i:s');
                }
                
                $update_reservation = "UPDATE reservations 
                                      SET status = ?,
                                          completed_at = ?,
                                          updated_at = NOW()
                                      WHERE reservation_id = ? AND company_id = ?";
                $res_stmt = $conn->prepare($update_reservation);
                $res_stmt->execute([
                    $reservation_status,
                    $completed_at,
                    $payment['reservation_id'],
                    $company_id
                ]);
                
                $plot_status = 'available';
                
                if ($reservation_status === 'active') {
                    $plot_status = 'reserved';
                } elseif ($reservation_status === 'completed') {
                    $plot_status = 'sold';
                }
                
                $update_plot = "UPDATE plots 
                               SET status = ?,
                                   updated_at = NOW()
                               WHERE plot_id = ? AND company_id = ?";
                $plot_stmt = $conn->prepare($update_plot);
                $plot_stmt->execute([
                    $plot_status,
                    $payment['plot_id'],
                    $company_id
                ]);
                
                updateProjectCounts($conn, $payment['plot_id'], $company_id);
                createPaymentJournalEntry($conn, $payment, $company_id, $user_id);
                
                $conn->commit();
                
                $_SESSION['success'] = "Payment approved successfully! " .
                    "Account balance updated by TZS " . number_format($payment['amount'], 2) . ". " .
                    "Reservation status: " . ucfirst($reservation_status) . ". " .
                    "Plot status: " . ucfirst($plot_status) . ". " .
                    "Outstanding: TZS " . number_format($outstanding, 2);
                
            }
            elseif ($approval_type === 'commission') {
                
                $update_sql = "UPDATE commissions 
                              SET payment_status = 'paid',
                                  approved_by = ?,
                                  approved_at = NOW()
                              WHERE commission_id = ? AND company_id = ?";
                $stmt = $conn->prepare($update_sql);
                $stmt->execute([$user_id, $record_id, $company_id]);
                
                $conn->commit();
                $_SESSION['success'] = "Commission approved successfully!";
                
            }
            elseif ($approval_type === 'refund') {
                
                $refund_sql = "SELECT * FROM refunds WHERE refund_id = ? AND company_id = ?";
                $refund_stmt = $conn->prepare($refund_sql);
                $refund_stmt->execute([$record_id, $company_id]);
                $refund = $refund_stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$refund) {
                    throw new Exception("Refund not found");
                }
                
                $update_sql = "UPDATE refunds 
                              SET status = 'approved',
                                  approved_by = ?,
                                  approved_at = NOW()
                              WHERE refund_id = ? AND company_id = ?";
                $stmt = $conn->prepare($update_sql);
                $stmt->execute([$user_id, $record_id, $company_id]);
                
                if (!empty($refund['from_account_id'])) {
                    $net_refund = $refund['refund_amount'] - ($refund['penalty_amount'] ?? 0);
                    
                    $update_account = "UPDATE bank_accounts 
                                      SET current_balance = current_balance - ?,
                                          updated_at = NOW()
                                      WHERE bank_account_id = ? AND company_id = ?";
                    $acc_stmt = $conn->prepare($update_account);
                    $acc_stmt->execute([$net_refund, $refund['from_account_id'], $company_id]);
                }
                
                $conn->commit();
                $_SESSION['success'] = "Refund approved successfully!";
                
            }
            elseif ($approval_type === 'purchase') {
                
                $update_sql = "UPDATE purchase_orders 
                              SET status = 'approved',
                                  approved_by = ?,
                                  approved_at = NOW()
                              WHERE purchase_order_id = ? AND company_id = ?";
                $stmt = $conn->prepare($update_sql);
                $stmt->execute([$user_id, $record_id, $company_id]);
                
                $conn->commit();
                $_SESSION['success'] = "Purchase order approved successfully!";
                
            }
            elseif ($approval_type === 'payroll') {
                
                $update_sql = "UPDATE payroll 
                              SET status = 'processed',
                                  processed_by = ?,
                                  processed_at = NOW()
                              WHERE payroll_id = ? AND company_id = ?";
                $stmt = $conn->prepare($update_sql);
                $stmt->execute([$user_id, $record_id, $company_id]);
                
                $conn->commit();
                $_SESSION['success'] = "Payroll approved successfully!";
            }
            
        } elseif ($action === 'reject') {
            
            $rejection_reason = $_POST['rejection_reason'] ?? 'No reason provided';
            
            if ($approval_type === 'payment') {
                
                $update_sql = "UPDATE payments 
                              SET status = 'rejected',
                                  rejected_by = ?,
                                  rejected_at = NOW(),
                                  rejection_reason = ?
                              WHERE payment_id = ? AND company_id = ?";
                $stmt = $conn->prepare($update_sql);
                $stmt->execute([$user_id, $rejection_reason, $record_id, $company_id]);
                
                $conn->commit();
                $_SESSION['success'] = "Payment rejected";
                
            }
            elseif ($approval_type === 'commission') {
                
                $update_sql = "UPDATE commissions 
                              SET payment_status = 'rejected',
                                  rejected_by = ?,
                                  rejected_at = NOW(),
                                  rejection_reason = ?
                              WHERE commission_id = ? AND company_id = ?";
                $stmt = $conn->prepare($update_sql);
                $stmt->execute([$user_id, $rejection_reason, $record_id, $company_id]);
                
                $conn->commit();
                $_SESSION['success'] = "Commission rejected";
                
            }
            elseif ($approval_type === 'refund') {
                
                $update_sql = "UPDATE refunds 
                              SET status = 'rejected',
                                  rejected_by = ?,
                                  rejected_at = NOW(),
                                  rejection_reason = ?
                              WHERE refund_id = ? AND company_id = ?";
                $stmt = $conn->prepare($update_sql);
                $stmt->execute([$user_id, $rejection_reason, $record_id, $company_id]);
                
                $conn->commit();
                $_SESSION['success'] = "Refund rejected";
                
            }
            elseif ($approval_type === 'purchase') {
                
                $update_sql = "UPDATE purchase_orders 
                              SET status = 'rejected',
                                  rejected_by = ?,
                                  rejected_at = NOW(),
                                  rejection_reason = ?
                              WHERE purchase_order_id = ? AND company_id = ?";
                $stmt = $conn->prepare($update_sql);
                $stmt->execute([$user_id, $rejection_reason, $record_id, $company_id]);
                
                $conn->commit();
                $_SESSION['success'] = "Purchase order rejected";
                
            }
            elseif ($approval_type === 'payroll') {
                
                $update_sql = "UPDATE payroll 
                              SET status = 'rejected',
                                  rejected_by = ?,
                                  rejected_at = NOW(),
                                  rejection_reason = ?
                              WHERE payroll_id = ? AND company_id = ?";
                $stmt = $conn->prepare($update_sql);
                $stmt->execute([$user_id, $rejection_reason, $record_id, $company_id]);
                
                $conn->commit();
                $_SESSION['success'] = "Payroll rejected";
            }
        }
        
    } catch (Exception $e) {
        $conn->rollBack();
        $_SESSION['error'] = "Error: " . $e->getMessage();
        error_log("Approval error: " . $e->getMessage());
    }
    
    header("Location: pending.php");
    exit;
}

// Fetch pending payments
try {
    $payments_sql = "SELECT p.*,
                            r.reservation_number,
                            r.total_amount as reservation_total,
                            c.full_name as customer_name,
                            pl.plot_number,
                            pr.project_name,
                            ba.account_name as to_account_name,
                            ba.bank_name as to_bank_name,
                            u.full_name as submitted_by_name,
                            DATEDIFF(NOW(), p.submitted_at) as days_pending,
                            (r.total_amount - COALESCE((SELECT SUM(amount) FROM payments 
                              WHERE reservation_id = r.reservation_id AND company_id = r.company_id 
                              AND status = 'approved'), 0)) as current_outstanding
                     FROM payments p
                     JOIN reservations r ON p.reservation_id = r.reservation_id
                     JOIN customers c ON r.customer_id = c.customer_id
                     JOIN plots pl ON r.plot_id = pl.plot_id
                     JOIN projects pr ON pl.project_id = pr.project_id
                     LEFT JOIN bank_accounts ba ON p.to_account_id = ba.bank_account_id
                     LEFT JOIN users u ON p.submitted_by = u.user_id
                     WHERE p.company_id = ? AND p.status = 'pending_approval'
                     ORDER BY p.submitted_at ASC";
    
    $payments_stmt = $conn->prepare($payments_sql);
    $payments_stmt->execute([$company_id]);
    $pending_payments = $payments_stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    $pending_payments = [];
    error_log("Error fetching payments: " . $e->getMessage());
}

// Fetch pending commissions
try {
    $commissions_sql = "SELECT cm.*,
                               r.reservation_number,
                               c.full_name as customer_name,
                               pl.plot_number,
                               pr.project_name,
                               u.full_name as recipient_name,
                               us.full_name as submitted_by_name
                        FROM commissions cm
                        JOIN reservations r ON cm.reservation_id = r.reservation_id
                        JOIN customers c ON r.customer_id = c.customer_id
                        JOIN plots pl ON r.plot_id = pl.plot_id
                        JOIN projects pr ON pl.project_id = pr.project_id
                        JOIN users u ON cm.user_id = u.user_id
                        LEFT JOIN users us ON cm.created_by = us.user_id
                        WHERE cm.company_id = ? AND cm.payment_status = 'pending'
                        ORDER BY cm.created_at ASC";
    
    $commissions_stmt = $conn->prepare($commissions_sql);
    $commissions_stmt->execute([$company_id]);
    $pending_commissions = $commissions_stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    $pending_commissions = [];
}

// Fetch pending refunds
try {
    $refunds_sql = "SELECT rf.*,
                           p.payment_number,
                           c.full_name as customer_name,
                           pl.plot_number,
                           pr.project_name,
                           u.full_name as requested_by_name,
                           (rf.refund_amount - COALESCE(rf.penalty_amount, 0)) as net_refund_amount
                    FROM refunds rf
                    JOIN payments p ON rf.payment_id = p.payment_id
                    JOIN reservations r ON p.reservation_id = r.reservation_id
                    JOIN customers c ON r.customer_id = c.customer_id
                    JOIN plots pl ON r.plot_id = pl.plot_id
                    JOIN projects pr ON pl.project_id = pr.project_id
                    LEFT JOIN users u ON rf.requested_by = u.user_id
                    WHERE rf.company_id = ? AND rf.status = 'pending'
                    ORDER BY rf.requested_at ASC";
    
    $refunds_stmt = $conn->prepare($refunds_sql);
    $refunds_stmt->execute([$company_id]);
    $pending_refunds = $refunds_stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    $pending_refunds = [];
}

// Fetch pending purchase orders
try {
    $purchases_sql = "SELECT po.*,
                             s.supplier_name,
                             u.full_name as created_by_name,
                             COUNT(poi.item_id) as item_count
                      FROM purchase_orders po
                      JOIN suppliers s ON po.supplier_id = s.supplier_id
                      LEFT JOIN purchase_order_items poi ON po.purchase_order_id = poi.purchase_order_id
                      LEFT JOIN users u ON po.created_by = u.user_id
                      WHERE po.company_id = ? AND po.status = 'pending'
                      GROUP BY po.purchase_order_id
                      ORDER BY po.created_at ASC";
    
    $purchases_stmt = $conn->prepare($purchases_sql);
    $purchases_stmt->execute([$company_id]);
    $pending_purchases = $purchases_stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    $pending_purchases = [];
}

// Fetch pending payroll
try {
    $payroll_sql = "SELECT pr.*,
                           u.full_name as created_by_name,
                           COUNT(pd.payroll_detail_id) as employee_count,
                           SUM(pd.net_salary) as total_amount
                    FROM payroll pr
                    LEFT JOIN payroll_details pd ON pr.payroll_id = pd.payroll_id
                    LEFT JOIN users u ON pr.created_by = u.user_id
                    WHERE pr.company_id = ? AND pr.status = 'pending'
                    GROUP BY pr.payroll_id
                    ORDER BY pr.created_at ASC";
    
    $payroll_stmt = $conn->prepare($payroll_sql);
    $payroll_stmt->execute([$company_id]);
    $pending_payroll = $payroll_stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    $pending_payroll = [];
}

// Calculate totals
$total_pending = count($pending_payments) + count($pending_commissions) + 
                 count($pending_refunds) + count($pending_purchases) + 
                 count($pending_payroll);

$page_title = 'Pending Approvals';
require_once '../../includes/header.php';
?>

<!-- BOOTSTRAP 5 FIX - Add this if header.php doesn't have Bootstrap 5 JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<style>
.stats-card {
    background: white;
    padding: 20px;
    border-radius: 10px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    transition: all 0.3s;
}

.stats-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 5px 15px rgba(0,0,0,0.15);
}

.stats-card .number {
    font-size: 36px;
    font-weight: bold;
    color: #667eea;
}

.stats-card .label {
    font-size: 14px;
    color: #6c757d;
    text-transform: uppercase;
}

.nav-tabs .nav-link {
    color: #6c757d;
    border: none;
    border-bottom: 3px solid transparent;
    padding: 12px 20px;
    cursor: pointer;
}

.nav-tabs .nav-link.active {
    color: #667eea;
    border-bottom-color: #667eea;
    background: transparent;
}

.nav-tabs .nav-link:hover {
    border-bottom-color: #b8c1ec;
}

.badge-pending {
    background: #fff3cd;
    color: #856404;
    padding: 4px 8px;
    border-radius: 20px;
    font-size: 11px;
}

.badge-urgent {
    background: #f8d7da;
    color: #721c24;
    padding: 4px 8px;
    border-radius: 20px;
    font-size: 11px;
    animation: pulse 2s infinite;
}

@keyframes pulse {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.6; }
}

.table-container {
    background: white;
    border-radius: 10px;
    padding: 20px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}

.workflow-box {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 20px;
    border-radius: 10px;
    margin-bottom: 20px;
}

.workflow-box h5 {
    color: white;
    margin-bottom: 15px;
}

.workflow-box ul {
    margin: 0;
    padding-left: 20px;
}

.workflow-box li {
    margin-bottom: 8px;
}

.info-badge {
    background: #e7f3ff;
    color: #004085;
    padding: 2px 8px;
    border-radius: 12px;
    font-size: 10px;
    font-weight: 600;
}

.btn-approve {
    background: #28a745;
    color: white;
    border: none;
    padding: 6px 12px;
    border-radius: 6px;
    font-size: 13px;
}

.btn-approve:hover {
    background: #218838;
    color: white;
}

.btn-reject {
    background: #dc3545;
    color: white;
    border: none;
    padding: 6px 12px;
    border-radius: 6px;
    font-size: 13px;
}

.btn-reject:hover {
    background: #c82333;
    color: white;
}

.modal-header.bg-success {
    background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
    color: white;
}

.modal-header.bg-danger {
    background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
    color: white;
}

.modal-header.bg-info {
    background: linear-gradient(135deg, #17a2b8 0%, #138496 100%);
    color: white;
}
</style>

<div class="content-header">
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-6">
                <h1><i class="fas fa-check-double"></i> Pending Approvals</h1>
            </div>
            <div class="col-sm-6">
                <ol class="breadcrumb float-sm-right">
                    <li class="breadcrumb-item"><a href="../../dashboard.php">Home</a></li>
                    <li class="breadcrumb-item active">Approvals</li>
                </ol>
            </div>
        </div>
    </div>
</div>

<section class="content">
    <div class="container-fluid">

        <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            <i class="fas fa-check-circle"></i> <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
        </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            <i class="fas fa-exclamation-triangle"></i> <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
        </div>
        <?php endif; ?>

        <!-- Workflow Information -->
        <div class="workflow-box">
            <h5><i class="fas fa-info-circle"></i> Approval Workflow</h5>
            <p style="margin-bottom: 10px;">When you approve a payment, the following happens automatically:</p>
            <ul>
                <li><strong>Payment Status:</strong> Changes to "Approved"</li>
                <li><strong>Account Balance:</strong> Payment amount is added to the selected company account</li>
                <li><strong>Reservation Status:</strong> Updates from "Pending Approval" → "Active" (first payment) or "Active" → "Completed" (fully paid)</li>
                <li><strong>Plot Status:</strong> Updates from "Available" → "Reserved" (first payment) or "Reserved" → "Sold" (fully paid)</li>
                <li><strong>Project Counts:</strong> Available/Reserved/Sold plot counts are recalculated</li>
                <li><strong>Journal Entry:</strong> Automatic accounting entry is created</li>
            </ul>
            
            <!-- Debug Test Button -->
            <button type="button" class="btn btn-light btn-sm" onclick="testModals()">
                <i class="fas fa-vial"></i> Test Modal System
            </button>
        </div>

        <!-- Statistics Cards -->
        <div class="row mb-4">
            <div class="col-lg-2 col-6">
                <div class="stats-card">
                    <div class="number"><?php echo $total_pending; ?></div>
                    <div class="label">Total Pending</div>
                </div>
            </div>
            <div class="col-lg-2 col-6">
                <div class="stats-card">
                    <div class="number"><?php echo count($pending_payments); ?></div>
                    <div class="label">Payments</div>
                </div>
            </div>
            <div class="col-lg-2 col-6">
                <div class="stats-card">
                    <div class="number"><?php echo count($pending_commissions); ?></div>
                    <div class="label">Commissions</div>
                </div>
            </div>
            <div class="col-lg-2 col-6">
                <div class="stats-card">
                    <div class="number"><?php echo count($pending_refunds); ?></div>
                    <div class="label">Refunds</div>
                </div>
            </div>
            <div class="col-lg-2 col-6">
                <div class="stats-card">
                    <div class="number"><?php echo count($pending_purchases); ?></div>
                    <div class="label">Purchases</div>
                </div>
            </div>
            <div class="col-lg-2 col-6">
                <div class="stats-card">
                    <div class="number"><?php echo count($pending_payroll); ?></div>
                    <div class="label">Payroll</div>
                </div>
            </div>
        </div>

        <!-- Tabs -->
        <ul class="nav nav-tabs" id="myTab" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="payments-tab" data-bs-toggle="tab" data-bs-target="#payments" type="button" role="tab">
                    Payments <span class="badge bg-warning"><?php echo count($pending_payments); ?></span>
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="commissions-tab" data-bs-toggle="tab" data-bs-target="#commissions" type="button" role="tab">
                    Commissions <span class="badge bg-warning"><?php echo count($pending_commissions); ?></span>
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="refunds-tab" data-bs-toggle="tab" data-bs-target="#refunds" type="button" role="tab">
                    Refunds <span class="badge bg-warning"><?php echo count($pending_refunds); ?></span>
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="purchases-tab" data-bs-toggle="tab" data-bs-target="#purchases" type="button" role="tab">
                    Purchase Orders <span class="badge bg-warning"><?php echo count($pending_purchases); ?></span>
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="payroll-tab" data-bs-toggle="tab" data-bs-target="#payroll" type="button" role="tab">
                    Payroll <span class="badge bg-warning"><?php echo count($pending_payroll); ?></span>
                </button>
            </li>
        </ul>

        <div class="tab-content" id="myTabContent" style="margin-top: 20px;">
            
            <!-- PAYMENTS TAB -->
            <div class="tab-pane fade show active" id="payments" role="tabpanel">
                <div class="table-container">
                    <?php if (empty($pending_payments)): ?>
                        <p class="text-center text-muted" style="padding: 40px;">
                            <i class="fas fa-check-circle fa-3x mb-3"></i><br>
                            No pending payments
                        </p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Submitted</th>
                                        <th>Payment #</th>
                                        <th>Customer / Reservation</th>
                                        <th>Amount</th>
                                        <th>To Account</th>
                                        <th>Outstanding</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($pending_payments as $payment): ?>
                                        <tr>
                                            <td>
                                                <?php echo date('d M Y', strtotime($payment['submitted_at'])); ?>
                                                <?php if ($payment['days_pending'] > 3): ?>
                                                    <br><span class="badge-urgent">Urgent!</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><strong><?php echo htmlspecialchars($payment['payment_number']); ?></strong></td>
                                            <td>
                                                <?php echo htmlspecialchars($payment['customer_name']); ?><br>
                                                <small class="text-muted"><?php echo htmlspecialchars($payment['reservation_number']); ?></small>
                                            </td>
                                            <td><strong>TZS <?php echo number_format($payment['amount'], 2); ?></strong></td>
                                            <td>
                                                <?php if ($payment['to_account_name']): ?>
                                                    <?php echo htmlspecialchars($payment['to_bank_name'] . ' - ' . $payment['to_account_name']); ?>
                                                    <br><span class="info-badge">Will be credited</span>
                                                <?php else: ?>
                                                    <span class="text-muted">No account selected</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                TZS <?php echo number_format($payment['current_outstanding'], 2); ?><br>
                                                <small class="text-muted">
                                                    <?php 
                                                    $paid_pct = ($payment['reservation_total'] > 0) ? 
                                                        (($payment['reservation_total'] - $payment['current_outstanding']) / $payment['reservation_total'] * 100) : 0;
                                                    echo number_format($paid_pct, 1); 
                                                    ?>% paid
                                                </small>
                                            </td>
                                            <td><span class="badge-pending">Pending</span></td>
                                            <td>
                                                <button class="btn btn-sm btn-info" onclick='viewPaymentDetails(<?php echo json_encode($payment, JSON_HEX_APOS | JSON_HEX_QUOT); ?>)'>
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                                <button class="btn btn-sm btn-approve" onclick="approveRecord(<?php echo $payment['payment_id']; ?>, 'payment', '<?php echo htmlspecialchars($payment['payment_number'], ENT_QUOTES); ?>')">
                                                    <i class="fas fa-check"></i>
                                                </button>
                                                <button class="btn btn-sm btn-reject" onclick="rejectRecord(<?php echo $payment['payment_id']; ?>, 'payment', '<?php echo htmlspecialchars($payment['payment_number'], ENT_QUOTES); ?>')">
                                                    <i class="fas fa-times"></i>
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- COMMISSIONS TAB -->
            <div class="tab-pane fade" id="commissions" role="tabpanel">
                <div class="table-container">
                    <?php if (empty($pending_commissions)): ?>
                        <p class="text-center text-muted" style="padding: 40px;">
                            <i class="fas fa-check-circle fa-3x mb-3"></i><br>
                            No pending commissions
                        </p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Submitted</th>
                                        <th>Recipient</th>
                                        <th>Customer / Reservation</th>
                                        <th>Amount</th>
                                        <th>Rate</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($pending_commissions as $commission): ?>
                                        <tr>
                                            <td><?php echo date('d M Y', strtotime($commission['created_at'])); ?></td>
                                            <td><strong><?php echo htmlspecialchars($commission['recipient_name']); ?></strong></td>
                                            <td>
                                                <?php echo htmlspecialchars($commission['customer_name']); ?><br>
                                                <small class="text-muted"><?php echo htmlspecialchars($commission['reservation_number']); ?></small>
                                            </td>
                                            <td><strong>TZS <?php echo number_format($commission['commission_amount'], 2); ?></strong></td>
                                            <td><?php echo $commission['commission_percentage']; ?>%</td>
                                            <td><span class="badge-pending">Pending</span></td>
                                            <td>
                                                <button class="btn btn-sm btn-approve" onclick="approveRecord(<?php echo $commission['commission_id']; ?>, 'commission', 'Commission')">
                                                    <i class="fas fa-check"></i>
                                                </button>
                                                <button class="btn btn-sm btn-reject" onclick="rejectRecord(<?php echo $commission['commission_id']; ?>, 'commission', 'Commission')">
                                                    <i class="fas fa-times"></i>
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- REFUNDS TAB -->
            <div class="tab-pane fade" id="refunds" role="tabpanel">
                <div class="table-container">
                    <?php if (empty($pending_refunds)): ?>
                        <p class="text-center text-muted" style="padding: 40px;">
                            <i class="fas fa-check-circle fa-3x mb-3"></i><br>
                            No pending refunds
                        </p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Requested</th>
                                        <th>Refund #</th>
                                        <th>Customer</th>
                                        <th>Amount</th>
                                        <th>Penalty</th>
                                        <th>Net Refund</th>
                                        <th>Reason</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($pending_refunds as $refund): ?>
                                        <tr>
                                            <td><?php echo date('d M Y', strtotime($refund['requested_at'])); ?></td>
                                            <td><strong><?php echo htmlspecialchars($refund['refund_number']); ?></strong></td>
                                            <td><?php echo htmlspecialchars($refund['customer_name']); ?></td>
                                            <td>TZS <?php echo number_format($refund['refund_amount'], 2); ?></td>
                                            <td>TZS <?php echo number_format($refund['penalty_amount'] ?? 0, 2); ?></td>
                                            <td><strong>TZS <?php echo number_format($refund['net_refund_amount'], 2); ?></strong></td>
                                            <td><?php echo htmlspecialchars($refund['refund_reason']); ?></td>
                                            <td>
                                                <button class="btn btn-sm btn-approve" onclick="approveRecord(<?php echo $refund['refund_id']; ?>, 'refund', '<?php echo htmlspecialchars($refund['refund_number'], ENT_QUOTES); ?>')">
                                                    <i class="fas fa-check"></i>
                                                </button>
                                                <button class="btn btn-sm btn-reject" onclick="rejectRecord(<?php echo $refund['refund_id']; ?>, 'refund', '<?php echo htmlspecialchars($refund['refund_number'], ENT_QUOTES); ?>')">
                                                    <i class="fas fa-times"></i>
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- PURCHASES TAB -->
            <div class="tab-pane fade" id="purchases" role="tabpanel">
                <div class="table-container">
                    <?php if (empty($pending_purchases)): ?>
                        <p class="text-center text-muted" style="padding: 40px;">
                            <i class="fas fa-check-circle fa-3x mb-3"></i><br>
                            No pending purchase orders
                        </p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Created</th>
                                        <th>PO Number</th>
                                        <th>Supplier</th>
                                        <th>Amount</th>
                                        <th>Items</th>
                                        <th>Delivery Date</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($pending_purchases as $po): ?>
                                        <tr>
                                            <td><?php echo date('d M Y', strtotime($po['created_at'])); ?></td>
                                            <td><strong><?php echo htmlspecialchars($po['po_number']); ?></strong></td>
                                            <td><?php echo htmlspecialchars($po['supplier_name']); ?></td>
                                            <td><strong>TZS <?php echo number_format($po['total_amount'], 2); ?></strong></td>
                                            <td><?php echo $po['item_count']; ?> items</td>
                                            <td><?php echo date('d M Y', strtotime($po['delivery_date'])); ?></td>
                                            <td>
                                                <button class="btn btn-sm btn-approve" onclick="approveRecord(<?php echo $po['purchase_order_id']; ?>, 'purchase', '<?php echo htmlspecialchars($po['po_number'], ENT_QUOTES); ?>')">
                                                    <i class="fas fa-check"></i>
                                                </button>
                                                <button class="btn btn-sm btn-reject" onclick="rejectRecord(<?php echo $po['purchase_order_id']; ?>, 'purchase', '<?php echo htmlspecialchars($po['po_number'], ENT_QUOTES); ?>')">
                                                    <i class="fas fa-times"></i>
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- PAYROLL TAB -->
            <div class="tab-pane fade" id="payroll" role="tabpanel">
                <div class="table-container">
                    <?php if (empty($pending_payroll)): ?>
                        <p class="text-center text-muted" style="padding: 40px;">
                            <i class="fas fa-check-circle fa-3x mb-3"></i><br>
                            No pending payroll
                        </p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Created</th>
                                        <th>Period (Month/Year)</th>
                                        <th>Employees</th>
                                        <th>Total Amount</th>
                                        <th>Payment Date</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($pending_payroll as $pr): ?>
                                        <tr>
                                            <td><?php echo date('d M Y', strtotime($pr['created_at'])); ?></td>
                                            <td><strong><?php echo date('F Y', mktime(0, 0, 0, $pr['month'], 1, $pr['year'])); ?></strong></td>
                                            <td><?php echo $pr['employee_count']; ?> employees</td>
                                            <td><strong>TZS <?php echo number_format($pr['total_amount'], 2); ?></strong></td>
                                            <td><?php echo date('d M Y', strtotime($pr['payment_date'])); ?></td>
                                            <td>
                                                <button class="btn btn-sm btn-approve" onclick="approveRecord(<?php echo $pr['payroll_id']; ?>, 'payroll', 'Payroll')">
                                                    <i class="fas fa-check"></i>
                                                </button>
                                                <button class="btn btn-sm btn-reject" onclick="rejectRecord(<?php echo $pr['payroll_id']; ?>, 'payroll', 'Payroll')">
                                                    <i class="fas fa-times"></i>
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

        </div>

    </div>
</section>

<!-- Payment Details Modal -->
<div class="modal fade" id="paymentDetailsModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title"><i class="fas fa-file-invoice"></i> Payment Details</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="paymentDetailsContent">
                <!-- Content populated by JS -->
            </div>
        </div>
    </div>
</div>

<!-- Approval Modal -->
<div class="modal fade" id="approvalModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title"><i class="fas fa-check-circle"></i> Approve <span id="approvalRecordType"></span></h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="approve">
                    <input type="hidden" name="record_id" id="approval_record_id">
                    <input type="hidden" name="approval_type" id="approval_type">
                    
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i> You are about to approve <strong id="approvalRecordNumber"></strong>
                    </div>
                    
                    <h6>What will happen:</h6>
                    <ul id="approvalActionsList">
                        <!-- Populated by JS -->
                    </ul>
                    
                    <div class="mb-3">
                        <label class="form-label">Comments (Optional)</label>
                        <textarea name="approval_comments" class="form-control" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-check"></i> Confirm Approval
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Rejection Modal -->
<div class="modal fade" id="rejectionModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title"><i class="fas fa-times-circle"></i> Reject <span id="rejectionRecordType"></span></h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="reject">
                    <input type="hidden" name="record_id" id="rejection_record_id">
                    <input type="hidden" name="approval_type" id="rejection_type">
                    
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle"></i> You are about to reject <strong id="rejectionRecordNumber"></strong>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Rejection Reason <span class="text-danger">*</span></label>
                        <textarea name="rejection_reason" class="form-control" rows="4" required placeholder="Please provide a detailed reason for rejection..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">
                        <i class="fas fa-times"></i> Confirm Rejection
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Test function to verify modal system
function testModals() {
    console.log('Testing modal system...');
    console.log('Bootstrap loaded:', typeof bootstrap !== 'undefined');
    
    if (typeof bootstrap === 'undefined') {
        alert('❌ PROBLEM FOUND: Bootstrap JavaScript is NOT loaded!\n\nPlease check your includes/header.php file and make sure Bootstrap 5 JavaScript is included.');
        return;
    }
    
    // Test approval modal
    try {
        document.getElementById('approval_record_id').value = '999';
        document.getElementById('approval_type').value = 'test';
        document.getElementById('approvalRecordType').textContent = 'Test';
        document.getElementById('approvalRecordNumber').textContent = 'TEST-001';
        document.getElementById('approvalActionsList').innerHTML = '<li>This is a test modal</li>';
        
        var modalElement = document.getElementById('approvalModal');
        var modal = new bootstrap.Modal(modalElement);
        modal.show();
        
        alert('✅ SUCCESS! Modal system is working correctly.\n\nYou should see the approval modal open now.');
    } catch (error) {
        alert('❌ ERROR: ' + error.message + '\n\nPlease check the browser console for more details.');
        console.error('Modal test error:', error);
    }
}

// Ensure Bootstrap is loaded
if (typeof bootstrap === 'undefined') {
    console.error('Bootstrap is not loaded!');
    alert('Bootstrap JavaScript is not loaded. Please check your includes/header.php file.');
}

// Wait for DOM to be ready
document.addEventListener('DOMContentLoaded', function() {
    console.log('Pending approvals page loaded');
    console.log('Bootstrap version:', typeof bootstrap !== 'undefined' ? 'Loaded' : 'NOT LOADED');
    
    // Log all action buttons
    var approveButtons = document.querySelectorAll('.btn-approve');
    var rejectButtons = document.querySelectorAll('.btn-reject');
    console.log('Found approve buttons:', approveButtons.length);
    console.log('Found reject buttons:', rejectButtons.length);
});

function viewPaymentDetails(data) {
    console.log('View payment details clicked', data);
    
    let html = `
        <div class="row">
            <div class="col-md-6">
                <h6>Payment Information</h6>
                <table class="table table-sm">
                    <tr><td><strong>Payment Number:</strong></td><td>${data.payment_number}</td></tr>
                    <tr><td><strong>Amount:</strong></td><td>TZS ${parseFloat(data.amount).toLocaleString()}</td></tr>
                    <tr><td><strong>Payment Date:</strong></td><td>${data.payment_date}</td></tr>
                    <tr><td><strong>Method:</strong></td><td>${data.payment_method}</td></tr>
                </table>
            </div>
            <div class="col-md-6">
                <h6>Customer & Reservation</h6>
                <table class="table table-sm">
                    <tr><td><strong>Customer:</strong></td><td>${data.customer_name}</td></tr>
                    <tr><td><strong>Reservation:</strong></td><td>${data.reservation_number}</td></tr>
                    <tr><td><strong>Plot:</strong></td><td>${data.plot_number}</td></tr>
                    <tr><td><strong>Project:</strong></td><td>${data.project_name}</td></tr>
                </table>
            </div>
        </div>
        <div class="row">
            <div class="col-12">
                <h6>Account Information</h6>
                <div class="alert alert-info">
                    ${data.to_account_name ? 
                        `<strong>Will be credited to:</strong> ${data.to_bank_name} - ${data.to_account_name}` : 
                        'No account selected for this payment'
                    }
                </div>
            </div>
        </div>
        <div class="row">
            <div class="col-12">
                <h6>Outstanding Balance</h6>
                <div class="alert alert-warning">
                    <strong>Current Outstanding:</strong> TZS ${parseFloat(data.current_outstanding).toLocaleString()}
                </div>
            </div>
        </div>
    `;
    
    document.getElementById('paymentDetailsContent').innerHTML = html;
    
    try {
        var modalElement = document.getElementById('paymentDetailsModal');
        var modal = new bootstrap.Modal(modalElement);
        modal.show();
        console.log('Payment details modal opened');
    } catch (error) {
        console.error('Error opening payment details modal:', error);
        alert('Error opening modal. Bootstrap may not be loaded correctly.');
    }
}

function approveRecord(recordId, approvalType, recordNumber) {
    console.log('Approve clicked:', recordId, approvalType, recordNumber);
    
    try {
        document.getElementById('approval_record_id').value = recordId;
        document.getElementById('approval_type').value = approvalType;
        document.getElementById('approvalRecordType').textContent = approvalType.charAt(0).toUpperCase() + approvalType.slice(1);
        document.getElementById('approvalRecordNumber').textContent = recordNumber;
        
        let actions = '';
        if (approvalType === 'payment') {
            actions = `
                <li>Payment status will change to <strong>Approved</strong></li>
                <li>Payment amount will be added to the selected account balance</li>
                <li>Reservation status will be updated (Pending → Active or Active → Completed)</li>
                <li>Plot status will be updated (Available → Reserved or Reserved → Sold)</li>
                <li>Project counts will be recalculated</li>
                <li>Journal entry will be created automatically</li>
            `;
        } else if (approvalType === 'commission') {
            actions = `
                <li>Commission status will change to <strong>Paid</strong></li>
                <li>Commission will be marked as processed</li>
            `;
        } else if (approvalType === 'refund') {
            actions = `
                <li>Refund status will change to <strong>Approved</strong></li>
                <li>Amount will be deducted from the selected account</li>
            `;
        } else if (approvalType === 'purchase') {
            actions = `
                <li>Purchase order status will change to <strong>Approved</strong></li>
                <li>Purchase order can now be processed</li>
            `;
        } else if (approvalType === 'payroll') {
            actions = `
                <li>Payroll status will change to <strong>Processed</strong></li>
                <li>Employees will be notified</li>
            `;
        } else {
            actions = `<li>Record will be approved and marked as processed</li>`;
        }
        
        document.getElementById('approvalActionsList').innerHTML = actions;
        
        var modalElement = document.getElementById('approvalModal');
        var modal = new bootstrap.Modal(modalElement);
        modal.show();
        console.log('Approval modal opened');
    } catch (error) {
        console.error('Error opening approval modal:', error);
        alert('Error opening approval modal: ' + error.message);
    }
}

function rejectRecord(recordId, approvalType, recordNumber) {
    console.log('Reject clicked:', recordId, approvalType, recordNumber);
    
    try {
        document.getElementById('rejection_record_id').value = recordId;
        document.getElementById('rejection_type').value = approvalType;
        document.getElementById('rejectionRecordType').textContent = approvalType.charAt(0).toUpperCase() + approvalType.slice(1);
        document.getElementById('rejectionRecordNumber').textContent = recordNumber;
        
        var modalElement = document.getElementById('rejectionModal');
        var modal = new bootstrap.Modal(modalElement);
        modal.show();
        console.log('Rejection modal opened');
    } catch (error) {
        console.error('Error opening rejection modal:', error);
        alert('Error opening rejection modal: ' + error.message);
    }
}

// Auto-dismiss alerts after 5 seconds
setTimeout(function() {
    var alerts = document.querySelectorAll('.alert-success, .alert-danger, .alert-warning');
    alerts.forEach(function(alert) {
        if (!alert.classList.contains('workflow-box')) {
            alert.style.transition = 'opacity 0.5s';
            alert.style.opacity = '0';
            setTimeout(function() {
                alert.remove();
            }, 500);
        }
    });
}, 5000);
</script>

<?php require_once '../../includes/footer.php'; ?>