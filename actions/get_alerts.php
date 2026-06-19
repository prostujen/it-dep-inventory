<?php
header('Content-Type: application/json; charset=utf-8');
require_once '../config/db.php';

$action = isset($_GET['action']) ? trim($_GET['action']) : 'list';

try {
    if ($action === 'read_all') {
        $stmt = $pdo->query("UPDATE system_alerts SET is_read = 1 WHERE is_read = 0");
        echo json_encode(['success' => true]);
        exit;
    }

    if ($action === 'read') {
        $id = isset($_GET['id']) ? intval($_GET['id']) : 0;
        if ($id > 0) {
            $stmt = $pdo->prepare("UPDATE system_alerts SET is_read = 1 WHERE id = ?");
            $stmt->execute([$id]);
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Некоректний ID']);
        }
        exit;
    }

    $stmt_count = $pdo->query("SELECT COUNT(*) FROM system_alerts WHERE is_read = 0");
    $unread_count = $stmt_count->fetchColumn();

    $stmt_list = $pdo->query("
        SELECT a.*, d.name AS device_name, d.inventory_number 
        FROM system_alerts a
        JOIN devices d ON a.device_id = d.id
        WHERE a.is_read = 0
        ORDER BY a.created_at DESC
        LIMIT 5
    ");
    $alerts = $stmt_list->fetchAll();

    echo json_encode([
        'success' => true,
        'unread_count' => $unread_count,
        'alerts' => $alerts
    ]);
    exit;

} catch (\PDOException $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    exit;
}
