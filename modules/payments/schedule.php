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

// Get ALL reservations - don't filter by status
$query = "
    SELECT 
        r.reservation_id,
        r.reservation_number,
        r.total_amount,
        r.down_payment,
        r.payment_periods,
        r.reservation_date,
        r.status as reservation_status,
        c.customer_id,
        c.full_name as customer_name,
        COALESCE(c.phone, c.alternative_phone) as phone,
        pl.plot_number,
        pr.project_name
    FROM reservations r
    INNER JOIN customers c ON r.customer_id = c.customer_id
    INNER JOIN plots pl ON r.plot_id = pl.plot_id
    INNER JOIN projects pr ON pl.project_id = pr.project_id
    WHERE r.company_id = ?
    ORDER BY r.reservation_date DESC, r.reservation_number
";

$stmt = $conn->prepare($query);
$stmt->execute([$company_id]);
$reservations = $stmt->fetchAll(PDO::FETCH_ASSOC);

$schedules = [];
$sn = 1;

foreach ($reservations as $r) {
    // Skip if payment_periods is 0 or null
    if (empty($r['payment_periods']) || $r['payment_periods'] <= 0) {
        continue;
    }

    $installment_amount = round(($r['total_amount'] - $r['down_payment']) / $r['payment_periods'], 2);

    // ==================== DOWN PAYMENT ROW ====================
    $down_paid = 0;
    $down_payment_date = null;
    $down_payment_status = 'unpaid';
    
    $down_payment_query = $conn->prepare("
        SELECT SUM(amount) as total_amount, MAX(payment_date) as payment_date, status 
        FROM payments 
        WHERE reservation_id = ? 
        AND payment_type = 'down_payment'
        AND status = 'approved'
        GROUP BY reservation_id
    ");
    $down_payment_query->execute([$r['reservation_id']]);
    $dp = $down_payment_query->fetch(PDO::FETCH_ASSOC);
    
    if ($dp && $dp['total_amount']) {
        $down_paid = $dp['total_amount'];
        $down_payment_date = $dp['payment_date'];
        $down_payment_status = 'approved';
    } else {
        // Check for pending payments
        $pending_dp = $conn->prepare("
            SELECT status 
            FROM payments 
            WHERE reservation_id = ? 
            AND payment_type = 'down_payment'
            ORDER BY payment_date DESC
            LIMIT 1
        ");
        $pending_dp->execute([$r['reservation_id']]);
        $pending_status = $pending_dp->fetch(PDO::FETCH_ASSOC);
        if ($pending_status) {
            $down_payment_status = $pending_status['status'];
        }
    }

    $down_balance = $r['down_payment'] - $down_paid;

    // Determine down payment row styling
    if ($down_paid >= $r['down_payment']) {
        $row_class = 'table-success';
        $status = 'paid';
        $badge = 'paid';
    } elseif ($down_paid > 0) {
        $row_class = 'table-warning';
        $status = 'partially paid';
        $badge = 'partially-paid';
    } elseif ($down_payment_status === 'pending_approval') {
        $row_class = 'table-info';
        $status = 'pending approval';
        $badge = 'pending-approval';
    } elseif ($down_payment_status === 'rejected') {
        $row_class = 'table-danger';
        $status = 'rejected';
        $badge = 'rejected';
    } else {
        $row_class = 'table-danger';
        $status = 'not paid';
        $badge = 'overdue';
    }

    $schedules[] = [
        'sn' => $sn++,
        'due_date' => $r['reservation_date'],
        'customer_name' => $r['customer_name'],
        'phone' => $r['phone'] ?? '',
        'reservation_number' => $r['reservation_number'],
        'plot_number' => $r['plot_number'],
        'project_name' => $r['project_name'],
        'installment_number' => 'DP',
        'payment_periods' => $r['payment_periods'],
        'installment_amount' => $r['down_payment'],
        'paid_amount' => $down_paid,
        'balance' => $down_balance,
        'late_fee' => 0,
        'days_overdue' => 0,
        'row_class' => $row_class,
        'status_text' => $status,
        'badge' => $badge,
        'is_downpayment' => true,
        'payment_status' => $down_payment_status
    ];

    // ==================== INSTALLMENTS ====================
    // Get all approved installment payments for this reservation
    $installment_payments_query = $conn->prepare("
        SELECT 
            payment_id,
            SUM(amount) as total_amount,
            MAX(payment_date) as payment_date,
            status
        FROM payments 
        WHERE reservation_id = ? 
        AND payment_type = 'installment'
        AND status = 'approved'
        GROUP BY reservation_id
    ");
    $installment_payments_query->execute([$r['reservation_id']]);
    $installment_data = $installment_payments_query->fetch(PDO::FETCH_ASSOC);
    
    $total_installments_paid = $installment_data ? $installment_data['total_amount'] : 0;

    // Generate installment rows
    $remaining_paid = $total_installments_paid;
    
    for ($i = 1; $i <= $r['payment_periods']; $i++) {
        $due_date = date('Y-m-d', strtotime($r['reservation_date'] . " + $i months"));

        // Allocate payments to installments in order
        $paid = 0;
        if ($remaining_paid >= $installment_amount) {
            $paid = $installment_amount;
            $remaining_paid -= $installment_amount;
        } elseif ($remaining_paid > 0) {
            $paid = $remaining_paid;
            $remaining_paid = 0;
        }

        $balance = $installment_amount - $paid;
        $days_overdue = (strtotime($due_date) < time()) ? (int)((time() - strtotime($due_date)) / 86400) : 0;
        $is_overdue = $days_overdue > 0 && $balance > 0;

        // Determine row styling
        if ($paid >= $installment_amount) {
            $row_class = 'table-success';
            $status = 'paid';
            $badge = 'paid';
        } elseif ($paid > 0) {
            $row_class = 'table-warning';
            $status = 'partially paid';
            $badge = 'partially-paid';
        } elseif ($is_overdue) {
            $row_class = 'table-danger';
            $status = 'overdue';
            $badge = 'overdue';
        } else {
            $row_class = '';
            $status = 'pending';
            $badge = 'pending';
        }

        $schedules[] = [
            'sn' => $sn++,
            'due_date' => $due_date,
            'customer_name' => $r['customer_name'],
            'phone' => $r['phone'] ?? '',
            'reservation_number' => $r['reservation_number'],
            'plot_number' => $r['plot_number'],
            'project_name' => $r['project_name'],
            'installment_number' => $i,
            'payment_periods' => $r['payment_periods'],
            'installment_amount' => $installment_amount,
            'paid_amount' => $paid,
            'balance' => $balance,
            'late_fee' => 0,
            'days_overdue' => $days_overdue,
            'row_class' => $row_class,
            'status_text' => $status,
            'badge' => $badge,
            'is_downpayment' => false,
            'payment_status' => $paid > 0 ? 'approved' : 'unpaid'
        ];
    }
}

// Calculate totals
$total_expected = array_sum(array_column($schedules, 'installment_amount'));
$total_collected = array_sum(array_column($schedules, 'paid_amount'));
$total_outstanding = $total_expected - $total_collected;
$total_overdue = 0;
$total_pending_approval = 0;

foreach ($schedules as $s) {
    if ($s['days_overdue'] > 0 && $s['balance'] > 0 && $s['payment_status'] !== 'pending_approval') {
        $total_overdue += $s['balance'];
    }
    if ($s['payment_status'] === 'pending_approval') {
        $total_pending_approval += $s['installment_amount'];
    }
}

$page_title = 'Payment Recovery';
require_once '../../includes/header.php';
?>

<style>
.stats-card { 
    background:white; 
    border-radius:12px; 
    padding:1.5rem; 
    box-shadow:0 4px 20px rgba(0,0,0,0.1); 
    border-left:6px solid; 
    margin-bottom:1rem; 
    transition: transform 0.3s;
}
.stats-card:hover {
    transform: translateY(-5px);
}
.stats-card.primary { border-left-color:#007bff; }
.stats-card.success { border-left-color:#28a745; }
.stats-card.danger { border-left-color:#dc3545; }
.stats-card.warning { border-left-color:#ffc107; }
.stats-card.info { border-left-color:#17a2b8; }

.stats-number {
    font-size: 1.8rem;
    font-weight: 700;
    color: #2c3e50;
}

.stats-label {
    font-size: 0.9rem;
    color: #6c757d;
    text-transform: uppercase;
    font-weight: 600;
    margin-top: 0.5rem;
}

.legend-container { 
    display:flex; 
    gap:25px; 
    flex-wrap:wrap; 
    padding:18px; 
    background:#f8f9fa; 
    border-radius:12px; 
    margin:20px 0; 
    font-weight:600; 
}
.legend-item { display:flex; align-items:center; gap:12px; }
.legend-color { width:30px; height:24px; border-radius:6px; }
.legend-color.paid { background:#d4edda; }
.legend-color.partially { background:#fff3cd; }
.legend-color.overdue { background:#f8d7da; }
.legend-color.pending { background:#fff; border:2px solid #dee2e6; }
.legend-color.pending-approval { background:#d1ecf1; }
.legend-color.rejected { background:#f5c6cb; }

.status-badge { 
    padding:7px 16px; 
    border-radius:30px; 
    font-size:0.85rem; 
    font-weight:700; 
    text-transform:uppercase; 
}
.status-badge.paid { background:#28a745; color:white; }
.status-badge.partially-paid { background:#ffc107; color:black; }
.status-badge.overdue { background:#dc3545; color:white; }
.status-badge.pending { background:#6c757d; color:white; }
.status-badge.pending-approval { background:#17a2b8; color:white; }
.status-badge.rejected { background:#dc3545; color:white; }

.dp-badge { 
    background:#007bff; 
    color:white; 
    padding:4px 10px; 
    border-radius:20px; 
    font-size:0.8rem; 
    font-weight:bold; 
}
.installment-circle { 
    display:inline-block; 
    width:38px; 
    height:38px; 
    line-height:38px; 
    text-align:center; 
    border-radius:50%; 
    background:#007bff; 
    color:white; 
    font-weight:bold; 
    font-size:1rem; 
}

.table-info {
    background-color: #d1ecf1 !important;
}
</style>

<div class="content-header mb-4">
    <div class="container-fluid">
        <h1 class="m-0 fw-bold text-primary">
            <i class="fas fa-money-bill-wave me-2"></i>Payment Recovery Dashboard
        </h1>
        <p class="text-muted mb-0">Complete payment tracking: Down Payments + Installments (All Approved Payments)</p>
    </div>
</div>

<section class="content">
    <div class="container-fluid">

        <!-- Stats -->
        <div class="row g-4 mb-4">
            <div class="col-lg-3 col-md-6">
                <div class="stats-card primary">
                    <div class="stats-number">TSH <?=number_format($total_expected/1000000,1)?>M</div>
                    <div class="stats-label">Total Expected</div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6">
                <div class="stats-card success">
                    <div class="stats-number">TSH <?=number_format($total_collected/1000000,1)?>M</div>
                    <div class="stats-label">Collected (Approved)</div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6">
                <div class="stats-card danger">
                    <div class="stats-number">TSH <?=number_format($total_outstanding/1000000,1)?>M</div>
                    <div class="stats-label">Outstanding Balance</div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6">
                <div class="stats-card warning">
                    <div class="stats-number">TSH <?=number_format($total_overdue/1000000,1)?>M</div>
                    <div class="stats-label">Overdue Amount</div>
                </div>
            </div>
        </div>

        <!-- Legend -->
        <div class="legend-container">
            <div class="legend-item">
                <div class="legend-color paid"></div>
                <span>Paid (Approved)</span>
            </div>
            <div class="legend-item">
                <div class="legend-color partially"></div>
                <span>Partially Paid</span>
            </div>
            <div class="legend-item">
                <div class="legend-color overdue"></div>
                <span>Overdue / Not Paid</span>
            </div>
            <div class="legend-item">
                <div class="legend-color pending"></div>
                <span>Pending (Not Due)</span>
            </div>
            <div class="legend-item">
                <span class="dp-badge">DP</span>
                <span>Down Payment</span>
            </div>
        </div>

        <!-- Table -->
        <div class="card shadow-lg border-0">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0 align-middle">
                        <thead class="table-primary">
                            <tr>
                                <th>SN</th>
                                <th>Due Date</th>
                                <th>Customer</th>
                                <th>Reservation</th>
                                <th>Total Due</th>
                                <th>Inst Amount</th>
                                <th>Inst #</th>
                                <th>Penalty</th>
                                <th>Amount Paid</th>
                                <th>Outstanding</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($schedules)): ?>
                            <tr>
                                <td colspan="11" class="text-center py-5">
                                    <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                                    <p class="text-muted">No payment schedules found</p>
                                    <p class="text-muted small">Make sure reservations have payment_periods set</p>
                                </td>
                            </tr>
                            <?php else: ?>
                                <?php foreach ($schedules as $s): ?>
                                <tr class="<?=$s['row_class']?>">
                                    <td class="text-center fw-bold"><?=$s['sn']?></td>
                                    <td><?=date('d M Y', strtotime($s['due_date']))?></td>
                                    <td>
                                        <div class="fw-bold"><?=htmlspecialchars($s['customer_name'])?></div>
                                        <?php if ($s['phone']): ?>
                                            <small class="text-muted">
                                                <i class="fas fa-phone-alt me-1"></i><?=htmlspecialchars($s['phone'])?>
                                            </small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="fw-bold text-primary"><?=$s['reservation_number']?></div>
                                        <small class="text-muted">
                                            <i class="fas fa-map-marker-alt me-1"></i>Plot <?=$s['plot_number']?> - <?=htmlspecialchars($s['project_name'])?>
                                        </small>
                                    </td>
                                    <td class="text-end fw-bold">TSH <?=number_format($s['installment_amount'] + $s['late_fee'])?></td>
                                    <td class="text-end fw-bold">TSH <?=number_format($s['installment_amount'])?></td>
                                    <td class="text-center">
                                        <?php if ($s['is_downpayment']): ?>
                                            <span class="dp-badge">DP</span>
                                        <?php else: ?>
                                            <div class="installment-circle"><?=$s['installment_number']?></div>
                                            <small class="d-block text-muted">of <?=$s['payment_periods']?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end text-danger fw-bold">TSH <?=number_format($s['late_fee'])?></td>
                                    <td class="text-end text-success fw-bold">TSH <?=number_format($s['paid_amount'])?></td>
                                    <td class="text-end text-danger fw-bold">TSH <?=number_format($s['balance'])?></td>
                                    <td>
                                        <span class="status-badge <?=$s['badge']?>"><?=$s['status_text']?></span>
                                        <?php if ($s['days_overdue'] > 0 && !$s['is_downpayment']): ?>
                                            <div class="mt-1">
                                                <span class="badge bg-danger">
                                                    <i class="fas fa-exclamation-triangle me-1"></i><?=$s['days_overdue']?> days overdue
                                                </span>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                        <tfoot class="table-light">
                            <tr class="fw-bold">
                                <td colspan="4" class="text-end">TOTALS:</td>
                                <td class="text-end">TSH <?=number_format($total_expected)?></td>
                                <td colspan="3"></td>
                                <td class="text-end text-success">TSH <?=number_format($total_collected)?></td>
                                <td class="text-end text-danger">TSH <?=number_format($total_outstanding)?></td>
                                <td></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>

        <!-- Additional Info -->
        <div class="alert alert-success mt-4">
            <i class="fas fa-check-circle me-2"></i>
            <strong>Showing All Approved Payments:</strong> This dashboard displays all reservations with their complete payment schedules. 
            Only approved payments are counted in the totals.
        </div>
    </div>
</section>

<?php require_once '../../includes/footer.php'; ?>