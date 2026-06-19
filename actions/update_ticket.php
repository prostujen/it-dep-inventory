<?php
require_once '../config/db.php';

// Check if user is admin
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || !isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: ../index.php?error=access_denied");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: ../index.php");
    exit;
}

$ticket_id = intval($_POST['ticket_id'] ?? 0);
$ticket_action = trim($_POST['action'] ?? '');
$comment = trim($_POST['comment'] ?? '');
$admin_fullname = $_SESSION['user_fullname'] ?? 'Адміністратор';

if ($ticket_id <= 0 || empty($ticket_action)) {
    header("Location: ../index.php?error=invalid_id");
    exit;
}

try {
    // Get ticket details and current device status
    $stmt_ticket = $pdo->prepare("
        SELECT t.*, d.status AS device_status, d.id AS dev_id 
        FROM tickets t
        JOIN devices d ON t.device_id = d.id
        WHERE t.id = ?
    ");
    $stmt_ticket->execute([$ticket_id]);
    $ticket = $stmt_ticket->fetch();

    if (!$ticket) {
        header("Location: ../index.php?error=not_found");
        exit;
    }

    $device_id = $ticket['dev_id'];
    $current_device_status = $ticket['device_status'];

    $pdo->beginTransaction();

    $success_type = 'ticket_updated';
    if ($ticket_action === 'take_job') {
        // 1. Update ticket status to 'в роботі'
        $stmt_up_ticket = $pdo->prepare("UPDATE tickets SET status = 'в роботі' WHERE id = ?");
        $stmt_up_ticket->execute([$ticket_id]);

        // 2. Update device status to 'на ремонті'
        $stmt_up_dev = $pdo->prepare("UPDATE devices SET status = 'на ремонті' WHERE id = ?");
        $stmt_up_dev->execute([$device_id]);

        // 3. Log history
        $stmt_hist = $pdo->prepare("INSERT INTO device_history (device_id, event_type, old_value, new_value, responsible_person) VALUES (?, 'зміна статусу', ?, 'на ремонті', ?)");
        $stmt_hist->execute([$device_id, $current_device_status, $admin_fullname]);
        
        $success_type = 'ticket_taken';

    } elseif ($ticket_action === 'resolve') {
        // 1. Update ticket status to 'виконана' and add comment
        $stmt_up_ticket = $pdo->prepare("UPDATE tickets SET status = 'виконана', comment = ? WHERE id = ?");
        $stmt_up_ticket->execute([$comment, $ticket_id]);

        // 2. Update device status back to 'в роботі'
        $stmt_up_dev = $pdo->prepare("UPDATE devices SET status = 'в роботі' WHERE id = ?");
        $stmt_up_dev->execute([$device_id]);

        // 3. Log history status change
        $stmt_hist_status = $pdo->prepare("INSERT INTO device_history (device_id, event_type, old_value, new_value, responsible_person) VALUES (?, 'зміна статусу', 'на ремонті', 'в роботі', ?)");
        $stmt_hist_status->execute([$device_id, $admin_fullname]);

        // 4. Log history repair work
        $repair_details = "Виконано ремонт: " . $comment;
        $stmt_hist_repair = $pdo->prepare("INSERT INTO device_history (device_id, event_type, old_value, new_value, responsible_person) VALUES (?, 'ремонт', ?, 'в роботі', ?)");
        $stmt_hist_repair->execute([$device_id, $repair_details, $admin_fullname]);
        
        $success_type = 'ticket_closed';

    } elseif ($ticket_action === 'reject') {
        // 1. Update ticket status to 'відхилена' and add comment
        $stmt_up_ticket = $pdo->prepare("UPDATE tickets SET status = 'відхилена', comment = ? WHERE id = ?");
        $stmt_up_ticket->execute([$comment, $ticket_id]);

        // 2. Update device status back to 'в роботі'
        $stmt_up_dev = $pdo->prepare("UPDATE devices SET status = 'в роботі' WHERE id = ?");
        $stmt_up_dev->execute([$device_id]);

        // 3. Log history
        $reject_details = "Заявку відхилено. Причина: " . $comment;
        $stmt_hist = $pdo->prepare("INSERT INTO device_history (device_id, event_type, old_value, new_value, responsible_person) VALUES (?, 'зміна статусу', ?, 'в роботі', ?)");
        $stmt_hist->execute([$device_id, $reject_details, $admin_fullname]);
        
        $success_type = 'ticket_rejected';
    }

    $pdo->commit();

    header("Location: ../device_view.php?id=$device_id&success=" . $success_type);
    exit;

} catch (\PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    header("Location: ../device_view.php?id=$device_id&error=db_error&msg=" . urlencode($e->getMessage()));
    exit;
}
