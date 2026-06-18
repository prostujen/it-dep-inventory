<?php
if (session_status() === PHP_SESSION_NONE) {
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
     $pdo->exec("CREATE TABLE IF NOT EXISTS `users` (
         `id` INT AUTO_INCREMENT PRIMARY KEY,
         `username` VARCHAR(50) NOT NULL UNIQUE,
         `password` VARCHAR(255) NOT NULL
     ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

     $stmt_count = $pdo->query("SELECT COUNT(*) FROM `users`");
     if ($stmt_count->fetchColumn() == 0) {
         $default_pass_hash = password_hash('admin123', PASSWORD_DEFAULT);
         $stmt_insert_default = $pdo->prepare("INSERT INTO `users` (username, password) VALUES ('admin', ?)");
         $stmt_insert_default->execute([$default_pass_hash]);
     }
} catch (\PDOException $e) {
     throw new \PDOException($e->getMessage(), (int)$e->getCode());
}
?>
