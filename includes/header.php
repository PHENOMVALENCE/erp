<?php
defined('APP_ACCESS') or die('Direct access not permitted');

// Calculate the base path dynamically based on current location
$current_path = dirname($_SERVER['PHP_SELF']);
$depth = substr_count($current_path, '/') - 1; // -1 because root is already counted
$base_path = $depth > 0 ? str_repeat('../', $depth) : '';

// Get current page for active menu
$current_page = basename($_SERVER['PHP_SELF']);
$current_dir = dirname($_SERVER['PHP_SELF']);
$current_module = basename($current_dir);

// Handle special case: "leave management" folder should be treated as "leave"
if ($current_module == 'leave management') {
    $current_module = 'leave';
}

// Build current path for comparison (normalize slashes)
$current_full_path = str_replace('\\', '/', trim($current_dir . '/' . $current_page, '/'));
$current_full_path = preg_replace('#^/#', '', $current_full_path); // Remove leading slash

// Function to check if a link is active
function isActiveLink($link_href, $current_full_path, $current_page, $current_module) {
    // Normalize the link path
    $link_path = str_replace('\\', '/', $link_href);
    
    // Remove base_path (../) if present
    $link_path = preg_replace('#^(\.\./)+#', '', $link_path);
    
    // Extract module and page from link
    $link_module = '';
    $link_page = basename($link_path);
    
    if (preg_match('#modules/([^/]+)/#', $link_path, $matches)) {
        $link_module = $matches[1];
        if ($link_module == 'leave management') $link_module = 'leave';
    }
    
    // Check if it's the root index.php
    if ($link_path == 'index.php' && $current_full_path == 'index.php' && $current_module != 'modules') {
        return true;
    }
    
    // Check by module and page name (most reliable method)
    if ($link_module == $current_module && $link_page == $current_page) {
        return true;
    }
    
    // Also check full path match (normalize both)
    $normalized_link = preg_replace('#^/#', '', $link_path);
    $normalized_current = preg_replace('#^/#', '', $current_full_path);
    if ($normalized_link == $normalized_current) {
        return true;
    }
    
    return false;
}
?>


    <!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? $page_title . ' - ' : ''; ?><?php echo APP_NAME; ?></title>
    
    <!-- Favicon -->
    <link rel="icon" type="image/jpeg" href="<?php echo $base_path; ?>assets/img/logo.jpg">
    
    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { 
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; 
            font-size: 14px; 
            line-height: 1.6; 
            color: #1f2937; 
            background: linear-gradient(135deg, #f5f7fa 0%, #e8ecf1 100%);
            min-height: 100vh;
        }
        
        /* Enhanced Header */
        .main-header { 
            position: fixed; 
            top: 0; 
            left: 0; 
            right: 0; 
            z-index: 1030; 
            background: linear-gradient(135deg, #1e3a8a 0%, #3b82f6 100%);
            border-bottom: none;
            height: 64px; 
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
        }
        
        .navbar { padding: 0; height: 64px; }
        
        .navbar-brand { 
            padding: 0 20px; 
            font-size: 22px; 
            line-height: 64px; 
            color: #fff; 
            font-weight: 600;
            letter-spacing: -0.5px;
            transition: opacity 0.3s;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .navbar-brand:hover { opacity: 0.9; color: #fff; }
        .navbar-brand b { font-weight: 700; }
        
        .brand-logo {
            width: 100px;
            height: 60px;
            border-radius: 8px;
            object-fit: cover;
            background: #fff;
            padding: 2px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .nav-link { 
            padding: 0 16px; 
            color: rgba(255,255,255,0.9); 
            height: 64px; 
            display: flex; 
            align-items: center;
            transition: all 0.3s;
            position: relative;
        }
        
        .nav-link:hover { 
            background: rgba(255,255,255,0.1); 
            color: #fff;
        }
        
        .nav-link i { font-size: 18px; }
        
        /* Enhanced Sidebar */
        .main-sidebar { 
            position: fixed; 
            top: 64px; 
            bottom: 0; 
            left: 0; 
            width: 260px; 
            background: #1f2937;
            overflow-y: auto; 
            z-index: 1020; 
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: 2px 0 8px rgba(0,0,0,0.1);
        }
        
        /* Sidebar Logo Section */
        .sidebar-logo {
            padding: 20px;
            text-align: center;
            background: linear-gradient(135deg, rgba(59, 130, 246, 0.1) 0%, rgba(59, 130, 246, 0.05) 100%);
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }
        
        .sidebar-logo img {
            width: 80px;
            height: 80px;
            border-radius: 12px;
            object-fit: cover;
            background: #fff;
            padding: 4px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.2);
            margin-bottom: 12px;
        }
        
        .sidebar-logo-text {
            color: #fff;
            font-size: 18px;
            font-weight: 700;
            letter-spacing: -0.5px;
        }
        
        .sidebar-logo-subtitle {
            color: #9ca3af;
            font-size: 11px;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-top: 4px;
        }
        
        .sidebar { padding: 16px 0; }
        
        .nav-sidebar .nav-link { 
            color: #9ca3af; 
            padding: 12px 20px; 
            display: flex; 
            align-items: center; 
            border-left: 3px solid transparent;
            transition: all 0.2s;
            font-weight: 500;
            font-size: 14px;
        }
        
        .nav-sidebar .nav-link:hover { 
            background: rgba(59, 130, 246, 0.1); 
            color: #3b82f6;
            border-left-color: #3b82f6;
            padding-left: 24px;
        }
        
        .nav-sidebar .nav-link.active { 
            color: #fff; 
            background: linear-gradient(90deg, rgba(59, 130, 246, 0.2) 0%, rgba(59, 130, 246, 0.05) 100%);
            border-left-color: #3b82f6;
            font-weight: 600;
        }
        
        .nav-icon { 
            width: 24px; 
            text-align: center; 
            margin-right: 12px; 
            font-size: 16px;
        }
        
        .nav-treeview { 
            list-style: none; 
            padding: 0; 
            margin: 0; 
            display: none; 
            background: #111827;
        }
        
        .nav-treeview .nav-link { 
            padding-left: 56px; 
            font-size: 13px;
            font-weight: 400;
        }
        
        .nav-treeview .nav-link:hover {
            padding-left: 60px;
        }
        
        .nav-item.menu-open > .nav-treeview { 
            display: block;
            animation: slideDown 0.3s ease-out;
        }
        
        @keyframes slideDown {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .nav-link.has-dropdown { 
            cursor: pointer;
            user-select: none;
        }
        
        .nav-link.has-dropdown::after { 
            margin-left: auto; 
            content: "\f105"; 
            font-family: "Font Awesome 6 Free"; 
            font-weight: 900; 
            transition: transform 0.3s;
            font-size: 12px;
            pointer-events: none;
        }
        
        .nav-item.menu-open > .nav-link.has-dropdown::after { 
            transform: rotate(90deg); 
        }
        
        /* Content Wrapper */
        .content-wrapper { 
            margin-left: 260px; 
            margin-top: 64px; 
            min-height: calc(100vh - 64px); 
            padding: 24px; 
            background: transparent;
            transition: margin-left 0.3s;
        }
        
        /* Enhanced Badges */
        .badge { 
            font-size: 10px; 
            padding: 4px 8px; 
            margin-left: auto;
            border-radius: 12px;
            font-weight: 600;
        }
        
        /* Enhanced Dropdown */
        .dropdown-menu { 
            border: none;
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
            border-radius: 8px;
            padding: 8px;
            margin-top: 8px;
        }
        
        .dropdown-item { 
            padding: 10px 16px;
            border-radius: 6px;
            transition: all 0.2s;
            font-size: 14px;
        }
        
        .dropdown-item:hover { 
            background: #f3f4f6;
            color: #1f2937;
        }
        
        .dropdown-item i { 
            width: 20px;
            text-align: center;
        }
        
        .dropdown-divider { 
            margin: 8px 0;
            opacity: 0.1;
        }
        
        /* Menu Section Headers */
        .nav-header {
            padding: 16px 20px 8px;
            color: #6b7280;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        /* Responsive */
        @media (max-width: 991.98px) {
            .main-sidebar { 
                margin-left: -260px; 
            }
            .main-sidebar.sidebar-open { 
                margin-left: 0;
                box-shadow: 4px 0 12px rgba(0,0,0,0.15);
            }
            .content-wrapper { 
                margin-left: 0; 
            }
            .brand-logo {
                width: 36px;
                height: 36px;
            }
            .navbar-brand {
                font-size: 18px;
            }
        }
        
        /* Scrollbar Styling */
        .main-sidebar::-webkit-scrollbar { 
            width: 6px; 
        }
        
        .main-sidebar::-webkit-scrollbar-track {
            background: #111827;
        }
        
        .main-sidebar::-webkit-scrollbar-thumb { 
            background: #374151;
            border-radius: 3px;
        }
        
        .main-sidebar::-webkit-scrollbar-thumb:hover {
            background: #4b5563;
        }
        
        /* Notification Badge Animation */
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
        }
        
        .nav-link .badge {
            animation: pulse 2s infinite;
        }
        
        /* User Avatar */
        .user-avatar {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background: rgba(255,255,255,0.2);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin-right: 8px;
            font-weight: 600;
        }
    </style>
</head>
<body class="hold-transition sidebar-mini">
<div class="wrapper">

    <!-- Enhanced Navbar -->
    <nav class="main-header navbar navbar-expand">
        <ul class="navbar-nav">
            <li class="nav-item d-lg-none">
                <a class="nav-link" href="#" onclick="toggleSidebar(); return false;">
                    <i class="fas fa-bars"></i>
                </a>
            </li>
            <li class="nav-item">
                <a href="<?php echo $base_path; ?>index.php" class="navbar-brand">
                    <img src="<?php echo $base_path; ?>assets/img/logo.jpg" alt="Logo" class="brand-logo">
                    <span>ERP</span>
                </a>
            </li>
        </ul>
        <ul class="navbar-nav ms-auto">
            <!-- Quick Actions Dropdown -->
            <li class="nav-item dropdown">
                <a class="nav-link dropdown-toggle" data-bs-toggle="dropdown" href="#" role="button" aria-expanded="false" title="Quick Actions">
                    <i class="fas fa-bolt"></i>
                </a>
                <div class="dropdown-menu dropdown-menu-end" style="min-width: 220px;">
                    <h6 class="dropdown-header">Quick Actions</h6>
                    <a href="<?php echo $base_path; ?>modules/sales/create.php" class="dropdown-item">
                        <i class="fas fa-plus-circle me-2 text-success"></i> New Reservation
                    </a>
                    <a href="<?php echo $base_path; ?>modules/leave/apply.php" class="dropdown-item">
                        <i class="fas fa-calendar-plus me-2 text-info"></i> Apply Leave
                    </a>
                    <a href="<?php echo $base_path; ?>modules/loans/apply.php" class="dropdown-item">
                        <i class="fas fa-hand-holding-usd me-2 text-warning"></i> Apply Loan
                    </a>
                    <a href="<?php echo $base_path; ?>modules/petty_cash/request.php" class="dropdown-item">
                        <i class="fas fa-wallet me-2 text-danger"></i> Petty Cash Request
                    </a>
                </div>
            </li>
            
            <!-- Messages Dropdown -->
            <li class="nav-item dropdown">
                <a class="nav-link dropdown-toggle" data-bs-toggle="dropdown" href="#" role="button" aria-expanded="false" title="Messages">
                    <i class="far fa-envelope"></i>
                    <span class="badge bg-danger">3</span>
                </a>
                <div class="dropdown-menu dropdown-menu-end" style="min-width: 280px;">
                    <h6 class="dropdown-header">New Messages</h6>
                    <a href="#" class="dropdown-item">
                        <div class="d-flex align-items-center">
                            <div class="user-avatar bg-primary text-white">JD</div>
                            <div class="flex-grow-1">
                                <strong>John Doe</strong>
                                <p class="mb-0 small text-muted">Payment inquiry...</p>
                            </div>
                        </div>
                    </a>
                    <div class="dropdown-divider"></div>
                    <a href="#" class="dropdown-item text-center small">View All Messages</a>
                </div>
            </li>
            
            <!-- Notifications Dropdown -->
            <li class="nav-item dropdown">
                <a class="nav-link dropdown-toggle" data-bs-toggle="dropdown" href="#" role="button" aria-expanded="false" title="Notifications">
                    <i class="far fa-bell"></i>
                    <span class="badge bg-warning">8</span>
                </a>
                <div class="dropdown-menu dropdown-menu-end" style="min-width: 320px;">
                    <h6 class="dropdown-header">Notifications</h6>
                    <a href="#" class="dropdown-item">
                        <i class="fas fa-check-circle me-2 text-success"></i> New payment received
                        <small class="float-end text-muted">5m ago</small>
                    </a>
                    <a href="#" class="dropdown-item">
                        <i class="fas fa-exclamation-triangle me-2 text-warning"></i> 3 expenses pending approval
                        <small class="float-end text-muted">1h ago</small>
                    </a>
                    <a href="#" class="dropdown-item">
                        <i class="fas fa-hand-holding-usd me-2 text-info"></i> Loan application received
                        <small class="float-end text-muted">2h ago</small>
                    </a>
                    <div class="dropdown-divider"></div>
                    <a href="#" class="dropdown-item text-center small">View All Notifications</a>
                </div>
            </li>
            
            <!-- Tasks Dropdown -->
            <li class="nav-item dropdown">
                <a class="nav-link dropdown-toggle" data-bs-toggle="dropdown" href="#" role="button" aria-expanded="false" title="Tasks">
                    <i class="fas fa-tasks"></i>
                    <span class="badge bg-info">6</span>
                </a>
                <div class="dropdown-menu dropdown-menu-end" style="min-width: 280px;">
                    <h6 class="dropdown-header">Pending Tasks</h6>
                    <a href="#" class="dropdown-item">
                        <i class="fas fa-file-signature me-2 text-primary"></i> Review contract #1234
                    </a>
                    <a href="#" class="dropdown-item">
                        <i class="fas fa-check-double me-2 text-success"></i> Approve 2 expense claims
                    </a>
                    <a href="#" class="dropdown-item">
                        <i class="fas fa-cash-register me-2 text-warning"></i> Petty cash reconciliation
                    </a>
                    <div class="dropdown-divider"></div>
                    <a href="#" class="dropdown-item text-center small">View All Tasks</a>
                </div>
            </li>
            
            <!-- User Profile Dropdown -->
            <li class="nav-item dropdown">
                <a class="nav-link dropdown-toggle" data-bs-toggle="dropdown" href="#" role="button" aria-expanded="false" title="Profile">
                    <div class="user-avatar">
                        <?php echo strtoupper(substr($_SESSION['full_name'] ?? 'U', 0, 1)); ?>
                    </div>
                    <span class="d-none d-md-inline"><?php echo $_SESSION['full_name'] ?? 'User'; ?></span>
                    <i class="fas fa-chevron-down ms-2" style="font-size: 10px;"></i>
                </a>
                <div class="dropdown-menu dropdown-menu-end" style="min-width: 220px;">
                    <div class="dropdown-header">
                        <strong><?php echo $_SESSION['full_name'] ?? 'User'; ?></strong>
                        <p class="mb-0 small text-muted"><?php echo $_SESSION['email'] ?? 'user@example.com'; ?></p>
                    </div>
                    <div class="dropdown-divider"></div>
                    <a href="<?php echo $base_path; ?>modules/settings/profile.php" class="dropdown-item">
                        <i class="fas fa-user me-2"></i> My Profile
                    </a>
                    <a href="<?php echo $base_path; ?>modules/settings/index.php" class="dropdown-item">
                        <i class="fas fa-cog me-2"></i> Settings
                    </a>
                    <a href="#" class="dropdown-item">
                        <i class="fas fa-question-circle me-2"></i> Help & Support
                    </a>
                    <div class="dropdown-divider"></div>
                    <a href="<?php echo $base_path; ?>logout.php" class="dropdown-item text-danger">
                        <i class="fas fa-sign-out-alt me-2"></i> Logout
                    </a>
                </div>
            </li>
        </ul>
    </nav>

    <!-- Enhanced Sidebar -->
    <aside class="main-sidebar" id="mainSidebar">
        <div class="sidebar">
            <nav class="mt-2">
                <ul class="nav nav-pills nav-sidebar flex-column" data-widget="treeview" role="menu">

                    <!-- Dashboard -->
                    <li class="nav-item">
                        <a href="<?php echo $base_path; ?>index.php" class="nav-link <?php echo ($current_page == 'index.php' && $current_module != 'modules') ? 'active' : ''; ?>">
                            <i class="nav-icon fas fa-tachometer-alt"></i>
                            <span>Dashboard</span>
                        </a>
                    </li>

                    <!-- MAIN MODULES -->
                    <li class="nav-header">CORE MODULES</li>

                    <!-- Projects & Plots -->
                    <li class="nav-item <?php echo in_array($current_module, ['plots','projects']) ? 'menu-open' : ''; ?>">
                        <a href="#" class="nav-link has-dropdown">
                            <i class="nav-icon fas fa-map-marked-alt"></i>
                            <span>Projects & Plots</span>
                        </a>
                        <ul class="nav nav-treeview">
                            <li class="nav-item"><a href="<?php echo $base_path; ?>modules/projects/index.php" class="nav-link <?php echo isActiveLink($base_path . 'modules/projects/index.php', $current_full_path, $current_page, $current_module) ? 'active' : ''; ?>"><i class="far fa-circle nav-icon"></i><span>All Projects</span></a></li>
                            <li class="nav-item"><a href="<?php echo $base_path; ?>modules/projects/create.php" class="nav-link <?php echo isActiveLink($base_path . 'modules/projects/create.php', $current_full_path, $current_page, $current_module) ? 'active' : ''; ?>"><i class="far fa-circle nav-icon"></i><span>Add Project</span></a></li>
                            <li class="nav-item"><a href="<?php echo $base_path; ?>modules/projects/costs.php" class="nav-link <?php echo isActiveLink($base_path . 'modules/projects/costs.php', $current_full_path, $current_page, $current_module) ? 'active' : ''; ?>"><i class="far fa-circle nav-icon"></i><span>Project Costs</span></a></li>
                            <li class="nav-item"><a href="<?php echo $base_path; ?>modules/plots/index.php" class="nav-link <?php echo isActiveLink($base_path . 'modules/plots/index.php', $current_full_path, $current_page, $current_module) ? 'active' : ''; ?>"><i class="far fa-circle nav-icon"></i><span>All Plots</span></a></li>
                            <li class="nav-item"><a href="<?php echo $base_path; ?>modules/plots/create.php" class="nav-link <?php echo isActiveLink($base_path . 'modules/plots/create.php', $current_full_path, $current_page, $current_module) ? 'active' : ''; ?>"><i class="far fa-circle nav-icon"></i><span>Add Plot</span></a></li>
                        </ul>
                    </li>

                    <!-- Marketing & Leads -->
                    <li class="nav-item <?php echo ($current_module == 'marketing') ? 'menu-open' : ''; ?>">
                        <a href="#" class="nav-link has-dropdown">
                            <i class="nav-icon fas fa-bullhorn"></i>
                            <span>Marketing & Leads</span>
                        </a>
                        <ul class="nav nav-treeview">
                            <li class="nav-item"><a href="<?php echo $base_path; ?>modules/marketing/leads.php" class="nav-link"><i class="far fa-circle nav-icon"></i><span>All Leads</span></a></li>
                            <li class="nav-item"><a href="<?php echo $base_path; ?>modules/marketing/create-lead.php" class="nav-link"><i class="far fa-circle nav-icon"></i><span>Add Lead</span></a></li>
                            <li class="nav-item"><a href="<?php echo $base_path; ?>modules/marketing/campaigns.php" class="nav-link"><i class="far fa-circle nav-icon"></i><span>Campaigns</span></a></li>
                            <li class="nav-item"><a href="<?php echo $base_path; ?>modules/marketing/quotations.php" class="nav-link"><i class="far fa-circle nav-icon"></i><span>Sales Quotations</span></a></li>
                        </ul>
                    </li>

                    <!-- Customers -->
                    <li class="nav-item <?php echo ($current_module == 'customers') ? 'menu-open' : ''; ?>">
                        <a href="#" class="nav-link has-dropdown">
                            <i class="nav-icon fas fa-users"></i>
                            <span>Customers</span>
                        </a>
                        <ul class="nav nav-treeview">
                            <li class="nav-item"><a href="<?php echo $base_path; ?>modules/customers/index.php" class="nav-link"><i class="far fa-circle nav-icon"></i><span>All Customers</span></a></li>
                            <li class="nav-item"><a href="<?php echo $base_path; ?>modules/customers/create.php" class="nav-link"><i class="far fa-circle nav-icon"></i><span>Add Customer</span></a></li>
                            <li class="nav-item"><a href="<?php echo $base_path; ?>modules/customers/debtors.php" class="nav-link"><i class="far fa-circle nav-icon"></i><span>Debtors / AR</span></a></li>
                        </ul>
                    </li>

                    <!-- Sales & Reservations -->
                    <li class="nav-item <?php echo ($current_module == 'sales') ? 'menu-open' : ''; ?>">
                        <a href="#" class="nav-link has-dropdown">
                            <i class="nav-icon fas fa-file-contract"></i>
                            <span>Sales & Reservations</span>
                        </a>
                        <ul class="nav nav-treeview">
                            <li class="nav-item"><a href="<?php echo $base_path; ?>modules/sales/index.php" class="nav-link"><i class="far fa-circle nav-icon"></i><span>All Reservations</span></a></li>
                            <li class="nav-item"><a href="<?php echo $base_path; ?>modules/sales/create.php" class="nav-link"><i class="far fa-circle nav-icon"></i><span>New Reservation</span></a></li>
                            <li class="nav-item"><a href="<?php echo $base_path; ?>modules/sales/contracts.php" class="nav-link"><i class="far fa-circle nav-icon"></i><span>Contracts</span></a></li>
                            <li class="nav-item"><a href="<?php echo $base_path; ?>modules/sales/cancellations.php" class="nav-link"><i class="far fa-circle nav-icon"></i><span>Cancellations</span></a></li>
                        </ul>
                    </li>

                    <!-- Land Services -->
                    <li class="nav-item <?php echo ($current_module == 'services') ? 'menu-open' : ''; ?>">
                        <a href="#" class="nav-link has-dropdown">
                            <i class="nav-icon fas fa-tools"></i>
                            <span>Land Services</span>
                        </a>
                        <ul class="nav nav-treeview">
                            <li class="nav-item"><a href="<?php echo $base_path; ?>modules/services/types.php" class="nav-link"><i class="far fa-circle nav-icon"></i><span>Service Catalog</span></a></li>
                            <li class="nav-item"><a href="<?php echo $base_path; ?>modules/services/requests.php" class="nav-link"><i class="far fa-circle nav-icon"></i><span>Service Requests</span></a></li>
                            <li class="nav-item"><a href="<?php echo $base_path; ?>modules/services/create.php" class="nav-link"><i class="far fa-circle nav-icon"></i><span>New Service Request</span></a></li>
                        </ul>
                    </li>

                    <!-- FINANCIAL SECTION -->
                    <li class="nav-header">FINANCIAL MANAGEMENT</li>

                    <!-- Payments -->
                    <li class="nav-item <?php echo ($current_module == 'payments') ? 'menu-open' : ''; ?>">
                        <a href="#" class="nav-link has-dropdown">
                            <i class="nav-icon fas fa-money-bill-wave"></i>
                            <span>Payments</span>
                            <span class="badge bg-success">5</span>
                        </a>
                        <ul class="nav nav-treeview">
                            <li class="nav-item"><a href="<?php echo $base_path; ?>modules/payments/index.php" class="nav-link"><i class="far fa-circle nav-icon"></i><span>All Payments</span></a></li>
                            <li class="nav-item"><a href="<?php echo $base_path; ?>modules/payments/record.php" class="nav-link"><i class="far fa-circle nav-icon"></i><span>Record Payment</span></a></li>
                            <li class="nav-item"><a href="<?php echo $base_path; ?>modules/payments/schedule.php" class="nav-link"><i class="far fa-circle nav-icon"></i><span>Payment Schedules</span></a></li>
                            <li class="nav-item"><a href="<?php echo $base_path; ?>modules/payments/refunds.php" class="nav-link"><i class="far fa-circle nav-icon"></i><span>Refunds</span></a></li>
                            <li class="nav-item"><a href="<?php echo $base_path; ?>modules/payments/vouchers.php" class="nav-link"><i class="far fa-circle nav-icon"></i><span>Vouchers & Receipts</span></a></li>
                        </ul>
                    </li>

                    <!-- Expenses -->
                    <li class="nav-item <?php echo ($current_module == 'expenses') ? 'menu-open' : ''; ?>">
                        <a href="#" class="nav-link has-dropdown">
                            <i class="nav-icon fas fa-receipt"></i>
                            <span>Expenses</span>
                        </a>
                        <ul class="nav nav-treeview">
                            <li class="nav-item"><a href="<?php echo $base_path; ?>modules/expenses/index.php" class="nav-link"><i class="far fa-circle nav-icon"></i><span>All Expenses</span></a></li>
                            <li class="nav-item"><a href="<?php echo $base_path; ?>modules/expenses/create_claim.php" class="nav-link"><i class="far fa-circle nav-icon"></i><span>Submit Expense</span></a></li>
                            <li class="nav-item"><a href="<?php echo $base_path; ?>modules/expenses/claims.php" class="nav-link"><i class="far fa-circle nav-icon"></i><span>Expense Claims</span></a></li>
                            <li class="nav-item"><a href="<?php echo $base_path; ?>modules/expenses/approvals.php" class="nav-link"><i class="far fa-circle nav-icon"></i><span>Approvals</span></a></li>
                            <li class="nav-item"><a href="<?php echo $base_path; ?>modules/expenses/categories.php" class="nav-link"><i class="far fa-circle nav-icon"></i><span>Expense Categories</span></a></li>
                        </ul>
                    </li>

                    <!-- Petty Cash (NEW) -->
                    <li class="nav-item <?php echo ($current_module == 'petty_cash') ? 'menu-open' : ''; ?>">
                        <a href="#" class="nav-link has-dropdown">
                            <i class="nav-icon fas fa-wallet"></i>
                            <span>Petty Cash</span>
                        </a>
                        <ul class="nav nav-treeview">
                            <li class="nav-item"><a href="<?php echo $base_path; ?>modules/petty_cash/index.php" class="nav-link"><i class="far fa-circle nav-icon"></i><span>Dashboard</span></a></li>
                            <li class="nav-item"><a href="<?php echo $base_path; ?>modules/petty_cash/request.php" class="nav-link"><i class="far fa-circle nav-icon"></i><span>Request Cash</span></a></li>
                            <li class="nav-item"><a href="<?php echo $base_path; ?>modules/petty_cash/approvals.php" class="nav-link"><i class="far fa-circle nav-icon"></i><span>Approvals</span></a></li>
                            <!-- petty_cash/accounts.php not yet available -->
                        </ul>
                    </li>

                    <!-- Commissions -->
                    <li class="nav-item <?php echo ($current_module == 'commissions') ? 'menu-open' : ''; ?>">
                        <a href="#" class="nav-link has-dropdown">
                            <i class="nav-icon fas fa-percent"></i>
                            <span>Commissions</span>
                        </a>
                        <ul class="nav nav-treeview">
                            <li class="nav-item"><a href="<?php echo $base_path; ?>modules/commissions/index.php" class="nav-link"><i class="far fa-circle nav-icon"></i><span>All Commissions</span></a></li>
                            <li class="nav-item"><a href="<?php echo $base_path; ?>modules/commissions/structures.php" class="nav-link"><i class="far fa-circle nav-icon"></i><span>Commission Structures</span></a></li>
                            <li class="nav-item"><a href="<?php echo $base_path; ?>modules/commissions/pay.php" class="nav-link"><i class="far fa-circle nav-icon"></i><span>Pay Commissions</span></a></li>
                        </ul>
                    </li>

                    <!-- Accounting -->
                    <li class="nav-item <?php echo ($current_module == 'accounting') ? 'menu-open' : ''; ?>">
                        <a href="#" class="nav-link has-dropdown">
                            <i class="nav-icon fas fa-book"></i>
                            <span>Accounting</span>
                        </a>
                        <ul class="nav nav-treeview">
                            <li class="nav-item"><a href="<?php echo $base_path; ?>modules/accounting/accounts.php" class="nav-link"><i class="far fa-circle nav-icon"></i><span>Chart of Accounts</span></a></li>
                            <!-- accounts_comprehensive.php not yet available -->
                            <li class="nav-item"><a href="<?php echo $base_path; ?>modules/accounting/journal.php" class="nav-link"><i class="far fa-circle nav-icon"></i><span>Journal Entries</span></a></li>
                            <li class="nav-item"><a href="<?php echo $base_path; ?>modules/accounting/ledger.php" class="nav-link"><i class="far fa-circle nav-icon"></i><span>General Ledger</span></a></li>
                            <li class="nav-item"><a href="<?php echo $base_path; ?>modules/accounting/trial.php" class="nav-link"><i class="far fa-circle nav-icon"></i><span>Trial Balance</span></a></li>
                        </ul>
                    </li>

                    <!-- Finance & Banking -->
                    <li class="nav-item <?php echo in_array($current_module, ['finance','bank']) ? 'menu-open' : ''; ?>">
                        <a href="#" class="nav-link has-dropdown">
                            <i class="nav-icon fas fa-university"></i>
                            <span>Finance & Banking</span>
                        </a>
                        <ul class="nav nav-treeview">
                            <li class="nav-item"><a href="<?php echo $base_path; ?>modules/finance/bank_accounts.php" class="nav-link"><i class="far fa-circle nav-icon"></i><span>Bank Accounts</span></a></li>
                            <li class="nav-item"><a href="<?php echo $base_path; ?>modules/finance/bank_reconciliation.php" class="nav-link"><i class="far fa-circle nav-icon"></i><span>Bank Reconciliation</span></a></li>
                            <li class="nav-item"><a href="<?php echo $base_path; ?>modules/finance/budgets.php" class="nav-link"><i class="far fa-circle nav-icon"></i><span>Budgets</span></a></li>
                            <li class="nav-item"><a href="<?php echo $base_path; ?>modules/finance/creditors.php" class="nav-link"><i class="far fa-circle nav-icon"></i><span>Creditors / AP</span></a></li>
                        </ul>
                    </li>

                    <!-- Tax Management -->
                    <li class="nav-item <?php echo ($current_module == 'tax') ? 'menu-open' : ''; ?>">
                        <a href="#" class="nav-link has-dropdown">
                            <i class="nav-icon fas fa-file-invoice-dollar"></i>
                            <span>Tax Management</span>
                        </a>
                        <ul class="nav nav-treeview">
                            <li class="nav-item"><a href="<?php echo $base_path; ?>modules/tax/types.php" class="nav-link"><i class="far fa-circle nav-icon"></i><span>Tax Types</span></a></li>
                            <li class="nav-item"><a href="<?php echo $base_path; ?>modules/tax/transactions.php" class="nav-link"><i class="far fa-circle nav-icon"></i><span>Tax Transactions</span></a></li>
                            <li class="nav-item"><a href="<?php echo $base_path; ?>modules/tax/reports.php" class="nav-link"><i class="far fa-circle nav-icon"></i><span>Tax Reports</span></a></li>
                        </ul>
                    </li>

                    <!-- OPERATIONS SECTION -->
                    <li class="nav-header">OPERATIONS</li>

                    <!-- Procurement -->
                    <li class="nav-item <?php echo ($current_module == 'procurement') ? 'menu-open' : ''; ?>">
                        <a href="#" class="nav-link has-dropdown">
                            <i class="nav-icon fas fa-shopping-cart"></i>
                            <span>Procurement</span>
                        </a>
                        <ul class="nav nav-treeview">
                            <li class="nav-item"><a href="<?php echo $base_path; ?>modules/procurement/requisitions.php" class="nav-link"><i class="far fa-circle nav-icon"></i><span>Purchase Requisitions</span></a></li>
                            <li class="nav-item"><a href="<?php echo $base_path; ?>modules/procurement/orders.php" class="nav-link"><i class="far fa-circle nav-icon"></i><span>Purchase Orders</span></a></li>
                            <li class="nav-item"><a href="<?php echo $base_path; ?>modules/procurement/suppliers.php" class="nav-link"><i class="far fa-circle nav-icon"></i><span>Suppliers</span></a></li>
                            <li class="nav-item"><a href="<?php echo $base_path; ?>modules/procurement/contracts.php" class="nav-link"><i class="far fa-circle nav-icon"></i><span>Supplier Contracts</span></a></li>
                        </ul>
                    </li>

                    <!-- Inventory -->
                    <li class="nav-item <?php echo ($current_module == 'inventory') ? 'menu-open' : ''; ?>">
                        <a href="#" class="nav-link has-dropdown">
                            <i class="nav-icon fas fa-boxes"></i>
                            <span>Inventory</span>
                        </a>
                        <ul class="nav nav-treeview">
                            <li class="nav-item"><a href="<?php echo $base_path; ?>modules/inventory/items.php" class="nav-link"><i class="far fa-circle nav-icon"></i><span>Items</span></a></li>
                            <li class="nav-item"><a href="<?php echo $base_path; ?>modules/inventory/stores.php" class="nav-link"><i class="far fa-circle nav-icon"></i><span>Store Locations</span></a></li>
                            <li class="nav-item"><a href="<?php echo $base_path; ?>modules/inventory/stock.php" class="nav-link"><i class="far fa-circle nav-icon"></i><span>Stock Levels</span></a></li>
                            <li class="nav-item"><a href="<?php echo $base_path; ?>modules/inventory/movements.php" class="nav-link"><i class="far fa-circle nav-icon"></i><span>Stock Movements</span></a></li>
                            <li class="nav-item"><a href="<?php echo $base_path; ?>modules/inventory/audit.php" class="nav-link"><i class="far fa-circle nav-icon"></i><span>Stock Audit</span></a></li>
                        </ul>
                    </li>

                    <!-- Assets (NEW) -->
                    <li class="nav-item <?php echo ($current_module == 'assets') ? 'menu-open' : ''; ?>">
                        <a href="#" class="nav-link has-dropdown">
                            <i class="nav-icon fas fa-warehouse"></i>
                            <span>Assets</span>
                        </a>
                        <ul class="nav nav-treeview">
                            <li class="nav-item"><a href="<?php echo $base_path; ?>modules/assets/index.php" class="nav-link <?php echo isActiveLink($base_path . 'modules/assets/index.php', $current_full_path, $current_page, $current_module) ? 'active' : ''; ?>"><i class="far fa-circle nav-icon"></i><span>Dashboard</span></a></li>
                            <li class="nav-item"><a href="<?php echo $base_path; ?>modules/assets/add.php" class="nav-link <?php echo isActiveLink($base_path . 'modules/assets/add.php', $current_full_path, $current_page, $current_module) ? 'active' : ''; ?>"><i class="far fa-circle nav-icon"></i><span>Add Asset</span></a></li>
                            <li class="nav-item"><a href="<?php echo $base_path; ?>modules/assets/list.php" class="nav-link <?php echo isActiveLink($base_path . 'modules/assets/list.php', $current_full_path, $current_page, $current_module) ? 'active' : ''; ?>"><i class="far fa-circle nav-icon"></i><span>Asset Register</span></a></li>
                            <li class="nav-item"><a href="<?php echo $base_path; ?>modules/assets/depreciation.php" class="nav-link <?php echo isActiveLink($base_path . 'modules/assets/depreciation.php', $current_full_path, $current_page, $current_module) ? 'active' : ''; ?>"><i class="far fa-circle nav-icon"></i><span>Depreciation</span></a></li>
                            <!-- assets/categories.php not yet available -->
                        </ul>
                    </li>

                    <!-- HR SECTION -->
                    <li class="nav-header">HUMAN RESOURCES</li>

                    <!-- HR -->
                    <li class="nav-item <?php echo ($current_module == 'hr') ? 'menu-open' : ''; ?>">
                        <a href="#" class="nav-link has-dropdown">
                            <i class="nav-icon fas fa-user-tie"></i>
                            <span>Human Resources</span>
                        </a>
                        <ul class="nav nav-treeview">
                            <li class="nav-item"><a href="<?php echo $base_path; ?>modules/hr/employees.php" class="nav-link"><i class="far fa-circle nav-icon"></i><span>Employees</span></a></li>
                            <li class="nav-item"><a href="<?php echo $base_path; ?>modules/hr/attendance.php" class="nav-link"><i class="far fa-circle nav-icon"></i><span>Attendance</span></a></li>
                            <li class="nav-item"><a href="<?php echo $base_path; ?>modules/hr/recruitment.php" class="nav-link"><i class="far fa-circle nav-icon"></i><span>Recruitment</span></a></li>
                        </ul>
                    </li>

                    <!-- Leave Management (NEW) -->
                    <li class="nav-item <?php echo ($current_module == 'leave') ? 'menu-open' : ''; ?>">
                        <a href="#" class="nav-link has-dropdown">
                            <i class="nav-icon fas fa-calendar-alt"></i>
                            <span>Leave Management</span>
                        </a>
                        <ul class="nav nav-treeview">
                            <li class="nav-item"><a href="<?php echo $base_path; ?>modules/leave/index.php" class="nav-link <?php echo isActiveLink($base_path . 'modules/leave/index.php', $current_full_path, $current_page, $current_module) ? 'active' : ''; ?>"><i class="far fa-circle nav-icon"></i><span>Dashboard</span></a></li>
                            <li class="nav-item"><a href="<?php echo $base_path; ?>modules/leave/apply.php" class="nav-link <?php echo isActiveLink($base_path . 'modules/leave/apply.php', $current_full_path, $current_page, $current_module) ? 'active' : ''; ?>"><i class="far fa-circle nav-icon"></i><span>Apply for Leave</span></a></li>
                            <li class="nav-item"><a href="<?php echo $base_path; ?>modules/leave/my-leaves.php" class="nav-link <?php echo isActiveLink($base_path . 'modules/leave/my-leaves.php', $current_full_path, $current_page, $current_module) ? 'active' : ''; ?>"><i class="far fa-circle nav-icon"></i><span>My Leaves</span></a></li>
                            <li class="nav-item"><a href="<?php echo $base_path; ?>modules/leave/balance.php" class="nav-link <?php echo isActiveLink($base_path . 'modules/leave/balance.php', $current_full_path, $current_page, $current_module) ? 'active' : ''; ?>"><i class="far fa-circle nav-icon"></i><span>Leave Balance</span></a></li>
                            <li class="nav-item"><a href="<?php echo $base_path; ?>modules/leave/approvals.php" class="nav-link <?php echo isActiveLink($base_path . 'modules/leave/approvals.php', $current_full_path, $current_page, $current_module) ? 'active' : ''; ?>"><i class="far fa-circle nav-icon"></i><span>Approvals</span></a></li>
                            <li class="nav-item"><a href="<?php echo $base_path; ?>modules/leave/leave-types.php" class="nav-link <?php echo isActiveLink($base_path . 'modules/leave/leave-types.php', $current_full_path, $current_page, $current_module) ? 'active' : ''; ?>"><i class="far fa-circle nav-icon"></i><span>Leave Types</span></a></li>
                        </ul>
                    </li>

                    <!-- Loans (NEW) -->
                    <li class="nav-item <?php echo ($current_module == 'loans') ? 'menu-open' : ''; ?>">
                        <a href="#" class="nav-link has-dropdown">
                            <i class="nav-icon fas fa-hand-holding-usd"></i>
                            <span>Loans</span>
                        </a>
                        <ul class="nav nav-treeview">
                            <li class="nav-item"><a href="<?php echo $base_path; ?>modules/loans/index.php" class="nav-link <?php echo isActiveLink($base_path . 'modules/loans/index.php', $current_full_path, $current_page, $current_module) ? 'active' : ''; ?>"><i class="far fa-circle nav-icon"></i><span>Dashboard</span></a></li>
                            <li class="nav-item"><a href="<?php echo $base_path; ?>modules/loans/apply.php" class="nav-link <?php echo isActiveLink($base_path . 'modules/loans/apply.php', $current_full_path, $current_page, $current_module) ? 'active' : ''; ?>"><i class="far fa-circle nav-icon"></i><span>Apply for Loan</span></a></li>
                            <li class="nav-item"><a href="<?php echo $base_path; ?>modules/loans/my-loans.php" class="nav-link <?php echo isActiveLink($base_path . 'modules/loans/my-loans.php', $current_full_path, $current_page, $current_module) ? 'active' : ''; ?>"><i class="far fa-circle nav-icon"></i><span>My Loans</span></a></li>
                            <li class="nav-item"><a href="<?php echo $base_path; ?>modules/loans/approvals.php" class="nav-link <?php echo isActiveLink($base_path . 'modules/loans/approvals.php', $current_full_path, $current_page, $current_module) ? 'active' : ''; ?>"><i class="far fa-circle nav-icon"></i><span>Approvals</span></a></li>
                            <li class="nav-item"><a href="<?php echo $base_path; ?>modules/loans/loan-types.php" class="nav-link <?php echo isActiveLink($base_path . 'modules/loans/loan-types.php', $current_full_path, $current_page, $current_module) ? 'active' : ''; ?>"><i class="far fa-circle nav-icon"></i><span>Loan Products</span></a></li>
                        </ul>
                    </li>

                    <!-- Payroll (NEW) -->
                    <li class="nav-item <?php echo ($current_module == 'payroll') ? 'menu-open' : ''; ?>">
                        <a href="#" class="nav-link has-dropdown">
                            <i class="nav-icon fas fa-money-check-alt"></i>
                            <span>Payroll</span>
                        </a>
                        <ul class="nav nav-treeview">
                            <li class="nav-item"><a href="<?php echo $base_path; ?>modules/payroll/index.php" class="nav-link <?php echo isActiveLink($base_path . 'modules/payroll/index.php', $current_full_path, $current_page, $current_module) ? 'active' : ''; ?>"><i class="far fa-circle nav-icon"></i><span>Dashboard</span></a></li>
                            <li class="nav-item"><a href="<?php echo $base_path; ?>modules/payroll/generate.php" class="nav-link <?php echo isActiveLink($base_path . 'modules/payroll/generate.php', $current_full_path, $current_page, $current_module) ? 'active' : ''; ?>"><i class="far fa-circle nav-icon"></i><span>Run Payroll</span></a></li>
                            <li class="nav-item"><a href="<?php echo $base_path; ?>modules/payroll/payroll.php" class="nav-link <?php echo isActiveLink($base_path . 'modules/payroll/payroll.php', $current_full_path, $current_page, $current_module) ? 'active' : ''; ?>"><i class="far fa-circle nav-icon"></i><span>Process Payroll</span></a></li>
                            <li class="nav-item"><a href="<?php echo $base_path; ?>modules/payroll/payslips.php" class="nav-link <?php echo isActiveLink($base_path . 'modules/payroll/payslips.php', $current_full_path, $current_page, $current_module) ? 'active' : ''; ?>"><i class="far fa-circle nav-icon"></i><span>Payslips</span></a></li>
                            <li class="nav-item"><a href="<?php echo $base_path; ?>modules/payroll/history.php" class="nav-link <?php echo isActiveLink($base_path . 'modules/payroll/history.php', $current_full_path, $current_page, $current_module) ? 'active' : ''; ?>"><i class="far fa-circle nav-icon"></i><span>Payroll History</span></a></li>
                        </ul>
                    </li>

                    <!-- WORKFLOW SECTION -->
                    <li class="nav-header">WORKFLOW</li>

                    <!-- Approvals -->
                    <li class="nav-item <?php echo ($current_module == 'approvals') ? 'menu-open' : ''; ?>">
                        <a href="#" class="nav-link has-dropdown">
                            <i class="nav-icon fas fa-check-double"></i>
                            <span>Approvals</span>
                            <span class="badge bg-warning">15</span>
                        </a>
                        <ul class="nav nav-treeview">
                            <li class="nav-item"><a href="<?php echo $base_path; ?>modules/approvals/pending.php" class="nav-link"><i class="far fa-circle nav-icon"></i><span>Pending Approvals</span></a></li>
                            <li class="nav-item"><a href="<?php echo $base_path; ?>modules/approvals/workflows.php" class="nav-link"><i class="far fa-circle nav-icon"></i><span>Workflows</span></a></li>
                            <li class="nav-item"><a href="<?php echo $base_path; ?>modules/approvals/history.php" class="nav-link"><i class="far fa-circle nav-icon"></i><span>Approval History</span></a></li>
                        </ul>
                    </li>

                    <!-- Documents module not yet available -->

                    <!-- ANALYTICS SECTION -->
                    <li class="nav-header">ANALYTICS</li>

                    <!-- Reports (UPDATED) -->
                    <li class="nav-item <?php echo ($current_module == 'reports') ? 'menu-open' : ''; ?>">
                        <a href="#" class="nav-link has-dropdown">
                            <i class="nav-icon fas fa-chart-bar"></i>
                            <span>Reports</span>
                        </a>
                        <ul class="nav nav-treeview">
                            <li class="nav-item"><a href="<?php echo $base_path; ?>modules/reports/index.php" class="nav-link"><i class="far fa-circle nav-icon"></i><span>Reports Hub</span></a></li>
                            <li class="nav-item"><a href="<?php echo $base_path; ?>modules/reports/payroll-summary.php" class="nav-link"><i class="far fa-circle nav-icon"></i><span>Payroll Summary</span></a></li>
                            <li class="nav-item"><a href="<?php echo $base_path; ?>modules/reports/leave-report.php" class="nav-link"><i class="far fa-circle nav-icon"></i><span>Leave Report</span></a></li>
                            <li class="nav-item"><a href="<?php echo $base_path; ?>modules/reports/loan-report.php" class="nav-link"><i class="far fa-circle nav-icon"></i><span>Loan Report</span></a></li>
                            <li class="nav-item"><a href="<?php echo $base_path; ?>modules/reports/asset-register.php" class="nav-link"><i class="far fa-circle nav-icon"></i><span>Asset Register</span></a></li>
                            <li class="nav-item"><a href="<?php echo $base_path; ?>modules/reports/depreciation-report.php" class="nav-link"><i class="far fa-circle nav-icon"></i><span>Depreciation Report</span></a></li>
                            <li class="nav-item"><a href="<?php echo $base_path; ?>modules/reports/employee-report.php" class="nav-link"><i class="far fa-circle nav-icon"></i><span>Employee Report</span></a></li>
                            <li class="nav-item"><a href="<?php echo $base_path; ?>modules/reports/expense-report.php" class="nav-link"><i class="far fa-circle nav-icon"></i><span>Expense Report</span></a></li>
                            <li class="nav-item"><a href="<?php echo $base_path; ?>modules/reports/petty-cash-summary.php" class="nav-link"><i class="far fa-circle nav-icon"></i><span>Petty Cash Summary</span></a></li>
                        </ul>
                    </li>

                    <!-- Analytics module not yet available -->

                    <!-- SYSTEM SECTION -->
                    <li class="nav-header">SYSTEM</li>

                    <!-- Settings -->
                    <li class="nav-item <?php echo ($current_module == 'settings') ? 'menu-open' : ''; ?>">
                        <a href="#" class="nav-link has-dropdown">
                            <i class="nav-icon fas fa-cog"></i>
                            <span>Settings</span>
                        </a>
                        <ul class="nav nav-treeview">
                            <li class="nav-item"><a href="<?php echo $base_path; ?>modules/settings/index.php" class="nav-link"><i class="far fa-circle nav-icon"></i><span>General Settings</span></a></li>
                            <li class="nav-item"><a href="<?php echo $base_path; ?>modules/settings/users.php" class="nav-link"><i class="far fa-circle nav-icon"></i><span>User Management</span></a></li>
                            <li class="nav-item"><a href="<?php echo $base_path; ?>modules/settings/company.php" class="nav-link"><i class="far fa-circle nav-icon"></i><span>Company Profile</span></a></li>
                            <li class="nav-item"><a href="<?php echo $base_path; ?>modules/settings/roles.php" class="nav-link"><i class="far fa-circle nav-icon"></i><span>Roles & Permissions</span></a></li>
                            <li class="nav-item"><a href="<?php echo $base_path; ?>modules/settings/integrations.php" class="nav-link"><i class="far fa-circle nav-icon"></i><span>Integrations</span></a></li>
                            <!-- settings/backup.php not yet available -->
                        </ul>
                    </li>

                    <!-- Audit Trail module not yet available -->

                </ul>
            </nav>
        </div>
    </aside>

    <!-- Content Wrapper -->
    <div class="content-wrapper">
        <!-- Your page content goes here -->

<!-- Scripts at bottom -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Sidebar toggle functionality for mobile
function toggleSidebar() {
    document.getElementById('mainSidebar').classList.toggle('sidebar-open');
}

// Sidebar menu dropdown functionality
document.addEventListener('DOMContentLoaded', function() {
    // Initialize top navbar dropdowns in case data attributes are not auto-bound
    if (typeof bootstrap !== 'undefined') {
        document.querySelectorAll('.navbar .dropdown-toggle').forEach(function(el) {
            new bootstrap.Dropdown(el);
        });
    }

    // Handle sidebar menu dropdowns
    var dropdownLinks = document.querySelectorAll('.nav-sidebar .nav-link.has-dropdown');
    
    dropdownLinks.forEach(function(element) {
        element.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            var parent = this.closest('.nav-item');
            if (!parent) return;
            
            var wasOpen = parent.classList.contains('menu-open');
            
            // Close all other open menus at the same level
            var container = parent.parentElement;
            if (container) {
                var siblings = container.querySelectorAll('.nav-item.menu-open');
                siblings.forEach(function(sibling) {
                    if (sibling !== parent) {
                        sibling.classList.remove('menu-open');
                    }
                });
            }
            
            // Toggle current menu
            if (wasOpen) {
                parent.classList.remove('menu-open');
            } else {
                parent.classList.add('menu-open');
            }
        });
    });
    
    // Keep current module menu open
    document.querySelectorAll('.nav-item.menu-open').forEach(function(item) {
        // Already has menu-open class from PHP, ensure it stays
    });
    
    // Auto-open parent menu if a child link is active
    const activeLinks = document.querySelectorAll('.nav-treeview .nav-link.active');
    activeLinks.forEach(function(activeLink) {
        const parentNavItem = activeLink.closest('.nav-item').parentElement.closest('.nav-item');
        if (parentNavItem) {
            parentNavItem.classList.add('menu-open');
        }
    });
});
</script>
