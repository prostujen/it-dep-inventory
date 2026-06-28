<?php
require_once '../config/db.php';

if (!$is_admin) {
    header('Location: ../index.php?error=access_denied');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../index.php');
    exit;
}

$device_id      = isset($_POST['device_id']) ? (int)$_POST['device_id'] : 0;
$ticket_id      = isset($_POST['ticket_id']) && $_POST['ticket_id'] !== '' ? (int)$_POST['ticket_id'] : null;
$expense_date   = isset($_POST['expense_date']) ? trim($_POST['expense_date']) : date('Y-m-d');
$receipt_number = isset($_POST['receipt_number']) && trim($_POST['receipt_number']) !== '' ? trim($_POST['receipt_number']) : null;
$description    = isset($_POST['description']) && trim($_POST['description']) !== '' ? trim($_POST['description']) : null;
$created_by     = $_SESSION['user_id'] ?? null;

$redirect_to = isset($_POST['redirect_to']) ? trim($_POST['redirect_to']) : '';
if ($redirect_to === 'repair_costs.php') {
    $redirect_base = '../repair_costs.php?from=1';
} else {
    $redirect_base = '../device_view.php?id=' . $device_id . '&tab=expenses';
}

// Basic validation
if ($device_id <= 0) {
    header('Location: ' . $redirect_base . '&error=empty_fields');
    exit;
}
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $expense_date)) {
    $expense_date = date('Y-m-d');
}

// Check device exists
$stmt_device = $pdo->prepare("SELECT id, name, model FROM devices WHERE id = ?");
$stmt_device->execute([$device_id]);
$device_row = $stmt_device->fetch();
if (!$device_row) {
    header('Location: ../index.php?error=not_found');
    exit;
}

if (isset($_POST['expense_rows']) && is_array($_POST['expense_rows'])) {
    $allowed_expense_types   = ['запчастина', 'витратний матеріал', 'послуга', 'доставка', 'інше'];
    $allowed_payment_statuses = ['оплачено', 'очікує оплати', 'заплановано'];
    
    $pdo->beginTransaction();
    try {
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
            
            if (!in_array($expense_type, $allowed_expense_types)) {
                $expense_type = 'запчастина';
            }
            if (!in_array($payment_status, $allowed_payment_statuses)) {
                $payment_status = 'оплачено';
            }
            
            $attachment_path = null;
            // File upload logic for this row
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
                    (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $stmt_exp->execute([
                $ticket_id, $device_id, $expense_type, $part_name, $description,
                $quantity, $unit_price, $total_price, $supplier, $receipt_number,
                $attachment_path, $warranty_months, $expense_date, $payment_status, $created_by
            ]);
            
            // Log in history
            $user_name = $_SESSION['user_fullname'] ?? 'Адмін';
            $log_value = "«{$part_name}» ({$expense_type}) — {$total_price} грн";
            $stmt_log = $pdo->prepare("
                INSERT INTO `device_history` (`device_id`, `event_type`, `old_value`, `new_value`, `responsible_person`)
                VALUES (?, 'ремонт', 'Нова витрата на обслуговування', ?, ?)
            ");
            $stmt_log->execute([$device_id, $log_value, $user_name]);
        }
        
        $pdo->commit();
        header('Location: ' . $redirect_base . '&success=expense_added');
        exit;
        
    } catch (\Exception $e) {
        $pdo->rollBack();
        header('Location: ' . $redirect_base . '&error=db_error&msg=' . urlencode($e->getMessage()));
        exit;
    }
} else {
    header('Location: ' . $redirect_base . '&error=empty_fields');
    exit;
}
