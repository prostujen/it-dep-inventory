CREATE DATABASE IF NOT EXISTS `it_dep_inventory` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `it_dep_inventory`;

SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `full_name` varchar(100) DEFAULT NULL,
  `role` enum('admin','teacher') NOT NULL DEFAULT 'teacher',
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `users` VALUES 
('1', 'admin', '$2y$10$WThZmGOBio/t/lfEyS1Vlu6Sy.pDEDjQGA3JBii6BLitZdJJzw86i', 'Системний адміністратор', 'admin');

DROP TABLE IF EXISTS `devices`;
CREATE TABLE `devices` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `inventory_number` varchar(50) DEFAULT NULL,
  `name` varchar(100) NOT NULL,
  `model` varchar(100) DEFAULT NULL,
  `serial_number` varchar(100) DEFAULT NULL,
  `status` enum('в роботі','очікує перевірки','на ремонті','в резерві','списано') NOT NULL DEFAULT 'в резерві',
  `location` varchar(100) DEFAULT NULL,
  `accepted_date` date DEFAULT NULL,
  `responsible_person` varchar(100) NOT NULL,
  `is_pc` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `inventory_number` (`inventory_number`),
  UNIQUE KEY `serial_number` (`serial_number`),
  KEY `idx_status` (`status`),
  KEY `idx_location` (`location`)
) ENGINE=InnoDB AUTO_INCREMENT=15 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `devices` VALUES 
('13', '1000110001', 'Test Printer HPTest Printer HP', 'LaserJet ProLaserJet Pro', 'SN123456SN123456', 'на ремонті', 'Room 201Room 201', NULL, 'AdminAdmin', '1'),
('14', NULL, 'Testovyi komutator', NULL, NULL, 'на ремонті', NULL, NULL, 'Testovyi komutator', '0');

