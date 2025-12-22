-- =====================================================
-- MKUMBI ERP - COMPLETE DATABASE SCHEMA
-- Clean installation for all modules
-- Database: mkumbi_erp_clean
-- Created: December 22, 2025
-- Version: 2.0
-- =====================================================

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET AUTOCOMMIT = 0;
START TRANSACTION;
SET time_zone = "+00:00";

-- Drop existing database if it exists (CAUTION!)
-- DROP DATABASE IF EXISTS `mkumbi_erp_clean`;

-- Create new database
CREATE DATABASE IF NOT EXISTS `mkumbi_erp_clean` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `mkumbi_erp_clean`;

-- =====================================================
-- TABLE: companies
-- =====================================================
CREATE TABLE `companies` (
  `company_id` int(11) NOT NULL AUTO_INCREMENT,
  `company_name` varchar(255) NOT NULL,
  `company_code` varchar(50) NOT NULL UNIQUE,
  `company_type` enum('real_estate','construction','service','trading','other') DEFAULT 'real_estate',
  `registration_number` varchar(100) DEFAULT NULL,
  `tin_number` varchar(50) DEFAULT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `city` varchar(100) DEFAULT NULL,
  `country` varchar(100) DEFAULT NULL,
  `logo_path` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`company_id`),
  UNIQUE KEY `unique_code` (`company_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- TABLE: roles
-- =====================================================
CREATE TABLE `roles` (
  `role_id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL,
  `role_name` varchar(100) NOT NULL,
  `role_code` varchar(50) NOT NULL,
  `description` text DEFAULT NULL,
  `is_system_role` tinyint(1) DEFAULT 0,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`role_id`),
  KEY `fk_company` (`company_id`),
  FOREIGN KEY (`company_id`) REFERENCES `companies` (`company_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- TABLE: permissions
-- =====================================================
CREATE TABLE `permissions` (
  `permission_id` int(11) NOT NULL AUTO_INCREMENT,
  `permission_name` varchar(100) NOT NULL UNIQUE,
  `permission_code` varchar(50) NOT NULL UNIQUE,
  `module` varchar(100) DEFAULT NULL,
  `description` text DEFAULT NULL,
  PRIMARY KEY (`permission_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- TABLE: role_permissions
-- =====================================================
CREATE TABLE `role_permissions` (
  `role_permission_id` int(11) NOT NULL AUTO_INCREMENT,
  `role_id` int(11) NOT NULL,
  `permission_id` int(11) NOT NULL,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`role_permission_id`),
  UNIQUE KEY `unique_role_permission` (`role_id`, `permission_id`),
  KEY `fk_permission` (`permission_id`),
  FOREIGN KEY (`role_id`) REFERENCES `roles` (`role_id`) ON DELETE CASCADE,
  FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`permission_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- TABLE: users
-- =====================================================
CREATE TABLE `users` (
  `user_id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL UNIQUE,
  `email` varchar(150) NOT NULL UNIQUE,
  `password_hash` varchar(255) NOT NULL,
  `first_name` varchar(100) NOT NULL,
  `middle_name` varchar(100) DEFAULT NULL,
  `last_name` varchar(100) NOT NULL,
  `full_name` varchar(300) GENERATED ALWAYS AS (CONCAT(`first_name`,' ',IFNULL(CONCAT(`middle_name`,' '),''),`last_name`)) STORED,
  `phone1` varchar(50) DEFAULT NULL,
  `phone2` varchar(50) DEFAULT NULL,
  `gender` enum('male','female','other') DEFAULT NULL,
  `date_of_birth` date DEFAULT NULL,
  `national_id` varchar(50) DEFAULT NULL,
  `profile_picture` varchar(255) DEFAULT NULL,
  `status` enum('active','inactive','suspended') DEFAULT 'active',
  `last_login` datetime DEFAULT NULL,
  `password_changed_at` datetime DEFAULT NULL,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`user_id`),
  UNIQUE KEY `unique_username` (`username`),
  UNIQUE KEY `unique_email` (`email`),
  KEY `fk_company` (`company_id`),
  FOREIGN KEY (`company_id`) REFERENCES `companies` (`company_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- TABLE: user_roles
-- =====================================================
CREATE TABLE `user_roles` (
  `user_role_id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `role_id` int(11) NOT NULL,
  `assigned_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`user_role_id`),
  UNIQUE KEY `unique_user_role` (`user_id`, `role_id`),
  KEY `fk_role` (`role_id`),
  FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  FOREIGN KEY (`role_id`) REFERENCES `roles` (`role_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- TABLE: departments
-- =====================================================
CREATE TABLE `departments` (
  `department_id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL,
  `department_name` varchar(100) NOT NULL,
  `department_code` varchar(20) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `manager_id` int(11) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`department_id`),
  KEY `fk_company` (`company_id`),
  KEY `fk_manager` (`manager_id`),
  FOREIGN KEY (`company_id`) REFERENCES `companies` (`company_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- TABLE: positions
-- =====================================================
CREATE TABLE `positions` (
  `position_id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL,
  `position_name` varchar(100) NOT NULL,
  `position_code` varchar(20) DEFAULT NULL,
  `department_id` int(11) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `is_management` tinyint(1) DEFAULT 0,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`position_id`),
  KEY `fk_company` (`company_id`),
  KEY `fk_department` (`department_id`),
  FOREIGN KEY (`company_id`) REFERENCES `companies` (`company_id`) ON DELETE CASCADE,
  FOREIGN KEY (`department_id`) REFERENCES `departments` (`department_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- TABLE: employees
-- =====================================================
CREATE TABLE `employees` (
  `employee_id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `employee_number` varchar(50) NOT NULL UNIQUE,
  `first_name` varchar(100) NOT NULL,
  `last_name` varchar(100) NOT NULL,
  `middle_name` varchar(100) DEFAULT NULL,
  `full_name` varchar(300) GENERATED ALWAYS AS (CONCAT(`first_name`,' ',IFNULL(CONCAT(`middle_name`,' '),''),`last_name`)) STORED,
  `email` varchar(100) DEFAULT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `gender` enum('male','female','other') DEFAULT NULL,
  `date_of_birth` date DEFAULT NULL,
  `national_id` varchar(50) DEFAULT NULL,
  `bank_name` varchar(100) DEFAULT NULL,
  `bank_account` varchar(50) DEFAULT NULL,
  `bank_branch` varchar(100) DEFAULT NULL,
  `department_id` int(11) DEFAULT NULL,
  `position_id` int(11) DEFAULT NULL,
  `manager_id` int(11) DEFAULT NULL,
  `employment_type` enum('permanent','contract','temporary','casual') DEFAULT 'permanent',
  `employment_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `salary_scale` varchar(50) DEFAULT NULL,
  `basic_salary` decimal(15,2) DEFAULT NULL,
  `contract_renewal_date` date DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`employee_id`),
  UNIQUE KEY `unique_employee` (`employee_number`, `company_id`),
  KEY `fk_company` (`company_id`),
  KEY `fk_user` (`user_id`),
  KEY `fk_department` (`department_id`),
  KEY `fk_position` (`position_id`),
  KEY `fk_manager` (`manager_id`),
  FOREIGN KEY (`company_id`) REFERENCES `companies` (`company_id`) ON DELETE CASCADE,
  FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE SET NULL,
  FOREIGN KEY (`department_id`) REFERENCES `departments` (`department_id`) ON DELETE SET NULL,
  FOREIGN KEY (`position_id`) REFERENCES `positions` (`position_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- TABLE: leave_types
-- =====================================================
CREATE TABLE `leave_types` (
  `leave_type_id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL,
  `leave_type_name` varchar(100) NOT NULL,
  `leave_code` varchar(20) NOT NULL,
  `days_per_year` int(11) DEFAULT 0,
  `is_paid` tinyint(1) DEFAULT 1,
  `requires_approval` tinyint(1) DEFAULT 1,
  `carry_forward` tinyint(1) DEFAULT 0,
  `max_carry_forward_days` int(11) DEFAULT 0,
  `description` text DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`leave_type_id`),
  UNIQUE KEY `unique_code` (`leave_code`, `company_id`),
  KEY `fk_company` (`company_id`),
  FOREIGN KEY (`company_id`) REFERENCES `companies` (`company_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- TABLE: leave_applications
-- =====================================================
CREATE TABLE `leave_applications` (
  `leave_application_id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL,
  `employee_id` int(11) NOT NULL,
  `leave_type_id` int(11) NOT NULL,
  `leave_reference` varchar(50) DEFAULT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `total_days` decimal(5,1) DEFAULT NULL,
  `days_requested` decimal(5,1) DEFAULT NULL,
  `reason` text DEFAULT NULL,
  `relief_officer_id` int(11) DEFAULT NULL,
  `attachment_path` varchar(255) DEFAULT NULL,
  `status` enum('pending','approved','rejected','cancelled') DEFAULT 'pending',
  `rejection_reason` text DEFAULT NULL,
  `approved_by` int(11) DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`leave_application_id`),
  KEY `fk_company` (`company_id`),
  KEY `fk_employee` (`employee_id`),
  KEY `fk_leave_type` (`leave_type_id`),
  KEY `fk_relief_officer` (`relief_officer_id`),
  FOREIGN KEY (`company_id`) REFERENCES `companies` (`company_id`) ON DELETE CASCADE,
  FOREIGN KEY (`employee_id`) REFERENCES `employees` (`employee_id`) ON DELETE CASCADE,
  FOREIGN KEY (`leave_type_id`) REFERENCES `leave_types` (`leave_type_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- TABLE: leave_balances
-- =====================================================
CREATE TABLE `leave_balances` (
  `balance_id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL,
  `employee_id` int(11) NOT NULL,
  `leave_type_id` int(11) NOT NULL,
  `fiscal_year` int(11) NOT NULL,
  `entitled_days` decimal(5,1) DEFAULT 0,
  `used_days` decimal(5,1) DEFAULT 0,
  `carried_forward` decimal(5,1) DEFAULT 0,
  `balance` decimal(5,1) DEFAULT 0,
  `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`balance_id`),
  UNIQUE KEY `unique_balance` (`employee_id`, `leave_type_id`, `fiscal_year`),
  KEY `fk_company` (`company_id`),
  KEY `fk_employee` (`employee_id`),
  KEY `fk_leave_type` (`leave_type_id`),
  FOREIGN KEY (`company_id`) REFERENCES `companies` (`company_id`) ON DELETE CASCADE,
  FOREIGN KEY (`employee_id`) REFERENCES `employees` (`employee_id`) ON DELETE CASCADE,
  FOREIGN KEY (`leave_type_id`) REFERENCES `leave_types` (`leave_type_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- TABLE: loan_types
-- =====================================================
CREATE TABLE `loan_types` (
  `loan_type_id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL,
  `loan_type_name` varchar(100) NOT NULL,
  `loan_code` varchar(20) NOT NULL,
  `type_name` varchar(100) DEFAULT NULL,
  `type_code` varchar(20) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `interest_rate` decimal(5,2) DEFAULT 0,
  `min_amount` decimal(15,2) DEFAULT NULL,
  `max_amount` decimal(15,2) DEFAULT NULL,
  `min_guarantors` int(11) DEFAULT 0,
  `requires_guarantor` tinyint(1) DEFAULT 0,
  `max_repayment_months` int(11) DEFAULT 12,
  `max_term_months` int(11) DEFAULT 12,
  `max_salary_multiple` decimal(5,2) DEFAULT 3,
  `max_deduction_percent` decimal(5,2) DEFAULT 33.33,
  `processing_fee` decimal(15,2) DEFAULT 0,
  `eligibility_criteria` text DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`loan_type_id`),
  UNIQUE KEY `unique_code` (`loan_code`, `company_id`),
  KEY `fk_company` (`company_id`),
  FOREIGN KEY (`company_id`) REFERENCES `companies` (`company_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- TABLE: employee_loans
-- =====================================================
CREATE TABLE `employee_loans` (
  `loan_id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL,
  `employee_id` int(11) NOT NULL,
  `loan_type_id` int(11) NOT NULL,
  `loan_number` varchar(50) NOT NULL UNIQUE,
  `loan_reference` varchar(50) DEFAULT NULL,
  `loan_date` date NOT NULL,
  `principal_amount` decimal(15,2) NOT NULL,
  `amount` decimal(15,2) NOT NULL,
  `interest_rate` decimal(5,2) DEFAULT 0,
  `loan_term_months` int(11) DEFAULT NULL,
  `repayment_period_months` int(11) DEFAULT NULL,
  `monthly_installment` decimal(15,2) DEFAULT NULL,
  `monthly_deduction` decimal(15,2) DEFAULT NULL,
  `total_interest` decimal(15,2) DEFAULT 0,
  `total_repayable` decimal(15,2) DEFAULT NULL,
  `processing_fee` decimal(15,2) DEFAULT 0,
  `amount_disbursed` decimal(15,2) DEFAULT 0,
  `amount_repaid` decimal(15,2) DEFAULT 0,
  `balance` decimal(15,2) DEFAULT 0,
  `status` enum('pending','approved','rejected','disbursed','completed','defaulted') DEFAULT 'pending',
  `disbursement_date` date DEFAULT NULL,
  `disbursement_reference` varchar(50) DEFAULT NULL,
  `expected_completion_date` date DEFAULT NULL,
  `actual_completion_date` date DEFAULT NULL,
  `approval_date` date DEFAULT NULL,
  `approved_by` int(11) DEFAULT NULL,
  `rejection_reason` text DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`loan_id`),
  KEY `fk_company` (`company_id`),
  KEY `fk_employee` (`employee_id`),
  KEY `fk_loan_type` (`loan_type_id`),
  FOREIGN KEY (`company_id`) REFERENCES `companies` (`company_id`) ON DELETE CASCADE,
  FOREIGN KEY (`employee_id`) REFERENCES `employees` (`employee_id`) ON DELETE CASCADE,
  FOREIGN KEY (`loan_type_id`) REFERENCES `loan_types` (`loan_type_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- TABLE: loan_payments
-- =====================================================
CREATE TABLE `loan_payments` (
  `payment_id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL,
  `loan_id` int(11) NOT NULL,
  `payment_number` varchar(50) DEFAULT NULL,
  `payment_date` date NOT NULL,
  `principal_paid` decimal(15,2) DEFAULT NULL,
  `interest_paid` decimal(15,2) DEFAULT NULL,
  `amount_paid` decimal(15,2) NOT NULL,
  `payment_method` varchar(50) DEFAULT NULL,
  `reference_number` varchar(50) DEFAULT NULL,
  `status` enum('pending','completed','reversed') DEFAULT 'completed',
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`payment_id`),
  KEY `fk_company` (`company_id`),
  KEY `fk_loan` (`loan_id`),
  FOREIGN KEY (`company_id`) REFERENCES `companies` (`company_id`) ON DELETE CASCADE,
  FOREIGN KEY (`loan_id`) REFERENCES `employee_loans` (`loan_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- TABLE: asset_categories
-- =====================================================
CREATE TABLE `asset_categories` (
  `category_id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL,
  `category_name` varchar(100) NOT NULL,
  `category_code` varchar(20) DEFAULT NULL,
  `depreciation_method` enum('straight_line','declining_balance','units_of_production') DEFAULT 'straight_line',
  `useful_life_years` int(11) DEFAULT 5,
  `depreciation_rate` decimal(5,2) DEFAULT 0,
  `description` text DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`category_id`),
  UNIQUE KEY `unique_category` (`category_name`, `company_id`),
  KEY `fk_company` (`company_id`),
  FOREIGN KEY (`company_id`) REFERENCES `companies` (`company_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- TABLE: fixed_assets
-- =====================================================
CREATE TABLE `fixed_assets` (
  `asset_id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL,
  `asset_number` varchar(50) NOT NULL,
  `asset_code` varchar(50) DEFAULT NULL,
  `asset_tag` varchar(50) DEFAULT NULL,
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
  `condition_status` enum('EXCELLENT','GOOD','FAIR','POOR','UNUSABLE') DEFAULT 'GOOD',
  `insurance_policy` varchar(100) DEFAULT NULL,
  `insurance_value` decimal(15,2) DEFAULT NULL,
  `insurance_expiry` date DEFAULT NULL,
  `barcode` varchar(100) DEFAULT NULL,
  `qr_code` varchar(255) DEFAULT NULL,
  `disposal_date` date DEFAULT NULL,
  `disposal_amount` decimal(15,2) DEFAULT NULL,
  `disposal_reason` text DEFAULT NULL,
  `approval_status` enum('pending','approved','rejected') DEFAULT 'pending',
  `approved_by` int(11) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`asset_id`),
  KEY `fk_company` (`company_id`),
  KEY `fk_category` (`category_id`),
  KEY `fk_department` (`department_id`),
  FOREIGN KEY (`company_id`) REFERENCES `companies` (`company_id`) ON DELETE CASCADE,
  FOREIGN KEY (`category_id`) REFERENCES `asset_categories` (`category_id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- TABLE: asset_depreciation
-- =====================================================
CREATE TABLE `asset_depreciation` (
  `depreciation_id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL,
  `asset_id` int(11) NOT NULL,
  `depreciation_date` date NOT NULL,
  `depreciation_amount` decimal(15,2) NOT NULL,
  `accumulated_to_date` decimal(15,2) DEFAULT NULL,
  `book_value` decimal(15,2) DEFAULT NULL,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`depreciation_id`),
  KEY `fk_company` (`company_id`),
  KEY `fk_asset` (`asset_id`),
  FOREIGN KEY (`company_id`) REFERENCES `companies` (`company_id`) ON DELETE CASCADE,
  FOREIGN KEY (`asset_id`) REFERENCES `fixed_assets` (`asset_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- TABLE: asset_maintenance
-- =====================================================
CREATE TABLE `asset_maintenance` (
  `maintenance_id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL,
  `asset_id` int(11) NOT NULL,
  `maintenance_type` varchar(100) DEFAULT NULL,
  `scheduled_date` date DEFAULT NULL,
  `completion_date` date DEFAULT NULL,
  `description` text DEFAULT NULL,
  `estimated_cost` decimal(15,2) DEFAULT NULL,
  `actual_cost` decimal(15,2) DEFAULT NULL,
  `vendor_name` varchar(200) DEFAULT NULL,
  `status` enum('scheduled','in_progress','completed','cancelled') DEFAULT 'scheduled',
  `notes` text DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`maintenance_id`),
  KEY `fk_company` (`company_id`),
  KEY `fk_asset` (`asset_id`),
  FOREIGN KEY (`company_id`) REFERENCES `companies` (`company_id`) ON DELETE CASCADE,
  FOREIGN KEY (`asset_id`) REFERENCES `fixed_assets` (`asset_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- TABLE: petty_cash_accounts
-- =====================================================
CREATE TABLE `petty_cash_accounts` (
  `petty_cash_id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL,
  `account_name` varchar(100) NOT NULL,
  `account_code` varchar(20) DEFAULT NULL,
  `custodian_id` int(11) DEFAULT NULL,
  `backup_custodian_id` int(11) DEFAULT NULL,
  `current_balance` decimal(15,2) DEFAULT 0,
  `maximum_limit` decimal(15,2) DEFAULT 0,
  `transaction_limit` decimal(15,2) DEFAULT 0,
  `daily_limit` decimal(15,2) DEFAULT 0,
  `gl_account_code` varchar(20) DEFAULT NULL,
  `status` enum('active','inactive','suspended') DEFAULT 'active',
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`petty_cash_id`),
  KEY `fk_company` (`company_id`),
  KEY `fk_custodian` (`custodian_id`),
  FOREIGN KEY (`company_id`) REFERENCES `companies` (`company_id`) ON DELETE CASCADE,
  FOREIGN KEY (`custodian_id`) REFERENCES `employees` (`employee_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- TABLE: petty_cash_transactions
-- =====================================================
CREATE TABLE `petty_cash_transactions` (
  `transaction_id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL,
  `petty_cash_id` int(11) NOT NULL,
  `transaction_number` varchar(50) NOT NULL,
  `transaction_type` enum('disbursement','replenishment','adjustment','return') DEFAULT 'disbursement',
  `transaction_date` date NOT NULL,
  `amount` decimal(15,2) NOT NULL,
  `balance_before` decimal(15,2) DEFAULT 0,
  `balance_after` decimal(15,2) DEFAULT 0,
  `category` varchar(100) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `vendor_name` varchar(255) DEFAULT NULL,
  `expense_category_id` int(11) DEFAULT NULL,
  `receipt_path` varchar(255) DEFAULT NULL,
  `status` enum('pending','approved','rejected','completed') DEFAULT 'pending',
  `rejection_reason` text DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  `approved_by` int(11) DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `disbursed_by` int(11) DEFAULT NULL,
  `disbursed_at` datetime DEFAULT NULL,
  `gl_posted` tinyint(1) DEFAULT 0,
  `journal_entry_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`transaction_id`),
  KEY `fk_company` (`company_id`),
  KEY `fk_petty_cash` (`petty_cash_id`),
  FOREIGN KEY (`company_id`) REFERENCES `companies` (`company_id`) ON DELETE CASCADE,
  FOREIGN KEY (`petty_cash_id`) REFERENCES `petty_cash_accounts` (`petty_cash_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- TABLE: petty_cash_replenishments
-- =====================================================
CREATE TABLE `petty_cash_replenishments` (
  `replenishment_id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL,
  `petty_cash_id` int(11) NOT NULL,
  `replenishment_number` varchar(50) DEFAULT NULL,
  `replenishment_date` date NOT NULL,
  `amount_replenished` decimal(15,2) NOT NULL,
  `balance_before` decimal(15,2) DEFAULT NULL,
  `balance_after` decimal(15,2) DEFAULT NULL,
  `source_account` varchar(100) DEFAULT NULL,
  `approved_by` int(11) DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`replenishment_id`),
  KEY `fk_company` (`company_id`),
  KEY `fk_petty_cash` (`petty_cash_id`),
  FOREIGN KEY (`company_id`) REFERENCES `companies` (`company_id`) ON DELETE CASCADE,
  FOREIGN KEY (`petty_cash_id`) REFERENCES `petty_cash_accounts` (`petty_cash_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- TABLE: payroll
-- =====================================================
CREATE TABLE `payroll` (
  `payroll_id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL,
  `employee_id` int(11) DEFAULT NULL,
  `payroll_month` int(11) NOT NULL,
  `payroll_year` int(11) NOT NULL,
  `period_id` int(11) DEFAULT NULL,
  `pay_period_start` date DEFAULT NULL,
  `pay_period_end` date DEFAULT NULL,
  `status` enum('draft','processing','completed','paid') DEFAULT 'draft',
  `approved_by` int(11) DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `paid_by` int(11) DEFAULT NULL,
  `paid_at` datetime DEFAULT NULL,
  `payment_method` varchar(50) DEFAULT NULL,
  `payment_reference` varchar(100) DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`payroll_id`),
  UNIQUE KEY `unique_payroll` (`company_id`, `payroll_month`, `payroll_year`),
  KEY `fk_employee` (`employee_id`),
  FOREIGN KEY (`company_id`) REFERENCES `companies` (`company_id`) ON DELETE CASCADE,
  FOREIGN KEY (`employee_id`) REFERENCES `employees` (`employee_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- TABLE: payroll_details
-- =====================================================
CREATE TABLE `payroll_details` (
  `payroll_detail_id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL,
  `payroll_id` int(11) NOT NULL,
  `employee_id` int(11) NOT NULL,
  `basic_salary` decimal(15,2) DEFAULT 0,
  `housing_allowance` decimal(15,2) DEFAULT 0,
  `transport_allowance` decimal(15,2) DEFAULT 0,
  `medical_allowance` decimal(15,2) DEFAULT 0,
  `meal_allowance` decimal(15,2) DEFAULT 0,
  `other_allowances` decimal(15,2) DEFAULT 0,
  `allowances_total` decimal(15,2) DEFAULT 0,
  `overtime_hours` decimal(10,2) DEFAULT 0,
  `overtime_rate` decimal(10,2) DEFAULT 0,
  `overtime_pay` decimal(15,2) DEFAULT 0,
  `bonus` decimal(15,2) DEFAULT 0,
  `commission` decimal(15,2) DEFAULT 0,
  `arrears` decimal(15,2) DEFAULT 0,
  `gross_pay` decimal(15,2) DEFAULT 0,
  `paye` decimal(15,2) DEFAULT 0,
  `nhif` decimal(15,2) DEFAULT 0,
  `wcf_amount` decimal(15,2) DEFAULT 0,
  `sdl_amount` decimal(15,2) DEFAULT 0,
  `total_deductions` decimal(15,2) DEFAULT 0,
  `salary_advance` decimal(15,2) DEFAULT 0,
  `loan_deduction` decimal(15,2) DEFAULT 0,
  `net_pay` decimal(15,2) DEFAULT 0,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`payroll_detail_id`),
  KEY `fk_company` (`company_id`),
  KEY `fk_payroll` (`payroll_id`),
  KEY `fk_employee` (`employee_id`),
  FOREIGN KEY (`company_id`) REFERENCES `companies` (`company_id`) ON DELETE CASCADE,
  FOREIGN KEY (`payroll_id`) REFERENCES `payroll` (`payroll_id`) ON DELETE CASCADE,
  FOREIGN KEY (`employee_id`) REFERENCES `employees` (`employee_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- TABLE: audit_log
-- =====================================================
CREATE TABLE `audit_log` (
  `audit_id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `action` varchar(100) DEFAULT NULL,
  `module` varchar(100) DEFAULT NULL,
  `table_name` varchar(100) DEFAULT NULL,
  `record_id` int(11) DEFAULT NULL,
  `old_values` longtext DEFAULT NULL,
  `new_values` longtext DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`audit_id`),
  KEY `fk_company` (`company_id`),
  KEY `fk_user` (`user_id`),
  KEY `idx_action` (`action`),
  KEY `idx_timestamp` (`created_at`),
  FOREIGN KEY (`company_id`) REFERENCES `companies` (`company_id`) ON DELETE CASCADE,
  FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- TABLE: system_settings
-- =====================================================
CREATE TABLE `system_settings` (
  `setting_id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text DEFAULT NULL,
  `setting_type` enum('text','number','boolean','json') DEFAULT 'text',
  `description` text DEFAULT NULL,
  `is_editable` tinyint(1) DEFAULT 1,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`setting_id`),
  UNIQUE KEY `unique_setting` (`company_id`, `setting_key`),
  KEY `fk_company` (`company_id`),
  FOREIGN KEY (`company_id`) REFERENCES `companies` (`company_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- INSERT INITIAL DATA
-- =====================================================

-- Insert default company
INSERT INTO `companies` (`company_id`, `company_name`, `company_code`, `company_type`, `phone`, `email`, `address`, `is_active`)
VALUES (3, 'Mkumbi Investments', 'MKUMBI', 'real_estate', '+255657123456', 'info@mkumbi.com', 'Dar es Salaam, Tanzania', 1);

-- Insert default roles
INSERT INTO `roles` (`company_id`, `role_name`, `role_code`, `is_system_role`, `is_active`)
VALUES 
(3, 'Super Admin', 'SUPER_ADMIN', 1, 1),
(3, 'Company Admin', 'COMPANY_ADMIN', 1, 1),
(3, 'Finance Officer', 'FINANCE_OFFICER', 0, 1),
(3, 'HR Officer', 'HR_OFFICER', 0, 1),
(3, 'Accountant', 'ACCOUNTANT', 0, 1),
(3, 'Employee', 'EMPLOYEE', 0, 1);

-- Insert default departments
INSERT INTO `departments` (`company_id`, `department_name`, `department_code`, `is_active`)
VALUES 
(3, 'Finance', 'FIN', 1),
(3, 'Human Resources', 'HR', 1),
(3, 'Operations', 'OPS', 1),
(3, 'Sales & Marketing', 'SAL', 1),
(3, 'Administration', 'ADM', 1);

-- Insert default leave types
INSERT INTO `leave_types` (`company_id`, `leave_type_name`, `leave_code`, `days_per_year`, `is_paid`, `requires_approval`, `carry_forward`, `description`)
VALUES 
(3, 'Annual Leave', 'AL', 28, 1, 1, 1, 'Standard annual leave'),
(3, 'Sick Leave', 'SL', 14, 1, 1, 0, 'Sick leave'),
(3, 'Maternity Leave', 'ML', 84, 1, 1, 0, 'Maternity leave'),
(3, 'Paternity Leave', 'PL', 3, 1, 1, 0, 'Paternity leave'),
(3, 'Compassionate Leave', 'CL', 5, 1, 1, 0, 'Bereavement leave'),
(3, 'Unpaid Leave', 'UL', 30, 0, 1, 0, 'Unpaid leave');

-- Insert default loan types
INSERT INTO `loan_types` (`company_id`, `loan_type_name`, `loan_code`, `type_name`, `type_code`, `interest_rate`, `min_amount`, `max_amount`, `max_repayment_months`, `requires_guarantor`, `is_active`)
VALUES 
(3, 'Salary Advance', 'SA', 'Salary Advance', 'SA', 0, 50000, 500000, 3, 0, 1),
(3, 'Personal Loan', 'PL', 'Personal Loan', 'PL', 12, 100000, 5000000, 24, 1, 1),
(3, 'Emergency Loan', 'EL', 'Emergency Loan', 'EL', 10, 50000, 2000000, 12, 0, 1),
(3, 'Education Loan', 'ED', 'Education Loan', 'ED', 8, 500000, 10000000, 36, 1, 1);

-- Insert default asset categories
INSERT INTO `asset_categories` (`company_id`, `category_name`, `category_code`, `depreciation_rate`, `useful_life_years`, `depreciation_method`, `is_active`)
VALUES 
(3, 'Computer Equipment', 'IT', 33.33, 3, 'straight_line', 1),
(3, 'Office Furniture', 'FUR', 12.5, 8, 'straight_line', 1),
(3, 'Vehicles', 'VEH', 25, 4, 'declining_balance', 1),
(3, 'Office Equipment', 'OEQ', 20, 5, 'straight_line', 1),
(3, 'Buildings', 'BLD', 2.5, 40, 'straight_line', 1),
(3, 'Land', 'LND', 0, 0, 'straight_line', 1);

-- Insert default system settings
INSERT INTO `system_settings` (`company_id`, `setting_key`, `setting_value`, `setting_type`, `description`, `is_editable`)
VALUES
(3, 'date_format', 'Y-m-d', 'text', 'System date format', 1),
(3, 'currency_code', 'TZS', 'text', 'Default currency code', 1),
(3, 'currency_symbol', 'TSH', 'text', 'Default currency symbol', 1),
(3, 'nssf_employee_rate', '10', 'number', 'NSSF employee contribution rate (%)', 1),
(3, 'nssf_employer_rate', '10', 'number', 'NSSF employer contribution rate (%)', 1),
(3, 'nhif_rate', '3', 'number', 'NHIF contribution rate (%)', 1),
(3, 'paye_threshold', '270000', 'number', 'PAYE tax-free threshold (TZS)', 1),
(3, 'wcf_rate', '1', 'number', 'Workers Compensation Fund rate (%)', 1),
(3, 'sdl_rate', '4.5', 'number', 'Skills Development Levy rate (%)', 1),
(3, 'fiscal_year_start', '7', 'number', 'Fiscal year start month (July)', 1),
(3, 'leave_year_start', '1', 'number', 'Leave year start month (January)', 1);

-- =====================================================
-- COMMIT
-- =====================================================

COMMIT;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

-- Database creation complete!
