<?php
require_once '../config/db.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || !isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: ../index.php?error=access_denied");
    exit;
}

$device_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($device_id <= 0) {
    header("Location: ../index.php?error=invalid_id");
    exit;
}

try {
    $stmt_check = $pdo->prepare("SELECT COUNT(*) FROM devices WHERE id = ?");
    $stmt_check->execute([$device_id]);
    if ($stmt_check->fetchColumn() == 0) {
        header("Location: ../index.php?error=not_found");
        exit;
    }

    $stmt_delete = $pdo->prepare("DELETE FROM devices WHERE id = ?");
    $stmt_delete->execute([$device_id]);

    header("Location: ../index.php?success=deleted");
    exit;
} catch (\PDOException $e) {
    header("Location: ../index.php?error=db_error&msg=" . urlencode($e->getMessage()));
    exit;
}
