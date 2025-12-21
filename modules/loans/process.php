<?php
/**
 * Loan Process Handler
 * Mkumbi Investments ERP System
 */

define('APP_ACCESS', true);
require_once '../../config/database.php';
require_once '../../includes/functions.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../../login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

$db = Database::getInstance();
$conn = $db->getConnection();
$company_id = $_SESSION['company_id'];
$user_id = $_SESSION['user_id'];

$action = $_POST['action'] ?? '';
$loan_id = (int)($_POST['loan_id'] ?? 0);

// Verify loan exists and belongs to company
$sql = "SELECT el.*, e.employee_id, e.first_name, e.last_name, u.email
        FROM employee_loans el
        JOIN employees e ON el.employee_id = e.employee_id
        LEFT JOIN users u ON e.user_id = u.id
        WHERE el.loan_id = ? AND el.company_id = ?";
$stmt = $conn->prepare($sql);
$stmt->execute([$loan_id, $company_id]);
$loan = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$loan) {
    $_SESSION['error_message'] = "Loan not found.";
    header('Location: approvals.php');
    exit;
}

try {
    switch ($action) {
        case 'approve':
            // Check permission
            if (!hasPermission($conn, $user_id, ['HR_OFFICER', 'FINANCE_OFFICER', 'COMPANY_ADMIN', 'SUPER_ADMIN'])) {
                throw new Exception("You don't have permission to approve loans.");
            }
            
            if ($loan['status'] !== 'PENDING') {
                throw new Exception("Only pending loans can be approved.");
            }
            
            $comments = sanitize($_POST['comments'] ?? '');
            
            $sql = "UPDATE employee_loans 
                    SET status = 'APPROVED', approved_by = ?, approved_at = NOW(), approval_comments = ?
                    WHERE loan_id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->execute([$user_id, $comments, $loan_id]);
            
            logAudit($conn, $company_id, $user_id, 'approve', 'loans', 'employee_loans', $loan_id, 
                     ['status' => 'PENDING'], ['status' => 'APPROVED']);
            
            // TODO: Send notification to employee
            
            $_SESSION['success_message'] = "Loan approved successfully.";
            break;
            
        case 'reject':
            // Check permission
            if (!hasPermission($conn, $user_id, ['HR_OFFICER', 'FINANCE_OFFICER', 'COMPANY_ADMIN', 'SUPER_ADMIN'])) {
                throw new Exception("You don't have permission to reject loans.");
            }
            
            if ($loan['status'] !== 'PENDING') {
                throw new Exception("Only pending loans can be rejected.");
            }
            
            $rejection_reason = sanitize($_POST['rejection_reason'] ?? '');
            if (empty($rejection_reason)) {
                throw new Exception("Rejection reason is required.");
            }
            
            $sql = "UPDATE employee_loans 
                    SET status = 'REJECTED', approved_by = ?, approved_at = NOW(), rejection_reason = ?
                    WHERE loan_id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->execute([$user_id, $rejection_reason, $loan_id]);
            
            logAudit($conn, $company_id, $user_id, 'reject', 'loans', 'employee_loans', $loan_id,
                     ['status' => 'PENDING'], ['status' => 'REJECTED', 'reason' => $rejection_reason]);
            
            $_SESSION['success_message'] = "Loan application rejected.";
            break;
            
        case 'disburse':
            // Check permission
            if (!hasPermission($conn, $user_id, ['FINANCE_OFFICER', 'COMPANY_ADMIN', 'SUPER_ADMIN'])) {
                throw new Exception("You don't have permission to disburse loans.");
            }
            
            if ($loan['status'] !== 'APPROVED') {
                throw new Exception("Only approved loans can be disbursed.");
            }
            
            $disbursement_date = $_POST['disbursement_date'] ?? date('Y-m-d');
            $payment_method = sanitize($_POST['payment_method'] ?? 'BANK_TRANSFER');
            $payment_reference = sanitize($_POST['payment_reference'] ?? '');
            
            $conn->beginTransaction();
            
            // Update loan status
            $sql = "UPDATE employee_loans 
                    SET status = 'DISBURSED', disbursed_at = ?, disbursed_by = ?,
                        payment_method = ?, payment_reference = ?
                    WHERE loan_id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->execute([$disbursement_date, $user_id, $payment_method, $payment_reference, $loan_id]);
            
            // Update repayment schedule due dates based on disbursement date
            $sql = "SELECT * FROM loan_repayment_schedule WHERE loan_id = ? ORDER BY installment_number";
            $stmt = $conn->prepare($sql);
            $stmt->execute([$loan_id]);
            $schedules = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($schedules as $i => $schedule) {
                $due_date = date('Y-m-d', strtotime($disbursement_date . ' +' . ($i + 1) . ' months'));
                $sql = "UPDATE loan_repayment_schedule SET due_date = ? WHERE schedule_id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->execute([$due_date, $schedule['schedule_id']]);
            }
            
            // Set first payment start date
            $first_payment_date = date('Y-m-d', strtotime($disbursement_date . ' +1 month'));
            $sql = "UPDATE employee_loans SET repayment_start_date = ? WHERE loan_id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->execute([$first_payment_date, $loan_id]);
            
            $conn->commit();
            
            logAudit($conn, $company_id, $user_id, 'disburse', 'loans', 'employee_loans', $loan_id,
                     ['status' => 'APPROVED'], ['status' => 'DISBURSED', 'date' => $disbursement_date]);
            
            $_SESSION['success_message'] = "Loan disbursed successfully. Repayments start from " . date('M Y', strtotime($first_payment_date));
            break;
            
        case 'record_payment':
            // Check permission
            if (!hasPermission($conn, $user_id, ['FINANCE_OFFICER', 'COMPANY_ADMIN', 'SUPER_ADMIN'])) {
                throw new Exception("You don't have permission to record payments.");
            }
            
            if (!in_array($loan['status'], ['DISBURSED', 'ACTIVE'])) {
                throw new Exception("Can only record payments for disbursed or active loans.");
            }
            
            $payment_amount = (float)$_POST['payment_amount'];
            $payment_date = $_POST['payment_date'] ?? date('Y-m-d');
            $payment_method = sanitize($_POST['payment_method'] ?? 'SALARY_DEDUCTION');
            $payment_reference = sanitize($_POST['payment_reference'] ?? '');
            
            if ($payment_amount <= 0) {
                throw new Exception("Payment amount must be greater than zero.");
            }
            
            if ($payment_amount > $loan['total_outstanding']) {
                throw new Exception("Payment amount cannot exceed outstanding balance.");
            }
            
            $conn->beginTransaction();
            
            // Get next pending installment
            $sql = "SELECT * FROM loan_repayment_schedule 
                    WHERE loan_id = ? AND status = 'PENDING' 
                    ORDER BY installment_number LIMIT 1";
            $stmt = $conn->prepare($sql);
            $stmt->execute([$loan_id]);
            $installment = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Record payment
            $payment_ref = generateReference($conn, 'LP', 'loan_payments', 'payment_reference', $company_id);
            $sql = "INSERT INTO loan_payments (
                        loan_id, schedule_id, payment_reference, payment_date, amount_paid,
                        principal_paid, interest_paid, payment_method, payment_reference_external,
                        recorded_by
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            
            // Allocate payment to principal and interest
            $interest_portion = min($payment_amount, $loan['interest_outstanding']);
            $principal_portion = $payment_amount - $interest_portion;
            
            $stmt->execute([
                $loan_id, $installment ? $installment['schedule_id'] : null, $payment_ref,
                $payment_date, $payment_amount, $principal_portion, $interest_portion,
                $payment_method, $payment_reference, $user_id
            ]);
            
            // Update loan outstanding balances
            $new_principal = $loan['principal_outstanding'] - $principal_portion;
            $new_interest = $loan['interest_outstanding'] - $interest_portion;
            $new_total = $new_principal + $new_interest;
            
            $new_status = $new_total <= 0 ? 'COMPLETED' : ($loan['status'] === 'DISBURSED' ? 'ACTIVE' : $loan['status']);
            
            $sql = "UPDATE employee_loans 
                    SET principal_outstanding = ?, interest_outstanding = ?, total_outstanding = ?,
                        status = ?, last_payment_date = ?
                    WHERE loan_id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->execute([$new_principal, $new_interest, $new_total, $new_status, $payment_date, $loan_id]);
            
            // Update installment status if fully paid
            if ($installment && $payment_amount >= $installment['total_amount']) {
                $sql = "UPDATE loan_repayment_schedule SET status = 'PAID', paid_date = ? WHERE schedule_id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->execute([$payment_date, $installment['schedule_id']]);
            }
            
            $conn->commit();
            
            logAudit($conn, $company_id, $user_id, 'payment', 'loans', 'loan_payments', 
                     $conn->lastInsertId(), null, ['amount' => $payment_amount]);
            
            $_SESSION['success_message'] = "Payment of " . formatCurrency($payment_amount) . " recorded successfully.";
            break;
            
        case 'cancel':
            // Employee can cancel their own pending loan
            $employee = getEmployeeByUserId($conn, $user_id, $company_id);
            
            if (!$employee || $loan['employee_id'] != $employee['employee_id']) {
                if (!hasPermission($conn, $user_id, ['HR_OFFICER', 'COMPANY_ADMIN', 'SUPER_ADMIN'])) {
                    throw new Exception("You can only cancel your own loan applications.");
                }
            }
            
            if ($loan['status'] !== 'PENDING') {
                throw new Exception("Only pending loan applications can be cancelled.");
            }
            
            $sql = "UPDATE employee_loans SET status = 'CANCELLED', cancelled_at = NOW() WHERE loan_id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->execute([$loan_id]);
            
            logAudit($conn, $company_id, $user_id, 'cancel', 'loans', 'employee_loans', $loan_id);
            
            $_SESSION['success_message'] = "Loan application cancelled.";
            break;
            
        default:
            throw new Exception("Invalid action.");
    }
    
} catch (Exception $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    $_SESSION['error_message'] = $e->getMessage();
}

// Redirect based on action
$redirect = $_POST['redirect'] ?? 'approvals.php';
header('Location: ' . $redirect);
exit;
