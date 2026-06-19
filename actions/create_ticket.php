<?php
require_once '../config/db.php';

// Check if user is logged in
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: ../login.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: ../index.php");
    exit;
}

$device_id = intval($_POST['device_id'] ?? 0);
$description = trim($_POST['description'] ?? '');
$user_id = intval($_SESSION['user_id'] ?? 0);
$user_fullname = $_SESSION['user_fullname'] ?? 'Викладач';

if ($device_id <= 0 || empty($description)) {
    header("Location: ../device_view.php?id=$device_id&error=empty_fields");
    exit;
}

try {
    // Get device info
    $stmt_dev = $pdo->prepare("SELECT name, status FROM devices WHERE id = ?");
    $stmt_dev->execute([$device_id]);
    $device = $stmt_dev->fetch();

    if (!$device) {
        header("Location: ../index.php?error=not_found");
        exit;
    }

    $old_status = $device['status'];
    $device_name = $device['name'];

    // Start transaction
    $pdo->beginTransaction();

    // 1. Create ticket
    $stmt_ticket = $pdo->prepare("INSERT INTO tickets (device_id, user_id, description, status) VALUES (?, ?, ?, 'нова')");
    $stmt_ticket->execute([$device_id, $user_id, $description]);

    // 2. Update device status to 'очікує перевірки'
    $stmt_update_dev = $pdo->prepare("UPDATE devices SET status = 'очікує перевірки' WHERE id = ?");
    $stmt_update_dev->execute([$device_id]);

    // 3. Write event to history
    $stmt_hist = $pdo->prepare("INSERT INTO device_history (device_id, event_type, old_value, new_value, responsible_person) VALUES (?, 'зміна статусу', ?, 'очікує перевірки', ?)");
    $stmt_hist->execute([$device_id, $old_status, $user_fullname]);

    // 4. Create system alert for admin
    $alert_title = "Нова заявка: " . $device_name;
    $alert_msg = "Користувач " . $user_fullname . " створив заявку: " . $description;
    $stmt_alert = $pdo->prepare("INSERT INTO system_alerts (device_id, title, message, is_read) VALUES (?, ?, ?, 0)");
    $stmt_alert->execute([$device_id, $alert_title, $alert_msg]);

    // Commit transaction
    $pdo->commit();

    header("Location: ../device_view.php?id=$device_id&success=ticket_created");
    exit;

} catch (\PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    header("Location: ../device_view.php?id=$device_id&error=db_error&msg=" . urlencode($e->getMessage()));
    exit;
}
