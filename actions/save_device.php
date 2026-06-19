<?php
require_once '../config/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: ../index.php");
    exit;
}

$device_id          = isset($_POST['device_id']) ? intval($_POST['device_id']) : 0;
$is_pc              = isset($_POST['is_pc']) ? intval($_POST['is_pc']) : 0;
$inventory_number   = isset($_POST['inventory_number']) && trim($_POST['inventory_number']) !== '' ? trim($_POST['inventory_number']) : null;
$serial_number      = isset($_POST['serial_number']) && trim($_POST['serial_number']) !== '' ? trim($_POST['serial_number']) : null;
$name               = trim($_POST['name'] ?? '');
$model              = isset($_POST['model']) && trim($_POST['model']) !== '' ? trim($_POST['model']) : null;
$status             = trim($_POST['status'] ?? '');
$location           = isset($_POST['location']) && trim($_POST['location']) !== '' ? trim($_POST['location']) : null;
$accepted_date      = isset($_POST['accepted_date']) && trim($_POST['accepted_date']) !== '' ? trim($_POST['accepted_date']) : null;
$responsible_person = trim($_POST['responsible_person'] ?? '');

if (empty($name) || empty($status) || empty($responsible_person)) {
    header("Location: ../index.php?error=empty_fields");
    exit;
}

