<?php
require_once '../config/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: ../index.php");
    exit;
}

$device_id   = isset($_POST['device_id']) ? intval($_POST['device_id']) : 0;
$ip_address  = trim($_POST['ip_address']);
$subnet_mask = trim($_POST['subnet_mask']);
$gateway     = trim($_POST['gateway']);
$dns_server  = trim($_POST['dns_server']);

if ($device_id <= 0) {
    header("Location: ../index.php?error=invalid_id");
    exit;
}

try {
    $stmt_dev = $pdo->prepare("SELECT * FROM devices WHERE id = ?");
    $stmt_dev->execute([$device_id]);
    $device = $stmt_dev->fetch();
    
    if (!$device) {
        header("Location: ../index.php?error=not_found");
        exit;
    }
} catch (\PDOException $e) {
    header("Location: ../device_view.php?id=$device_id&error=db_error&msg=" . urlencode($e->getMessage()));
    exit;
}

$is_empty = (empty($ip_address) && empty($subnet_mask) && empty($gateway) && empty($dns_server));

if (!$is_empty) {
    if (!empty($ip_address) && !filter_var($ip_address, FILTER_VALIDATE_IP)) {
        header("Location: ../device_view.php?id=$device_id&error=invalid_ip");
        exit;
    }
    if (!empty($subnet_mask) && !filter_var($subnet_mask, FILTER_VALIDATE_IP)) {
        header("Location: ../device_view.php?id=$device_id&error=invalid_subnet");
        exit;
    }
    if (!empty($gateway) && !filter_var($gateway, FILTER_VALIDATE_IP)) {
        header("Location: ../device_view.php?id=$device_id&error=invalid_gateway");
        exit;
    }
    if (!empty($dns_server) && !filter_var($dns_server, FILTER_VALIDATE_IP)) {
        header("Location: ../device_view.php?id=$device_id&error=invalid_dns");
        exit;
    }

    if (!empty($ip_address)) {
        try {
            $stmt_conflict = $pdo->prepare("
                SELECT d.id, d.name, d.inventory_number 
                FROM network_settings n
                JOIN devices d ON n.device_id = d.id
                WHERE n.ip_address = ? AND d.id != ? AND d.status != 'списано'
            ");
            $stmt_conflict->execute([$ip_address, $device_id]);
            $conflicting_device = $stmt_conflict->fetch();

            if ($conflicting_device) {
                $stmt_log_conflict = $pdo->prepare("
                    INSERT INTO network_history_and_logs (device_id, admin_name, log_type, ip_address, subnet_mask, gateway, session_status) 
                    VALUES (?, 'Адміністратор', 'конфлікт', ?, ?, ?, 'конфлікт')
                ");
                $stmt_log_conflict->execute([$device_id, $ip_address, $subnet_mask, $gateway]);

                header("Location: ../device_view.php?id=$device_id&error=ip_conflict&conflicting_inv=" . urlencode($conflicting_device['inventory_number']));
                exit;
            }
        } catch (\PDOException $e) {
            header("Location: ../device_view.php?id=$device_id&error=db_error&msg=" . urlencode($e->getMessage()));
            exit;
        }
    }
}

try {
    $pdo->beginTransaction();

    $stmt_check = $pdo->prepare("SELECT COUNT(*) FROM network_settings WHERE device_id = ?");
    $stmt_check->execute([$device_id]);
    $exists = $stmt_check->fetchColumn() > 0;

    $net_ip      = !empty($ip_address) ? $ip_address : null;
    $net_subnet  = !empty($subnet_mask) ? $subnet_mask : null;
    $net_gateway = !empty($gateway) ? $gateway : null;
    $net_dns     = !empty($dns_server) ? $dns_server : null;

    if ($exists) {
        $stmt_net_update = $pdo->prepare("
            UPDATE network_settings 
            SET ip_address = ?, subnet_mask = ?, gateway = ?, dns_server = ? 
            WHERE device_id = ?
        ");
        $stmt_net_update->execute([$net_ip, $net_subnet, $net_gateway, $net_dns, $device_id]);
    } else {
        $stmt_net_insert = $pdo->prepare("
            INSERT INTO network_settings (device_id, ip_address, subnet_mask, gateway, dns_server) 
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt_net_insert->execute([$device_id, $net_ip, $net_subnet, $net_gateway, $net_dns]);
    }

    $stmt_log = $pdo->prepare("
        INSERT INTO network_history_and_logs (device_id, admin_name, log_type, ip_address, subnet_mask, gateway, session_status) 
        VALUES (?, 'Адміністратор', 'зміна налаштувань', ?, ?, ?, 'успішно')
    ");
    $stmt_log->execute([$device_id, $net_ip, $net_subnet, $net_gateway]);

    $pdo->commit();
    header("Location: ../device_view.php?id=$device_id&success=net_updated");
    exit;

} catch (\PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    header("Location: ../device_view.php?id=$device_id&error=db_error&msg=" . urlencode($e->getMessage()));
    exit;
}
