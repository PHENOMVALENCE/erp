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

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax'])) {
    header('Content-Type: application/json');
    
    try {
        if ($_POST['action'] === 'calculate_commission') {
            // Validate required fields
            if (empty($_POST['reservation_id']) || empty($_POST['commission_rate'])) {
                throw new Exception('Reservation and commission rate are required');
            }
            
            // Validate agent selection
            $recipient_type = $_POST['recipient_type'] ?? 'user';
            if ($recipient_type === 'user' && empty($_POST['user_id'])) {
                throw new Exception('Please select a user');
            }
            if (($recipient_type === 'external' || $recipient_type === 'consultant') && empty($_POST['recipient_name'])) {
                throw new Exception('Please enter recipient name');
            }
            
            // Get reservation details
            $reservation_stmt = $conn->prepare("
                SELECT r.*, c.first_name, c.last_name, p.plot_number, pr.project_name
                FROM reservations r
                INNER JOIN customers c ON r.customer_id = c.customer_id
                INNER JOIN plots p ON r.plot_id = p.plot_id
                INNER JOIN projects pr ON p.project_id = pr.project_id
                WHERE r.reservation_id = ? AND r.company_id = ?
            ");
            $reservation_stmt->execute([$_POST['reservation_id'], $company_id]);
            $reservation = $reservation_stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$reservation) {
                throw new Exception('Reservation not found');
            }
            
            // Check if commission already exists for this reservation and recipient
            if ($recipient_type === 'user') {
                $check = $conn->prepare("
                    SELECT commission_id FROM commissions 
                    WHERE reservation_id = ? AND user_id = ? AND company_id = ?
                ");
                $check->execute([$_POST['reservation_id'], $_POST['user_id'], $company_id]);
            } else {
                $check = $conn->prepare("
                    SELECT commission_id FROM commissions 
                    WHERE reservation_id = ? AND recipient_name = ? AND company_id = ?
                ");
                $check->execute([$_POST['reservation_id'], $_POST['recipient_name'], $company_id]);
            }
            
            if ($check->rowCount() > 0) {
                throw new Exception('Commission already exists for this reservation and recipient');
            }
            
            // Calculate commission
            $commission_rate = (float)$_POST['commission_rate'];
            $reservation_amount = (float)$reservation['total_amount'];
            $commission_amount = ($reservation_amount * $commission_rate) / 100;
            
            // Get recipient name
            $recipient_name = '';
            $recipient_phone = $_POST['recipient_phone'] ?? null;
            
            if ($recipient_type === 'user') {
                $user_stmt = $conn->prepare("SELECT first_name, last_name, phone1 FROM users WHERE user_id = ?");
                $user_stmt->execute([$_POST['user_id']]);
                $user_data = $user_stmt->fetch(PDO::FETCH_ASSOC);
                $recipient_name = $user_data['first_name'] . ' ' . $user_data['last_name'];
                $recipient_phone = $recipient_phone ?: $user_data['phone1'];
            } else {
                $recipient_name = $_POST['recipient_name'];
            }
            
            // Insert commission
            $stmt = $conn->prepare("
                INSERT INTO commissions (
                    company_id, reservation_id, recipient_type, user_id, recipient_name,
                    recipient_phone, commission_type, commission_percentage, commission_amount,
                    payment_status, remarks, created_by, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', ?, ?, NOW())
            ");
            
            $stmt->execute([
                $company_id,
                $_POST['reservation_id'],
                $recipient_type,
                ($recipient_type === 'user' ? $_POST['user_id'] : null),
                $recipient_name,
                $recipient_phone,
                $_POST['commission_type'] ?? 'sales',
                $commission_rate,
                $commission_amount,
                $_POST['remarks'] ?? null,
                $user_id
            ]);
            
            echo json_encode([
                'success' => true, 
                'message' => 'Commission calculated and saved successfully',
                'commission_amount' => $commission_amount
            ]);
            
        } elseif ($_POST['action'] === 'update_commission') {
            if (empty($_POST['commission_id'])) {
                throw new Exception('Commission ID is required');
            }
            
            // Check if commission is still pending
            $check = $conn->prepare("
                SELECT payment_status FROM commissions 
                WHERE commission_id = ? AND company_id = ?
            ");
            $check->execute([$_POST['commission_id'], $company_id]);
            $commission = $check->fetch(PDO::FETCH_ASSOC);
            
            if (!$commission) {
                throw new Exception('Commission not found');
            }
            
            if ($commission['payment_status'] !== 'pending') {
                throw new Exception('Only pending commissions can be updated');
            }
            
            $stmt = $conn->prepare("
                UPDATE commissions SET
                    commission_percentage = ?,
                    commission_amount = ?,
                    remarks = ?,
                    updated_at = NOW()
                WHERE commission_id = ? AND company_id = ?
            ");
            
            $stmt->execute([
                $_POST['commission_percentage'],
                $_POST['commission_amount'],
                $_POST['remarks'] ?? null,
                $_POST['commission_id'],
                $company_id
            ]);
            
            echo json_encode(['success' => true, 'message' => 'Commission updated successfully']);
            
        } elseif ($_POST['action'] === 'delete_commission') {
            if (empty($_POST['commission_id'])) {
                throw new Exception('Commission ID is required');
            }
            
            // Check if commission is still pending
            $check = $conn->prepare("
                SELECT payment_status FROM commissions 
                WHERE commission_id = ? AND company_id = ?
            ");
            $check->execute([$_POST['commission_id'], $company_id]);
            $commission = $check->fetch(PDO::FETCH_ASSOC);
            
            if (!$commission) {
                throw new Exception('Commission not found');
            }
            
            if ($commission['payment_status'] !== 'pending') {
                throw new Exception('Only pending commissions can be deleted');
            }
            
            $stmt = $conn->prepare("DELETE FROM commissions WHERE commission_id = ? AND company_id = ?");
            $stmt->execute([$_POST['commission_id'], $company_id]);
            
            echo json_encode(['success' => true, 'message' => 'Commission deleted successfully']);
            
        } elseif ($_POST['action'] === 'get_commission') {
            if (empty($_POST['commission_id'])) {
                throw new Exception('Commission ID is required');
            }
            
            $stmt = $conn->prepare("
                SELECT 
                    c.*,
                    r.reservation_number,
                    r.reservation_date,
                    r.total_amount as reservation_amount,
                    cu.first_name as customer_first_name,
                    cu.last_name as customer_last_name,
                    p.plot_number,
                    pr.project_name
                FROM commissions c
                INNER JOIN reservations r ON c.reservation_id = r.reservation_id
                INNER JOIN customers cu ON r.customer_id = cu.customer_id
                INNER JOIN plots p ON r.plot_id = p.plot_id
                INNER JOIN projects pr ON p.project_id = pr.project_id
                WHERE c.commission_id = ? AND c.company_id = ?
            ");
            $stmt->execute([$_POST['commission_id'], $company_id]);
            $commission = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$commission) {
                throw new Exception('Commission not found');
            }
            
            echo json_encode(['success' => true, 'commission' => $commission]);
        }
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

// Fetch commissions
try {
    $status_filter = $_GET['status'] ?? 'all';
    
    $where_conditions = ["c.company_id = ?"];
    $params = [$company_id];
    
    if ($status_filter !== 'all') {
        $where_conditions[] = "c.payment_status = ?";
        $params[] = $status_filter;
    }
    
    $where_clause = implode(' AND ', $where_conditions);
    
    $stmt = $conn->prepare("
        SELECT 
            c.*,
            r.reservation_number,
            r.reservation_date,
            r.total_amount as reservation_amount,
            cu.first_name as customer_first_name,
            cu.last_name as customer_last_name,
            p.plot_number,
            pr.project_name,
            creator.first_name as creator_first_name,
            creator.last_name as creator_last_name,
            cp.payment_date,
            cp.amount_paid
        FROM commissions c
        INNER JOIN reservations r ON c.reservation_id = r.reservation_id
        INNER JOIN customers cu ON r.customer_id = cu.customer_id
        INNER JOIN plots p ON r.plot_id = p.plot_id
        INNER JOIN projects pr ON p.project_id = pr.project_id
        LEFT JOIN users creator ON c.created_by = creator.user_id
        LEFT JOIN commission_payments cp ON c.commission_id = cp.commission_id
        WHERE $where_clause
        ORDER BY c.created_at DESC
    ");
    $stmt->execute($params);
    $commissions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get statistics
    $stats = $conn->prepare("
        SELECT 
            COUNT(*) as total_commissions,
            COALESCE(SUM(CASE WHEN payment_status = 'pending' THEN 1 ELSE 0 END), 0) as pending_count,
            COALESCE(SUM(CASE WHEN payment_status = 'paid' THEN 1 ELSE 0 END), 0) as paid_count,
            COALESCE(SUM(CASE WHEN payment_status = 'cancelled' THEN 1 ELSE 0 END), 0) as cancelled_count,
            COALESCE(SUM(commission_amount), 0) as total_amount,
            COALESCE(SUM(CASE WHEN payment_status = 'pending' THEN commission_amount ELSE 0 END), 0) as pending_amount,
            COALESCE(SUM(CASE WHEN payment_status = 'paid' THEN commission_amount ELSE 0 END), 0) as paid_amount
        FROM commissions 
        WHERE company_id = ?
    ");
    $stats->execute([$company_id]);
    $statistics = $stats->fetch(PDO::FETCH_ASSOC);
    
    // Get unpaid reservations (for commission calculation)
    $unpaid_reservations = $conn->prepare("
        SELECT 
            r.reservation_id,
            r.reservation_number,
            r.reservation_date,
            r.total_amount,
            c.first_name,
            c.last_name,
            p.plot_number,
            pr.project_name
        FROM reservations r
        INNER JOIN customers c ON r.customer_id = c.customer_id
        INNER JOIN plots p ON r.plot_id = p.plot_id
        INNER JOIN projects pr ON p.project_id = pr.project_id
        LEFT JOIN commissions com ON r.reservation_id = com.reservation_id
        WHERE r.company_id = ? 
        AND r.status = 'active'
        AND com.commission_id IS NULL
        ORDER BY r.reservation_date DESC
    ");
    $unpaid_reservations->execute([$company_id]);
    $available_reservations = $unpaid_reservations->fetchAll(PDO::FETCH_ASSOC);
    
    // Get users for dropdown (sales team)
    $users_stmt = $conn->prepare("
        SELECT u.user_id, u.first_name, u.last_name, u.email, u.phone1
        FROM users u
        WHERE u.company_id = ? 
        AND u.is_active = 1
        AND u.can_get_commission = 1
        ORDER BY u.first_name, u.last_name
    ");
    $users_stmt->execute([$company_id]);
    $users = $users_stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    $error_message = "Error fetching commissions: " . $e->getMessage();
    $commissions = [];
    $statistics = [
        'total_commissions' => 0, 'pending_count' => 0, 'paid_count' => 0, 
        'cancelled_count' => 0, 'total_amount' => 0,
        'pending_amount' => 0, 'paid_amount' => 0
    ];
    $available_reservations = [];
    $users = [];
}

$page_title = 'Commission Management';
require_once '../../includes/header.php';
?>

<style>
.commission-card {
    border-radius: 12px;
    border: none;
    box-shadow: 0 2px 8px rgba(0,0,0,0.08);
    transition: all 0.3s;
}

.commission-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 4px 16px rgba(0,0,0,0.12);
}

.stats-card {
    border-radius: 12px;
    border: none;
    box-shadow: 0 2px 8px rgba(0,0,0,0.08);
}

.status-badge {
    padding: 6px 12px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
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

.filter-tabs {
    display: flex;
    gap: 0.5rem;
    margin-bottom: 1.5rem;
    flex-wrap: wrap;
}

.filter-tab {
    padding: 0.5rem 1rem;
    border-radius: 8px;
    border: 2px solid #e5e7eb;
    background: #fff;
    cursor: pointer;
    transition: all 0.2s;
}

.filter-tab:hover {
    border-color: #667eea;
}

.filter-tab.active {
    background: #667eea;
    color: #fff;
    border-color: #667eea;
}

.commission-amount {
    font-size: 1.25rem;
    font-weight: 700;
    color: #059669;
}

.recipient-type-option {
    padding: 1rem;
    border: 2px solid #e5e7eb;
    border-radius: 8px;
    cursor: pointer;
    transition: all 0.2s;
    text-align: center;
}

.recipient-type-option:hover {
    border-color: #667eea;
    background: #f9fafb;
}

.recipient-type-option.active {
    border-color: #667eea;
    background: #ede9fe;
}

.recipient-type-option input[type="radio"] {
    display: none;
}
</style>

<!-- Content Header -->
<div class="content-header">
    <div class="container-fluid">
        <div class="row mb-4">
            <div class="col-sm-6">
                <h1 class="m-0 fw-bold">
                    <i class="fas fa-percentage text-primary me-2"></i>Commission Management
                </h1>
                <p class="text-muted small mb-0 mt-1">Calculate and track sales commissions</p>
            </div>
            <div class="col-sm-6">
                <div class="float-sm-end">
                    <button class="btn btn-primary" onclick="showCommissionModal()">
                        <i class="fas fa-plus me-2"></i>Calculate Commission
                    </button>
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
        
        <!-- Statistics Cards -->
        <div class="row g-3 mb-4">
            <div class="col-xl-3 col-md-6">
                <div class="card stats-card">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="flex-grow-1">
                                <h6 class="text-muted mb-2">Total</h6>
                                <h2 class="fw-bold mb-0"><?php echo (int)$statistics['total_commissions']; ?></h2>
                            </div>
                            <div class="fs-1 text-primary">
                                <i class="fas fa-list"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-xl-3 col-md-6">
                <div class="card stats-card">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="flex-grow-1">
                                <h6 class="text-muted mb-2">Pending</h6>
                                <h2 class="fw-bold mb-0 text-warning"><?php echo (int)$statistics['pending_count']; ?></h2>
                            </div>
                            <div class="fs-1 text-warning">
                                <i class="fas fa-clock"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-xl-3 col-md-6">
                <div class="card stats-card">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="flex-grow-1">
                                <h6 class="text-muted mb-2">Paid</h6>
                                <h2 class="fw-bold mb-0 text-success"><?php echo (int)$statistics['paid_count']; ?></h2>
                            </div>
                            <div class="fs-1 text-success">
                                <i class="fas fa-money-bill-wave"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-xl-3 col-md-6">
                <div class="card stats-card">
                    <div class="card-body">
                        <h6 class="text-muted mb-2">Total Value</h6>
                        <h2 class="fw-bold mb-2">TZS <?php echo number_format((float)$statistics['total_amount'], 0); ?></h2>
                        <div class="row g-2 small">
                            <div class="col-6">
                                <span class="text-warning">●</span> Pending: TZS <?php echo number_format((float)$statistics['pending_amount'], 0); ?>
                            </div>
                            <div class="col-6">
                                <span class="text-success">●</span> Paid: TZS <?php echo number_format((float)$statistics['paid_amount'], 0); ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Filter Tabs -->
        <div class="filter-tabs">
            <div class="filter-tab <?php echo $status_filter === 'all' ? 'active' : ''; ?>" 
                 onclick="filterByStatus('all')">
                <i class="fas fa-globe me-1"></i>All
            </div>
            <div class="filter-tab <?php echo $status_filter === 'pending' ? 'active' : ''; ?>" 
                 onclick="filterByStatus('pending')">
                <i class="fas fa-clock me-1"></i>Pending
            </div>
            <div class="filter-tab <?php echo $status_filter === 'paid' ? 'active' : ''; ?>" 
                 onclick="filterByStatus('paid')">
                <i class="fas fa-money-bill-wave me-1"></i>Paid
            </div>
            <div class="filter-tab <?php echo $status_filter === 'cancelled' ? 'active' : ''; ?>" 
                 onclick="filterByStatus('cancelled')">
                <i class="fas fa-times-circle me-1"></i>Cancelled
            </div>
        </div>
        
        <!-- Commissions List -->
        <?php if (count($commissions) > 0): ?>
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-list me-2"></i>Commission Records
                </h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover" id="commissionsTable">
                        <thead>
                            <tr>
                                <th>Reservation Details</th>
                                <th>Recipient</th>
                                <th>Type</th>
                                <th>Rate</th>
                                <th>Amount</th>
                                <th>Status</th>
                                <th>Created</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($commissions as $commission): ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars($commission['customer_first_name'] . ' ' . $commission['customer_last_name']); ?></strong><br>
                                    <small class="text-muted">
                                        <?php echo htmlspecialchars($commission['project_name']); ?> - 
                                        Plot <?php echo htmlspecialchars($commission['plot_number']); ?>
                                    </small><br>
                                    <small class="text-muted">
                                        Amount: TZS <?php echo number_format((float)$commission['reservation_amount'], 0); ?>
                                    </small>
                                </td>
                                <td>
                                    <strong><?php echo htmlspecialchars($commission['recipient_name']); ?></strong>
                                    <span class="badge bg-secondary ms-1"><?php echo ucfirst($commission['recipient_type']); ?></span>
                                    <?php if ($commission['recipient_phone']): ?>
                                    <br><small class="text-muted"><?php echo htmlspecialchars($commission['recipient_phone']); ?></small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge bg-info">
                                        <?php echo ucfirst($commission['commission_type']); ?>
                                    </span>
                                </td>
                                <td><?php echo number_format((float)$commission['commission_percentage'], 2); ?>%</td>
                                <td>
                                    <span class="commission-amount">
                                        TZS <?php echo number_format((float)$commission['commission_amount'], 2); ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="status-badge <?php 
                                        echo match($commission['payment_status']) {
                                            'pending' => 'bg-warning text-dark',
                                            'paid' => 'bg-success text-white',
                                            'cancelled' => 'bg-danger text-white',
                                            default => 'bg-secondary text-white'
                                        };
                                    ?>">
                                        <?php echo ucfirst($commission['payment_status']); ?>
                                    </span>
                                    <?php if ($commission['payment_status'] === 'paid' && $commission['payment_date']): ?>
                                    <br><small class="text-success">
                                        Paid: <?php echo date('M d, Y', strtotime($commission['payment_date'])); ?>
                                    </small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php echo htmlspecialchars($commission['creator_first_name'] . ' ' . $commission['creator_last_name']); ?>
                                    <br><small class="text-muted"><?php echo date('M d, Y', strtotime($commission['created_at'])); ?></small>
                                </td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <button class="btn btn-outline-primary" onclick="viewCommission(<?php echo $commission['commission_id']; ?>)" title="View">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <?php if ($commission['payment_status'] === 'pending'): ?>
                                        <button class="btn btn-outline-success" onclick="editCommission(<?php echo $commission['commission_id']; ?>)" title="Edit">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button class="btn btn-outline-danger" onclick="deleteCommission(<?php echo $commission['commission_id']; ?>)" title="Delete">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php else: ?>
        <div class="card">
            <div class="card-body text-center py-5">
                <i class="fas fa-percentage fa-4x text-muted mb-4"></i>
                <h4 class="text-muted">No Commissions Found</h4>
                <p class="text-muted">Calculate commissions for completed reservations</p>
                <button class="btn btn-primary mt-3" onclick="showCommissionModal()">
                    <i class="fas fa-plus me-2"></i>Calculate First Commission
                </button>
            </div>
        </div>
        <?php endif; ?>
        
    </div>
</section>

<!-- Commission Modal -->
<div class="modal fade" id="commissionModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-percentage me-2"></i>
                    <span id="modalTitle">Calculate Commission</span>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="commissionForm">
                <div class="modal-body">
                    <input type="hidden" name="ajax" value="1">
                    <input type="hidden" name="action" value="calculate_commission">
                    <input type="hidden" name="commission_id" id="commission_id">
                    
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label">Reservation <span class="text-danger">*</span></label>
                            <select class="form-select" name="reservation_id" id="reservation_id" required onchange="updateReservationInfo()">
                                <option value="">-- Select Reservation --</option>
                                <?php foreach ($available_reservations as $reservation): ?>
                                <option value="<?php echo $reservation['reservation_id']; ?>" 
                                        data-amount="<?php echo $reservation['total_amount']; ?>"
                                        data-customer="<?php echo htmlspecialchars($reservation['first_name'] . ' ' . $reservation['last_name']); ?>"
                                        data-plot="<?php echo htmlspecialchars($reservation['plot_number']); ?>"
                                        data-project="<?php echo htmlspecialchars($reservation['project_name']); ?>">
                                    <?php echo htmlspecialchars($reservation['first_name'] . ' ' . $reservation['last_name']); ?> - 
                                    <?php echo htmlspecialchars($reservation['project_name']); ?> 
                                    (Plot <?php echo htmlspecialchars($reservation['plot_number']); ?>) - 
                                    TZS <?php echo number_format((float)$reservation['total_amount'], 0); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="col-12" id="reservationInfoDiv" style="display: none;">
                            <div class="alert alert-info">
                                <h6 class="fw-bold mb-2">Reservation Information</h6>
                                <div class="row">
                                    <div class="col-6">
                                        <small class="text-muted">Customer:</small><br>
                                        <strong id="info_customer"></strong>
                                    </div>
                                    <div class="col-6">
                                        <small class="text-muted">Amount:</small><br>
                                        <strong id="info_amount"></strong>
                                    </div>
                                    <div class="col-12 mt-2">
                                        <small class="text-muted">Property:</small><br>
                                        <strong id="info_property"></strong>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-12">
                            <label class="form-label">Recipient Type <span class="text-danger">*</span></label>
                            <div class="row g-2">
                                <div class="col-md-4">
                                    <label class="recipient-type-option active">
                                        <input type="radio" name="recipient_type" value="user" checked onchange="toggleRecipientFields()">
                                        <div>
                                            <i class="fas fa-user fa-2x mb-2 text-primary"></i>
                                            <div class="fw-bold">System User</div>
                                            <small class="text-muted">Employee</small>
                                        </div>
                                    </label>
                                </div>
                                <div class="col-md-4">
                                    <label class="recipient-type-option">
                                        <input type="radio" name="recipient_type" value="external" onchange="toggleRecipientFields()">
                                        <div>
                                            <i class="fas fa-user-tie fa-2x mb-2 text-success"></i>
                                            <div class="fw-bold">External Agent</div>
                                            <small class="text-muted">Third party</small>
                                        </div>
                                    </label>
                                </div>
                                <div class="col-md-4">
                                    <label class="recipient-type-option">
                                        <input type="radio" name="recipient_type" value="consultant" onchange="toggleRecipientFields()">
                                        <div>
                                            <i class="fas fa-handshake fa-2x mb-2 text-info"></i>
                                            <div class="fw-bold">Consultant</div>
                                            <small class="text-muted">Advisor</small>
                                        </div>
                                    </label>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-12" id="userSelectDiv">
                            <label class="form-label">Select User <span class="text-danger">*</span></label>
                            <select class="form-select" name="user_id" id="user_id">
                                <option value="">-- Select User --</option>
                                <?php foreach ($users as $usr): ?>
                                <option value="<?php echo $usr['user_id']; ?>">
                                    <?php echo htmlspecialchars($usr['first_name'] . ' ' . $usr['last_name']); ?> - 
                                    <?php echo htmlspecialchars($usr['email']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="col-12" id="manualRecipientDiv" style="display: none;">
                            <div class="row g-3">
                                <div class="col-md-8">
                                    <label class="form-label">Recipient Name <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" name="recipient_name" id="recipient_name" 
                                           placeholder="Enter recipient name">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Phone Number</label>
                                    <input type="text" class="form-control" name="recipient_phone" id="recipient_phone" 
                                           placeholder="e.g., 0712345678">
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label">Commission Type <span class="text-danger">*</span></label>
                            <select class="form-select" name="commission_type" id="commission_type" required>
                                <option value="sales">Sales Commission</option>
                                <option value="referral">Referral Commission</option>
                                <option value="marketing">Marketing Commission</option>
                                <option value="consultant">Consultant Fee</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label">Commission Rate (%) <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" name="commission_rate" id="commission_rate" 
                                   step="0.01" min="0" max="100" required onchange="calculateAmount()">
                        </div>
                        
                        <div class="col-12">
                            <label class="form-label">Commission Amount (TZS)</label>
                            <input type="number" class="form-control" name="commission_amount" id="commission_amount" 
                                   step="0.01" min="0" readonly>
                            <small class="text-muted">Automatically calculated based on reservation amount and rate</small>
                        </div>
                        
                        <div class="col-12">
                            <label class="form-label">Remarks</label>
                            <textarea class="form-control" name="remarks" id="remarks" rows="3" 
                                      placeholder="Additional notes or comments"></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-2"></i>Save Commission
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- View Commission Modal -->
<div class="modal fade" id="viewModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-eye me-2"></i>Commission Details
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="commissionDetailsContent">
                <!-- Content loaded via JavaScript -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>

<script>
let commissionModal, viewModal;

$(document).ready(function() {
    commissionModal = new bootstrap.Modal(document.getElementById('commissionModal'));
    viewModal = new bootstrap.Modal(document.getElementById('viewModal'));
    
    // Initialize DataTable
    $('#commissionsTable').DataTable({
        order: [[0, 'desc']],
        pageLength: 25
    });
    
    // Handle recipient type option clicks
    $('.recipient-type-option').on('click', function() {
        $('.recipient-type-option').removeClass('active');
        $(this).addClass('active');
        $(this).find('input[type="radio"]').prop('checked', true).trigger('change');
    });
});

function showCommissionModal() {
    document.getElementById('commissionForm').reset();
    document.getElementById('commission_id').value = '';
    document.getElementById('reservationInfoDiv').style.display = 'none';
    document.getElementById('commission_amount').value = '';
    document.getElementById('modalTitle').textContent = 'Calculate Commission';
    $('.recipient-type-option').removeClass('active');
    $('.recipient-type-option').first().addClass('active');
    toggleRecipientFields();
    commissionModal.show();
}

function toggleRecipientFields() {
    const recipientType = document.querySelector('input[name="recipient_type"]:checked').value;
    const userSelectDiv = document.getElementById('userSelectDiv');
    const manualRecipientDiv = document.getElementById('manualRecipientDiv');
    const userSelect = document.getElementById('user_id');
    const recipientName = document.getElementById('recipient_name');
    
    if (recipientType === 'user') {
        userSelectDiv.style.display = 'block';
        manualRecipientDiv.style.display = 'none';
        userSelect.required = true;
        recipientName.required = false;
    } else {
        userSelectDiv.style.display = 'none';
        manualRecipientDiv.style.display = 'block';
        userSelect.required = false;
        recipientName.required = true;
    }
}

function updateReservationInfo() {
    const reservationSelect = document.getElementById('reservation_id');
    const selectedOption = reservationSelect.options[reservationSelect.selectedIndex];
    
    if (selectedOption.value) {
        document.getElementById('info_customer').textContent = selectedOption.dataset.customer;
        document.getElementById('info_amount').textContent = 'TZS ' + parseFloat(selectedOption.dataset.amount).toLocaleString('en-US', {minimumFractionDigits: 2});
        document.getElementById('info_property').textContent = selectedOption.dataset.project + ' - Plot ' + selectedOption.dataset.plot;
        document.getElementById('reservationInfoDiv').style.display = 'block';
        
        // Trigger calculation if rate is filled
        calculateAmount();
    } else {
        document.getElementById('reservationInfoDiv').style.display = 'none';
    }
}

function calculateAmount() {
    const reservationSelect = document.getElementById('reservation_id');
    const selectedOption = reservationSelect.options[reservationSelect.selectedIndex];
    const rate = parseFloat(document.getElementById('commission_rate').value) || 0;
    
    if (selectedOption.value && rate > 0) {
        const reservationAmount = parseFloat(selectedOption.dataset.amount) || 0;
        const commissionAmount = (reservationAmount * rate) / 100;
        document.getElementById('commission_amount').value = commissionAmount.toFixed(2);
    }
}

function editCommission(commissionId) {
    $.ajax({
        url: '',
        type: 'POST',
        data: {
            ajax: 1,
            action: 'get_commission',
            commission_id: commissionId
        },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                const commission = response.commission;
                document.getElementById('commission_id').value = commission.commission_id;
                document.getElementById('commission_rate').value = commission.commission_percentage;
                document.getElementById('commission_amount').value = commission.commission_amount;
                document.getElementById('remarks').value = commission.remarks || '';
                
                document.getElementById('modalTitle').textContent = 'Edit Commission';
                document.querySelector('#commissionForm input[name="action"]').value = 'update_commission';
                commissionModal.show();
            } else {
                alert('Error: ' + response.message);
            }
        }
    });
}

function viewCommission(commissionId) {
    $.ajax({
        url: '',
        type: 'POST',
        data: {
            ajax: 1,
            action: 'get_commission',
            commission_id: commissionId
        },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                const c = response.commission;
                let html = `
                    <div class="row g-3">
                        <div class="col-md-6">
                            <h6 class="fw-bold text-muted mb-2">Reservation Information</h6>
                            <p class="mb-1"><strong>Customer:</strong> ${c.customer_first_name} ${c.customer_last_name}</p>
                            <p class="mb-1"><strong>Property:</strong> ${c.project_name} - Plot ${c.plot_number}</p>
                            <p class="mb-1"><strong>Amount:</strong> TZS ${parseFloat(c.reservation_amount).toLocaleString('en-US', {minimumFractionDigits: 2})}</p>
                        </div>
                        <div class="col-md-6">
                            <h6 class="fw-bold text-muted mb-2">Recipient Information</h6>
                            <p class="mb-1"><strong>Name:</strong> ${c.recipient_name}</p>
                            <p class="mb-1"><strong>Type:</strong> ${c.recipient_type}</p>
                            ${c.recipient_phone ? `<p class="mb-1"><strong>Phone:</strong> ${c.recipient_phone}</p>` : ''}
                        </div>
                        <div class="col-12"><hr></div>
                        <div class="col-md-6">
                            <h6 class="fw-bold text-muted mb-2">Commission Details</h6>
                            <p class="mb-1"><strong>Type:</strong> ${c.commission_type}</p>
                            <p class="mb-1"><strong>Rate:</strong> ${c.commission_percentage}%</p>
                            <p class="mb-1"><strong>Amount:</strong> <span class="commission-amount">TZS ${parseFloat(c.commission_amount).toLocaleString('en-US', {minimumFractionDigits: 2})}</span></p>
                            <p class="mb-1"><strong>Status:</strong> <span class="badge bg-${c.payment_status === 'pending' ? 'warning' : c.payment_status === 'paid' ? 'success' : 'danger'}">${c.payment_status.toUpperCase()}</span></p>
                        </div>
                        <div class="col-md-6">
                            <h6 class="fw-bold text-muted mb-2">Additional Information</h6>
                            <p class="mb-1"><strong>Created:</strong> ${new Date(c.created_at).toLocaleDateString()}</p>
                            ${c.payment_date ? `<p class="mb-1"><strong>Paid:</strong> ${new Date(c.payment_date).toLocaleDateString()}</p>` : ''}
                        </div>
                        ${c.remarks ? `<div class="col-12"><h6 class="fw-bold text-muted mb-2">Remarks</h6><p>${c.remarks}</p></div>` : ''}
                    </div>
                `;
                document.getElementById('commissionDetailsContent').innerHTML = html;
                viewModal.show();
            } else {
                alert('Error: ' + response.message);
            }
        }
    });
}

function deleteCommission(commissionId) {
    if (confirm('Are you sure you want to delete this commission?')) {
        $.ajax({
            url: '',
            type: 'POST',
            data: {
                ajax: 1,
                action: 'delete_commission',
                commission_id: commissionId
            },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    alert(response.message);
                    location.reload();
                } else {
                    alert('Error: ' + response.message);
                }
            }
        });
    }
}

function filterByStatus(status) {
    window.location.href = '?status=' + status;
}

// Save commission
document.getElementById('commissionForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    
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
                location.reload();
            } else {
                alert('Error: ' + response.message);
            }
        }
    });
});
</script>

<?php require_once '../../includes/footer.php'; ?>