try {
    if ($device_id > 0) {
        $stmt_old = $pdo->prepare("SELECT * FROM devices WHERE id = ?");
        $stmt_old->execute([$device_id]);
        $old_device = $stmt_old->fetch();
        
        if (!$old_device) {
            header("Location: ../index.php?error=not_found");
            exit;
        }

        $check_sql = "1=0";
        $check_params = [];
        if ($inventory_number !== null) {
            $check_sql .= " OR inventory_number = ?";
            $check_params[] = $inventory_number;
        }
        if ($serial_number !== null) {
            $check_sql .= " OR serial_number = ?";
            $check_params[] = $serial_number;
        }
        if (!empty($check_params)) {
            $stmt_check = $pdo->prepare("SELECT COUNT(*) FROM devices WHERE ($check_sql) AND id != ?");
            $check_params[] = $device_id;
            $stmt_check->execute($check_params);
            if ($stmt_check->fetchColumn() > 0) {
                header("Location: ../index.php?error=duplicate_numbers");
                exit;
            }
        }

        $pdo->beginTransaction();

        $stmt_update = $pdo->prepare("UPDATE devices SET 
            inventory_number = ?, 
            serial_number = ?, 
            name = ?, 
            model = ?, 
            status = ?, 
            location = ?, 
            accepted_date = ?, 
            responsible_person = ?,
            is_pc = ? 
            WHERE id = ?");
        $stmt_update->execute([
            $inventory_number,
            $serial_number,
            $name,
            $model,
            $status,
            $location,
            $accepted_date,
            $responsible_person,
            $is_pc,
            $device_id
        ]);

        $admin_name = 'Адміністратор';
        
        if ($old_device['status'] !== $status) {
            $stmt_hist = $pdo->prepare("INSERT INTO device_history (device_id, event_type, old_value, new_value, responsible_person) VALUES (?, 'зміна статусу', ?, ?, ?)");
            $stmt_hist->execute([$device_id, $old_device['status'], $status, $responsible_person]);
        }
        if ($old_device['location'] !== $location) {
            $stmt_hist = $pdo->prepare("INSERT INTO device_history (device_id, event_type, old_value, new_value, responsible_person) VALUES (?, 'переміщення', ?, ?, ?)");
            $stmt_hist->execute([$device_id, $old_device['location'], $location, $responsible_person]);
        }
        if ($old_device['responsible_person'] !== $responsible_person) {
            $stmt_hist = $pdo->prepare("INSERT INTO device_history (device_id, event_type, old_value, new_value, responsible_person) VALUES (?, 'інше', ?, ?, ?)");
            $stmt_hist->execute([$device_id, "Стара відп. особа: " . $old_device['responsible_person'], "Нова відп. особа: " . $responsible_person, $responsible_person]);
        }
        if ($old_device['is_pc'] != $is_pc) {
            $old_val = $old_device['is_pc'] ? 'Комп\'ютер' : 'Інше обладнання';
            $new_val = $is_pc ? 'Комп\'ютер' : 'Інше обладнання';
            $stmt_hist = $pdo->prepare("INSERT INTO device_history (device_id, event_type, old_value, new_value, responsible_person) VALUES (?, 'інше', ?, ?, ?)");
            $stmt_hist->execute([$device_id, "Тип: " . $old_val, "Тип: " . $new_val, $responsible_person]);
        }

        $pdo->commit();
        $redirect_to = isset($_POST['redirect_to']) ? $_POST['redirect_to'] : '../index.php';
        if (strpos($redirect_to, 'device_view.php') !== false) {
            header("Location: " . $redirect_to . (strpos($redirect_to, '?') !== false ? '&' : '?') . "success=updated");
        } else {
            header("Location: ../index.php?success=updated");
        }
        exit;

    } else {
        $check_sql = "1=0";
        $check_params = [];
        if ($inventory_number !== null) {
            $check_sql .= " OR inventory_number = ?";
            $check_params[] = $inventory_number;
        }
        if ($serial_number !== null) {
            $check_sql .= " OR serial_number = ?";
            $check_params[] = $serial_number;
        }
        if (!empty($check_params)) {
            $stmt_check = $pdo->prepare("SELECT COUNT(*) FROM devices WHERE $check_sql");
            $stmt_check->execute($check_params);
            if ($stmt_check->fetchColumn() > 0) {
                header("Location: ../index.php?error=duplicate_numbers");
                exit;
            }
        }

        $ip_address  = isset($_POST['ip_address']) ? trim($_POST['ip_address']) : '';
        $subnet_mask = isset($_POST['subnet_mask']) ? trim($_POST['subnet_mask']) : '';
        $gateway     = isset($_POST['gateway']) ? trim($_POST['gateway']) : '';
        $dns_server  = isset($_POST['dns_server']) ? trim($_POST['dns_server']) : '';
        $mac_address = isset($_POST['mac_address']) ? trim($_POST['mac_address']) : '';

        if (!empty($ip_address)) {
            if (!filter_var($ip_address, FILTER_VALIDATE_IP)) {
                header("Location: ../index.php?error=invalid_ip");
                exit;
            }
            
            if (!empty($subnet_mask) && !filter_var($subnet_mask, FILTER_VALIDATE_IP)) {
                header("Location: ../index.php?error=invalid_subnet");
                exit;
            }
            if (!empty($gateway) && !filter_var($gateway, FILTER_VALIDATE_IP)) {
                header("Location: ../index.php?error=invalid_gateway");
                exit;
            }
            if (!empty($dns_server) && !filter_var($dns_server, FILTER_VALIDATE_IP)) {
                header("Location: ../index.php?error=invalid_dns");
                exit;
            }

            $stmt_ip_check = $pdo->prepare("
                SELECT d.name, d.inventory_number 
                FROM network_settings n
                JOIN devices d ON n.device_id = d.id
                WHERE n.ip_address = ? AND d.status != 'списано'
            ");
            $stmt_ip_check->execute([$ip_address]);
            $conflicting_device = $stmt_ip_check->fetch();

            if ($conflicting_device) {
                header("Location: ../index.php?error=ip_conflict&conflicting_inv=" . urlencode($conflicting_device['inventory_number']));
                exit;
            }
        }

        if (!empty($mac_address)) {
            $stmt_mac_check = $pdo->prepare("
                SELECT d.name, d.inventory_number 
                FROM network_settings n
                JOIN devices d ON n.device_id = d.id
                WHERE n.mac_address = ? AND d.status != 'списано'
            ");
            $stmt_mac_check->execute([$mac_address]);
            $conflicting_mac = $stmt_mac_check->fetch();

            if ($conflicting_mac) {
                header("Location: ../index.php?error=mac_conflict&conflicting_inv=" . urlencode($conflicting_mac['inventory_number']));
                exit;
            }
        }

        $pdo->beginTransaction();

        $stmt_insert = $pdo->prepare("INSERT INTO devices (inventory_number, serial_number, name, model, status, location, accepted_date, responsible_person, is_pc) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt_insert->execute([
            $inventory_number,
            $serial_number,
            $name,
            $model,
            $status,
            $location,
            $accepted_date,
            $responsible_person,
            $is_pc
        ]);
        
        $new_device_id = $pdo->lastInsertId();

        $stmt_net = $pdo->prepare("INSERT INTO network_settings (device_id, ip_address, subnet_mask, gateway, dns_server, mac_address) VALUES (?, ?, ?, ?, ?, ?)");
        $net_ip      = !empty($ip_address) ? $ip_address : null;
        $net_subnet  = !empty($subnet_mask) ? $subnet_mask : null;
        $net_gateway = !empty($gateway) ? $gateway : null;
        $net_dns     = !empty($dns_server) ? $dns_server : null;
        $net_mac     = !empty($mac_address) ? $mac_address : null;
        
        $stmt_net->execute([$new_device_id, $net_ip, $net_subnet, $net_gateway, $net_dns, $net_mac]);

        $stmt_hist = $pdo->prepare("INSERT INTO device_history (device_id, event_type, old_value, new_value, responsible_person) VALUES (?, 'створення', NULL, 'Пристрій додано в систему', ?)");
        $stmt_hist->execute([$new_device_id, $responsible_person]);

        if (!empty($ip_address)) {
            $stmt_net_log = $pdo->prepare("INSERT INTO network_history_and_logs (device_id, admin_name, log_type, ip_address, subnet_mask, gateway, session_status) VALUES (?, 'Адмін', 'зміна налаштувань', ?, ?, ?, 'успішно')");
            $stmt_net_log->execute([$new_device_id, $net_ip, $net_subnet, $net_gateway]);
        }

        $pdo->commit();
        header("Location: ../index.php?success=created");
        exit;
    }

} catch (\PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    header("Location: ../index.php?error=db_error&msg=" . urlencode($e->getMessage()));
    exit;
}
