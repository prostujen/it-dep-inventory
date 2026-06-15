<?php
// actions/save_device.php
require_once '../config/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: ../index.php");
    exit;
}

// Отримання даних з форми
$device_id          = isset($_POST['device_id']) ? intval($_POST['device_id']) : 0;
$inventory_number   = trim($_POST['inventory_number']);
$serial_number      = trim($_POST['serial_number']);
$name               = trim($_POST['name']);
$model              = trim($_POST['model']);
$status             = trim($_POST['status']);
$location           = trim($_POST['location']);
$accepted_date      = trim($_POST['accepted_date']);
$responsible_person = trim($_POST['responsible_person']);

// Валідація основних полів
if (empty($inventory_number) || empty($serial_number) || empty($name) || empty($model) || empty($status) || empty($location) || empty($accepted_date) || empty($responsible_person)) {
    header("Location: ../index.php?error=empty_fields");
    exit;
}

try {
    if ($device_id > 0) {
        // --- РЕДАГУВАННЯ ПРИСТРОЮ ---
        
        // Отримуємо поточні значення для порівняння та запису в історію
        $stmt_old = $pdo->prepare("SELECT * FROM devices WHERE id = ?");
        $stmt_old->execute([$device_id]);
        $old_device = $stmt_old->fetch();
        
        if (!$old_device) {
            header("Location: ../index.php?error=not_found");
            exit;
        }

        // Перевірка унікальності інвентарного та серійного номерів серед інших пристроїв
        $stmt_check = $pdo->prepare("SELECT COUNT(*) FROM devices WHERE (inventory_number = ? OR serial_number = ?) AND id != ?");
        $stmt_check->execute([$inventory_number, $serial_number, $device_id]);
        if ($stmt_check->fetchColumn() > 0) {
            header("Location: ../index.php?error=duplicate_numbers");
            exit;
        }

        // Починаємо транзакцію
        $pdo->beginTransaction();

        // Оновлюємо пристрій
        $stmt_update = $pdo->prepare("UPDATE devices SET 
            inventory_number = ?, 
            serial_number = ?, 
            name = ?, 
            model = ?, 
            status = ?, 
            location = ?, 
            accepted_date = ?, 
            responsible_person = ? 
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
            $device_id
        ]);

        // Запис в історію змін
        $admin_name = 'Адміністратор';
        
        // 1. Зміна статусу
        if ($old_device['status'] !== $status) {
            $stmt_hist = $pdo->prepare("INSERT INTO device_history (device_id, event_type, old_value, new_value, responsible_person) VALUES (?, 'зміна статусу', ?, ?, ?)");
            $stmt_hist->execute([$device_id, $old_device['status'], $status, $responsible_person]);
        }
        // 2. Переміщення (зміна локації)
        if ($old_device['location'] !== $location) {
            $stmt_hist = $pdo->prepare("INSERT INTO device_history (device_id, event_type, old_value, new_value, responsible_person) VALUES (?, 'переміщення', ?, ?, ?)");
            $stmt_hist->execute([$device_id, $old_device['location'], $location, $responsible_person]);
        }
        // 3. Зміна відповідальної особи
        if ($old_device['responsible_person'] !== $responsible_person) {
            $stmt_hist = $pdo->prepare("INSERT INTO device_history (device_id, event_type, old_value, new_value, responsible_person) VALUES (?, 'інше', ?, ?, ?)");
            $stmt_hist->execute([$device_id, "Стара відп. особа: " . $old_device['responsible_person'], "Нова відп. особа: " . $responsible_person, $responsible_person]);
        }

        $pdo->commit();
        header("Location: ../index.php?success=updated");
        exit;

    } else {
        // --- СТВОРЕННЯ НОВОГО ПРИСТРОЮ ---

        // Перевірка унікальності інвентарного та серійного номерів
        $stmt_check = $pdo->prepare("SELECT COUNT(*) FROM devices WHERE inventory_number = ? OR serial_number = ?");
        $stmt_check->execute([$inventory_number, $serial_number]);
        if ($stmt_check->fetchColumn() > 0) {
            header("Location: ../index.php?error=duplicate_numbers");
            exit;
        }

        // Перевірка мережевих налаштувань (якщо введені)
        $ip_address  = isset($_POST['ip_address']) ? trim($_POST['ip_address']) : '';
        $subnet_mask = isset($_POST['subnet_mask']) ? trim($_POST['subnet_mask']) : '';
        $gateway     = isset($_POST['gateway']) ? trim($_POST['gateway']) : '';
        $dns_server  = isset($_POST['dns_server']) ? trim($_POST['dns_server']) : '';

        if (!empty($ip_address)) {
            // Валідація IP-адреси
            if (!filter_var($ip_address, FILTER_VALIDATE_IP)) {
                header("Location: ../index.php?error=invalid_ip");
                exit;
            }
            
            // Валідація маски, шлюзу, DNS (якщо вони не порожні)
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

            // Перевірка на конфлікт IP-адрес серед активних пристроїв
            $stmt_ip_check = $pdo->prepare("
                SELECT d.name, d.inventory_number 
                FROM network_settings n
                JOIN devices d ON n.device_id = d.id
                WHERE n.ip_address = ? AND d.status != 'списано'
            ");
            $stmt_ip_check->execute([$ip_address]);
            $conflicting_device = $stmt_ip_check->fetch();

            if ($conflicting_device) {
                // Якщо є конфлікт, перенаправляємо з помилкою конфлікту
                header("Location: ../index.php?error=ip_conflict&conflicting_inv=" . urlencode($conflicting_device['inventory_number']));
                exit;
            }
        }

        // Починаємо транзакцію
        $pdo->beginTransaction();

        // 1. Вставка в devices
        $stmt_insert = $pdo->prepare("INSERT INTO devices (inventory_number, serial_number, name, model, status, location, accepted_date, responsible_person) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt_insert->execute([
            $inventory_number,
            $serial_number,
            $name,
            $model,
            $status,
            $location,
            $accepted_date,
            $responsible_person
        ]);
        
        $new_device_id = $pdo->lastInsertId();

        // 2. Вставка в network_settings
        $stmt_net = $pdo->prepare("INSERT INTO network_settings (device_id, ip_address, subnet_mask, gateway, dns_server) VALUES (?, ?, ?, ?, ?)");
        $net_ip      = !empty($ip_address) ? $ip_address : null;
        $net_subnet  = !empty($subnet_mask) ? $subnet_mask : null;
        $net_gateway = !empty($gateway) ? $gateway : null;
        $net_dns     = !empty($dns_server) ? $dns_server : null;
        
        $stmt_net->execute([$new_device_id, $net_ip, $net_subnet, $net_gateway, $net_dns]);

        // 3. Запис в device_history
        $stmt_hist = $pdo->prepare("INSERT INTO device_history (device_id, event_type, old_value, new_value, responsible_person) VALUES (?, 'створення', NULL, 'Пристрій додано в систему', ?)");
        $stmt_hist->execute([$new_device_id, $responsible_person]);

        // 4. Запис в network_history_and_logs (якщо вказано IP)
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
?>
