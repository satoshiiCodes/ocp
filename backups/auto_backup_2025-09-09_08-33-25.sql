-- SKYLINE Database Backup (Auto)
-- Generated: 2025-09-09 08:33:25 (Philippine Time)
-- Database: db_skyline

SET FOREIGN_KEY_CHECKS=0;

DROP TABLE IF EXISTS `employee`;
CREATE TABLE `employee` (
  `id` int NOT NULL AUTO_INCREMENT,
  `employee_id` varchar(50) NOT NULL,
  `firstname` varchar(100) NOT NULL,
  `middlename` varchar(100) DEFAULT NULL,
  `lastname` varchar(100) NOT NULL,
  `suffix` varchar(10) DEFAULT NULL,
  `address` text,
  `contact_number` varchar(20) DEFAULT NULL,
  `birth_date` date DEFAULT NULL,
  `marital_status` enum('Single','Married','Divorced','Widowed') DEFAULT NULL,
  `position` enum('Operator','Driver','Mechanic','Welder','Vulcanizer','Building Electrician','Auto Electrician','Foreman','Skilled','Helper','Labor','Flockman','Cook | Office Helper','Painter') CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
  `hire_date` date NOT NULL,
  `daily_wage` decimal(10,2) DEFAULT '0.00',
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `employee_id` (`employee_id`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Dumping data for table `employee`
INSERT INTO `employee` (`id`, `employee_id`, `firstname`, `middlename`, `lastname`, `suffix`, `address`, `contact_number`, `birth_date`, `marital_status`, `position`, `hire_date`, `daily_wage`, `status`, `created_at`, `updated_at`) VALUES ('1', '0121', 'Juls', 'Barcarse', 'Garcia', '', 'Cagayan', '09560285830', '2000-07-12', 'Single', 'Foreman', '2025-09-07', '600.00', 'active', '2025-09-07 11:19:07', '2025-09-07 14:22:46');
INSERT INTO `employee` (`id`, `employee_id`, `firstname`, `middlename`, `lastname`, `suffix`, `address`, `contact_number`, `birth_date`, `marital_status`, `position`, `hire_date`, `daily_wage`, `status`, `created_at`, `updated_at`) VALUES ('2', '0122', 'Randy', 'Cruz', 'Suyu', 'III', 'Cagayan', '09560285830', '2000-01-22', 'Single', 'Welder', '2025-09-07', '550.00', 'active', '2025-09-07 11:35:07', '2025-09-07 14:23:05');
INSERT INTO `employee` (`id`, `employee_id`, `firstname`, `middlename`, `lastname`, `suffix`, `address`, `contact_number`, `birth_date`, `marital_status`, `position`, `hire_date`, `daily_wage`, `status`, `created_at`, `updated_at`) VALUES ('3', '0123', 'Tom', 'Rizal', 'Damaso', '', 'Cagayan', '09560285830', '1996-03-21', 'Single', 'Skilled', '2025-09-07', '550.00', 'active', '2025-09-07 11:36:34', '2025-09-07 14:23:11');
INSERT INTO `employee` (`id`, `employee_id`, `firstname`, `middlename`, `lastname`, `suffix`, `address`, `contact_number`, `birth_date`, `marital_status`, `position`, `hire_date`, `daily_wage`, `status`, `created_at`, `updated_at`) VALUES ('4', '0124', 'Rodel', 'Telan', 'Cumbali', '', 'Cagayan', '09560285830', '1999-04-01', 'Single', 'Helper', '2025-09-07', '450.00', 'active', '2025-09-07 11:37:27', '2025-09-07 23:55:26');
INSERT INTO `employee` (`id`, `employee_id`, `firstname`, `middlename`, `lastname`, `suffix`, `address`, `contact_number`, `birth_date`, `marital_status`, `position`, `hire_date`, `daily_wage`, `status`, `created_at`, `updated_at`) VALUES ('5', '0125', 'Jorem', 'Bangloy', 'Barangan', '', 'Cagayan', '09560285830', '1998-07-22', 'Married', 'Driver', '2025-09-08', '500.00', 'active', '2025-09-08 12:00:27', '2025-09-08 12:00:27');

DROP TABLE IF EXISTS `equipment`;
CREATE TABLE `equipment` (
  `id` int NOT NULL AUTO_INCREMENT,
  `equipment_name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `fuel_type` enum('gasoline','diesel','electric') COLLATE utf8mb4_unicode_ci DEFAULT 'gasoline',
  `description` text COLLATE utf8mb4_unicode_ci,
  `is_active` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table `equipment`
INSERT INTO `equipment` (`id`, `equipment_name`, `fuel_type`, `description`, `is_active`, `created_at`, `updated_at`) VALUES ('2', 'Dumtruck', 'diesel', 'Dumtruck', '1', '2025-09-08 12:49:31', '2025-09-08 12:49:31');

DROP TABLE IF EXISTS `gasoline_inventory`;
CREATE TABLE `gasoline_inventory` (
  `id` int NOT NULL AUTO_INCREMENT,
  `gasoline_type` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tank_id` int NOT NULL,
  `quantity_liters` decimal(10,2) NOT NULL DEFAULT '0.00',
  `price_per_liter` decimal(10,2) NOT NULL DEFAULT '0.00',
  `last_updated` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_gasoline_tank` (`gasoline_type`,`tank_id`),
  KEY `tank_id` (`tank_id`),
  CONSTRAINT `gasoline_inventory_ibfk_1` FOREIGN KEY (`tank_id`) REFERENCES `gasoline_tanks` (`id`),
  CONSTRAINT `gasoline_inventory_chk_1` CHECK ((`quantity_liters` >= 0)),
  CONSTRAINT `gasoline_inventory_chk_2` CHECK ((`price_per_liter` >= 0))
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table `gasoline_inventory`
INSERT INTO `gasoline_inventory` (`id`, `gasoline_type`, `tank_id`, `quantity_liters`, `price_per_liter`, `last_updated`, `created_at`) VALUES ('5', 'Diesel', '3', '40.00', '50.00', '2025-09-08 13:02:24', '2025-09-08 13:00:16');

DROP TABLE IF EXISTS `gasoline_min_levels`;
CREATE TABLE `gasoline_min_levels` (
  `id` int NOT NULL AUTO_INCREMENT,
  `tank_id` int NOT NULL,
  `gasoline_type` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `min_stock_liters` decimal(10,2) NOT NULL DEFAULT '0.00',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_tank_gasoline` (`tank_id`,`gasoline_type`),
  CONSTRAINT `gasoline_min_levels_ibfk_1` FOREIGN KEY (`tank_id`) REFERENCES `gasoline_tanks` (`id`),
  CONSTRAINT `gasoline_min_levels_chk_1` CHECK ((`min_stock_liters` >= 0))
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table `gasoline_min_levels`
INSERT INTO `gasoline_min_levels` (`id`, `tank_id`, `gasoline_type`, `min_stock_liters`, `created_at`, `updated_at`) VALUES ('4', '3', 'Diesel', '50.00', '2025-09-08 13:01:29', '2025-09-08 13:01:29');

DROP TABLE IF EXISTS `gasoline_movements`;
CREATE TABLE `gasoline_movements` (
  `id` int NOT NULL AUTO_INCREMENT,
  `gasoline_type` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `movement_type` enum('in','out') COLLATE utf8mb4_unicode_ci NOT NULL,
  `tank_id` int NOT NULL,
  `quantity_liters` decimal(10,2) NOT NULL,
  `price_per_liter` decimal(10,2) NOT NULL DEFAULT '0.00',
  `movement_date` date NOT NULL,
  `supplier_id` int DEFAULT NULL,
  `purchase_order` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `vehicle_id` int DEFAULT NULL,
  `equipment_id` int DEFAULT NULL,
  `driver_operator` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `purpose` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `odometer_reading` int DEFAULT NULL,
  `transfer_from` int DEFAULT NULL,
  `transfer_to` int DEFAULT NULL,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `supplier_id` (`supplier_id`),
  KEY `vehicle_id` (`vehicle_id`),
  KEY `equipment_id` (`equipment_id`),
  KEY `transfer_from` (`transfer_from`),
  KEY `transfer_to` (`transfer_to`),
  KEY `idx_movement_date` (`movement_date`),
  KEY `idx_gasoline_type` (`gasoline_type`),
  KEY `idx_movement_type` (`movement_type`),
  KEY `idx_tank_id` (`tank_id`),
  CONSTRAINT `gasoline_movements_ibfk_1` FOREIGN KEY (`tank_id`) REFERENCES `gasoline_tanks` (`id`),
  CONSTRAINT `gasoline_movements_ibfk_2` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`),
  CONSTRAINT `gasoline_movements_ibfk_3` FOREIGN KEY (`vehicle_id`) REFERENCES `vehicles` (`id`),
  CONSTRAINT `gasoline_movements_ibfk_4` FOREIGN KEY (`equipment_id`) REFERENCES `equipment` (`id`),
  CONSTRAINT `gasoline_movements_ibfk_5` FOREIGN KEY (`transfer_from`) REFERENCES `gasoline_tanks` (`id`),
  CONSTRAINT `gasoline_movements_ibfk_6` FOREIGN KEY (`transfer_to`) REFERENCES `gasoline_tanks` (`id`),
  CONSTRAINT `gasoline_movements_chk_1` CHECK ((`quantity_liters` > 0)),
  CONSTRAINT `gasoline_movements_chk_2` CHECK ((`price_per_liter` >= 0))
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table `gasoline_movements`
INSERT INTO `gasoline_movements` (`id`, `gasoline_type`, `movement_type`, `tank_id`, `quantity_liters`, `price_per_liter`, `movement_date`, `supplier_id`, `purchase_order`, `vehicle_id`, `equipment_id`, `driver_operator`, `purpose`, `odometer_reading`, `transfer_from`, `transfer_to`, `notes`, `created_at`, `updated_at`) VALUES ('9', 'Diesel', 'in', '3', '100.00', '50.00', '2025-09-08', '4', '129192', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-09-08 13:00:16', '2025-09-08 13:00:16');
INSERT INTO `gasoline_movements` (`id`, `gasoline_type`, `movement_type`, `tank_id`, `quantity_liters`, `price_per_liter`, `movement_date`, `supplier_id`, `purchase_order`, `vehicle_id`, `equipment_id`, `driver_operator`, `purpose`, `odometer_reading`, `transfer_from`, `transfer_to`, `notes`, `created_at`, `updated_at`) VALUES ('10', 'Diesel', 'out', '3', '60.00', '50.00', '2025-09-08', NULL, NULL, '2', NULL, 'LJ Lopez', 'Travel to Project', NULL, NULL, NULL, NULL, '2025-09-08 13:02:24', '2025-09-08 13:02:24');

DROP TABLE IF EXISTS `gasoline_suppliers`;
CREATE TABLE `gasoline_suppliers` (
  `id` int NOT NULL AUTO_INCREMENT,
  `supplier_name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `supplier_type` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `contact_person` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `phone` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `address` text COLLATE utf8mb4_unicode_ci,
  `is_active` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_supplier_name` (`supplier_name`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table `gasoline_suppliers`
INSERT INTO `gasoline_suppliers` (`id`, `supplier_name`, `supplier_type`, `contact_person`, `phone`, `email`, `address`, `is_active`, `created_at`, `updated_at`) VALUES ('4', 'Gasoline Supplier 1', 'Fuel', 'Jorem', '09560285830', 'test@gmail.com', 'Cagayan', '1', '2025-09-08 12:48:44', '2025-09-08 12:48:44');

DROP TABLE IF EXISTS `gasoline_tanks`;
CREATE TABLE `gasoline_tanks` (
  `id` int NOT NULL AUTO_INCREMENT,
  `tank_name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `location` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `capacity_liters` decimal(10,2) NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `is_active` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_tank_name` (`tank_name`),
  CONSTRAINT `gasoline_tanks_chk_1` CHECK ((`capacity_liters` > 0))
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table `gasoline_tanks`
INSERT INTO `gasoline_tanks` (`id`, `tank_name`, `location`, `capacity_liters`, `description`, `is_active`, `created_at`, `updated_at`) VALUES ('3', 'Tank 1', 'Cagayan', '1000.00', 'Tank 1', '1', '2025-09-08 12:48:13', '2025-09-08 12:48:13');
INSERT INTO `gasoline_tanks` (`id`, `tank_name`, `location`, `capacity_liters`, `description`, `is_active`, `created_at`, `updated_at`) VALUES ('4', 'Tank 2', 'Cagayan', '1000.00', 'Tank 2', '1', '2025-09-08 12:48:23', '2025-09-08 12:48:23');

DROP TABLE IF EXISTS `inventory`;
CREATE TABLE `inventory` (
  `id` int NOT NULL AUTO_INCREMENT,
  `item_id` int NOT NULL,
  `warehouse_id` int NOT NULL,
  `quantity` int NOT NULL DEFAULT '0',
  `unit_cost` decimal(10,2) DEFAULT '0.00',
  `last_updated` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `total_value` decimal(15,2) DEFAULT '0.00',
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_item_warehouse` (`item_id`,`warehouse_id`),
  KEY `warehouse_id` (`warehouse_id`),
  CONSTRAINT `inventory_ibfk_1` FOREIGN KEY (`item_id`) REFERENCES `item_names` (`id`) ON DELETE CASCADE,
  CONSTRAINT `inventory_ibfk_2` FOREIGN KEY (`warehouse_id`) REFERENCES `warehouses` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=53 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

DROP TABLE IF EXISTS `inventory_batches`;
CREATE TABLE `inventory_batches` (
  `id` int NOT NULL AUTO_INCREMENT,
  `item_id` int NOT NULL,
  `warehouse_id` int NOT NULL,
  `batch_number` varchar(50) NOT NULL,
  `quantity` decimal(10,2) NOT NULL DEFAULT '0.00',
  `unit_cost` decimal(10,2) NOT NULL DEFAULT '0.00',
  `received_date` date NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `warehouse_id` (`warehouse_id`),
  KEY `idx_item_warehouse` (`item_id`,`warehouse_id`),
  KEY `idx_batch_number` (`batch_number`),
  KEY `idx_received_date` (`received_date`),
  CONSTRAINT `inventory_batches_ibfk_1` FOREIGN KEY (`item_id`) REFERENCES `item_names` (`id`) ON DELETE CASCADE,
  CONSTRAINT `inventory_batches_ibfk_2` FOREIGN KEY (`warehouse_id`) REFERENCES `warehouses` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Dumping data for table `inventory_batches`
INSERT INTO `inventory_batches` (`id`, `item_id`, `warehouse_id`, `batch_number`, `quantity`, `unit_cost`, `received_date`, `created_at`, `updated_at`) VALUES ('7', '7', '4', 'INITIAL-20250908231220', '0.00', '100.00', '2025-09-08', '2025-09-09 07:12:20', '2025-09-09 07:16:05');
INSERT INTO `inventory_batches` (`id`, `item_id`, `warehouse_id`, `batch_number`, `quantity`, `unit_cost`, `received_date`, `created_at`, `updated_at`) VALUES ('8', '7', '4', 'BATCH-20250908231220', '0.00', '50.00', '2025-09-08', '2025-09-09 07:12:39', '2025-09-09 07:16:05');
INSERT INTO `inventory_batches` (`id`, `item_id`, `warehouse_id`, `batch_number`, `quantity`, `unit_cost`, `received_date`, `created_at`, `updated_at`) VALUES ('9', '7', '4', 'BATCH-20250908231239', '0.00', '25.00', '2025-09-08', '2025-09-09 07:13:03', '2025-09-09 07:16:05');
INSERT INTO `inventory_batches` (`id`, `item_id`, `warehouse_id`, `batch_number`, `quantity`, `unit_cost`, `received_date`, `created_at`, `updated_at`) VALUES ('10', '7', '4', 'BATCH-20250908231303', '0.00', '50.00', '2025-09-08', '2025-09-09 07:13:39', '2025-09-09 07:16:05');
INSERT INTO `inventory_batches` (`id`, `item_id`, `warehouse_id`, `batch_number`, `quantity`, `unit_cost`, `received_date`, `created_at`, `updated_at`) VALUES ('11', '7', '4', 'INITIAL-20250908231815', '0.00', '15.00', '2025-09-08', '2025-09-09 07:18:15', '2025-09-09 07:50:48');
INSERT INTO `inventory_batches` (`id`, `item_id`, `warehouse_id`, `batch_number`, `quantity`, `unit_cost`, `received_date`, `created_at`, `updated_at`) VALUES ('12', '7', '4', 'BATCH-20250908234900', '0.00', '100.00', '2025-09-08', '2025-09-09 07:49:51', '2025-09-09 07:50:48');

DROP TABLE IF EXISTS `item_names`;
CREATE TABLE `item_names` (
  `id` int NOT NULL AUTO_INCREMENT,
  `item_code` varchar(50) NOT NULL,
  `item_name` varchar(255) NOT NULL,
  `min_stock_level` int NOT NULL DEFAULT '0',
  `item_type` varchar(100) NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `item_code` (`item_code`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb3;

-- Dumping data for table `item_names`
INSERT INTO `item_names` (`id`, `item_code`, `item_name`, `min_stock_level`, `item_type`, `created_at`, `updated_at`) VALUES ('7', '001', 'Bakal 2mm', '10', 'Metal', '2025-09-08 12:28:24', '2025-09-09 07:17:53');

DROP TABLE IF EXISTS `project_engineers`;
CREATE TABLE `project_engineers` (
  `id` int NOT NULL AUTO_INCREMENT,
  `project_id` int NOT NULL,
  `user_id` int NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `project_id` (`project_id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `project_engineers_ibfk_1` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE,
  CONSTRAINT `project_engineers_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Dumping data for table `project_engineers`
INSERT INTO `project_engineers` (`id`, `project_id`, `user_id`, `created_at`) VALUES ('6', '7', '2', '2025-09-08 12:37:33');

DROP TABLE IF EXISTS `project_workers`;
CREATE TABLE `project_workers` (
  `id` int NOT NULL AUTO_INCREMENT,
  `project_id` int NOT NULL,
  `user_id` int NOT NULL,
  `assigned_date` date NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `project_id` (`project_id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `project_workers_ibfk_1` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE,
  CONSTRAINT `project_workers_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `employee` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=23 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Dumping data for table `project_workers`
INSERT INTO `project_workers` (`id`, `project_id`, `user_id`, `assigned_date`, `created_at`) VALUES ('19', '7', '1', '2025-09-08', '2025-09-08 12:38:13');
INSERT INTO `project_workers` (`id`, `project_id`, `user_id`, `assigned_date`, `created_at`) VALUES ('20', '7', '2', '2025-09-08', '2025-09-08 12:38:13');
INSERT INTO `project_workers` (`id`, `project_id`, `user_id`, `assigned_date`, `created_at`) VALUES ('22', '7', '4', '2025-09-09', '2025-09-09 07:10:21');

DROP TABLE IF EXISTS `projects`;
CREATE TABLE `projects` (
  `id` int NOT NULL AUTO_INCREMENT,
  `project_name` varchar(255) NOT NULL,
  `project_code` varchar(50) NOT NULL,
  `address` text,
  `description` text,
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `status` enum('planning','active','completed','on-hold') DEFAULT 'planning',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `project_code` (`project_code`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Dumping data for table `projects`
INSERT INTO `projects` (`id`, `project_name`, `project_code`, `address`, `description`, `start_date`, `end_date`, `status`, `created_at`, `updated_at`) VALUES ('7', 'Project1', '01', 'Tuguegarao City, Cagayan', 'Project1', '2025-09-08', '2025-10-31', 'active', '2025-09-08 12:37:33', '2025-09-08 12:37:33');

DROP TABLE IF EXISTS `stock_movements`;
CREATE TABLE `stock_movements` (
  `id` int NOT NULL AUTO_INCREMENT,
  `item_id` int NOT NULL,
  `supplier_id` int DEFAULT NULL,
  `project_id` int DEFAULT NULL,
  `warehouse_id` int NOT NULL,
  `quantity` int NOT NULL,
  `unit_cost` decimal(10,2) DEFAULT '0.00',
  `movement_type` enum('in','out') NOT NULL,
  `movement_date` date NOT NULL,
  `batch_number` varchar(50) DEFAULT NULL,
  `transfer_from` int DEFAULT NULL,
  `transfer_to` int DEFAULT NULL,
  `notes` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `total_value` decimal(15,2) DEFAULT '0.00',
  PRIMARY KEY (`id`),
  KEY `item_id` (`item_id`),
  KEY `supplier_id` (`supplier_id`),
  KEY `project_id` (`project_id`),
  KEY `warehouse_id` (`warehouse_id`),
  KEY `transfer_from` (`transfer_from`),
  KEY `transfer_to` (`transfer_to`),
  KEY `idx_batch_number` (`batch_number`),
  CONSTRAINT `stock_movements_ibfk_1` FOREIGN KEY (`item_id`) REFERENCES `item_names` (`id`) ON DELETE CASCADE,
  CONSTRAINT `stock_movements_ibfk_2` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`) ON DELETE SET NULL,
  CONSTRAINT `stock_movements_ibfk_3` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE SET NULL,
  CONSTRAINT `stock_movements_ibfk_4` FOREIGN KEY (`warehouse_id`) REFERENCES `warehouses` (`id`) ON DELETE CASCADE,
  CONSTRAINT `stock_movements_ibfk_5` FOREIGN KEY (`transfer_from`) REFERENCES `warehouses` (`id`) ON DELETE SET NULL,
  CONSTRAINT `stock_movements_ibfk_6` FOREIGN KEY (`transfer_to`) REFERENCES `warehouses` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=106 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Dumping data for table `stock_movements`
INSERT INTO `stock_movements` (`id`, `item_id`, `supplier_id`, `project_id`, `warehouse_id`, `quantity`, `unit_cost`, `movement_type`, `movement_date`, `batch_number`, `transfer_from`, `transfer_to`, `notes`, `created_at`, `total_value`) VALUES ('94', '7', NULL, NULL, '4', '10', '100.00', 'in', '2025-09-08', 'INITIAL-20250908231220', NULL, NULL, 'Initial stock', '2025-09-09 07:12:20', '0.00');
INSERT INTO `stock_movements` (`id`, `item_id`, `supplier_id`, `project_id`, `warehouse_id`, `quantity`, `unit_cost`, `movement_type`, `movement_date`, `batch_number`, `transfer_from`, `transfer_to`, `notes`, `created_at`, `total_value`) VALUES ('95', '7', '4', NULL, '4', '10', '50.00', 'in', '2025-09-08', 'BATCH-20250908231220', NULL, NULL, NULL, '2025-09-09 07:12:39', '0.00');
INSERT INTO `stock_movements` (`id`, `item_id`, `supplier_id`, `project_id`, `warehouse_id`, `quantity`, `unit_cost`, `movement_type`, `movement_date`, `batch_number`, `transfer_from`, `transfer_to`, `notes`, `created_at`, `total_value`) VALUES ('96', '7', '4', NULL, '4', '30', '25.00', 'in', '2025-09-08', 'BATCH-20250908231239', NULL, NULL, NULL, '2025-09-09 07:13:03', '0.00');
INSERT INTO `stock_movements` (`id`, `item_id`, `supplier_id`, `project_id`, `warehouse_id`, `quantity`, `unit_cost`, `movement_type`, `movement_date`, `batch_number`, `transfer_from`, `transfer_to`, `notes`, `created_at`, `total_value`) VALUES ('97', '7', '5', NULL, '4', '75', '50.00', 'in', '2025-09-08', 'BATCH-20250908231303', NULL, NULL, NULL, '2025-09-09 07:13:39', '0.00');
INSERT INTO `stock_movements` (`id`, `item_id`, `supplier_id`, `project_id`, `warehouse_id`, `quantity`, `unit_cost`, `movement_type`, `movement_date`, `batch_number`, `transfer_from`, `transfer_to`, `notes`, `created_at`, `total_value`) VALUES ('98', '7', NULL, '7', '4', '10', '100.00', 'out', '2025-09-08', 'INITIAL-20250908231220', NULL, NULL, NULL, '2025-09-09 07:16:05', '0.00');
INSERT INTO `stock_movements` (`id`, `item_id`, `supplier_id`, `project_id`, `warehouse_id`, `quantity`, `unit_cost`, `movement_type`, `movement_date`, `batch_number`, `transfer_from`, `transfer_to`, `notes`, `created_at`, `total_value`) VALUES ('99', '7', NULL, '7', '4', '10', '50.00', 'out', '2025-09-08', 'BATCH-20250908231220', NULL, NULL, NULL, '2025-09-09 07:16:05', '0.00');
INSERT INTO `stock_movements` (`id`, `item_id`, `supplier_id`, `project_id`, `warehouse_id`, `quantity`, `unit_cost`, `movement_type`, `movement_date`, `batch_number`, `transfer_from`, `transfer_to`, `notes`, `created_at`, `total_value`) VALUES ('100', '7', NULL, '7', '4', '30', '25.00', 'out', '2025-09-08', 'BATCH-20250908231239', NULL, NULL, NULL, '2025-09-09 07:16:05', '0.00');
INSERT INTO `stock_movements` (`id`, `item_id`, `supplier_id`, `project_id`, `warehouse_id`, `quantity`, `unit_cost`, `movement_type`, `movement_date`, `batch_number`, `transfer_from`, `transfer_to`, `notes`, `created_at`, `total_value`) VALUES ('101', '7', NULL, '7', '4', '75', '50.00', 'out', '2025-09-08', 'BATCH-20250908231303', NULL, NULL, NULL, '2025-09-09 07:16:05', '0.00');
INSERT INTO `stock_movements` (`id`, `item_id`, `supplier_id`, `project_id`, `warehouse_id`, `quantity`, `unit_cost`, `movement_type`, `movement_date`, `batch_number`, `transfer_from`, `transfer_to`, `notes`, `created_at`, `total_value`) VALUES ('102', '7', NULL, NULL, '4', '50', '15.00', 'in', '2025-09-08', 'INITIAL-20250908231815', NULL, NULL, 'Initial stock', '2025-09-09 07:18:15', '0.00');
INSERT INTO `stock_movements` (`id`, `item_id`, `supplier_id`, `project_id`, `warehouse_id`, `quantity`, `unit_cost`, `movement_type`, `movement_date`, `batch_number`, `transfer_from`, `transfer_to`, `notes`, `created_at`, `total_value`) VALUES ('103', '7', '5', NULL, '4', '10', '100.00', 'in', '2025-09-08', 'BATCH-20250908234900', NULL, NULL, NULL, '2025-09-09 07:49:51', '0.00');
INSERT INTO `stock_movements` (`id`, `item_id`, `supplier_id`, `project_id`, `warehouse_id`, `quantity`, `unit_cost`, `movement_type`, `movement_date`, `batch_number`, `transfer_from`, `transfer_to`, `notes`, `created_at`, `total_value`) VALUES ('104', '7', NULL, '7', '4', '50', '15.00', 'out', '2025-09-08', 'INITIAL-20250908231815', NULL, NULL, NULL, '2025-09-09 07:50:48', '0.00');
INSERT INTO `stock_movements` (`id`, `item_id`, `supplier_id`, `project_id`, `warehouse_id`, `quantity`, `unit_cost`, `movement_type`, `movement_date`, `batch_number`, `transfer_from`, `transfer_to`, `notes`, `created_at`, `total_value`) VALUES ('105', '7', NULL, '7', '4', '10', '100.00', 'out', '2025-09-08', 'BATCH-20250908234900', NULL, NULL, NULL, '2025-09-09 07:50:48', '0.00');

DROP TABLE IF EXISTS `suppliers`;
CREATE TABLE `suppliers` (
  `id` int NOT NULL AUTO_INCREMENT,
  `supplier_name` varchar(255) NOT NULL,
  `contact_person` varchar(255) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `address` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Dumping data for table `suppliers`
INSERT INTO `suppliers` (`id`, `supplier_name`, `contact_person`, `phone`, `email`, `address`, `created_at`, `updated_at`) VALUES ('4', 'Supplier', 'Juan Baranagan', '09560285830', 'test@gmail.com', 'Cagayan', '2025-09-08 12:26:58', '2025-09-08 12:26:58');
INSERT INTO `suppliers` (`id`, `supplier_name`, `contact_person`, `phone`, `email`, `address`, `created_at`, `updated_at`) VALUES ('5', 'Supplier 2', 'Randy', '09560285830', 'test1@gmail.com', 'Supplier 2', '2025-09-08 13:10:17', '2025-09-08 13:10:17');

DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
  `id` int NOT NULL AUTO_INCREMENT,
  `lastname` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `firstname` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `middlename` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `suffix` enum('','Sr.','Jr.','III','IV') COLLATE utf8mb4_unicode_ci DEFAULT '',
  `department` enum('Engineering','Warehouse','Admin','Site','IT') COLLATE utf8mb4_unicode_ci NOT NULL,
  `position` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `address` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `contact` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` enum('active','inactive','on-leave') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `accounttype` enum('Admin','Staff') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Admin',
  `email` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `username` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `password` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `registration_date` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `last_login` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`),
  UNIQUE KEY `username` (`username`),
  KEY `idx_department` (`department`),
  KEY `idx_status` (`status`),
  KEY `idx_accounttype` (`accounttype`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table `users`
INSERT INTO `users` (`id`, `lastname`, `firstname`, `middlename`, `suffix`, `department`, `position`, `address`, `contact`, `status`, `accounttype`, `email`, `username`, `password`, `registration_date`, `last_login`) VALUES ('1', 'Barangan', 'Josue', 'Bangloy', 'III', 'IT', 'Assistant IT Programmer', 'Tuguegarao City, Cagayan', '09560285830', 'active', 'Admin', 'test@gmail.com', 'Admin', '$2y$10$OMnHoCN6fK2vWXwwKYIes.oOOaQ.pLsa.YeItL6R9HD5uCYxwJtpm', '2025-09-04 22:54:39', '2025-09-09 08:20:11');
INSERT INTO `users` (`id`, `lastname`, `firstname`, `middlename`, `suffix`, `department`, `position`, `address`, `contact`, `status`, `accounttype`, `email`, `username`, `password`, `registration_date`, `last_login`) VALUES ('2', 'Salatan', 'Rio', 'Grapas', 'Sr.', 'Engineering', 'Project Engineer', 'Tuguegarao City, Cagayan', '09560285830', 'active', 'Admin', 'salatan@gmail.com', 'Engineering', '$2y$10$qRrZbbn3sXrJxzbJjOb8jeAvIZy55Oo/DIypCqLWk7Zlqlil97wGa', '2025-09-06 07:43:48', NULL);

DROP TABLE IF EXISTS `vehicles`;
CREATE TABLE `vehicles` (
  `id` int NOT NULL AUTO_INCREMENT,
  `vehicle_name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `plate_number` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `fuel_type` enum('gasoline','diesel','electric','hybrid') COLLATE utf8mb4_unicode_ci DEFAULT 'gasoline',
  `description` text COLLATE utf8mb4_unicode_ci,
  `is_active` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_plate_number` (`plate_number`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table `vehicles`
INSERT INTO `vehicles` (`id`, `vehicle_name`, `plate_number`, `fuel_type`, `description`, `is_active`, `created_at`, `updated_at`) VALUES ('2', 'Trailer', 'HDN 1023', 'diesel', 'Trailer', '1', '2025-09-08 12:49:15', '2025-09-08 12:49:15');

DROP TABLE IF EXISTS `wage_history`;
CREATE TABLE `wage_history` (
  `id` int NOT NULL AUTO_INCREMENT,
  `employee_id` int NOT NULL,
  `old_wage` decimal(10,2) DEFAULT '0.00',
  `new_wage` decimal(10,2) DEFAULT '0.00',
  `change_amount` decimal(10,2) DEFAULT '0.00',
  `change_percentage` decimal(5,2) DEFAULT '0.00',
  `change_type` enum('increase','decrease','no change') DEFAULT 'no change',
  `changed_by` int NOT NULL,
  `change_reason` text,
  `changed_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `employee_id` (`employee_id`),
  KEY `changed_by` (`changed_by`),
  CONSTRAINT `wage_history_ibfk_1` FOREIGN KEY (`employee_id`) REFERENCES `employee` (`id`) ON DELETE CASCADE,
  CONSTRAINT `wage_history_ibfk_2` FOREIGN KEY (`changed_by`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Dumping data for table `wage_history`
INSERT INTO `wage_history` (`id`, `employee_id`, `old_wage`, `new_wage`, `change_amount`, `change_percentage`, `change_type`, `changed_by`, `change_reason`, `changed_at`) VALUES ('5', '5', '0.00', '500.00', '500.00', '100.00', 'increase', '1', 'Initial wage setting', '2025-09-08 12:00:27');

DROP TABLE IF EXISTS `warehouses`;
CREATE TABLE `warehouses` (
  `id` int NOT NULL AUTO_INCREMENT,
  `warehouse_name` varchar(255) NOT NULL,
  `location` varchar(255) NOT NULL,
  `capacity` int DEFAULT NULL,
  `manager` varchar(255) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Dumping data for table `warehouses`
INSERT INTO `warehouses` (`id`, `warehouse_name`, `location`, `capacity`, `manager`, `phone`, `created_at`, `updated_at`) VALUES ('4', 'Piddig Warehouse', 'Ilocos', '10000', 'Juan De Vera', '09560285830', '2025-09-08 12:26:00', '2025-09-08 12:26:00');
INSERT INTO `warehouses` (`id`, `warehouse_name`, `location`, `capacity`, `manager`, `phone`, `created_at`, `updated_at`) VALUES ('5', 'Claveria Warehouse', 'Cagayan', '10000', 'Juan De Vera', '09560285830', '2025-09-08 12:46:13', '2025-09-08 12:46:13');

SET FOREIGN_KEY_CHECKS=1;
