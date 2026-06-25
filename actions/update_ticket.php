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

        // 5. Save repair expenses if present
        if (isset($_POST['expense_rows']) && is_array($_POST['expense_rows'])) {
            $allowed_expense_types   = ['запчастина', 'витратний матеріал', 'послуга', 'доставка', 'інше'];
            $allowed_payment_statuses = ['оплачено', 'очікує оплати', 'заплановано'];
            
            foreach ($_POST['expense_rows'] as $idx => $row) {
                $part_name = trim($row['name'] ?? '');
                if (empty($part_name)) {
                    continue;
                }
                
                $expense_type   = trim($row['type'] ?? 'запчастина');
                $quantity       = isset($row['qty']) ? max(1, (int)$row['qty']) : 1;
                $unit_price     = isset($row['price']) ? round((float)str_replace(',', '.', $row['price']), 2) : 0.0;
                $supplier       = isset($row['supplier']) && trim($row['supplier']) !== '' ? trim($row['supplier']) : null;
                $warranty_months = isset($row['warranty']) ? max(0, (int)$row['warranty']) : 0;
                $payment_status = isset($row['payment']) ? trim($row['payment']) : 'оплачено';
                $created_by     = $_SESSION['user_id'] ?? null;
                
                if (!in_array($expense_type, $allowed_expense_types)) {
                    $expense_type = 'запчастина';
                }
                if (!in_array($payment_status, $allowed_payment_statuses)) {
                    $payment_status = 'оплачено';
                }
                
                $attachment_path = null;
                // Check if file was uploaded for this row
                if (isset($_FILES['expense_rows']['error'][$idx]['attachment']) && 
                    $_FILES['expense_rows']['error'][$idx]['attachment'] === UPLOAD_ERR_OK) {
                    
                    $upload_dir = __DIR__ . '/../uploads/receipts/';
                    if (!is_dir($upload_dir)) {
                        mkdir($upload_dir, 0755, true);
                    }
                    
                    $allowed_ext = ['jpg', 'jpeg', 'png', 'pdf'];
                    $max_size    = 5 * 1024 * 1024; // 5 MB
                    
                    $orig_name = $_FILES['expense_rows']['name'][$idx]['attachment'];
                    $ext       = strtolower(pathinfo($orig_name, PATHINFO_EXTENSION));
                    $tmp_path  = $_FILES['expense_rows']['tmp_name'][$idx]['attachment'];
                    $file_size = $_FILES['expense_rows']['size'][$idx]['attachment'];
                    
                    if (in_array($ext, $allowed_ext) && $file_size <= $max_size) {
                        // Verify MIME type
                        $finfo = finfo_open(FILEINFO_MIME_TYPE);
                        $mime  = finfo_file($finfo, $tmp_path);
                        finfo_close($finfo);
                        
                        $allowed_mimes = ['image/jpeg', 'image/png', 'application/pdf'];
                        if (in_array($mime, $allowed_mimes)) {
                            $safe_name = 'receipt_' . time() . '_' . $idx . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
                            $dest      = $upload_dir . $safe_name;
                            if (move_uploaded_file($tmp_path, $dest)) {
                                $attachment_path = 'uploads/receipts/' . $safe_name;
                            }
                        }
                    }
                }
                
                $total_price = round($quantity * $unit_price, 2);
                
                $stmt_exp = $pdo->prepare("
                    INSERT INTO `repair_expenses`
                        (`ticket_id`, `device_id`, `expense_type`, `part_name`, `description`,
                         `quantity`, `unit_price`, `total_price`, `supplier`, `receipt_number`,
                         `attachment_path`, `warranty_months`, `expense_date`, `payment_status`, `created_by`)
                    VALUES
                        (?, ?, ?, ?, ?, ?, ?, ?, ?, NULL, ?, ?, ?, ?, ?)
                ");
                
                $stmt_exp->execute([
                    $ticket_id, $device_id, $expense_type, $part_name, null,
                    $quantity, $unit_price, $total_price, $supplier,
                    $attachment_path, $warranty_months, date('Y-m-d'), $payment_status, $created_by
                ]);
                
                // Log in device history
                $log_value = "«{$part_name}» ({$expense_type}) — {$total_price} грн (через заявку #{$ticket_id})";
                $stmt_log = $pdo->prepare("
                    INSERT INTO `device_history` (`device_id`, `event_type`, `old_value`, `new_value`, `responsible_person`)
                    VALUES (?, 'ремонт', 'Витрата на обслуговування при вирішенні заявки', ?, ?)
                ");
                $stmt_log->execute([$device_id, $log_value, $admin_fullname]);
            }
        }
        
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
