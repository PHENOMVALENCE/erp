-- =====================================================
-- MIGRATION SQL FOR NEW MODULES - COMPLETE UPDATE
-- ERP System - Mkumbi Investment Company
-- Date: December 2025
-- Modules: Loans, Assets, Petty Cash, Reports, Payroll, Leave
-- =====================================================

USE `mkumbi_erp`;

-- =====================================================
-- 1. LOAN MANAGEMENT MODULE TABLES
-- =====================================================

-- Loan Types Table
CREATE TABLE IF NOT EXISTS `loan_types` (
  `loan_type_id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL,
  `type_name` varchar(100) NOT NULL,
  `type_code` varchar(20) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `max_amount` decimal(15,2) DEFAULT NULL,
  `min_amount` decimal(15,2) DEFAULT 0.00,
  `max_repayment_months` int(11) DEFAULT 12,
  `interest_rate` decimal(5,2) DEFAULT 0.00,
  `requires_guarantor` tinyint(1) DEFAULT 1,
  `min_guarantors` int(11) DEFAULT 1,
  `eligibility_months` int(11) DEFAULT 3 COMMENT 'Months of employment required',
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_by` int(11) DEFAULT NULL,
  PRIMARY KEY (`loan_type_id`),
  KEY `idx_company_id` (`company_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Employee Loans Table
CREATE TABLE IF NOT EXISTS `employee_loans` (
  `loan_id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL,
  `loan_number` varchar(50) NOT NULL,
  `employee_id` int(11) NOT NULL,
  `loan_type_id` int(11) NOT NULL,
  `application_date` date NOT NULL,
  `loan_amount` decimal(15,2) NOT NULL,
  `interest_rate` decimal(5,2) DEFAULT 0.00,
  `repayment_period_months` int(11) NOT NULL,
  `monthly_deduction` decimal(15,2) NOT NULL,
  `purpose` text NOT NULL,
  `guarantor1_id` int(11) DEFAULT NULL,
  `guarantor2_id` int(11) DEFAULT NULL,
  `status` enum('pending','approved','rejected','disbursed','active','completed','defaulted','written_off') DEFAULT 'pending',
  `approved_by` int(11) DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `approved_amount` decimal(15,2) DEFAULT NULL,
  `rejection_reason` text DEFAULT NULL,
  `disbursement_date` date DEFAULT NULL,
  `disbursement_method` enum('bank_transfer','cash','cheque') DEFAULT 'bank_transfer',
  `bank_account_id` int(11) DEFAULT NULL,
  `disbursement_reference` varchar(100) DEFAULT NULL,
  `principal_outstanding` decimal(15,2) DEFAULT 0.00,
  `interest_outstanding` decimal(15,2) DEFAULT 0.00,
  `total_outstanding` decimal(15,2) DEFAULT 0.00,
  `total_paid` decimal(15,2) DEFAULT 0.00,
  `last_payment_date` date DEFAULT NULL,
  `next_payment_date` date DEFAULT NULL,
  `loan_account_code` varchar(20) DEFAULT '1134',
  `notes` text DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`loan_id`),
  UNIQUE KEY `loan_number` (`loan_number`,`company_id`),
  KEY `idx_company_employee` (`company_id`,`employee_id`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Loan Payments Table
CREATE TABLE IF NOT EXISTS `loan_payments` (
  `payment_id` int(11) NOT NULL AUTO_INCREMENT,
  `loan_id` int(11) NOT NULL,
  `payment_date` date NOT NULL,
  `payment_month` varchar(7) NOT NULL COMMENT 'YYYY-MM format',
  `principal_amount` decimal(15,2) NOT NULL,
  `interest_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `total_amount` decimal(15,2) NOT NULL,
  `payment_method` enum('salary_deduction','manual_payment','bank_transfer','cash') DEFAULT 'salary_deduction',
  `payroll_id` int(11) DEFAULT NULL,
  `reference_number` varchar(100) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`payment_id`),
  KEY `idx_loan` (`loan_id`),
  KEY `idx_payment_month` (`payment_month`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Loan Repayment Schedule Table
CREATE TABLE IF NOT EXISTS `loan_repayment_schedule` (
  `schedule_id` int(11) NOT NULL AUTO_INCREMENT,
  `loan_id` int(11) NOT NULL,
  `installment_number` int(11) NOT NULL,
  `due_date` date NOT NULL,
  `principal_amount` decimal(15,2) NOT NULL,
  `interest_amount` decimal(15,2) NOT NULL,
  `total_amount` decimal(15,2) NOT NULL,
  `paid_amount` decimal(15,2) DEFAULT 0.00,
  `balance_due` decimal(15,2) GENERATED ALWAYS AS (`total_amount` - `paid_amount`) STORED,
  `status` enum('pending','paid','partially_paid','overdue') DEFAULT 'pending',
  `payment_date` date DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`schedule_id`),
  KEY `idx_loan_installment` (`loan_id`,`installment_number`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- 2. ASSET MANAGEMENT MODULE TABLES
-- =====================================================

-- Asset Categories Table
CREATE TABLE IF NOT EXISTS `asset_categories` (
  `category_id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL,
  `category_name` varchar(100) NOT NULL,
  `category_code` varchar(20) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `depreciation_method` enum('straight_line','declining_balance','units_of_production') DEFAULT 'straight_line',
  `default_useful_life_years` int(11) DEFAULT 5,
  `default_salvage_percentage` decimal(5,2) DEFAULT 10.00,
  `account_code` varchar(20) DEFAULT NULL,
  `depreciation_account_code` varchar(20) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_by` int(11) DEFAULT NULL,
  PRIMARY KEY (`category_id`),
  KEY `idx_company` (`company_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Fixed Assets Table (should already exist, but ensuring structure)
CREATE TABLE IF NOT EXISTS `fixed_assets` (
  `asset_id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL,
  `asset_number` varchar(50) NOT NULL,
  `category_id` int(11) NOT NULL,
  `asset_name` varchar(200) NOT NULL,
  `description` text DEFAULT NULL,
  `purchase_date` date NOT NULL,
  `supplier_id` int(11) DEFAULT NULL,
  `purchase_order_id` int(11) DEFAULT NULL,
  `invoice_number` varchar(100) DEFAULT NULL,
  `purchase_cost` decimal(15,2) NOT NULL,
  `installation_cost` decimal(15,2) DEFAULT 0.00,
  `total_cost` decimal(15,2) NOT NULL,
  `serial_number` varchar(100) DEFAULT NULL,
  `model_number` varchar(100) DEFAULT NULL,
  `manufacturer` varchar(200) DEFAULT NULL,
  `warranty_expiry_date` date DEFAULT NULL,
  `location` varchar(200) DEFAULT NULL,
  `department_id` int(11) DEFAULT NULL,
  `custodian_id` int(11) DEFAULT NULL,
  `account_code` varchar(20) NOT NULL,
  `depreciation_account_code` varchar(20) NOT NULL,
  `depreciation_method` enum('straight_line','declining_balance','units_of_production') DEFAULT 'straight_line',
  `useful_life_years` int(11) DEFAULT 5,
  `salvage_value` decimal(15,2) DEFAULT 0.00,
  `accumulated_depreciation` decimal(15,2) DEFAULT 0.00,
  `current_book_value` decimal(15,2) NOT NULL,
  `last_depreciation_date` date DEFAULT NULL,
  `status` enum('active','inactive','under_maintenance','disposed','stolen','damaged') DEFAULT 'active',
  `disposal_date` date DEFAULT NULL,
  `disposal_amount` decimal(15,2) DEFAULT NULL,
  `disposal_reason` text DEFAULT NULL,
  `approval_status` enum('pending','approved','rejected') DEFAULT 'pending',
  `approved_by` int(11) DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `purchase_doc_path` varchar(255) DEFAULT NULL,
  `asset_photo_path` varchar(255) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`asset_id`),
  UNIQUE KEY `asset_number` (`asset_number`,`company_id`),
  KEY `idx_company_category` (`company_id`,`category_id`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Asset Depreciation Table
CREATE TABLE IF NOT EXISTS `asset_depreciation` (
  `depreciation_id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL,
  `asset_id` int(11) NOT NULL,
  `depreciation_date` date NOT NULL,
  `depreciation_period` varchar(7) NOT NULL COMMENT 'YYYY-MM format',
  `opening_book_value` decimal(15,2) NOT NULL,
  `depreciation_amount` decimal(15,2) NOT NULL,
  `accumulated_depreciation` decimal(15,2) NOT NULL,
  `closing_book_value` decimal(15,2) NOT NULL,
  `journal_id` int(11) DEFAULT NULL COMMENT 'Link to journal entry',
  `notes` text DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`depreciation_id`),
  KEY `idx_asset_period` (`asset_id`,`depreciation_period`),
  KEY `idx_company_date` (`company_id`,`depreciation_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Asset Maintenance Table
CREATE TABLE IF NOT EXISTS `asset_maintenance` (
  `maintenance_id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL,
  `asset_id` int(11) NOT NULL,
  `maintenance_date` date NOT NULL,
  `maintenance_type` enum('preventive','corrective','emergency','inspection') NOT NULL,
  `description` text NOT NULL,
  `performed_by` varchar(200) DEFAULT NULL,
  `cost` decimal(15,2) DEFAULT 0.00,
  `downtime_hours` decimal(5,2) DEFAULT 0.00,
  `next_maintenance_date` date DEFAULT NULL,
  `status` enum('scheduled','in_progress','completed','cancelled') DEFAULT 'scheduled',
  `notes` text DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`maintenance_id`),
  KEY `idx_asset` (`asset_id`),
  KEY `idx_maintenance_date` (`maintenance_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- 3. PETTY CASH MODULE TABLES
-- =====================================================

-- Petty Cash Accounts Table
CREATE TABLE IF NOT EXISTS `petty_cash_accounts` (
  `account_id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL,
  `account_name` varchar(100) NOT NULL,
  `account_code` varchar(20) DEFAULT NULL,
  `custodian_id` int(11) NOT NULL COMMENT 'User ID of petty cash custodian',
  `opening_balance` decimal(15,2) DEFAULT 0.00,
  `current_balance` decimal(15,2) DEFAULT 0.00,
  `float_amount` decimal(15,2) DEFAULT 0.00 COMMENT 'Standard float amount',
  `location` varchar(200) DEFAULT NULL,
  `account_gl_code` varchar(20) DEFAULT '1112' COMMENT 'GL account code for petty cash',
  `is_active` tinyint(1) DEFAULT 1,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`account_id`),
  KEY `idx_company` (`company_id`),
  KEY `idx_custodian` (`custodian_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Petty Cash Transactions Table
CREATE TABLE IF NOT EXISTS `petty_cash_transactions` (
  `transaction_id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL,
  `account_id` int(11) NOT NULL,
  `transaction_number` varchar(50) NOT NULL,
  `transaction_date` date NOT NULL,
  `transaction_type` enum('expense','replenishment','adjustment') NOT NULL,
  `expense_category_id` int(11) DEFAULT NULL,
  `description` text NOT NULL,
  `amount` decimal(15,2) NOT NULL,
  `payee_name` varchar(200) DEFAULT NULL,
  `receipt_number` varchar(100) DEFAULT NULL,
  `account_code` varchar(20) DEFAULT NULL COMMENT 'Expense account code',
  `requested_by` int(11) DEFAULT NULL,
  `approved_by` int(11) DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `status` enum('pending','approved','rejected','posted') DEFAULT 'pending',
  `rejection_reason` text DEFAULT NULL,
  `receipt_image_path` varchar(255) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`transaction_id`),
  UNIQUE KEY `transaction_number` (`transaction_number`,`company_id`),
  KEY `idx_account_date` (`account_id`,`transaction_date`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Petty Cash Replenishments Table
CREATE TABLE IF NOT EXISTS `petty_cash_replenishments` (
  `replenishment_id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL,
  `account_id` int(11) NOT NULL,
  `replenishment_number` varchar(50) NOT NULL,
  `request_date` date NOT NULL,
  `replenishment_amount` decimal(15,2) NOT NULL,
  `bank_account_id` int(11) DEFAULT NULL,
  `status` enum('pending','approved','disbursed','rejected') DEFAULT 'pending',
  `requested_by` int(11) NOT NULL,
  `approved_by` int(11) DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `disbursed_by` int(11) DEFAULT NULL,
  `disbursed_at` datetime DEFAULT NULL,
  `payment_method` enum('cash','bank_transfer','cheque') DEFAULT 'cash',
  `payment_reference` varchar(100) DEFAULT NULL,
  `rejection_reason` text DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`replenishment_id`),
  UNIQUE KEY `replenishment_number` (`replenishment_number`,`company_id`),
  KEY `idx_account` (`account_id`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- 4. LEAVE MANAGEMENT MODULE TABLES
-- =====================================================

-- Leave Types Table
CREATE TABLE IF NOT EXISTS `leave_types` (
  `leave_type_id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL,
  `type_name` varchar(100) NOT NULL,
  `type_code` varchar(20) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `days_per_year` int(11) DEFAULT 21,
  `max_consecutive_days` int(11) DEFAULT NULL,
  `is_paid` tinyint(1) DEFAULT 1,
  `requires_approval` tinyint(1) DEFAULT 1,
  `advance_notice_days` int(11) DEFAULT 7,
  `is_carry_forward` tinyint(1) DEFAULT 0,
  `max_carry_forward_days` int(11) DEFAULT 0,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_by` int(11) DEFAULT NULL,
  PRIMARY KEY (`leave_type_id`),
  KEY `idx_company` (`company_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Leave Applications Table
CREATE TABLE IF NOT EXISTS `leave_applications` (
  `leave_id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL,
  `leave_number` varchar(50) NOT NULL,
  `employee_id` int(11) NOT NULL,
  `leave_type_id` int(11) NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `total_days` int(11) NOT NULL,
  `reason` text DEFAULT NULL,
  `application_date` date NOT NULL,
  `status` enum('pending','approved','rejected','cancelled') DEFAULT 'pending',
  `approved_by` int(11) DEFAULT NULL,
  `approved_at` timestamp NULL DEFAULT NULL,
  `rejection_reason` text DEFAULT NULL,
  `handover_notes` text DEFAULT NULL,
  `relief_officer_id` int(11) DEFAULT NULL,
  `contact_during_leave` varchar(50) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_by` int(11) DEFAULT NULL,
  PRIMARY KEY (`leave_id`),
  UNIQUE KEY `leave_number` (`leave_number`,`company_id`),
  KEY `idx_employee` (`employee_id`),
  KEY `idx_status` (`status`),
  KEY `idx_dates` (`start_date`,`end_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Leave Balances Table
CREATE TABLE IF NOT EXISTS `leave_balances` (
  `balance_id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL,
  `employee_id` int(11) NOT NULL,
  `leave_type_id` int(11) NOT NULL,
  `year` int(11) NOT NULL,
  `entitled_days` int(11) NOT NULL,
  `carried_forward_days` int(11) DEFAULT 0,
  `total_days` int(11) GENERATED ALWAYS AS (`entitled_days` + `carried_forward_days`) STORED,
  `used_days` int(11) DEFAULT 0,
  `pending_days` int(11) DEFAULT 0,
  `available_days` int(11) GENERATED ALWAYS AS (`entitled_days` + `carried_forward_days` - `used_days` - `pending_days`) STORED,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`balance_id`),
  UNIQUE KEY `employee_type_year` (`employee_id`,`leave_type_id`,`year`),
  KEY `idx_company_year` (`company_id`,`year`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- 5. PAYROLL MODULE TABLES
-- =====================================================

-- Payroll Runs Table
CREATE TABLE IF NOT EXISTS `payroll` (
  `payroll_id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL,
  `payroll_number` varchar(50) NOT NULL,
  `payroll_month` varchar(7) NOT NULL COMMENT 'YYYY-MM format',
  `payroll_date` date NOT NULL,
  `payment_date` date NOT NULL,
  `total_employees` int(11) DEFAULT 0,
  `total_gross_salary` decimal(15,2) DEFAULT 0.00,
  `total_deductions` decimal(15,2) DEFAULT 0.00,
  `total_net_salary` decimal(15,2) DEFAULT 0.00,
  `total_employer_contributions` decimal(15,2) DEFAULT 0.00,
  `status` enum('draft','calculated','approved','paid','cancelled') DEFAULT 'draft',
  `approved_by` int(11) DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `processed_by` int(11) DEFAULT NULL,
  `processed_at` datetime DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`payroll_id`),
  UNIQUE KEY `payroll_number` (`payroll_number`,`company_id`),
  KEY `idx_company_month` (`company_id`,`payroll_month`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Payroll Details Table
CREATE TABLE IF NOT EXISTS `payroll_details` (
  `payroll_detail_id` int(11) NOT NULL AUTO_INCREMENT,
  `payroll_id` int(11) NOT NULL,
  `employee_id` int(11) NOT NULL,
  `basic_salary` decimal(15,2) NOT NULL,
  `allowances` decimal(15,2) DEFAULT 0.00,
  `overtime_amount` decimal(15,2) DEFAULT 0.00,
  `bonus_amount` decimal(15,2) DEFAULT 0.00,
  `gross_salary` decimal(15,2) NOT NULL,
  `paye_tax` decimal(15,2) DEFAULT 0.00,
  `nssf_contribution` decimal(15,2) DEFAULT 0.00,
  `nhif_contribution` decimal(15,2) DEFAULT 0.00,
  `wcf_contribution` decimal(15,2) DEFAULT 0.00,
  `sdl_contribution` decimal(15,2) DEFAULT 0.00,
  `loan_deduction` decimal(15,2) DEFAULT 0.00,
  `advance_deduction` decimal(15,2) DEFAULT 0.00,
  `other_deductions` decimal(15,2) DEFAULT 0.00,
  `total_deductions` decimal(15,2) DEFAULT 0.00,
  `net_salary` decimal(15,2) NOT NULL,
  `employer_nssf` decimal(15,2) DEFAULT 0.00,
  `employer_wcf` decimal(15,2) DEFAULT 0.00,
  `employer_sdl` decimal(15,2) DEFAULT 0.00,
  `employer_nhif` decimal(15,2) DEFAULT 0.00,
  `total_employer_cost` decimal(15,2) DEFAULT 0.00,
  `payment_status` enum('pending','paid','cancelled') DEFAULT 'pending',
  `payment_method` enum('bank_transfer','cash','cheque','mobile_money') DEFAULT 'bank_transfer',
  `payment_reference` varchar(100) DEFAULT NULL,
  `bank_account_number` varchar(50) DEFAULT NULL,
  `bank_name` varchar(100) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`payroll_detail_id`),
  UNIQUE KEY `payroll_employee` (`payroll_id`,`employee_id`),
  KEY `idx_employee` (`employee_id`),
  KEY `idx_payment_status` (`payment_status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Payroll Allowances Table
CREATE TABLE IF NOT EXISTS `payroll_allowances` (
  `allowance_id` int(11) NOT NULL AUTO_INCREMENT,
  `payroll_detail_id` int(11) NOT NULL,
  `allowance_type` varchar(100) NOT NULL,
  `amount` decimal(15,2) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`allowance_id`),
  KEY `idx_payroll_detail` (`payroll_detail_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Payroll Deductions Table
CREATE TABLE IF NOT EXISTS `payroll_deductions` (
  `deduction_id` int(11) NOT NULL AUTO_INCREMENT,
  `payroll_detail_id` int(11) NOT NULL,
  `deduction_type` varchar(100) NOT NULL,
  `amount` decimal(15,2) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`deduction_id`),
  KEY `idx_payroll_detail` (`payroll_detail_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- 6. INSERT DEFAULT DATA FOR NEW MODULES
-- =====================================================

-- Default Loan Types
INSERT IGNORE INTO `loan_types` (`company_id`, `type_name`, `type_code`, `description`, `max_amount`, `min_amount`, `max_repayment_months`, `interest_rate`, `requires_guarantor`, `min_guarantors`, `eligibility_months`, `is_active`, `created_by`) VALUES
(3, 'Emergency Loan', 'EML', 'Short-term emergency loan for urgent needs', 500000.00, 50000.00, 6, 5.00, 1, 1, 3, 1, 1),
(3, 'Salary Advance', 'SAL', 'Advance on monthly salary', 1000000.00, 100000.00, 3, 0.00, 0, 0, 1, 1, 1),
(3, 'Development Loan', 'DEV', 'Long-term development loan', 5000000.00, 500000.00, 36, 8.00, 1, 2, 12, 1, 1);

-- Default Asset Categories
INSERT IGNORE INTO `asset_categories` (`company_id`, `category_name`, `category_code`, `description`, `depreciation_method`, `default_useful_life_years`, `default_salvage_percentage`, `account_code`, `depreciation_account_code`, `is_active`, `created_by`) VALUES
(3, 'Computer Equipment', 'COMP', 'Laptops, Desktops, Servers, and IT Equipment', 'straight_line', 3, 10.00, '1240', '1250', 1, 1),
(3, 'Furniture & Fixtures', 'FURN', 'Office furniture, desks, chairs', 'straight_line', 5, 10.00, '1230', '1250', 1, 1),
(3, 'Vehicles', 'VEH', 'Company vehicles and transport equipment', 'declining_balance', 4, 15.00, '1220', '1250', 1, 1),
(3, 'Office Equipment', 'OFF', 'Printers, photocopiers, office machines', 'straight_line', 5, 10.00, '1240', '1250', 1, 1),
(3, 'Land and Buildings', 'LAND', 'Land, buildings, and property', 'straight_line', 50, 5.00, '1210', '1250', 1, 1);

-- Default Petty Cash Account
INSERT IGNORE INTO `petty_cash_accounts` (`company_id`, `account_name`, `account_code`, `custodian_id`, `opening_balance`, `current_balance`, `float_amount`, `location`, `account_gl_code`, `is_active`, `created_by`) VALUES
(3, 'Main Office Petty Cash', 'PC001', 9, 100000.00, 100000.00, 100000.00, 'Main Office', '1112', 1, 9);

-- Default Leave Types
INSERT IGNORE INTO `leave_types` (`company_id`, `type_name`, `type_code`, `description`, `days_per_year`, `max_consecutive_days`, `is_paid`, `requires_approval`, `advance_notice_days`, `is_carry_forward`, `max_carry_forward_days`, `is_active`, `created_by`) VALUES
(3, 'Annual Leave', 'AL', 'Annual paid vacation leave', 21, 21, 1, 1, 14, 1, 5, 1, 1),
(3, 'Sick Leave', 'SL', 'Medical/Sick leave', 14, 14, 1, 1, 0, 0, 0, 1, 1),
(3, 'Maternity Leave', 'ML', 'Maternity leave for female employees', 84, 84, 1, 1, 30, 0, 0, 1, 1),
(3, 'Paternity Leave', 'PL', 'Paternity leave for male employees', 3, 3, 1, 1, 7, 0, 0, 1, 1),
(3, 'Compassionate Leave', 'CL', 'Bereavement/Compassionate leave', 5, 5, 1, 1, 0, 0, 0, 1, 1);

-- =====================================================
-- 7. UPDATE PERMISSIONS AND DOCUMENT SEQUENCES
-- =====================================================

-- Add document sequences for new modules
INSERT IGNORE INTO `document_sequences` (`company_id`, `document_type`, `prefix`, `next_number`, `padding`) VALUES
(3, 'loan', 'LN', 1, 6),
(3, 'asset', 'AST', 1, 6),
(3, 'petty_cash', 'PC', 1, 6),
(3, 'leave', 'LV', 1, 6),
(3, 'payroll', 'PAY', 1, 6);

-- =====================================================
-- END OF MIGRATION
-- =====================================================

-- Migration completed successfully!
-- New modules: Loans, Assets, Petty Cash, Leave, Payroll
-- All tables created with proper indexes and default data
