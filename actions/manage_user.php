<?php
require_once '../config/db.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || !isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: ../index.php?error=access_denied");
    exit;
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';

if ($action === 'add' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = trim($_POST['full_name'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $role = $_POST['role'] ?? 'teacher';

    if (empty($full_name) || empty($username) || empty($password)) {
        header("Location: ../settings.php?tab=users&error=empty_fields");
        exit;
    }

    try {
        // Check if username already exists
        $stmt_check = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = ?");
        $stmt_check->execute([$username]);
        if ($stmt_check->fetchColumn() > 0) {
            header("Location: ../settings.php?tab=users&error=username_exists");
            exit;
        }

        // Hash password and insert user
        $pass_hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt_insert = $pdo->prepare("INSERT INTO users (username, password, full_name, role) VALUES (?, ?, ?, ?)");
        $stmt_insert->execute([$username, $pass_hash, $full_name, $role]);

        header("Location: ../settings.php?tab=users&success=user_added");
        exit;
    } catch (\PDOException $e) {
        header("Location: ../settings.php?tab=users&error=db_error&msg=" . urlencode($e->getMessage()));
        exit;
    }
} elseif ($action === 'delete' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    $id = intval($_GET['id'] ?? 0);

    if ($id === 0) {
        header("Location: ../settings.php?tab=users&error=user_not_found");
        exit;
    }

    if ($id === intval($_SESSION['user_id'])) {
        header("Location: ../settings.php?tab=users&error=cannot_delete_self");
        exit;
    }

    try {
        // Delete user from database
        $stmt_delete = $pdo->prepare("DELETE FROM users WHERE id = ?");
        $stmt_delete->execute([$id]);

        header("Location: ../settings.php?tab=users&success=user_deleted");
        exit;
    } catch (\PDOException $e) {
        header("Location: ../settings.php?tab=users&error=db_error&msg=" . urlencode($e->getMessage()));
        exit;
    }
}

header("Location: ../settings.php?tab=users");
exit;
