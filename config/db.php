<?php
if (session_status() === PHP_SESSION_NONE) {
    if (!headers_sent()) {
        session_set_cookie_params([
            'path' => '/',
            'httponly' => true,
            'samesite' => 'Lax'
        ]);
    }
    session_start();
}

if (basename($_SERVER['SCRIPT_NAME']) !== 'login.php') {
    if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
        $is_action = (strpos($_SERVER['SCRIPT_NAME'], '/actions/') !== false);
        header('Location: ' . ($is_action ? '../login.php' : 'login.php'));
        exit;
    }
}

$host = '127.0.0.1';
$db   = 'it_dep_inventory';
$user = 'root';
$pass = '';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
     $pdo = new PDO($dsn, $user, $pass, $options);
     try {
          $pdo->exec("ALTER TABLE `devices` ADD COLUMN `is_pc` TINYINT(1) NOT NULL DEFAULT 0;");
     } catch (\PDOException $e) {
          // column already exists or other non-critical migration issue
     }
     try {
          $pdo->exec("ALTER TABLE `network_settings` ADD COLUMN `mac_address` VARCHAR(17) DEFAULT NULL;");
     } catch (\PDOException $e) {
          // column already exists
     }
     try {
          $pdo->exec("ALTER TABLE `devices` MODIFY COLUMN `status` ENUM('в роботі', 'очікує перевірки', 'на ремонті', 'в резерві', 'списано') NOT NULL DEFAULT 'в резерві';");
     } catch (\PDOException $e) {
          // migration failed or already exists
     }
     try {
          $pdo->exec("ALTER TABLE `users` ADD COLUMN `full_name` VARCHAR(100) DEFAULT NULL;");
     } catch (\PDOException $e) {
          // column already exists
     }
     try {
          $pdo->exec("ALTER TABLE `users` ADD COLUMN `role` ENUM('admin','teacher') NOT NULL DEFAULT 'teacher';");
     } catch (\PDOException $e) {
          // column already exists
     }

     $pdo->exec("CREATE TABLE IF NOT EXISTS `users` (
         `id` INT AUTO_INCREMENT PRIMARY KEY,
         `username` VARCHAR(50) NOT NULL UNIQUE,
         `password` VARCHAR(255) NOT NULL,
         `full_name` VARCHAR(100) DEFAULT NULL,
         `role` ENUM('admin','teacher') NOT NULL DEFAULT 'teacher'
     ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

     $stmt_count = $pdo->query("SELECT COUNT(*) FROM `users`");
     if ($stmt_count->fetchColumn() == 0) {
         $default_pass_hash = password_hash('admin123', PASSWORD_DEFAULT);
         $stmt_insert_default = $pdo->prepare("INSERT INTO `users` (username, password, full_name, role) VALUES ('admin', ?, 'Системний адміністратор', 'admin')");
         $stmt_insert_default->execute([$default_pass_hash]);
     } else {
         $pdo->exec("UPDATE `users` SET `role` = 'admin', `full_name` = 'Системний адміністратор' WHERE `username` = 'admin'");
     }

     $pdo->exec("CREATE TABLE IF NOT EXISTS `tickets` (
         `id` INT AUTO_INCREMENT PRIMARY KEY,
         `device_id` INT NOT NULL,
         `user_id` INT NOT NULL,
         `description` TEXT NOT NULL,
         `status` ENUM('нова', 'в роботі', 'виконана', 'відхилена') DEFAULT 'нова',
         `comment` TEXT DEFAULT NULL,
         `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
         FOREIGN KEY (`device_id`) REFERENCES `devices`(`id`) ON DELETE CASCADE,
         FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
     ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

     $pdo->exec("CREATE TABLE IF NOT EXISTS `system_alerts` (
         `id` INT AUTO_INCREMENT PRIMARY KEY,
         `device_id` INT NOT NULL,
         `title` VARCHAR(255) NOT NULL,
         `message` TEXT NOT NULL,
         `is_read` TINYINT(1) DEFAULT 0,
         `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
         FOREIGN KEY (`device_id`) REFERENCES `devices`(`id`) ON DELETE CASCADE
     ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

     $pdo->exec("CREATE TABLE IF NOT EXISTS `device_specifications` (
         `device_id` INT PRIMARY KEY,
         `cpu` VARCHAR(100) DEFAULT NULL,
         `ram` VARCHAR(100) DEFAULT NULL,
         `storage` VARCHAR(150) DEFAULT NULL,
         `gpu` VARCHAR(100) DEFAULT NULL,
         `os` VARCHAR(100) DEFAULT NULL,
         `motherboard` VARCHAR(100) DEFAULT NULL,
         `power_supply` VARCHAR(100) DEFAULT NULL,
         FOREIGN KEY (`device_id`) REFERENCES `devices`(`id`) ON DELETE CASCADE
     ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
} catch (\PDOException $e) {
     throw new \PDOException($e->getMessage(), (int)$e->getCode());
}
