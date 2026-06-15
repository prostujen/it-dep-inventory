-- Скрипт створення бази даних та таблиць для IT-інвентаризації
CREATE DATABASE IF NOT EXISTS `it_dep_inventory` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `it_dep_inventory`;

-- 1. Таблиця пристроїв
CREATE TABLE IF NOT EXISTS `devices` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `inventory_number` VARCHAR(50) NOT NULL UNIQUE,
    `name` VARCHAR(100) NOT NULL,
    `model` VARCHAR(100) NOT NULL,
    `serial_number` VARCHAR(100) NOT NULL UNIQUE,
    `status` ENUM('в роботі', 'на ремонті', 'в резерві', 'списано') NOT NULL DEFAULT 'в резерві',
    `location` VARCHAR(100) NOT NULL,
    `accepted_date` DATE NOT NULL,
    `responsible_person` VARCHAR(100) NOT NULL,
    INDEX `idx_status` (`status`),
    INDEX `idx_location` (`location`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Мережеві налаштування (One-to-One з devices)
CREATE TABLE IF NOT EXISTS `network_settings` (
    `device_id` INT PRIMARY KEY,
    `ip_address` VARCHAR(45) DEFAULT NULL,
    `subnet_mask` VARCHAR(45) DEFAULT NULL,
    `gateway` VARCHAR(45) DEFAULT NULL,
    `dns_server` VARCHAR(45) DEFAULT NULL,
    FOREIGN KEY (`device_id`) REFERENCES `devices`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Історія життєвого циклу та переміщень пристроїв
CREATE TABLE IF NOT EXISTS `device_history` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `device_id` INT NOT NULL,
    `event_type` ENUM('переміщення', 'зміна статусу', 'ремонт', 'створення', 'інше') NOT NULL,
    `old_value` VARCHAR(255) DEFAULT NULL,
    `new_value` VARCHAR(255) DEFAULT NULL,
    `event_date` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `responsible_person` VARCHAR(100) NOT NULL,
    FOREIGN KEY (`device_id`) REFERENCES `devices`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Логи мережевих змін, сесій та збоїв зв'язку
CREATE TABLE IF NOT EXISTS `network_history_and_logs` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `device_id` INT NOT NULL,
    `admin_name` VARCHAR(100) NOT NULL,
    `log_type` ENUM('зміна налаштувань', 'збій зв\'язку', 'конфлікт', 'відновлення') NOT NULL,
    `ip_address` VARCHAR(45) DEFAULT NULL,
    `subnet_mask` VARCHAR(45) DEFAULT NULL,
    `gateway` VARCHAR(45) DEFAULT NULL,
    `session_status` ENUM('успішно', 'конфлікт', 'збій') NOT NULL DEFAULT 'успішно',
    `log_date` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`device_id`) REFERENCES `devices`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Додавання початкових тестових даних

-- Очистка таблиць перед додаванням (для чистоти тестування)
SET FOREIGN_KEY_CHECKS = 0;
TRUNCATE TABLE `network_history_and_logs`;
TRUNCATE TABLE `device_history`;
TRUNCATE TABLE `network_settings`;
TRUNCATE TABLE `devices`;
SET FOREIGN_KEY_CHECKS = 1;

-- Вставка пристроїв
INSERT INTO `devices` (`id`, `inventory_number`, `name`, `model`, `serial_number`, `status`, `location`, `accepted_date`, `responsible_person`) VALUES
(1, 'INV-2026-0001', 'Комутатор доступу', 'Cisco Catalyst 2960', 'SN-CS2960-9921', 'в роботі', 'Аудиторія 204', '2025-09-10', 'доц. Петренко І.В.'),
(2, 'INV-2026-0002', 'Маршрутизатор кафедральний', 'MikroTik hEX gr3', 'SN-MTK-7712', 'в роботі', 'Лабораторія 305', '2025-10-15', 'проф. Сидоренко О.М.'),
(3, 'INV-2026-0003', 'Сервер БД', 'Dell PowerEdge R740', 'SN-DELL-8831', 'на ремонті', 'Серверна 101', '2024-03-22', 'ст. викл. Ковальчук А.П.'),
(4, 'INV-2026-0004', 'Точка доступу Wi-Fi', 'TP-Link EAP225', 'SN-TPL-4432', 'в резерві', 'Склад', '2026-01-18', 'доц. Петренко І.В.'),
(5, 'INV-2026-0005', 'Мережевий принтер', 'HP LaserJet Pro M402d', 'SN-HP-2211', 'списано', 'Аудиторія 204', '2021-05-12', 'проф. Сидоренко О.М.');

-- Вставка мережевих налаштувань
INSERT INTO `network_settings` (`device_id`, `ip_address`, `subnet_mask`, `gateway`, `dns_server`) VALUES
(1, '192.168.10.10', '255.255.255.0', '192.168.10.1', '8.8.8.8'),
(2, '192.168.1.1', '255.255.255.0', '192.168.1.254', '1.1.1.1'),
(3, '192.168.10.20', '255.255.255.0', '192.168.10.1', '8.8.4.4'),
(4, NULL, NULL, NULL, NULL),
(5, '192.168.10.55', '255.255.255.0', '192.168.10.1', '8.8.8.8');

-- Вставка історії пристроїв
INSERT INTO `device_history` (`device_id`, `event_type`, `old_value`, `new_value`, `responsible_person`) VALUES
(1, 'створення', NULL, 'Додано в систему інвентаризації', 'доц. Петренко І.В.'),
(2, 'створення', NULL, 'Додано в систему інвентаризації', 'проф. Сидоренко О.М.'),
(3, 'створення', NULL, 'Додано в систему інвентаризації', 'ст. викл. Ковальчук А.П.'),
(4, 'створення', NULL, 'Додано в систему інвентаризації', 'доц. Петренко І.В.'),
(5, 'створення', NULL, 'Додано в систему інвентаризації', 'проф. Сидоренко О.М.'),
(1, 'переміщення', 'Склад', 'Аудиторія 204', 'доц. Петренко І.В.'),
(3, 'зміна статусу', 'в роботі', 'на ремонті', 'ст. викл. Ковальчук А.П.'),
(5, 'зміна статусу', 'в роботі', 'списано', 'проф. Сидоренко О.М.');

-- Вставка мережевих логів
INSERT INTO `network_history_and_logs` (`device_id`, `admin_name`, `log_type`, `ip_address`, `subnet_mask`, `gateway`, `session_status`) VALUES
(1, 'admin_petya', 'зміна налаштувань', '192.168.10.10', '255.255.255.0', '192.168.10.1', 'успішно'),
(2, 'admin_petya', 'зміна налаштувань', '192.168.1.1', '255.255.255.0', '192.168.1.254', 'успішно'),
(3, 'sys_monitor', 'збій зв\'язку', '192.168.10.20', '255.255.255.0', '192.168.10.1', 'збій'),
(1, 'admin_vasya', 'конфлікт', '192.168.10.10', '255.255.255.0', '192.168.10.1', 'конфлікт');
