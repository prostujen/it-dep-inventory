<?php
require_once '../config/db.php';

if (!$is_admin) {
    header('Location: ../index.php?error=access_denied');
    exit;
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$device_id = isset($_GET['device_id']) ? (int)$_GET['device_id'] : 0;

if ($id <= 0) {
    header('Location: ../index.php');
    exit;
}

// Fetch expense to get attachment path
$stmt = $pdo->prepare("SELECT `id`, `device_id`, `attachment_path` FROM `repair_expenses` WHERE `id` = ?");
$stmt->execute([$id]);
$expense = $stmt->fetch();

if (!$expense) {
    header('Location: ../device_view.php?id=' . $device_id . '&tab=expenses&error=not_found');
    exit;
}

$device_id = $expense['device_id'];

// Delete attachment file if exists
if (!empty($expense['attachment_path'])) {
    $file_path = __DIR__ . '/../' . $expense['attachment_path'];
    if (file_exists($file_path)) {
        unlink($file_path);
    }
}

try {
    $stmt_del = $pdo->prepare("DELETE FROM `repair_expenses` WHERE `id` = ?");
    $stmt_del->execute([$id]);

    // Log the deletion
    $user_name = $_SESSION['user_fullname'] ?? 'Адмін';
    $stmt_log = $pdo->prepare("
        INSERT INTO `device_history` (`device_id`, `event_type`, `old_value`, `new_value`, `responsible_person`)
        VALUES (?, 'ремонт', 'Видалення запису витрати', ?, ?)
    ");
    $stmt_log->execute([$device_id, 'Запис витрати #' . $id . ' видалено', $user_name]);

    $redirect_to = isset($_GET['redirect_to']) ? trim($_GET['redirect_to']) : '';
    if ($redirect_to === 'repair_costs.php') {
        header('Location: ../repair_costs.php?success=expense_deleted');
    } else {
        header('Location: ../device_view.php?id=' . $device_id . '&tab=expenses&success=expense_deleted');
    }
    exit;

} catch (\PDOException $e) {
    $redirect_to = isset($_GET['redirect_to']) ? trim($_GET['redirect_to']) : '';
    if ($redirect_to === 'repair_costs.php') {
        header('Location: ../repair_costs.php?error=db_error&msg=' . urlencode($e->getMessage()));
    } else {
        header('Location: ../device_view.php?id=' . $device_id . '&tab=expenses&error=db_error&msg=' . urlencode($e->getMessage()));
    }
    exit;
}
