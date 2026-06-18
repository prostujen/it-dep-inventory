<?php
require_once '../config/db.php';

$log_id    = isset($_GET['log_id']) ? intval($_GET['log_id']) : 0;
$device_id = isset($_GET['device_id']) ? intval($_GET['device_id']) : 0;

if ($log_id <= 0 || $device_id <= 0) {
    header("Location: ../index.php?error=invalid_id");
    exit;
}

try {
    $stmt_log = $pdo->prepare("SELECT * FROM network_history_and_logs WHERE id = ? AND device_id = ?");
    $stmt_log->execute([$log_id, $device_id]);
    $log_entry = $stmt_log->fetch();

    if (!$log_entry) {
        header("Location: ../device_view.php?id=$device_id&error=log_not_found");
        exit;
    }

    if (!empty($log_entry['ip_address'])) {
        $stmt_conflict = $pdo->prepare("
            SELECT d.id, d.name, d.inventory_number 
            FROM network_settings n
            JOIN devices d ON n.device_id = d.id
            WHERE n.ip_address = ? AND d.id != ? AND d.status != 'списано'
        ");
        $stmt_conflict->execute([$log_entry['ip_address'], $device_id]);
        $conflicting_device = $stmt_conflict->fetch();

        if ($conflicting_device) {
            $stmt_log_conflict = $pdo->prepare("
                INSERT INTO network_history_and_logs (device_id, admin_name, log_type, ip_address, subnet_mask, gateway, session_status) 
                VALUES (?, 'Адміністратор', 'конфлікт', ?, ?, ?, 'конфлікт')
            ");
            $stmt_log_conflict->execute([$device_id, $log_entry['ip_address'], $log_entry['subnet_mask'], $log_entry['gateway']]);

            header("Location: ../device_view.php?id=$device_id&error=ip_conflict&conflicting_inv=" . urlencode($conflicting_device['inventory_number']));
            exit;
        }
    }

    $pdo->beginTransaction();

    $stmt_update = $pdo->prepare("
        UPDATE network_settings 
        SET ip_address = ?, subnet_mask = ?, gateway = ?
        WHERE device_id = ?
    ");
    $stmt_update->execute([
        $log_entry['ip_address'],
        $log_entry['subnet_mask'],
        $log_entry['gateway'],
        $device_id
    ]);

    $stmt_rollback_log = $pdo->prepare("
        INSERT INTO network_history_and_logs (device_id, admin_name, log_type, ip_address, subnet_mask, gateway, session_status) 
        VALUES (?, 'Адміністратор', 'відновлення', ?, ?, ?, 'успішно')
    ");
    $stmt_rollback_log->execute([
        $device_id,
        $log_entry['ip_address'],
        $log_entry['subnet_mask'],
        $log_entry['gateway']
    ]);

    $pdo->commit();
    header("Location: ../device_view.php?id=$device_id&success=rolled_back");
    exit;

} catch (\PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    header("Location: ../device_view.php?id=$device_id&error=db_error&msg=" . urlencode($e->getMessage()));
    exit;
}
?>