DROP TABLE IF EXISTS `network_settings`;
CREATE TABLE `network_settings` (
  `device_id` int(11) NOT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `subnet_mask` varchar(45) DEFAULT NULL,
  `gateway` varchar(45) DEFAULT NULL,
  `dns_server` varchar(45) DEFAULT NULL,
  `mac_address` varchar(17) DEFAULT NULL,
  PRIMARY KEY (`device_id`),
  CONSTRAINT `network_settings_ibfk_1` FOREIGN KEY (`device_id`) REFERENCES `devices` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `network_settings` VALUES 
('13', NULL, NULL, NULL, NULL, NULL),
('14', NULL, NULL, NULL, NULL, NULL);

DROP TABLE IF EXISTS `device_history`;
CREATE TABLE `device_history` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `device_id` int(11) NOT NULL,
  `event_type` enum('переміщення','зміна статусу','ремонт','створення','інше') NOT NULL,
  `old_value` varchar(255) DEFAULT NULL,
  `new_value` varchar(255) DEFAULT NULL,
  `event_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `responsible_person` varchar(100) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `device_id` (`device_id`),
  CONSTRAINT `device_history_ibfk_1` FOREIGN KEY (`device_id`) REFERENCES `devices` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=37 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `device_history` VALUES 
('17', '13', 'створення', NULL, 'Пристрій додано в систему', '2026-06-25 09:24:43', 'AdminAdmin'),
('18', '13', 'ремонт', 'Нова витрата на обслуговування', '«Cartridge HP 85A» (витратний матеріал) — 450 грн', '2026-06-25 09:25:45', 'Системний адміністратор'),
('19', '13', 'зміна статусу', 'очікує перевірки', 'списано', '2026-06-25 09:30:58', 'AdminAdmin'),
('20', '13', 'зміна статусу', 'списано', 'очікує перевірки', '2026-06-25 09:31:38', 'AdminAdmin'),
('21', '13', 'зміна статусу', 'очікує перевірки', 'в резерві', '2026-06-25 09:32:00', 'AdminAdmin'),
('22', '13', 'зміна статусу', 'в резерві', 'в роботі', '2026-06-25 09:32:19', 'AdminAdmin'),
('23', '13', 'зміна статусу', 'в роботі', 'очікує перевірки', '2026-06-25 09:32:40', 'AdminAdmin'),
('24', '13', 'зміна статусу', 'очікує перевірки', 'на ремонті', '2026-06-25 09:33:08', 'AdminAdmin'),
('25', '14', 'створення', NULL, 'Пристрій додано в систему', '2026-06-25 09:46:03', 'Testovyi komutator'),
('26', '14', 'зміна статусу', 'в роботі', 'на ремонті', '2026-06-25 09:46:28', 'Testovyi komutator'),
('27', '13', 'ремонт', 'Нова витрата на обслуговування', '«Testova detal 2» (послуга) — 300 грн', '2026-06-25 09:50:44', 'Системний адміністратор'),
('28', '13', 'ремонт', 'Видалення запису витрати', 'Запис витрати #2 видалено', '2026-06-25 09:51:30', 'Системний адміністратор'),
('29', '13', 'ремонт', 'Нова витрата на обслуговування', '«Testova detal 2» (витратний матеріал) — 300 грн', '2026-06-25 09:52:49', 'Системний адміністратор'),
('30', '13', 'ремонт', 'Видалення запису витрати', 'Запис витрати #3 видалено', '2026-06-25 09:53:10', 'Системний адміністратор'),
('31', '13', 'ремонт', 'Нова витрата на обслуговування', '«Testova detal 2» (доставка) — 300 грн', '2026-06-25 09:55:29', 'Системний адміністратор'),
('32', '13', 'ремонт', 'Видалення запису витрати', 'Запис витрати #4 видалено', '2026-06-25 09:55:48', 'Системний адміністратор'),
('33', '13', 'ремонт', 'Нова витрата на обслуговування', '«Testova detal 2» (запчастина) — 300 грн', '2026-06-25 09:57:01', 'Системний адміністратор'),
('34', '13', 'ремонт', 'Нова витрата на обслуговування', '«Testova detal 3» (запчастина) — 50 грн', '2026-06-25 09:59:04', 'Системний адміністратор'),
('35', '13', 'ремонт', 'Видалення запису витрати', 'Запис витрати #5 видалено', '2026-06-25 10:07:38', 'Системний адміністратор'),
('36', '13', 'ремонт', 'Видалення запису витрати', 'Запис витрати #6 видалено', '2026-06-25 10:07:41', 'Системний адміністратор');

DROP TABLE IF EXISTS `network_history_and_logs`;
CREATE TABLE `network_history_and_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `device_id` int(11) NOT NULL,
  `admin_name` varchar(100) NOT NULL,
  `log_type` enum('зміна налаштувань','збій зв''язку','конфлікт','відновлення') NOT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `subnet_mask` varchar(45) DEFAULT NULL,
  `gateway` varchar(45) DEFAULT NULL,
  `session_status` enum('успішно','конфлікт','збій') NOT NULL DEFAULT 'успішно',
  `log_date` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `device_id` (`device_id`),
  CONSTRAINT `network_history_and_logs_ibfk_1` FOREIGN KEY (`device_id`) REFERENCES `devices` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=16 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `tickets`;
CREATE TABLE `tickets` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `device_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `description` text NOT NULL,
  `status` enum('нова','в роботі','виконана','відхилена') DEFAULT 'нова',
  `comment` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `device_id` (`device_id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `tickets_ibfk_1` FOREIGN KEY (`device_id`) REFERENCES `devices` (`id`) ON DELETE CASCADE,
  CONSTRAINT `tickets_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `system_alerts`;
CREATE TABLE `system_alerts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `device_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `device_id` (`device_id`),
  CONSTRAINT `system_alerts_ibfk_1` FOREIGN KEY (`device_id`) REFERENCES `devices` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `device_specifications`;
CREATE TABLE `device_specifications` (
  `device_id` int(11) NOT NULL,
  `cpu` varchar(100) DEFAULT NULL,
  `ram` varchar(100) DEFAULT NULL,
  `storage` varchar(150) DEFAULT NULL,
  `gpu` varchar(100) DEFAULT NULL,
  `os` varchar(100) DEFAULT NULL,
  `motherboard` varchar(100) DEFAULT NULL,
  `power_supply` varchar(100) DEFAULT NULL,
  PRIMARY KEY (`device_id`),
  CONSTRAINT `device_specifications_ibfk_1` FOREIGN KEY (`device_id`) REFERENCES `devices` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `repair_expenses`;
CREATE TABLE `repair_expenses` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `ticket_id` int(11) DEFAULT NULL,
  `device_id` int(11) NOT NULL,
  `expense_type` enum('запчастина','витратний матеріал','послуга','доставка','інше') NOT NULL DEFAULT 'запчастина',
  `part_name` varchar(150) NOT NULL,
  `description` text DEFAULT NULL,
  `quantity` int(11) NOT NULL DEFAULT 1,
  `unit_price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `total_price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `supplier` varchar(150) DEFAULT NULL,
  `receipt_number` varchar(100) DEFAULT NULL,
  `attachment_path` varchar(255) DEFAULT NULL,
  `warranty_months` int(11) NOT NULL DEFAULT 0,
  `expense_date` date NOT NULL,
  `payment_status` enum('оплачено','очікує оплати','заплановано') NOT NULL DEFAULT 'оплачено',
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `created_by` (`created_by`),
  KEY `idx_device_id` (`device_id`),
  KEY `idx_ticket_id` (`ticket_id`),
  KEY `idx_expense_date` (`expense_date`),
  CONSTRAINT `repair_expenses_ibfk_1` FOREIGN KEY (`ticket_id`) REFERENCES `tickets` (`id`) ON DELETE SET NULL,
  CONSTRAINT `repair_expenses_ibfk_2` FOREIGN KEY (`device_id`) REFERENCES `devices` (`id`) ON DELETE CASCADE,
  CONSTRAINT `repair_expenses_ibfk_3` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `repair_expenses` VALUES 
('1', NULL, '13', 'витратний матеріал', 'Cartridge HP 85A', NULL, '1', '450.00', '450.00', 'Rozetka', 'INV-100', NULL, '12', '2026-06-25', 'оплачено', '1', '2026-06-25 09:25:45');

SET FOREIGN_KEY_CHECKS = 1;
