<?php
// actions/check_ping.php
header('Content-Type: application/json; charset=utf-8');
require_once '../config/db.php';

$device_id = isset($_GET['device_id']) ? intval($_GET['device_id']) : 0;

if ($device_id <= 0) {
    echo json_encode(['success' => false, 'error' => 'Некоректний ID пристрою']);
    exit;
}

try {
    // Отримуємо пристрій та його IP-адресу
    $stmt = $pdo->prepare("
        SELECT d.*, n.ip_address, n.subnet_mask, n.gateway 
        FROM devices d
        LEFT JOIN network_settings n ON d.id = n.device_id
        WHERE d.id = ?
    ");
    $stmt->execute([$device_id]);
    $device = $stmt->fetch();

    if (!$device) {
        echo json_encode(['success' => false, 'error' => 'Пристрій не знайдено']);
        exit;
    }

    if (empty($device['ip_address'])) {
        echo json_encode(['success' => false, 'error' => 'У пристрою відсутня IP-адреса']);
        exit;
    }

    // Симулюємо затримку мережі від 0.2 до 0.8 секунд для реалістичності UI
    usleep(rand(200000, 800000));

    // Логіка перевірки:
    // 1. Якщо пристрій "списано", він завжди офлайн.
    // 2. Якщо пристрій "на ремонті", він офлайн з ймовірністю 80%.
    // 3. Якщо пристрій "в роботі" або "в резерві", він онлайн з ймовірністю 90%.
    
    $status = 'online';
    $latency = rand(2, 45); // мілісекунди
    $message = 'Успішний пінг';

    if ($device['status'] === 'списано') {
        $status = 'offline';
        $message = 'Пристрій списаний та відключений від мережі';
    } elseif ($device['status'] === 'на ремонті') {
        if (rand(1, 10) <= 8) { // 80% ймовірність відключення
            $status = 'offline';
            $message = 'Немає відповіді (пристрій знаходиться на фізичному ремонті)';
        }
    } else {
        // Стандартний моніторинг активних пристроїв: 10% ймовірність збою зв'язку
        if (rand(1, 10) === 10) {
            $status = 'offline';
            $message = 'Перевищено ліміт часу очікування запиту (Timeout)';
        }
    }

    // Якщо зафіксовано збій зв'язку (для активного пристрою або на ремонті),
    // автоматично логуємо цю подію в таблицю network_history_and_logs
    if ($status === 'offline') {
        $stmt_log = $pdo->prepare("
            INSERT INTO network_history_and_logs (device_id, admin_name, log_type, ip_address, subnet_mask, gateway, session_status) 
            VALUES (?, 'sys_monitor', 'збій зв\'язку', ?, ?, ?, 'збій')
        ");
        $stmt_log->execute([
            $device_id,
            $device['ip_address'],
            $device['subnet_mask'],
            $device['gateway']
        ]);
    }

    echo json_encode([
        'success' => true,
        'status'  => $status,
        'latency' => ($status === 'online') ? $latency : null,
        'message' => $message
    ]);
    exit;

} catch (\PDOException $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    exit;
}
?>
