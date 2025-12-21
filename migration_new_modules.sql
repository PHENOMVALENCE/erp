-- =====================================================
-- Mkumbi ERP - Database Migration Script
-- New Modules: Leave, Loans, Assets, Petty Cash, Reports
-- Run this script to add required tables
-- =====================================================

-- =====================================================
-- LEAVE MANAGEMENT TABLES
-- =====================================================

-- Leave Types Table
CREATE TABLE IF NOT EXISTS `leave_types` (
    `leave_type_id` INT AUTO_INCREMENT PRIMARY KEY,
    `company_id` INT NOT NULL,
    `leave_type_name` VARCHAR(100) NOT NULL,
    `leave_code` VARCHAR(20),
    `days_per_year` INT DEFAULT 0,
    `is_paid` TINYINT(1) DEFAULT 1,
    `requires_approval` TINYINT(1) DEFAULT 1,
    `carry_forward` TINYINT(1) DEFAULT 0,
    `max_carry_forward_days` INT DEFAULT 0,
    `description` TEXT,
    `is_active` TINYINT(1) DEFAULT 1,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_company` (`company_id`),
    INDEX `idx_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Leave Applications Table
CREATE TABLE IF NOT EXISTS `leave_applications` (
    `leave_id` INT AUTO_INCREMENT PRIMARY KEY,
    `company_id` INT NOT NULL,
    `employee_id` INT NOT NULL,
    `leave_type_id` INT NOT NULL,
    `leave_reference` VARCHAR(50),
    `start_date` DATE NOT NULL,
    `end_date` DATE NOT NULL,
    `days_requested` DECIMAL(5,1) NOT NULL,
    `reason` TEXT,
    `status` ENUM('PENDING', 'APPROVED', 'REJECTED', 'CANCELLED') DEFAULT 'PENDING',
    `approved_by` INT,
    `approved_at` DATETIME,
    `rejection_reason` TEXT,
    `relief_officer_id` INT,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_company` (`company_id`),
    INDEX `idx_employee` (`employee_id`),
    INDEX `idx_status` (`status`),
    INDEX `idx_dates` (`start_date`, `end_date`),
    FOREIGN KEY (`leave_type_id`) REFERENCES `leave_types`(`leave_type_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Leave Balances Table
CREATE TABLE IF NOT EXISTS `leave_balances` (
    `balance_id` INT AUTO_INCREMENT PRIMARY KEY,
    `employee_id` INT NOT NULL,
    `leave_type_id` INT NOT NULL,
    `year` INT NOT NULL,
    `entitled_days` DECIMAL(5,1) DEFAULT 0,
    `used_days` DECIMAL(5,1) DEFAULT 0,
    `carried_forward` DECIMAL(5,1) DEFAULT 0,
    `balance` DECIMAL(5,1) GENERATED ALWAYS AS (entitled_days + carried_forward - used_days) STORED,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `unique_balance` (`employee_id`, `leave_type_id`, `year`),
    INDEX `idx_employee_year` (`employee_id`, `year`),
    FOREIGN KEY (`leave_type_id`) REFERENCES `leave_types`(`leave_type_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insert default leave types
INSERT INTO `leave_types` (`company_id`, `leave_type_name`, `leave_code`, `days_per_year`, `is_paid`, `requires_approval`, `carry_forward`, `description`) VALUES
(1, 'Annual Leave', 'AL', 28, 1, 1, 1, 'Standard annual leave entitlement'),
(1, 'Sick Leave', 'SL', 14, 1, 1, 0, 'Sick leave with medical certificate'),
(1, 'Maternity Leave', 'ML', 84, 1, 1, 0, 'Maternity leave for female employees'),
(1, 'Paternity Leave', 'PL', 3, 1, 1, 0, 'Paternity leave for male employees'),
(1, 'Compassionate Leave', 'CL', 5, 1, 1, 0, 'Leave for bereavement or family emergencies'),
(1, 'Unpaid Leave', 'UL', 30, 0, 1, 0, 'Leave without pay');

-- =====================================================
-- LOAN MANAGEMENT TABLES
-- =====================================================

-- Loan Types Table
CREATE TABLE IF NOT EXISTS `loan_types` (
    `loan_type_id` INT AUTO_INCREMENT PRIMARY KEY,
    `company_id` INT NOT NULL,
    `loan_type_name` VARCHAR(100) NOT NULL,
    `loan_code` VARCHAR(20),
    `interest_rate` DECIMAL(5,2) DEFAULT 0 COMMENT 'Annual interest rate %',
    `min_amount` DECIMAL(15,2) DEFAULT 0,
    `max_amount` DECIMAL(15,2) DEFAULT 0,
    `max_term_months` INT DEFAULT 12,
    `requires_guarantor` TINYINT(1) DEFAULT 0,
    `max_salary_multiple` DECIMAL(5,2) DEFAULT 3 COMMENT 'Max loan as multiple of salary',
    `description` TEXT,
    `is_active` TINYINT(1) DEFAULT 1,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_company` (`company_id`),
    INDEX `idx_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Employee Loans Table
CREATE TABLE IF NOT EXISTS `employee_loans` (
    `loan_id` INT AUTO_INCREMENT PRIMARY KEY,
    `company_id` INT NOT NULL,
    `employee_id` INT NOT NULL,
    `loan_type_id` INT NOT NULL,
    `loan_reference` VARCHAR(50) NOT NULL,
    `loan_amount` DECIMAL(15,2) NOT NULL,
    `interest_rate` DECIMAL(5,2) NOT NULL,
    `loan_term_months` INT NOT NULL,
    `monthly_installment` DECIMAL(15,2) NOT NULL,
    `total_repayable` DECIMAL(15,2) NOT NULL,
    `principal_outstanding` DECIMAL(15,2) NOT NULL,
    `interest_outstanding` DECIMAL(15,2) NOT NULL,
    `total_outstanding` DECIMAL(15,2) GENERATED ALWAYS AS (principal_outstanding + interest_outstanding) STORED,
    `purpose` TEXT,
    `status` ENUM('PENDING', 'APPROVED', 'REJECTED', 'DISBURSED', 'ACTIVE', 'COMPLETED', 'CANCELLED', 'DEFAULTED') DEFAULT 'PENDING',
    `applied_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `approved_by` INT,
    `approved_at` DATETIME,
    `rejection_reason` TEXT,
    `disbursed_at` DATETIME,
    `disbursed_by` INT,
    `payment_method` VARCHAR(50),
    `payment_reference` VARCHAR(100),
    `repayment_start_date` DATE,
    `last_payment_date` DATE,
    `cancelled_at` DATETIME,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `unique_reference` (`loan_reference`),
    INDEX `idx_company` (`company_id`),
    INDEX `idx_employee` (`employee_id`),
    INDEX `idx_status` (`status`),
    FOREIGN KEY (`loan_type_id`) REFERENCES `loan_types`(`loan_type_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Loan Repayment Schedule Table
CREATE TABLE IF NOT EXISTS `loan_repayment_schedule` (
    `schedule_id` INT AUTO_INCREMENT PRIMARY KEY,
    `loan_id` INT NOT NULL,
    `installment_number` INT NOT NULL,
    `due_date` DATE NOT NULL,
    `principal_amount` DECIMAL(15,2) NOT NULL,
    `interest_amount` DECIMAL(15,2) NOT NULL,
    `total_amount` DECIMAL(15,2) NOT NULL,
    `balance_after` DECIMAL(15,2) NOT NULL,
    `status` ENUM('PENDING', 'PAID', 'PARTIAL', 'OVERDUE') DEFAULT 'PENDING',
    `paid_date` DATE,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_loan` (`loan_id`),
    INDEX `idx_due_date` (`due_date`),
    INDEX `idx_status` (`status`),
    FOREIGN KEY (`loan_id`) REFERENCES `employee_loans`(`loan_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Loan Payments Table
CREATE TABLE IF NOT EXISTS `loan_payments` (
    `payment_id` INT AUTO_INCREMENT PRIMARY KEY,
    `loan_id` INT NOT NULL,
    `schedule_id` INT,
    `payment_reference` VARCHAR(50) NOT NULL,
    `payment_date` DATE NOT NULL,
    `amount_paid` DECIMAL(15,2) NOT NULL,
    `principal_paid` DECIMAL(15,2) NOT NULL,
    `interest_paid` DECIMAL(15,2) NOT NULL,
    `payment_method` ENUM('SALARY_DEDUCTION', 'BANK_TRANSFER', 'CASH', 'CHEQUE', 'MOBILE_MONEY') DEFAULT 'SALARY_DEDUCTION',
    `payment_reference_external` VARCHAR(100),
    `recorded_by` INT,
    `notes` TEXT,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `unique_reference` (`payment_reference`),
    INDEX `idx_loan` (`loan_id`),
    INDEX `idx_date` (`payment_date`),
    FOREIGN KEY (`loan_id`) REFERENCES `employee_loans`(`loan_id`),
    FOREIGN KEY (`schedule_id`) REFERENCES `loan_repayment_schedule`(`schedule_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Loan Guarantors Table
CREATE TABLE IF NOT EXISTS `loan_guarantors` (
    `guarantor_id` INT AUTO_INCREMENT PRIMARY KEY,
    `loan_id` INT NOT NULL,
    `guarantor_employee_id` INT NOT NULL,
    `guarantee_amount` DECIMAL(15,2),
    `status` ENUM('PENDING', 'APPROVED', 'DECLINED') DEFAULT 'PENDING',
    `approved_at` DATETIME,
    `notes` TEXT,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_loan` (`loan_id`),
    INDEX `idx_guarantor` (`guarantor_employee_id`),
    FOREIGN KEY (`loan_id`) REFERENCES `employee_loans`(`loan_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insert default loan types
INSERT INTO `loan_types` (`company_id`, `loan_type_name`, `loan_code`, `interest_rate`, `min_amount`, `max_amount`, `max_term_months`, `requires_guarantor`, `description`) VALUES
(1, 'Salary Advance', 'SA', 0, 50000, 500000, 3, 0, 'Short-term salary advance'),
(1, 'Personal Loan', 'PL', 12, 100000, 5000000, 24, 1, 'Personal loan with interest'),
(1, 'Emergency Loan', 'EL', 10, 50000, 2000000, 12, 0, 'Emergency loan for urgent needs'),
(1, 'Education Loan', 'ED', 8, 500000, 10000000, 36, 1, 'Loan for education expenses');

-- =====================================================
-- ASSET MANAGEMENT TABLES
-- =====================================================

-- Asset Categories Table
CREATE TABLE IF NOT EXISTS `asset_categories` (
    `category_id` INT AUTO_INCREMENT PRIMARY KEY,
    `company_id` INT,
    `category_name` VARCHAR(100) NOT NULL,
    `category_code` VARCHAR(20),
    `depreciation_rate` DECIMAL(5,2) DEFAULT 0 COMMENT 'Annual depreciation rate %',
    `depreciation_method` ENUM('STRAIGHT_LINE', 'DECLINING_BALANCE') DEFAULT 'STRAIGHT_LINE',
    `useful_life_years` INT DEFAULT 5,
    `description` TEXT,
    `is_active` TINYINT(1) DEFAULT 1,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_company` (`company_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Assets Table
CREATE TABLE IF NOT EXISTS `assets` (
    `asset_id` INT AUTO_INCREMENT PRIMARY KEY,
    `company_id` INT NOT NULL,
    `asset_name` VARCHAR(255) NOT NULL,
    `asset_code` VARCHAR(50) NOT NULL,
    `category_id` INT,
    `description` TEXT,
    `serial_number` VARCHAR(100),
    `purchase_date` DATE NOT NULL,
    `purchase_cost` DECIMAL(15,2) NOT NULL,
    `current_value` DECIMAL(15,2) NOT NULL,
    `salvage_value` DECIMAL(15,2) DEFAULT 0,
    `supplier` VARCHAR(255),
    `warranty_expiry` DATE,
    `location` VARCHAR(255),
    `department_id` INT,
    `assigned_to` INT,
    `status` ENUM('ACTIVE', 'MAINTENANCE', 'DISPOSED', 'LOST', 'DAMAGED') DEFAULT 'ACTIVE',
    `disposal_date` DATE,
    `disposal_reason` TEXT,
    `disposal_value` DECIMAL(15,2),
    `notes` TEXT,
    `image_path` VARCHAR(255),
    `created_by` INT,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `unique_code` (`company_id`, `asset_code`),
    INDEX `idx_company` (`company_id`),
    INDEX `idx_category` (`category_id`),
    INDEX `idx_status` (`status`),
    INDEX `idx_department` (`department_id`),
    FOREIGN KEY (`category_id`) REFERENCES `asset_categories`(`category_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Asset Depreciation Table
CREATE TABLE IF NOT EXISTS `asset_depreciation` (
    `depreciation_id` INT AUTO_INCREMENT PRIMARY KEY,
    `asset_id` INT NOT NULL,
    `depreciation_date` DATE NOT NULL,
    `depreciation_amount` DECIMAL(15,2) NOT NULL,
    `book_value_before` DECIMAL(15,2) NOT NULL,
    `book_value_after` DECIMAL(15,2) NOT NULL,
    `depreciation_method` VARCHAR(50),
    `created_by` INT,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_asset` (`asset_id`),
    INDEX `idx_date` (`depreciation_date`),
    FOREIGN KEY (`asset_id`) REFERENCES `assets`(`asset_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Asset Maintenance Table
CREATE TABLE IF NOT EXISTS `asset_maintenance` (
    `maintenance_id` INT AUTO_INCREMENT PRIMARY KEY,
    `asset_id` INT NOT NULL,
    `maintenance_type` ENUM('PREVENTIVE', 'CORRECTIVE', 'INSPECTION', 'UPGRADE') DEFAULT 'PREVENTIVE',
    `scheduled_date` DATE,
    `completed_date` DATE,
    `description` TEXT,
    `vendor` VARCHAR(255),
    `estimated_cost` DECIMAL(15,2),
    `actual_cost` DECIMAL(15,2),
    `status` ENUM('SCHEDULED', 'IN_PROGRESS', 'COMPLETED', 'CANCELLED') DEFAULT 'SCHEDULED',
    `notes` TEXT,
    `created_by` INT,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_asset` (`asset_id`),
    INDEX `idx_status` (`status`),
    INDEX `idx_scheduled` (`scheduled_date`),
    FOREIGN KEY (`asset_id`) REFERENCES `assets`(`asset_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insert default asset categories
INSERT INTO `asset_categories` (`company_id`, `category_name`, `category_code`, `depreciation_rate`, `depreciation_method`, `useful_life_years`) VALUES
(1, 'Computer Equipment', 'IT', 33.33, 'STRAIGHT_LINE', 3),
(1, 'Office Furniture', 'FUR', 12.5, 'STRAIGHT_LINE', 8),
(1, 'Vehicles', 'VEH', 25, 'DECLINING_BALANCE', 4),
(1, 'Office Equipment', 'OEQ', 20, 'STRAIGHT_LINE', 5),
(1, 'Buildings', 'BLD', 2.5, 'STRAIGHT_LINE', 40),
(1, 'Land', 'LND', 0, 'STRAIGHT_LINE', 0),
(1, 'Machinery', 'MCH', 15, 'STRAIGHT_LINE', 7);

-- =====================================================
-- PETTY CASH TABLES
-- =====================================================

-- Petty Cash Accounts Table
CREATE TABLE IF NOT EXISTS `petty_cash_accounts` (
    `account_id` INT AUTO_INCREMENT PRIMARY KEY,
    `company_id` INT NOT NULL,
    `account_name` VARCHAR(100) NOT NULL,
    `account_code` VARCHAR(20),
    `maximum_balance` DECIMAL(15,2) NOT NULL,
    `current_balance` DECIMAL(15,2) DEFAULT 0,
    `single_transaction_limit` DECIMAL(15,2) DEFAULT 0,
    `custodian_id` INT,
    `description` TEXT,
    `is_active` TINYINT(1) DEFAULT 1,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_company` (`company_id`),
    INDEX `idx_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Petty Cash Transactions Table
CREATE TABLE IF NOT EXISTS `petty_cash_transactions` (
    `transaction_id` INT AUTO_INCREMENT PRIMARY KEY,
    `account_id` INT NOT NULL,
    `transaction_reference` VARCHAR(50) NOT NULL,
    `transaction_type` ENUM('REPLENISHMENT', 'DISBURSEMENT') NOT NULL,
    `transaction_date` DATE NOT NULL,
    `amount` DECIMAL(15,2) NOT NULL,
    `description` TEXT,
    `category` VARCHAR(50),
    `receipt_number` VARCHAR(100),
    `requested_by` INT,
    `approved_by` INT,
    `approved_at` DATETIME,
    `status` ENUM('PENDING', 'APPROVED', 'REJECTED', 'CANCELLED') DEFAULT 'PENDING',
    `rejection_reason` TEXT,
    `comments` TEXT,
    `receipt_path` VARCHAR(255),
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `unique_reference` (`transaction_reference`),
    INDEX `idx_account` (`account_id`),
    INDEX `idx_status` (`status`),
    INDEX `idx_date` (`transaction_date`),
    INDEX `idx_type` (`transaction_type`),
    FOREIGN KEY (`account_id`) REFERENCES `petty_cash_accounts`(`account_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insert default petty cash account
INSERT INTO `petty_cash_accounts` (`company_id`, `account_name`, `account_code`, `maximum_balance`, `current_balance`, `single_transaction_limit`) VALUES
(1, 'Main Office Petty Cash', 'PC001', 1000000, 500000, 100000);

-- =====================================================
-- PAYROLL RELATED TABLES (if not exist)
-- =====================================================

-- Payroll Table
CREATE TABLE IF NOT EXISTS `payroll` (
    `payroll_id` INT AUTO_INCREMENT PRIMARY KEY,
    `company_id` INT NOT NULL,
    `employee_id` INT NOT NULL,
    `pay_period_start` DATE NOT NULL,
    `pay_period_end` DATE NOT NULL,
    `payment_date` DATE,
    `status` ENUM('DRAFT', 'PROCESSING', 'APPROVED', 'PAID', 'CANCELLED') DEFAULT 'DRAFT',
    `approved_by` INT,
    `approved_at` DATETIME,
    `created_by` INT,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_company` (`company_id`),
    INDEX `idx_employee` (`employee_id`),
    INDEX `idx_period` (`pay_period_start`, `pay_period_end`),
    INDEX `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Payroll Details Table
CREATE TABLE IF NOT EXISTS `payroll_details` (
    `detail_id` INT AUTO_INCREMENT PRIMARY KEY,
    `payroll_id` INT NOT NULL,
    `basic_salary` DECIMAL(15,2) DEFAULT 0,
    `housing_allowance` DECIMAL(15,2) DEFAULT 0,
    `transport_allowance` DECIMAL(15,2) DEFAULT 0,
    `other_allowances` DECIMAL(15,2) DEFAULT 0,
    `allowances` DECIMAL(15,2) GENERATED ALWAYS AS (housing_allowance + transport_allowance + other_allowances) STORED,
    `overtime_hours` DECIMAL(10,2) DEFAULT 0,
    `overtime_rate` DECIMAL(10,2) DEFAULT 0,
    `overtime_pay` DECIMAL(15,2) DEFAULT 0,
    `bonus` DECIMAL(15,2) DEFAULT 0,
    `commission` DECIMAL(15,2) DEFAULT 0,
    `gross_salary` DECIMAL(15,2) DEFAULT 0,
    `tax_amount` DECIMAL(15,2) DEFAULT 0 COMMENT 'PAYE',
    `nssf_employee` DECIMAL(15,2) DEFAULT 0,
    `nssf_employer` DECIMAL(15,2) DEFAULT 0,
    `nhif_amount` DECIMAL(15,2) DEFAULT 0,
    `loan_deduction` DECIMAL(15,2) DEFAULT 0,
    `other_deductions` DECIMAL(15,2) DEFAULT 0,
    `total_deductions` DECIMAL(15,2) DEFAULT 0,
    `net_salary` DECIMAL(15,2) DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_payroll` (`payroll_id`),
    FOREIGN KEY (`payroll_id`) REFERENCES `payroll`(`payroll_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================================================
-- AUDIT LOG TABLE (if not exist)
-- =====================================================

CREATE TABLE IF NOT EXISTS `audit_logs` (
    `log_id` INT AUTO_INCREMENT PRIMARY KEY,
    `company_id` INT,
    `user_id` INT,
    `action` VARCHAR(50) NOT NULL,
    `module` VARCHAR(50),
    `table_name` VARCHAR(100),
    `record_id` INT,
    `old_values` JSON,
    `new_values` JSON,
    `ip_address` VARCHAR(45),
    `user_agent` TEXT,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_company` (`company_id`),
    INDEX `idx_user` (`user_id`),
    INDEX `idx_module` (`module`),
    INDEX `idx_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================================================
-- USER PERMISSIONS TABLE (if not exist)
-- =====================================================

CREATE TABLE IF NOT EXISTS `user_permissions` (
    `permission_id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `permission_name` VARCHAR(100) NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `unique_permission` (`user_id`, `permission_name`),
    INDEX `idx_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insert sample permissions for admin user (user_id = 1)
INSERT IGNORE INTO `user_permissions` (`user_id`, `permission_name`) VALUES
(1, 'SUPER_ADMIN'),
(1, 'COMPANY_ADMIN'),
(1, 'HR_OFFICER'),
(1, 'FINANCE_OFFICER'),
(1, 'ACCOUNTANT'),
(1, 'SALES_MANAGER');

-- =====================================================
-- ADD COLUMNS TO EXISTING TABLES (if needed)
-- =====================================================

-- Add loan_deduction to employees if not exists
-- ALTER TABLE `employees` ADD COLUMN IF NOT EXISTS `loan_deduction` DECIMAL(15,2) DEFAULT 0;

-- =====================================================
-- COMPLETE!
-- =====================================================

SELECT 'Migration completed successfully!' AS Status;
