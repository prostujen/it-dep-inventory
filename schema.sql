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
