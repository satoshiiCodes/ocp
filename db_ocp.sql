-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Generation Time: Oct 01, 2026 at 02:09 PM
-- Server version: 8.4.3
-- PHP Version: 8.3.16

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `db_skyline`
--

-- --------------------------------------------------------

--
-- Table structure for table `attendance`
--

CREATE TABLE `attendance` (
  `id` int NOT NULL,
  `employee_id` int NOT NULL,
  `employee_name` varchar(100) NOT NULL,
  `department` varchar(100) DEFAULT NULL,
  `attendance_date` date NOT NULL,
  `check_in` time DEFAULT NULL,
  `break_out` time DEFAULT NULL,
  `break_in` time DEFAULT NULL,
  `check_out` time DEFAULT NULL,
  `late_time` int DEFAULT '0',
  `over_time` int DEFAULT '0',
  `status` enum('Present','Half Day','Absent') NOT NULL DEFAULT 'Present',
  `remarks` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `created_by` int DEFAULT NULL,
  `updated_by` int DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `attendance`
--

INSERT INTO `attendance` (`id`, `employee_id`, `employee_name`, `department`, `attendance_date`, `check_in`, `break_out`, `break_in`, `check_out`, `late_time`, `over_time`, `status`, `remarks`, `created_at`, `updated_at`, `created_by`, `updated_by`) VALUES
(170, 5, 'Jorem B. Barangan', 'Company', '2026-07-09', '06:21:00', '12:01:00', '13:45:00', '17:05:00', 30, 0, 'Present', '', '2026-10-01 09:18:56', '2026-10-01 09:18:56', 15, NULL),
(171, 4, 'Rodel T. Cumbali', 'Company', '2026-07-09', '08:50:00', '12:00:00', '12:45:00', '17:05:00', 35, 0, 'Present', '', '2026-10-01 09:18:56', '2026-10-01 09:18:56', 15, NULL),
(172, 4, 'Rodel T. Cumbali', 'Company', '2026-07-25', '08:50:00', '12:00:00', '12:45:00', '17:05:00', 35, 0, 'Present', '', '2026-10-01 09:18:56', '2026-10-01 09:18:56', 15, NULL),
(173, 4, 'Rodel T. Cumbali', 'Company', '2026-07-26', '08:50:00', '12:00:00', '12:45:00', '17:05:00', 35, 0, 'Present', '', '2026-10-01 09:18:56', '2026-10-01 09:18:56', 15, NULL),
(174, 4, 'Rodel T. Cumbali', 'Company', '2026-07-27', '08:51:00', '12:00:00', '12:45:00', '17:05:00', 36, 30, 'Present', '', '2026-10-01 09:18:56', '2026-10-01 09:19:24', 15, 15),
(175, 3, 'Tom R. Damaso', 'Company', '2026-07-09', '08:58:00', '12:04:00', '12:45:00', '17:06:00', 43, 0, 'Present', '', '2026-10-01 09:18:56', '2026-10-01 09:18:56', 15, NULL),
(176, 3, 'Tom R. Damaso', 'Company', '2026-07-25', '08:58:00', '12:04:00', '12:45:00', '17:06:00', 43, 0, 'Present', '', '2026-10-01 09:18:56', '2026-10-01 09:18:56', 15, NULL),
(177, 3, 'Tom R. Damaso', 'Company', '2026-07-26', '08:58:00', '12:04:00', '12:45:00', '17:06:00', 43, 0, 'Present', '', '2026-10-01 09:18:56', '2026-10-01 09:18:56', 15, NULL),
(178, 3, 'Tom R. Damaso', 'Company', '2026-07-27', '08:58:00', '12:04:00', '12:45:00', '17:06:00', 43, 0, 'Present', '', '2026-10-01 09:18:56', '2026-10-01 09:18:56', 15, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `cash_on_hand`
--

CREATE TABLE `cash_on_hand` (
  `id` int NOT NULL,
  `transaction_type` enum('in','out') COLLATE utf8mb4_general_ci NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `previous_balance` decimal(10,2) NOT NULL DEFAULT '0.00',
  `balance` decimal(10,2) NOT NULL DEFAULT '0.00',
  `transaction_date` date NOT NULL,
  `description` text COLLATE utf8mb4_general_ci NOT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `cash_on_hand`
--

INSERT INTO `cash_on_hand` (`id`, `transaction_type`, `amount`, `previous_balance`, `balance`, `transaction_date`, `description`, `created_by`, `created_at`) VALUES
(2, 'in', 100000.00, 0.00, 100000.00, '2026-02-23', 'dasdw', 8, '2026-02-23 09:47:06'),
(3, 'out', 1000.00, 100000.00, 99000.00, '2026-02-23', 'Expense added: Cash Advance for Employee: Jorem Barangan', 8, '2026-02-23 10:00:15'),
(4, 'in', 500.00, 99000.00, 99500.00, '2026-02-23', 'Expense decreased by ₱500.00 (Edit): Cash Advance for Employee: Jorem Barangan', 8, '2026-02-23 10:01:59'),
(5, 'in', 500.00, 99500.00, 100000.00, '2026-02-23', 'Expense deleted (reversal): Cash Advance for Employee: Jorem Barangan', 8, '2026-02-23 10:02:57'),
(6, 'out', 100.00, 100000.00, 99900.00, '2026-02-23', 'Expense added: Parcel for CEO: CEO C. CEO', 8, '2026-02-23 10:03:51'),
(7, 'out', 100.00, 99900.00, 99800.00, '2026-02-23', 'Expense increased by ₱100.00 (Edit): Parcel for CEO: CEO C. CEO', 8, '2026-02-23 10:04:18'),
(8, 'out', 500.00, 99800.00, 99300.00, '2026-02-23', 'Expense added: Cash Advance for Employee: Juls Garcia', 8, '2026-02-23 10:27:56'),
(9, 'in', 100000.00, 99300.00, 199300.00, '2026-02-23', 'sdw', 8, '2026-02-23 14:32:46'),
(10, 'out', 1000.00, 199300.00, 198300.00, '2026-02-27', 'Expense added: Food / Market', 7, '2026-02-27 15:10:33'),
(11, 'out', 1000.00, 198300.00, 197300.00, '2026-02-27', 'Expense added: Parcel for CEO: CEO C. CEO2', 15, '2026-02-27 15:11:47'),
(13, 'out', 1000.00, 197300.00, 196300.00, '2026-10-01', 'Expense added: Cash Advance', 15, '2026-10-01 04:23:49'),
(14, 'in', 1000.00, 196300.00, 197300.00, '2026-10-01', 'Expense deleted (reversal): Cash Advance', 15, '2026-10-01 04:24:03'),
(15, 'out', 1000.00, 197300.00, 196300.00, '2026-10-01', 'Expense added: Food / Market for Employee: Jorem Barangan', 15, '2026-10-01 04:24:20'),
(16, 'in', 1000.00, 196300.00, 197300.00, '2026-10-01', 'Expense deleted (reversal): Food / Market for Employee: Jorem Barangan', 15, '2026-10-01 04:24:33'),
(17, 'out', 1000.00, 197300.00, 196300.00, '2026-10-01', 'Expense added: Parcel for CEO: CEO C. Barangan', 15, '2026-10-01 04:24:47'),
(18, 'in', 1000.00, 196300.00, 197300.00, '2026-10-01', 'Expense deleted (reversal): Parcel for CEO: CEO C. Barangan', 15, '2026-10-01 04:24:52'),
(19, 'out', 1000.00, 197300.00, 196300.00, '2026-10-01', 'Expense added: Cash Advance for: dasda wdaw', 15, '2026-10-01 04:25:02'),
(21, 'out', 50.00, 196300.00, 196250.00, '2026-02-27', 'Expense increased by ₱50.00 (Edit): probe 1790821599266 for CEO: CEO C. Barangan', 15, '2026-10-01 05:32:16');

-- --------------------------------------------------------

--
-- Table structure for table `employee`
--

CREATE TABLE `employee` (
  `id` int NOT NULL,
  `employee_id` varchar(50) NOT NULL,
  `firstname` varchar(100) NOT NULL,
  `middlename` varchar(100) DEFAULT NULL,
  `lastname` varchar(100) NOT NULL,
  `suffix` varchar(10) DEFAULT NULL,
  `address` text,
  `contact_number` varchar(20) DEFAULT NULL,
  `birth_date` date DEFAULT NULL,
  `marital_status` enum('Single','Married','Divorced','Widowed') DEFAULT NULL,
  `position` enum('Operator','Driver','Mechanic','Welder','Vulcanizer','Building Electrician','Auto Electrician','Foreman','Skilled','Helper','Labor','Flockman','Cook | Office Helper','Painter','Site Engineer','Liaison Officer','Asistant Project Manager','Chief Mechanic') CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
  `hire_date` date NOT NULL,
  `daily_wage` decimal(10,2) DEFAULT '0.00',
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `employee`
--

INSERT INTO `employee` (`id`, `employee_id`, `firstname`, `middlename`, `lastname`, `suffix`, `address`, `contact_number`, `birth_date`, `marital_status`, `position`, `hire_date`, `daily_wage`, `status`, `created_at`, `updated_at`) VALUES
(1, '0121', 'Juls', 'Barcarse', 'Garcia', '', 'Cagayan', '09560285830', '2000-07-12', 'Single', 'Foreman', '2025-09-07', 650.00, 'active', '2025-09-07 03:19:07', '2026-03-11 07:50:43'),
(2, '0122', 'Randy', 'Cruz', 'Suyu', 'III', 'Cagayan', '09560285830', '2000-01-22', 'Single', 'Welder', '2025-09-07', 550.00, 'active', '2025-09-07 03:35:07', '2025-09-07 06:23:05'),
(3, '0123', 'Tom', 'Rizal', 'Damaso', '', 'Cagayan', '09560285830', '1996-03-21', 'Single', 'Skilled', '2025-09-07', 550.00, 'active', '2025-09-07 03:36:34', '2025-09-07 06:23:11'),
(4, '0124', 'Rodel', 'Telan', 'Cumbali', '', 'Cagayan', '09560285830', '1999-04-01', 'Single', 'Helper', '2025-09-07', 450.00, 'active', '2025-09-07 03:37:27', '2025-09-07 15:55:26'),
(5, '0125', 'Jorem', 'Bangloy', 'Barangan', '', 'Cagayan', '09560285830', '1998-07-22', 'Married', 'Driver', '2025-09-08', 600.00, 'active', '2025-09-08 04:00:27', '2025-10-24 05:46:26'),
(7, '12211', 'Mechanic', 'Mechanic', 'Mechanic', 'III', '13th st. Block 10 lot 8 camella homes larion alto', '09560285830', '2000-02-22', 'Single', 'Mechanic', '2026-02-02', 560.00, 'active', '2026-02-08 12:54:28', '2026-03-04 03:19:59'),
(8, '111', 'Sample', 'Sample', 'Samples', 'Sr.', 'Sample', '09560285830', '2000-07-12', 'Single', 'Auto Electrician', '2026-03-04', 400.00, 'active', '2026-03-04 13:11:37', '2026-10-01 05:55:42'),
(10, '2234', 'asdw', 'asdwd', 'asdwdww', '', 'asdwd', '09560285830', '2000-10-01', 'Single', 'Liaison Officer', '2026-10-01', 501.00, 'active', '2026-10-01 05:56:14', '2026-10-01 05:57:11');

-- --------------------------------------------------------

--
-- Table structure for table `employee_deductions`
--

CREATE TABLE `employee_deductions` (
  `id` int NOT NULL,
  `employee_id` int NOT NULL,
  `deduction_type` enum('cash_advance','sss','pag_ibig','philhealth') NOT NULL,
  `amount` decimal(10,2) NOT NULL DEFAULT '0.00',
  `effectivity_date` date DEFAULT NULL,
  `from_date` date DEFAULT NULL,
  `to_date` date DEFAULT NULL,
  `notes` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `created_by` int DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `employee_deductions`
--

INSERT INTO `employee_deductions` (`id`, `employee_id`, `deduction_type`, `amount`, `effectivity_date`, `from_date`, `to_date`, `notes`, `created_at`, `updated_at`, `created_by`) VALUES
(1, 7, 'cash_advance', 160.00, NULL, '2026-03-02', '2026-03-15', '', '2026-03-02 06:27:46', '2026-03-11 05:39:23', 12),
(2, 7, 'sss', 201.00, '2026-03-04', NULL, NULL, '', '2026-03-02 06:27:46', '2026-03-04 03:16:27', 12),
(3, 7, 'pag_ibig', 150.00, '2026-03-02', NULL, NULL, '', '2026-03-02 06:27:46', '2026-03-02 06:27:46', 12),
(4, 7, 'philhealth', 160.00, '2026-03-02', NULL, NULL, '', '2026-03-02 06:27:46', '2026-03-02 06:27:46', 12),
(5, 10, 'cash_advance', 1.00, NULL, '2026-10-01', '2026-10-30', '', '2026-10-01 05:57:31', '2026-10-01 05:57:31', 15);

-- --------------------------------------------------------

--
-- Table structure for table `employee_deductions_history`
--

CREATE TABLE `employee_deductions_history` (
  `id` int NOT NULL,
  `employee_id` int NOT NULL,
  `deduction_type` enum('cash_advance','sss','pag_ibig','philhealth') NOT NULL,
  `old_amount` decimal(10,2) NOT NULL DEFAULT '0.00',
  `new_amount` decimal(10,2) NOT NULL DEFAULT '0.00',
  `change_amount` decimal(10,2) NOT NULL DEFAULT '0.00',
  `change_percentage` decimal(10,2) NOT NULL DEFAULT '0.00',
  `change_type` enum('increase','decrease','no change') NOT NULL,
  `old_effectivity_date` date DEFAULT NULL,
  `new_effectivity_date` date DEFAULT NULL,
  `old_from_date` date DEFAULT NULL,
  `new_from_date` date DEFAULT NULL,
  `old_to_date` date DEFAULT NULL,
  `new_to_date` date DEFAULT NULL,
  `old_notes` text,
  `new_notes` text,
  `changed_by` int NOT NULL,
  `change_reason` text,
  `changed_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `employee_deductions_history`
--

INSERT INTO `employee_deductions_history` (`id`, `employee_id`, `deduction_type`, `old_amount`, `new_amount`, `change_amount`, `change_percentage`, `change_type`, `old_effectivity_date`, `new_effectivity_date`, `old_from_date`, `new_from_date`, `old_to_date`, `new_to_date`, `old_notes`, `new_notes`, `changed_by`, `change_reason`, `changed_at`) VALUES
(1, 7, 'cash_advance', 0.00, 1000.00, 1000.00, 100.00, 'increase', NULL, NULL, NULL, '2026-03-02', NULL, '2026-03-15', '', '', 12, '', '2026-03-02 06:27:46'),
(2, 7, 'sss', 151.00, 200.00, 49.00, 32.45, 'increase', '2026-03-02', '2026-03-03', NULL, NULL, NULL, NULL, '', '', 12, '', '2026-03-02 06:29:21'),
(3, 7, 'pag_ibig', 0.00, 150.00, 150.00, 100.00, 'increase', NULL, '2026-03-02', NULL, NULL, NULL, NULL, '', '', 12, '', '2026-03-02 06:27:46'),
(4, 7, 'philhealth', 0.00, 160.00, 160.00, 100.00, 'increase', NULL, '2026-03-02', NULL, NULL, NULL, NULL, '', '', 12, '', '2026-03-02 06:27:46'),
(5, 7, 'cash_advance', 160.00, 160.00, 0.00, 0.00, 'no change', NULL, NULL, '2026-03-02', '2026-03-02', '2026-03-15', '2026-03-14', '2', '2', 12, 'asdw', '2026-03-04 12:20:39'),
(6, 7, 'sss', 200.00, 201.00, 1.00, 0.50, 'increase', '2026-03-03', '2026-03-04', NULL, NULL, NULL, NULL, '', '', 12, '', '2026-03-04 03:16:27'),
(7, 7, 'cash_advance', 160.00, 160.00, 0.00, 0.00, 'no change', NULL, NULL, '2026-03-02', '2026-03-02', '2026-03-14', '2026-03-15', '2', '', 12, '', '2026-03-11 05:39:23'),
(8, 10, 'cash_advance', 0.00, 1.00, 1.00, 100.00, 'increase', NULL, NULL, NULL, '2026-10-01', NULL, '2026-10-30', '', '', 15, '', '2026-10-01 05:57:31');

-- --------------------------------------------------------

--
-- Table structure for table `employee_materials_issued`
--

CREATE TABLE `employee_materials_issued` (
  `id` int NOT NULL,
  `employee_id` int NOT NULL,
  `part_id` int NOT NULL,
  `quantity` decimal(10,2) NOT NULL,
  `price_per_unit` decimal(10,2) NOT NULL,
  `total_price` decimal(10,2) NOT NULL,
  `purpose` varchar(255) NOT NULL,
  `date_issued` date NOT NULL,
  `batch_id` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `equipment`
--

CREATE TABLE `equipment` (
  `id` int NOT NULL,
  `equipment_name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `fuel_type` enum('gasoline','diesel','electric') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'gasoline',
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `is_active` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `equipment`
--

INSERT INTO `equipment` (`id`, `equipment_name`, `fuel_type`, `description`, `is_active`, `created_at`, `updated_at`) VALUES
(2, 'Dumtruck', 'diesel', 'Dumtruck', 1, '2025-09-08 04:49:31', '2025-09-23 13:53:21'),
(14, 'VerifyEquip 1790781252638', 'diesel', 'created by the verification run', 1, '2026-09-30 15:14:12', '2026-09-30 15:14:12');

-- --------------------------------------------------------

--
-- Table structure for table `expenses`
--

CREATE TABLE `expenses` (
  `id` int NOT NULL,
  `expense_type_id` int DEFAULT NULL,
  `amount` decimal(10,2) NOT NULL,
  `expense_date` date NOT NULL,
  `description` text,
  `employee_id` int DEFAULT NULL,
  `ceo_id` int DEFAULT NULL,
  `person_name` varchar(255) DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `expenses`
--

INSERT INTO `expenses` (`id`, `expense_type_id`, `amount`, `expense_date`, `description`, `employee_id`, `ceo_id`, `person_name`, `created_by`, `created_at`, `updated_at`) VALUES
(19, 2, 200.00, '2026-02-23', 'Parcel for CEO: CEO C. CEO', NULL, 12, NULL, 8, '2026-02-23 10:03:51', '2026-02-23 10:04:18'),
(20, 3, 500.00, '2026-02-23', 'Cash Advance for Employee: Juls Garcia', 1, NULL, NULL, 8, '2026-02-23 10:27:56', '2026-02-23 10:27:56'),
(21, 4, 1000.00, '2026-02-27', 'Food / Market', NULL, NULL, NULL, 7, '2026-02-27 15:10:33', '2026-02-27 15:10:33'),
(22, 2, 1050.00, '2026-02-27', 'probe 1790821599266 for CEO: CEO C. Barangan', NULL, 12, NULL, 7, '2026-02-27 15:11:47', '2026-10-01 05:32:16');

-- --------------------------------------------------------

--
-- Table structure for table `expenses_type`
--

CREATE TABLE `expenses_type` (
  `id` int NOT NULL,
  `expense_name` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `description` text COLLATE utf8mb4_general_ci,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `expenses_type`
--

INSERT INTO `expenses_type` (`id`, `expense_name`, `description`, `created_at`, `updated_at`) VALUES
(2, 'probe 1790821599266', '', '2026-02-23 04:56:31', '2026-10-01 02:26:39'),
(3, 'Cash Advance', 'Cash Advance', '2026-02-23 04:56:42', NULL),
(4, 'Food / Market', '', '2026-02-23 08:33:43', NULL),
(7, 'Parcel', 'temp', '2026-09-30 14:52:36', '2026-10-01 09:28:32');

-- --------------------------------------------------------

--
-- Table structure for table `gasoline_batches`
--

CREATE TABLE `gasoline_batches` (
  `id` int NOT NULL,
  `gasoline_type` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `tank_id` int DEFAULT NULL,
  `quantity_liters` decimal(10,2) NOT NULL,
  `price_per_liter` decimal(10,2) NOT NULL,
  `date_received` date NOT NULL,
  `purchase_order` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `purchase_request` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `supplier_id` int DEFAULT NULL,
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `transfer_from` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `gasoline_inventory`
--

CREATE TABLE `gasoline_inventory` (
  `id` int NOT NULL,
  `gasoline_type` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `tank_id` int DEFAULT NULL,
  `quantity_liters` decimal(10,2) NOT NULL DEFAULT '0.00',
  `price_per_liter` decimal(10,2) NOT NULL DEFAULT '0.00',
  `last_updated` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `gasoline_min_levels`
--

CREATE TABLE `gasoline_min_levels` (
  `id` int NOT NULL,
  `tank_id` int NOT NULL,
  `gasoline_type` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `min_stock_liters` decimal(10,2) NOT NULL DEFAULT '0.00',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `gasoline_movements`
--

CREATE TABLE `gasoline_movements` (
  `id` int NOT NULL,
  `gasoline_type` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `movement_type` enum('in','out') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `tank_id` int DEFAULT NULL,
  `quantity_liters` decimal(10,2) NOT NULL,
  `price_per_liter` decimal(10,2) DEFAULT '0.00',
  `movement_date` date NOT NULL,
  `supplier_id` int DEFAULT NULL,
  `purchase_order` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `po_id` int DEFAULT NULL,
  `purchase_request` varchar(255) DEFAULT NULL,
  `vehicle_id` int DEFAULT NULL,
  `equipment_id` int DEFAULT NULL,
  `driver_operator_id` int DEFAULT NULL,
  `manual_driver_name` varchar(255) DEFAULT NULL,
  `driver_operator` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `purpose` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `odometer_reading` int DEFAULT NULL,
  `batch_id` int DEFAULT NULL,
  `transfer_from` int DEFAULT NULL,
  `transfer_to` int DEFAULT NULL,
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `gasoline_movements`
--

INSERT INTO `gasoline_movements` (`id`, `gasoline_type`, `movement_type`, `tank_id`, `quantity_liters`, `price_per_liter`, `movement_date`, `supplier_id`, `purchase_order`, `po_id`, `purchase_request`, `vehicle_id`, `equipment_id`, `driver_operator_id`, `manual_driver_name`, `driver_operator`, `purpose`, `odometer_reading`, `batch_id`, `transfer_from`, `transfer_to`, `notes`, `created_at`, `updated_at`) VALUES
(90, 'Unleaded', 'in', NULL, 1.00, 50.00, '2026-02-20', 4, 'GPO-000001', 34, NULL, 2, NULL, 5, NULL, NULL, 'wdasd', NULL, NULL, NULL, NULL, NULL, '2026-02-20 12:22:08', '2026-02-20 12:22:08'),
(91, 'Premium', 'in', NULL, 1.00, 50.00, '2026-02-21', 4, 'GPO-000002', 36, NULL, 2, NULL, 5, NULL, NULL, 'asdw', NULL, NULL, NULL, NULL, NULL, '2026-02-21 08:46:20', '2026-02-21 08:46:20'),
(92, 'Premium', 'in', NULL, 1.00, 49.00, '2026-02-21', 5, 'GPO-000004', 38, NULL, 2, NULL, 5, NULL, NULL, 'asdw', NULL, NULL, NULL, NULL, NULL, '2026-02-21 09:20:41', '2026-02-21 09:20:41'),
(93, 'Diesel', 'in', NULL, 1.00, 56.00, '2026-02-21', 5, 'GPO-000003', 37, NULL, NULL, 2, 5, NULL, NULL, 'asdw', NULL, NULL, NULL, NULL, NULL, '2026-02-21 09:20:57', '2026-02-21 09:20:57'),
(94, 'Unleaded', 'in', NULL, 1.00, 50.00, '2026-02-21', 4, 'GPO-000005', 39, NULL, 2, NULL, 5, NULL, NULL, 'daskde', NULL, NULL, NULL, NULL, NULL, '2026-02-21 09:36:26', '2026-02-21 09:36:26'),
(95, 'Diesel', 'in', NULL, 1.00, 54.00, '2026-02-21', 5, 'GPO-000006', 40, NULL, NULL, 2, 5, NULL, NULL, 'asdw', NULL, NULL, NULL, NULL, NULL, '2026-02-21 09:40:15', '2026-02-21 09:40:15'),
(96, 'Diesel', 'in', NULL, 1.00, NULL, '2026-02-22', 5, 'GPO-000007', 41, NULL, NULL, 2, 5, NULL, NULL, 'adw', NULL, NULL, NULL, NULL, NULL, '2026-02-22 04:15:41', '2026-02-22 04:15:41'),
(97, 'Premium', 'in', NULL, 1.00, 22.00, '2026-02-22', 5, 'GPO-000008', 42, NULL, 2, NULL, 5, NULL, NULL, 'asdw', NULL, NULL, NULL, NULL, NULL, '2026-02-22 04:32:00', '2026-02-22 04:32:00'),
(98, 'Unleaded', 'in', NULL, 1.00, 50.00, '2026-02-26', 4, 'GPO-000010', 44, NULL, 2, 2, 5, NULL, NULL, 'asdw', NULL, NULL, NULL, NULL, NULL, '2026-02-26 02:50:00', '2026-02-26 02:50:00'),
(99, 'Premium', 'in', NULL, 1.00, 49.00, '2026-02-26', 4, 'GPO-000010', 44, NULL, NULL, 2, 5, NULL, NULL, 'asdw', NULL, NULL, NULL, NULL, NULL, '2026-02-26 02:50:00', '2026-02-26 02:50:00'),
(100, 'Diesel', 'in', NULL, 1.00, 48.00, '2026-02-26', 5, 'GPO-000010', 44, NULL, NULL, NULL, 5, NULL, NULL, 'asdw', NULL, NULL, NULL, NULL, NULL, '2026-02-26 02:50:00', '2026-02-26 02:50:00'),
(102, 'Unleaded', 'in', NULL, 1.00, 50.00, '2026-02-26', 4, 'GPO-000009', 43, NULL, 2, 2, 5, NULL, NULL, 'asdw', NULL, NULL, NULL, NULL, NULL, '2026-04-15 07:28:47', '2026-04-15 07:28:47'),
(103, 'Premium', 'in', NULL, 1.00, 51.00, '2026-02-26', 5, 'GPO-000009', 43, NULL, NULL, NULL, 5, NULL, NULL, 'asdw', NULL, NULL, NULL, NULL, NULL, '2026-04-15 07:28:47', '2026-04-15 07:28:47'),
(110, 'Unleaded', 'in', NULL, 1.00, 22.00, '2026-04-30', 4, '000006', 53, NULL, 2, NULL, 5, NULL, 'Jorem B. Barangan', 'asd', NULL, NULL, NULL, NULL, NULL, '2026-04-30 01:36:09', '2026-04-30 01:36:09'),
(111, 'Premium', 'in', NULL, 10.00, 100.00, '2026-04-29', 4, '000005', 52, NULL, 2, NULL, NULL, 'Rio Salatan', NULL, 'asd', NULL, NULL, NULL, NULL, NULL, '2026-04-30 01:36:59', '2026-04-30 01:36:59'),
(112, 'Diesel', 'in', NULL, 1.00, 22.00, '2026-04-30', 4, '000007', 54, NULL, NULL, 2, 5, NULL, 'Jorem B. Barangan', 'asd', NULL, NULL, NULL, NULL, NULL, '2026-04-30 01:43:32', '2026-04-30 01:43:32'),
(113, 'Premium', 'in', NULL, 2.00, 22.00, '2026-04-30', 4, '000008', 55, NULL, NULL, 2, NULL, 'Bryan Tumaliuan', NULL, 'asd', NULL, NULL, NULL, NULL, NULL, '2026-04-30 01:44:03', '2026-04-30 01:44:03'),
(116, 'Unleaded', 'in', NULL, 1.00, 22.00, '2026-04-29', 4, '000004', 51, NULL, 2, NULL, 5, NULL, 'Jorem B. Barangan', '22', NULL, NULL, NULL, NULL, NULL, '2026-05-14 07:26:11', '2026-05-14 07:26:11'),
(117, 'Premium', 'in', NULL, 1.00, 45.00, '2026-02-26', 4, '000001', 45, NULL, 2, NULL, NULL, 'asdw', NULL, 'asdw', NULL, NULL, NULL, NULL, NULL, '2026-05-14 07:32:25', '2026-05-14 07:32:25'),
(119, 'Unleaded', 'in', NULL, 12.00, 22.00, '2026-04-28', 4, '000002', 49, NULL, 2, NULL, 5, NULL, 'Jorem B. Barangan', 'asdw', NULL, NULL, NULL, NULL, NULL, '2026-05-14 09:16:18', '2026-05-14 09:16:18'),
(120, 'Unleaded', 'in', NULL, 2.00, 22.00, '2026-04-28', 4, '000003', 50, NULL, 2, NULL, NULL, 'test t. testsss1', NULL, 'asdwd', NULL, NULL, NULL, NULL, NULL, '2026-05-14 09:20:08', '2026-05-14 09:20:08'),
(121, 'Unleaded', 'in', NULL, 15.00, NULL, '2026-09-30', 4, '000009', 56, NULL, 2, NULL, 5, NULL, 'Jorem B. Barangan', 'dasd', NULL, NULL, NULL, NULL, NULL, '2026-09-30 12:11:36', '2026-09-30 12:11:36'),
(144, 'Diesel', 'in', NULL, 1.00, 80.00, '2026-10-01', 4, '000010', 57, NULL, 44, NULL, 5, NULL, 'Jorem B. Barangan', 'asdw', NULL, NULL, NULL, NULL, NULL, '2026-10-01 09:21:17', '2026-10-01 09:21:17'),
(145, 'Premium', 'in', NULL, 15.00, 80.00, '2026-10-01', 4, '000011', 58, NULL, 2, NULL, NULL, 'Manual Driver', NULL, 'adwww', NULL, NULL, NULL, NULL, NULL, '2026-10-01 09:24:11', '2026-10-01 09:24:11'),
(146, 'Premium', 'in', NULL, 15.00, 80.00, '2026-10-01', 4, 'VERIFY-1790847012', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'probe', NULL, NULL, NULL, NULL, NULL, '2026-10-01 09:30:12', '2026-10-01 09:30:12'),
(148, 'Unleaded', 'in', NULL, 1.00, 212.00, '2026-10-01', 5, '000012', 67, NULL, NULL, 2, 5, NULL, 'Jorem B. Barangan', 'asdwd', NULL, NULL, NULL, NULL, NULL, '2026-10-01 09:42:24', '2026-10-01 09:42:24');

-- --------------------------------------------------------

--
-- Table structure for table `gasoline_po_items`
--

CREATE TABLE `gasoline_po_items` (
  `id` int NOT NULL,
  `po_id` int NOT NULL,
  `gasoline_type` varchar(50) NOT NULL,
  `supplier_id` int NOT NULL,
  `vehicle_id` int DEFAULT NULL,
  `equipment_id` int DEFAULT NULL,
  `driver_operator_id` int DEFAULT NULL,
  `manual_driver_name` varchar(255) DEFAULT NULL,
  `purpose` varchar(255) NOT NULL,
  `quantity_liters` decimal(10,2) NOT NULL,
  `price_per_liter` decimal(10,2) DEFAULT '0.00',
  `odometer_reading` int DEFAULT NULL,
  `date_issued` date NOT NULL,
  `issued` tinyint(1) DEFAULT '0',
  `issue_date` date DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `gasoline_po_items`
--

INSERT INTO `gasoline_po_items` (`id`, `po_id`, `gasoline_type`, `supplier_id`, `vehicle_id`, `equipment_id`, `driver_operator_id`, `manual_driver_name`, `purpose`, `quantity_liters`, `price_per_liter`, `odometer_reading`, `date_issued`, `issued`, `issue_date`, `created_at`) VALUES
(49, 34, 'Unleaded', 4, 2, NULL, 5, NULL, 'wdasd', 1.00, 50.00, NULL, '2026-02-20', 0, NULL, '2026-02-20 12:15:00'),
(51, 36, 'Premium', 4, 2, NULL, 5, NULL, 'asdw', 1.00, 50.00, NULL, '2026-02-21', 0, NULL, '2026-02-21 08:46:01'),
(52, 37, 'Diesel', 5, NULL, 2, 5, NULL, 'asdw', 1.00, 56.00, NULL, '2026-02-21', 0, NULL, '2026-02-21 08:52:16'),
(53, 38, 'Premium', 5, 2, NULL, 5, NULL, 'asdw', 1.00, 49.00, NULL, '2026-02-21', 0, NULL, '2026-02-21 09:20:28'),
(54, 39, 'Unleaded', 4, 2, NULL, 5, NULL, 'daskde', 1.00, 50.00, NULL, '2026-02-21', 0, NULL, '2026-02-21 09:36:12'),
(55, 40, 'Diesel', 5, NULL, 2, 5, NULL, 'asdw', 1.00, 54.00, NULL, '2026-02-21', 0, NULL, '2026-02-21 09:40:11'),
(56, 41, 'Diesel', 5, NULL, 2, 5, NULL, 'adw', 1.00, NULL, NULL, '2026-02-22', 0, NULL, '2026-02-22 04:15:16'),
(57, 42, 'Premium', 5, 2, NULL, 5, NULL, 'asdw', 1.00, 22.00, NULL, '2026-02-22', 0, NULL, '2026-02-22 04:31:56'),
(58, 43, 'Unleaded', 4, 2, 2, 5, NULL, 'asdw', 1.00, 50.00, NULL, '2026-02-26', 0, NULL, '2026-02-26 02:18:48'),
(59, 43, 'Premium', 5, NULL, NULL, 5, NULL, 'asdw', 1.00, 51.00, NULL, '2026-02-26', 0, NULL, '2026-02-26 02:18:48'),
(60, 44, 'Unleaded', 4, 2, 2, 5, NULL, 'asdw', 1.00, 50.00, NULL, '2026-02-26', 0, NULL, '2026-02-26 02:49:29'),
(61, 44, 'Premium', 4, NULL, 2, 5, NULL, 'asdw', 1.00, 49.00, NULL, '2026-02-26', 0, NULL, '2026-02-26 02:49:29'),
(62, 44, 'Diesel', 5, NULL, NULL, 5, NULL, 'asdw', 1.00, 48.00, NULL, '2026-02-26', 0, NULL, '2026-02-26 02:49:29'),
(70, 50, 'Unleaded', 4, 2, NULL, NULL, 'test t. testsss1', 'asdwd', 2.00, 22.00, NULL, '2026-04-28', 0, NULL, '2026-04-29 04:40:05'),
(71, 51, 'Unleaded', 4, 2, NULL, 5, NULL, '22', 1.00, 22.00, NULL, '2026-04-29', 0, NULL, '2026-04-29 06:54:03'),
(78, 53, 'Unleaded', 4, 2, NULL, 5, NULL, 'asd', 1.00, 22.00, NULL, '2026-04-30', 0, NULL, '2026-04-30 01:36:05'),
(79, 52, 'Premium', 4, 2, NULL, NULL, 'Rio Salatan', 'asd', 10.00, 100.00, NULL, '2026-04-29', 0, NULL, '2026-04-30 01:36:55'),
(80, 54, 'Diesel', 4, NULL, 2, 5, NULL, 'asd', 1.00, 22.00, NULL, '2026-04-30', 0, NULL, '2026-04-30 01:43:28'),
(81, 55, 'Premium', 4, NULL, 2, NULL, 'Bryan Tumaliuan', 'asd', 2.00, 22.00, NULL, '2026-04-30', 0, NULL, '2026-04-30 01:43:59'),
(82, 45, 'Premium', 4, 2, NULL, NULL, 'asdw', 'asdw', 1.00, 45.00, NULL, '2026-02-26', 0, NULL, '2026-05-14 06:43:07'),
(83, 49, 'Unleaded', 4, 2, NULL, 5, NULL, 'asdw', 12.00, 22.00, NULL, '2026-04-28', 0, NULL, '2026-05-14 09:15:55'),
(84, 56, 'Unleaded', 4, 2, NULL, 5, NULL, 'dasd', 15.00, NULL, NULL, '2026-09-30', 0, NULL, '2026-09-30 12:11:06'),
(87, 57, 'Diesel', 4, 44, NULL, 5, NULL, 'asdw', 1.00, 80.00, NULL, '2026-10-01', 0, NULL, '2026-10-01 09:20:55'),
(88, 58, 'Premium', 4, 2, NULL, NULL, 'Manual Driver', 'adwww', 15.00, 80.00, NULL, '2026-10-01', 0, NULL, '2026-10-01 09:22:15'),
(95, 67, 'Unleaded', 5, NULL, 2, 5, NULL, 'asdwd', 1.00, 212.00, NULL, '2026-10-01', 0, NULL, '2026-10-01 09:42:11');

-- --------------------------------------------------------

--
-- Table structure for table `gasoline_purchase_orders`
--

CREATE TABLE `gasoline_purchase_orders` (
  `id` int NOT NULL,
  `po_number` varchar(50) NOT NULL,
  `supplier_id` int DEFAULT NULL,
  `po_date` date NOT NULL,
  `delivery_date` date DEFAULT NULL,
  `status` enum('pending','approved','completed','cancelled') CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT 'pending',
  `invoice_number` varchar(100) DEFAULT NULL,
  `completed_date` datetime DEFAULT NULL,
  `prepared_by` int NOT NULL,
  `approved_by` int DEFAULT NULL,
  `approval_signature` text,
  `completion_signature` longtext,
  `approval_date` datetime DEFAULT NULL,
  `total_amount` decimal(12,2) DEFAULT '0.00',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `gasoline_purchase_orders`
--

INSERT INTO `gasoline_purchase_orders` (`id`, `po_number`, `supplier_id`, `po_date`, `delivery_date`, `status`, `invoice_number`, `completed_date`, `prepared_by`, `approved_by`, `approval_signature`, `completion_signature`, `approval_date`, `total_amount`, `created_at`, `updated_at`) VALUES
(34, 'GPO-000001', 4, '2026-02-20', NULL, 'completed', '123456', '2026-02-20 21:00:14', 10, 12, NULL, NULL, NULL, 50.00, '2026-02-20 12:15:00', '2026-02-21 09:21:28'),
(36, 'GPO-000002', 4, '2026-02-21', NULL, 'completed', '1234', '2026-02-21 17:16:01', 7, 12, NULL, NULL, NULL, 50.00, '2026-02-21 08:46:01', '2026-02-21 09:18:47'),
(37, 'GPO-000003', 5, '2026-02-21', NULL, 'completed', '', '2026-02-21 17:34:25', 7, 12, NULL, NULL, NULL, 56.00, '2026-02-21 08:52:16', '2026-02-21 09:34:25'),
(38, 'GPO-000004', 5, '2026-02-21', NULL, 'completed', '', '2026-02-21 17:33:18', 7, 12, NULL, NULL, NULL, 49.00, '2026-02-21 09:20:28', '2026-02-21 09:33:18'),
(39, 'GPO-000005', 4, '2026-02-21', NULL, 'completed', '', '2026-02-21 17:39:20', 7, 12, NULL, NULL, NULL, 50.00, '2026-02-21 09:36:12', '2026-02-21 09:39:20'),
(40, 'GPO-000006', 5, '2026-02-21', NULL, 'completed', '', '2026-02-21 17:42:07', 12, 12, NULL, NULL, NULL, 54.00, '2026-02-21 09:40:11', '2026-02-21 09:42:07'),
(41, 'GPO-000007', 5, '2026-02-22', NULL, 'completed', '5789', '2026-02-22 12:15:52', 10, 12, NULL, NULL, NULL, 0.00, '2026-02-22 04:15:16', '2026-02-22 04:17:08'),
(42, 'GPO-000008', 5, '2026-02-22', NULL, 'completed', '12121', '2026-02-22 12:32:41', 12, 12, NULL, NULL, NULL, 22.00, '2026-02-22 04:31:56', '2026-02-22 04:32:41'),
(43, 'GPO-000009', 4, '2026-02-26', NULL, 'approved', NULL, NULL, 12, 12, NULL, NULL, NULL, 101.00, '2026-02-26 02:18:48', '2026-04-15 07:28:47'),
(44, 'GPO-000010', 4, '2026-02-26', NULL, 'completed', '22212', '2026-02-26 10:55:21', 12, 12, NULL, NULL, NULL, 147.00, '2026-02-26 02:49:29', '2026-02-26 02:55:21'),
(45, '000001', 4, '2026-02-26', NULL, 'approved', NULL, NULL, 12, 12, 'data:image/png;base64,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', NULL, '2026-05-14 15:32:25', 45.00, '2026-02-26 03:11:23', '2026-05-14 07:32:25'),
(49, '000002', 4, '2026-04-28', NULL, 'completed', '2255', '2026-05-14 17:51:31', 12, 12, 'data:image/png;base64,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', 'data:image/png;base64,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', '2026-05-14 17:16:18', 264.00, '2026-04-28 08:02:32', '2026-05-14 10:03:11');
INSERT INTO `gasoline_purchase_orders` (`id`, `po_number`, `supplier_id`, `po_date`, `delivery_date`, `status`, `invoice_number`, `completed_date`, `prepared_by`, `approved_by`, `approval_signature`, `completion_signature`, `approval_date`, `total_amount`, `created_at`, `updated_at`) VALUES
(50, '000003', 4, '2026-04-28', NULL, 'approved', NULL, NULL, 12, 12, 'data:image/png;base64,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', NULL, '2026-05-14 17:20:08', 44.00, '2026-04-28 08:27:20', '2026-05-14 09:20:08'),
(51, '000004', 4, '2026-04-29', NULL, 'approved', NULL, NULL, 12, 12, 'data:image/png;base64,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', NULL, '2026-05-14 15:26:11', 22.00, '2026-04-29 03:37:28', '2026-05-14 07:26:11'),
(52, '000005', 4, '2026-04-29', NULL, 'approved', NULL, NULL, 12, 12, NULL, NULL, NULL, 1000.00, '2026-04-29 06:55:15', '2026-04-30 01:36:59'),
(53, '000006', 4, '2026-04-30', NULL, 'approved', NULL, NULL, 12, 12, NULL, NULL, NULL, 22.00, '2026-04-30 00:55:13', '2026-04-30 01:36:09'),
(54, '000007', 4, '2026-04-30', NULL, 'approved', NULL, NULL, 12, 12, NULL, NULL, NULL, 22.00, '2026-04-30 01:43:28', '2026-04-30 01:43:32'),
(55, '000008', 4, '2026-04-30', NULL, 'approved', NULL, NULL, 12, 12, NULL, NULL, NULL, 44.00, '2026-04-30 01:43:59', '2026-04-30 01:44:03');
INSERT INTO `gasoline_purchase_orders` (`id`, `po_number`, `supplier_id`, `po_date`, `delivery_date`, `status`, `invoice_number`, `completed_date`, `prepared_by`, `approved_by`, `approval_signature`, `completion_signature`, `approval_date`, `total_amount`, `created_at`, `updated_at`) VALUES
(56, '000009', 4, '2026-09-30', NULL, 'completed', '123456', '2026-09-30 20:13:55', 15, 15, 'data:image/png;base64,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', 'data:image/png;base64,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', '2026-09-30 20:11:36', 0.00, '2026-09-30 12:11:06', '2026-09-30 12:13:55'),
(57, '000010', 4, '2026-10-01', NULL, 'approved', NULL, NULL, 15, 15, 'data:image/png;base64,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', NULL, '2026-10-01 17:21:17', 80.00, '2026-10-01 05:25:55', '2026-10-01 09:21:17');
INSERT INTO `gasoline_purchase_orders` (`id`, `po_number`, `supplier_id`, `po_date`, `delivery_date`, `status`, `invoice_number`, `completed_date`, `prepared_by`, `approved_by`, `approval_signature`, `completion_signature`, `approval_date`, `total_amount`, `created_at`, `updated_at`) VALUES
(58, '000011', 4, '2026-10-01', NULL, 'approved', NULL, NULL, 15, 15, 'data:image/png;base64,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', NULL, '2026-10-01 17:24:11', 1200.00, '2026-10-01 09:22:15', '2026-10-01 09:24:11'),
(67, '000012', 5, '2026-10-01', NULL, 'completed', '21211', '2026-10-01 17:43:21', 15, 15, 'data:image/png;base64,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', 'data:image/png;base64,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', '2026-10-01 17:42:24', 212.00, '2026-10-01 09:42:11', '2026-10-01 09:43:39');

-- --------------------------------------------------------

--
-- Table structure for table `gasoline_suppliers`
--

CREATE TABLE `gasoline_suppliers` (
  `id` int NOT NULL,
  `supplier_name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `supplier_type` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `contact_person` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `phone` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `address` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `is_active` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `gasoline_suppliers`
--

INSERT INTO `gasoline_suppliers` (`id`, `supplier_name`, `supplier_type`, `contact_person`, `phone`, `email`, `address`, `is_active`, `created_at`, `updated_at`) VALUES
(4, 'Gasoline Supplier 1', 'Fuel', 'Jorem', '09560285830', 'test@gmail.com', 'Cagayan', 1, '2025-09-08 04:48:44', '2025-09-08 04:48:44'),
(5, 'Supplier 1s2', 'Fuel', 'boy', '09560285830', 'test@gmail.com', '', 1, '2025-09-18 03:36:34', '2026-10-01 05:01:38');

-- --------------------------------------------------------

--
-- Table structure for table `gasoline_tanks`
--

CREATE TABLE `gasoline_tanks` (
  `id` int NOT NULL,
  `tank_name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `location` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `capacity_liters` decimal(10,2) NOT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `is_active` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `inventory`
--

CREATE TABLE `inventory` (
  `id` int NOT NULL,
  `item_id` int NOT NULL,
  `warehouse_id` int NOT NULL,
  `quantity` int NOT NULL DEFAULT '0',
  `unit_cost` decimal(10,2) DEFAULT '0.00',
  `last_updated` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `total_value` decimal(15,2) DEFAULT '0.00'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `inventory`
--

INSERT INTO `inventory` (`id`, `item_id`, `warehouse_id`, `quantity`, `unit_cost`, `last_updated`, `total_value`) VALUES
(67, 11, 5, 3, 45.33, '2026-10-01 14:07:49', 136.00),
(68, 13, 5, 0, 0.00, '2026-10-01 14:07:49', 0.00),
(69, 12, 5, 1, 150.00, '2026-10-01 14:07:49', 150.00),
(70, 16, 5, 2, 137.50, '2026-10-01 14:07:49', 275.00),
(71, 56, 5, 15, 8.33, '2026-10-01 14:07:49', 125.00);

-- --------------------------------------------------------

--
-- Table structure for table `inventory_batches`
--

CREATE TABLE `inventory_batches` (
  `id` int NOT NULL,
  `item_id` int NOT NULL,
  `warehouse_id` int NOT NULL,
  `batch_number` varchar(50) NOT NULL,
  `purchase_order` varchar(255) DEFAULT NULL,
  `purchase_request` varchar(255) DEFAULT NULL,
  `quantity` decimal(10,2) NOT NULL DEFAULT '0.00',
  `unit_cost` decimal(10,2) NOT NULL DEFAULT '0.00',
  `supplier_id` int DEFAULT NULL,
  `received_date` date NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `inventory_batches`
--

INSERT INTO `inventory_batches` (`id`, `item_id`, `warehouse_id`, `batch_number`, `purchase_order`, `purchase_request`, `quantity`, `unit_cost`, `supplier_id`, `received_date`, `created_at`, `updated_at`) VALUES
(155, 11, 5, 'INITIAL-20260216092646', NULL, NULL, 0.00, 150.00, NULL, '2026-02-16', '2026-02-16 09:26:46', '2026-02-18 15:40:33'),
(156, 11, 5, 'BATCH-20260217-135130-75', 'PO-2026-0002', NULL, 0.00, 11.00, 9, '2026-02-17', '2026-02-17 13:54:01', '2026-02-18 15:40:33'),
(157, 11, 5, 'BATCH-20260218-032018-78', 'PO-2026-0005', NULL, 0.00, 111.00, 5, '2026-02-18', '2026-02-18 03:20:26', '2026-02-19 01:28:20'),
(158, 11, 5, 'BATCH-20260218-034201-79', 'PO-2026-0006', NULL, 0.00, 12.00, 10, '2026-02-18', '2026-02-18 03:42:14', '2026-02-19 07:27:59'),
(159, 11, 5, 'BATCH-20260218-034558-80', 'PO-2026-0007', NULL, 0.00, 40.00, 9, '2026-02-18', '2026-02-18 03:46:01', '2026-02-19 07:33:38'),
(160, 13, 5, 'BATCH-20260218-053627-81', 'PO-2026-0008', NULL, 0.00, 30.00, 10, '2026-02-18', '2026-02-18 05:36:40', '2026-02-18 12:52:27'),
(161, 11, 5, 'BATCH-20260218-055016-82', 'PO-2026-0009', NULL, 0.00, 50.00, 9, '2026-02-18', '2026-02-18 05:50:38', '2026-02-19 09:30:52'),
(162, 11, 5, 'BATCH-20260218-055828-83', 'PO-2026-0010', NULL, 0.00, 25.00, 10, '2026-02-18', '2026-02-18 05:58:33', '2026-02-19 10:15:58'),
(163, 11, 5, 'BATCH-20260218-060440-84', 'PO-2026-0011', NULL, 0.00, 10.00, 10, '2026-02-18', '2026-02-18 06:04:52', '2026-04-15 07:24:57'),
(164, 11, 5, 'BATCH-20260218-062829-85', 'PO-2026-0012', NULL, 1.00, 15.00, 9, '2026-02-18', '2026-02-18 06:31:04', '2026-02-18 06:31:04'),
(165, 13, 5, 'INITIAL-20260218082245', NULL, NULL, 0.00, 40.00, NULL, '2026-02-18', '2026-02-18 08:22:45', '2026-02-18 12:52:27'),
(166, 12, 5, 'BATCH-20260218-090942-90', 'PO-2026-0017', NULL, 0.00, 152.00, 4, '2026-02-18', '2026-02-18 09:09:57', '2026-02-18 09:45:53'),
(167, 12, 5, 'BATCH-20260218-091121-89', 'PO-2026-0016', NULL, 0.00, 151.00, 9, '2026-02-18', '2026-02-18 09:12:48', '2026-02-18 09:45:53'),
(168, 12, 5, 'BATCH-20260218-095005-91', 'PO-2026-0018', NULL, 0.00, 99.00, 9, '2026-02-18', '2026-02-18 09:50:12', '2026-02-18 10:42:54'),
(169, 12, 5, 'BATCH-20260218-104510-92', 'PO-2026-0019', NULL, 0.00, 95.00, 9, '2026-02-17', '2026-02-18 10:45:22', '2026-02-18 10:48:28'),
(170, 12, 5, 'BATCH-20260218-104753-93', 'PO-2026-0020', NULL, 0.00, 51.00, 9, '2026-02-16', '2026-02-18 10:48:00', '2026-02-18 10:48:28'),
(171, 12, 5, 'BATCH-20260218-112201-94', 'PO-2026-0021', NULL, 0.00, 15.00, 10, '2026-02-18', '2026-02-18 11:22:05', '2026-02-18 12:33:42'),
(172, 12, 5, 'BATCH-20260218-123840-95', 'PO-2026-0022', NULL, 0.00, 16.00, 9, '2026-02-18', '2026-02-18 12:38:51', '2026-02-18 12:39:29'),
(173, 12, 5, 'INITIAL-20260218130226', NULL, NULL, 0.00, 10.00, NULL, '2026-02-18', '2026-02-18 13:02:26', '2026-02-18 13:05:48'),
(174, 12, 5, 'BATCH-20260218-131555-98', 'PO-2026-0024', NULL, 0.00, 10.00, 10, '2026-02-18', '2026-02-18 13:16:03', '2026-02-18 13:22:14'),
(175, 13, 5, 'BATCH-20260218-131555-99', 'PO-2026-0024', NULL, 0.00, 10.00, 10, '2026-02-18', '2026-02-18 13:16:03', '2026-02-18 13:20:10'),
(176, 12, 5, 'BATCH-20260218-132335-100', 'PO-2026-0025', NULL, 0.00, 10.00, 9, '2026-02-18', '2026-02-18 13:23:39', '2026-02-18 13:34:48'),
(177, 13, 5, 'BATCH-20260218-132335-101', 'PO-2026-0025', NULL, 0.00, 11.00, 9, '2026-02-18', '2026-02-18 13:23:39', '2026-02-18 13:31:05'),
(179, 12, 5, 'BATCH-20260218-133907-102', 'PO-2026-0026', NULL, 0.00, 10.00, 9, '2026-02-18', '2026-02-18 13:39:13', '2026-02-19 09:30:52'),
(180, 13, 5, 'BATCH-20260218-133907-103', 'PO-2026-0026', NULL, 0.00, 20.00, 9, '2026-02-18', '2026-02-18 13:39:13', '2026-02-18 14:32:33'),
(181, 13, 5, 'BATCH-20260219-074147-107', 'PO-2026-0030', NULL, 0.00, 140.00, 10, '2026-02-19', '2026-02-19 07:42:47', '2026-02-19 08:23:46'),
(182, 11, 5, 'BATCH-20260219-080731-110', 'PO-2026-0033', NULL, 1.00, 120.00, 10, '2026-02-19', '2026-02-19 08:07:34', '2026-02-19 08:07:34'),
(183, 12, 5, 'BATCH-20260219-093147-111', 'PO-2026-0034', NULL, 1.00, 150.00, 9, '2026-02-19', '2026-02-19 09:31:51', '2026-04-15 07:24:57'),
(184, 13, 5, 'BATCH-20260219-093147-112', 'PO-2026-0034', NULL, 0.00, 180.00, 9, '2026-02-19', '2026-02-19 09:31:51', '2026-02-19 09:46:53'),
(185, 13, 5, 'BATCH-20260219-100334-114', 'PO-2026-0036', NULL, 0.00, 144.00, 9, '2026-02-19', '2026-02-19 10:03:40', '2026-02-19 10:11:20'),
(186, 13, 5, 'BATCH-20260219-101300-115', 'PO-2026-0037', NULL, 0.00, 121.00, 9, '2026-02-19', '2026-02-19 10:13:21', '2026-02-20 11:21:58'),
(187, 11, 5, 'INITIAL-20260430033118', NULL, NULL, 1.00, 1.00, NULL, '2026-04-30', '2026-04-30 03:31:18', '2026-04-30 03:31:18'),
(188, 16, 5, 'INITIAL-20261001042540', NULL, NULL, 1.00, 25.00, NULL, '2026-10-01', '2026-10-01 04:25:40', '2026-10-01 05:32:59'),
(189, 16, 5, 'INITIAL-20261001053313', NULL, NULL, 1.00, 250.00, NULL, '2026-10-01', '2026-10-01 05:33:13', '2026-10-01 05:33:13'),
(191, 56, 5, 'INITIAL-20261001100805', NULL, NULL, 10.00, 10.00, NULL, '2026-10-01', '2026-10-01 10:08:05', '2026-10-01 10:08:05'),
(192, 56, 5, 'INITIAL-20261001100822', NULL, NULL, 5.00, 5.00, NULL, '2026-10-01', '2026-10-01 10:08:22', '2026-10-01 10:08:22');

-- --------------------------------------------------------

--
-- Table structure for table `items_categories`
--

CREATE TABLE `items_categories` (
  `id` int NOT NULL,
  `category_name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `status` enum('active','inactive') COLLATE utf8mb4_unicode_ci DEFAULT 'active',
  `created_by` int DEFAULT NULL,
  `updated_by` int DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `items_categories`
--

INSERT INTO `items_categories` (`id`, `category_name`, `description`, `created_at`, `updated_at`, `status`, `created_by`, `updated_by`) VALUES
(1, 'Battery1', 'Battery', '2026-02-20 11:59:55', '2026-02-20 12:00:14', 'active', NULL, NULL),
(31, 'Test1', 'asdss', '2026-10-01 00:54:42', '2026-10-01 04:03:06', 'active', NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `item_names`
--

CREATE TABLE `item_names` (
  `id` int NOT NULL,
  `item_code` varchar(50) NOT NULL,
  `item_name` varchar(255) NOT NULL,
  `min_stock_level` int NOT NULL DEFAULT '0',
  `category_id` int DEFAULT NULL,
  `unit_of_measure` varchar(50) NOT NULL DEFAULT 'pcs',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;

--
-- Dumping data for table `item_names`
--

INSERT INTO `item_names` (`id`, `item_code`, `item_name`, `min_stock_level`, `category_id`, `unit_of_measure`, `created_at`, `updated_at`) VALUES
(11, '001', 'Bakal 2mm', 0, 1, 'pcs', '2025-09-23 09:28:24', '2026-10-01 02:28:31'),
(12, '002', 'Bakal 3mm', 10, 1, 'pcs', '2025-09-26 20:20:19', '2026-02-20 12:02:50'),
(13, '003', 'Bakal 4mm', 5, 1, 'pcs', '2026-02-17 06:07:06', '2026-02-20 12:02:45'),
(16, '0001', 'ATF TRANSANMISSION FLUID 1 LITER', 5, 1, 'liter', '2026-03-27 03:13:03', '2026-03-27 03:13:03'),
(56, '0012', 'test', 0, 1, 'sack', '2026-10-01 02:20:11', '2026-10-01 04:02:02');

-- --------------------------------------------------------

--
-- Table structure for table `materials`
--

CREATE TABLE `materials` (
  `id` int NOT NULL,
  `project_id` int NOT NULL,
  `material_name` varchar(255) NOT NULL,
  `quantity` decimal(10,2) NOT NULL,
  `unit_price` decimal(10,2) NOT NULL,
  `total_value` decimal(12,2) NOT NULL,
  `stock_in_date` date NOT NULL,
  `supplier` varchar(255) DEFAULT NULL,
  `notes` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `po_items`
--

CREATE TABLE `po_items` (
  `id` int NOT NULL,
  `po_id` int NOT NULL,
  `pr_item_id` int NOT NULL,
  `item_id` int NOT NULL,
  `warehouse_id` int NOT NULL,
  `supplier_id` int DEFAULT NULL,
  `quantity` int NOT NULL,
  `unit_cost` decimal(10,2) NOT NULL,
  `total_cost` decimal(10,2) NOT NULL,
  `received_date` date DEFAULT NULL,
  `status` enum('pending','partially_received','delivered','cancelled','processing','approved') CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT 'pending',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `received_quantity` decimal(10,2) DEFAULT '0.00'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `po_items`
--

INSERT INTO `po_items` (`id`, `po_id`, `pr_item_id`, `item_id`, `warehouse_id`, `supplier_id`, `quantity`, `unit_cost`, `total_cost`, `received_date`, `status`, `created_at`, `received_quantity`) VALUES
(74, 75, 192, 12, 5, 9, 1, 100.00, 100.00, NULL, 'pending', '2026-02-17 01:40:51', 0.00),
(75, 76, 195, 11, 5, 9, 1, 11.00, 11.00, NULL, 'delivered', '2026-02-17 13:40:46', 1.00),
(76, 77, 196, 11, 5, 10, 1, 150.00, 150.00, NULL, 'pending', '2026-02-18 02:14:15', 0.00),
(77, 79, 197, 12, 5, 4, 1, 150.00, 150.00, NULL, 'pending', '2026-02-18 02:46:18', 0.00),
(78, 80, 198, 11, 5, 5, 1, 111.00, 111.00, NULL, 'delivered', '2026-02-18 02:53:23', 1.00),
(79, 81, 199, 11, 5, 10, 1, 12.00, 12.00, '2026-02-18', 'delivered', '2026-02-18 03:39:54', 1.00),
(80, 82, 200, 11, 5, 9, 3, 40.00, 120.00, '2026-02-18', 'partially_received', '2026-02-18 03:45:06', 2.00),
(81, 83, 202, 13, 5, 10, 3, 30.00, 60.00, '2026-02-18', 'partially_received', '2026-02-18 05:15:25', 2.00),
(82, 84, 203, 11, 5, 9, 3, 50.00, 150.00, '2026-02-18', 'partially_received', '2026-02-18 05:49:12', 2.00),
(83, 85, 204, 11, 5, 10, 2, 25.00, 25.00, '2026-02-18', 'partially_received', '2026-02-18 05:55:54', 1.00),
(84, 86, 205, 11, 5, 10, 2, 10.00, 10.00, '2026-02-18', 'partially_received', '2026-02-18 06:01:38', 1.00),
(85, 87, 206, 11, 5, 9, 2, 15.00, 15.00, '2026-02-18', 'partially_received', '2026-02-18 06:21:51', 1.00),
(86, 88, 211, 13, 5, 9, 1, 50.00, 50.00, NULL, 'pending', '2026-02-18 07:46:03', 0.00),
(87, 89, 218, 13, 5, 9, 1, 51.00, 51.00, NULL, 'pending', '2026-02-18 08:25:50', 0.00),
(88, 90, 222, 12, 5, 9, 1, 150.00, 150.00, NULL, 'pending', '2026-02-18 08:54:22', 0.00),
(89, 91, 224, 12, 5, 9, 1, 151.00, 151.00, '2026-02-18', 'delivered', '2026-02-18 09:03:21', 1.00),
(90, 92, 225, 12, 5, 4, 1, 152.00, 152.00, '2026-02-18', 'delivered', '2026-02-18 09:05:14', 1.00),
(91, 93, 227, 12, 5, 9, 1, 99.00, 99.00, '2026-02-18', 'delivered', '2026-02-18 09:47:37', 1.00),
(92, 94, 228, 12, 5, 9, 1, 95.00, 95.00, '2026-02-17', 'delivered', '2026-02-18 10:44:15', 1.00),
(93, 95, 229, 12, 5, 9, 1, 51.00, 51.00, '2026-02-16', 'delivered', '2026-02-18 10:46:48', 1.00),
(94, 96, 231, 12, 5, 10, 1, 15.00, 15.00, '2026-02-18', 'delivered', '2026-02-18 11:14:22', 1.00),
(95, 97, 232, 12, 5, 9, 1, 16.00, 16.00, '2026-02-18', 'delivered', '2026-02-18 12:34:27', 1.00),
(96, 98, 234, 12, 5, 9, 1, 11.00, 11.00, NULL, 'approved', '2026-02-18 12:45:05', 0.00),
(97, 98, 235, 13, 5, 9, 2, 12.00, 24.00, NULL, 'approved', '2026-02-18 12:45:05', 0.00),
(98, 99, 240, 12, 5, 10, 2, 10.00, 20.00, '2026-02-18', 'delivered', '2026-02-18 13:05:01', 2.00),
(99, 99, 241, 13, 5, 10, 1, 10.00, 10.00, '2026-02-18', 'delivered', '2026-02-18 13:05:01', 1.00),
(100, 100, 243, 12, 5, 9, 2, 10.00, 20.00, '2026-02-18', 'delivered', '2026-02-18 13:21:17', 2.00),
(101, 100, 244, 13, 5, 9, 1, 11.00, 11.00, '2026-02-18', 'delivered', '2026-02-18 13:21:17', 1.00),
(102, 101, 246, 12, 5, 9, 2, 10.00, 20.00, '2026-02-18', 'delivered', '2026-02-18 13:32:22', 2.00),
(103, 101, 247, 13, 5, 9, 1, 20.00, 20.00, '2026-02-18', 'delivered', '2026-02-18 13:32:22', 1.00),
(104, 102, 248, 12, 5, 9, 2, 10.00, 20.00, NULL, 'pending', '2026-02-18 14:27:56', 0.00),
(105, 103, 250, 13, 5, 9, 1, 30.00, 30.00, NULL, 'pending', '2026-02-18 14:54:55', 0.00),
(106, 104, 253, 12, 5, 9, 2, 15.00, 30.00, NULL, 'processing', '2026-02-18 15:33:45', 0.00),
(107, 105, 257, 13, 5, 10, 2, 140.00, 140.00, '2026-02-19', 'partially_received', '2026-02-19 07:38:03', 1.00),
(108, 106, 259, 13, 5, 9, 1, 100.00, 100.00, NULL, 'pending', '2026-02-19 07:49:27', 0.00),
(109, 107, 261, 13, 5, 9, 1, 150.00, 150.00, NULL, 'processing', '2026-02-19 07:50:57', 0.00),
(110, 108, 263, 11, 5, 10, 1, 120.00, 120.00, '2026-02-19', 'delivered', '2026-02-19 07:58:56', 1.00),
(111, 109, 265, 12, 5, 9, 2, 150.00, 300.00, '2026-02-19', 'delivered', '2026-02-19 08:25:01', 2.00),
(112, 109, 266, 13, 5, 9, 1, 180.00, 180.00, '2026-02-19', 'delivered', '2026-02-19 08:25:01', 1.00),
(113, 110, 268, 13, 5, 9, 1, 101.00, 101.00, NULL, 'approved', '2026-02-19 09:47:59', 0.00),
(114, 111, 269, 13, 5, 9, 3, 144.00, 288.00, '2026-02-19', 'partially_received', '2026-02-19 10:02:26', 2.00),
(115, 112, 270, 13, 5, 9, 2, 121.00, 121.00, '2026-02-19', 'partially_received', '2026-02-19 10:11:58', 1.00),
(116, 113, 279, 12, 5, 9, 1, 121.00, 121.00, NULL, 'pending', '2026-02-27 14:02:39', 0.00),
(117, 114, 277, 11, 5, 9, 1, 150.00, 150.00, NULL, 'pending', '2026-02-27 14:07:10', 0.00),
(118, 115, 280, 11, 5, 9, 1, 12.00, 12.00, NULL, 'pending', '2026-02-27 14:10:12', 0.00),
(119, 116, 281, 11, 5, 9, 1, 22.00, 22.00, NULL, 'pending', '2026-02-27 14:11:33', 0.00);

-- --------------------------------------------------------

--
-- Table structure for table `projects`
--

CREATE TABLE `projects` (
  `id` int NOT NULL,
  `project_name` varchar(255) NOT NULL,
  `project_code` varchar(50) NOT NULL,
  `address` text,
  `description` text,
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `status` enum('planning','active','completed','on-hold') DEFAULT 'planning',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `threshold_amount` decimal(15,2) DEFAULT '0.00'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `projects`
--

INSERT INTO `projects` (`id`, `project_name`, `project_code`, `address`, `description`, `start_date`, `end_date`, `status`, `created_at`, `updated_at`, `threshold_amount`) VALUES
(17, 'Project', '001', 'ProjectProject, ProjectProject', 'Project', '2026-02-16', '2027-02-27', 'active', '2026-02-16 09:25:44', '2026-02-20 11:21:58', 996622.00),
(18, 'asdsad', '22', 'asd', 'asd', '2026-03-01', '2026-03-31', 'active', '2026-03-23 09:11:24', '2026-03-23 09:11:24', NULL),
(19, 'Bahay', '02201', 'santa marcela', 'asd', '2026-04-01', '2026-04-30', 'active', '2026-04-15 07:11:09', '2026-04-15 07:24:57', 9999840.00),
(47, 'Project1', '012', 'asdas', 'asdsad', '2026-10-02', '2026-10-31', 'active', '2026-10-01 00:49:37', '2026-10-01 00:49:37', NULL),
(60, 'Project21', '02021', 'asdasd', 'asd', '2026-10-01', '2026-10-10', 'active', '2026-10-01 01:39:51', '2026-10-01 01:39:51', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `project_engineers`
--

CREATE TABLE `project_engineers` (
  `id` int NOT NULL,
  `project_id` int NOT NULL,
  `user_id` int NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `project_engineers`
--

INSERT INTO `project_engineers` (`id`, `project_id`, `user_id`, `created_at`) VALUES
(16, 17, 6, '2026-02-16 09:25:44'),
(17, 18, 2, '2026-03-23 09:11:24'),
(18, 19, 6, '2026-04-15 07:11:09'),
(19, 19, 2, '2026-04-15 07:11:09'),
(47, 47, 6, '2026-10-01 00:49:37'),
(60, 60, 6, '2026-10-01 01:39:51'),
(61, 60, 2, '2026-10-01 01:39:51');

-- --------------------------------------------------------

--
-- Table structure for table `project_rentals`
--

CREATE TABLE `project_rentals` (
  `id` int NOT NULL,
  `project_id` int NOT NULL,
  `vehicle_id` int DEFAULT NULL,
  `equipment_id` int DEFAULT NULL,
  `start_date` datetime NOT NULL,
  `end_date` datetime NOT NULL,
  `rate` decimal(10,2) NOT NULL,
  `rate_type` enum('daily','hourly') NOT NULL DEFAULT 'daily',
  `total_cost` decimal(10,2) NOT NULL,
  `notes` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `project_rentals`
--

INSERT INTO `project_rentals` (`id`, `project_id`, `vehicle_id`, `equipment_id`, `start_date`, `end_date`, `rate`, `rate_type`, `total_cost`, `notes`, `created_at`, `updated_at`) VALUES
(14, 19, 2, NULL, '2026-04-15 15:12:00', '2026-04-16 15:12:00', 1000.00, 'hourly', 24000.00, '', '2026-04-15 07:12:48', '2026-04-15 07:12:48');

-- --------------------------------------------------------

--
-- Table structure for table `project_workers`
--

CREATE TABLE `project_workers` (
  `id` int NOT NULL,
  `project_id` int NOT NULL,
  `user_id` int NOT NULL,
  `assigned_date` date NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `project_workers`
--

INSERT INTO `project_workers` (`id`, `project_id`, `user_id`, `assigned_date`, `created_at`) VALUES
(40, 17, 1, '2026-02-22', '2026-02-22 12:17:57'),
(41, 19, 1, '2026-04-15', '2026-04-15 07:12:01'),
(42, 19, 2, '2026-04-15', '2026-04-15 07:12:01'),
(43, 19, 4, '2026-04-15', '2026-04-15 07:12:01'),
(44, 17, 2, '2026-10-01', '2026-09-30 16:13:58');

-- --------------------------------------------------------

--
-- Table structure for table `pr_items`
--

CREATE TABLE `pr_items` (
  `id` int NOT NULL,
  `pr_id` int NOT NULL,
  `item_id` int NOT NULL,
  `warehouse_id` int DEFAULT NULL,
  `supplier_id` int DEFAULT NULL,
  `quantity` int NOT NULL,
  `delivered_quantity` decimal(15,2) DEFAULT '0.00',
  `supplier_received_quantity` decimal(10,2) NOT NULL DEFAULT '0.00',
  `unit_cost` decimal(10,2) DEFAULT '0.00',
  `total_cost` decimal(15,2) DEFAULT '0.00',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `pr_items`
--

INSERT INTO `pr_items` (`id`, `pr_id`, `item_id`, `warehouse_id`, `supplier_id`, `quantity`, `delivered_quantity`, `supplier_received_quantity`, `unit_cost`, `total_cost`, `created_at`) VALUES
(188, 161, 11, 5, 9, 1, 0.00, 0.00, 0.00, 0.00, '2026-02-16 08:14:13'),
(190, 163, 11, 5, NULL, 1, 0.00, 0.00, 0.00, 0.00, '2026-02-16 09:27:17'),
(191, 164, 11, 5, NULL, 1, 0.00, 0.00, 0.00, 0.00, '2026-02-16 09:40:00'),
(192, 164, 12, 5, NULL, 1, 0.00, 0.00, 0.00, 0.00, '2026-02-16 09:40:00'),
(193, 165, 11, 5, NULL, 1, 0.00, 0.00, 0.00, 0.00, '2026-02-17 01:47:14'),
(194, 166, 12, 5, NULL, 1, 0.00, 0.00, 0.00, 0.00, '2026-02-17 01:48:43'),
(195, 167, 11, 5, 9, 1, 0.00, 0.00, 0.00, 0.00, '2026-02-17 13:03:10'),
(196, 168, 11, 5, NULL, 1, 0.00, 0.00, 0.00, 0.00, '2026-02-18 01:49:59'),
(197, 169, 12, 5, 4, 1, 0.00, 0.00, 150.00, 150.00, '2026-02-18 02:30:11'),
(198, 170, 11, 5, 5, 1, 0.00, 0.00, 111.00, 111.00, '2026-02-18 02:52:23'),
(199, 171, 11, 5, 10, 1, 0.00, 0.00, 12.00, 12.00, '2026-02-18 03:39:18'),
(200, 172, 11, 5, 9, 3, 0.00, 0.00, 40.00, 120.00, '2026-02-18 03:44:36'),
(201, 173, 11, 5, NULL, 1, 0.00, 0.00, 0.00, 0.00, '2026-02-18 04:59:52'),
(202, 174, 13, 5, 10, 3, 2.00, 0.00, 30.00, 150.00, '2026-02-18 05:14:43'),
(203, 175, 11, 5, 9, 3, 2.00, 0.00, 50.00, 100.00, '2026-02-18 05:48:44'),
(204, 176, 11, 5, 10, 2, 1.00, 0.00, 25.00, 25.00, '2026-02-18 05:55:33'),
(205, 177, 11, 5, 10, 2, 1.00, 0.00, 10.00, 10.00, '2026-02-18 06:01:14'),
(206, 178, 11, 5, 9, 2, 1.00, 0.00, 15.00, 30.00, '2026-02-18 06:20:53'),
(207, 179, 11, 5, NULL, 1, 0.00, 0.00, 0.00, 0.00, '2026-02-18 06:36:45'),
(208, 180, 11, 5, NULL, 12, 0.00, 0.00, 0.00, 0.00, '2026-02-18 07:07:23'),
(209, 181, 11, 5, NULL, 1, 0.00, 0.00, 0.00, 0.00, '2026-02-18 07:31:04'),
(210, 181, 12, 5, NULL, 1, 0.00, 0.00, 0.00, 0.00, '2026-02-18 07:31:04'),
(211, 182, 13, 5, NULL, 3, 0.00, 0.00, 0.00, 0.00, '2026-02-18 07:39:53'),
(212, 182, 11, 5, NULL, 1, 0.00, 0.00, 0.00, 0.00, '2026-02-18 07:39:53'),
(214, 184, 11, 5, NULL, 10, 0.00, 0.00, 0.00, 0.00, '2026-02-18 08:10:04'),
(215, 184, 12, 5, NULL, 3, 0.00, 0.00, 0.00, 0.00, '2026-02-18 08:10:04'),
(216, 185, 11, 5, NULL, 11, 0.00, 0.00, 0.00, 0.00, '2026-02-18 08:11:43'),
(217, 185, 13, 5, NULL, 4, 0.00, 0.00, 0.00, 0.00, '2026-02-18 08:11:43'),
(218, 186, 13, 5, NULL, 4, 0.00, 0.00, 0.00, 0.00, '2026-02-18 08:23:27'),
(219, 186, 11, 5, NULL, 11, 0.00, 0.00, 0.00, 0.00, '2026-02-18 08:23:27'),
(220, 187, 11, 5, NULL, 10, 0.00, 0.00, 0.00, 0.00, '2026-02-18 08:38:11'),
(221, 188, 11, 5, NULL, 5, 0.00, 0.00, 0.00, 0.00, '2026-02-18 08:38:47'),
(222, 189, 12, 5, NULL, 1, 0.00, 0.00, 0.00, 0.00, '2026-02-18 08:43:22'),
(223, 190, 11, 5, 9, 1, 0.00, 0.00, 70.00, 0.00, '2026-02-18 09:01:45'),
(224, 191, 12, 5, NULL, 1, 0.00, 0.00, 151.00, 151.00, '2026-02-18 09:02:16'),
(225, 192, 12, 5, 4, 1, 1.00, 0.00, 152.00, 152.00, '2026-02-18 09:04:28'),
(226, 193, 12, 5, NULL, 2, 0.00, 0.00, 0.00, 0.00, '2026-02-18 09:44:27'),
(227, 194, 12, 5, NULL, 1, 1.00, 0.00, 99.00, 99.00, '2026-02-18 09:47:03'),
(228, 195, 12, 5, NULL, 1, 1.00, 0.00, 95.00, 95.00, '2026-02-18 10:43:44'),
(229, 196, 12, 5, 9, 1, 1.00, 0.00, 51.00, 51.00, '2026-02-18 10:46:24'),
(230, 197, 12, 5, NULL, 1, 0.00, 0.00, 0.00, 0.00, '2026-02-18 10:48:49'),
(231, 198, 12, 5, NULL, 1, 1.00, 0.00, 15.00, 15.00, '2026-02-18 11:13:12'),
(232, 199, 12, 5, NULL, 1, 1.00, 0.00, 16.00, 16.00, '2026-02-18 12:33:56'),
(233, 200, 11, 5, NULL, 1, 0.00, 0.00, 0.00, 0.00, '2026-02-18 12:41:44'),
(234, 200, 12, 5, NULL, 1, 0.00, 0.00, 11.00, 11.00, '2026-02-18 12:41:44'),
(235, 200, 13, 5, NULL, 5, 0.00, 0.00, 12.00, 24.00, '2026-02-18 12:41:44'),
(236, 201, 11, 5, NULL, 1, 0.00, 0.00, 0.00, 0.00, '2026-02-18 12:46:52'),
(237, 201, 12, 5, NULL, 1, 0.00, 0.00, 0.00, 0.00, '2026-02-18 12:46:52'),
(238, 201, 13, 5, NULL, 5, 0.00, 0.00, 0.00, 0.00, '2026-02-18 12:46:52'),
(239, 202, 11, 5, NULL, 1, 1.00, 0.00, 0.00, 0.00, '2026-02-18 13:03:03'),
(240, 202, 12, 5, NULL, 5, 3.00, 0.00, 10.00, 20.00, '2026-02-18 13:03:03'),
(241, 202, 13, 5, NULL, 1, 0.00, 0.00, 10.00, 10.00, '2026-02-18 13:03:03'),
(242, 203, 11, 5, NULL, 1, 1.00, 0.00, 0.00, 0.00, '2026-02-18 13:20:41'),
(243, 203, 12, 5, NULL, 4, 2.00, 0.00, 10.00, 20.00, '2026-02-18 13:20:41'),
(244, 203, 13, 5, NULL, 1, 0.00, 0.00, 11.00, 11.00, '2026-02-18 13:20:41'),
(245, 204, 11, 5, NULL, 1, 1.00, 0.00, 0.00, 0.00, '2026-02-18 13:31:48'),
(246, 204, 12, 5, NULL, 4, 2.00, 2.00, 10.00, 20.00, '2026-02-18 13:31:48'),
(247, 204, 13, 5, NULL, 1, 0.00, 1.00, 20.00, 20.00, '2026-02-18 13:31:48'),
(248, 205, 12, 5, NULL, 4, 0.00, 0.00, 10.00, 20.00, '2026-02-18 14:25:46'),
(249, 206, 11, 5, NULL, 1, 0.00, 0.00, 0.00, 0.00, '2026-02-18 14:30:46'),
(250, 207, 13, 5, NULL, 1, 0.00, 0.00, 30.00, 30.00, '2026-02-18 14:32:57'),
(251, 208, 11, 5, NULL, 1, 0.00, 0.00, 0.00, 0.00, '2026-02-18 15:14:14'),
(252, 209, 11, 5, NULL, 7, 7.00, 0.00, 0.00, 0.00, '2026-02-18 15:25:07'),
(253, 210, 12, 5, NULL, 4, 0.00, 0.00, 15.00, 30.00, '2026-02-18 15:31:53'),
(254, 211, 11, 5, NULL, 1, 1.00, 0.00, 0.00, 0.00, '2026-02-19 01:25:59'),
(255, 212, 11, 5, NULL, 2, 2.00, 0.00, 0.00, 0.00, '2026-02-19 07:10:47'),
(256, 213, 11, 5, NULL, 2, 2.00, 0.00, 0.00, 0.00, '2026-02-19 07:31:39'),
(257, 214, 13, 5, 10, 2, 1.00, 1.00, 140.00, 280.00, '2026-02-19 07:37:24'),
(258, 215, 12, 5, NULL, 1, 0.00, 0.00, 0.00, 0.00, '2026-02-19 07:47:29'),
(259, 215, 13, 5, NULL, 2, 0.00, 0.00, 100.00, 100.00, '2026-02-19 07:47:29'),
(260, 216, 12, 5, NULL, 1, 0.00, 0.00, 0.00, 0.00, '2026-02-19 07:50:08'),
(261, 216, 13, 5, NULL, 2, 0.00, 0.00, 150.00, 150.00, '2026-02-19 07:50:08'),
(262, 217, 11, 5, NULL, 1, 0.00, 0.00, 0.00, 0.00, '2026-02-19 07:57:36'),
(263, 218, 11, 5, 10, 1, 1.00, 1.00, 120.00, 120.00, '2026-02-19 07:58:26'),
(264, 219, 11, 5, NULL, 1, 1.00, 0.00, 0.00, 0.00, '2026-02-19 08:24:10'),
(265, 219, 12, 5, NULL, 4, 2.00, 2.00, 150.00, 300.00, '2026-02-19 08:24:10'),
(266, 219, 13, 5, NULL, 1, 0.00, 1.00, 180.00, 180.00, '2026-02-19 08:24:10'),
(268, 221, 13, 5, NULL, 1, 0.00, 0.00, 101.00, 101.00, '2026-02-19 09:47:03'),
(269, 222, 13, 5, NULL, 3, 2.00, 2.00, 144.00, 432.00, '2026-02-19 10:01:50'),
(270, 223, 13, 5, NULL, 2, 1.00, 1.00, 121.00, 242.00, '2026-02-19 10:11:34'),
(271, 224, 11, 5, NULL, 1, 1.00, 0.00, 0.00, 0.00, '2026-02-19 10:14:19'),
(272, 225, 11, 5, NULL, 1, 0.00, 0.00, 0.00, 0.00, '2026-02-20 10:00:36'),
(273, 226, 11, 5, 9, 1, 0.00, 0.00, 99.00, 0.00, '2026-02-20 11:20:51'),
(274, 227, 11, 5, NULL, 1, 0.00, 0.00, 0.00, 0.00, '2026-02-20 11:21:06'),
(275, 228, 13, 5, NULL, 1, 0.00, 0.00, 0.00, 0.00, '2026-02-20 11:22:19'),
(276, 229, 11, 5, NULL, 1, 0.00, 0.00, 0.00, 0.00, '2026-02-27 05:54:21'),
(277, 230, 11, 5, 9, 1, 0.00, 0.00, 150.00, 150.00, '2026-02-27 05:54:42'),
(278, 231, 11, 5, NULL, 1, 0.00, 0.00, 0.00, 0.00, '2026-02-27 06:45:25'),
(279, 232, 12, 5, 9, 1, 0.00, 0.00, 121.00, 121.00, '2026-02-27 06:45:37'),
(280, 233, 11, 5, 9, 1, 0.00, 0.00, 12.00, 12.00, '2026-02-27 14:09:16'),
(281, 234, 11, 5, 9, 1, 0.00, 0.00, 22.00, 22.00, '2026-02-27 14:10:53'),
(282, 235, 16, 5, NULL, 20, 0.00, 0.00, 0.00, 0.00, '2026-03-27 03:16:54'),
(283, 236, 11, 5, NULL, 1, 1.00, 0.00, 0.00, 0.00, '2026-04-15 07:15:26'),
(284, 236, 12, 5, NULL, 1, 1.00, 0.00, 0.00, 0.00, '2026-04-15 07:15:26'),
(287, 238, 16, 5, NULL, 1, 0.00, 0.00, 0.00, 0.00, '2026-10-01 05:11:31'),
(288, 239, 11, 5, NULL, 1, 0.00, 0.00, 0.00, 0.00, '2026-10-01 06:05:01'),
(289, 269, 11, 5, NULL, 1, 0.00, 0.00, 0.00, 0.00, '2026-10-01 12:50:36'),
(290, 275, 16, 5, 9, 10, 0.00, 0.00, 0.00, 0.00, '2026-10-01 14:07:11');

-- --------------------------------------------------------

--
-- Table structure for table `pr_routing`
--

CREATE TABLE `pr_routing` (
  `id` int NOT NULL,
  `pr_id` int NOT NULL,
  `stage` enum('requestor','warehouse','purchasing','accounting','approver','purchasing_final','warehouse_receiving','purchasing_completion','accounting_final','completed','rejected','warehouse_releasing','complete_warehouse_releasing') CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
  `status` enum('pending','approved','rejected','completed') NOT NULL DEFAULT 'pending',
  `action_by` int NOT NULL,
  `remarks` text,
  `simplified_flow` tinyint(1) DEFAULT '0',
  `document_type` enum('po','ws') DEFAULT 'po',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `pr_routing`
--

INSERT INTO `pr_routing` (`id`, `pr_id`, `stage`, `status`, `action_by`, `remarks`, `simplified_flow`, `document_type`, `created_at`) VALUES
(853, 163, 'warehouse', 'pending', 12, 'asd', 0, 'po', '2026-02-16 09:28:50'),
(854, 163, 'purchasing', 'pending', 10, 'sd', 0, 'po', '2026-02-16 09:56:30'),
(855, 163, 'purchasing', 'pending', 7, 'Withdrawal Slip WS-2026-0001 created', 0, 'po', '2026-02-16 09:56:49'),
(856, 163, 'approver', 'pending', 7, 'asdw', 0, 'po', '2026-02-16 09:56:55'),
(857, 163, 'warehouse_releasing', 'pending', 12, 'sdw', 0, 'po', '2026-02-16 09:59:21'),
(858, 164, 'warehouse', 'pending', 12, '', 0, 'po', '2026-02-17 01:39:13'),
(859, 164, 'purchasing', 'pending', 10, 'sd', 0, 'po', '2026-02-17 01:39:29'),
(860, 164, 'purchasing', 'pending', 7, 'Withdrawal Slip WS-2026-0002 created', 0, 'po', '2026-02-17 01:40:25'),
(861, 164, 'purchasing', 'pending', 7, 'Purchase Order PO-2026-0001 created', 0, 'po', '2026-02-17 01:40:51'),
(862, 166, 'warehouse', 'pending', 7, '', 0, 'po', '2026-02-17 01:59:15'),
(863, 166, 'purchasing', 'pending', 10, 'as', 0, 'po', '2026-02-17 12:45:12'),
(864, 167, 'warehouse', 'pending', 7, '', 0, 'po', '2026-02-17 13:03:17'),
(865, 167, 'purchasing', 'pending', 10, 'asd', 0, 'po', '2026-02-17 13:03:29'),
(866, 167, 'purchasing', 'pending', 7, 'Purchase Order PO-2026-0002 created', 0, 'po', '2026-02-17 13:40:46'),
(867, 167, 'accounting', 'pending', 7, 'sdas', 0, 'po', '2026-02-17 13:49:25'),
(868, 167, 'approver', 'pending', 8, 'sad', 0, 'po', '2026-02-17 13:49:49'),
(869, 167, 'purchasing_final', 'pending', 12, 'asd', 0, 'po', '2026-02-17 13:50:03'),
(870, 167, 'warehouse_receiving', 'pending', 7, 'asd', 0, 'po', '2026-02-17 13:51:20'),
(871, 167, 'warehouse_receiving', 'pending', 10, 'asd', 0, 'po', '2026-02-17 13:54:01'),
(872, 167, 'purchasing_completion', 'pending', 10, 'asdw', 0, 'po', '2026-02-17 13:59:25'),
(873, 168, 'warehouse', 'pending', 8, '', 0, 'po', '2026-02-18 01:50:06'),
(874, 168, 'purchasing', 'pending', 10, 'asd', 0, 'po', '2026-02-18 02:01:01'),
(875, 168, 'purchasing', 'pending', 7, 'Purchase Order PO-2026-0003 created', 0, 'po', '2026-02-18 02:14:15'),
(876, 169, 'warehouse', 'pending', 10, '', 0, 'po', '2026-02-18 02:30:20'),
(877, 169, 'purchasing', 'pending', 10, 'asdw', 0, 'po', '2026-02-18 02:30:24'),
(878, 169, 'purchasing', 'pending', 7, 'Purchase Order PO-2026-0004 created', 0, 'po', '2026-02-18 02:46:18'),
(879, 170, 'warehouse', 'pending', 10, '', 0, 'po', '2026-02-18 02:52:30'),
(880, 170, 'purchasing', 'pending', 10, 'asd', 0, 'po', '2026-02-18 02:52:33'),
(881, 170, 'purchasing', 'pending', 7, 'Purchase Order PO-2026-0005 created', 0, 'po', '2026-02-18 02:53:23'),
(882, 170, 'accounting', 'pending', 7, 'ad', 0, 'po', '2026-02-18 02:54:10'),
(883, 170, 'approver', 'pending', 8, 'asd', 0, 'po', '2026-02-18 02:54:21'),
(884, 170, 'purchasing_final', 'pending', 12, 'asdw', 0, 'po', '2026-02-18 03:01:25'),
(885, 170, 'warehouse_receiving', 'pending', 7, 'asd', 0, 'po', '2026-02-18 03:05:12'),
(886, 170, 'warehouse_receiving', 'pending', 10, 'sdw', 0, 'po', '2026-02-18 03:20:26'),
(887, 171, 'warehouse', 'pending', 10, '', 0, 'po', '2026-02-18 03:39:22'),
(888, 171, 'purchasing', 'pending', 10, 'asd', 0, 'po', '2026-02-18 03:39:26'),
(889, 171, 'purchasing', 'pending', 7, 'Purchase Order PO-2026-0006 created', 0, 'po', '2026-02-18 03:39:54'),
(890, 171, 'accounting', 'pending', 7, 'asd', 0, 'po', '2026-02-18 03:39:58'),
(891, 171, 'approver', 'pending', 8, 'adsw', 0, 'po', '2026-02-18 03:40:10'),
(892, 171, 'purchasing_final', 'pending', 12, 'asdw', 0, 'po', '2026-02-18 03:41:04'),
(893, 171, 'warehouse_receiving', 'pending', 7, 'adw', 0, 'po', '2026-02-18 03:41:36'),
(894, 171, 'warehouse_receiving', 'pending', 10, 'asdw', 0, 'po', '2026-02-18 03:42:14'),
(895, 170, 'purchasing_completion', 'pending', 10, 'asd', 0, 'po', '2026-02-18 03:44:07'),
(896, 172, 'warehouse', 'pending', 10, '', 0, 'po', '2026-02-18 03:44:41'),
(897, 172, 'purchasing', 'pending', 10, 'asd', 0, 'po', '2026-02-18 03:44:44'),
(898, 172, 'purchasing', 'pending', 7, 'Purchase Order PO-2026-0007 created', 0, 'po', '2026-02-18 03:45:06'),
(899, 172, 'accounting', 'pending', 7, 'dasd', 0, 'po', '2026-02-18 03:45:11'),
(900, 172, 'approver', 'pending', 8, 'asd', 0, 'po', '2026-02-18 03:45:23'),
(901, 172, 'purchasing_final', 'pending', 12, 'asdw', 0, 'po', '2026-02-18 03:45:36'),
(902, 172, 'warehouse_receiving', 'pending', 7, 'asd', 0, 'po', '2026-02-18 03:45:47'),
(903, 172, 'warehouse_receiving', 'pending', 10, 'asdw', 0, 'po', '2026-02-18 03:46:01'),
(904, 173, 'warehouse', 'pending', 10, '', 0, 'po', '2026-02-18 05:12:33'),
(905, 173, 'purchasing', 'pending', 10, 'wdasd', 0, 'po', '2026-02-18 05:12:37'),
(906, 169, 'rejected', 'rejected', 7, 'zdss', 0, 'po', '2026-02-18 05:13:31'),
(907, 174, 'warehouse', 'pending', 10, '', 0, 'po', '2026-02-18 05:14:57'),
(908, 174, 'purchasing', 'pending', 10, 'wa', 0, 'po', '2026-02-18 05:15:01'),
(909, 174, 'purchasing', 'pending', 7, 'Purchase Order PO-2026-0008 created', 0, 'po', '2026-02-18 05:15:25'),
(910, 174, 'accounting', 'pending', 7, 'asd', 0, 'po', '2026-02-18 05:15:31'),
(911, 174, 'approver', 'pending', 8, 'asdw', 0, 'po', '2026-02-18 05:15:43'),
(912, 174, 'purchasing_final', 'pending', 12, 'asdw', 0, 'po', '2026-02-18 05:15:55'),
(913, 174, 'warehouse_receiving', 'pending', 7, 'asdw', 0, 'po', '2026-02-18 05:16:26'),
(914, 174, 'warehouse_receiving', 'pending', 10, 'sdw', 0, 'po', '2026-02-18 05:36:40'),
(915, 175, 'warehouse', 'pending', 10, '', 0, 'po', '2026-02-18 05:48:49'),
(916, 175, 'purchasing', 'pending', 10, 'wad', 0, 'po', '2026-02-18 05:48:52'),
(917, 175, 'purchasing', 'pending', 7, 'Purchase Order PO-2026-0009 created', 0, 'po', '2026-02-18 05:49:12'),
(918, 175, 'accounting', 'pending', 7, 'asdw', 0, 'po', '2026-02-18 05:49:15'),
(919, 175, 'approver', 'pending', 8, 'asdw', 0, 'po', '2026-02-18 05:49:37'),
(920, 175, 'purchasing_final', 'pending', 12, 'asd', 0, 'po', '2026-02-18 05:49:55'),
(921, 175, 'warehouse_receiving', 'pending', 7, 'asdw', 0, 'po', '2026-02-18 05:50:06'),
(922, 175, 'warehouse_receiving', 'pending', 10, 'asdw', 0, 'po', '2026-02-18 05:50:38'),
(923, 176, 'warehouse', 'pending', 10, '', 0, 'po', '2026-02-18 05:55:38'),
(924, 176, 'purchasing', 'pending', 10, 'asd', 0, 'po', '2026-02-18 05:55:41'),
(925, 176, 'purchasing', 'pending', 7, 'Purchase Order PO-2026-0010 created', 0, 'po', '2026-02-18 05:55:54'),
(926, 176, 'accounting', 'pending', 7, 'asd', 0, 'po', '2026-02-18 05:55:58'),
(927, 176, 'approver', 'pending', 8, 'asd', 0, 'po', '2026-02-18 05:56:12'),
(928, 176, 'purchasing_final', 'pending', 12, 'asdw', 0, 'po', '2026-02-18 05:56:24'),
(929, 176, 'warehouse_receiving', 'pending', 7, 'asdw', 0, 'po', '2026-02-18 05:56:37'),
(930, 176, 'warehouse_receiving', 'pending', 10, 'asdw', 0, 'po', '2026-02-18 05:58:33'),
(931, 177, 'warehouse', 'pending', 10, '', 0, 'po', '2026-02-18 06:01:18'),
(932, 177, 'purchasing', 'pending', 10, 'asdw', 0, 'po', '2026-02-18 06:01:21'),
(933, 177, 'purchasing', 'pending', 7, 'Purchase Order PO-2026-0011 created', 0, 'po', '2026-02-18 06:01:38'),
(934, 177, 'accounting', 'pending', 7, 'asdw', 0, 'po', '2026-02-18 06:01:42'),
(935, 177, 'approver', 'pending', 8, 'asd', 0, 'po', '2026-02-18 06:01:55'),
(936, 177, 'purchasing_final', 'pending', 12, 'asdw', 0, 'po', '2026-02-18 06:02:06'),
(937, 177, 'warehouse_receiving', 'pending', 7, 'asdw', 0, 'po', '2026-02-18 06:03:16'),
(938, 177, 'warehouse_receiving', 'pending', 10, 'asdw', 0, 'po', '2026-02-18 06:04:52'),
(939, 177, 'purchasing_completion', 'pending', 10, 'asdw', 0, 'po', '2026-02-18 06:15:14'),
(940, 178, 'warehouse', 'pending', 10, '', 0, 'po', '2026-02-18 06:20:56'),
(941, 178, 'purchasing', 'pending', 10, 'asd', 0, 'po', '2026-02-18 06:21:00'),
(942, 178, 'purchasing', 'pending', 7, 'Purchase Order PO-2026-0012 created', 0, 'po', '2026-02-18 06:21:51'),
(943, 178, 'accounting', 'pending', 7, 'asdw', 0, 'po', '2026-02-18 06:21:54'),
(944, 178, 'approver', 'pending', 8, 'asdw', 0, 'po', '2026-02-18 06:22:07'),
(945, 178, 'purchasing_final', 'pending', 12, 'sdw', 0, 'po', '2026-02-18 06:22:23'),
(946, 178, 'warehouse_receiving', 'pending', 7, 'asdw', 0, 'po', '2026-02-18 06:28:16'),
(947, 178, 'warehouse_receiving', 'pending', 10, 'asdw', 0, 'po', '2026-02-18 06:31:04'),
(948, 178, 'purchasing_completion', 'pending', 10, 'asdw', 0, 'po', '2026-02-18 06:34:09'),
(949, 178, 'accounting_final', 'pending', 7, 'adw', 0, 'po', '2026-02-18 06:35:08'),
(950, 178, 'completed', 'completed', 8, 'adw', 0, 'po', '2026-02-18 06:35:22'),
(951, 182, 'warehouse', 'pending', 8, '', 0, 'po', '2026-02-18 07:44:51'),
(952, 182, 'purchasing', 'pending', 10, 'asdw', 0, 'po', '2026-02-18 07:45:04'),
(953, 182, 'purchasing', 'pending', 7, 'Withdrawal Slip WS-2026-0003 created', 0, 'po', '2026-02-18 07:45:36'),
(954, 182, 'purchasing', 'pending', 7, 'Purchase Order PO-2026-0013 created', 0, 'po', '2026-02-18 07:46:03'),
(955, 186, 'warehouse', 'pending', 7, '', 0, 'po', '2026-02-18 08:25:04'),
(956, 186, 'purchasing', 'pending', 10, 'asdw', 0, 'po', '2026-02-18 08:25:20'),
(957, 186, 'purchasing', 'pending', 7, 'Withdrawal Slip WS-2026-0004 created', 0, 'po', '2026-02-18 08:25:40'),
(958, 186, 'purchasing', 'pending', 7, 'Purchase Order PO-2026-0014 created', 0, 'po', '2026-02-18 08:25:50'),
(959, 189, 'warehouse', 'pending', 7, '', 0, 'po', '2026-02-18 08:43:40'),
(960, 189, 'purchasing', 'pending', 10, 'sda', 0, 'po', '2026-02-18 08:48:34'),
(961, 189, 'purchasing', 'pending', 7, 'Purchase Order PO-2026-0015 created', 0, 'po', '2026-02-18 08:54:22'),
(962, 190, 'warehouse', 'pending', 7, '', 0, 'po', '2026-02-18 09:01:49'),
(963, 191, 'warehouse', 'pending', 7, '', 0, 'po', '2026-02-18 09:02:20'),
(964, 191, 'purchasing', 'pending', 10, 'asd', 0, 'po', '2026-02-18 09:02:32'),
(965, 190, 'purchasing', 'pending', 10, 'asdw', 0, 'po', '2026-02-18 09:02:41'),
(966, 191, 'purchasing', 'pending', 7, 'Purchase Order PO-2026-0016 created', 0, 'po', '2026-02-18 09:03:21'),
(967, 192, 'warehouse', 'pending', 7, '', 0, 'po', '2026-02-18 09:04:32'),
(968, 192, 'purchasing', 'pending', 10, 'adw', 0, 'po', '2026-02-18 09:04:46'),
(969, 192, 'purchasing', 'pending', 7, 'Purchase Order PO-2026-0017 created', 0, 'po', '2026-02-18 09:05:14'),
(970, 192, 'accounting', 'pending', 7, 'asdw', 0, 'po', '2026-02-18 09:05:51'),
(971, 191, 'accounting', 'pending', 7, 'asd', 0, 'po', '2026-02-18 09:05:58'),
(972, 192, 'approver', 'pending', 8, 'asdw', 0, 'po', '2026-02-18 09:06:15'),
(973, 191, 'approver', 'pending', 8, 'sdw', 0, 'po', '2026-02-18 09:06:22'),
(974, 192, 'purchasing_final', 'pending', 12, 'asdw', 0, 'po', '2026-02-18 09:06:45'),
(975, 191, 'purchasing_final', 'pending', 12, 'asdw', 0, 'po', '2026-02-18 09:07:52'),
(976, 192, 'warehouse_receiving', 'pending', 7, 'asdw', 0, 'po', '2026-02-18 09:08:08'),
(977, 191, 'warehouse_receiving', 'pending', 7, 'asdw', 0, 'po', '2026-02-18 09:08:15'),
(978, 192, 'warehouse_receiving', 'pending', 10, 'zsadw', 0, 'po', '2026-02-18 09:09:57'),
(979, 192, 'purchasing_completion', 'pending', 10, 'asdw', 0, 'po', '2026-02-18 09:10:27'),
(980, 191, 'warehouse_receiving', 'pending', 10, 'asdw', 0, 'po', '2026-02-18 09:12:48'),
(981, 193, 'warehouse', 'pending', 10, '', 0, 'po', '2026-02-18 09:44:31'),
(982, 193, 'purchasing', 'pending', 10, 'asdw', 0, 'po', '2026-02-18 09:44:35'),
(983, 193, 'purchasing', 'pending', 7, 'Withdrawal Slip WS-2026-0005 created', 0, 'po', '2026-02-18 09:44:59'),
(984, 193, 'approver', 'pending', 7, 'asd', 0, 'po', '2026-02-18 09:45:03'),
(985, 193, 'warehouse_releasing', 'pending', 12, 'asdw', 0, 'po', '2026-02-18 09:45:25'),
(986, 193, 'warehouse_releasing', 'pending', 10, 'Withdrawal slip processed', 0, 'po', '2026-02-18 09:45:53'),
(987, 193, 'completed', 'pending', 10, 'asdw', 0, 'po', '2026-02-18 09:46:04'),
(988, 191, 'purchasing_completion', 'pending', 10, 'adw', 0, 'po', '2026-02-18 09:46:49'),
(989, 194, 'warehouse', 'pending', 10, '', 0, 'po', '2026-02-18 09:47:07'),
(990, 194, 'purchasing', 'pending', 10, 'dwd', 0, 'po', '2026-02-18 09:47:10'),
(991, 194, 'purchasing', 'pending', 7, 'Purchase Order PO-2026-0018 created', 0, 'po', '2026-02-18 09:47:37'),
(992, 194, 'accounting', 'pending', 7, 'asd', 0, 'po', '2026-02-18 09:47:43'),
(993, 194, 'approver', 'pending', 8, 'asdw', 0, 'po', '2026-02-18 09:48:05'),
(994, 194, 'purchasing_final', 'pending', 12, 'adw', 0, 'po', '2026-02-18 09:48:29'),
(995, 194, 'warehouse_receiving', 'pending', 7, 'adw', 0, 'po', '2026-02-18 09:48:52'),
(996, 194, 'warehouse_receiving', 'pending', 10, 'asdw', 0, 'po', '2026-02-18 09:50:12'),
(997, 195, 'warehouse', 'pending', 10, '', 0, 'po', '2026-02-18 10:43:48'),
(998, 195, 'purchasing', 'pending', 10, 'adw', 0, 'po', '2026-02-18 10:43:52'),
(999, 195, 'purchasing', 'pending', 7, 'Purchase Order PO-2026-0019 created', 0, 'po', '2026-02-18 10:44:15'),
(1000, 195, 'accounting', 'pending', 7, 'asd', 0, 'po', '2026-02-18 10:44:19'),
(1001, 195, 'approver', 'pending', 8, 'asdw', 0, 'po', '2026-02-18 10:44:30'),
(1002, 195, 'purchasing_final', 'pending', 12, 'asdw', 0, 'po', '2026-02-18 10:44:48'),
(1003, 195, 'warehouse_receiving', 'pending', 7, 'dadw', 0, 'po', '2026-02-18 10:45:01'),
(1004, 195, 'warehouse_receiving', 'pending', 10, 'asdw', 0, 'po', '2026-02-18 10:45:22'),
(1005, 195, 'purchasing_completion', 'pending', 10, 'adw', 0, 'po', '2026-02-18 10:46:06'),
(1006, 196, 'warehouse', 'pending', 10, 'asdw', 0, 'po', '2026-02-18 10:46:29'),
(1007, 196, 'purchasing', 'pending', 10, 'asdw', 0, 'po', '2026-02-18 10:46:32'),
(1008, 196, 'purchasing', 'pending', 7, 'Purchase Order PO-2026-0020 created', 0, 'po', '2026-02-18 10:46:48'),
(1009, 196, 'accounting', 'pending', 7, 'sdw', 0, 'po', '2026-02-18 10:46:51'),
(1010, 196, 'approver', 'pending', 8, 'asdw', 0, 'po', '2026-02-18 10:47:03'),
(1011, 196, 'purchasing_final', 'pending', 12, 'adw', 0, 'po', '2026-02-18 10:47:16'),
(1012, 196, 'warehouse_receiving', 'pending', 7, 'asdw', 0, 'po', '2026-02-18 10:47:44'),
(1013, 196, 'warehouse_receiving', 'pending', 10, 'asdw', 0, 'po', '2026-02-18 10:48:00'),
(1014, 196, 'purchasing_completion', 'pending', 10, 'sdw', 0, 'po', '2026-02-18 10:48:12'),
(1015, 198, 'warehouse', 'pending', 10, '', 0, 'po', '2026-02-18 11:14:00'),
(1016, 198, 'purchasing', 'pending', 10, 'sd', 0, 'po', '2026-02-18 11:14:03'),
(1017, 198, 'purchasing', 'pending', 7, 'Purchase Order PO-2026-0021 created', 0, 'po', '2026-02-18 11:14:22'),
(1018, 198, 'accounting', 'pending', 7, 'asd', 0, 'po', '2026-02-18 11:14:26'),
(1019, 198, 'approver', 'pending', 8, 'adw', 0, 'po', '2026-02-18 11:14:39'),
(1020, 198, 'purchasing_final', 'pending', 12, 'asdw', 0, 'po', '2026-02-18 11:14:51'),
(1021, 198, 'warehouse_receiving', 'pending', 7, 'sadw', 0, 'po', '2026-02-18 11:15:32'),
(1022, 198, 'warehouse_receiving', 'pending', 10, 'sdw', 0, 'po', '2026-02-18 11:22:05'),
(1023, 198, 'purchasing_completion', 'pending', 10, 'sdw', 0, 'po', '2026-02-18 11:22:47'),
(1024, 194, 'purchasing_completion', 'pending', 10, 'asdw', 0, 'po', '2026-02-18 11:26:21'),
(1025, 199, 'warehouse', 'pending', 10, 'ad', 0, 'po', '2026-02-18 12:34:01'),
(1026, 199, 'purchasing', 'pending', 10, 'asd', 0, 'po', '2026-02-18 12:34:04'),
(1027, 199, 'purchasing', 'pending', 7, 'Purchase Order PO-2026-0022 created', 0, 'po', '2026-02-18 12:34:27'),
(1028, 199, 'accounting', 'pending', 7, 'sd', 0, 'po', '2026-02-18 12:34:30'),
(1029, 199, 'approver', 'pending', 8, 'asd', 0, 'po', '2026-02-18 12:34:43'),
(1030, 199, 'purchasing_final', 'pending', 12, 'asdw', 0, 'po', '2026-02-18 12:35:02'),
(1031, 199, 'warehouse_receiving', 'pending', 7, 'adw', 0, 'po', '2026-02-18 12:35:26'),
(1032, 199, 'warehouse_receiving', 'pending', 10, 'sdw', 0, 'po', '2026-02-18 12:38:51'),
(1033, 199, 'purchasing_completion', 'pending', 10, 'sd', 0, 'po', '2026-02-18 12:40:04'),
(1034, 199, 'accounting_final', 'pending', 7, 'sd', 0, 'po', '2026-02-18 12:40:17'),
(1035, 199, 'completed', 'completed', 8, 'sd', 0, 'po', '2026-02-18 12:40:55'),
(1036, 200, 'warehouse', 'pending', 8, '', 0, 'po', '2026-02-18 12:44:03'),
(1037, 200, 'purchasing', 'pending', 10, 'ds', 0, 'po', '2026-02-18 12:44:17'),
(1038, 200, 'purchasing', 'pending', 7, 'Withdrawal Slip WS-2026-0006 created', 0, 'po', '2026-02-18 12:44:32'),
(1039, 200, 'purchasing', 'pending', 7, 'Purchase Order PO-2026-0023 created', 0, 'po', '2026-02-18 12:45:05'),
(1040, 200, 'accounting', 'pending', 7, 'asd', 0, 'po', '2026-02-18 12:45:10'),
(1041, 200, 'approver', 'pending', 8, 'asdw', 0, 'po', '2026-02-18 12:49:55'),
(1042, 200, 'warehouse_releasing', 'pending', 12, 'sdw', 0, 'po', '2026-02-18 12:50:20'),
(1043, 200, 'warehouse_releasing', 'pending', 10, 'Withdrawal slip processed', 0, 'po', '2026-02-18 12:52:27'),
(1044, 202, 'warehouse', 'pending', 10, '', 0, 'po', '2026-02-18 13:03:50'),
(1045, 202, 'purchasing', 'pending', 10, 'ad', 0, 'po', '2026-02-18 13:03:54'),
(1046, 202, 'purchasing', 'pending', 7, 'Withdrawal Slip WS-2026-0007 created', 0, 'po', '2026-02-18 13:04:10'),
(1047, 202, 'purchasing', 'pending', 7, 'Purchase Order PO-2026-0024 created', 0, 'po', '2026-02-18 13:05:01'),
(1048, 202, 'accounting', 'pending', 7, 'asd', 0, 'po', '2026-02-18 13:05:07'),
(1049, 202, 'approver', 'pending', 8, 'sad', 0, 'po', '2026-02-18 13:05:20'),
(1050, 202, 'warehouse_releasing', 'pending', 12, 'asd', 0, 'po', '2026-02-18 13:05:33'),
(1051, 202, 'warehouse_releasing', 'pending', 10, 'Withdrawal slip processed', 0, 'po', '2026-02-18 13:05:48'),
(1052, 202, 'purchasing_final', 'pending', 10, 'sd', 0, 'po', '2026-02-18 13:15:07'),
(1053, 202, 'warehouse_receiving', 'pending', 7, 'asd', 0, 'po', '2026-02-18 13:15:42'),
(1054, 202, 'warehouse_receiving', 'pending', 10, 'sd', 0, 'po', '2026-02-18 13:16:03'),
(1055, 203, 'warehouse', 'pending', 10, '', 0, 'po', '2026-02-18 13:20:46'),
(1056, 203, 'purchasing', 'pending', 10, 'asd', 0, 'po', '2026-02-18 13:20:49'),
(1057, 203, 'purchasing', 'pending', 7, 'Withdrawal Slip WS-2026-0008 created', 0, 'po', '2026-02-18 13:21:02'),
(1058, 203, 'purchasing', 'pending', 7, 'Purchase Order PO-2026-0025 created', 0, 'po', '2026-02-18 13:21:17'),
(1059, 203, 'accounting', 'pending', 7, 'sda', 0, 'po', '2026-02-18 13:21:22'),
(1060, 203, 'approver', 'pending', 8, 'asd', 0, 'po', '2026-02-18 13:21:44'),
(1061, 203, 'warehouse_releasing', 'pending', 12, 'asdw', 0, 'po', '2026-02-18 13:21:58'),
(1062, 203, 'warehouse_releasing', 'pending', 10, 'Withdrawal slip processed', 0, 'po', '2026-02-18 13:22:14'),
(1063, 203, 'purchasing_final', 'pending', 10, 'asd', 0, 'po', '2026-02-18 13:23:08'),
(1064, 203, 'warehouse_receiving', 'pending', 7, 'asdw', 0, 'po', '2026-02-18 13:23:26'),
(1065, 203, 'warehouse_receiving', 'pending', 10, 'asdw', 0, 'po', '2026-02-18 13:23:39'),
(1066, 204, 'warehouse', 'pending', 10, '', 0, 'po', '2026-02-18 13:31:54'),
(1067, 204, 'purchasing', 'pending', 10, 'asd', 0, 'po', '2026-02-18 13:31:57'),
(1068, 204, 'purchasing', 'pending', 7, 'Withdrawal Slip WS-2026-0009 created', 0, 'po', '2026-02-18 13:32:11'),
(1069, 204, 'purchasing', 'pending', 7, 'Purchase Order PO-2026-0026 created', 0, 'po', '2026-02-18 13:32:22'),
(1070, 204, 'accounting', 'pending', 7, 'asd', 0, 'po', '2026-02-18 13:32:26'),
(1071, 204, 'approver', 'pending', 8, 'asd', 0, 'po', '2026-02-18 13:32:39'),
(1072, 204, 'warehouse_releasing', 'pending', 12, 'asdw', 0, 'po', '2026-02-18 13:32:53'),
(1073, 204, 'warehouse_releasing', 'pending', 10, 'Withdrawal slip processed', 0, 'po', '2026-02-18 13:34:48'),
(1074, 204, 'purchasing_final', 'pending', 10, 'asd', 0, 'po', '2026-02-18 13:35:02'),
(1075, 204, 'warehouse_receiving', 'pending', 7, 'asd', 0, 'po', '2026-02-18 13:35:14'),
(1076, 204, 'warehouse_receiving', 'pending', 10, 'asdw', 0, 'po', '2026-02-18 13:39:13'),
(1077, 205, 'warehouse', 'pending', 10, '', 0, 'po', '2026-02-18 14:27:16'),
(1078, 205, 'purchasing', 'pending', 10, 'sd', 0, 'po', '2026-02-18 14:27:21'),
(1079, 205, 'purchasing', 'pending', 7, 'Withdrawal Slip WS-2026-0010 created', 0, 'po', '2026-02-18 14:27:40'),
(1080, 205, 'purchasing', 'pending', 7, 'Purchase Order PO-2026-0027 created', 0, 'po', '2026-02-18 14:27:57'),
(1081, 206, 'warehouse', 'pending', 7, '', 0, 'po', '2026-02-18 14:31:01'),
(1082, 206, 'purchasing', 'pending', 10, 'ads', 0, 'po', '2026-02-18 14:31:25'),
(1083, 206, 'purchasing', 'pending', 7, 'Withdrawal Slip WS-2026-0011 created', 0, 'po', '2026-02-18 14:31:43'),
(1084, 207, 'warehouse', 'pending', 7, '', 0, 'po', '2026-02-18 14:54:17'),
(1085, 207, 'purchasing', 'pending', 10, 'asd', 0, 'po', '2026-02-18 14:54:34'),
(1086, 207, 'purchasing', 'pending', 7, 'Purchase Order PO-2026-0028 created', 0, 'po', '2026-02-18 14:54:55'),
(1087, 208, 'warehouse', 'pending', 7, '', 0, 'po', '2026-02-18 15:14:18'),
(1088, 208, 'purchasing', 'pending', 10, 'asd', 0, 'po', '2026-02-18 15:14:32'),
(1089, 208, 'purchasing', 'pending', 7, 'Withdrawal Slip WS-2026-0012 created', 0, 'po', '2026-02-18 15:14:45'),
(1090, 209, 'warehouse', 'pending', 7, '', 0, 'po', '2026-02-18 15:25:39'),
(1091, 209, 'purchasing', 'pending', 10, 'asd', 0, 'po', '2026-02-18 15:25:54'),
(1092, 209, 'purchasing', 'pending', 7, 'Withdrawal Slip WS-2026-0013 created', 0, 'po', '2026-02-18 15:28:01'),
(1093, 209, 'approver', 'pending', 7, 'asd', 0, 'po', '2026-02-18 15:28:07'),
(1094, 210, 'warehouse', 'pending', 7, '', 0, 'po', '2026-02-18 15:32:25'),
(1095, 210, 'purchasing', 'pending', 10, 'dasd', 0, 'po', '2026-02-18 15:33:04'),
(1096, 210, 'purchasing', 'pending', 7, 'Withdrawal Slip WS-2026-0014 created', 0, 'po', '2026-02-18 15:33:25'),
(1097, 210, 'purchasing', 'pending', 7, 'Purchase Order PO-2026-0029 created', 0, 'po', '2026-02-18 15:33:45'),
(1098, 210, 'accounting', 'pending', 7, 'we', 0, 'po', '2026-02-18 15:38:04'),
(1099, 209, 'warehouse_releasing', 'pending', 12, 'asd', 0, 'po', '2026-02-18 15:39:20'),
(1100, 209, 'warehouse_releasing', 'pending', 10, 'Withdrawal slip processed', 0, 'po', '2026-02-18 15:40:33'),
(1101, 211, 'warehouse', 'pending', 10, '', 0, 'po', '2026-02-19 01:26:39'),
(1102, 211, 'purchasing', 'pending', 10, 'asdw', 0, 'po', '2026-02-19 01:26:56'),
(1103, 211, 'purchasing', 'pending', 7, 'Withdrawal Slip WS-2026-0015 created', 0, 'po', '2026-02-19 01:27:11'),
(1104, 211, 'approver', 'pending', 7, 'asdw', 0, 'po', '2026-02-19 01:27:15'),
(1105, 211, 'warehouse_releasing', 'pending', 12, 'asdw', 0, 'po', '2026-02-19 01:27:28'),
(1106, 211, 'warehouse_releasing', 'pending', 10, 'Withdrawal slip processed', 0, 'po', '2026-02-19 01:28:20'),
(1107, 211, 'completed', 'pending', 10, 'asdw', 0, 'po', '2026-02-19 01:28:36'),
(1108, 212, 'warehouse', 'pending', 10, '', 0, 'po', '2026-02-19 07:11:18'),
(1109, 212, 'purchasing', 'pending', 10, 'asdw', 0, 'po', '2026-02-19 07:11:45'),
(1110, 212, 'purchasing', 'pending', 7, 'Withdrawal Slip WS-2026-0016 created', 0, 'po', '2026-02-19 07:12:08'),
(1111, 212, 'approver', 'pending', 7, 'asdw', 0, 'po', '2026-02-19 07:12:11'),
(1112, 212, 'warehouse_releasing', 'pending', 12, 'asd', 0, 'po', '2026-02-19 07:27:27'),
(1113, 212, 'warehouse_releasing', 'pending', 10, 'Withdrawal slip processed', 0, 'po', '2026-02-19 07:27:59'),
(1114, 213, 'warehouse', 'pending', 10, 'a', 0, 'po', '2026-02-19 07:31:44'),
(1115, 213, 'purchasing', 'pending', 10, 's', 0, 'po', '2026-02-19 07:31:47'),
(1116, 213, 'purchasing', 'pending', 7, 'Withdrawal Slip WS-2026-0017 created', 0, 'po', '2026-02-19 07:32:24'),
(1117, 213, 'approver', 'pending', 7, 'asdw', 0, 'po', '2026-02-19 07:32:28'),
(1118, 213, 'warehouse_releasing', 'pending', 12, 'asdw', 0, 'po', '2026-02-19 07:32:40'),
(1119, 213, 'warehouse_releasing', 'pending', 10, 'Withdrawal slip processed', 0, 'po', '2026-02-19 07:33:38'),
(1120, 213, 'completed', 'pending', 10, 'asdw', 0, 'po', '2026-02-19 07:34:01'),
(1121, 214, 'warehouse', 'pending', 10, '', 0, 'po', '2026-02-19 07:37:36'),
(1122, 214, 'purchasing', 'pending', 10, 'sasd', 0, 'po', '2026-02-19 07:37:40'),
(1123, 214, 'purchasing', 'pending', 7, 'Purchase Order PO-2026-0030 created', 0, 'po', '2026-02-19 07:38:03'),
(1124, 214, 'accounting', 'pending', 7, 'asd', 0, 'po', '2026-02-19 07:38:06'),
(1125, 214, 'approver', 'pending', 8, 'asdw', 0, 'po', '2026-02-19 07:40:30'),
(1126, 214, 'purchasing_final', 'pending', 12, 'asdw', 0, 'po', '2026-02-19 07:40:45'),
(1127, 214, 'warehouse_receiving', 'pending', 7, 'asdw', 0, 'po', '2026-02-19 07:41:37'),
(1128, 214, 'warehouse_receiving', 'pending', 10, 'asdw', 0, 'po', '2026-02-19 07:42:47'),
(1129, 214, 'purchasing_completion', 'pending', 10, 'sda', 0, 'po', '2026-02-19 07:45:25'),
(1130, 214, 'accounting_final', 'pending', 7, 'ad', 0, 'po', '2026-02-19 07:45:38'),
(1131, 214, 'completed', 'completed', 8, 'asdw', 0, 'po', '2026-02-19 07:46:02'),
(1132, 215, 'warehouse', 'pending', 8, '', 0, 'po', '2026-02-19 07:47:53'),
(1133, 215, 'purchasing', 'pending', 10, 'asdw', 0, 'po', '2026-02-19 07:48:06'),
(1134, 215, 'purchasing', 'pending', 7, 'Withdrawal Slip WS-2026-0018 created', 0, 'po', '2026-02-19 07:48:38'),
(1135, 215, 'purchasing', 'pending', 7, 'Purchase Order PO-2026-0031 created', 0, 'po', '2026-02-19 07:49:27'),
(1136, 216, 'warehouse', 'pending', 7, '', 0, 'po', '2026-02-19 07:50:12'),
(1137, 216, 'purchasing', 'pending', 10, 'asd', 0, 'po', '2026-02-19 07:50:24'),
(1138, 216, 'purchasing', 'pending', 7, 'Withdrawal Slip WS-2026-0019 created', 0, 'po', '2026-02-19 07:50:41'),
(1139, 216, 'purchasing', 'pending', 7, 'Purchase Order PO-2026-0032 created', 0, 'po', '2026-02-19 07:50:57'),
(1140, 216, 'accounting', 'pending', 7, 'asdw', 0, 'po', '2026-02-19 07:51:38'),
(1141, 217, 'warehouse', 'pending', 7, '', 0, 'po', '2026-02-19 07:57:43'),
(1142, 218, 'warehouse', 'pending', 10, '', 0, 'po', '2026-02-19 07:58:30'),
(1143, 218, 'purchasing', 'pending', 10, 'asd', 0, 'po', '2026-02-19 07:58:41'),
(1144, 218, 'purchasing', 'pending', 7, 'Purchase Order PO-2026-0033 created', 0, 'po', '2026-02-19 07:58:56'),
(1145, 218, 'accounting', 'pending', 7, 'asd', 0, 'po', '2026-02-19 07:58:59'),
(1146, 218, 'approver', 'pending', 8, 'asd', 0, 'po', '2026-02-19 07:59:11'),
(1147, 218, 'purchasing_final', 'pending', 12, 'asdw', 0, 'po', '2026-02-19 07:59:25'),
(1148, 218, 'warehouse_receiving', 'pending', 7, 'asdw', 0, 'po', '2026-02-19 08:00:12'),
(1149, 218, 'warehouse_receiving', 'pending', 10, 'asdw', 0, 'po', '2026-02-19 08:07:34'),
(1150, 218, 'purchasing_completion', 'pending', 10, 'jk', 0, 'po', '2026-02-19 08:23:23'),
(1151, 219, 'warehouse', 'pending', 10, '', 0, 'po', '2026-02-19 08:24:27'),
(1152, 219, 'purchasing', 'pending', 10, 'w', 0, 'po', '2026-02-19 08:24:30'),
(1153, 219, 'purchasing', 'pending', 7, 'Withdrawal Slip WS-2026-0020 created', 0, 'po', '2026-02-19 08:24:45'),
(1154, 219, 'purchasing', 'pending', 7, 'Purchase Order PO-2026-0034 created', 0, 'po', '2026-02-19 08:25:01'),
(1155, 219, 'accounting', 'pending', 7, 'asd', 0, 'po', '2026-02-19 09:28:26'),
(1156, 219, 'approver', 'pending', 8, 'asdw', 0, 'po', '2026-02-19 09:29:29'),
(1157, 219, 'warehouse_releasing', 'pending', 12, 'asd', 0, 'po', '2026-02-19 09:29:43'),
(1158, 219, 'warehouse_releasing', 'pending', 10, 'Withdrawal slip processed', 0, 'po', '2026-02-19 09:30:52'),
(1159, 219, 'purchasing_final', 'pending', 10, 'asd', 0, 'po', '2026-02-19 09:31:06'),
(1160, 219, 'warehouse_receiving', 'pending', 7, 'asd', 0, 'po', '2026-02-19 09:31:30'),
(1161, 219, 'warehouse_receiving', 'pending', 10, 'sd', 0, 'po', '2026-02-19 09:31:51'),
(1162, 219, 'purchasing_completion', 'pending', 10, 'asd', 0, 'po', '2026-02-19 09:32:54'),
(1163, 219, 'accounting_final', 'pending', 7, 'asdw', 0, 'po', '2026-02-19 09:33:07'),
(1164, 219, 'completed', 'completed', 8, 'asdw', 0, 'po', '2026-02-19 09:33:35'),
(1165, 221, 'warehouse', 'pending', 8, '', 0, 'po', '2026-02-19 09:47:10'),
(1166, 221, 'purchasing', 'pending', 10, 'ASD', 0, 'po', '2026-02-19 09:47:36'),
(1167, 221, 'purchasing', 'pending', 7, 'Purchase Order PO-2026-0035 created', 0, 'po', '2026-02-19 09:47:59'),
(1168, 221, 'accounting', 'pending', 7, 'asdw', 0, 'po', '2026-02-19 09:48:03'),
(1169, 221, 'approver', 'pending', 8, 'asd', 0, 'po', '2026-02-19 09:48:14'),
(1170, 221, 'purchasing_final', 'pending', 12, 'asd', 0, 'po', '2026-02-19 09:48:28'),
(1171, 222, 'warehouse', 'pending', 12, '', 0, 'po', '2026-02-19 10:01:54'),
(1172, 222, 'purchasing', 'pending', 10, 'asdw', 0, 'po', '2026-02-19 10:02:06'),
(1173, 222, 'purchasing', 'pending', 7, 'Purchase Order PO-2026-0036 created', 0, 'po', '2026-02-19 10:02:26'),
(1174, 222, 'accounting', 'pending', 7, 'asd', 0, 'po', '2026-02-19 10:02:29'),
(1175, 222, 'approver', 'pending', 8, 'ads', 0, 'po', '2026-02-19 10:02:44'),
(1176, 222, 'purchasing_final', 'pending', 12, 'asdw', 0, 'po', '2026-02-19 10:02:57'),
(1177, 222, 'warehouse_receiving', 'pending', 7, 'asdw', 0, 'po', '2026-02-19 10:03:24'),
(1178, 222, 'warehouse_receiving', 'pending', 10, 'asdw', 0, 'po', '2026-02-19 10:03:40'),
(1179, 222, 'purchasing_completion', 'pending', 10, 'kk', 0, 'po', '2026-02-19 10:10:49'),
(1180, 223, 'warehouse', 'pending', 10, 'asd', 0, 'po', '2026-02-19 10:11:39'),
(1181, 223, 'purchasing', 'pending', 10, 'asd', 0, 'po', '2026-02-19 10:11:42'),
(1182, 223, 'purchasing', 'pending', 7, 'Purchase Order PO-2026-0037 created', 0, 'po', '2026-02-19 10:11:58'),
(1183, 223, 'accounting', 'pending', 7, 'asd', 0, 'po', '2026-02-19 10:12:01'),
(1184, 223, 'approver', 'pending', 8, 'asd', 0, 'po', '2026-02-19 10:12:15'),
(1185, 223, 'purchasing_final', 'pending', 12, 'asd', 0, 'po', '2026-02-19 10:12:26'),
(1186, 223, 'warehouse_receiving', 'pending', 7, 'asd', 0, 'po', '2026-02-19 10:12:44'),
(1187, 223, 'warehouse_receiving', 'pending', 10, 'asdw', 0, 'po', '2026-02-19 10:13:21'),
(1188, 223, 'purchasing_completion', 'pending', 10, 'asd', 0, 'po', '2026-02-19 10:13:35'),
(1189, 223, 'accounting_final', 'pending', 7, 'asdw', 0, 'po', '2026-02-19 10:13:50'),
(1190, 223, 'completed', 'completed', 8, 'asd', 0, 'po', '2026-02-19 10:14:05'),
(1191, 224, 'warehouse', 'pending', 8, 'asdw', 0, 'po', '2026-02-19 10:14:24'),
(1192, 224, 'purchasing', 'pending', 10, 'asdw', 0, 'po', '2026-02-19 10:14:36'),
(1193, 224, 'purchasing', 'pending', 7, 'Withdrawal Slip WS-2026-0021 created', 0, 'po', '2026-02-19 10:15:05'),
(1194, 224, 'approver', 'pending', 7, 'asd', 0, 'po', '2026-02-19 10:15:09'),
(1195, 224, 'warehouse_releasing', 'pending', 12, 'asdw', 0, 'po', '2026-02-19 10:15:23'),
(1196, 224, 'warehouse_releasing', 'pending', 10, 'Withdrawal slip processed', 0, 'po', '2026-02-19 10:15:58'),
(1197, 224, 'completed', 'pending', 10, 'asd', 0, 'po', '2026-02-19 10:16:10'),
(1198, 232, 'warehouse', 'pending', 7, '', 0, 'po', '2026-02-27 14:01:17'),
(1199, 232, 'purchasing', 'pending', 10, 'asd', 0, 'po', '2026-02-27 14:02:14'),
(1200, 232, 'purchasing', 'pending', 7, 'Purchase Order PO-2026-0038 created', 0, 'po', '2026-02-27 14:02:39'),
(1201, 230, 'warehouse', 'pending', 7, '', 0, 'po', '2026-02-27 14:06:16'),
(1202, 230, 'purchasing', 'pending', 10, 'sad', 0, 'po', '2026-02-27 14:06:34'),
(1203, 230, 'purchasing', 'pending', 7, 'Purchase Order PO-2026-0039 created', 0, 'po', '2026-02-27 14:07:10'),
(1204, 233, 'warehouse', 'pending', 7, '', 0, 'po', '2026-02-27 14:09:22'),
(1205, 233, 'purchasing', 'pending', 10, 'asd', 0, 'po', '2026-02-27 14:09:37'),
(1206, 233, 'purchasing', 'pending', 7, 'Purchase Order 000001 created', 0, 'po', '2026-02-27 14:10:12'),
(1207, 234, 'warehouse', 'pending', 7, '', 0, 'po', '2026-02-27 14:11:00'),
(1208, 234, 'purchasing', 'pending', 10, 'asdw', 0, 'po', '2026-02-27 14:11:17'),
(1209, 234, 'purchasing', 'pending', 7, 'Purchase Order 000002 created', 0, 'po', '2026-02-27 14:11:33'),
(1210, 235, 'warehouse', 'pending', 10, '', 0, 'po', '2026-03-27 03:17:23'),
(1211, 235, 'purchasing', 'pending', 10, 'ASD', 0, 'po', '2026-03-27 03:17:31'),
(1212, 236, 'warehouse', 'pending', 12, '', 0, 'po', '2026-04-15 07:16:23'),
(1213, 236, 'purchasing', 'pending', 10, 'asdw', 0, 'po', '2026-04-15 07:17:47'),
(1214, 236, 'purchasing', 'pending', 7, 'Withdrawal Slip WS-2026-0022 created', 0, 'po', '2026-04-15 07:18:44'),
(1215, 236, 'approver', 'pending', 7, 'Okay', 0, 'po', '2026-04-15 07:18:52'),
(1216, 236, 'warehouse_releasing', 'pending', 12, 'asdw', 0, 'po', '2026-04-15 07:23:52'),
(1217, 236, 'warehouse_releasing', 'pending', 10, 'Withdrawal slip processed', 0, 'po', '2026-04-15 07:24:57'),
(1218, 236, 'completed', 'pending', 10, 'asd', 0, 'po', '2026-04-15 07:25:34'),
(1225, 239, 'warehouse', 'pending', 15, '', 0, 'po', '2026-10-01 06:05:06'),
(1229, 239, 'purchasing', 'pending', 10, 'asd', 0, 'po', '2026-10-01 09:59:17'),
(1230, 239, 'purchasing', 'pending', 7, 'Withdrawal Slip WS-2026-0023 created', 0, 'po', '2026-10-01 10:00:04'),
(1249, 248, 'warehouse', 'pending', 1, 'why', 0, 'po', '2026-10-01 12:30:41'),
(1250, 248, 'purchasing', 'pending', 10, 'why', 0, 'po', '2026-10-01 12:30:41'),
(1282, 239, 'approver', 'pending', 7, 'asdw', 0, 'po', '2026-10-01 12:41:39'),
(1287, 239, 'warehouse_releasing', 'pending', 15, 'asd', 0, 'po', '2026-10-01 12:42:34'),
(1290, 239, 'warehouse_releasing', 'pending', 10, 'Withdrawal slip processed', 0, 'po', '2026-10-01 12:43:18'),
(1303, 269, 'warehouse', 'pending', 10, '', 0, 'po', '2026-10-01 12:50:47'),
(1311, 269, 'purchasing', 'pending', 10, 'asda', 0, 'po', '2026-10-01 12:51:32'),
(1312, 269, 'purchasing', 'pending', 7, 'Withdrawal Slip WS-2026-0024 created', 0, 'po', '2026-10-01 12:52:01'),
(1313, 269, 'approver', 'pending', 7, 'asd', 0, 'po', '2026-10-01 12:52:10'),
(1314, 269, 'warehouse_releasing', 'pending', 15, 'asd', 0, 'po', '2026-10-01 12:52:40'),
(1315, 269, 'warehouse_releasing', 'pending', 10, 'Withdrawal slip processed', 0, 'po', '2026-10-01 12:53:19'),
(1344, 275, 'warehouse', 'pending', 15, '', 0, 'po', '2026-10-01 14:07:18'),
(1345, 275, 'purchasing', 'pending', 10, 'asd', 0, 'po', '2026-10-01 14:07:35');

-- --------------------------------------------------------

--
-- Table structure for table `pr_routing_history`
--

CREATE TABLE `pr_routing_history` (
  `id` int NOT NULL,
  `pr_id` int NOT NULL,
  `action` varchar(100) NOT NULL,
  `remarks` text,
  `simplified_flow` tinyint(1) DEFAULT '0',
  `document_type` enum('po','ws') DEFAULT 'po',
  `action_by` int NOT NULL,
  `stage_from` varchar(50) DEFAULT NULL,
  `stage_to` varchar(50) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `pr_routing_history`
--

INSERT INTO `pr_routing_history` (`id`, `pr_id`, `action`, `remarks`, `simplified_flow`, `document_type`, `action_by`, `stage_from`, `stage_to`, `created_at`) VALUES
(933, 163, 'Forwarded to Warehouse', 'asd', 0, 'po', 12, 'requestor', 'warehouse', '2026-02-16 09:28:50'),
(934, 163, 'Approved by Warehouse', 'sd', 0, 'po', 10, 'warehouse', 'purchasing', '2026-02-16 09:56:30'),
(935, 163, 'Withdrawal Slip Created', 'Withdrawal Slip WS-2026-0001 created with 1 items', 0, 'po', 7, 'purchasing', 'purchasing', '2026-02-16 09:56:49'),
(936, 163, 'Approved by Purchasing', 'asdw', 0, 'po', 7, 'purchasing', 'approver', '2026-02-16 09:56:55'),
(937, 163, 'Approved by Approver (CEO)', 'sdw', 0, 'po', 12, 'approver', 'warehouse_releasing', '2026-02-16 09:59:21'),
(938, 164, 'Forwarded to Warehouse', '', 0, 'po', 12, 'requestor', 'warehouse', '2026-02-17 01:39:13'),
(939, 164, 'Approved by Warehouse', 'sd', 0, 'po', 10, 'warehouse', 'purchasing', '2026-02-17 01:39:29'),
(940, 164, 'Withdrawal Slip Created', 'Withdrawal Slip WS-2026-0002 created with 1 items', 0, 'po', 7, 'purchasing', 'purchasing', '2026-02-17 01:40:25'),
(941, 164, 'Purchase Order Created', 'Purchase Order PO-2026-0001 created with 1 items', 0, 'po', 7, 'purchasing', 'purchasing', '2026-02-17 01:40:51'),
(942, 166, 'Forwarded to Warehouse', '', 0, 'po', 7, 'requestor', 'warehouse', '2026-02-17 01:59:15'),
(943, 166, 'Approved by Warehouse', 'as', 0, 'po', 10, 'warehouse', 'purchasing', '2026-02-17 12:45:12'),
(944, 167, 'Forwarded to Warehouse', '', 0, 'po', 7, 'requestor', 'warehouse', '2026-02-17 13:03:17'),
(945, 167, 'Approved by Warehouse', 'asd', 0, 'po', 10, 'warehouse', 'purchasing', '2026-02-17 13:03:29'),
(946, 167, 'Purchase Order Created', 'Purchase Order PO-2026-0002 created with 1 items', 0, 'po', 7, 'purchasing', 'purchasing', '2026-02-17 13:40:46'),
(947, 167, 'Approved by Purchasing', 'sdas', 0, 'po', 7, 'purchasing', 'accounting', '2026-02-17 13:49:25'),
(948, 167, 'Approved by Accounting', 'sad', 0, 'po', 8, 'accounting', 'approver', '2026-02-17 13:49:49'),
(949, 167, 'Approved by Approver (CEO)', 'asd', 0, 'po', 12, 'approver', 'purchasing_final', '2026-02-17 13:50:03'),
(950, 167, 'Approved by Purchasing (Final)', 'asd', 0, 'po', 7, 'purchasing_final', 'warehouse_receiving', '2026-02-17 13:51:20'),
(951, 167, 'Items Received', 'asd', 0, 'po', 10, 'warehouse_receiving', 'warehouse_receiving', '2026-02-17 13:54:01'),
(952, 167, 'Completed Warehouse Receiving', 'asdw', 0, 'po', 10, 'warehouse_receiving', 'purchasing_completion', '2026-02-17 13:59:25'),
(953, 168, 'Forwarded to Warehouse', '', 0, 'po', 8, 'requestor', 'warehouse', '2026-02-18 01:50:06'),
(954, 168, 'Approved by Warehouse', 'asd', 0, 'po', 10, 'warehouse', 'purchasing', '2026-02-18 02:01:01'),
(955, 168, 'Purchase Order Created', 'Purchase Order PO-2026-0003 created with 1 items', 0, 'po', 7, 'purchasing', 'purchasing', '2026-02-18 02:14:15'),
(956, 169, 'Forwarded to Warehouse', '', 0, 'po', 10, 'requestor', 'warehouse', '2026-02-18 02:30:20'),
(957, 169, 'Approved by Warehouse', 'asdw', 0, 'po', 10, 'warehouse', 'purchasing', '2026-02-18 02:30:24'),
(958, 169, 'Purchase Order Created', 'Purchase Order PO-2026-0004 created with 1 items', 0, 'po', 7, 'purchasing', 'purchasing', '2026-02-18 02:46:18'),
(959, 170, 'Forwarded to Warehouse', '', 0, 'po', 10, 'requestor', 'warehouse', '2026-02-18 02:52:30'),
(960, 170, 'Approved by Warehouse', 'asd', 0, 'po', 10, 'warehouse', 'purchasing', '2026-02-18 02:52:33'),
(961, 170, 'Purchase Order Created', 'Purchase Order PO-2026-0005 created with 1 items. PR updated with supplier and total cost.', 0, 'po', 7, 'purchasing', 'purchasing', '2026-02-18 02:53:23'),
(962, 170, 'Approved by Purchasing', 'ad', 0, 'po', 7, 'purchasing', 'accounting', '2026-02-18 02:54:10'),
(963, 170, 'Approved by Accounting', 'asd', 0, 'po', 8, 'accounting', 'approver', '2026-02-18 02:54:21'),
(964, 170, 'Approved by Approver (CEO)', 'asdw', 0, 'po', 12, 'approver', 'purchasing_final', '2026-02-18 03:01:25'),
(965, 170, 'Approved by Purchasing (Final)', 'asd', 0, 'po', 7, 'purchasing_final', 'warehouse_receiving', '2026-02-18 03:05:12'),
(966, 170, 'Items Received', 'sdw', 0, 'po', 10, 'warehouse_receiving', 'warehouse_receiving', '2026-02-18 03:20:26'),
(967, 171, 'Forwarded to Warehouse', '', 0, 'po', 10, 'requestor', 'warehouse', '2026-02-18 03:39:22'),
(968, 171, 'Approved by Warehouse', 'asd', 0, 'po', 10, 'warehouse', 'purchasing', '2026-02-18 03:39:26'),
(969, 171, 'Purchase Order Created', 'Purchase Order PO-2026-0006 created with 1 items. PR updated with supplier and total cost.', 0, 'po', 7, 'purchasing', 'purchasing', '2026-02-18 03:39:54'),
(970, 171, 'Approved by Purchasing', 'asd', 0, 'po', 7, 'purchasing', 'accounting', '2026-02-18 03:39:58'),
(971, 171, 'Approved by Accounting', 'adsw', 0, 'po', 8, 'accounting', 'approver', '2026-02-18 03:40:10'),
(972, 171, 'Approved by Approver (CEO)', 'asdw', 0, 'po', 12, 'approver', 'purchasing_final', '2026-02-18 03:41:04'),
(973, 171, 'Approved by Purchasing (Final)', 'adw', 0, 'po', 7, 'purchasing_final', 'warehouse_receiving', '2026-02-18 03:41:36'),
(974, 171, 'Items Received', 'asdw', 0, 'po', 10, 'warehouse_receiving', 'warehouse_receiving', '2026-02-18 03:42:14'),
(975, 170, 'Completed Warehouse Receiving', 'asd', 0, 'po', 10, 'warehouse_receiving', 'purchasing_completion', '2026-02-18 03:44:07'),
(976, 172, 'Forwarded to Warehouse', '', 0, 'po', 10, 'requestor', 'warehouse', '2026-02-18 03:44:41'),
(977, 172, 'Approved by Warehouse', 'asd', 0, 'po', 10, 'warehouse', 'purchasing', '2026-02-18 03:44:44'),
(978, 172, 'Purchase Order Created', 'Purchase Order PO-2026-0007 created with 1 items. PR updated with supplier and total cost.', 0, 'po', 7, 'purchasing', 'purchasing', '2026-02-18 03:45:06'),
(979, 172, 'Approved by Purchasing', 'dasd', 0, 'po', 7, 'purchasing', 'accounting', '2026-02-18 03:45:11'),
(980, 172, 'Approved by Accounting', 'asd', 0, 'po', 8, 'accounting', 'approver', '2026-02-18 03:45:23'),
(981, 172, 'Approved by Approver (CEO)', 'asdw', 0, 'po', 12, 'approver', 'purchasing_final', '2026-02-18 03:45:36'),
(982, 172, 'Approved by Purchasing (Final)', 'asd', 0, 'po', 7, 'purchasing_final', 'warehouse_receiving', '2026-02-18 03:45:47'),
(983, 172, 'Items Received', 'asdw', 0, 'po', 10, 'warehouse_receiving', 'warehouse_receiving', '2026-02-18 03:46:01'),
(984, 173, 'Forwarded to Warehouse', '', 0, 'po', 10, 'requestor', 'warehouse', '2026-02-18 05:12:33'),
(985, 173, 'Approved by Warehouse', 'wdasd', 0, 'po', 10, 'warehouse', 'purchasing', '2026-02-18 05:12:37'),
(986, 169, 'Rejected by Purchasing', 'zdss', 0, 'po', 7, 'purchasing', 'rejected', '2026-02-18 05:13:31'),
(987, 174, 'Forwarded to Warehouse', '', 0, 'po', 10, 'requestor', 'warehouse', '2026-02-18 05:14:57'),
(988, 174, 'Approved by Warehouse', 'wa', 0, 'po', 10, 'warehouse', 'purchasing', '2026-02-18 05:15:01'),
(989, 174, 'Purchase Order Created', 'Purchase Order PO-2026-0008 created with 1 items. PR updated with supplier and total cost.', 0, 'po', 7, 'purchasing', 'purchasing', '2026-02-18 05:15:25'),
(990, 174, 'Approved by Purchasing', 'asd', 0, 'po', 7, 'purchasing', 'accounting', '2026-02-18 05:15:31'),
(991, 174, 'Approved by Accounting', 'asdw', 0, 'po', 8, 'accounting', 'approver', '2026-02-18 05:15:43'),
(992, 174, 'Approved by Approver (CEO)', 'asdw', 0, 'po', 12, 'approver', 'purchasing_final', '2026-02-18 05:15:55'),
(993, 174, 'Approved by Purchasing (Final)', 'asdw', 0, 'po', 7, 'purchasing_final', 'warehouse_receiving', '2026-02-18 05:16:26'),
(994, 174, 'Items Received', 'sdw', 0, 'po', 10, 'warehouse_receiving', 'warehouse_receiving', '2026-02-18 05:36:40'),
(995, 175, 'Forwarded to Warehouse', '', 0, 'po', 10, 'requestor', 'warehouse', '2026-02-18 05:48:49'),
(996, 175, 'Approved by Warehouse', 'wad', 0, 'po', 10, 'warehouse', 'purchasing', '2026-02-18 05:48:52'),
(997, 175, 'Purchase Order Created', 'Purchase Order PO-2026-0009 created with 1 items. PR updated with supplier and total cost.', 0, 'po', 7, 'purchasing', 'purchasing', '2026-02-18 05:49:12'),
(998, 175, 'Approved by Purchasing', 'asdw', 0, 'po', 7, 'purchasing', 'accounting', '2026-02-18 05:49:15'),
(999, 175, 'Approved by Accounting', 'asdw', 0, 'po', 8, 'accounting', 'approver', '2026-02-18 05:49:37'),
(1000, 175, 'Approved by Approver (CEO)', 'asd', 0, 'po', 12, 'approver', 'purchasing_final', '2026-02-18 05:49:55'),
(1001, 175, 'Approved by Purchasing (Final)', 'asdw', 0, 'po', 7, 'purchasing_final', 'warehouse_receiving', '2026-02-18 05:50:06'),
(1002, 175, 'Items Received', 'asdw', 0, 'po', 10, 'warehouse_receiving', 'warehouse_receiving', '2026-02-18 05:50:38'),
(1003, 176, 'Forwarded to Warehouse', '', 0, 'po', 10, 'requestor', 'warehouse', '2026-02-18 05:55:38'),
(1004, 176, 'Approved by Warehouse', 'asd', 0, 'po', 10, 'warehouse', 'purchasing', '2026-02-18 05:55:41'),
(1005, 176, 'Purchase Order Created', 'Purchase Order PO-2026-0010 created with 1 items. PR updated with supplier and total cost.', 0, 'po', 7, 'purchasing', 'purchasing', '2026-02-18 05:55:54'),
(1006, 176, 'Approved by Purchasing', 'asd', 0, 'po', 7, 'purchasing', 'accounting', '2026-02-18 05:55:58'),
(1007, 176, 'Approved by Accounting', 'asd', 0, 'po', 8, 'accounting', 'approver', '2026-02-18 05:56:12'),
(1008, 176, 'Approved by Approver (CEO)', 'asdw', 0, 'po', 12, 'approver', 'purchasing_final', '2026-02-18 05:56:24'),
(1009, 176, 'Approved by Purchasing (Final)', 'asdw', 0, 'po', 7, 'purchasing_final', 'warehouse_receiving', '2026-02-18 05:56:37'),
(1010, 176, 'Items Received', 'asdw Total cost added: ₱25.00', 0, 'po', 10, 'warehouse_receiving', 'warehouse_receiving', '2026-02-18 05:58:33'),
(1011, 177, 'Forwarded to Warehouse', '', 0, 'po', 10, 'requestor', 'warehouse', '2026-02-18 06:01:18'),
(1012, 177, 'Approved by Warehouse', 'asdw', 0, 'po', 10, 'warehouse', 'purchasing', '2026-02-18 06:01:21'),
(1013, 177, 'Purchase Order Created', 'Purchase Order PO-2026-0011 created with 1 items. PR updated with supplier and total cost.', 0, 'po', 7, 'purchasing', 'purchasing', '2026-02-18 06:01:38'),
(1014, 177, 'Approved by Purchasing', 'asdw', 0, 'po', 7, 'purchasing', 'accounting', '2026-02-18 06:01:42'),
(1015, 177, 'Approved by Accounting', 'asd', 0, 'po', 8, 'accounting', 'approver', '2026-02-18 06:01:55'),
(1016, 177, 'Approved by Approver (CEO)', 'asdw', 0, 'po', 12, 'approver', 'purchasing_final', '2026-02-18 06:02:06'),
(1017, 177, 'Approved by Purchasing (Final)', 'asdw', 0, 'po', 7, 'purchasing_final', 'warehouse_receiving', '2026-02-18 06:03:16'),
(1018, 177, 'Items Received', 'asdw Receipt total: ₱10.00', 0, 'po', 10, 'warehouse_receiving', 'warehouse_receiving', '2026-02-18 06:04:52'),
(1019, 177, 'Completed Warehouse Receiving', 'asdw', 0, 'po', 10, 'warehouse_receiving', 'purchasing_completion', '2026-02-18 06:15:14'),
(1020, 178, 'Forwarded to Warehouse', '', 0, 'po', 10, 'requestor', 'warehouse', '2026-02-18 06:20:56'),
(1021, 178, 'Approved by Warehouse', 'asd', 0, 'po', 10, 'warehouse', 'purchasing', '2026-02-18 06:21:00'),
(1022, 178, 'Purchase Order Created', 'Purchase Order PO-2026-0012 created with 1 items. PR updated with supplier and total cost.', 0, 'po', 7, 'purchasing', 'purchasing', '2026-02-18 06:21:51'),
(1023, 178, 'Approved by Purchasing', 'asdw', 0, 'po', 7, 'purchasing', 'accounting', '2026-02-18 06:21:54'),
(1024, 178, 'Approved by Accounting', 'asdw', 0, 'po', 8, 'accounting', 'approver', '2026-02-18 06:22:07'),
(1025, 178, 'Approved by Approver (CEO)', 'sdw', 0, 'po', 12, 'approver', 'purchasing_final', '2026-02-18 06:22:23'),
(1026, 178, 'Approved by Purchasing (Final)', 'asdw', 0, 'po', 7, 'purchasing_final', 'warehouse_receiving', '2026-02-18 06:28:16'),
(1027, 178, 'Items Received', 'asdw', 0, 'po', 10, 'warehouse_receiving', 'warehouse_receiving', '2026-02-18 06:31:04'),
(1028, 178, 'Completed Warehouse Receiving', 'asdw', 0, 'po', 10, 'warehouse_receiving', 'purchasing_completion', '2026-02-18 06:34:09'),
(1029, 178, 'Completed by Purchasing', 'adw', 0, 'po', 7, 'purchasing_completion', 'accounting_final', '2026-02-18 06:35:08'),
(1030, 178, 'Finalized by Accounting', 'adw', 0, 'po', 8, 'accounting_final', 'completed', '2026-02-18 06:35:22'),
(1031, 182, 'Forwarded to Warehouse', '', 0, 'po', 8, 'requestor', 'warehouse', '2026-02-18 07:44:51'),
(1032, 182, 'Approved by Warehouse', 'asdw', 0, 'po', 10, 'warehouse', 'purchasing', '2026-02-18 07:45:04'),
(1033, 182, 'Withdrawal Slip Created', 'Withdrawal Slip WS-2026-0003 created with 2 items', 0, 'po', 7, 'purchasing', 'purchasing', '2026-02-18 07:45:36'),
(1034, 182, 'Purchase Order Created', 'Purchase Order PO-2026-0013 created with 1 items', 0, 'po', 7, 'purchasing', 'purchasing', '2026-02-18 07:46:03'),
(1035, 186, 'Forwarded to Warehouse', '', 0, 'po', 7, 'requestor', 'warehouse', '2026-02-18 08:25:04'),
(1036, 186, 'Approved by Warehouse', 'asdw', 0, 'po', 10, 'warehouse', 'purchasing', '2026-02-18 08:25:20'),
(1037, 186, 'Withdrawal Slip Created', 'Withdrawal Slip WS-2026-0004 created with 2 items', 0, 'po', 7, 'purchasing', 'purchasing', '2026-02-18 08:25:40'),
(1038, 186, 'Purchase Order Created', 'Purchase Order PO-2026-0014 created with 1 items', 0, 'po', 7, 'purchasing', 'purchasing', '2026-02-18 08:25:50'),
(1039, 189, 'Forwarded to Warehouse', '', 0, 'po', 7, 'requestor', 'warehouse', '2026-02-18 08:43:40'),
(1040, 189, 'Approved by Warehouse', 'sda', 0, 'po', 10, 'warehouse', 'purchasing', '2026-02-18 08:48:34'),
(1041, 189, 'Purchase Order Created', 'Purchase Order PO-2026-0015 created with 1 items', 0, 'po', 7, 'purchasing', 'purchasing', '2026-02-18 08:54:22'),
(1042, 190, 'Forwarded to Warehouse', '', 0, 'po', 7, 'requestor', 'warehouse', '2026-02-18 09:01:49'),
(1043, 191, 'Forwarded to Warehouse', '', 0, 'po', 7, 'requestor', 'warehouse', '2026-02-18 09:02:20'),
(1044, 191, 'Approved by Warehouse', 'asd', 0, 'po', 10, 'warehouse', 'purchasing', '2026-02-18 09:02:32'),
(1045, 190, 'Approved by Warehouse', 'asdw', 0, 'po', 10, 'warehouse', 'purchasing', '2026-02-18 09:02:41'),
(1046, 191, 'Purchase Order Created', 'Purchase Order PO-2026-0016 created with 1 items. PR items updated with unit cost and total cost.', 0, 'po', 7, 'purchasing', 'purchasing', '2026-02-18 09:03:21'),
(1047, 192, 'Forwarded to Warehouse', '', 0, 'po', 7, 'requestor', 'warehouse', '2026-02-18 09:04:32'),
(1048, 192, 'Approved by Warehouse', 'adw', 0, 'po', 10, 'warehouse', 'purchasing', '2026-02-18 09:04:46'),
(1049, 192, 'Purchase Order Created', 'Purchase Order PO-2026-0017 created with 1 items. PR updated with supplier and total cost.', 0, 'po', 7, 'purchasing', 'purchasing', '2026-02-18 09:05:14'),
(1050, 192, 'Approved by Purchasing', 'asdw', 0, 'po', 7, 'purchasing', 'accounting', '2026-02-18 09:05:51'),
(1051, 191, 'Approved by Purchasing', 'asd', 0, 'po', 7, 'purchasing', 'accounting', '2026-02-18 09:05:58'),
(1052, 192, 'Approved by Accounting', 'asdw', 0, 'po', 8, 'accounting', 'approver', '2026-02-18 09:06:15'),
(1053, 191, 'Approved by Accounting', 'sdw', 0, 'po', 8, 'accounting', 'approver', '2026-02-18 09:06:22'),
(1054, 192, 'Approved by Approver (CEO)', 'asdw', 0, 'po', 12, 'approver', 'purchasing_final', '2026-02-18 09:06:45'),
(1055, 191, 'Approved by Approver (CEO)', 'asdw', 0, 'po', 12, 'approver', 'purchasing_final', '2026-02-18 09:07:52'),
(1056, 192, 'Approved by Purchasing (Final)', 'asdw', 0, 'po', 7, 'purchasing_final', 'warehouse_receiving', '2026-02-18 09:08:08'),
(1057, 191, 'Approved by Purchasing (Final)', 'asdw', 0, 'po', 7, 'purchasing_final', 'warehouse_receiving', '2026-02-18 09:08:15'),
(1058, 192, 'Items Received', 'zsadw', 0, 'po', 10, 'warehouse_receiving', 'warehouse_receiving', '2026-02-18 09:09:57'),
(1059, 192, 'Completed Warehouse Receiving', 'asdw', 0, 'po', 10, 'warehouse_receiving', 'purchasing_completion', '2026-02-18 09:10:27'),
(1060, 191, 'Items Received', 'asdw', 0, 'po', 10, 'warehouse_receiving', 'warehouse_receiving', '2026-02-18 09:12:48'),
(1061, 193, 'Forwarded to Warehouse', '', 0, 'po', 10, 'requestor', 'warehouse', '2026-02-18 09:44:31'),
(1062, 193, 'Approved by Warehouse', 'asdw', 0, 'po', 10, 'warehouse', 'purchasing', '2026-02-18 09:44:35'),
(1063, 193, 'Withdrawal Slip Created', 'Withdrawal Slip WS-2026-0005 created with 1 items', 0, 'po', 7, 'purchasing', 'purchasing', '2026-02-18 09:44:59'),
(1064, 193, 'Approved by Purchasing', 'asd', 0, 'po', 7, 'purchasing', 'approver', '2026-02-18 09:45:03'),
(1065, 193, 'Approved by Approver (CEO)', 'asdw', 0, 'po', 12, 'approver', 'warehouse_releasing', '2026-02-18 09:45:25'),
(1066, 193, 'Withdrawal Slip Processed', 'Withdrawal slip processed. Total withdrawn: ₱303.00. ₱303.00 deducted from project threshold.', 0, 'po', 10, 'warehouse_releasing', 'warehouse_releasing', '2026-02-18 09:45:53'),
(1067, 193, 'Completed Warehouse Releasing', 'asdw', 0, 'po', 10, 'warehouse_releasing', 'completed', '2026-02-18 09:46:04'),
(1068, 191, 'Completed Warehouse Receiving', 'adw', 0, 'po', 10, 'warehouse_receiving', 'purchasing_completion', '2026-02-18 09:46:49'),
(1069, 194, 'Forwarded to Warehouse', '', 0, 'po', 10, 'requestor', 'warehouse', '2026-02-18 09:47:07'),
(1070, 194, 'Approved by Warehouse', 'dwd', 0, 'po', 10, 'warehouse', 'purchasing', '2026-02-18 09:47:10'),
(1071, 194, 'Purchase Order Created', 'Purchase Order PO-2026-0018 created with 1 items. PR items updated with unit cost and total cost.', 0, 'po', 7, 'purchasing', 'purchasing', '2026-02-18 09:47:37'),
(1072, 194, 'Approved by Purchasing', 'asd', 0, 'po', 7, 'purchasing', 'accounting', '2026-02-18 09:47:43'),
(1073, 194, 'Approved by Accounting', 'asdw', 0, 'po', 8, 'accounting', 'approver', '2026-02-18 09:48:05'),
(1074, 194, 'Approved by Approver (CEO)', 'adw', 0, 'po', 12, 'approver', 'purchasing_final', '2026-02-18 09:48:29'),
(1075, 194, 'Approved by Purchasing (Final)', 'adw', 0, 'po', 7, 'purchasing_final', 'warehouse_receiving', '2026-02-18 09:48:52'),
(1076, 194, 'Items Received', 'asdw', 0, 'po', 10, 'warehouse_receiving', 'warehouse_receiving', '2026-02-18 09:50:12'),
(1077, 195, 'Forwarded to Warehouse', '', 0, 'po', 10, 'requestor', 'warehouse', '2026-02-18 10:43:48'),
(1078, 195, 'Approved by Warehouse', 'adw', 0, 'po', 10, 'warehouse', 'purchasing', '2026-02-18 10:43:52'),
(1079, 195, 'Purchase Order Created', 'Purchase Order PO-2026-0019 created with 1 items. PR items updated with unit cost and total cost.', 0, 'po', 7, 'purchasing', 'purchasing', '2026-02-18 10:44:15'),
(1080, 195, 'Approved by Purchasing', 'asd', 0, 'po', 7, 'purchasing', 'accounting', '2026-02-18 10:44:19'),
(1081, 195, 'Approved by Accounting', 'asdw', 0, 'po', 8, 'accounting', 'approver', '2026-02-18 10:44:30'),
(1082, 195, 'Approved by Approver (CEO)', 'asdw', 0, 'po', 12, 'approver', 'purchasing_final', '2026-02-18 10:44:48'),
(1083, 195, 'Approved by Purchasing (Final)', 'dadw', 0, 'po', 7, 'purchasing_final', 'warehouse_receiving', '2026-02-18 10:45:01'),
(1084, 195, 'Items Received', 'asdw', 0, 'po', 10, 'warehouse_receiving', 'warehouse_receiving', '2026-02-18 10:45:22'),
(1085, 195, 'Completed Warehouse Receiving', 'adw', 0, 'po', 10, 'warehouse_receiving', 'purchasing_completion', '2026-02-18 10:46:06'),
(1086, 196, 'Forwarded to Warehouse', 'asdw', 0, 'po', 10, 'requestor', 'warehouse', '2026-02-18 10:46:29'),
(1087, 196, 'Approved by Warehouse', 'asdw', 0, 'po', 10, 'warehouse', 'purchasing', '2026-02-18 10:46:32'),
(1088, 196, 'Purchase Order Created', 'Purchase Order PO-2026-0020 created with 1 items. PR updated with supplier and total cost.', 0, 'po', 7, 'purchasing', 'purchasing', '2026-02-18 10:46:48'),
(1089, 196, 'Approved by Purchasing', 'sdw', 0, 'po', 7, 'purchasing', 'accounting', '2026-02-18 10:46:51'),
(1090, 196, 'Approved by Accounting', 'asdw', 0, 'po', 8, 'accounting', 'approver', '2026-02-18 10:47:03'),
(1091, 196, 'Approved by Approver (CEO)', 'adw', 0, 'po', 12, 'approver', 'purchasing_final', '2026-02-18 10:47:16'),
(1092, 196, 'Approved by Purchasing (Final)', 'asdw', 0, 'po', 7, 'purchasing_final', 'warehouse_receiving', '2026-02-18 10:47:44'),
(1093, 196, 'Items Received', 'asdw', 0, 'po', 10, 'warehouse_receiving', 'warehouse_receiving', '2026-02-18 10:48:00'),
(1094, 196, 'Completed Warehouse Receiving', 'sdw', 0, 'po', 10, 'warehouse_receiving', 'purchasing_completion', '2026-02-18 10:48:12'),
(1095, 198, 'Forwarded to Warehouse', '', 0, 'po', 10, 'requestor', 'warehouse', '2026-02-18 11:14:00'),
(1096, 198, 'Approved by Warehouse', 'sd', 0, 'po', 10, 'warehouse', 'purchasing', '2026-02-18 11:14:03'),
(1097, 198, 'Purchase Order Created', 'Purchase Order PO-2026-0021 created with 1 items. PR items updated with unit cost and total cost.', 0, 'po', 7, 'purchasing', 'purchasing', '2026-02-18 11:14:22'),
(1098, 198, 'Approved by Purchasing', 'asd', 0, 'po', 7, 'purchasing', 'accounting', '2026-02-18 11:14:26'),
(1099, 198, 'Approved by Accounting', 'adw', 0, 'po', 8, 'accounting', 'approver', '2026-02-18 11:14:39'),
(1100, 198, 'Approved by Approver (CEO)', 'asdw', 0, 'po', 12, 'approver', 'purchasing_final', '2026-02-18 11:14:51'),
(1101, 198, 'Approved by Purchasing (Final)', 'sadw', 0, 'po', 7, 'purchasing_final', 'warehouse_receiving', '2026-02-18 11:15:32'),
(1102, 198, 'Items Received', 'sdw', 0, 'po', 10, 'warehouse_receiving', 'warehouse_receiving', '2026-02-18 11:22:05'),
(1103, 198, 'Completed Warehouse Receiving', 'sdw', 0, 'po', 10, 'warehouse_receiving', 'purchasing_completion', '2026-02-18 11:22:47'),
(1104, 194, 'Completed Warehouse Receiving', 'asdw', 0, 'po', 10, 'warehouse_receiving', 'purchasing_completion', '2026-02-18 11:26:21'),
(1105, 199, 'Forwarded to Warehouse', 'ad', 0, 'po', 10, 'requestor', 'warehouse', '2026-02-18 12:34:01'),
(1106, 199, 'Approved by Warehouse', 'asd', 0, 'po', 10, 'warehouse', 'purchasing', '2026-02-18 12:34:04'),
(1107, 199, 'Purchase Order Created', 'Purchase Order PO-2026-0022 created with 1 items. PR items updated with unit cost and total cost.', 0, 'po', 7, 'purchasing', 'purchasing', '2026-02-18 12:34:27'),
(1108, 199, 'Approved by Purchasing', 'sd', 0, 'po', 7, 'purchasing', 'accounting', '2026-02-18 12:34:30'),
(1109, 199, 'Approved by Accounting', 'asd', 0, 'po', 8, 'accounting', 'approver', '2026-02-18 12:34:43'),
(1110, 199, 'Approved by Approver (CEO)', 'asdw', 0, 'po', 12, 'approver', 'purchasing_final', '2026-02-18 12:35:02'),
(1111, 199, 'Approved by Purchasing (Final)', 'adw', 0, 'po', 7, 'purchasing_final', 'warehouse_receiving', '2026-02-18 12:35:26'),
(1112, 199, 'Items Received', 'sdw', 0, 'po', 10, 'warehouse_receiving', 'warehouse_receiving', '2026-02-18 12:38:51'),
(1113, 199, 'Completed Warehouse Receiving', 'sd', 0, 'po', 10, 'warehouse_receiving', 'purchasing_completion', '2026-02-18 12:40:04'),
(1114, 199, 'Completed by Purchasing', 'sd', 0, 'po', 7, 'purchasing_completion', 'accounting_final', '2026-02-18 12:40:17'),
(1115, 199, 'Finalized by Accounting', 'sd', 0, 'po', 8, 'accounting_final', 'completed', '2026-02-18 12:40:55'),
(1116, 200, 'Forwarded to Warehouse', '', 0, 'po', 8, 'requestor', 'warehouse', '2026-02-18 12:44:03'),
(1117, 200, 'Approved by Warehouse', 'ds', 0, 'po', 10, 'warehouse', 'purchasing', '2026-02-18 12:44:17'),
(1118, 200, 'Withdrawal Slip Created', 'Withdrawal Slip WS-2026-0006 created with 2 items', 0, 'po', 7, 'purchasing', 'purchasing', '2026-02-18 12:44:32'),
(1119, 200, 'Purchase Order Created', 'Purchase Order PO-2026-0023 created with 2 items. PR items updated with unit cost and total cost.', 0, 'po', 7, 'purchasing', 'purchasing', '2026-02-18 12:45:05'),
(1120, 200, 'Approved by Purchasing', 'asd', 0, 'po', 7, 'purchasing', 'accounting', '2026-02-18 12:45:10'),
(1121, 200, 'Approved by Accounting', 'asdw', 0, 'po', 8, 'accounting', 'approver', '2026-02-18 12:49:55'),
(1122, 200, 'Approved by Approver (CEO)', 'sdw', 0, 'po', 12, 'approver', 'warehouse_releasing', '2026-02-18 12:50:20'),
(1123, 200, 'Withdrawal Slip Processed', 'Withdrawal slip processed. Total withdrawn: ₱250.00. ₱250.00 deducted from project threshold.', 0, 'po', 10, 'warehouse_releasing', 'warehouse_releasing', '2026-02-18 12:52:27'),
(1124, 202, 'Forwarded to Warehouse', '', 0, 'po', 10, 'requestor', 'warehouse', '2026-02-18 13:03:50'),
(1125, 202, 'Approved by Warehouse', 'ad', 0, 'po', 10, 'warehouse', 'purchasing', '2026-02-18 13:03:54'),
(1126, 202, 'Withdrawal Slip Created', 'Withdrawal Slip WS-2026-0007 created with 2 items', 0, 'po', 7, 'purchasing', 'purchasing', '2026-02-18 13:04:10'),
(1127, 202, 'Purchase Order Created', 'Purchase Order PO-2026-0024 created with 2 items. PR items updated with unit cost and total cost.', 0, 'po', 7, 'purchasing', 'purchasing', '2026-02-18 13:05:01'),
(1128, 202, 'Approved by Purchasing', 'asd', 0, 'po', 7, 'purchasing', 'accounting', '2026-02-18 13:05:07'),
(1129, 202, 'Approved by Accounting', 'sad', 0, 'po', 8, 'accounting', 'approver', '2026-02-18 13:05:20'),
(1130, 202, 'Approved by Approver (CEO)', 'asd', 0, 'po', 12, 'approver', 'warehouse_releasing', '2026-02-18 13:05:33'),
(1131, 202, 'Withdrawal Slip Processed', 'Withdrawal slip processed. Total withdrawn: ₱180.00. ₱180.00 deducted from project threshold.', 0, 'po', 10, 'warehouse_releasing', 'warehouse_releasing', '2026-02-18 13:05:48'),
(1132, 202, 'Completed Warehouse Releasing', 'sd', 0, 'po', 10, 'warehouse_releasing', 'purchasing_final', '2026-02-18 13:15:07'),
(1133, 202, 'Approved by Purchasing (Final)', 'asd', 0, 'po', 7, 'purchasing_final', 'warehouse_receiving', '2026-02-18 13:15:42'),
(1134, 202, 'Items Received', 'sd', 0, 'po', 10, 'warehouse_receiving', 'warehouse_receiving', '2026-02-18 13:16:03'),
(1135, 203, 'Forwarded to Warehouse', '', 0, 'po', 10, 'requestor', 'warehouse', '2026-02-18 13:20:46'),
(1136, 203, 'Approved by Warehouse', 'asd', 0, 'po', 10, 'warehouse', 'purchasing', '2026-02-18 13:20:49'),
(1137, 203, 'Withdrawal Slip Created', 'Withdrawal Slip WS-2026-0008 created with 2 items', 0, 'po', 7, 'purchasing', 'purchasing', '2026-02-18 13:21:02'),
(1138, 203, 'Purchase Order Created', 'Purchase Order PO-2026-0025 created with 2 items. PR items updated with unit cost and total cost.', 0, 'po', 7, 'purchasing', 'purchasing', '2026-02-18 13:21:17'),
(1139, 203, 'Approved by Purchasing', 'sda', 0, 'po', 7, 'purchasing', 'accounting', '2026-02-18 13:21:22'),
(1140, 203, 'Approved by Accounting', 'asd', 0, 'po', 8, 'accounting', 'approver', '2026-02-18 13:21:44'),
(1141, 203, 'Approved by Approver (CEO)', 'asdw', 0, 'po', 12, 'approver', 'warehouse_releasing', '2026-02-18 13:21:58'),
(1142, 203, 'Withdrawal Slip Processed', 'Withdrawal slip processed. Total withdrawn: ₱170.00. ₱170.00 deducted from project threshold.', 0, 'po', 10, 'warehouse_releasing', 'warehouse_releasing', '2026-02-18 13:22:14'),
(1143, 203, 'Completed Warehouse Releasing', 'asd', 0, 'po', 10, 'warehouse_releasing', 'purchasing_final', '2026-02-18 13:23:08'),
(1144, 203, 'Approved by Purchasing (Final)', 'asdw', 0, 'po', 7, 'purchasing_final', 'warehouse_receiving', '2026-02-18 13:23:26'),
(1145, 203, 'Items Received', 'asdw', 0, 'po', 10, 'warehouse_receiving', 'warehouse_receiving', '2026-02-18 13:23:39'),
(1146, 204, 'Forwarded to Warehouse', '', 0, 'po', 10, 'requestor', 'warehouse', '2026-02-18 13:31:54'),
(1147, 204, 'Approved by Warehouse', 'asd', 0, 'po', 10, 'warehouse', 'purchasing', '2026-02-18 13:31:57'),
(1148, 204, 'Withdrawal Slip Created', 'Withdrawal Slip WS-2026-0009 created with 2 items', 0, 'po', 7, 'purchasing', 'purchasing', '2026-02-18 13:32:11'),
(1149, 204, 'Purchase Order Created', 'Purchase Order PO-2026-0026 created with 2 items. PR items updated with unit cost and total cost.', 0, 'po', 7, 'purchasing', 'purchasing', '2026-02-18 13:32:22'),
(1150, 204, 'Approved by Purchasing', 'asd', 0, 'po', 7, 'purchasing', 'accounting', '2026-02-18 13:32:26'),
(1151, 204, 'Approved by Accounting', 'asd', 0, 'po', 8, 'accounting', 'approver', '2026-02-18 13:32:39'),
(1152, 204, 'Approved by Approver (CEO)', 'asdw', 0, 'po', 12, 'approver', 'warehouse_releasing', '2026-02-18 13:32:53'),
(1153, 204, 'Withdrawal Slip Processed', 'Withdrawal slip processed. Total withdrawn: ₱170.00. ₱170.00 deducted from project threshold.', 0, 'po', 10, 'warehouse_releasing', 'warehouse_releasing', '2026-02-18 13:34:48'),
(1154, 204, 'Completed Warehouse Releasing', 'asd', 0, 'po', 10, 'warehouse_releasing', 'purchasing_final', '2026-02-18 13:35:02'),
(1155, 204, 'Approved by Purchasing (Final)', 'asd', 0, 'po', 7, 'purchasing_final', 'warehouse_receiving', '2026-02-18 13:35:14'),
(1156, 204, 'Items Received', 'asdw', 0, 'po', 10, 'warehouse_receiving', 'warehouse_receiving', '2026-02-18 13:39:13'),
(1157, 205, 'Forwarded to Warehouse', '', 0, 'po', 10, 'requestor', 'warehouse', '2026-02-18 14:27:16'),
(1158, 205, 'Approved by Warehouse', 'sd', 0, 'po', 10, 'warehouse', 'purchasing', '2026-02-18 14:27:21'),
(1159, 205, 'Withdrawal Slip Created', 'Withdrawal Slip WS-2026-0010 created with 1 items', 0, 'po', 7, 'purchasing', 'purchasing', '2026-02-18 14:27:40'),
(1160, 205, 'Purchase Order Created', 'Purchase Order PO-2026-0027 created with 1 items. PR items updated with unit cost and total cost.', 0, 'po', 7, 'purchasing', 'purchasing', '2026-02-18 14:27:57'),
(1161, 206, 'Forwarded to Warehouse', '', 0, 'po', 7, 'requestor', 'warehouse', '2026-02-18 14:31:01'),
(1162, 206, 'Approved by Warehouse', 'ads', 0, 'po', 10, 'warehouse', 'purchasing', '2026-02-18 14:31:25'),
(1163, 206, 'Withdrawal Slip Created', 'Withdrawal Slip WS-2026-0011 created with 1 items', 0, 'po', 7, 'purchasing', 'purchasing', '2026-02-18 14:31:43'),
(1164, 207, 'Forwarded to Warehouse', '', 0, 'po', 7, 'requestor', 'warehouse', '2026-02-18 14:54:17'),
(1165, 207, 'Approved by Warehouse', 'asd', 0, 'po', 10, 'warehouse', 'purchasing', '2026-02-18 14:54:34'),
(1166, 207, 'Purchase Order Created', 'Purchase Order PO-2026-0028 created with 1 items. PR items updated with unit cost and total cost.', 0, 'po', 7, 'purchasing', 'purchasing', '2026-02-18 14:54:55'),
(1167, 208, 'Forwarded to Warehouse', '', 0, 'po', 7, 'requestor', 'warehouse', '2026-02-18 15:14:18'),
(1168, 208, 'Approved by Warehouse', 'asd', 0, 'po', 10, 'warehouse', 'purchasing', '2026-02-18 15:14:32'),
(1169, 208, 'Withdrawal Slip Created', 'Withdrawal Slip WS-2026-0012 created with 1 items', 0, 'po', 7, 'purchasing', 'purchasing', '2026-02-18 15:14:45'),
(1170, 209, 'Forwarded to Warehouse', '', 0, 'po', 7, 'requestor', 'warehouse', '2026-02-18 15:25:39'),
(1171, 209, 'Approved by Warehouse', 'asd', 0, 'po', 10, 'warehouse', 'purchasing', '2026-02-18 15:25:54'),
(1172, 209, 'Withdrawal Slip Created', 'Withdrawal Slip WS-2026-0013 created with 1 items', 0, 'po', 7, 'purchasing', 'purchasing', '2026-02-18 15:28:01'),
(1173, 209, 'Approved by Purchasing', 'asd', 0, 'po', 7, 'purchasing', 'approver', '2026-02-18 15:28:07'),
(1174, 210, 'Forwarded to Warehouse', '', 0, 'po', 7, 'requestor', 'warehouse', '2026-02-18 15:32:25'),
(1175, 210, 'Approved by Warehouse', 'dasd', 0, 'po', 10, 'warehouse', 'purchasing', '2026-02-18 15:33:04'),
(1176, 210, 'Withdrawal Slip Created', 'Withdrawal Slip WS-2026-0014 created with 1 items', 0, 'po', 7, 'purchasing', 'purchasing', '2026-02-18 15:33:25'),
(1177, 210, 'Purchase Order Created', 'Purchase Order PO-2026-0029 created with 1 items. PR items updated with unit cost and total cost.', 0, 'po', 7, 'purchasing', 'purchasing', '2026-02-18 15:33:45'),
(1178, 210, 'Approved by Purchasing', 'we', 0, 'po', 7, 'purchasing', 'accounting', '2026-02-18 15:38:04'),
(1179, 209, 'Approved by Approver (CEO)', 'asd', 0, 'po', 12, 'approver', 'warehouse_releasing', '2026-02-18 15:39:20'),
(1180, 209, 'Withdrawal Slip Processed', 'Withdrawal slip processed. Total withdrawn: ₱911.00. ₱911.00 deducted from project threshold.', 0, 'po', 10, 'warehouse_releasing', 'warehouse_releasing', '2026-02-18 15:40:33'),
(1181, 211, 'Forwarded to Warehouse', '', 0, 'po', 10, 'requestor', 'warehouse', '2026-02-19 01:26:39'),
(1182, 211, 'Approved by Warehouse', 'asdw', 0, 'po', 10, 'warehouse', 'purchasing', '2026-02-19 01:26:56'),
(1183, 211, 'Withdrawal Slip Created', 'Withdrawal Slip WS-2026-0015 created with 1 items', 0, 'po', 7, 'purchasing', 'purchasing', '2026-02-19 01:27:11'),
(1184, 211, 'Approved by Purchasing', 'asdw', 0, 'po', 7, 'purchasing', 'approver', '2026-02-19 01:27:15'),
(1185, 211, 'Approved by Approver (CEO)', 'asdw', 0, 'po', 12, 'approver', 'warehouse_releasing', '2026-02-19 01:27:28'),
(1186, 211, 'Withdrawal Slip Processed', 'Withdrawal slip processed. Total withdrawn: ₱111.00. ₱111.00 deducted from project threshold.', 0, 'po', 10, 'warehouse_releasing', 'warehouse_releasing', '2026-02-19 01:28:20'),
(1187, 211, 'Completed Warehouse Releasing', 'asdw', 0, 'po', 10, 'warehouse_releasing', 'completed', '2026-02-19 01:28:36'),
(1188, 212, 'Forwarded to Warehouse', '', 0, 'po', 10, 'requestor', 'warehouse', '2026-02-19 07:11:18'),
(1189, 212, 'Approved by Warehouse', 'asdw', 0, 'po', 10, 'warehouse', 'purchasing', '2026-02-19 07:11:45'),
(1190, 212, 'Withdrawal Slip Created', 'Withdrawal Slip WS-2026-0016 created with 1 items', 0, 'po', 7, 'purchasing', 'purchasing', '2026-02-19 07:12:08'),
(1191, 212, 'Approved by Purchasing', 'asdw', 0, 'po', 7, 'purchasing', 'approver', '2026-02-19 07:12:11'),
(1192, 212, 'Approved by Approver (CEO)', 'asd', 0, 'po', 12, 'approver', 'warehouse_releasing', '2026-02-19 07:27:27'),
(1193, 212, 'Withdrawal Slip Processed', 'Withdrawal slip processed. Total withdrawn: ₱52.00. ₱52.00 deducted from project threshold.', 0, 'po', 10, 'warehouse_releasing', 'warehouse_releasing', '2026-02-19 07:27:59'),
(1194, 213, 'Forwarded to Warehouse', 'a', 0, 'po', 10, 'requestor', 'warehouse', '2026-02-19 07:31:44'),
(1195, 213, 'Approved by Warehouse', 's', 0, 'po', 10, 'warehouse', 'purchasing', '2026-02-19 07:31:47'),
(1196, 213, 'Withdrawal Slip Created', 'Withdrawal Slip WS-2026-0017 created with 1 items', 0, 'po', 7, 'purchasing', 'purchasing', '2026-02-19 07:32:24'),
(1197, 213, 'Approved by Purchasing', 'asdw', 0, 'po', 7, 'purchasing', 'approver', '2026-02-19 07:32:28'),
(1198, 213, 'Approved by Approver (CEO)', 'asdw', 0, 'po', 12, 'approver', 'warehouse_releasing', '2026-02-19 07:32:40'),
(1199, 213, 'Withdrawal Slip Processed', 'Withdrawal slip processed. Total withdrawn: ₱90.00. ₱90.00 deducted from project threshold.', 0, 'po', 10, 'warehouse_releasing', 'warehouse_releasing', '2026-02-19 07:33:38'),
(1200, 213, 'Completed Warehouse Releasing', 'asdw', 0, 'po', 10, 'warehouse_releasing', 'completed', '2026-02-19 07:34:01'),
(1201, 214, 'Forwarded to Warehouse', '', 0, 'po', 10, 'requestor', 'warehouse', '2026-02-19 07:37:36'),
(1202, 214, 'Approved by Warehouse', 'sasd', 0, 'po', 10, 'warehouse', 'purchasing', '2026-02-19 07:37:40'),
(1203, 214, 'Purchase Order Created', 'Purchase Order PO-2026-0030 created with 1 items. PR updated with supplier and total cost.', 0, 'po', 7, 'purchasing', 'purchasing', '2026-02-19 07:38:03'),
(1204, 214, 'Approved by Purchasing', 'asd', 0, 'po', 7, 'purchasing', 'accounting', '2026-02-19 07:38:06'),
(1205, 214, 'Approved by Accounting', 'asdw', 0, 'po', 8, 'accounting', 'approver', '2026-02-19 07:40:30'),
(1206, 214, 'Approved by Approver (CEO)', 'asdw', 0, 'po', 12, 'approver', 'purchasing_final', '2026-02-19 07:40:45'),
(1207, 214, 'Approved by Purchasing (Final)', 'asdw', 0, 'po', 7, 'purchasing_final', 'warehouse_receiving', '2026-02-19 07:41:37'),
(1208, 214, 'Items Received', 'asdw', 0, 'po', 10, 'warehouse_receiving', 'warehouse_receiving', '2026-02-19 07:42:47'),
(1209, 214, 'Completed Warehouse Receiving', 'sda', 0, 'po', 10, 'warehouse_receiving', 'purchasing_completion', '2026-02-19 07:45:25'),
(1210, 214, 'Completed by Purchasing', 'ad', 0, 'po', 7, 'purchasing_completion', 'accounting_final', '2026-02-19 07:45:38'),
(1211, 214, 'Finalized by Accounting', 'asdw', 0, 'po', 8, 'accounting_final', 'completed', '2026-02-19 07:46:02'),
(1212, 215, 'Forwarded to Warehouse', '', 0, 'po', 8, 'requestor', 'warehouse', '2026-02-19 07:47:53'),
(1213, 215, 'Approved by Warehouse', 'asdw', 0, 'po', 10, 'warehouse', 'purchasing', '2026-02-19 07:48:06'),
(1214, 215, 'Withdrawal Slip Created', 'Withdrawal Slip WS-2026-0018 created with 2 items', 0, 'po', 7, 'purchasing', 'purchasing', '2026-02-19 07:48:38'),
(1215, 215, 'Purchase Order Created', 'Purchase Order PO-2026-0031 created with 1 items. PR items updated with unit cost and total cost.', 0, 'po', 7, 'purchasing', 'purchasing', '2026-02-19 07:49:27'),
(1216, 216, 'Forwarded to Warehouse', '', 0, 'po', 7, 'requestor', 'warehouse', '2026-02-19 07:50:12'),
(1217, 216, 'Approved by Warehouse', 'asd', 0, 'po', 10, 'warehouse', 'purchasing', '2026-02-19 07:50:24'),
(1218, 216, 'Withdrawal Slip Created', 'Withdrawal Slip WS-2026-0019 created with 2 items', 0, 'po', 7, 'purchasing', 'purchasing', '2026-02-19 07:50:41'),
(1219, 216, 'Purchase Order Created', 'Purchase Order PO-2026-0032 created with 1 items. PR items updated with unit cost and total cost.', 0, 'po', 7, 'purchasing', 'purchasing', '2026-02-19 07:50:57'),
(1220, 216, 'Approved by Purchasing', 'asdw', 0, 'po', 7, 'purchasing', 'accounting', '2026-02-19 07:51:38'),
(1221, 217, 'Forwarded to Warehouse', '', 0, 'po', 7, 'requestor', 'warehouse', '2026-02-19 07:57:43'),
(1222, 218, 'Forwarded to Warehouse', '', 0, 'po', 10, 'requestor', 'warehouse', '2026-02-19 07:58:30'),
(1223, 218, 'Approved by Warehouse', 'asd', 0, 'po', 10, 'warehouse', 'purchasing', '2026-02-19 07:58:41'),
(1224, 218, 'Purchase Order Created', 'Purchase Order PO-2026-0033 created with 1 items. PR updated with supplier and total cost.', 0, 'po', 7, 'purchasing', 'purchasing', '2026-02-19 07:58:56'),
(1225, 218, 'Approved by Purchasing', 'asd', 0, 'po', 7, 'purchasing', 'accounting', '2026-02-19 07:58:59'),
(1226, 218, 'Approved by Accounting', 'asd', 0, 'po', 8, 'accounting', 'approver', '2026-02-19 07:59:11'),
(1227, 218, 'Approved by Approver (CEO)', 'asdw', 0, 'po', 12, 'approver', 'purchasing_final', '2026-02-19 07:59:25'),
(1228, 218, 'Approved by Purchasing (Final)', 'asdw', 0, 'po', 7, 'purchasing_final', 'warehouse_receiving', '2026-02-19 08:00:12'),
(1229, 218, 'Items Received', 'asdw', 0, 'po', 10, 'warehouse_receiving', 'warehouse_receiving', '2026-02-19 08:07:34'),
(1230, 218, 'Completed Warehouse Receiving', 'jk', 0, 'po', 10, 'warehouse_receiving', 'purchasing_completion', '2026-02-19 08:23:23'),
(1231, 219, 'Forwarded to Warehouse', '', 0, 'po', 10, 'requestor', 'warehouse', '2026-02-19 08:24:27'),
(1232, 219, 'Approved by Warehouse', 'w', 0, 'po', 10, 'warehouse', 'purchasing', '2026-02-19 08:24:30'),
(1233, 219, 'Withdrawal Slip Created', 'Withdrawal Slip WS-2026-0020 created with 2 items', 0, 'po', 7, 'purchasing', 'purchasing', '2026-02-19 08:24:45'),
(1234, 219, 'Purchase Order Created', 'Purchase Order PO-2026-0034 created with 2 items. PR items updated with unit cost and total cost.', 0, 'po', 7, 'purchasing', 'purchasing', '2026-02-19 08:25:01'),
(1235, 219, 'Approved by Purchasing', 'asd', 0, 'po', 7, 'purchasing', 'accounting', '2026-02-19 09:28:26'),
(1236, 219, 'Approved by Accounting', 'asdw', 0, 'po', 8, 'accounting', 'approver', '2026-02-19 09:29:29'),
(1237, 219, 'Approved by Approver (CEO)', 'asd', 0, 'po', 12, 'approver', 'warehouse_releasing', '2026-02-19 09:29:43'),
(1238, 219, 'Withdrawal Slip Processed', 'Withdrawal slip processed. Total withdrawn: ₱70.00. ₱70.00 deducted from project threshold.', 0, 'po', 10, 'warehouse_releasing', 'warehouse_releasing', '2026-02-19 09:30:52'),
(1239, 219, 'Completed Warehouse Releasing', 'asd', 0, 'po', 10, 'warehouse_releasing', 'purchasing_final', '2026-02-19 09:31:06'),
(1240, 219, 'Approved by Purchasing (Final)', 'asd', 0, 'po', 7, 'purchasing_final', 'warehouse_receiving', '2026-02-19 09:31:30'),
(1241, 219, 'Items Received', 'sd', 0, 'po', 10, 'warehouse_receiving', 'warehouse_receiving', '2026-02-19 09:31:51'),
(1242, 219, 'Completed Warehouse Receiving', 'asd', 0, 'po', 10, 'warehouse_receiving', 'purchasing_completion', '2026-02-19 09:32:54'),
(1243, 219, 'Completed by Purchasing', 'asdw', 0, 'po', 7, 'purchasing_completion', 'accounting_final', '2026-02-19 09:33:07'),
(1244, 219, 'Finalized by Accounting', 'asdw', 0, 'po', 8, 'accounting_final', 'completed', '2026-02-19 09:33:35'),
(1245, 221, 'Forwarded to Warehouse', '', 0, 'po', 8, 'requestor', 'warehouse', '2026-02-19 09:47:10'),
(1246, 221, 'Approved by Warehouse', 'ASD', 0, 'po', 10, 'warehouse', 'purchasing', '2026-02-19 09:47:36'),
(1247, 221, 'Purchase Order Created', 'Purchase Order PO-2026-0035 created with 1 items. PR items updated with unit cost and total cost.', 0, 'po', 7, 'purchasing', 'purchasing', '2026-02-19 09:47:59'),
(1248, 221, 'Approved by Purchasing', 'asdw', 0, 'po', 7, 'purchasing', 'accounting', '2026-02-19 09:48:03'),
(1249, 221, 'Approved by Accounting', 'asd', 0, 'po', 8, 'accounting', 'approver', '2026-02-19 09:48:14'),
(1250, 221, 'Approved by Approver (CEO)', 'asd', 0, 'po', 12, 'approver', 'purchasing_final', '2026-02-19 09:48:28'),
(1251, 222, 'Forwarded to Warehouse', '', 0, 'po', 12, 'requestor', 'warehouse', '2026-02-19 10:01:54'),
(1252, 222, 'Approved by Warehouse', 'asdw', 0, 'po', 10, 'warehouse', 'purchasing', '2026-02-19 10:02:06'),
(1253, 222, 'Purchase Order Created', 'Purchase Order PO-2026-0036 created with 1 items. PR items updated with unit cost and total cost.', 0, 'po', 7, 'purchasing', 'purchasing', '2026-02-19 10:02:26'),
(1254, 222, 'Approved by Purchasing', 'asd', 0, 'po', 7, 'purchasing', 'accounting', '2026-02-19 10:02:29'),
(1255, 222, 'Approved by Accounting', 'ads', 0, 'po', 8, 'accounting', 'approver', '2026-02-19 10:02:44'),
(1256, 222, 'Approved by Approver (CEO)', 'asdw', 0, 'po', 12, 'approver', 'purchasing_final', '2026-02-19 10:02:57'),
(1257, 222, 'Approved by Purchasing (Final)', 'asdw', 0, 'po', 7, 'purchasing_final', 'warehouse_receiving', '2026-02-19 10:03:24'),
(1258, 222, 'Items Received', 'asdw', 0, 'po', 10, 'warehouse_receiving', 'warehouse_receiving', '2026-02-19 10:03:40'),
(1259, 222, 'Completed Warehouse Receiving', 'kk', 0, 'po', 10, 'warehouse_receiving', 'purchasing_completion', '2026-02-19 10:10:49'),
(1260, 223, 'Forwarded to Warehouse', 'asd', 0, 'po', 10, 'requestor', 'warehouse', '2026-02-19 10:11:39'),
(1261, 223, 'Approved by Warehouse', 'asd', 0, 'po', 10, 'warehouse', 'purchasing', '2026-02-19 10:11:42'),
(1262, 223, 'Purchase Order Created', 'Purchase Order PO-2026-0037 created with 1 items. PR items updated with unit cost and total cost.', 0, 'po', 7, 'purchasing', 'purchasing', '2026-02-19 10:11:58'),
(1263, 223, 'Approved by Purchasing', 'asd', 0, 'po', 7, 'purchasing', 'accounting', '2026-02-19 10:12:01'),
(1264, 223, 'Approved by Accounting', 'asd', 0, 'po', 8, 'accounting', 'approver', '2026-02-19 10:12:15'),
(1265, 223, 'Approved by Approver (CEO)', 'asd', 0, 'po', 12, 'approver', 'purchasing_final', '2026-02-19 10:12:26'),
(1266, 223, 'Approved by Purchasing (Final)', 'asd', 0, 'po', 7, 'purchasing_final', 'warehouse_receiving', '2026-02-19 10:12:44'),
(1267, 223, 'Items Received', 'asdw', 0, 'po', 10, 'warehouse_receiving', 'warehouse_receiving', '2026-02-19 10:13:21'),
(1268, 223, 'Completed Warehouse Receiving', 'asd', 0, 'po', 10, 'warehouse_receiving', 'purchasing_completion', '2026-02-19 10:13:35'),
(1269, 223, 'Completed by Purchasing', 'asdw', 0, 'po', 7, 'purchasing_completion', 'accounting_final', '2026-02-19 10:13:50'),
(1270, 223, 'Finalized by Accounting', 'asd', 0, 'po', 8, 'accounting_final', 'completed', '2026-02-19 10:14:05'),
(1271, 224, 'Forwarded to Warehouse', 'asdw', 0, 'po', 8, 'requestor', 'warehouse', '2026-02-19 10:14:24'),
(1272, 224, 'Approved by Warehouse', 'asdw', 0, 'po', 10, 'warehouse', 'purchasing', '2026-02-19 10:14:36'),
(1273, 224, 'Withdrawal Slip Created', 'Withdrawal Slip WS-2026-0021 created with 1 items', 0, 'po', 7, 'purchasing', 'purchasing', '2026-02-19 10:15:05'),
(1274, 224, 'Approved by Purchasing', 'asd', 0, 'po', 7, 'purchasing', 'approver', '2026-02-19 10:15:09'),
(1275, 224, 'Approved by Approver (CEO)', 'asdw', 0, 'po', 12, 'approver', 'warehouse_releasing', '2026-02-19 10:15:23'),
(1276, 224, 'Withdrawal Slip Processed', 'Withdrawal slip processed. Total withdrawn: ₱25.00. ₱25.00 deducted from project threshold.', 0, 'po', 10, 'warehouse_releasing', 'warehouse_releasing', '2026-02-19 10:15:58'),
(1277, 224, 'Completed Warehouse Releasing', 'asd', 0, 'po', 10, 'warehouse_releasing', 'completed', '2026-02-19 10:16:10'),
(1278, 232, 'Forwarded to Warehouse', '', 0, 'po', 7, 'requestor', 'warehouse', '2026-02-27 14:01:17'),
(1279, 232, 'Approved by Warehouse', 'asd', 0, 'po', 10, 'warehouse', 'purchasing', '2026-02-27 14:02:14'),
(1280, 232, 'Purchase Order Created', 'Purchase Order PO-2026-0038 created with 1 items. PR updated with supplier and total cost.', 0, 'po', 7, 'purchasing', 'purchasing', '2026-02-27 14:02:39'),
(1281, 230, 'Forwarded to Warehouse', '', 0, 'po', 7, 'requestor', 'warehouse', '2026-02-27 14:06:16'),
(1282, 230, 'Approved by Warehouse', 'sad', 0, 'po', 10, 'warehouse', 'purchasing', '2026-02-27 14:06:34'),
(1283, 230, 'Purchase Order Created', 'Purchase Order PO-2026-0039 created with 1 items. PR updated with supplier and total cost.', 0, 'po', 7, 'purchasing', 'purchasing', '2026-02-27 14:07:10'),
(1284, 233, 'Forwarded to Warehouse', '', 0, 'po', 7, 'requestor', 'warehouse', '2026-02-27 14:09:22'),
(1285, 233, 'Approved by Warehouse', 'asd', 0, 'po', 10, 'warehouse', 'purchasing', '2026-02-27 14:09:37'),
(1286, 233, 'Purchase Order Created', 'Purchase Order 000001 created with 1 items. PR updated with supplier and total cost.', 0, 'po', 7, 'purchasing', 'purchasing', '2026-02-27 14:10:12'),
(1287, 234, 'Forwarded to Warehouse', '', 0, 'po', 7, 'requestor', 'warehouse', '2026-02-27 14:11:00'),
(1288, 234, 'Approved by Warehouse', 'asdw', 0, 'po', 10, 'warehouse', 'purchasing', '2026-02-27 14:11:17'),
(1289, 234, 'Purchase Order Created', 'Purchase Order 000002 created with 1 items. PR updated with supplier and total cost.', 0, 'po', 7, 'purchasing', 'purchasing', '2026-02-27 14:11:33'),
(1290, 235, 'Forwarded to Warehouse', '', 0, 'po', 10, 'requestor', 'warehouse', '2026-03-27 03:17:23'),
(1291, 235, 'Approved by Warehouse', 'ASD', 0, 'po', 10, 'warehouse', 'purchasing', '2026-03-27 03:17:31'),
(1292, 236, 'Forwarded to Warehouse', '', 0, 'po', 12, 'requestor', 'warehouse', '2026-04-15 07:16:23'),
(1293, 236, 'Approved by Warehouse', 'asdw', 0, 'po', 10, 'warehouse', 'purchasing', '2026-04-15 07:17:47'),
(1294, 236, 'Withdrawal Slip Created', 'Withdrawal Slip WS-2026-0022 created with 2 items', 0, 'po', 7, 'purchasing', 'purchasing', '2026-04-15 07:18:44'),
(1295, 236, 'Approved by Purchasing', 'Okay', 0, 'po', 7, 'purchasing', 'approver', '2026-04-15 07:18:52'),
(1296, 236, 'Approved by Approver (CEO)', 'asdw', 0, 'po', 12, 'approver', 'warehouse_releasing', '2026-04-15 07:23:52'),
(1297, 236, 'Withdrawal Slip Processed', 'Withdrawal slip processed. Total withdrawn: ₱160.00. ₱160.00 deducted from project threshold.', 0, 'po', 10, 'warehouse_releasing', 'warehouse_releasing', '2026-04-15 07:24:57'),
(1298, 236, 'Completed Warehouse Releasing', 'asd', 0, 'po', 10, 'warehouse_releasing', 'completed', '2026-04-15 07:25:34'),
(1305, 239, 'Forwarded to Warehouse', '', 0, 'po', 15, 'requestor', 'warehouse', '2026-10-01 06:05:06'),
(1309, 239, 'Approved by Warehouse', 'asd', 0, 'po', 10, 'warehouse', 'purchasing', '2026-10-01 09:59:17'),
(1310, 239, 'Withdrawal Slip Created', 'Withdrawal Slip WS-2026-0023 created with 1 items', 0, 'po', 7, 'purchasing', 'purchasing', '2026-10-01 10:00:04'),
(1362, 239, 'Approved by Purchasing', 'asdw', 0, 'po', 7, 'purchasing', 'approver', '2026-10-01 12:41:39'),
(1367, 239, 'Approved by Approver (CEO)', 'asd', 0, 'po', 15, 'approver', 'warehouse_releasing', '2026-10-01 12:42:34'),
(1370, 239, 'Withdrawal Slip Processed', 'Withdrawal slip processed. Total withdrawn: ₱0.00. ₱0.00 deducted from project threshold.', 0, 'po', 10, 'warehouse_releasing', 'warehouse_releasing', '2026-10-01 12:43:18'),
(1383, 269, 'Forwarded to Warehouse', '', 0, 'po', 10, 'requestor', 'warehouse', '2026-10-01 12:50:47'),
(1391, 269, 'Approved by Warehouse', 'asda', 0, 'po', 10, 'warehouse', 'purchasing', '2026-10-01 12:51:32'),
(1392, 269, 'Withdrawal Slip Created', 'Withdrawal Slip WS-2026-0024 created with 1 items', 0, 'po', 7, 'purchasing', 'purchasing', '2026-10-01 12:52:01'),
(1393, 269, 'Approved by Purchasing', 'asd', 0, 'po', 7, 'purchasing', 'approver', '2026-10-01 12:52:10'),
(1394, 269, 'Approved by Approver (CEO)', 'asd', 0, 'po', 15, 'approver', 'warehouse_releasing', '2026-10-01 12:52:40'),
(1395, 269, 'Withdrawal Slip Processed', 'Withdrawal slip processed. Total withdrawn: ₱0.00. ₱0.00 deducted from project threshold.', 0, 'po', 10, 'warehouse_releasing', 'warehouse_releasing', '2026-10-01 12:53:19'),
(1424, 275, 'Forwarded to Warehouse', '', 0, 'po', 15, 'requestor', 'warehouse', '2026-10-01 14:07:18'),
(1425, 275, 'Approved by Warehouse', 'asd', 0, 'po', 10, 'warehouse', 'purchasing', '2026-10-01 14:07:35');

-- --------------------------------------------------------

--
-- Table structure for table `purchase_orders`
--

CREATE TABLE `purchase_orders` (
  `id` int NOT NULL,
  `po_number` varchar(50) NOT NULL,
  `pr_id` int NOT NULL,
  `supplier_id` int DEFAULT NULL,
  `po_date` date NOT NULL,
  `expected_delivery` date DEFAULT NULL,
  `requested_by` int NOT NULL,
  `project_id` int DEFAULT NULL,
  `status` enum('draft','sent','confirmed','partially_received','delivered','cancelled','pending','processing','approved') CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT 'draft',
  `is_simplified_flow` tinyint(1) DEFAULT '0',
  `total_amount` decimal(12,2) DEFAULT '0.00',
  `remarks` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `purchase_orders`
--

INSERT INTO `purchase_orders` (`id`, `po_number`, `pr_id`, `supplier_id`, `po_date`, `expected_delivery`, `requested_by`, `project_id`, `status`, `is_simplified_flow`, `total_amount`, `remarks`, `created_at`, `updated_at`) VALUES
(75, 'PO-2026-0001', 164, NULL, '2026-02-17', '2026-02-17', 12, 17, 'pending', 0, 100.00, '', '2026-02-17 01:40:51', '2026-02-17 01:40:51'),
(76, 'PO-2026-0002', 167, 9, '2026-02-17', '2026-02-17', 7, NULL, 'confirmed', 0, 11.00, '', '2026-02-17 13:40:46', '2026-02-17 13:54:01'),
(77, 'PO-2026-0003', 168, NULL, '2026-02-18', '2026-02-18', 8, NULL, 'pending', 0, 150.00, '', '2026-02-18 02:14:15', '2026-02-18 02:14:15'),
(79, 'PO-2026-0004', 169, NULL, '2026-02-18', '2026-02-18', 10, NULL, 'pending', 0, 150.00, '', '2026-02-18 02:46:18', '2026-02-18 02:46:18'),
(80, 'PO-2026-0005', 170, NULL, '2026-02-18', '2026-02-18', 10, NULL, 'confirmed', 0, 111.00, '', '2026-02-18 02:53:23', '2026-02-18 03:20:26'),
(81, 'PO-2026-0006', 171, NULL, '2026-02-18', '2026-02-18', 10, NULL, 'confirmed', 0, 12.00, '', '2026-02-18 03:39:54', '2026-02-18 03:42:14'),
(82, 'PO-2026-0007', 172, NULL, '2026-02-18', '2026-02-19', 10, NULL, 'confirmed', 0, 120.00, '', '2026-02-18 03:45:06', '2026-02-18 03:46:01'),
(83, 'PO-2026-0008', 174, 10, '2026-02-18', '2026-02-19', 10, NULL, 'confirmed', 0, 90.00, '', '2026-02-18 05:15:25', '2026-02-18 05:36:40'),
(84, 'PO-2026-0009', 175, 9, '2026-02-18', '2026-02-18', 10, NULL, 'confirmed', 0, 150.00, '', '2026-02-18 05:49:12', '2026-02-18 05:50:38'),
(85, 'PO-2026-0010', 176, 10, '2026-02-18', '2026-02-18', 10, NULL, 'confirmed', 0, 50.00, '', '2026-02-18 05:55:54', '2026-02-18 05:58:33'),
(86, 'PO-2026-0011', 177, 10, '2026-02-18', '2026-02-18', 10, NULL, 'confirmed', 0, 20.00, '', '2026-02-18 06:01:38', '2026-02-18 06:04:52'),
(87, 'PO-2026-0012', 178, 9, '2026-02-18', '2026-02-18', 10, NULL, 'confirmed', 0, 30.00, '', '2026-02-18 06:21:51', '2026-02-18 06:31:04'),
(88, 'PO-2026-0013', 182, NULL, '2026-02-18', '2026-02-18', 8, 17, 'pending', 0, 50.00, '', '2026-02-18 07:46:03', '2026-02-18 07:46:03'),
(89, 'PO-2026-0014', 186, NULL, '2026-02-18', '2026-02-18', 7, 17, 'pending', 0, 51.00, '', '2026-02-18 08:25:50', '2026-02-18 08:25:50'),
(90, 'PO-2026-0015', 189, NULL, '2026-02-18', '2026-02-18', 7, 17, 'pending', 0, 150.00, '', '2026-02-18 08:54:22', '2026-02-18 08:54:22'),
(91, 'PO-2026-0016', 191, NULL, '2026-02-18', '2026-02-18', 7, 17, 'confirmed', 0, 151.00, '', '2026-02-18 09:03:21', '2026-02-18 09:12:48'),
(92, 'PO-2026-0017', 192, NULL, '2026-02-18', '2026-02-18', 7, NULL, 'confirmed', 0, 152.00, '', '2026-02-18 09:05:14', '2026-02-18 09:09:57'),
(93, 'PO-2026-0018', 194, NULL, '2026-02-18', '2026-02-18', 10, 17, 'confirmed', 0, 99.00, '', '2026-02-18 09:47:37', '2026-02-18 09:50:12'),
(94, 'PO-2026-0019', 195, NULL, '2026-02-18', '2026-02-18', 10, 17, 'confirmed', 0, 95.00, '', '2026-02-18 10:44:15', '2026-02-18 10:45:22'),
(95, 'PO-2026-0020', 196, 9, '2026-02-18', '2026-02-18', 10, NULL, 'confirmed', 0, 51.00, '', '2026-02-18 10:46:48', '2026-02-18 10:48:00'),
(96, 'PO-2026-0021', 198, NULL, '2026-02-18', '2026-02-18', 10, 17, 'confirmed', 0, 15.00, '', '2026-02-18 11:14:22', '2026-02-18 11:22:05'),
(97, 'PO-2026-0022', 199, NULL, '2026-02-18', '2026-02-18', 10, 17, 'confirmed', 0, 16.00, '', '2026-02-18 12:34:27', '2026-02-18 12:38:51'),
(98, 'PO-2026-0023', 200, NULL, '2026-02-18', '2026-02-18', 8, 17, 'approved', 0, 35.00, '', '2026-02-18 12:45:05', '2026-02-18 12:50:20'),
(99, 'PO-2026-0024', 202, NULL, '2026-02-18', '2026-02-18', 10, 17, 'confirmed', 0, 30.00, '', '2026-02-18 13:05:01', '2026-02-18 13:16:03'),
(100, 'PO-2026-0025', 203, NULL, '2026-02-18', '2026-02-18', 10, 17, 'confirmed', 0, 31.00, '', '2026-02-18 13:21:17', '2026-02-18 13:23:39'),
(101, 'PO-2026-0026', 204, NULL, '2026-02-18', '2026-02-18', 10, 17, 'confirmed', 0, 40.00, '', '2026-02-18 13:32:22', '2026-02-18 13:39:13'),
(102, 'PO-2026-0027', 205, NULL, '2026-02-18', '2026-02-18', 10, 17, 'pending', 0, 20.00, '', '2026-02-18 14:27:56', '2026-02-18 14:27:57'),
(103, 'PO-2026-0028', 207, NULL, '2026-02-18', '2026-02-18', 7, 17, 'pending', 0, 30.00, '', '2026-02-18 14:54:55', '2026-02-18 14:54:55'),
(104, 'PO-2026-0029', 210, NULL, '2026-02-18', '2026-02-18', 7, 17, 'processing', 0, 30.00, '', '2026-02-18 15:33:45', '2026-02-18 15:38:04'),
(105, 'PO-2026-0030', 214, 10, '2026-02-19', '2026-02-19', 10, NULL, 'confirmed', 0, 280.00, '', '2026-02-19 07:38:03', '2026-02-19 07:42:47'),
(106, 'PO-2026-0031', 215, NULL, '2026-02-19', '2026-02-19', 8, 17, 'pending', 0, 100.00, '', '2026-02-19 07:49:27', '2026-02-19 07:49:27'),
(107, 'PO-2026-0032', 216, NULL, '2026-02-19', '2026-02-19', 7, 17, 'processing', 0, 150.00, '', '2026-02-19 07:50:57', '2026-02-19 07:51:38'),
(108, 'PO-2026-0033', 218, 10, '2026-02-19', '2026-02-19', 10, NULL, 'confirmed', 0, 120.00, '', '2026-02-19 07:58:56', '2026-02-19 08:07:34'),
(109, 'PO-2026-0034', 219, NULL, '2026-02-19', '2026-02-19', 10, 17, 'confirmed', 0, 480.00, '', '2026-02-19 08:25:01', '2026-02-19 09:31:51'),
(110, 'PO-2026-0035', 221, NULL, '2026-02-19', '2026-02-19', 8, 17, 'approved', 0, 101.00, '', '2026-02-19 09:47:59', '2026-02-19 09:48:28'),
(111, 'PO-2026-0036', 222, NULL, '2026-02-19', '2026-02-19', 12, 17, 'confirmed', 0, 432.00, '', '2026-02-19 10:02:26', '2026-02-19 10:03:40'),
(112, 'PO-2026-0037', 223, NULL, '2026-02-19', '2026-02-19', 10, 17, 'confirmed', 0, 242.00, '', '2026-02-19 10:11:58', '2026-02-19 10:13:21'),
(113, 'PO-2026-0038', 232, NULL, '2026-02-27', '2026-02-27', 7, NULL, 'pending', 0, 121.00, '', '2026-02-27 14:02:39', '2026-02-27 14:02:39'),
(114, 'PO-2026-0039', 230, 9, '2026-02-27', '2026-02-27', 7, NULL, 'pending', 0, 150.00, '', '2026-02-27 14:07:10', '2026-02-27 14:07:10'),
(115, '000001', 233, 9, '2026-02-27', '2026-02-27', 7, NULL, 'pending', 0, 12.00, '', '2026-02-27 14:10:12', '2026-02-27 14:10:12'),
(116, '000002', 234, 9, '2026-02-27', '2026-02-27', 7, NULL, 'pending', 0, 22.00, '', '2026-02-27 14:11:33', '2026-02-27 14:11:33'),
(124, 'VERIFY-WHYPO-1790857841', 248, 4, '2026-10-01', NULL, 1, NULL, 'pending', 0, 0.00, NULL, '2026-10-01 12:30:41', '2026-10-01 12:30:41');

-- --------------------------------------------------------

--
-- Table structure for table `purchase_requests`
--

CREATE TABLE `purchase_requests` (
  `id` int NOT NULL,
  `pr_number` varchar(50) NOT NULL,
  `requested_by` int NOT NULL,
  `project_id` int DEFAULT NULL,
  `request_type` enum('project','supplier') NOT NULL DEFAULT 'project',
  `document_type` enum('ws','po_ws','pr_po','direct_po') CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `supplier_id` int DEFAULT NULL,
  `request_date` date NOT NULL,
  `status` enum('pending','processing','approved','rejected','completed') DEFAULT 'pending',
  `total_estimated_cost` decimal(15,2) DEFAULT '0.00',
  `remarks` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `purchase_requests`
--

INSERT INTO `purchase_requests` (`id`, `pr_number`, `requested_by`, `project_id`, `request_type`, `document_type`, `supplier_id`, `request_date`, `status`, `total_estimated_cost`, `remarks`, `created_at`, `updated_at`) VALUES
(161, 'PR-2026-0001', 10, NULL, 'supplier', 'pr_po', 9, '2026-02-16', 'pending', 0.00, NULL, '2026-02-16 08:14:13', '2026-02-16 08:14:13'),
(163, 'PR-2026-0002', 12, 17, 'project', 'ws', NULL, '2026-02-16', 'approved', 0.00, NULL, '2026-02-16 09:27:17', '2026-02-16 09:59:21'),
(164, 'PR-2026-0003', 12, 17, 'project', 'po_ws', NULL, '2026-02-16', 'processing', 0.00, NULL, '2026-02-16 09:40:00', '2026-02-17 01:39:13'),
(165, 'PR-2026-0004', 7, 17, 'project', 'ws', NULL, '2026-02-17', 'pending', 0.00, NULL, '2026-02-17 01:47:14', '2026-02-17 01:47:14'),
(166, 'PR-2026-0005', 7, 17, 'project', 'pr_po', NULL, '2026-02-17', 'processing', 0.00, NULL, '2026-02-17 01:48:43', '2026-02-17 01:59:15'),
(167, 'PR-2026-0006', 7, NULL, 'supplier', 'pr_po', 9, '2026-02-17', 'approved', 0.00, NULL, '2026-02-17 13:03:10', '2026-02-17 13:50:03'),
(168, 'PR-2026-0007', 8, NULL, 'supplier', 'pr_po', NULL, '2026-02-18', 'processing', 0.00, NULL, '2026-02-18 01:49:59', '2026-02-18 01:50:06'),
(169, 'PR-2026-0008', 10, NULL, 'supplier', 'pr_po', NULL, '2026-02-18', 'rejected', 150.00, NULL, '2026-02-18 02:30:11', '2026-02-18 05:13:31'),
(170, 'PR-2026-0009', 10, NULL, 'supplier', 'pr_po', 5, '2026-02-18', 'approved', 111.00, NULL, '2026-02-18 02:52:23', '2026-02-18 03:01:25'),
(171, 'PR-2026-0010', 10, NULL, 'supplier', 'pr_po', 10, '2026-02-18', 'approved', 12.00, NULL, '2026-02-18 03:39:18', '2026-02-18 03:41:04'),
(172, 'PR-2026-0011', 10, NULL, 'supplier', 'pr_po', 9, '2026-02-18', 'approved', 120.00, NULL, '2026-02-18 03:44:36', '2026-02-18 03:45:36'),
(173, 'PR-2026-0012', 10, NULL, 'supplier', 'pr_po', NULL, '2026-02-18', 'processing', 0.00, NULL, '2026-02-18 04:59:52', '2026-02-18 05:12:33'),
(174, 'PR-2026-0013', 10, NULL, 'supplier', 'pr_po', 10, '2026-02-18', 'processing', 90.00, NULL, '2026-02-18 05:14:43', '2026-02-18 05:36:40'),
(175, 'PR-2026-0014', 10, NULL, 'supplier', 'pr_po', 9, '2026-02-18', 'processing', 150.00, NULL, '2026-02-18 05:48:44', '2026-02-18 05:50:38'),
(176, 'PR-2026-0015', 10, NULL, 'supplier', 'pr_po', 10, '2026-02-18', 'processing', 75.00, NULL, '2026-02-18 05:55:33', '2026-02-18 05:58:33'),
(177, 'PR-2026-0016', 10, NULL, 'supplier', 'pr_po', 10, '2026-02-18', 'processing', 10.00, NULL, '2026-02-18 06:01:14', '2026-02-18 06:04:52'),
(178, 'PR-2026-0017', 10, NULL, 'supplier', 'pr_po', 9, '2026-02-18', 'completed', 30.00, NULL, '2026-02-18 06:20:53', '2026-02-18 06:35:22'),
(179, 'PR-2026-0018', 8, 17, 'project', 'ws', NULL, '2026-02-18', 'pending', 0.00, NULL, '2026-02-18 06:36:45', '2026-02-18 06:36:45'),
(180, 'PR-2026-0019', 8, 17, 'project', 'ws', NULL, '2026-02-18', 'pending', 0.00, NULL, '2026-02-18 07:07:23', '2026-02-18 07:07:23'),
(181, 'PR-2026-0020', 8, 17, 'project', 'po_ws', NULL, '2026-02-18', 'pending', 0.00, NULL, '2026-02-18 07:31:04', '2026-02-18 07:31:04'),
(182, 'PR-2026-0021', 8, 17, 'project', 'po_ws', NULL, '2026-02-18', 'processing', 0.00, NULL, '2026-02-18 07:39:53', '2026-02-18 07:44:51'),
(184, 'PR-2026-0022', 7, 17, 'project', 'po_ws', NULL, '2026-02-18', 'pending', 0.00, NULL, '2026-02-18 08:10:04', '2026-02-18 08:10:04'),
(185, 'PR-2026-0023', 7, 17, 'project', 'po_ws', NULL, '2026-02-18', 'pending', 0.00, NULL, '2026-02-18 08:11:43', '2026-02-18 08:11:43'),
(186, 'PR-2026-0024', 7, 17, 'project', 'po_ws', NULL, '2026-02-18', 'processing', 0.00, NULL, '2026-02-18 08:23:27', '2026-02-18 08:25:04'),
(187, 'PR-2026-0025', 7, 17, 'project', 'ws', NULL, '2026-02-18', 'pending', 0.00, NULL, '2026-02-18 08:38:11', '2026-02-18 08:38:11'),
(188, 'PR-2026-0026', 7, 17, 'project', 'ws', NULL, '2026-02-18', 'pending', 0.00, NULL, '2026-02-18 08:38:47', '2026-02-18 08:38:47'),
(189, 'PR-2026-0027', 7, 17, 'project', 'pr_po', NULL, '2026-02-18', 'processing', 0.00, NULL, '2026-02-18 08:43:22', '2026-02-18 08:43:40'),
(190, 'PR-2026-0028', 7, NULL, 'supplier', 'pr_po', 9, '2026-02-18', 'processing', 0.00, NULL, '2026-02-18 09:01:45', '2026-02-18 09:01:49'),
(191, 'PR-2026-0029', 7, 17, 'project', 'pr_po', NULL, '2026-02-18', 'approved', 151.00, NULL, '2026-02-18 09:02:16', '2026-02-18 09:07:52'),
(192, 'PR-2026-0030', 7, NULL, 'supplier', 'pr_po', 4, '2026-02-18', 'completed', 152.00, NULL, '2026-02-18 09:04:28', '2026-02-18 09:09:57'),
(193, 'PR-2026-0031', 10, 17, 'project', 'ws', NULL, '2026-02-18', 'completed', 0.00, NULL, '2026-02-18 09:44:27', '2026-02-18 09:46:04'),
(194, 'PR-2026-0032', 10, 17, 'project', 'pr_po', NULL, '2026-02-18', 'completed', 99.00, NULL, '2026-02-18 09:47:03', '2026-02-18 09:50:12'),
(195, 'PR-2026-0033', 10, 17, 'project', 'pr_po', NULL, '2026-02-18', 'completed', 95.00, NULL, '2026-02-18 10:43:44', '2026-02-18 10:45:22'),
(196, 'PR-2026-0034', 10, NULL, 'supplier', 'pr_po', 9, '2026-02-18', 'completed', 51.00, NULL, '2026-02-18 10:46:24', '2026-02-18 10:48:00'),
(197, 'PR-2026-0035', 10, 17, 'project', 'pr_po', NULL, '2026-02-18', 'pending', 0.00, NULL, '2026-02-18 10:48:49', '2026-02-18 10:48:49'),
(198, 'PR-2026-0036', 10, 17, 'project', 'pr_po', NULL, '2026-02-18', 'approved', 15.00, NULL, '2026-02-18 11:13:12', '2026-02-18 11:14:51'),
(199, 'PR-2026-0037', 10, 17, 'project', 'pr_po', NULL, '2026-02-18', 'completed', 16.00, NULL, '2026-02-18 12:33:56', '2026-02-18 12:38:51'),
(200, 'PR-2026-0038', 8, 17, 'project', 'po_ws', NULL, '2026-02-18', 'approved', 0.00, NULL, '2026-02-18 12:41:44', '2026-02-18 12:50:20'),
(201, 'PR-2026-0039', 8, 17, 'project', 'po_ws', NULL, '2026-02-18', 'pending', 0.00, NULL, '2026-02-18 12:46:52', '2026-02-18 12:46:52'),
(202, 'PR-2026-0040', 10, 17, 'project', 'po_ws', NULL, '2026-02-18', 'approved', 0.00, NULL, '2026-02-18 13:03:03', '2026-02-18 13:05:33'),
(203, 'PR-2026-0041', 10, 17, 'project', 'po_ws', NULL, '2026-02-18', 'approved', 0.00, NULL, '2026-02-18 13:20:41', '2026-02-18 13:21:58'),
(204, 'PR-2026-0042', 10, 17, 'project', 'po_ws', NULL, '2026-02-18', 'completed', 0.00, NULL, '2026-02-18 13:31:48', '2026-02-18 13:39:13'),
(205, 'PR-2026-0043', 10, 17, 'project', 'po_ws', NULL, '2026-02-18', 'processing', 0.00, NULL, '2026-02-18 14:25:46', '2026-02-18 14:27:16'),
(206, 'PR-2026-0044', 7, 17, 'project', 'ws', NULL, '2026-02-18', 'processing', 0.00, NULL, '2026-02-18 14:30:46', '2026-02-18 14:31:01'),
(207, 'PR-2026-0045', 7, 17, 'project', 'pr_po', NULL, '2026-02-18', 'processing', 30.00, NULL, '2026-02-18 14:32:57', '2026-02-18 14:54:55'),
(208, 'PR-2026-0046', 7, 17, 'project', 'ws', NULL, '2026-02-18', 'processing', 0.00, NULL, '2026-02-18 15:14:14', '2026-02-18 15:14:19'),
(209, 'PR-2026-0047', 7, 17, 'project', 'ws', NULL, '2026-02-18', 'approved', 0.00, NULL, '2026-02-18 15:25:07', '2026-02-18 15:39:20'),
(210, 'PR-2026-0048', 7, 17, 'project', 'po_ws', NULL, '2026-02-18', 'processing', 0.00, NULL, '2026-02-18 15:31:53', '2026-02-18 15:32:25'),
(211, 'PR-2026-0049', 10, 17, 'project', 'ws', NULL, '2026-02-19', 'completed', 0.00, NULL, '2026-02-19 01:25:59', '2026-02-19 01:28:20'),
(212, 'PR-2026-0050', 10, 17, 'project', 'ws', NULL, '2026-02-19', 'completed', 0.00, NULL, '2026-02-19 07:10:47', '2026-02-19 07:27:59'),
(213, 'PR-2026-0051', 10, 17, 'project', 'ws', NULL, '2026-02-19', 'completed', 0.00, NULL, '2026-02-19 07:31:39', '2026-02-19 07:33:38'),
(214, 'PR-2026-0052', 10, NULL, 'supplier', 'pr_po', 10, '2026-02-19', 'completed', 280.00, NULL, '2026-02-19 07:37:24', '2026-02-19 07:42:47'),
(215, 'PR-2026-0053', 8, 17, 'project', 'po_ws', NULL, '2026-02-19', 'processing', 0.00, NULL, '2026-02-19 07:47:29', '2026-02-19 07:47:53'),
(216, 'PR-2026-0054', 7, 17, 'project', 'po_ws', NULL, '2026-02-19', 'processing', 0.00, NULL, '2026-02-19 07:50:08', '2026-02-19 07:50:12'),
(217, 'PR-2026-0055', 7, 17, 'project', 'ws', NULL, '2026-02-19', 'processing', 0.00, NULL, '2026-02-19 07:57:36', '2026-02-19 07:57:43'),
(218, 'PR-2026-0056', 10, NULL, 'supplier', 'pr_po', 10, '2026-02-19', 'completed', 120.00, NULL, '2026-02-19 07:58:26', '2026-02-19 08:07:34'),
(219, 'PR-2026-0057', 10, 17, 'project', 'po_ws', NULL, '2026-02-19', 'completed', 0.00, NULL, '2026-02-19 08:24:10', '2026-02-19 09:31:51'),
(221, 'PR-2026-0058', 8, 17, 'project', 'pr_po', NULL, '2026-02-19', 'approved', 101.00, NULL, '2026-02-19 09:47:03', '2026-02-19 09:48:28'),
(222, 'PR-2026-0059', 12, 17, 'project', 'pr_po', NULL, '2026-02-19', 'completed', 432.00, NULL, '2026-02-19 10:01:50', '2026-02-19 10:03:40'),
(223, 'PR-2026-0060', 10, 17, 'project', 'pr_po', NULL, '2026-02-19', 'completed', 242.00, NULL, '2026-02-19 10:11:34', '2026-02-19 10:14:05'),
(224, 'PR-2026-0061', 8, 17, 'project', 'ws', NULL, '2026-02-19', 'completed', 0.00, NULL, '2026-02-19 10:14:19', '2026-02-19 10:15:58'),
(225, 'PR-2026-0062', 10, 17, 'project', 'ws', NULL, '2026-02-20', 'pending', 0.00, NULL, '2026-02-20 10:00:36', '2026-02-20 10:00:36'),
(226, 'PR-2026-0063', 10, NULL, 'supplier', 'pr_po', 9, '2026-02-20', 'pending', 0.00, NULL, '2026-02-20 11:20:51', '2026-02-20 11:20:51'),
(227, 'PR-2026-0064', 10, NULL, 'supplier', 'pr_po', NULL, '2026-02-20', 'pending', 0.00, NULL, '2026-02-20 11:21:06', '2026-02-20 11:21:06'),
(228, 'PR-2026-0065', 10, 17, 'project', 'pr_po', NULL, '2026-02-20', 'pending', 0.00, NULL, '2026-02-20 11:22:19', '2026-02-20 11:22:19'),
(229, 'PR-2026-0066', 7, 17, 'project', 'ws', NULL, '2026-02-27', 'pending', 0.00, NULL, '2026-02-27 05:54:21', '2026-02-27 05:54:21'),
(230, 'PR-2026-0067', 7, NULL, 'supplier', 'pr_po', 9, '2026-02-27', 'processing', 150.00, NULL, '2026-02-27 05:54:42', '2026-02-27 14:07:10'),
(231, 'PR-2026-0068', 7, 17, 'project', 'ws', NULL, '2026-02-27', 'pending', 0.00, NULL, '2026-02-27 06:45:25', '2026-10-01 14:01:23'),
(232, 'PR-2026-0069', 7, NULL, 'supplier', 'pr_po', 9, '2026-02-27', 'processing', 121.00, NULL, '2026-02-27 06:45:37', '2026-02-27 14:02:39'),
(233, 'PR-2026-0070', 7, NULL, 'supplier', 'pr_po', 9, '2026-02-27', 'processing', 12.00, NULL, '2026-02-27 14:09:16', '2026-02-27 14:10:12'),
(234, 'PR-2026-0071', 7, NULL, 'supplier', 'pr_po', 9, '2026-02-27', 'processing', 22.00, NULL, '2026-02-27 14:10:53', '2026-02-27 14:11:33'),
(235, 'PR-2026-0072', 10, NULL, 'supplier', 'pr_po', NULL, '2026-03-27', 'processing', 0.00, NULL, '2026-03-27 03:16:54', '2026-03-27 03:17:23'),
(236, 'PR-2026-0073', 12, 19, 'project', 'ws', NULL, '2026-04-15', 'completed', 0.00, NULL, '2026-04-15 07:15:26', '2026-04-15 07:24:57'),
(238, 'PR-2026-0074', 15, 19, 'project', 'ws', NULL, '2026-10-01', 'processing', 0.00, NULL, '2026-10-01 05:11:31', '2026-10-01 12:16:54'),
(239, 'PR-2026-0075', 15, 19, 'project', 'ws', NULL, '2026-10-01', 'approved', 0.00, NULL, '2026-10-01 06:05:01', '2026-10-01 12:42:34'),
(248, 'VERIFY-WHY-1790857841', 1, NULL, 'project', 'pr_po', NULL, '2026-10-01', 'processing', 0.00, NULL, '2026-10-01 12:30:41', '2026-10-01 12:30:41'),
(269, 'PR-2026-0077', 10, 19, 'project', 'ws', NULL, '2026-10-01', 'approved', 0.00, NULL, '2026-10-01 12:50:36', '2026-10-01 12:52:40'),
(275, 'PR-2026-0078', 15, NULL, 'supplier', 'pr_po', 9, '2026-10-01', 'processing', 0.00, NULL, '2026-10-01 14:07:11', '2026-10-01 14:07:18');

-- --------------------------------------------------------

--
-- Table structure for table `spare_parts`
--

CREATE TABLE `spare_parts` (
  `id` int NOT NULL,
  `part_number` varchar(50) NOT NULL,
  `part_name` varchar(255) NOT NULL,
  `description` text,
  `category_id` int NOT NULL,
  `unit_of_measure` varchar(20) DEFAULT 'pcs',
  `specifications` text,
  `min_stock_level` int DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `spare_parts`
--

INSERT INTO `spare_parts` (`id`, `part_number`, `part_name`, `description`, `category_id`, `unit_of_measure`, `specifications`, `min_stock_level`, `created_at`, `updated_at`) VALUES
(2, '0012', 'Motolite 2sm', 'Motolite 2sm', 1, 'box', 'Motolite 2sm', 0, '2025-09-10 02:05:54', '2026-02-19 06:59:24'),
(10, '001', 'Motolite 3sm', 'ewrefsd', 1, 'set', 'sdf', 0, '2025-09-26 20:16:35', '2026-02-17 09:59:28'),
(11, '2255', 'PALA', '', 6, 'set', '', 5, '2026-02-04 06:04:19', '2026-02-19 06:52:26'),
(13, 'S-1', 'asda', NULL, 1, 'pcs', NULL, 5, '2026-03-27 05:43:50', '2026-03-27 05:43:50');

-- --------------------------------------------------------

--
-- Table structure for table `spare_parts_batches`
--

CREATE TABLE `spare_parts_batches` (
  `id` int NOT NULL,
  `part_id` int NOT NULL,
  `quantity` decimal(10,2) NOT NULL DEFAULT '0.00',
  `price_per_unit` decimal(10,2) NOT NULL,
  `date_received` date NOT NULL,
  `purchase_order` varchar(100) DEFAULT NULL,
  `purchase_request` varchar(100) DEFAULT NULL,
  `batch_number` varchar(100) DEFAULT NULL,
  `supplier_id` int DEFAULT NULL,
  `notes` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `pr_item_id` int DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `spare_parts_batches`
--

INSERT INTO `spare_parts_batches` (`id`, `part_id`, `quantity`, `price_per_unit`, `date_received`, `purchase_order`, `purchase_request`, `batch_number`, `supplier_id`, `notes`, `created_at`, `updated_at`, `pr_item_id`) VALUES
(86, 2, 98.00, 100.00, '2026-02-19', NULL, NULL, NULL, NULL, 'Initial stock', '2026-02-19 11:04:12', '2026-10-01 14:02:17', NULL),
(87, 10, 98.00, 102.00, '2026-02-19', NULL, NULL, NULL, NULL, 'Initial stock', '2026-02-19 11:04:21', '2026-02-19 11:37:15', NULL),
(88, 11, 98.00, 103.00, '2026-02-19', NULL, NULL, NULL, NULL, 'Initial stock', '2026-02-19 11:04:29', '2026-03-23 08:51:52', NULL),
(89, 2, 1.00, 111.00, '2026-02-19', 'PO-2026-0001', 'SPR-2026-0001', 'BATCH-02-19-2026-110822-46', 9, NULL, '2026-02-19 11:08:25', '2026-02-19 11:08:25', 207),
(90, 2, 1.00, 11.00, '2026-02-20', 'PO-2026-0002', 'SPR-2026-0005', 'BATCH-02-20-2026-102408-47', 9, NULL, '2026-02-20 10:25:36', '2026-02-20 10:25:36', 225),
(91, 2, 1.00, 150.00, '2026-03-23', '000002', 'SPR-2026-0010', 'BATCH-03-23-2026-085019-49', 1, NULL, '2026-03-23 08:50:27', '2026-03-23 08:50:27', 243),
(92, 2, 1.00, 900.00, '2026-03-27', '000003', 'SPR-2026-0011', 'BATCH-03-27-2026-034831-50', 1, NULL, '2026-03-27 03:49:18', '2026-03-27 03:49:18', 246),
(93, 11, 1.00, 22.00, '2026-04-30', NULL, NULL, NULL, NULL, 'Initial stock', '2026-04-30 02:57:25', '2026-04-30 02:57:25', NULL),
(120, 13, 0.00, 23.00, '2026-10-01', NULL, NULL, NULL, NULL, 'Initial stock', '2026-10-01 05:38:01', '2026-10-01 11:20:18', NULL),
(126, 11, 1.00, 100.00, '2026-10-01', NULL, NULL, NULL, NULL, 'Initial stock', '2026-10-01 09:31:23', '2026-10-01 09:31:23', NULL),
(127, 11, 1.00, 12.00, '2026-10-01', NULL, NULL, NULL, NULL, 'Initial stock', '2026-10-01 09:32:49', '2026-10-01 09:32:49', NULL),
(130, 11, 4.00, 1222.00, '2026-10-01', NULL, NULL, NULL, NULL, 'Initial stock', '2026-10-01 10:01:45', '2026-10-01 10:01:45', NULL),
(131, 13, 10.00, 10.00, '2026-10-01', NULL, NULL, NULL, NULL, 'Initial stock', '2026-10-01 10:04:30', '2026-10-01 10:04:30', NULL),
(132, 13, 5.00, 10.00, '2026-10-01', NULL, NULL, NULL, NULL, 'Initial stock', '2026-10-01 10:04:40', '2026-10-01 10:04:40', NULL),
(133, 13, 5.00, 5.00, '2026-10-01', NULL, NULL, NULL, NULL, 'Initial stock', '2026-10-01 10:05:16', '2026-10-01 10:05:16', NULL),
(135, 13, 0.00, 10.00, '2026-10-01', NULL, NULL, NULL, NULL, 'Initial stock', '2026-10-01 11:21:16', '2026-10-01 11:22:13', NULL),
(136, 13, 0.00, 132.00, '2026-10-01', NULL, NULL, NULL, NULL, 'Initial stock', '2026-10-01 11:22:28', '2026-10-01 11:25:08', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `spare_parts_categories`
--

CREATE TABLE `spare_parts_categories` (
  `id` int NOT NULL,
  `category_name` varchar(100) NOT NULL,
  `description` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `spare_parts_categories`
--

INSERT INTO `spare_parts_categories` (`id`, `category_name`, `description`, `created_at`, `updated_at`) VALUES
(1, 'Battery', 'Battery', '2025-09-10 00:14:53', '2025-09-23 09:29:21'),
(6, 'Materials', 'asds', '2026-02-04 06:03:09', '2026-10-01 04:59:32');

-- --------------------------------------------------------

--
-- Table structure for table `spare_parts_inventory`
--

CREATE TABLE `spare_parts_inventory` (
  `id` int NOT NULL,
  `part_id` int NOT NULL,
  `quantity` decimal(10,2) NOT NULL DEFAULT '0.00',
  `price_per_unit` decimal(10,2) NOT NULL DEFAULT '0.00',
  `last_updated` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `spare_parts_inventory`
--

INSERT INTO `spare_parts_inventory` (`id`, `part_id`, `quantity`, `price_per_unit`, `last_updated`) VALUES
(50, 2, 102.00, 107.64, '2026-10-01 14:02:17'),
(51, 10, 98.00, 102.00, '2026-02-19 11:37:15'),
(52, 11, 105.00, 1222.00, '2026-10-01 10:25:24'),
(80, 13, 5.00, 132.00, '2026-10-01 11:25:08');

-- --------------------------------------------------------

--
-- Table structure for table `spare_parts_job_orders`
--

CREATE TABLE `spare_parts_job_orders` (
  `id` int NOT NULL,
  `job_order_number` varchar(50) NOT NULL,
  `pr_id` int NOT NULL,
  `technician` int DEFAULT NULL,
  `purpose` text,
  `job_order_date` date DEFAULT NULL,
  `status` varchar(50) DEFAULT 'draft',
  `remarks` text,
  `created_by` int NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `spare_parts_job_orders`
--

INSERT INTO `spare_parts_job_orders` (`id`, `job_order_number`, `pr_id`, `technician`, `purpose`, `job_order_date`, `status`, `remarks`, `created_by`, `created_at`) VALUES
(31, 'JO-2026-0001', 219, 7, 'wdasd', '2026-02-19', 'confirmed', '', 7, '2026-02-19 11:31:39'),
(32, 'JO-2026-0002', 213, 7, '1', '2026-02-19', 'confirmed', '', 7, '2026-02-19 11:36:44'),
(33, 'JO-2026-0003', 248, 7, 'asd', '2026-03-23', 'confirmed', 'sdw', 7, '2026-03-23 08:46:30'),
(34, 'JO-2026-0004', 251, 7, 'LOWBAT', '2026-03-27', 'confirmed', '', 7, '2026-03-27 03:53:47');

-- --------------------------------------------------------

--
-- Table structure for table `spare_parts_job_order_items`
--

CREATE TABLE `spare_parts_job_order_items` (
  `id` int NOT NULL,
  `job_order_id` int NOT NULL,
  `pr_item_id` int NOT NULL,
  `part_id` int NOT NULL,
  `quantity` decimal(10,2) NOT NULL,
  `unit_cost` decimal(10,2) DEFAULT NULL,
  `total_cost` decimal(10,2) DEFAULT NULL,
  `status` varchar(50) DEFAULT 'pending',
  `released_date` date DEFAULT NULL,
  `remarks` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `spare_parts_job_order_items`
--

INSERT INTO `spare_parts_job_order_items` (`id`, `job_order_id`, `pr_item_id`, `part_id`, `quantity`, `unit_cost`, `total_cost`, `status`, `released_date`, `remarks`, `created_at`) VALUES
(30, 31, 223, 10, 1.00, 102.00, 102.00, 'confirmed', '2026-02-19', NULL, '2026-02-19 11:31:39'),
(31, 32, 216, 2, 1.00, 100.00, 100.00, 'confirmed', '2026-02-19', NULL, '2026-02-19 11:36:44'),
(32, 32, 217, 10, 1.00, 102.00, 102.00, 'confirmed', '2026-02-19', NULL, '2026-02-19 11:36:44'),
(33, 33, 244, 2, 1.00, 100.00, 100.00, 'confirmed', '2026-03-23', NULL, '2026-03-23 08:46:30'),
(34, 34, 247, 2, 1.00, 100.00, 100.00, 'confirmed', '2026-03-27', NULL, '2026-03-27 03:53:47');

-- --------------------------------------------------------

--
-- Table structure for table `spare_parts_min_levels`
--

CREATE TABLE `spare_parts_min_levels` (
  `id` int NOT NULL,
  `part_id` int NOT NULL,
  `min_stock` int NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `spare_parts_movements`
--

CREATE TABLE `spare_parts_movements` (
  `id` int NOT NULL,
  `part_id` int NOT NULL,
  `employee_id` int DEFAULT NULL,
  `pr_item_id` int DEFAULT NULL,
  `quantity` decimal(10,2) NOT NULL DEFAULT '0.00',
  `price_per_unit` decimal(10,2) NOT NULL,
  `movement_type` enum('in','out') NOT NULL,
  `movement_date` date NOT NULL,
  `supplier_id` int DEFAULT NULL,
  `vehicle_id` int DEFAULT NULL,
  `equipment_id` int DEFAULT NULL,
  `technician` int DEFAULT NULL,
  `purpose` text,
  `work_order` varchar(100) DEFAULT NULL,
  `purchase_order` varchar(100) DEFAULT NULL,
  `purchase_request` varchar(100) DEFAULT NULL,
  `batch_number` varchar(100) DEFAULT NULL,
  `batch_id` int DEFAULT NULL,
  `notes` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `spare_parts_movements`
--

INSERT INTO `spare_parts_movements` (`id`, `part_id`, `employee_id`, `pr_item_id`, `quantity`, `price_per_unit`, `movement_type`, `movement_date`, `supplier_id`, `vehicle_id`, `equipment_id`, `technician`, `purpose`, `work_order`, `purchase_order`, `purchase_request`, `batch_number`, `batch_id`, `notes`, `created_at`) VALUES
(167, 2, NULL, NULL, 100.00, 100.00, 'in', '2026-02-19', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Initial stock', '2026-02-19 11:04:12'),
(168, 10, NULL, NULL, 100.00, 102.00, 'in', '2026-02-19', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Initial stock', '2026-02-19 11:04:21'),
(169, 11, NULL, NULL, 100.00, 103.00, 'in', '2026-02-19', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Initial stock', '2026-02-19 11:04:29'),
(170, 2, NULL, 207, 1.00, 111.00, 'in', '2026-02-19', 9, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-02-19 11:08:25'),
(171, 10, NULL, 223, 1.00, 102.00, 'out', '2026-02-19', NULL, 2, NULL, 7, 'wdasd', NULL, NULL, 'IPPR-2026-0005', NULL, 87, 'asd', '2026-02-19 11:32:32'),
(172, 2, NULL, 216, 1.00, 100.00, 'out', '2026-02-19', NULL, NULL, 2, 7, '1', NULL, NULL, 'IPPR-2026-0002', NULL, 86, 'asdw', '2026-02-19 11:37:15'),
(173, 10, NULL, 217, 1.00, 102.00, 'out', '2026-02-19', NULL, NULL, 2, 7, '1', NULL, NULL, 'IPPR-2026-0002', NULL, 87, 'asdw', '2026-02-19 11:37:15'),
(174, 11, 4, 212, 1.00, 103.00, 'out', '2026-02-20', NULL, NULL, NULL, NULL, 'asdw', NULL, NULL, 'PRWS-2026-0003', NULL, 88, 'asd', '2026-02-20 10:25:09'),
(175, 2, NULL, 225, 1.00, 11.00, 'in', '2026-02-20', 9, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-02-20 10:25:36'),
(176, 2, NULL, 243, 1.00, 150.00, 'in', '2026-03-23', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-03-23 08:50:27'),
(178, 11, 5, 245, 1.00, 103.00, 'out', '2026-03-23', NULL, NULL, NULL, NULL, 'asd', NULL, NULL, 'PRWS-2026-0014', NULL, 88, 'asd', '2026-03-23 08:51:52'),
(179, 2, NULL, 246, 1.00, 900.00, 'in', '2026-03-27', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-03-27 03:49:18'),
(180, 2, NULL, 247, 1.00, 100.00, 'out', '2026-03-27', NULL, 2, NULL, 7, 'LOWBAT', NULL, NULL, 'IPPR-2026-0011', NULL, 86, 'OKAY NA', '2026-03-27 03:56:38'),
(181, 11, NULL, NULL, 1.00, 22.00, 'in', '2026-04-30', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Initial stock', '2026-04-30 02:57:25'),
(215, 11, NULL, NULL, 1.00, 200.00, 'in', '2026-10-01', NULL, NULL, NULL, NULL, '', NULL, NULL, NULL, NULL, NULL, 'Initial stock', '2026-10-01 09:31:23'),
(229, 11, NULL, NULL, 1.00, 100.00, 'in', '2026-10-01', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 126, 'Initial stock', '2026-10-01 09:31:23'),
(230, 11, NULL, NULL, 1.00, 12.00, 'in', '2026-10-01', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 127, 'Initial stock', '2026-10-01 09:32:49'),
(231, 11, NULL, NULL, 4.00, 1222.00, 'in', '2026-10-01', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 130, 'Initial stock', '2026-10-01 10:01:45');

-- --------------------------------------------------------

--
-- Table structure for table `spare_parts_pr`
--

CREATE TABLE `spare_parts_pr` (
  `id` int NOT NULL,
  `pr_number` varchar(50) NOT NULL,
  `requested_by` int NOT NULL,
  `request_type` varchar(20) DEFAULT 'stock',
  `employee_id` int DEFAULT NULL,
  `supplier_id` int DEFAULT NULL,
  `vehicle_id` int DEFAULT NULL,
  `equipment_id` int DEFAULT NULL,
  `technician` int DEFAULT NULL,
  `driver_id` int DEFAULT NULL,
  `purpose` text,
  `work_order` varchar(100) DEFAULT NULL,
  `request_date` date NOT NULL,
  `expected_delivery_date` date DEFAULT NULL,
  `status` enum('pending','approved','rejected','processing','completed') DEFAULT 'pending',
  `remarks` text,
  `total_estimated_cost` decimal(15,2) DEFAULT '0.00',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `spare_parts_pr`
--

INSERT INTO `spare_parts_pr` (`id`, `pr_number`, `requested_by`, `request_type`, `employee_id`, `supplier_id`, `vehicle_id`, `equipment_id`, `technician`, `driver_id`, `purpose`, `work_order`, `request_date`, `expected_delivery_date`, `status`, `remarks`, `total_estimated_cost`, `created_at`, `updated_at`) VALUES
(205, 'SPR-2026-0001', 10, 'stock', NULL, 9, NULL, NULL, NULL, NULL, NULL, NULL, '2026-02-19', NULL, 'completed', '', 111.00, '2026-02-19 11:04:46', '2026-02-19 11:08:32'),
(206, 'IPPR-2026-0001', 10, 'issue', NULL, NULL, 2, NULL, 7, 5, 'asd', NULL, '2026-02-19', NULL, 'pending', '', 0.00, '2026-02-19 11:05:00', '2026-02-19 11:05:00'),
(207, 'PRWS-2026-0001', 10, 'issue_materials', 4, NULL, NULL, NULL, NULL, NULL, 'asdw', NULL, '2026-02-19', NULL, 'pending', '', 0.00, '2026-02-19 11:05:17', '2026-02-19 11:05:17'),
(208, 'PRWS-2026-0002', 10, 'issue_materials', 4, NULL, NULL, NULL, NULL, NULL, 'asd', NULL, '2026-02-19', NULL, 'pending', '', 0.00, '2026-02-19 11:05:26', '2026-02-19 11:05:26'),
(209, 'PRWS-2026-0003', 10, 'issue_materials', 4, NULL, NULL, NULL, NULL, NULL, 'asdw', NULL, '2026-02-19', NULL, 'approved', '', 0.00, '2026-02-19 11:05:34', '2026-02-19 11:24:55'),
(210, 'PRWS-2026-0004', 10, 'issue_materials', 4, NULL, NULL, NULL, NULL, NULL, 'asdw', NULL, '2026-02-19', NULL, 'pending', '', 0.00, '2026-02-19 11:05:42', '2026-02-19 11:05:42'),
(211, 'PRWS-2026-0005', 10, 'issue_materials', 4, NULL, NULL, NULL, NULL, NULL, 'asdw', NULL, '2026-02-19', NULL, 'pending', '', 0.00, '2026-02-19 11:05:48', '2026-02-19 11:05:48'),
(212, 'PRWS-2026-0006', 10, 'issue_materials', 4, NULL, NULL, NULL, NULL, NULL, 'asdsa', NULL, '2026-02-19', NULL, 'pending', '', 0.00, '2026-02-19 11:05:55', '2026-02-19 11:05:55'),
(213, 'IPPR-2026-0002', 10, 'issue', NULL, NULL, NULL, 2, 7, 5, '1', NULL, '2026-02-19', NULL, 'completed', '', 0.00, '2026-02-19 11:06:10', '2026-02-19 11:37:19'),
(214, 'IPPR-2026-0003', 10, 'issue', NULL, NULL, 2, NULL, 7, 5, 'asd', NULL, '2026-02-19', NULL, 'pending', '', 0.00, '2026-02-19 11:06:21', '2026-02-19 11:06:21'),
(215, 'IPPR-2026-0004', 10, 'issue', NULL, NULL, 2, NULL, 7, 5, 'sawe1qw', NULL, '2026-02-19', NULL, 'pending', '', 0.00, '2026-02-19 11:06:31', '2026-02-19 11:06:31'),
(216, 'SPR-2026-0002', 10, 'stock', NULL, 9, NULL, NULL, NULL, NULL, NULL, NULL, '2026-02-19', NULL, 'pending', '', 150.00, '2026-02-19 11:15:13', '2026-02-19 11:15:13'),
(217, 'SPR-2026-0003', 10, 'stock', NULL, 1, NULL, NULL, NULL, NULL, NULL, NULL, '2026-02-19', NULL, 'pending', '', 111.00, '2026-02-19 11:15:26', '2026-02-19 11:15:26'),
(218, 'SPR-2026-0004', 12, 'stock', NULL, 9, NULL, NULL, NULL, NULL, NULL, NULL, '2026-02-19', NULL, 'pending', '', 121.00, '2026-02-19 11:29:31', '2026-02-19 11:29:31'),
(219, 'IPPR-2026-0005', 12, 'issue', NULL, NULL, 2, NULL, 7, 5, 'wdasd', NULL, '2026-02-19', NULL, 'completed', '', 0.00, '2026-02-19 11:29:40', '2026-02-19 11:32:37'),
(220, 'PRWS-2026-0007', 12, 'issue_materials', 4, NULL, NULL, NULL, NULL, NULL, 'asd', NULL, '2026-02-19', NULL, 'pending', '', 0.00, '2026-02-19 11:29:48', '2026-02-19 11:29:48'),
(221, 'SPR-2026-0005', 10, 'stock', NULL, 9, NULL, NULL, NULL, NULL, NULL, NULL, '2026-02-19', NULL, 'approved', '', 11.00, '2026-02-19 11:33:05', '2026-02-19 11:35:44'),
(222, 'IPPR-2026-0006', 10, 'issue', NULL, NULL, 2, NULL, 7, 5, '1', NULL, '2026-02-19', NULL, 'pending', '', 0.00, '2026-02-19 11:37:47', '2026-02-19 11:37:47'),
(224, 'IPPR-2026-0007', 10, 'issue', NULL, NULL, NULL, 2, 7, 5, 'asd', NULL, '2026-02-19', NULL, 'processing', '', 0.00, '2026-02-19 11:39:15', '2026-02-19 14:31:14'),
(225, 'SPR-2026-0006', 10, 'stock', NULL, 9, NULL, NULL, NULL, NULL, NULL, NULL, '2026-02-19', NULL, 'processing', '', 111.00, '2026-02-19 14:23:36', '2026-02-26 03:39:34'),
(231, 'SPR-2026-0007', 10, 'stock', NULL, 9, NULL, NULL, NULL, NULL, NULL, NULL, '2026-02-19', NULL, 'pending', '', 0.00, '2026-02-19 15:16:06', '2026-02-19 15:16:06'),
(232, 'IPPR-2026-0008', 10, 'issue', NULL, NULL, 2, NULL, 7, 5, '1', NULL, '2026-02-19', NULL, 'pending', '', 0.00, '2026-02-19 15:16:25', '2026-02-19 15:16:25'),
(234, 'PRWS-2026-0008', 10, 'issue_materials', 5, NULL, NULL, NULL, NULL, NULL, 'Replacement', NULL, '2026-02-19', NULL, 'pending', '', 0.00, '2026-02-19 15:21:53', '2026-02-19 15:21:53'),
(235, 'PRWS-2026-0009', 10, 'issue_materials', 5, NULL, NULL, NULL, NULL, NULL, 'asd', NULL, '2026-02-19', NULL, 'pending', '', 0.00, '2026-02-19 15:22:15', '2026-02-19 15:22:15'),
(236, 'PRWS-2026-0010', 10, 'issue_materials', 5, NULL, NULL, NULL, NULL, NULL, '11', NULL, '2026-02-19', NULL, 'pending', '', 0.00, '2026-02-19 15:22:34', '2026-02-19 15:22:34'),
(237, 'PRWS-2026-0011', 10, 'issue_materials', 5, NULL, NULL, NULL, NULL, NULL, '1', NULL, '2026-02-19', NULL, 'pending', '', 0.00, '2026-02-19 15:22:56', '2026-02-19 15:22:56'),
(239, 'SPR-2026-0008', 7, 'stock', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-02-26', NULL, 'pending', '', 0.00, '2026-02-26 06:19:32', '2026-02-26 06:19:32'),
(241, 'PRWS-2026-0012', 7, 'issue_materials', 5, NULL, NULL, NULL, NULL, NULL, 'asdw', NULL, '2026-02-26', NULL, 'pending', '', 0.00, '2026-02-26 06:21:04', '2026-02-26 06:21:04'),
(244, 'SPR-2026-0009', 7, 'stock', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-02-26', NULL, 'pending', '', 0.00, '2026-02-26 06:27:29', '2026-02-26 06:27:29'),
(245, 'PRWS-2026-0013', 7, 'issue_materials', 4, NULL, 2, NULL, 7, 5, 'asd', NULL, '2026-02-26', NULL, 'pending', '', 0.00, '2026-02-26 06:27:55', '2026-02-26 06:27:55'),
(246, 'IPPR-2026-0009', 7, 'issue', NULL, NULL, 2, NULL, 7, 5, 'asd', NULL, '2026-02-26', NULL, 'pending', '', 0.00, '2026-02-26 06:28:08', '2026-02-26 06:28:08'),
(247, 'SPR-2026-0010', 13, 'stock', NULL, 1, NULL, NULL, NULL, NULL, NULL, NULL, '2026-03-23', NULL, 'completed', '', 150.00, '2026-03-23 08:41:51', '2026-03-23 08:50:31'),
(248, 'IPPR-2026-0010', 13, 'issue', NULL, NULL, 2, NULL, 7, 5, 'asd', NULL, '2026-03-23', NULL, 'completed', '', 0.00, '2026-03-23 08:44:05', '2026-03-23 08:51:23'),
(249, 'PRWS-2026-0014', 13, 'issue_materials', 5, NULL, NULL, NULL, NULL, NULL, 'asd', NULL, '2026-03-23', NULL, 'completed', '', 0.00, '2026-03-23 08:45:15', '2026-03-23 08:51:58'),
(250, 'SPR-2026-0011', 13, 'stock', NULL, 1, NULL, NULL, NULL, NULL, NULL, NULL, '2026-03-27', NULL, 'completed', 'WALA NA BOSS', 900.00, '2026-03-27 03:42:52', '2026-03-27 03:49:47'),
(251, 'IPPR-2026-0011', 13, 'issue', NULL, NULL, 2, NULL, 7, 5, 'LOWBAT', NULL, '2026-03-27', NULL, 'completed', '', 0.00, '2026-03-27 03:52:22', '2026-03-27 03:56:50'),
(252, 'PRWS-2026-0015', 13, 'issue_materials', 1, NULL, NULL, NULL, NULL, NULL, 'asd', NULL, '2026-03-27', NULL, 'pending', 'NJH', 0.00, '2026-03-27 04:03:33', '2026-10-01 14:01:25'),
(256, 'SPR-2026-0012', 15, 'stock', NULL, 1, NULL, NULL, NULL, NULL, NULL, NULL, '2026-10-01', NULL, 'processing', '', 0.00, '2026-10-01 05:08:50', '2026-10-01 05:55:07');

-- --------------------------------------------------------

--
-- Table structure for table `spare_parts_pr_items`
--

CREATE TABLE `spare_parts_pr_items` (
  `id` int NOT NULL,
  `pr_id` int NOT NULL,
  `part_id` int NOT NULL,
  `vehicle_id` int DEFAULT NULL,
  `equipment_id` int DEFAULT NULL,
  `technician` int DEFAULT NULL,
  `driver_id` int DEFAULT NULL,
  `purpose` text,
  `employee_id` int DEFAULT NULL,
  `work_order` varchar(100) DEFAULT NULL,
  `quantity` decimal(10,2) NOT NULL DEFAULT '0.00',
  `delivered_quantity` decimal(10,2) DEFAULT '0.00',
  `unit_cost` decimal(15,2) DEFAULT NULL,
  `total_cost` decimal(15,2) GENERATED ALWAYS AS ((`quantity` * coalesce(`unit_cost`,0))) STORED,
  `required_by_date` date DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `spare_parts_pr_items`
--

INSERT INTO `spare_parts_pr_items` (`id`, `pr_id`, `part_id`, `vehicle_id`, `equipment_id`, `technician`, `driver_id`, `purpose`, `employee_id`, `work_order`, `quantity`, `delivered_quantity`, `unit_cost`, `required_by_date`, `created_at`) VALUES
(207, 205, 2, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1.00, 0.00, 111.00, NULL, '2026-02-19 11:04:46'),
(208, 206, 2, 2, NULL, 7, 5, 'asd', NULL, NULL, 1.00, 0.00, 0.00, NULL, '2026-02-19 11:05:00'),
(209, 206, 10, 2, NULL, 7, 5, 'asd', NULL, NULL, 1.00, 0.00, 0.00, NULL, '2026-02-19 11:05:00'),
(210, 207, 11, NULL, NULL, NULL, NULL, 'asdw', 4, NULL, 1.00, 0.00, 0.00, NULL, '2026-02-19 11:05:17'),
(211, 208, 11, NULL, NULL, NULL, NULL, 'asd', 4, NULL, 1.00, 0.00, 0.00, NULL, '2026-02-19 11:05:26'),
(212, 209, 11, NULL, NULL, NULL, NULL, 'asdw', 4, NULL, 1.00, 1.00, 0.00, NULL, '2026-02-19 11:05:34'),
(213, 210, 11, NULL, NULL, NULL, NULL, 'asdw', 4, NULL, 1.00, 0.00, 0.00, NULL, '2026-02-19 11:05:42'),
(214, 211, 11, NULL, NULL, NULL, NULL, 'asdw', 4, NULL, 1.00, 0.00, 0.00, NULL, '2026-02-19 11:05:48'),
(215, 212, 11, NULL, NULL, NULL, NULL, 'asdsa', 4, NULL, 1.00, 0.00, 0.00, NULL, '2026-02-19 11:05:55'),
(216, 213, 2, NULL, 2, 7, 5, '1', NULL, NULL, 1.00, 1.00, 0.00, NULL, '2026-02-19 11:06:10'),
(217, 213, 10, NULL, 2, 7, 5, '1', NULL, NULL, 1.00, 1.00, 0.00, NULL, '2026-02-19 11:06:10'),
(218, 214, 2, 2, NULL, 7, 5, 'asd', NULL, NULL, 1.00, 0.00, 0.00, NULL, '2026-02-19 11:06:21'),
(219, 215, 10, 2, NULL, 7, 5, 'sawe1qw', NULL, NULL, 1.00, 0.00, 0.00, NULL, '2026-02-19 11:06:31'),
(220, 216, 2, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1.00, 0.00, 150.00, NULL, '2026-02-19 11:15:13'),
(221, 217, 2, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1.00, 0.00, 111.00, NULL, '2026-02-19 11:15:26'),
(222, 218, 2, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1.00, 0.00, 121.00, NULL, '2026-02-19 11:29:31'),
(223, 219, 10, 2, NULL, 7, 5, 'wdasd', NULL, NULL, 1.00, 1.00, 0.00, NULL, '2026-02-19 11:29:40'),
(224, 220, 11, NULL, NULL, NULL, NULL, 'asd', 4, NULL, 2.00, 0.00, 0.00, NULL, '2026-02-19 11:29:48'),
(225, 221, 2, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1.00, 0.00, 11.00, NULL, '2026-02-19 11:33:05'),
(226, 222, 10, 2, NULL, 7, 5, '1', NULL, NULL, 1.00, 0.00, 0.00, NULL, '2026-02-19 11:37:47'),
(228, 224, 2, NULL, 2, 7, 5, 'asd', NULL, NULL, 1.00, 0.00, 0.00, NULL, '2026-02-19 11:39:15'),
(229, 225, 2, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1.00, 0.00, 111.00, NULL, '2026-02-19 14:23:36'),
(231, 231, 2, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1.00, 0.00, 0.00, NULL, '2026-02-19 15:16:06'),
(232, 232, 2, 2, NULL, 7, 5, '1', NULL, NULL, 1.00, 0.00, 0.00, NULL, '2026-02-19 15:16:25'),
(233, 234, 11, NULL, NULL, NULL, NULL, 'Replacement', 5, NULL, 1.00, 0.00, 0.00, NULL, '2026-02-19 15:21:53'),
(234, 235, 11, NULL, NULL, NULL, NULL, 'asd', 5, NULL, 1.00, 0.00, 0.00, NULL, '2026-02-19 15:22:15'),
(235, 236, 11, NULL, NULL, NULL, NULL, '11', 5, NULL, 1.00, 0.00, 0.00, NULL, '2026-02-19 15:22:34'),
(236, 237, 11, NULL, NULL, NULL, NULL, '1', 5, NULL, 1.00, 0.00, 0.00, NULL, '2026-02-19 15:22:56'),
(238, 239, 2, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1.00, 0.00, 0.00, NULL, '2026-02-26 06:19:32'),
(239, 241, 11, NULL, NULL, NULL, NULL, 'asdw', 5, NULL, 1.00, 0.00, 0.00, NULL, '2026-02-26 06:21:04'),
(240, 244, 2, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1.00, 0.00, 0.00, NULL, '2026-02-26 06:27:29'),
(241, 245, 11, 2, NULL, 7, 5, 'asd', 4, NULL, 1.00, 0.00, 0.00, NULL, '2026-02-26 06:27:55'),
(242, 246, 2, 2, NULL, 7, 5, 'asd', NULL, NULL, 1.00, 0.00, 0.00, NULL, '2026-02-26 06:28:08'),
(243, 247, 2, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1.00, 0.00, 150.00, NULL, '2026-03-23 08:41:51'),
(244, 248, 2, 2, NULL, 7, 5, 'asd', NULL, NULL, 1.00, 1.00, 0.00, NULL, '2026-03-23 08:44:05'),
(245, 249, 11, NULL, NULL, NULL, NULL, 'asd', 5, NULL, 1.00, 1.00, 0.00, NULL, '2026-03-23 08:45:15'),
(246, 250, 2, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1.00, 0.00, 900.00, NULL, '2026-03-27 03:42:52'),
(247, 251, 2, 2, NULL, 7, 5, 'LOWBAT', NULL, NULL, 1.00, 1.00, 0.00, NULL, '2026-03-27 03:52:22'),
(248, 252, 11, NULL, NULL, NULL, NULL, 'asd', 1, NULL, 1.00, 0.00, 0.00, NULL, '2026-03-27 04:03:33'),
(252, 256, 13, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1.00, 0.00, 0.00, NULL, '2026-10-01 05:08:50');

-- --------------------------------------------------------

--
-- Table structure for table `spare_parts_pr_routing`
--

CREATE TABLE `spare_parts_pr_routing` (
  `id` int NOT NULL,
  `pr_id` int NOT NULL,
  `stage` varchar(50) NOT NULL,
  `status` varchar(50) NOT NULL,
  `action_by` int NOT NULL,
  `remarks` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `spare_parts_pr_routing`
--

INSERT INTO `spare_parts_pr_routing` (`id`, `pr_id`, `stage`, `status`, `action_by`, `remarks`, `created_at`) VALUES
(616, 205, 'warehouse', 'pending', 10, '', '2026-02-19 11:06:56'),
(617, 205, 'purchasing', 'pending', 10, 'asd', '2026-02-19 11:06:59'),
(618, 205, 'purchasing', 'pending', 7, 'Purchase Order PO-2026-0001 created', '2026-02-19 11:07:18'),
(619, 205, 'approver', 'pending', 7, 'asd', '2026-02-19 11:07:22'),
(620, 205, 'warehouse_receiving', 'pending', 12, 'asdw', '2026-02-19 11:07:42'),
(621, 205, 'warehouse_receiving', 'pending', 10, 'asdw', '2026-02-19 11:08:25'),
(622, 205, 'completed', 'completed', 10, 'asdw', '2026-02-19 11:08:32'),
(623, 209, 'warehouse', 'pending', 10, '', '2026-02-19 11:24:01'),
(624, 209, 'purchasing', 'pending', 10, 'wad', '2026-02-19 11:24:05'),
(625, 209, 'purchasing', 'pending', 7, 'Withdrawal Slip WS-2026-0001 created', '2026-02-19 11:24:24'),
(626, 209, 'approver', 'pending', 7, 'asd', '2026-02-19 11:24:33'),
(627, 209, 'warehouse_releasing', 'pending', 12, 'asdw', '2026-02-19 11:24:55'),
(628, 219, 'warehouse', 'pending', 12, 'asd', '2026-02-19 11:30:46'),
(629, 219, 'purchasing', 'pending', 10, 'asdw', '2026-02-19 11:31:20'),
(630, 219, 'purchasing', 'pending', 7, 'Job Order JO-2026-0001 created', '2026-02-19 11:31:39'),
(631, 219, 'approver', 'pending', 7, 'asd', '2026-02-19 11:31:43'),
(632, 219, 'warehouse_releasing', 'pending', 12, 'asdw', '2026-02-19 11:31:56'),
(633, 219, 'warehouse_releasing', 'pending', 10, 'asd', '2026-02-19 11:32:32'),
(634, 219, 'completed', 'completed', 10, 'asd', '2026-02-19 11:32:37'),
(635, 221, 'warehouse', 'pending', 10, '', '2026-02-19 11:33:45'),
(636, 221, 'purchasing', 'pending', 10, 'sd', '2026-02-19 11:33:48'),
(637, 221, 'purchasing', 'pending', 7, 'Purchase Order PO-2026-0002 created', '2026-02-19 11:35:22'),
(638, 221, 'approver', 'pending', 7, 'asd', '2026-02-19 11:35:26'),
(639, 221, 'warehouse_receiving', 'pending', 12, 'asdw', '2026-02-19 11:35:44'),
(640, 213, 'warehouse', 'pending', 10, '', '2026-02-19 11:36:24'),
(641, 213, 'purchasing', 'pending', 10, 'dasd', '2026-02-19 11:36:27'),
(642, 213, 'purchasing', 'pending', 7, 'Job Order JO-2026-0002 created', '2026-02-19 11:36:44'),
(643, 213, 'approver', 'pending', 7, 'sd', '2026-02-19 11:36:48'),
(644, 213, 'warehouse_releasing', 'pending', 12, 'dasd', '2026-02-19 11:37:02'),
(645, 213, 'warehouse_releasing', 'pending', 10, 'asdw', '2026-02-19 11:37:15'),
(646, 213, 'completed', 'completed', 10, 'asd', '2026-02-19 11:37:19'),
(647, 225, 'warehouse', 'pending', 10, '', '2026-02-19 14:29:59'),
(648, 225, 'purchasing', 'pending', 10, 'asd', '2026-02-19 14:30:03'),
(649, 224, 'warehouse', 'pending', 10, '', '2026-02-19 14:31:14'),
(650, 224, 'purchasing', 'pending', 10, 'asdw', '2026-02-19 14:31:18'),
(652, 209, 'warehouse_releasing', 'pending', 10, 'asd', '2026-02-20 10:25:09'),
(653, 221, 'warehouse_receiving', 'pending', 10, 'asdw', '2026-02-20 10:25:36'),
(654, 225, 'purchasing', 'pending', 7, 'Purchase Order 000001 created', '2026-02-26 03:39:34'),
(655, 247, 'warehouse', 'pending', 13, '', '2026-03-23 08:41:58'),
(656, 247, 'purchasing', 'pending', 13, 'asdw', '2026-03-23 08:43:47'),
(657, 248, 'warehouse', 'pending', 13, 'asd', '2026-03-23 08:44:12'),
(658, 248, 'purchasing', 'pending', 13, 'asd', '2026-03-23 08:44:15'),
(659, 249, 'warehouse', 'pending', 13, 'asd', '2026-03-23 08:45:20'),
(660, 249, 'purchasing', 'pending', 13, 'asd', '2026-03-23 08:45:24'),
(661, 247, 'purchasing', 'pending', 7, 'Purchase Order 000002 created', '2026-03-23 08:46:06'),
(662, 247, 'approver', 'pending', 7, 'sd', '2026-03-23 08:46:10'),
(663, 248, 'purchasing', 'pending', 7, 'Job Order JO-2026-0003 created', '2026-03-23 08:46:30'),
(664, 248, 'approver', 'pending', 7, 's', '2026-03-23 08:46:38'),
(665, 249, 'purchasing', 'pending', 7, 'Withdrawal Slip WS-2026-0002 created', '2026-03-23 08:47:02'),
(666, 249, 'approver', 'pending', 7, 'sd', '2026-03-23 08:47:12'),
(667, 247, 'warehouse_receiving', 'pending', 12, 'asd', '2026-03-23 08:48:07'),
(668, 248, 'warehouse_releasing', 'pending', 12, 'asdasd', '2026-03-23 08:48:36'),
(669, 249, 'warehouse_releasing', 'pending', 12, 'asdw', '2026-03-23 08:49:07'),
(670, 247, 'warehouse_receiving', 'pending', 13, 'asd', '2026-03-23 08:50:27'),
(671, 247, 'completed', 'completed', 13, 'sad', '2026-03-23 08:50:31'),
(672, 248, 'warehouse_releasing', 'pending', 13, 'asd', '2026-03-23 08:51:17'),
(673, 248, 'completed', 'completed', 13, 'asd', '2026-03-23 08:51:23'),
(674, 249, 'warehouse_releasing', 'pending', 13, 'asd', '2026-03-23 08:51:52'),
(675, 249, 'completed', 'completed', 13, 'asd', '2026-03-23 08:51:58'),
(676, 250, 'warehouse', 'pending', 13, '', '2026-03-27 03:43:12'),
(677, 250, 'purchasing', 'pending', 13, 'TAGAL AY\r\n', '2026-03-27 03:43:40'),
(678, 250, 'purchasing', 'pending', 7, 'Purchase Order 000003 created', '2026-03-27 03:45:09'),
(679, 250, 'approver', 'pending', 7, 'ASD', '2026-03-27 03:45:17'),
(680, 250, 'warehouse_receiving', 'pending', 12, 'ASD', '2026-03-27 03:47:40'),
(681, 250, 'warehouse_receiving', 'pending', 13, 'OKAY NA BOSS', '2026-03-27 03:49:18'),
(682, 250, 'completed', 'completed', 13, 'ALL GOODS', '2026-03-27 03:49:47'),
(683, 251, 'warehouse', 'pending', 13, '', '2026-03-27 03:52:35'),
(684, 251, 'purchasing', 'pending', 13, 'ASD', '2026-03-27 03:52:45'),
(685, 251, 'purchasing', 'pending', 7, 'Job Order JO-2026-0004 created', '2026-03-27 03:53:47'),
(686, 251, 'approver', 'pending', 7, 'A', '2026-03-27 03:54:16'),
(687, 251, 'warehouse_releasing', 'pending', 12, 'SADW', '2026-03-27 03:54:45'),
(688, 251, 'warehouse_releasing', 'pending', 13, 'OKAY NA', '2026-03-27 03:56:38'),
(689, 251, 'completed', 'completed', 13, 'SD', '2026-03-27 03:56:50'),
(695, 256, 'warehouse', 'pending', 15, '', '2026-10-01 05:55:07'),
(701, 256, 'purchasing', 'pending', 13, 'asdw', '2026-10-01 12:01:25'),
(702, 256, 'purchasing', 'pending', 7, 'Purchase Order 000004 created', '2026-10-01 12:01:57');

-- --------------------------------------------------------

--
-- Table structure for table `spare_parts_pr_routing_history`
--

CREATE TABLE `spare_parts_pr_routing_history` (
  `id` int NOT NULL,
  `pr_id` int NOT NULL,
  `action` varchar(100) NOT NULL,
  `remarks` text,
  `action_by` int NOT NULL,
  `stage_from` varchar(50) DEFAULT NULL,
  `stage_to` varchar(50) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `spare_parts_pr_routing_history`
--

INSERT INTO `spare_parts_pr_routing_history` (`id`, `pr_id`, `action`, `remarks`, `action_by`, `stage_from`, `stage_to`, `created_at`) VALUES
(616, 205, 'Forwarded to Warehouse', '', 10, 'requestor', 'warehouse', '2026-02-19 11:06:56'),
(617, 205, 'Approved by Warehouse', 'asd', 10, 'warehouse', 'purchasing', '2026-02-19 11:06:59'),
(618, 205, 'Purchase Order Created', 'Purchase Order PO-2026-0001 created with 1 items. Supplier updated, total estimated cost: ₱111.00', 7, 'purchasing', 'purchasing', '2026-02-19 11:07:18'),
(619, 205, 'Approved by Purchasing', 'asd', 7, 'purchasing', 'approver', '2026-02-19 11:07:22'),
(620, 205, 'Approved by Approver', 'asdw', 12, 'approver', 'warehouse_receiving', '2026-02-19 11:07:42'),
(621, 205, 'Items Received', 'asdw', 10, 'warehouse_receiving', 'warehouse_receiving', '2026-02-19 11:08:25'),
(622, 205, 'Completed Warehouse Receiving', 'asdw', 10, 'warehouse_receiving', 'completed', '2026-02-19 11:08:32'),
(623, 209, 'Forwarded to Warehouse', '', 10, 'requestor', 'warehouse', '2026-02-19 11:24:01'),
(624, 209, 'Approved by Warehouse', 'wad', 10, 'warehouse', 'purchasing', '2026-02-19 11:24:05'),
(625, 209, 'Withdrawal Slip Created', 'Withdrawal Slip WS-2026-0001 created with 1 items', 7, 'purchasing', 'purchasing', '2026-02-19 11:24:24'),
(626, 209, 'Approved by Purchasing (With Withdrawal Slip)', 'asd', 7, 'purchasing', 'approver', '2026-02-19 11:24:33'),
(627, 209, 'Approved by Approver', 'asdw', 12, 'approver', 'warehouse_releasing', '2026-02-19 11:24:55'),
(628, 219, 'Forwarded to Warehouse', 'asd', 12, 'requestor', 'warehouse', '2026-02-19 11:30:46'),
(629, 219, 'Approved by Warehouse', 'asdw', 10, 'warehouse', 'purchasing', '2026-02-19 11:31:20'),
(630, 219, 'Job Order Created', 'Job Order JO-2026-0001 created with 1 items', 7, 'purchasing', 'purchasing', '2026-02-19 11:31:39'),
(631, 219, 'Approved by Purchasing (With Job Order)', 'asd', 7, 'purchasing', 'approver', '2026-02-19 11:31:43'),
(632, 219, 'Approved by Approver', 'asdw', 12, 'approver', 'warehouse_releasing', '2026-02-19 11:31:56'),
(633, 219, 'Items Released (FIFO)', 'asd', 10, 'warehouse_releasing', 'warehouse_releasing', '2026-02-19 11:32:32'),
(634, 219, 'Completed Warehouse Releasing', 'asd', 10, 'warehouse_releasing', 'completed', '2026-02-19 11:32:37'),
(635, 221, 'Forwarded to Warehouse', '', 10, 'requestor', 'warehouse', '2026-02-19 11:33:45'),
(636, 221, 'Approved by Warehouse', 'sd', 10, 'warehouse', 'purchasing', '2026-02-19 11:33:48'),
(637, 221, 'Purchase Order Created', 'Purchase Order PO-2026-0002 created with 1 items. Supplier updated, total estimated cost: ₱11.00', 7, 'purchasing', 'purchasing', '2026-02-19 11:35:22'),
(638, 221, 'Approved by Purchasing', 'asd', 7, 'purchasing', 'approver', '2026-02-19 11:35:25'),
(639, 221, 'Approved by Approver', 'asdw', 12, 'approver', 'warehouse_receiving', '2026-02-19 11:35:44'),
(640, 213, 'Forwarded to Warehouse', '', 10, 'requestor', 'warehouse', '2026-02-19 11:36:24'),
(641, 213, 'Approved by Warehouse', 'dasd', 10, 'warehouse', 'purchasing', '2026-02-19 11:36:27'),
(642, 213, 'Job Order Created', 'Job Order JO-2026-0002 created with 2 items', 7, 'purchasing', 'purchasing', '2026-02-19 11:36:44'),
(643, 213, 'Approved by Purchasing (With Job Order)', 'sd', 7, 'purchasing', 'approver', '2026-02-19 11:36:48'),
(644, 213, 'Approved by Approver', 'dasd', 12, 'approver', 'warehouse_releasing', '2026-02-19 11:37:02'),
(645, 213, 'Items Released (FIFO)', 'asdw', 10, 'warehouse_releasing', 'warehouse_releasing', '2026-02-19 11:37:15'),
(646, 213, 'Completed Warehouse Releasing', 'asd', 10, 'warehouse_releasing', 'completed', '2026-02-19 11:37:19'),
(647, 225, 'Forwarded to Warehouse', '', 10, 'requestor', 'warehouse', '2026-02-19 14:29:59'),
(648, 225, 'Approved by Warehouse', 'asd', 10, 'warehouse', 'purchasing', '2026-02-19 14:30:03'),
(649, 224, 'Forwarded to Warehouse', '', 10, 'requestor', 'warehouse', '2026-02-19 14:31:14'),
(650, 224, 'Approved by Warehouse', 'asdw', 10, 'warehouse', 'purchasing', '2026-02-19 14:31:18'),
(652, 209, 'Items Released (FIFO)', 'asd', 10, 'warehouse_releasing', 'warehouse_releasing', '2026-02-20 10:25:09'),
(653, 221, 'Items Received', 'asdw', 10, 'warehouse_receiving', 'warehouse_receiving', '2026-02-20 10:25:36'),
(654, 225, 'Purchase Order Created', 'Purchase Order 000001 created with 1 items. Supplier updated, total estimated cost: ₱111.00', 7, 'purchasing', 'purchasing', '2026-02-26 03:39:34'),
(655, 247, 'Forwarded to Motorpool', '', 13, 'requestor', 'warehouse', '2026-03-23 08:41:58'),
(656, 247, 'Approved by Motorpool', 'asdw', 13, 'warehouse', 'purchasing', '2026-03-23 08:43:47'),
(657, 248, 'Forwarded to Motorpool', 'asd', 13, 'requestor', 'warehouse', '2026-03-23 08:44:12'),
(658, 248, 'Approved by Motorpool', 'asd', 13, 'warehouse', 'purchasing', '2026-03-23 08:44:15'),
(659, 249, 'Forwarded to Motorpool', 'asd', 13, 'requestor', 'warehouse', '2026-03-23 08:45:20'),
(660, 249, 'Approved by Motorpool', 'asd', 13, 'warehouse', 'purchasing', '2026-03-23 08:45:24'),
(661, 247, 'Purchase Order Created', 'Purchase Order 000002 created with 1 items. Supplier updated, total estimated cost: ₱150.00', 7, 'purchasing', 'purchasing', '2026-03-23 08:46:06'),
(662, 247, 'Approved by Purchasing', 'sd', 7, 'purchasing', 'approver', '2026-03-23 08:46:10'),
(663, 248, 'Job Order Created', 'Job Order JO-2026-0003 created with 1 items', 7, 'purchasing', 'purchasing', '2026-03-23 08:46:30'),
(664, 248, 'Approved by Purchasing (With Job Order)', 's', 7, 'purchasing', 'approver', '2026-03-23 08:46:38'),
(665, 249, 'Withdrawal Slip Created', 'Withdrawal Slip WS-2026-0002 created with 1 items', 7, 'purchasing', 'purchasing', '2026-03-23 08:47:02'),
(666, 249, 'Approved by Purchasing (With Withdrawal Slip)', 'sd', 7, 'purchasing', 'approver', '2026-03-23 08:47:12'),
(667, 247, 'Approved by Approver', 'asd', 12, 'approver', 'warehouse_receiving', '2026-03-23 08:48:07'),
(668, 248, 'Approved by Approver', 'asdasd', 12, 'approver', 'warehouse_releasing', '2026-03-23 08:48:36'),
(669, 249, 'Approved by Approver', 'asdw', 12, 'approver', 'warehouse_releasing', '2026-03-23 08:49:07'),
(670, 247, 'Items Received', 'asd', 13, 'warehouse_receiving', 'warehouse_receiving', '2026-03-23 08:50:27'),
(671, 247, 'Completed Motorpool Receiving', 'sad', 13, 'warehouse_receiving', 'completed', '2026-03-23 08:50:31'),
(672, 248, 'Items Released (FIFO)', 'asd', 13, 'warehouse_releasing', 'warehouse_releasing', '2026-03-23 08:51:17'),
(673, 248, 'Completed Motorpool Releasing', 'asd', 13, 'warehouse_releasing', 'completed', '2026-03-23 08:51:23'),
(674, 249, 'Items Released (FIFO)', 'asd', 13, 'warehouse_releasing', 'warehouse_releasing', '2026-03-23 08:51:52'),
(675, 249, 'Completed Motorpool Releasing', 'asd', 13, 'warehouse_releasing', 'completed', '2026-03-23 08:51:58'),
(676, 250, 'Forwarded to Motorpool', '', 13, 'requestor', 'warehouse', '2026-03-27 03:43:12'),
(677, 250, 'Approved by Motorpool', 'TAGAL AY\r\n', 13, 'warehouse', 'purchasing', '2026-03-27 03:43:40'),
(678, 250, 'Purchase Order Created', 'Purchase Order 000003 created with 1 items. Supplier updated, total estimated cost: ₱900.00', 7, 'purchasing', 'purchasing', '2026-03-27 03:45:09'),
(679, 250, 'Approved by Purchasing', 'ASD', 7, 'purchasing', 'approver', '2026-03-27 03:45:17'),
(680, 250, 'Approved by Approver', 'ASD', 12, 'approver', 'warehouse_receiving', '2026-03-27 03:47:40'),
(681, 250, 'Items Received', 'OKAY NA BOSS', 13, 'warehouse_receiving', 'warehouse_receiving', '2026-03-27 03:49:18'),
(682, 250, 'Completed Motorpool Receiving', 'ALL GOODS', 13, 'warehouse_receiving', 'completed', '2026-03-27 03:49:47'),
(683, 251, 'Forwarded to Motorpool', '', 13, 'requestor', 'warehouse', '2026-03-27 03:52:35'),
(684, 251, 'Approved by Motorpool', 'ASD', 13, 'warehouse', 'purchasing', '2026-03-27 03:52:45'),
(685, 251, 'Job Order Created', 'Job Order JO-2026-0004 created with 1 items', 7, 'purchasing', 'purchasing', '2026-03-27 03:53:47'),
(686, 251, 'Approved by Purchasing (With Job Order)', 'A', 7, 'purchasing', 'approver', '2026-03-27 03:54:16'),
(687, 251, 'Approved by Approver', 'SADW', 12, 'approver', 'warehouse_releasing', '2026-03-27 03:54:45'),
(688, 251, 'Items Released (FIFO)', 'OKAY NA', 13, 'warehouse_releasing', 'warehouse_releasing', '2026-03-27 03:56:38'),
(689, 251, 'Completed Motorpool Releasing', 'SD', 13, 'warehouse_releasing', 'completed', '2026-03-27 03:56:50'),
(695, 256, 'Forwarded to Motorpool', '', 15, 'requestor', 'warehouse', '2026-10-01 05:55:07'),
(701, 256, 'Approved by Motorpool', 'asdw', 13, 'warehouse', 'purchasing', '2026-10-01 12:01:25'),
(702, 256, 'Purchase Order Created', 'Purchase Order 000004 created with 1 items. Supplier updated, total estimated cost: ₱0.00', 7, 'purchasing', 'purchasing', '2026-10-01 12:01:57');

-- --------------------------------------------------------

--
-- Table structure for table `spare_parts_pr_status_history`
--

CREATE TABLE `spare_parts_pr_status_history` (
  `id` int NOT NULL,
  `pr_id` int NOT NULL,
  `status` enum('pending','approved','rejected','processing','completed') NOT NULL,
  `changed_by` int NOT NULL,
  `remarks` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `spare_parts_pr_status_history`
--

INSERT INTO `spare_parts_pr_status_history` (`id`, `pr_id`, `status`, `changed_by`, `remarks`, `created_at`) VALUES
(201, 205, 'pending', 10, NULL, '2026-02-19 11:04:46'),
(203, 207, 'pending', 10, NULL, '2026-02-19 11:05:17'),
(204, 208, 'pending', 10, NULL, '2026-02-19 11:05:26'),
(205, 209, 'pending', 10, NULL, '2026-02-19 11:05:34'),
(206, 210, 'pending', 10, NULL, '2026-02-19 11:05:42'),
(207, 211, 'pending', 10, NULL, '2026-02-19 11:05:48'),
(208, 212, 'pending', 10, NULL, '2026-02-19 11:05:55'),
(209, 213, 'pending', 10, NULL, '2026-02-19 11:06:10'),
(210, 214, 'pending', 10, NULL, '2026-02-19 11:06:21'),
(211, 215, 'pending', 10, NULL, '2026-02-19 11:06:31'),
(212, 216, 'pending', 10, NULL, '2026-02-19 11:15:13'),
(213, 217, 'pending', 10, NULL, '2026-02-19 11:15:26'),
(214, 218, 'pending', 12, NULL, '2026-02-19 11:29:31'),
(215, 219, 'pending', 12, NULL, '2026-02-19 11:29:40'),
(216, 220, 'pending', 12, NULL, '2026-02-19 11:29:48'),
(217, 221, 'pending', 10, NULL, '2026-02-19 11:33:05'),
(218, 222, 'pending', 10, NULL, '2026-02-19 11:37:47'),
(220, 224, 'pending', 10, NULL, '2026-02-19 11:39:15'),
(221, 225, 'pending', 10, NULL, '2026-02-19 14:23:36'),
(223, 231, 'pending', 10, NULL, '2026-02-19 15:16:06'),
(224, 232, 'pending', 10, NULL, '2026-02-19 15:16:25'),
(225, 234, 'pending', 10, NULL, '2026-02-19 15:21:53'),
(226, 235, 'pending', 10, NULL, '2026-02-19 15:22:15'),
(227, 236, 'pending', 10, NULL, '2026-02-19 15:22:34'),
(228, 237, 'pending', 10, NULL, '2026-02-19 15:22:56'),
(230, 239, 'pending', 7, NULL, '2026-02-26 06:19:32'),
(232, 241, 'pending', 7, NULL, '2026-02-26 06:21:04'),
(235, 244, 'pending', 7, NULL, '2026-02-26 06:27:29'),
(236, 245, 'pending', 7, NULL, '2026-02-26 06:27:55'),
(237, 246, 'pending', 7, NULL, '2026-02-26 06:28:08'),
(238, 247, 'pending', 13, NULL, '2026-03-23 08:41:51'),
(239, 248, 'pending', 13, NULL, '2026-03-23 08:44:05'),
(240, 249, 'pending', 13, NULL, '2026-03-23 08:45:15'),
(241, 250, 'pending', 13, NULL, '2026-03-27 03:42:52'),
(242, 251, 'pending', 13, NULL, '2026-03-27 03:52:22'),
(243, 252, 'pending', 13, NULL, '2026-03-27 04:03:33'),
(247, 256, 'pending', 15, NULL, '2026-10-01 05:08:50');

-- --------------------------------------------------------

--
-- Table structure for table `spare_parts_suppliers`
--

CREATE TABLE `spare_parts_suppliers` (
  `id` int NOT NULL,
  `supplier_name` varchar(255) NOT NULL,
  `contact_person` varchar(100) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `address` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `spare_parts_suppliers`
--

INSERT INTO `spare_parts_suppliers` (`id`, `supplier_name`, `contact_person`, `email`, `phone`, `address`, `created_at`, `updated_at`) VALUES
(1, 'Spare Parts 1', 'Ramon Batak', 'ramon@gmail.com', '09560285830', 'Cagayan', '2025-09-10 01:00:33', '2025-09-23 09:29:36'),
(9, 'Spare Parts 2', 'asdd', 'salatan@gmail.com', '09560285830', 'asd', '2025-09-26 20:17:40', '2025-09-26 20:17:40'),
(10, 'Spare Parts 3', 'asdw', 'test1@gmail.com', '09560285830', 'qwe', '2025-09-26 20:18:01', '2025-09-26 20:18:01');

-- --------------------------------------------------------

--
-- Table structure for table `spare_parts_withdrawal_slips`
--

CREATE TABLE `spare_parts_withdrawal_slips` (
  `id` int NOT NULL,
  `withdrawal_slip_number` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `pr_id` int NOT NULL,
  `requested_by` int NOT NULL COMMENT 'User ID who requested the withdrawal',
  `employee_id` int DEFAULT NULL,
  `purpose` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `withdrawal_date` date DEFAULT NULL,
  `status` enum('draft','pending','approved','released','completed','cancelled') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT 'draft',
  `remarks` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `created_by` int NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `spare_parts_withdrawal_slips`
--

INSERT INTO `spare_parts_withdrawal_slips` (`id`, `withdrawal_slip_number`, `pr_id`, `requested_by`, `employee_id`, `purpose`, `withdrawal_date`, `status`, `remarks`, `created_by`, `created_at`, `updated_at`) VALUES
(24, 'WS-2026-0001', 209, 10, 4, 'asdw', '2026-02-19', 'released', '', 7, '2026-02-19 11:24:24', '2026-02-20 10:25:09'),
(25, 'WS-2026-0002', 249, 13, 5, 'asd', '2026-03-23', 'released', 'asd', 7, '2026-03-23 08:47:02', '2026-03-23 08:51:52');

-- --------------------------------------------------------

--
-- Table structure for table `spare_parts_withdrawal_slip_items`
--

CREATE TABLE `spare_parts_withdrawal_slip_items` (
  `id` int NOT NULL,
  `withdrawal_slip_id` int NOT NULL,
  `pr_item_id` int NOT NULL,
  `part_id` int NOT NULL,
  `quantity` decimal(10,2) NOT NULL,
  `unit_cost` decimal(10,2) DEFAULT NULL,
  `total_cost` decimal(10,2) DEFAULT NULL,
  `status` enum('draft','pending','approved','released','completed','cancelled') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT 'draft',
  `released_date` date DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `spare_parts_withdrawal_slip_items`
--

INSERT INTO `spare_parts_withdrawal_slip_items` (`id`, `withdrawal_slip_id`, `pr_item_id`, `part_id`, `quantity`, `unit_cost`, `total_cost`, `status`, `released_date`, `created_at`, `updated_at`) VALUES
(24, 24, 212, 11, 1.00, 103.00, 103.00, 'released', '2026-02-20', '2026-02-19 11:24:24', '2026-02-20 10:25:09'),
(25, 25, 245, 11, 1.00, 103.00, 103.00, 'released', '2026-03-23', '2026-03-23 08:47:02', '2026-03-23 08:51:52');

-- --------------------------------------------------------

--
-- Table structure for table `spare_part_po`
--

CREATE TABLE `spare_part_po` (
  `id` int NOT NULL,
  `po_number` varchar(50) NOT NULL,
  `pr_id` int NOT NULL,
  `supplier_id` int DEFAULT NULL,
  `po_date` date NOT NULL,
  `expected_delivery` date DEFAULT NULL,
  `order_date` date NOT NULL DEFAULT (curdate()),
  `total_amount` decimal(15,2) DEFAULT '0.00',
  `status` varchar(50) DEFAULT 'pending',
  `remarks` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `spare_part_po`
--

INSERT INTO `spare_part_po` (`id`, `po_number`, `pr_id`, `supplier_id`, `po_date`, `expected_delivery`, `order_date`, `total_amount`, `status`, `remarks`, `created_at`, `updated_at`) VALUES
(44, 'PO-2026-0001', 205, 9, '2026-02-19', '2026-02-26', '2026-02-19', 111.00, 'confirmed', '', '2026-02-19 11:07:18', '2026-02-19 11:08:25'),
(45, 'PO-2026-0002', 221, 9, '2026-02-19', '2026-02-26', '2026-02-19', 11.00, 'confirmed', '', '2026-02-19 11:35:22', '2026-02-20 10:25:36'),
(46, '000001', 225, 9, '2026-02-26', '2026-03-05', '2026-02-26', 111.00, 'pending', '', '2026-02-26 03:39:34', '2026-02-26 03:39:34'),
(47, '000002', 247, 1, '2026-03-23', '2026-03-30', '2026-03-23', 150.00, 'confirmed', 'asd', '2026-03-23 08:46:06', '2026-03-23 08:50:27'),
(48, '000003', 250, 1, '2026-03-27', '2026-04-03', '2026-03-27', 900.00, 'confirmed', '', '2026-03-27 03:45:09', '2026-03-27 03:49:18'),
(49, '000004', 256, 1, '2026-10-01', '2026-10-08', '2026-10-01', 0.00, 'pending', '', '2026-10-01 12:01:57', '2026-10-01 12:01:57');

-- --------------------------------------------------------

--
-- Table structure for table `spare_part_po_items`
--

CREATE TABLE `spare_part_po_items` (
  `id` int NOT NULL,
  `po_id` int NOT NULL,
  `pr_item_id` int NOT NULL,
  `part_id` int NOT NULL,
  `quantity` decimal(10,2) NOT NULL DEFAULT '0.00',
  `unit_cost` decimal(15,2) NOT NULL,
  `total_cost` decimal(15,2) NOT NULL,
  `received_quantity` int DEFAULT '0',
  `received_date` date DEFAULT NULL,
  `status` varchar(50) DEFAULT 'pending',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `spare_part_po_items`
--

INSERT INTO `spare_part_po_items` (`id`, `po_id`, `pr_item_id`, `part_id`, `quantity`, `unit_cost`, `total_cost`, `received_quantity`, `received_date`, `status`, `created_at`, `updated_at`) VALUES
(46, 44, 207, 2, 1.00, 111.00, 111.00, 1, '2026-02-19', 'confirmed', '2026-02-19 11:07:18', '2026-02-19 11:08:32'),
(47, 45, 225, 2, 1.00, 11.00, 11.00, 1, '2026-02-20', 'delivered', '2026-02-19 11:35:22', '2026-02-20 10:25:36'),
(48, 46, 229, 2, 1.00, 111.00, 111.00, 0, NULL, 'pending', '2026-02-26 03:39:34', '2026-02-26 03:39:34'),
(49, 47, 243, 2, 1.00, 150.00, 150.00, 1, '2026-03-23', 'confirmed', '2026-03-23 08:46:06', '2026-03-23 08:50:31'),
(50, 48, 246, 2, 1.00, 900.00, 900.00, 1, '2026-03-27', 'confirmed', '2026-03-27 03:45:09', '2026-03-27 03:49:47');

-- --------------------------------------------------------

--
-- Table structure for table `stock_movements`
--

CREATE TABLE `stock_movements` (
  `id` int NOT NULL,
  `item_id` int NOT NULL,
  `supplier_id` int DEFAULT NULL,
  `project_id` int DEFAULT NULL,
  `subcon_id` int DEFAULT NULL,
  `warehouse_id` int NOT NULL,
  `quantity` int NOT NULL,
  `unit_cost` decimal(10,2) DEFAULT '0.00',
  `movement_type` enum('in','out') NOT NULL,
  `movement_date` date NOT NULL,
  `batch_number` varchar(50) DEFAULT NULL,
  `pr_id` int DEFAULT NULL,
  `purchase_order` varchar(255) DEFAULT NULL,
  `purchase_request` varchar(255) DEFAULT NULL,
  `transfer_from` int DEFAULT NULL,
  `transfer_to` int DEFAULT NULL,
  `linked_movement_id` int DEFAULT NULL,
  `notes` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `total_value` decimal(15,2) DEFAULT '0.00'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `stock_movements`
--

INSERT INTO `stock_movements` (`id`, `item_id`, `supplier_id`, `project_id`, `subcon_id`, `warehouse_id`, `quantity`, `unit_cost`, `movement_type`, `movement_date`, `batch_number`, `pr_id`, `purchase_order`, `purchase_request`, `transfer_from`, `transfer_to`, `linked_movement_id`, `notes`, `created_at`, `total_value`) VALUES
(429, 11, NULL, NULL, NULL, 5, 10, 150.00, 'in', '2026-02-16', 'INITIAL-20260216092646', NULL, NULL, NULL, NULL, NULL, NULL, 'Initial stock', '2026-02-16 09:26:46', 0.00),
(430, 11, 9, NULL, NULL, 5, 1, 11.00, 'in', '2026-02-17', 'BATCH-20260217-135130-75', NULL, 'PO-2026-0002', 'PR-2026-0006', NULL, NULL, NULL, NULL, '2026-02-17 13:54:01', 11.00),
(431, 11, 5, NULL, NULL, 5, 1, 111.00, 'in', '2026-02-18', 'BATCH-20260218-032018-78', NULL, 'PO-2026-0005', 'PR-2026-0009', NULL, NULL, NULL, NULL, '2026-02-18 03:20:26', 111.00),
(432, 11, 10, NULL, NULL, 5, 1, 12.00, 'in', '2026-02-18', 'BATCH-20260218-034201-79', NULL, 'PO-2026-0006', 'PR-2026-0010', NULL, NULL, NULL, NULL, '2026-02-18 03:42:14', 12.00),
(433, 11, 9, NULL, NULL, 5, 2, 40.00, 'in', '2026-02-18', 'BATCH-20260218-034558-80', NULL, 'PO-2026-0007', 'PR-2026-0011', NULL, NULL, NULL, NULL, '2026-02-18 03:46:01', 80.00),
(434, 13, 10, NULL, NULL, 5, 2, 30.00, 'in', '2026-02-18', 'BATCH-20260218-053627-81', NULL, 'PO-2026-0008', 'PR-2026-0013', NULL, NULL, NULL, NULL, '2026-02-18 05:36:40', 60.00),
(435, 11, 9, NULL, NULL, 5, 2, 50.00, 'in', '2026-02-18', 'BATCH-20260218-055016-82', NULL, 'PO-2026-0009', 'PR-2026-0014', NULL, NULL, NULL, NULL, '2026-02-18 05:50:38', 100.00),
(436, 11, 10, NULL, NULL, 5, 1, 25.00, 'in', '2026-02-18', 'BATCH-20260218-055828-83', NULL, 'PO-2026-0010', 'PR-2026-0015', NULL, NULL, NULL, NULL, '2026-02-18 05:58:33', 25.00),
(437, 11, 10, NULL, NULL, 5, 1, 10.00, 'in', '2026-02-18', 'BATCH-20260218-060440-84', NULL, 'PO-2026-0011', 'PR-2026-0016', NULL, NULL, NULL, NULL, '2026-02-18 06:04:52', 10.00),
(438, 11, 9, NULL, NULL, 5, 1, 15.00, 'in', '2026-02-18', 'BATCH-20260218-062829-85', NULL, 'PO-2026-0012', 'PR-2026-0017', NULL, NULL, NULL, NULL, '2026-02-18 06:31:04', 15.00),
(439, 13, NULL, NULL, NULL, 5, 1, 40.00, 'in', '2026-02-18', 'INITIAL-20260218082245', NULL, NULL, NULL, NULL, NULL, NULL, 'Initial stock', '2026-02-18 08:22:45', 0.00),
(440, 12, 4, NULL, NULL, 5, 1, 152.00, 'in', '2026-02-18', 'BATCH-20260218-090942-90', NULL, 'PO-2026-0017', 'PR-2026-0030', NULL, NULL, NULL, NULL, '2026-02-18 09:09:57', 152.00),
(441, 12, 9, NULL, NULL, 5, 1, 151.00, 'in', '2026-02-18', 'BATCH-20260218-091121-89', NULL, 'PO-2026-0016', 'PR-2026-0029', NULL, NULL, NULL, NULL, '2026-02-18 09:12:48', 151.00),
(442, 12, NULL, 17, NULL, 5, 1, 152.00, 'out', '2026-02-18', 'BATCH-20260218-090942-90', NULL, NULL, 'PR-2026-0031', NULL, NULL, NULL, NULL, '2026-02-18 09:45:53', 0.00),
(443, 12, NULL, 17, NULL, 5, 1, 151.00, 'out', '2026-02-18', 'BATCH-20260218-091121-89', NULL, NULL, 'PR-2026-0031', NULL, NULL, NULL, NULL, '2026-02-18 09:45:53', 0.00),
(444, 12, 9, NULL, NULL, 5, 1, 99.00, 'in', '2026-02-18', 'BATCH-20260218-095005-91', NULL, 'PO-2026-0018', 'PR-2026-0032', NULL, NULL, NULL, NULL, '2026-02-18 09:50:12', 99.00),
(445, 12, NULL, 17, 1, 5, 1, 99.00, 'out', '2026-02-18', 'BATCH-20260218-095005-91', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-02-18 10:42:54', 0.00),
(446, 12, 9, NULL, NULL, 5, 1, 95.00, 'in', '2026-02-17', 'BATCH-20260218-104510-92', NULL, 'PO-2026-0019', 'PR-2026-0033', NULL, NULL, NULL, NULL, '2026-02-18 10:45:22', 95.00),
(447, 12, 9, NULL, NULL, 5, 1, 51.00, 'in', '2026-02-16', 'BATCH-20260218-104753-93', NULL, 'PO-2026-0020', 'PR-2026-0034', NULL, NULL, NULL, NULL, '2026-02-18 10:48:00', 51.00),
(448, 12, NULL, 17, 1, 5, 1, 51.00, 'out', '2026-02-18', 'BATCH-20260218-104753-93', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-02-18 10:48:28', 0.00),
(449, 12, NULL, 17, 1, 5, 1, 95.00, 'out', '2026-02-18', 'BATCH-20260218-104510-92', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-02-18 10:48:28', 0.00),
(450, 12, 10, NULL, NULL, 5, 1, 15.00, 'in', '2026-02-18', 'BATCH-20260218-112201-94', NULL, 'PO-2026-0021', 'PR-2026-0036', NULL, NULL, NULL, NULL, '2026-02-18 11:22:05', 15.00),
(451, 12, NULL, 17, 1, 5, 1, 15.00, 'out', '2026-02-18', 'BATCH-20260218-112201-94', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-02-18 12:33:42', 0.00),
(452, 12, 9, NULL, NULL, 5, 1, 16.00, 'in', '2026-02-18', 'BATCH-20260218-123840-95', NULL, 'PO-2026-0022', 'PR-2026-0037', NULL, NULL, NULL, NULL, '2026-02-18 12:38:51', 16.00),
(453, 12, NULL, 17, 1, 5, 1, 16.00, 'out', '2026-02-18', 'BATCH-20260218-123840-95', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-02-18 12:39:29', 0.00),
(454, 11, NULL, 17, NULL, 5, 1, 150.00, 'out', '2026-02-18', 'INITIAL-20260216092646', NULL, NULL, 'PR-2026-0038', NULL, NULL, NULL, NULL, '2026-02-18 12:52:27', 0.00),
(455, 13, NULL, 17, NULL, 5, 2, 30.00, 'out', '2026-02-18', 'BATCH-20260218-053627-81', NULL, NULL, 'PR-2026-0038', NULL, NULL, NULL, NULL, '2026-02-18 12:52:27', 0.00),
(456, 13, NULL, 17, NULL, 5, 1, 40.00, 'out', '2026-02-18', 'INITIAL-20260218082245', NULL, NULL, 'PR-2026-0038', NULL, NULL, NULL, NULL, '2026-02-18 12:52:27', 0.00),
(457, 12, NULL, NULL, NULL, 5, 3, 10.00, 'in', '2026-02-18', 'INITIAL-20260218130226', NULL, NULL, NULL, NULL, NULL, NULL, 'Initial stock', '2026-02-18 13:02:26', 0.00),
(458, 11, NULL, 17, NULL, 5, 1, 150.00, 'out', '2026-02-18', 'INITIAL-20260216092646', NULL, NULL, 'PR-2026-0040', NULL, NULL, NULL, NULL, '2026-02-18 13:05:48', 0.00),
(459, 12, NULL, 17, NULL, 5, 3, 10.00, 'out', '2026-02-18', 'INITIAL-20260218130226', NULL, NULL, 'PR-2026-0040', NULL, NULL, NULL, NULL, '2026-02-18 13:05:48', 0.00),
(460, 12, 10, NULL, NULL, 5, 2, 10.00, 'in', '2026-02-18', 'BATCH-20260218-131555-98', NULL, 'PO-2026-0024', 'PR-2026-0040', NULL, NULL, NULL, NULL, '2026-02-18 13:16:03', 20.00),
(461, 13, 10, NULL, NULL, 5, 1, 10.00, 'in', '2026-02-18', 'BATCH-20260218-131555-99', NULL, 'PO-2026-0024', 'PR-2026-0040', NULL, NULL, NULL, NULL, '2026-02-18 13:16:03', 10.00),
(462, 13, NULL, 17, 1, 5, 1, 10.00, 'out', '2026-02-18', 'BATCH-20260218-131555-99', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-02-18 13:20:10', 0.00),
(463, 11, NULL, 17, NULL, 5, 1, 150.00, 'out', '2026-02-18', 'INITIAL-20260216092646', NULL, NULL, 'PR-2026-0041', NULL, NULL, NULL, NULL, '2026-02-18 13:22:14', 0.00),
(464, 12, NULL, 17, NULL, 5, 2, 10.00, 'out', '2026-02-18', 'BATCH-20260218-131555-98', NULL, NULL, 'PR-2026-0041', NULL, NULL, NULL, NULL, '2026-02-18 13:22:14', 0.00),
(465, 12, 9, NULL, NULL, 5, 2, 10.00, 'in', '2026-02-18', 'BATCH-20260218-132335-100', NULL, 'PO-2026-0025', 'PR-2026-0041', NULL, NULL, NULL, NULL, '2026-02-18 13:23:39', 20.00),
(466, 13, 9, NULL, NULL, 5, 1, 11.00, 'in', '2026-02-18', 'BATCH-20260218-132335-101', NULL, 'PO-2026-0025', 'PR-2026-0041', NULL, NULL, NULL, NULL, '2026-02-18 13:23:39', 11.00),
(467, 13, NULL, 17, 1, 5, 1, 11.00, 'out', '2026-02-18', 'BATCH-20260218-132335-101', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-02-18 13:31:05', 0.00),
(468, 11, NULL, 17, NULL, 5, 1, 150.00, 'out', '2026-02-18', 'INITIAL-20260216092646', NULL, NULL, 'PR-2026-0042', NULL, NULL, NULL, NULL, '2026-02-18 13:34:48', 0.00),
(469, 12, NULL, 17, NULL, 5, 2, 10.00, 'out', '2026-02-18', 'BATCH-20260218-132335-100', NULL, NULL, 'PR-2026-0042', NULL, NULL, NULL, NULL, '2026-02-18 13:34:48', 0.00),
(471, 12, 9, NULL, NULL, 5, 2, 10.00, 'in', '2026-02-18', 'BATCH-20260218-133907-102', NULL, 'PO-2026-0026', 'PR-2026-0042', NULL, NULL, NULL, NULL, '2026-02-18 13:39:13', 20.00),
(472, 13, 9, NULL, NULL, 5, 1, 20.00, 'in', '2026-02-18', 'BATCH-20260218-133907-103', NULL, 'PO-2026-0026', 'PR-2026-0042', NULL, NULL, NULL, NULL, '2026-02-18 13:39:13', 20.00),
(473, 13, NULL, 17, 1, 5, 1, 20.00, 'out', '2026-02-18', 'BATCH-20260218-133907-103', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-02-18 14:32:33', 0.00),
(474, 11, NULL, 17, NULL, 5, 6, 150.00, 'out', '2026-02-18', 'INITIAL-20260216092646', NULL, NULL, 'PR-2026-0047', NULL, NULL, NULL, NULL, '2026-02-18 15:40:33', 0.00),
(475, 11, NULL, 17, NULL, 5, 1, 11.00, 'out', '2026-02-18', 'BATCH-20260217-135130-75', NULL, NULL, 'PR-2026-0047', NULL, NULL, NULL, NULL, '2026-02-18 15:40:33', 0.00),
(476, 11, NULL, 17, NULL, 5, 1, 111.00, 'out', '2026-02-19', 'BATCH-20260218-032018-78', NULL, NULL, 'PR-2026-0049', NULL, NULL, NULL, NULL, '2026-02-19 01:28:20', 0.00),
(477, 11, NULL, 17, NULL, 5, 1, 12.00, 'out', '2026-02-19', 'BATCH-20260218-034201-79', NULL, NULL, 'PR-2026-0050', NULL, NULL, NULL, NULL, '2026-02-19 07:27:59', 0.00),
(478, 11, NULL, 17, NULL, 5, 1, 40.00, 'out', '2026-02-19', 'BATCH-20260218-034558-80', NULL, NULL, 'PR-2026-0050', NULL, NULL, NULL, NULL, '2026-02-19 07:27:59', 0.00),
(479, 11, NULL, 17, NULL, 5, 1, 40.00, 'out', '2026-02-19', 'BATCH-20260218-034558-80', NULL, NULL, 'PR-2026-0051', NULL, NULL, NULL, NULL, '2026-02-19 07:33:38', 0.00),
(480, 11, NULL, 17, NULL, 5, 1, 50.00, 'out', '2026-02-19', 'BATCH-20260218-055016-82', NULL, NULL, 'PR-2026-0051', NULL, NULL, NULL, NULL, '2026-02-19 07:33:38', 0.00),
(481, 13, 10, NULL, NULL, 5, 1, 140.00, 'in', '2026-02-19', 'BATCH-20260219-074147-107', NULL, 'PO-2026-0030', 'PR-2026-0052', NULL, NULL, NULL, NULL, '2026-02-19 07:42:47', 140.00),
(482, 11, 10, NULL, NULL, 5, 1, 120.00, 'in', '2026-02-19', 'BATCH-20260219-080731-110', NULL, 'PO-2026-0033', 'PR-2026-0056', NULL, NULL, NULL, NULL, '2026-02-19 08:07:34', 120.00),
(483, 13, NULL, 17, 1, 5, 1, 140.00, 'out', '2026-02-19', 'BATCH-20260219-074147-107', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-02-19 08:23:46', 0.00),
(484, 11, NULL, 17, NULL, 5, 1, 50.00, 'out', '2026-02-19', 'BATCH-20260218-055016-82', NULL, NULL, 'PR-2026-0057', NULL, NULL, NULL, NULL, '2026-02-19 09:30:52', 0.00),
(485, 12, NULL, 17, NULL, 5, 2, 10.00, 'out', '2026-02-19', 'BATCH-20260218-133907-102', NULL, NULL, 'PR-2026-0057', NULL, NULL, NULL, NULL, '2026-02-19 09:30:52', 0.00),
(486, 12, 9, NULL, NULL, 5, 2, 150.00, 'in', '2026-02-19', 'BATCH-20260219-093147-111', NULL, 'PO-2026-0034', 'PR-2026-0057', NULL, NULL, NULL, NULL, '2026-02-19 09:31:51', 300.00),
(487, 13, 9, NULL, NULL, 5, 1, 180.00, 'in', '2026-02-19', 'BATCH-20260219-093147-112', NULL, 'PO-2026-0034', 'PR-2026-0057', NULL, NULL, NULL, NULL, '2026-02-19 09:31:51', 180.00),
(488, 13, NULL, 17, 1, 5, 1, 180.00, 'out', '2026-02-19', 'BATCH-20260219-093147-112', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-02-19 09:46:53', 0.00),
(489, 13, 9, NULL, NULL, 5, 2, 144.00, 'in', '2026-02-19', 'BATCH-20260219-100334-114', NULL, 'PO-2026-0036', 'PR-2026-0059', NULL, NULL, NULL, NULL, '2026-02-19 10:03:40', 288.00),
(490, 13, NULL, 17, 1, 5, 2, 144.00, 'out', '2026-02-19', 'BATCH-20260219-100334-114', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-02-19 10:11:20', 0.00),
(491, 13, 9, NULL, NULL, 5, 1, 121.00, 'in', '2026-02-19', 'BATCH-20260219-101300-115', NULL, 'PO-2026-0037', 'PR-2026-0060', NULL, NULL, NULL, NULL, '2026-02-19 10:13:21', 121.00),
(492, 11, NULL, 17, NULL, 5, 1, 25.00, 'out', '2026-02-19', 'BATCH-20260218-055828-83', NULL, NULL, 'PR-2026-0061', NULL, NULL, NULL, NULL, '2026-02-19 10:15:58', 0.00),
(493, 13, NULL, 17, 1, 5, 1, 121.00, 'out', '2026-02-20', 'BATCH-20260219-101300-115', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-02-20 11:21:58', 0.00),
(494, 11, NULL, 19, NULL, 5, 1, 10.00, 'out', '2026-04-15', 'BATCH-20260218-060440-84', NULL, NULL, 'PR-2026-0073', NULL, NULL, NULL, NULL, '2026-04-15 07:24:57', 0.00),
(495, 12, NULL, 19, NULL, 5, 1, 150.00, 'out', '2026-04-15', 'BATCH-20260219-093147-111', NULL, NULL, 'PR-2026-0073', NULL, NULL, NULL, NULL, '2026-04-15 07:24:57', 0.00),
(496, 11, NULL, NULL, NULL, 5, 1, 1.00, 'in', '2026-04-30', 'INITIAL-20260430033118', NULL, NULL, NULL, NULL, NULL, NULL, 'Initial stock', '2026-04-30 03:31:18', 0.00),
(497, 16, NULL, NULL, NULL, 5, 1, 25.00, 'in', '2026-10-01', 'INITIAL-20261001042540', NULL, NULL, NULL, NULL, NULL, NULL, 'Initial stock', '2026-10-01 04:25:40', 0.00),
(498, 16, NULL, NULL, NULL, 5, 1, 25.00, 'out', '2026-10-01', 'INITIAL-20261001042540', NULL, NULL, NULL, NULL, 5, NULL, NULL, '2026-10-01 04:25:57', 0.00),
(499, 16, NULL, NULL, NULL, 5, 1, 25.00, 'in', '2026-10-01', 'INITIAL-20261001042540', NULL, NULL, NULL, 5, NULL, NULL, NULL, '2026-10-01 04:25:57', 0.00),
(500, 16, NULL, NULL, NULL, 5, 1, 25.00, 'out', '2026-10-01', 'INITIAL-20261001042540', NULL, NULL, NULL, NULL, 5, NULL, NULL, '2026-10-01 05:32:59', 0.00),
(501, 16, NULL, NULL, NULL, 5, 1, 25.00, 'in', '2026-10-01', 'INITIAL-20261001042540', NULL, NULL, NULL, 5, NULL, NULL, NULL, '2026-10-01 05:32:59', 0.00),
(502, 16, NULL, NULL, NULL, 5, 1, 250.00, 'in', '2026-10-01', 'INITIAL-20261001053313', NULL, NULL, NULL, NULL, NULL, NULL, 'Initial stock', '2026-10-01 05:33:13', 0.00),
(504, 56, NULL, NULL, NULL, 5, 10, 10.00, 'in', '2026-10-01', 'INITIAL-20261001100805', NULL, NULL, NULL, NULL, NULL, NULL, 'Initial stock', '2026-10-01 10:08:05', 0.00),
(505, 56, NULL, NULL, NULL, 5, 5, 5.00, 'in', '2026-10-01', 'INITIAL-20261001100822', NULL, NULL, NULL, NULL, NULL, NULL, 'Initial stock', '2026-10-01 10:08:22', 0.00);

-- --------------------------------------------------------

--
-- Table structure for table `stock_withdrawals`
--

CREATE TABLE `stock_withdrawals` (
  `id` int NOT NULL,
  `withdrawal_slip_id` int NOT NULL,
  `withdrawal_slip_item_id` int NOT NULL,
  `item_id` int NOT NULL,
  `batch_id` int NOT NULL,
  `quantity` decimal(10,2) NOT NULL,
  `unit_cost` decimal(15,2) NOT NULL,
  `total_cost` decimal(15,2) NOT NULL,
  `withdrawal_date` date NOT NULL,
  `withdrawal_by` int DEFAULT NULL,
  `remarks` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `stock_withdrawals`
--

INSERT INTO `stock_withdrawals` (`id`, `withdrawal_slip_id`, `withdrawal_slip_item_id`, `item_id`, `batch_id`, `quantity`, `unit_cost`, `total_cost`, `withdrawal_date`, `withdrawal_by`, `remarks`, `created_at`) VALUES
(42, 56, 69, 12, 166, 1.00, 152.00, 152.00, '2026-02-18', 10, 'Withdrawn via Withdrawal Slip', '2026-02-18 09:45:53'),
(43, 56, 69, 12, 167, 1.00, 151.00, 151.00, '2026-02-18', 10, 'Withdrawn via Withdrawal Slip', '2026-02-18 09:45:53'),
(44, 57, 70, 11, 155, 1.00, 150.00, 150.00, '2026-02-18', 10, 'Withdrawn via Withdrawal Slip', '2026-02-18 12:52:27'),
(45, 57, 71, 13, 160, 2.00, 30.00, 60.00, '2026-02-18', 10, 'Withdrawn via Withdrawal Slip', '2026-02-18 12:52:27'),
(46, 57, 71, 13, 165, 1.00, 40.00, 40.00, '2026-02-18', 10, 'Withdrawn via Withdrawal Slip', '2026-02-18 12:52:27'),
(47, 58, 72, 11, 155, 1.00, 150.00, 150.00, '2026-02-18', 10, 'Withdrawn via Withdrawal Slip', '2026-02-18 13:05:48'),
(48, 58, 73, 12, 173, 3.00, 10.00, 30.00, '2026-02-18', 10, 'Withdrawn via Withdrawal Slip', '2026-02-18 13:05:48'),
(49, 59, 74, 11, 155, 1.00, 150.00, 150.00, '2026-02-18', 10, 'Withdrawn via Withdrawal Slip', '2026-02-18 13:22:14'),
(50, 59, 75, 12, 174, 2.00, 10.00, 20.00, '2026-02-18', 10, 'Withdrawn via Withdrawal Slip', '2026-02-18 13:22:14'),
(51, 60, 76, 11, 155, 1.00, 150.00, 150.00, '2026-02-18', 10, 'Withdrawn via Withdrawal Slip', '2026-02-18 13:34:48'),
(52, 60, 77, 12, 176, 2.00, 10.00, 20.00, '2026-02-18', 10, 'Withdrawn via Withdrawal Slip', '2026-02-18 13:34:48'),
(53, 64, 81, 11, 155, 6.00, 150.00, 900.00, '2026-02-18', 10, 'Withdrawn via Withdrawal Slip', '2026-02-18 15:40:33'),
(54, 64, 81, 11, 156, 1.00, 11.00, 11.00, '2026-02-18', 10, 'Withdrawn via Withdrawal Slip', '2026-02-18 15:40:33'),
(55, 66, 83, 11, 157, 1.00, 111.00, 111.00, '2026-02-19', 10, 'Withdrawn via Withdrawal Slip', '2026-02-19 01:28:20'),
(56, 67, 84, 11, 158, 1.00, 12.00, 12.00, '2026-02-19', 10, 'Withdrawn via Withdrawal Slip', '2026-02-19 07:27:59'),
(57, 67, 84, 11, 159, 1.00, 40.00, 40.00, '2026-02-19', 10, 'Withdrawn via Withdrawal Slip', '2026-02-19 07:27:59'),
(58, 68, 85, 11, 159, 1.00, 40.00, 40.00, '2026-02-19', 10, 'Withdrawn via Withdrawal Slip', '2026-02-19 07:33:38'),
(59, 68, 85, 11, 161, 1.00, 50.00, 50.00, '2026-02-19', 10, 'Withdrawn via Withdrawal Slip', '2026-02-19 07:33:38'),
(60, 71, 90, 11, 161, 1.00, 50.00, 50.00, '2026-02-19', 10, 'Withdrawn via Withdrawal Slip', '2026-02-19 09:30:52'),
(61, 71, 91, 12, 179, 2.00, 10.00, 20.00, '2026-02-19', 10, 'Withdrawn via Withdrawal Slip', '2026-02-19 09:30:52'),
(62, 72, 92, 11, 162, 1.00, 25.00, 25.00, '2026-02-19', 10, 'Withdrawn via Withdrawal Slip', '2026-02-19 10:15:58'),
(63, 73, 93, 11, 163, 1.00, 10.00, 10.00, '2026-04-15', 10, 'Withdrawn via Withdrawal Slip', '2026-04-15 07:24:57'),
(64, 73, 94, 12, 183, 1.00, 150.00, 150.00, '2026-04-15', 10, 'Withdrawn via Withdrawal Slip', '2026-04-15 07:24:57');

-- --------------------------------------------------------

--
-- Table structure for table `subcons`
--

CREATE TABLE `subcons` (
  `id` int NOT NULL,
  `subcon_name` varchar(255) NOT NULL,
  `contact_person` varchar(255) DEFAULT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `address` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `subcons`
--

INSERT INTO `subcons` (`id`, `subcon_name`, `contact_person`, `phone`, `email`, `address`, `created_at`, `updated_at`) VALUES
(1, 'Subcon Boy', 'Subcon Boy', 'Subcon Boy', 'ramon@gmail.com', 'Subcon Boy', '2025-10-03 04:47:55', '2025-10-03 04:49:50');

-- --------------------------------------------------------

--
-- Table structure for table `suppliers`
--

CREATE TABLE `suppliers` (
  `id` int NOT NULL,
  `supplier_name` varchar(255) NOT NULL,
  `contact_person` varchar(255) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `address` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `suppliers`
--

INSERT INTO `suppliers` (`id`, `supplier_name`, `contact_person`, `phone`, `email`, `address`, `created_at`, `updated_at`) VALUES
(4, 'EK', 'Juan Baranagan', '09560285830', 'test@gmail.com', 'Cagayan', '2025-09-08 04:26:58', '2025-09-26 19:50:34'),
(5, 'EMC', 'Randy', '09560285830', 'test1@gmail.com', 'Supplier 2', '2025-09-08 05:10:17', '2025-09-26 19:50:26'),
(9, 'Blue Seal', 'Peter', '09560285830', 'Peter@gmail.com', 'Peter', '2025-09-26 19:51:10', '2025-09-26 19:51:10'),
(10, 'Boy', 'Boy1', '09560285830', 'test1@gmail.com', 'Boy', '2025-09-27 04:12:30', '2026-10-01 02:21:29');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int NOT NULL,
  `lastname` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `firstname` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `middlename` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `suffix` enum('','Sr.','Jr.','III','IV') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT '',
  `department` enum('Engineering','Warehouse','Admin','BAC','Site','IT','Motorpool') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `position` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `address` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `contact` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` enum('active','inactive','on-leave') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `accounttype` enum('Admin','Staff') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Admin',
  `email` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `username` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `password` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `registration_date` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `last_login` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `lastname`, `firstname`, `middlename`, `suffix`, `department`, `position`, `address`, `contact`, `status`, `accounttype`, `email`, `username`, `password`, `registration_date`, `last_login`) VALUES
(1, 'Barangan', 'Josue', 'Bangloy', 'III', 'IT', 'Assistant IT Programmer', 'Tuguegarao City, Cagayan', '09560285830', 'active', 'Admin', 'test@gmail.com', 'Admin', '$2y$10$jOTkz3H4/yaz29x1N1PAG.rEDNzC3a4E6Y5326yQ0qvc6sVwfXdI6', '2025-09-04 14:54:39', '2026-09-30 14:34:21'),
(2, 'Salatan1', 'Rio', 'Grapas', 'Sr.', 'Engineering', 'Project Engineer', 'Tuguegarao City, Cagayan', '09560285830', 'active', 'Admin', 'salatan@gmail.com', 'Engineering', '$2y$10$Hf4Rq2sViXwoyyHg6EvFV.Ggp0M5ioecJLYZlPDzqN3XwZeMct6ye', '2025-09-05 23:43:48', '2025-09-18 11:47:36'),
(6, 'Boy', 'Boy', 'Boy', 'Sr.', 'Engineering', 'Operations Manager', 'Boy', '09560285830', 'active', 'Admin', 'ramon@gmail.com', 'BoyBoy', '$2y$10$uvDXWjWSmKR4PjrZKpFpZuJP8tUuhVER8gINhehpOZrDxP.cTAw1y', '2025-09-23 10:48:22', NULL),
(7, 'Purchaser', 'Purchaser', 'Purchaser', 'III', 'Admin', 'Purchaser', 'Purchaser', '09560285830', 'active', 'Admin', 'Purchaser@gmail.com', 'Purchaser', '$2y$10$QKu300KnNvD57iV5CeSdkOsICpLvlcMqJ.NU3xPg2wtK1rICRBhoa', '2025-09-28 03:59:54', '2026-10-01 14:07:44'),
(8, 'Accounting', 'Accounting', 'Accounting', '', 'Admin', 'Accounting', 'Accounting', '09560285830', 'active', 'Admin', 'Accounting@gmail.com', 'Accounting', '$2y$10$HKE258pyI.NV5Mcmh0SD..dHktko1FJnNRKNeC/5yntfmsNgkFe6.', '2025-09-28 04:00:33', '2026-04-20 02:37:10'),
(9, 'BAC', 'BAC', 'BAC', '', 'BAC', 'Chairman', 'BAC', '09560285830', 'active', 'Admin', 'BAC@gmail.com', 'BAC', '$2y$10$t.DdwPItuxdbRH/5dzXCc.F0Lj1kcDmXYuuXj96pc8xrzVIg11X2W', '2025-09-28 04:01:55', '2025-10-14 04:36:54'),
(10, 'Warehouse', 'Warehouse', 'Warehouse', 'III', 'Warehouse', 'Warehouse Manager', 'Warehouse', '09560285830', 'active', 'Admin', 'Warehouse@gmail.com', 'Warehouse', '$2y$10$VA8RU.LI0og7rACJ2H.k5.cq8amETIWJWE12KhTL2nDzjFx34WMoG', '2025-09-30 09:59:15', '2026-10-01 14:07:26'),
(11, 'Approver', 'Approver', 'Approver', '', 'Admin', 'HR Officer', 'Approver', '09560285830', 'active', 'Admin', 'josue@gmail.com', 'Approver', '$2y$10$EPkVdq1xBKWKmaDFkf.gaulSFcCpL.9J9PLkZFy6sUfJMydJwywvm', '2025-10-15 06:14:43', '2026-02-16 03:54:27'),
(12, 'Barangan', 'CEO', 'CEO', '', 'Admin', 'CEO', 'asd', '09560285830', 'active', 'Admin', 'altcabal7@gmail.com', 'CEO1', '$2y$10$AG.5em9zIArS0bijyafb8.qPNHYu/odnmAX8j./YpmR0bszgoN.dS', '2026-01-07 06:17:15', '2026-09-28 02:26:44'),
(13, 'Motorpool', 'Motorpool', 'Motorpool', 'III', 'Motorpool', 'Motorpool Manager', 'Motorpool, Motorpool', '09560285830', 'active', 'Admin', 'Motorpool@gmail.com', 'Motorpool', '$2y$10$vr9YfcAHyR22wUtG8Qmm3uG8F3VfI/trCrga4pFuwe3Rj9DkMOQ9y', '2026-03-14 11:08:38', '2026-10-01 12:01:19'),
(14, 'HR', 'Hr', 'HR', '', 'Admin', 'HR Officer', 'HR, HR', '09560285830', 'active', 'Admin', 'HR@gmail.com', 'HR', '$2y$10$HSzkKTtALCcMkSoKvEdnZeG12VV7ksXgNd/CUIUdv2pgR4Esv2qjC', '2026-03-14 11:10:26', '2026-04-22 02:45:05'),
(15, 'CEO', 'CEO', 'CEO', '', 'Admin', 'CEO', 'Sanchez Mira, Cagayan', '09560285830', 'active', 'Admin', 'ceo@gmail.com', 'CEO', '$2y$10$gWsdD8MT427rUbXIhSS6n.B/EACChsT0c4K/U/I8t6mmB9Wcl7wiG', '2026-09-28 02:29:22', '2026-10-01 14:05:30');

-- --------------------------------------------------------

--
-- Table structure for table `vehicles`
--

CREATE TABLE `vehicles` (
  `id` int NOT NULL,
  `vehicle_name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `plate_number` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `fuel_type` enum('gasoline','diesel','electric','hybrid') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'gasoline',
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `is_active` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `vehicles`
--

INSERT INTO `vehicles` (`id`, `vehicle_name`, `plate_number`, `fuel_type`, `description`, `is_active`, `created_at`, `updated_at`) VALUES
(2, 'Trailer1', 'HDN 1023', 'diesel', 'Trailer', 1, '2025-09-08 04:49:15', '2025-09-23 10:11:44'),
(15, 'Seq 1790780841835', 'SEQ-1790780841835', 'diesel', '', 1, '2026-09-30 15:07:21', '2026-09-30 15:07:21'),
(17, 'wqewq', 'F-1790780891609', 'diesel', '', 1, '2026-09-30 15:08:11', '2026-10-01 04:21:30'),
(44, 'dasdas', '123123', 'gasoline', '', 1, '2026-10-01 00:19:34', '2026-10-01 00:19:34');

-- --------------------------------------------------------

--
-- Table structure for table `wage_history`
--

CREATE TABLE `wage_history` (
  `id` int NOT NULL,
  `employee_id` int NOT NULL,
  `effectivity_date` date DEFAULT NULL,
  `old_wage` decimal(10,2) DEFAULT '0.00',
  `new_wage` decimal(10,2) DEFAULT '0.00',
  `change_amount` decimal(10,2) DEFAULT '0.00',
  `change_percentage` decimal(5,2) DEFAULT '0.00',
  `change_type` enum('increase','decrease','no change') DEFAULT 'no change',
  `changed_by` int NOT NULL,
  `change_reason` text,
  `deduction_changes` json DEFAULT NULL,
  `changed_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `wage_history`
--

INSERT INTO `wage_history` (`id`, `employee_id`, `effectivity_date`, `old_wage`, `new_wage`, `change_amount`, `change_percentage`, `change_type`, `changed_by`, `change_reason`, `deduction_changes`, `changed_at`) VALUES
(18, 8, '2026-03-01', 400.00, 400.00, 0.00, 0.00, 'no change', 12, '', NULL, '2026-03-11 05:57:04'),
(19, 7, '2026-03-01', 560.00, 560.00, 0.00, 0.00, 'no change', 12, '', NULL, '2026-03-11 05:57:15'),
(20, 5, '2026-03-01', 600.00, 600.00, 0.00, 0.00, 'no change', 12, '', NULL, '2026-03-11 05:57:21'),
(21, 4, '2026-03-01', 450.00, 450.00, 0.00, 0.00, 'no change', 12, '', NULL, '2026-03-11 05:57:29'),
(22, 3, '2026-03-01', 550.00, 550.00, 0.00, 0.00, 'no change', 12, '', NULL, '2026-03-11 05:57:39'),
(23, 3, '2026-03-01', 550.00, 550.00, 0.00, 0.00, 'no change', 12, '', NULL, '2026-03-11 05:58:11'),
(24, 2, '2026-03-01', 550.00, 550.00, 0.00, 0.00, 'no change', 12, '', NULL, '2026-03-11 05:58:36'),
(25, 1, '2026-03-01', 600.00, 600.00, 0.00, 0.00, 'no change', 12, '', NULL, '2026-03-11 05:58:43'),
(26, 1, '2026-03-03', 600.00, 650.00, 50.00, 8.33, 'increase', 12, '', NULL, '2026-03-11 07:50:43'),
(28, 10, '2026-10-01', 500.00, 501.00, 1.00, 0.20, 'increase', 15, '', '{\"cash_advance\": {\"new\": \"1\", \"old\": \"0\", \"type\": \"increase\", \"change\": \"1\", \"percentage\": \"100\"}}', '2026-10-01 05:57:11');

-- --------------------------------------------------------

--
-- Table structure for table `warehouses`
--

CREATE TABLE `warehouses` (
  `id` int NOT NULL,
  `warehouse_name` varchar(255) NOT NULL,
  `location` varchar(255) NOT NULL,
  `capacity` int DEFAULT NULL,
  `manager` varchar(255) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `warehouses`
--

INSERT INTO `warehouses` (`id`, `warehouse_name`, `location`, `capacity`, `manager`, `phone`, `created_at`, `updated_at`) VALUES
(5, 'Main Warehouse', 'Sanchez Mira', 10000, 'Juan De Vera', '09560285830', '2025-09-08 04:46:13', '2026-01-10 06:42:31');

-- --------------------------------------------------------

--
-- Table structure for table `withdrawal_slips`
--

CREATE TABLE `withdrawal_slips` (
  `id` int NOT NULL,
  `ws_number` varchar(50) NOT NULL,
  `pr_id` int NOT NULL,
  `ws_date` date NOT NULL,
  `requested_by` int NOT NULL,
  `project_id` int DEFAULT NULL,
  `warehouse_id` int DEFAULT NULL,
  `total_amount` decimal(15,2) DEFAULT '0.00',
  `status` enum('draft','confirmed','delivered','cancelled','approved','pending','completed','released','processing') CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT 'draft',
  `is_simplified_flow` tinyint(1) DEFAULT '0',
  `remarks` text,
  `released_date` date DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `withdrawal_slips`
--

INSERT INTO `withdrawal_slips` (`id`, `ws_number`, `pr_id`, `ws_date`, `requested_by`, `project_id`, `warehouse_id`, `total_amount`, `status`, `is_simplified_flow`, `remarks`, `released_date`, `created_at`, `updated_at`) VALUES
(52, 'WS-2026-0001', 163, '2026-02-16', 12, 17, 5, 0.00, 'approved', 0, '', NULL, '2026-02-16 09:56:49', '2026-02-16 09:59:21'),
(53, 'WS-2026-0002', 164, '2026-02-17', 12, 17, 5, 0.00, 'pending', 0, '', NULL, '2026-02-17 01:40:25', '2026-02-17 01:40:25'),
(54, 'WS-2026-0003', 182, '2026-02-18', 8, 17, 5, 0.00, 'pending', 0, '', NULL, '2026-02-18 07:45:36', '2026-02-18 07:45:36'),
(55, 'WS-2026-0004', 186, '2026-02-18', 7, 17, 5, 0.00, 'pending', 0, '', NULL, '2026-02-18 08:25:40', '2026-02-18 08:25:40'),
(56, 'WS-2026-0005', 193, '2026-02-18', 10, 17, 5, 0.00, 'released', 0, '', '2026-02-18', '2026-02-18 09:44:59', '2026-02-18 09:45:53'),
(57, 'WS-2026-0006', 200, '2026-02-18', 8, 17, 5, 0.00, 'released', 0, '', '2026-02-18', '2026-02-18 12:44:32', '2026-02-18 12:52:27'),
(58, 'WS-2026-0007', 202, '2026-02-18', 10, 17, 5, 0.00, 'released', 0, '', '2026-02-18', '2026-02-18 13:04:10', '2026-02-18 13:05:48'),
(59, 'WS-2026-0008', 203, '2026-02-18', 10, 17, 5, 0.00, 'released', 0, '', '2026-02-18', '2026-02-18 13:21:02', '2026-02-18 13:22:14'),
(60, 'WS-2026-0009', 204, '2026-02-18', 10, 17, 5, 0.00, 'released', 0, '', '2026-02-18', '2026-02-18 13:32:11', '2026-02-18 13:34:48'),
(61, 'WS-2026-0010', 205, '2026-02-18', 10, 17, 5, 0.00, 'pending', 0, '', NULL, '2026-02-18 14:27:40', '2026-02-18 14:27:40'),
(62, 'WS-2026-0011', 206, '2026-02-18', 7, 17, 5, 0.00, 'pending', 0, '', NULL, '2026-02-18 14:31:43', '2026-02-18 14:31:43'),
(63, 'WS-2026-0012', 208, '2026-02-18', 7, 17, 5, 0.00, 'pending', 0, '', NULL, '2026-02-18 15:14:45', '2026-02-18 15:14:45'),
(64, 'WS-2026-0013', 209, '2026-02-18', 7, 17, 5, 0.00, 'released', 0, '', '2026-02-18', '2026-02-18 15:28:01', '2026-02-18 15:40:33'),
(65, 'WS-2026-0014', 210, '2026-02-18', 7, 17, 5, 0.00, 'processing', 0, '', NULL, '2026-02-18 15:33:25', '2026-02-18 15:38:04'),
(66, 'WS-2026-0015', 211, '2026-02-19', 10, 17, 5, 0.00, 'released', 0, '', '2026-02-19', '2026-02-19 01:27:11', '2026-02-19 01:28:20'),
(67, 'WS-2026-0016', 212, '2026-02-19', 10, 17, 5, 0.00, 'released', 0, '', '2026-02-19', '2026-02-19 07:12:08', '2026-02-19 07:27:59'),
(68, 'WS-2026-0017', 213, '2026-02-19', 10, 17, 5, 0.00, 'released', 0, '', '2026-02-19', '2026-02-19 07:32:24', '2026-02-19 07:33:38'),
(69, 'WS-2026-0018', 215, '2026-02-19', 8, 17, 5, 0.00, 'pending', 0, '', NULL, '2026-02-19 07:48:38', '2026-02-19 07:48:38'),
(70, 'WS-2026-0019', 216, '2026-02-19', 7, 17, 5, 0.00, 'processing', 0, '', NULL, '2026-02-19 07:50:41', '2026-02-19 07:51:38'),
(71, 'WS-2026-0020', 219, '2026-02-19', 10, 17, 5, 0.00, 'released', 0, '', '2026-02-19', '2026-02-19 08:24:45', '2026-02-19 09:30:52'),
(72, 'WS-2026-0021', 224, '2026-02-19', 8, 17, 5, 0.00, 'released', 0, '', '2026-02-19', '2026-02-19 10:15:05', '2026-02-19 10:15:58'),
(73, 'WS-2026-0022', 236, '2026-04-15', 12, 19, 5, 0.00, 'released', 0, 'Okay', '2026-04-15', '2026-04-15 07:18:44', '2026-04-15 07:24:57'),
(74, 'WS-2026-0023', 239, '2026-10-01', 15, 19, NULL, 0.00, 'released', 0, '', '2026-10-01', '2026-10-01 10:00:04', '2026-10-01 12:43:18'),
(75, 'WS-2026-0024', 269, '2026-10-01', 10, 19, NULL, 0.00, 'released', 0, '', '2026-10-01', '2026-10-01 12:52:01', '2026-10-01 12:53:19');

-- --------------------------------------------------------

--
-- Table structure for table `withdrawal_slip_items`
--

CREATE TABLE `withdrawal_slip_items` (
  `id` int NOT NULL,
  `withdrawal_slip_id` int NOT NULL,
  `pr_item_id` int NOT NULL,
  `item_id` int NOT NULL,
  `warehouse_id` int NOT NULL,
  `quantity` decimal(10,2) NOT NULL,
  `unit_cost` decimal(15,2) NOT NULL,
  `total_cost` decimal(15,2) NOT NULL,
  `batch_numbers` text,
  `status` enum('pending','processed','cancelled','released','approved','processing') CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT 'pending',
  `released_date` date DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `withdrawal_slip_items`
--

INSERT INTO `withdrawal_slip_items` (`id`, `withdrawal_slip_id`, `pr_item_id`, `item_id`, `warehouse_id`, `quantity`, `unit_cost`, `total_cost`, `batch_numbers`, `status`, `released_date`, `created_at`) VALUES
(63, 52, 190, 11, 5, 1.00, 0.00, 0.00, NULL, 'approved', NULL, '2026-02-16 09:56:49'),
(64, 53, 191, 11, 5, 1.00, 0.00, 0.00, NULL, 'pending', NULL, '2026-02-17 01:40:25'),
(65, 54, 211, 13, 5, 2.00, 0.00, 0.00, NULL, 'pending', NULL, '2026-02-18 07:45:36'),
(66, 54, 212, 11, 5, 1.00, 0.00, 0.00, NULL, 'pending', NULL, '2026-02-18 07:45:36'),
(67, 55, 218, 13, 5, 3.00, 0.00, 0.00, NULL, 'pending', NULL, '2026-02-18 08:25:40'),
(68, 55, 219, 11, 5, 11.00, 0.00, 0.00, NULL, 'pending', NULL, '2026-02-18 08:25:40'),
(69, 56, 226, 12, 5, 2.00, 0.00, 0.00, '[\"BATCH-20260218-090942-90\",\"BATCH-20260218-091121-89\"]', 'released', '2026-02-18', '2026-02-18 09:44:59'),
(70, 57, 233, 11, 5, 1.00, 0.00, 0.00, '[\"INITIAL-20260216092646\"]', 'released', '2026-02-18', '2026-02-18 12:44:32'),
(71, 57, 235, 13, 5, 3.00, 0.00, 0.00, '[\"BATCH-20260218-053627-81\",\"INITIAL-20260218082245\"]', 'released', '2026-02-18', '2026-02-18 12:44:32'),
(72, 58, 239, 11, 5, 1.00, 0.00, 0.00, '[\"INITIAL-20260216092646\"]', 'released', '2026-02-18', '2026-02-18 13:04:10'),
(73, 58, 240, 12, 5, 3.00, 0.00, 0.00, '[\"INITIAL-20260218130226\"]', 'released', '2026-02-18', '2026-02-18 13:04:10'),
(74, 59, 242, 11, 5, 1.00, 0.00, 0.00, '[\"INITIAL-20260216092646\"]', 'released', '2026-02-18', '2026-02-18 13:21:02'),
(75, 59, 243, 12, 5, 2.00, 0.00, 0.00, '[\"BATCH-20260218-131555-98\"]', 'released', '2026-02-18', '2026-02-18 13:21:02'),
(76, 60, 245, 11, 5, 1.00, 0.00, 0.00, '[\"INITIAL-20260216092646\"]', 'released', '2026-02-18', '2026-02-18 13:32:11'),
(77, 60, 246, 12, 5, 2.00, 0.00, 0.00, '[\"BATCH-20260218-132335-100\"]', 'released', '2026-02-18', '2026-02-18 13:32:11'),
(78, 61, 248, 12, 5, 2.00, 0.00, 0.00, NULL, 'pending', NULL, '2026-02-18 14:27:40'),
(79, 62, 249, 11, 5, 1.00, 0.00, 0.00, NULL, 'pending', NULL, '2026-02-18 14:31:43'),
(80, 63, 251, 11, 5, 1.00, 0.00, 0.00, NULL, 'pending', NULL, '2026-02-18 15:14:45'),
(81, 64, 252, 11, 5, 7.00, 0.00, 0.00, '[\"INITIAL-20260216092646\",\"BATCH-20260217-135130-75\"]', 'released', '2026-02-18', '2026-02-18 15:28:01'),
(82, 65, 253, 12, 5, 2.00, 0.00, 0.00, NULL, 'processing', NULL, '2026-02-18 15:33:25'),
(83, 66, 254, 11, 5, 1.00, 0.00, 0.00, '[\"BATCH-20260218-032018-78\"]', 'released', '2026-02-19', '2026-02-19 01:27:11'),
(84, 67, 255, 11, 5, 2.00, 0.00, 0.00, '[\"BATCH-20260218-034201-79\",\"BATCH-20260218-034558-80\"]', 'released', '2026-02-19', '2026-02-19 07:12:08'),
(85, 68, 256, 11, 5, 2.00, 45.00, 90.00, '[\"BATCH-20260218-034558-80\",\"BATCH-20260218-055016-82\"]', 'released', '2026-02-19', '2026-02-19 07:32:24'),
(86, 69, 258, 12, 5, 1.00, 0.00, 0.00, NULL, 'pending', NULL, '2026-02-19 07:48:38'),
(87, 69, 259, 13, 5, 1.00, 0.00, 0.00, NULL, 'pending', NULL, '2026-02-19 07:48:38'),
(88, 70, 260, 12, 5, 1.00, 0.00, 0.00, NULL, 'processing', NULL, '2026-02-19 07:50:41'),
(89, 70, 261, 13, 5, 1.00, 0.00, 0.00, NULL, 'processing', NULL, '2026-02-19 07:50:41'),
(90, 71, 264, 11, 5, 1.00, 50.00, 50.00, '[\"BATCH-20260218-055016-82\"]', 'released', '2026-02-19', '2026-02-19 08:24:45'),
(91, 71, 265, 12, 5, 2.00, 10.00, 20.00, '[\"BATCH-20260218-133907-102\"]', 'released', '2026-02-19', '2026-02-19 08:24:45'),
(92, 72, 271, 11, 5, 1.00, 25.00, 25.00, '[\"BATCH-20260218-055828-83\"]', 'released', '2026-02-19', '2026-02-19 10:15:05'),
(93, 73, 283, 11, 5, 1.00, 10.00, 10.00, '[\"BATCH-20260218-060440-84\"]', 'released', '2026-04-15', '2026-04-15 07:18:44'),
(94, 73, 284, 12, 5, 1.00, 150.00, 150.00, '[\"BATCH-20260219-093147-111\"]', 'released', '2026-04-15', '2026-04-15 07:18:44');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `attendance`
--
ALTER TABLE `attendance`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_employee_attendance` (`employee_id`,`attendance_date`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `updated_by` (`updated_by`);

--
-- Indexes for table `cash_on_hand`
--
ALTER TABLE `cash_on_hand`
  ADD PRIMARY KEY (`id`),
  ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `employee`
--
ALTER TABLE `employee`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `employee_id` (`employee_id`);

--
-- Indexes for table `employee_deductions`
--
ALTER TABLE `employee_deductions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_active_deduction` (`employee_id`,`deduction_type`),
  ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `employee_deductions_history`
--
ALTER TABLE `employee_deductions_history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `changed_by` (`changed_by`),
  ADD KEY `idx_employee_deductions_history` (`employee_id`,`deduction_type`,`changed_at`);

--
-- Indexes for table `employee_materials_issued`
--
ALTER TABLE `employee_materials_issued`
  ADD PRIMARY KEY (`id`),
  ADD KEY `employee_id` (`employee_id`),
  ADD KEY `part_id` (`part_id`),
  ADD KEY `batch_id` (`batch_id`);

--
-- Indexes for table `equipment`
--
ALTER TABLE `equipment`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `expenses`
--
ALTER TABLE `expenses`
  ADD PRIMARY KEY (`id`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `idx_expense_date` (`expense_date`),
  ADD KEY `employee_id` (`employee_id`),
  ADD KEY `ceo_id` (`ceo_id`),
  ADD KEY `expense_type_id` (`expense_type_id`);

--
-- Indexes for table `expenses_type`
--
ALTER TABLE `expenses_type`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `expense_name` (`expense_name`);

--
-- Indexes for table `gasoline_batches`
--
ALTER TABLE `gasoline_batches`
  ADD PRIMARY KEY (`id`),
  ADD KEY `transfer_from` (`transfer_from`),
  ADD KEY `idx_gasoline_type` (`gasoline_type`),
  ADD KEY `idx_tank_id` (`tank_id`),
  ADD KEY `idx_date_received` (`date_received`),
  ADD KEY `idx_supplier_id` (`supplier_id`),
  ADD KEY `idx_quantity_liters` (`quantity_liters`);

--
-- Indexes for table `gasoline_inventory`
--
ALTER TABLE `gasoline_inventory`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_gasoline_tank` (`gasoline_type`,`tank_id`),
  ADD KEY `tank_id` (`tank_id`);

--
-- Indexes for table `gasoline_min_levels`
--
ALTER TABLE `gasoline_min_levels`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_tank_gasoline` (`tank_id`,`gasoline_type`);

--
-- Indexes for table `gasoline_movements`
--
ALTER TABLE `gasoline_movements`
  ADD PRIMARY KEY (`id`),
  ADD KEY `supplier_id` (`supplier_id`),
  ADD KEY `vehicle_id` (`vehicle_id`),
  ADD KEY `equipment_id` (`equipment_id`),
  ADD KEY `transfer_from` (`transfer_from`),
  ADD KEY `transfer_to` (`transfer_to`),
  ADD KEY `idx_movement_date` (`movement_date`),
  ADD KEY `idx_gasoline_type` (`gasoline_type`),
  ADD KEY `idx_movement_type` (`movement_type`),
  ADD KEY `idx_tank_id` (`tank_id`),
  ADD KEY `fk_gasoline_movements_batch_id` (`batch_id`),
  ADD KEY `idx_po_id` (`po_id`);

--
-- Indexes for table `gasoline_po_items`
--
ALTER TABLE `gasoline_po_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `po_id` (`po_id`),
  ADD KEY `supplier_id` (`supplier_id`),
  ADD KEY `vehicle_id` (`vehicle_id`),
  ADD KEY `equipment_id` (`equipment_id`),
  ADD KEY `fk_po_items_employee` (`driver_operator_id`);

--
-- Indexes for table `gasoline_purchase_orders`
--
ALTER TABLE `gasoline_purchase_orders`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `po_number` (`po_number`),
  ADD KEY `supplier_id` (`supplier_id`),
  ADD KEY `prepared_by` (`prepared_by`),
  ADD KEY `approved_by` (`approved_by`);

--
-- Indexes for table `gasoline_suppliers`
--
ALTER TABLE `gasoline_suppliers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_supplier_name` (`supplier_name`);

--
-- Indexes for table `gasoline_tanks`
--
ALTER TABLE `gasoline_tanks`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_tank_name` (`tank_name`);

--
-- Indexes for table `inventory`
--
ALTER TABLE `inventory`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_item_warehouse` (`item_id`,`warehouse_id`),
  ADD KEY `warehouse_id` (`warehouse_id`);

--
-- Indexes for table `inventory_batches`
--
ALTER TABLE `inventory_batches`
  ADD PRIMARY KEY (`id`),
  ADD KEY `warehouse_id` (`warehouse_id`),
  ADD KEY `idx_item_warehouse` (`item_id`,`warehouse_id`),
  ADD KEY `idx_batch_number` (`batch_number`),
  ADD KEY `idx_received_date` (`received_date`),
  ADD KEY `fk_inventory_batches_supplier` (`supplier_id`);

--
-- Indexes for table `items_categories`
--
ALTER TABLE `items_categories`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_category_name` (`category_name`),
  ADD KEY `idx_status` (`status`);

--
-- Indexes for table `item_names`
--
ALTER TABLE `item_names`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `item_code` (`item_code`),
  ADD KEY `fk_item_names_category` (`category_id`);

--
-- Indexes for table `materials`
--
ALTER TABLE `materials`
  ADD PRIMARY KEY (`id`),
  ADD KEY `project_id` (`project_id`);

--
-- Indexes for table `po_items`
--
ALTER TABLE `po_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `po_id` (`po_id`),
  ADD KEY `pr_item_id` (`pr_item_id`),
  ADD KEY `item_id` (`item_id`),
  ADD KEY `warehouse_id` (`warehouse_id`),
  ADD KEY `supplier_id` (`supplier_id`);

--
-- Indexes for table `projects`
--
ALTER TABLE `projects`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `project_code` (`project_code`);

--
-- Indexes for table `project_engineers`
--
ALTER TABLE `project_engineers`
  ADD PRIMARY KEY (`id`),
  ADD KEY `project_id` (`project_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `project_rentals`
--
ALTER TABLE `project_rentals`
  ADD PRIMARY KEY (`id`),
  ADD KEY `project_id` (`project_id`),
  ADD KEY `vehicle_id` (`vehicle_id`),
  ADD KEY `equipment_id` (`equipment_id`);

--
-- Indexes for table `project_workers`
--
ALTER TABLE `project_workers`
  ADD PRIMARY KEY (`id`),
  ADD KEY `project_id` (`project_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `pr_items`
--
ALTER TABLE `pr_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `pr_id` (`pr_id`),
  ADD KEY `item_id` (`item_id`),
  ADD KEY `warehouse_id` (`warehouse_id`),
  ADD KEY `supplier_id` (`supplier_id`),
  ADD KEY `idx_unit_cost` (`unit_cost`);

--
-- Indexes for table `pr_routing`
--
ALTER TABLE `pr_routing`
  ADD PRIMARY KEY (`id`),
  ADD KEY `pr_id` (`pr_id`),
  ADD KEY `action_by` (`action_by`);

--
-- Indexes for table `pr_routing_history`
--
ALTER TABLE `pr_routing_history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `pr_id` (`pr_id`),
  ADD KEY `action_by` (`action_by`);

--
-- Indexes for table `purchase_orders`
--
ALTER TABLE `purchase_orders`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `po_number` (`po_number`),
  ADD KEY `pr_id` (`pr_id`),
  ADD KEY `supplier_id` (`supplier_id`),
  ADD KEY `requested_by` (`requested_by`),
  ADD KEY `project_id` (`project_id`);

--
-- Indexes for table `purchase_requests`
--
ALTER TABLE `purchase_requests`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `pr_number` (`pr_number`),
  ADD KEY `requested_by` (`requested_by`),
  ADD KEY `project_id` (`project_id`),
  ADD KEY `idx_request_type` (`request_type`),
  ADD KEY `idx_supplier_id` (`supplier_id`),
  ADD KEY `idx_document_type` (`document_type`);

--
-- Indexes for table `spare_parts`
--
ALTER TABLE `spare_parts`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `part_number` (`part_number`),
  ADD KEY `category_id` (`category_id`);

--
-- Indexes for table `spare_parts_batches`
--
ALTER TABLE `spare_parts_batches`
  ADD PRIMARY KEY (`id`),
  ADD KEY `part_id` (`part_id`),
  ADD KEY `supplier_id` (`supplier_id`);

--
-- Indexes for table `spare_parts_categories`
--
ALTER TABLE `spare_parts_categories`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `category_name` (`category_name`);

--
-- Indexes for table `spare_parts_inventory`
--
ALTER TABLE `spare_parts_inventory`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `part_id` (`part_id`);

--
-- Indexes for table `spare_parts_job_orders`
--
ALTER TABLE `spare_parts_job_orders`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `job_order_number` (`job_order_number`),
  ADD KEY `pr_id` (`pr_id`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `fk_job_order_technician` (`technician`);

--
-- Indexes for table `spare_parts_job_order_items`
--
ALTER TABLE `spare_parts_job_order_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `job_order_id` (`job_order_id`),
  ADD KEY `pr_item_id` (`pr_item_id`),
  ADD KEY `part_id` (`part_id`);

--
-- Indexes for table `spare_parts_min_levels`
--
ALTER TABLE `spare_parts_min_levels`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `part_id` (`part_id`);

--
-- Indexes for table `spare_parts_movements`
--
ALTER TABLE `spare_parts_movements`
  ADD PRIMARY KEY (`id`),
  ADD KEY `part_id` (`part_id`),
  ADD KEY `supplier_id` (`supplier_id`),
  ADD KEY `vehicle_id` (`vehicle_id`),
  ADD KEY `equipment_id` (`equipment_id`),
  ADD KEY `batch_id` (`batch_id`),
  ADD KEY `employee_id` (`employee_id`),
  ADD KEY `fk_movement_technician` (`technician`);

--
-- Indexes for table `spare_parts_pr`
--
ALTER TABLE `spare_parts_pr`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `pr_number` (`pr_number`),
  ADD KEY `requested_by` (`requested_by`),
  ADD KEY `supplier_id` (`supplier_id`),
  ADD KEY `idx_spare_parts_pr_vehicle` (`vehicle_id`),
  ADD KEY `idx_spare_parts_pr_equipment` (`equipment_id`),
  ADD KEY `idx_spare_parts_pr_request_type` (`request_type`),
  ADD KEY `idx_spare_parts_pr_employee` (`employee_id`),
  ADD KEY `technician` (`technician`),
  ADD KEY `driver_id` (`driver_id`);

--
-- Indexes for table `spare_parts_pr_items`
--
ALTER TABLE `spare_parts_pr_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `pr_id` (`pr_id`),
  ADD KEY `part_id` (`part_id`),
  ADD KEY `fk_spare_parts_pr_items_vehicle` (`vehicle_id`),
  ADD KEY `fk_spare_parts_pr_items_equipment` (`equipment_id`),
  ADD KEY `idx_spare_parts_pr_items_employee` (`employee_id`),
  ADD KEY `technician` (`technician`),
  ADD KEY `driver_id` (`driver_id`);

--
-- Indexes for table `spare_parts_pr_routing`
--
ALTER TABLE `spare_parts_pr_routing`
  ADD PRIMARY KEY (`id`),
  ADD KEY `pr_id` (`pr_id`),
  ADD KEY `action_by` (`action_by`);

--
-- Indexes for table `spare_parts_pr_routing_history`
--
ALTER TABLE `spare_parts_pr_routing_history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `pr_id` (`pr_id`),
  ADD KEY `action_by` (`action_by`);

--
-- Indexes for table `spare_parts_pr_status_history`
--
ALTER TABLE `spare_parts_pr_status_history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `pr_id` (`pr_id`),
  ADD KEY `changed_by` (`changed_by`);

--
-- Indexes for table `spare_parts_suppliers`
--
ALTER TABLE `spare_parts_suppliers`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `spare_parts_withdrawal_slips`
--
ALTER TABLE `spare_parts_withdrawal_slips`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `withdrawal_slip_number` (`withdrawal_slip_number`),
  ADD KEY `pr_id` (`pr_id`),
  ADD KEY `requested_by` (`requested_by`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `fk_withdrawal_slips_employee` (`employee_id`);

--
-- Indexes for table `spare_parts_withdrawal_slip_items`
--
ALTER TABLE `spare_parts_withdrawal_slip_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `withdrawal_slip_id` (`withdrawal_slip_id`),
  ADD KEY `pr_item_id` (`pr_item_id`),
  ADD KEY `part_id` (`part_id`);

--
-- Indexes for table `spare_part_po`
--
ALTER TABLE `spare_part_po`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `po_number` (`po_number`),
  ADD KEY `idx_spare_part_po_pr_id` (`pr_id`),
  ADD KEY `idx_spare_part_po_supplier_id` (`supplier_id`),
  ADD KEY `idx_spare_part_po_status` (`status`);

--
-- Indexes for table `spare_part_po_items`
--
ALTER TABLE `spare_part_po_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_spare_part_po_items_po_id` (`po_id`),
  ADD KEY `idx_spare_part_po_items_part_id` (`part_id`),
  ADD KEY `idx_spare_part_po_items_pr_item_id` (`pr_item_id`);

--
-- Indexes for table `stock_movements`
--
ALTER TABLE `stock_movements`
  ADD PRIMARY KEY (`id`),
  ADD KEY `item_id` (`item_id`),
  ADD KEY `supplier_id` (`supplier_id`),
  ADD KEY `project_id` (`project_id`),
  ADD KEY `warehouse_id` (`warehouse_id`),
  ADD KEY `transfer_from` (`transfer_from`),
  ADD KEY `transfer_to` (`transfer_to`),
  ADD KEY `idx_batch_number` (`batch_number`),
  ADD KEY `fk_stock_movements_subcon` (`subcon_id`),
  ADD KEY `pr_id` (`pr_id`);

--
-- Indexes for table `stock_withdrawals`
--
ALTER TABLE `stock_withdrawals`
  ADD PRIMARY KEY (`id`),
  ADD KEY `withdrawal_slip_id` (`withdrawal_slip_id`),
  ADD KEY `withdrawal_slip_item_id` (`withdrawal_slip_item_id`),
  ADD KEY `item_id` (`item_id`),
  ADD KEY `batch_id` (`batch_id`),
  ADD KEY `withdrawal_by` (`withdrawal_by`);

--
-- Indexes for table `subcons`
--
ALTER TABLE `subcons`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `suppliers`
--
ALTER TABLE `suppliers`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD UNIQUE KEY `username` (`username`),
  ADD KEY `idx_department` (`department`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_accounttype` (`accounttype`);

--
-- Indexes for table `vehicles`
--
ALTER TABLE `vehicles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_plate_number` (`plate_number`);

--
-- Indexes for table `wage_history`
--
ALTER TABLE `wage_history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `employee_id` (`employee_id`),
  ADD KEY `changed_by` (`changed_by`);

--
-- Indexes for table `warehouses`
--
ALTER TABLE `warehouses`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `withdrawal_slips`
--
ALTER TABLE `withdrawal_slips`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `ws_number` (`ws_number`),
  ADD KEY `pr_id` (`pr_id`),
  ADD KEY `requested_by` (`requested_by`),
  ADD KEY `project_id` (`project_id`),
  ADD KEY `warehouse_id` (`warehouse_id`);

--
-- Indexes for table `withdrawal_slip_items`
--
ALTER TABLE `withdrawal_slip_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `withdrawal_slip_id` (`withdrawal_slip_id`),
  ADD KEY `pr_item_id` (`pr_item_id`),
  ADD KEY `item_id` (`item_id`),
  ADD KEY `warehouse_id` (`warehouse_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `attendance`
--
ALTER TABLE `attendance`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=211;

--
-- AUTO_INCREMENT for table `cash_on_hand`
--
ALTER TABLE `cash_on_hand`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;

--
-- AUTO_INCREMENT for table `employee`
--
ALTER TABLE `employee`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `employee_deductions`
--
ALTER TABLE `employee_deductions`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `employee_deductions_history`
--
ALTER TABLE `employee_deductions_history`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `employee_materials_issued`
--
ALTER TABLE `employee_materials_issued`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `equipment`
--
ALTER TABLE `equipment`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=64;

--
-- AUTO_INCREMENT for table `expenses`
--
ALTER TABLE `expenses`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=27;

--
-- AUTO_INCREMENT for table `expenses_type`
--
ALTER TABLE `expenses_type`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=116;

--
-- AUTO_INCREMENT for table `gasoline_batches`
--
ALTER TABLE `gasoline_batches`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=25;

--
-- AUTO_INCREMENT for table `gasoline_inventory`
--
ALTER TABLE `gasoline_inventory`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=31;

--
-- AUTO_INCREMENT for table `gasoline_min_levels`
--
ALTER TABLE `gasoline_min_levels`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `gasoline_movements`
--
ALTER TABLE `gasoline_movements`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=159;

--
-- AUTO_INCREMENT for table `gasoline_po_items`
--
ALTER TABLE `gasoline_po_items`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=96;

--
-- AUTO_INCREMENT for table `gasoline_purchase_orders`
--
ALTER TABLE `gasoline_purchase_orders`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=68;

--
-- AUTO_INCREMENT for table `gasoline_suppliers`
--
ALTER TABLE `gasoline_suppliers`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=65;

--
-- AUTO_INCREMENT for table `gasoline_tanks`
--
ALTER TABLE `gasoline_tanks`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=54;

--
-- AUTO_INCREMENT for table `inventory`
--
ALTER TABLE `inventory`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=72;

--
-- AUTO_INCREMENT for table `inventory_batches`
--
ALTER TABLE `inventory_batches`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=193;

--
-- AUTO_INCREMENT for table `items_categories`
--
ALTER TABLE `items_categories`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=86;

--
-- AUTO_INCREMENT for table `item_names`
--
ALTER TABLE `item_names`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=76;

--
-- AUTO_INCREMENT for table `materials`
--
ALTER TABLE `materials`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `po_items`
--
ALTER TABLE `po_items`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=120;

--
-- AUTO_INCREMENT for table `projects`
--
ALTER TABLE `projects`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=102;

--
-- AUTO_INCREMENT for table `project_engineers`
--
ALTER TABLE `project_engineers`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=103;

--
-- AUTO_INCREMENT for table `project_rentals`
--
ALTER TABLE `project_rentals`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT for table `project_workers`
--
ALTER TABLE `project_workers`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=94;

--
-- AUTO_INCREMENT for table `pr_items`
--
ALTER TABLE `pr_items`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=291;

--
-- AUTO_INCREMENT for table `pr_routing`
--
ALTER TABLE `pr_routing`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1346;

--
-- AUTO_INCREMENT for table `pr_routing_history`
--
ALTER TABLE `pr_routing_history`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1426;

--
-- AUTO_INCREMENT for table `purchase_orders`
--
ALTER TABLE `purchase_orders`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=151;

--
-- AUTO_INCREMENT for table `purchase_requests`
--
ALTER TABLE `purchase_requests`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=276;

--
-- AUTO_INCREMENT for table `spare_parts`
--
ALTER TABLE `spare_parts`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=103;

--
-- AUTO_INCREMENT for table `spare_parts_batches`
--
ALTER TABLE `spare_parts_batches`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=145;

--
-- AUTO_INCREMENT for table `spare_parts_categories`
--
ALTER TABLE `spare_parts_categories`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=84;

--
-- AUTO_INCREMENT for table `spare_parts_inventory`
--
ALTER TABLE `spare_parts_inventory`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=105;

--
-- AUTO_INCREMENT for table `spare_parts_job_orders`
--
ALTER TABLE `spare_parts_job_orders`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=35;

--
-- AUTO_INCREMENT for table `spare_parts_job_order_items`
--
ALTER TABLE `spare_parts_job_order_items`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=35;

--
-- AUTO_INCREMENT for table `spare_parts_min_levels`
--
ALTER TABLE `spare_parts_min_levels`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `spare_parts_movements`
--
ALTER TABLE `spare_parts_movements`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=260;

--
-- AUTO_INCREMENT for table `spare_parts_pr`
--
ALTER TABLE `spare_parts_pr`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=258;

--
-- AUTO_INCREMENT for table `spare_parts_pr_items`
--
ALTER TABLE `spare_parts_pr_items`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=254;

--
-- AUTO_INCREMENT for table `spare_parts_pr_routing`
--
ALTER TABLE `spare_parts_pr_routing`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=709;

--
-- AUTO_INCREMENT for table `spare_parts_pr_routing_history`
--
ALTER TABLE `spare_parts_pr_routing_history`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=709;

--
-- AUTO_INCREMENT for table `spare_parts_pr_status_history`
--
ALTER TABLE `spare_parts_pr_status_history`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=249;

--
-- AUTO_INCREMENT for table `spare_parts_suppliers`
--
ALTER TABLE `spare_parts_suppliers`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=55;

--
-- AUTO_INCREMENT for table `spare_parts_withdrawal_slips`
--
ALTER TABLE `spare_parts_withdrawal_slips`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=26;

--
-- AUTO_INCREMENT for table `spare_parts_withdrawal_slip_items`
--
ALTER TABLE `spare_parts_withdrawal_slip_items`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=26;

--
-- AUTO_INCREMENT for table `spare_part_po`
--
ALTER TABLE `spare_part_po`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=50;

--
-- AUTO_INCREMENT for table `spare_part_po_items`
--
ALTER TABLE `spare_part_po_items`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=51;

--
-- AUTO_INCREMENT for table `stock_movements`
--
ALTER TABLE `stock_movements`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=506;

--
-- AUTO_INCREMENT for table `stock_withdrawals`
--
ALTER TABLE `stock_withdrawals`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=65;

--
-- AUTO_INCREMENT for table `subcons`
--
ALTER TABLE `subcons`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=52;

--
-- AUTO_INCREMENT for table `suppliers`
--
ALTER TABLE `suppliers`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=63;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=62;

--
-- AUTO_INCREMENT for table `vehicles`
--
ALTER TABLE `vehicles`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=69;

--
-- AUTO_INCREMENT for table `wage_history`
--
ALTER TABLE `wage_history`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=29;

--
-- AUTO_INCREMENT for table `warehouses`
--
ALTER TABLE `warehouses`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=80;

--
-- AUTO_INCREMENT for table `withdrawal_slips`
--
ALTER TABLE `withdrawal_slips`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=76;

--
-- AUTO_INCREMENT for table `withdrawal_slip_items`
--
ALTER TABLE `withdrawal_slip_items`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=95;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `attendance`
--
ALTER TABLE `attendance`
  ADD CONSTRAINT `attendance_ibfk_1` FOREIGN KEY (`employee_id`) REFERENCES `employee` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `attendance_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `attendance_ibfk_3` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `cash_on_hand`
--
ALTER TABLE `cash_on_hand`
  ADD CONSTRAINT `cash_on_hand_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `employee_deductions`
--
ALTER TABLE `employee_deductions`
  ADD CONSTRAINT `employee_deductions_ibfk_1` FOREIGN KEY (`employee_id`) REFERENCES `employee` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `employee_deductions_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `employee_deductions_history`
--
ALTER TABLE `employee_deductions_history`
  ADD CONSTRAINT `employee_deductions_history_ibfk_1` FOREIGN KEY (`employee_id`) REFERENCES `employee` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `employee_deductions_history_ibfk_2` FOREIGN KEY (`changed_by`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `employee_materials_issued`
--
ALTER TABLE `employee_materials_issued`
  ADD CONSTRAINT `employee_materials_issued_ibfk_1` FOREIGN KEY (`employee_id`) REFERENCES `employee` (`id`),
  ADD CONSTRAINT `employee_materials_issued_ibfk_2` FOREIGN KEY (`part_id`) REFERENCES `spare_parts` (`id`),
  ADD CONSTRAINT `employee_materials_issued_ibfk_3` FOREIGN KEY (`batch_id`) REFERENCES `spare_parts_batches` (`id`);

--
-- Constraints for table `expenses`
--
ALTER TABLE `expenses`
  ADD CONSTRAINT `expenses_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `expenses_ibfk_2` FOREIGN KEY (`employee_id`) REFERENCES `employee` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `expenses_ibfk_3` FOREIGN KEY (`ceo_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `expenses_ibfk_4` FOREIGN KEY (`expense_type_id`) REFERENCES `expenses_type` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `gasoline_batches`
--
ALTER TABLE `gasoline_batches`
  ADD CONSTRAINT `gasoline_batches_ibfk_1` FOREIGN KEY (`tank_id`) REFERENCES `gasoline_tanks` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `gasoline_batches_ibfk_2` FOREIGN KEY (`supplier_id`) REFERENCES `gasoline_suppliers` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `gasoline_batches_ibfk_3` FOREIGN KEY (`transfer_from`) REFERENCES `gasoline_tanks` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `gasoline_inventory`
--
ALTER TABLE `gasoline_inventory`
  ADD CONSTRAINT `gasoline_inventory_ibfk_1` FOREIGN KEY (`tank_id`) REFERENCES `gasoline_tanks` (`id`);

--
-- Constraints for table `gasoline_min_levels`
--
ALTER TABLE `gasoline_min_levels`
  ADD CONSTRAINT `gasoline_min_levels_ibfk_1` FOREIGN KEY (`tank_id`) REFERENCES `gasoline_tanks` (`id`);

--
-- Constraints for table `gasoline_movements`
--
ALTER TABLE `gasoline_movements`
  ADD CONSTRAINT `fk_gasoline_movements_batch_id` FOREIGN KEY (`batch_id`) REFERENCES `gasoline_batches` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_gasoline_movements_po` FOREIGN KEY (`po_id`) REFERENCES `gasoline_purchase_orders` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `gasoline_movements_ibfk_1` FOREIGN KEY (`tank_id`) REFERENCES `gasoline_tanks` (`id`),
  ADD CONSTRAINT `gasoline_movements_ibfk_3` FOREIGN KEY (`vehicle_id`) REFERENCES `vehicles` (`id`),
  ADD CONSTRAINT `gasoline_movements_ibfk_4` FOREIGN KEY (`equipment_id`) REFERENCES `equipment` (`id`),
  ADD CONSTRAINT `gasoline_movements_ibfk_5` FOREIGN KEY (`transfer_from`) REFERENCES `gasoline_tanks` (`id`),
  ADD CONSTRAINT `gasoline_movements_ibfk_6` FOREIGN KEY (`transfer_to`) REFERENCES `gasoline_tanks` (`id`),
  ADD CONSTRAINT `gasoline_movements_supplier_fk` FOREIGN KEY (`supplier_id`) REFERENCES `gasoline_suppliers` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `gasoline_po_items`
--
ALTER TABLE `gasoline_po_items`
  ADD CONSTRAINT `fk_po_items_employee` FOREIGN KEY (`driver_operator_id`) REFERENCES `employee` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `gasoline_po_items_ibfk_3` FOREIGN KEY (`supplier_id`) REFERENCES `gasoline_suppliers` (`id`),
  ADD CONSTRAINT `gasoline_po_items_ibfk_4` FOREIGN KEY (`vehicle_id`) REFERENCES `vehicles` (`id`),
  ADD CONSTRAINT `gasoline_po_items_ibfk_5` FOREIGN KEY (`equipment_id`) REFERENCES `equipment` (`id`);

--
-- Constraints for table `gasoline_purchase_orders`
--
ALTER TABLE `gasoline_purchase_orders`
  ADD CONSTRAINT `gasoline_purchase_orders_ibfk_1` FOREIGN KEY (`supplier_id`) REFERENCES `gasoline_suppliers` (`id`),
  ADD CONSTRAINT `gasoline_purchase_orders_ibfk_2` FOREIGN KEY (`prepared_by`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `gasoline_purchase_orders_ibfk_3` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `inventory`
--
ALTER TABLE `inventory`
  ADD CONSTRAINT `inventory_ibfk_1` FOREIGN KEY (`item_id`) REFERENCES `item_names` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `inventory_ibfk_2` FOREIGN KEY (`warehouse_id`) REFERENCES `warehouses` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `inventory_batches`
--
ALTER TABLE `inventory_batches`
  ADD CONSTRAINT `fk_inventory_batches_supplier` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`),
  ADD CONSTRAINT `inventory_batches_ibfk_1` FOREIGN KEY (`item_id`) REFERENCES `item_names` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `inventory_batches_ibfk_2` FOREIGN KEY (`warehouse_id`) REFERENCES `warehouses` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `item_names`
--
ALTER TABLE `item_names`
  ADD CONSTRAINT `fk_item_names_category` FOREIGN KEY (`category_id`) REFERENCES `items_categories` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `materials`
--
ALTER TABLE `materials`
  ADD CONSTRAINT `materials_ibfk_1` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `po_items`
--
ALTER TABLE `po_items`
  ADD CONSTRAINT `po_items_ibfk_1` FOREIGN KEY (`po_id`) REFERENCES `purchase_orders` (`id`),
  ADD CONSTRAINT `po_items_ibfk_2` FOREIGN KEY (`pr_item_id`) REFERENCES `pr_items` (`id`),
  ADD CONSTRAINT `po_items_ibfk_3` FOREIGN KEY (`item_id`) REFERENCES `item_names` (`id`),
  ADD CONSTRAINT `po_items_ibfk_4` FOREIGN KEY (`warehouse_id`) REFERENCES `warehouses` (`id`),
  ADD CONSTRAINT `po_items_ibfk_5` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`);

--
-- Constraints for table `project_engineers`
--
ALTER TABLE `project_engineers`
  ADD CONSTRAINT `project_engineers_ibfk_1` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `project_engineers_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `project_rentals`
--
ALTER TABLE `project_rentals`
  ADD CONSTRAINT `project_rentals_ibfk_1` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `project_rentals_ibfk_2` FOREIGN KEY (`vehicle_id`) REFERENCES `vehicles` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `project_rentals_ibfk_3` FOREIGN KEY (`equipment_id`) REFERENCES `equipment` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `project_workers`
--
ALTER TABLE `project_workers`
  ADD CONSTRAINT `project_workers_ibfk_1` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `project_workers_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `employee` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `pr_items`
--
ALTER TABLE `pr_items`
  ADD CONSTRAINT `pr_items_ibfk_1` FOREIGN KEY (`pr_id`) REFERENCES `purchase_requests` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `pr_items_ibfk_2` FOREIGN KEY (`item_id`) REFERENCES `item_names` (`id`),
  ADD CONSTRAINT `pr_items_ibfk_3` FOREIGN KEY (`warehouse_id`) REFERENCES `warehouses` (`id`),
  ADD CONSTRAINT `pr_items_ibfk_4` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`),
  ADD CONSTRAINT `pr_items_ibfk_5` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`);

--
-- Constraints for table `pr_routing`
--
ALTER TABLE `pr_routing`
  ADD CONSTRAINT `pr_routing_ibfk_1` FOREIGN KEY (`pr_id`) REFERENCES `purchase_requests` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `pr_routing_ibfk_2` FOREIGN KEY (`action_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `pr_routing_history`
--
ALTER TABLE `pr_routing_history`
  ADD CONSTRAINT `pr_routing_history_ibfk_1` FOREIGN KEY (`pr_id`) REFERENCES `purchase_requests` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `pr_routing_history_ibfk_2` FOREIGN KEY (`action_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `purchase_orders`
--
ALTER TABLE `purchase_orders`
  ADD CONSTRAINT `purchase_orders_ibfk_1` FOREIGN KEY (`pr_id`) REFERENCES `purchase_requests` (`id`),
  ADD CONSTRAINT `purchase_orders_ibfk_2` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`),
  ADD CONSTRAINT `purchase_orders_ibfk_3` FOREIGN KEY (`requested_by`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `purchase_orders_ibfk_4` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`);

--
-- Constraints for table `purchase_requests`
--
ALTER TABLE `purchase_requests`
  ADD CONSTRAINT `fk_purchase_requests_supplier` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `purchase_requests_ibfk_1` FOREIGN KEY (`requested_by`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `purchase_requests_ibfk_2` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`);

--
-- Constraints for table `spare_parts`
--
ALTER TABLE `spare_parts`
  ADD CONSTRAINT `spare_parts_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `spare_parts_categories` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `spare_parts_batches`
--
ALTER TABLE `spare_parts_batches`
  ADD CONSTRAINT `spare_parts_batches_ibfk_1` FOREIGN KEY (`part_id`) REFERENCES `spare_parts` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `spare_parts_batches_ibfk_2` FOREIGN KEY (`supplier_id`) REFERENCES `spare_parts_suppliers` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `spare_parts_inventory`
--
ALTER TABLE `spare_parts_inventory`
  ADD CONSTRAINT `spare_parts_inventory_ibfk_1` FOREIGN KEY (`part_id`) REFERENCES `spare_parts` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `spare_parts_job_orders`
--
ALTER TABLE `spare_parts_job_orders`
  ADD CONSTRAINT `fk_job_order_technician` FOREIGN KEY (`technician`) REFERENCES `employee` (`id`),
  ADD CONSTRAINT `spare_parts_job_orders_ibfk_1` FOREIGN KEY (`pr_id`) REFERENCES `spare_parts_pr` (`id`),
  ADD CONSTRAINT `spare_parts_job_orders_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `spare_parts_job_order_items`
--
ALTER TABLE `spare_parts_job_order_items`
  ADD CONSTRAINT `spare_parts_job_order_items_ibfk_1` FOREIGN KEY (`job_order_id`) REFERENCES `spare_parts_job_orders` (`id`),
  ADD CONSTRAINT `spare_parts_job_order_items_ibfk_2` FOREIGN KEY (`pr_item_id`) REFERENCES `spare_parts_pr_items` (`id`),
  ADD CONSTRAINT `spare_parts_job_order_items_ibfk_3` FOREIGN KEY (`part_id`) REFERENCES `spare_parts` (`id`);

--
-- Constraints for table `spare_parts_min_levels`
--
ALTER TABLE `spare_parts_min_levels`
  ADD CONSTRAINT `spare_parts_min_levels_ibfk_1` FOREIGN KEY (`part_id`) REFERENCES `spare_parts` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `spare_parts_movements`
--
ALTER TABLE `spare_parts_movements`
  ADD CONSTRAINT `fk_movement_technician` FOREIGN KEY (`technician`) REFERENCES `employee` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `spare_parts_movements_ibfk_1` FOREIGN KEY (`part_id`) REFERENCES `spare_parts` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `spare_parts_movements_ibfk_2` FOREIGN KEY (`supplier_id`) REFERENCES `spare_parts_suppliers` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `spare_parts_movements_ibfk_3` FOREIGN KEY (`vehicle_id`) REFERENCES `vehicles` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `spare_parts_movements_ibfk_4` FOREIGN KEY (`equipment_id`) REFERENCES `equipment` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `spare_parts_movements_ibfk_5` FOREIGN KEY (`batch_id`) REFERENCES `spare_parts_batches` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `spare_parts_movements_ibfk_6` FOREIGN KEY (`employee_id`) REFERENCES `employee` (`id`);

--
-- Constraints for table `spare_parts_pr`
--
ALTER TABLE `spare_parts_pr`
  ADD CONSTRAINT `fk_spare_parts_pr_employee` FOREIGN KEY (`employee_id`) REFERENCES `employee` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_spare_parts_pr_equipment` FOREIGN KEY (`equipment_id`) REFERENCES `equipment` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_spare_parts_pr_vehicle` FOREIGN KEY (`vehicle_id`) REFERENCES `vehicles` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `spare_parts_pr_ibfk_1` FOREIGN KEY (`requested_by`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `spare_parts_pr_ibfk_2` FOREIGN KEY (`supplier_id`) REFERENCES `spare_parts_suppliers` (`id`),
  ADD CONSTRAINT `spare_parts_pr_ibfk_3` FOREIGN KEY (`technician`) REFERENCES `employee` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `spare_parts_pr_ibfk_4` FOREIGN KEY (`driver_id`) REFERENCES `employee` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `spare_parts_pr_items`
--
ALTER TABLE `spare_parts_pr_items`
  ADD CONSTRAINT `fk_spare_parts_pr_items_employee` FOREIGN KEY (`employee_id`) REFERENCES `employee` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_spare_parts_pr_items_equipment` FOREIGN KEY (`equipment_id`) REFERENCES `equipment` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_spare_parts_pr_items_vehicle` FOREIGN KEY (`vehicle_id`) REFERENCES `vehicles` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `spare_parts_pr_items_ibfk_1` FOREIGN KEY (`pr_id`) REFERENCES `spare_parts_pr` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `spare_parts_pr_items_ibfk_2` FOREIGN KEY (`part_id`) REFERENCES `spare_parts` (`id`),
  ADD CONSTRAINT `spare_parts_pr_items_ibfk_3` FOREIGN KEY (`technician`) REFERENCES `employee` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `spare_parts_pr_items_ibfk_4` FOREIGN KEY (`driver_id`) REFERENCES `employee` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `spare_parts_pr_routing`
--
ALTER TABLE `spare_parts_pr_routing`
  ADD CONSTRAINT `spare_parts_pr_routing_ibfk_1` FOREIGN KEY (`pr_id`) REFERENCES `spare_parts_pr` (`id`),
  ADD CONSTRAINT `spare_parts_pr_routing_ibfk_2` FOREIGN KEY (`action_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `spare_parts_pr_routing_history`
--
ALTER TABLE `spare_parts_pr_routing_history`
  ADD CONSTRAINT `spare_parts_pr_routing_history_ibfk_1` FOREIGN KEY (`pr_id`) REFERENCES `spare_parts_pr` (`id`),
  ADD CONSTRAINT `spare_parts_pr_routing_history_ibfk_2` FOREIGN KEY (`action_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `spare_parts_pr_status_history`
--
ALTER TABLE `spare_parts_pr_status_history`
  ADD CONSTRAINT `spare_parts_pr_status_history_ibfk_1` FOREIGN KEY (`pr_id`) REFERENCES `spare_parts_pr` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `spare_parts_pr_status_history_ibfk_2` FOREIGN KEY (`changed_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `spare_parts_withdrawal_slips`
--
ALTER TABLE `spare_parts_withdrawal_slips`
  ADD CONSTRAINT `fk_withdrawal_slips_employee` FOREIGN KEY (`employee_id`) REFERENCES `employee` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `spare_parts_withdrawal_slips_ibfk_1` FOREIGN KEY (`pr_id`) REFERENCES `spare_parts_pr` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `spare_parts_withdrawal_slips_ibfk_2` FOREIGN KEY (`requested_by`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `spare_parts_withdrawal_slips_ibfk_3` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `spare_parts_withdrawal_slip_items`
--
ALTER TABLE `spare_parts_withdrawal_slip_items`
  ADD CONSTRAINT `spare_parts_withdrawal_slip_items_ibfk_1` FOREIGN KEY (`withdrawal_slip_id`) REFERENCES `spare_parts_withdrawal_slips` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `spare_parts_withdrawal_slip_items_ibfk_2` FOREIGN KEY (`pr_item_id`) REFERENCES `spare_parts_pr_items` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `spare_parts_withdrawal_slip_items_ibfk_3` FOREIGN KEY (`part_id`) REFERENCES `spare_parts` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `spare_part_po`
--
ALTER TABLE `spare_part_po`
  ADD CONSTRAINT `spare_part_po_ibfk_1` FOREIGN KEY (`pr_id`) REFERENCES `spare_parts_pr` (`id`),
  ADD CONSTRAINT `spare_part_po_ibfk_2` FOREIGN KEY (`supplier_id`) REFERENCES `spare_parts_suppliers` (`id`);

--
-- Constraints for table `spare_part_po_items`
--
ALTER TABLE `spare_part_po_items`
  ADD CONSTRAINT `spare_part_po_items_ibfk_1` FOREIGN KEY (`po_id`) REFERENCES `spare_part_po` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `spare_part_po_items_ibfk_2` FOREIGN KEY (`pr_item_id`) REFERENCES `spare_parts_pr_items` (`id`),
  ADD CONSTRAINT `spare_part_po_items_ibfk_3` FOREIGN KEY (`part_id`) REFERENCES `spare_parts` (`id`);

--
-- Constraints for table `stock_movements`
--
ALTER TABLE `stock_movements`
  ADD CONSTRAINT `fk_stock_movements_subcon` FOREIGN KEY (`subcon_id`) REFERENCES `subcons` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `stock_movements_ibfk_1` FOREIGN KEY (`item_id`) REFERENCES `item_names` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `stock_movements_ibfk_2` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `stock_movements_ibfk_3` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `stock_movements_ibfk_4` FOREIGN KEY (`warehouse_id`) REFERENCES `warehouses` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `stock_movements_ibfk_5` FOREIGN KEY (`transfer_from`) REFERENCES `warehouses` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `stock_movements_ibfk_6` FOREIGN KEY (`transfer_to`) REFERENCES `warehouses` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `stock_movements_ibfk_7` FOREIGN KEY (`pr_id`) REFERENCES `purchase_requests` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `stock_withdrawals`
--
ALTER TABLE `stock_withdrawals`
  ADD CONSTRAINT `stock_withdrawals_ibfk_1` FOREIGN KEY (`withdrawal_slip_id`) REFERENCES `withdrawal_slips` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `stock_withdrawals_ibfk_2` FOREIGN KEY (`withdrawal_slip_item_id`) REFERENCES `withdrawal_slip_items` (`id`),
  ADD CONSTRAINT `stock_withdrawals_ibfk_3` FOREIGN KEY (`item_id`) REFERENCES `item_names` (`id`),
  ADD CONSTRAINT `stock_withdrawals_ibfk_4` FOREIGN KEY (`batch_id`) REFERENCES `inventory_batches` (`id`),
  ADD CONSTRAINT `stock_withdrawals_ibfk_5` FOREIGN KEY (`withdrawal_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `wage_history`
--
ALTER TABLE `wage_history`
  ADD CONSTRAINT `wage_history_ibfk_1` FOREIGN KEY (`employee_id`) REFERENCES `employee` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `wage_history_ibfk_2` FOREIGN KEY (`changed_by`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `withdrawal_slips`
--
ALTER TABLE `withdrawal_slips`
  ADD CONSTRAINT `withdrawal_slips_ibfk_1` FOREIGN KEY (`pr_id`) REFERENCES `purchase_requests` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `withdrawal_slips_ibfk_2` FOREIGN KEY (`requested_by`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `withdrawal_slips_ibfk_3` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`),
  ADD CONSTRAINT `withdrawal_slips_ibfk_4` FOREIGN KEY (`warehouse_id`) REFERENCES `warehouses` (`id`);

--
-- Constraints for table `withdrawal_slip_items`
--
ALTER TABLE `withdrawal_slip_items`
  ADD CONSTRAINT `withdrawal_slip_items_ibfk_1` FOREIGN KEY (`withdrawal_slip_id`) REFERENCES `withdrawal_slips` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `withdrawal_slip_items_ibfk_2` FOREIGN KEY (`pr_item_id`) REFERENCES `pr_items` (`id`),
  ADD CONSTRAINT `withdrawal_slip_items_ibfk_3` FOREIGN KEY (`item_id`) REFERENCES `item_names` (`id`),
  ADD CONSTRAINT `withdrawal_slip_items_ibfk_4` FOREIGN KEY (`warehouse_id`) REFERENCES `warehouses` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
