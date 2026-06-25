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
$expense_type   = isset($_POST['expense_type']) ? trim($_POST['expense_type']) : '';
$part_name      = isset($_POST['part_name']) ? trim($_POST['part_name']) : '';
$description    = isset($_POST['description']) ? trim($_POST['description']) : null;
$quantity       = isset($_POST['quantity']) ? max(1, (int)$_POST['quantity']) : 1;
$unit_price     = isset($_POST['unit_price']) ? round((float)str_replace(',', '.', $_POST['unit_price']), 2) : 0.0;
$supplier       = isset($_POST['supplier']) ? trim($_POST['supplier']) : null;
$receipt_number = isset($_POST['receipt_number']) ? trim($_POST['receipt_number']) : null;
$warranty_months = isset($_POST['warranty_months']) ? max(0, (int)$_POST['warranty_months']) : 0;
$expense_date   = isset($_POST['expense_date']) ? trim($_POST['expense_date']) : date('Y-m-d');
$payment_status = isset($_POST['payment_status']) ? trim($_POST['payment_status']) : 'оплачено';
$created_by     = $_SESSION['user_id'] ?? null;

$allowed_expense_types   = ['запчастина', 'витратний матеріал', 'послуга', 'доставка', 'інше'];
$allowed_payment_statuses = ['оплачено', 'очікує оплати', 'заплановано'];

$redirect_to = isset($_POST['redirect_to']) ? trim($_POST['redirect_to']) : '';
if ($redirect_to === 'repair_costs.php') {
    $redirect_base = '../repair_costs.php?from=1';
} else {
    $redirect_base = '../device_view.php?id=' . $device_id . '&tab=expenses';
}

// Validation
if ($device_id <= 0 || empty($part_name) || empty($expense_type) || $unit_price < 0) {
    header('Location: ' . $redirect_base . '&error=empty_fields');
    exit;
}
if (!in_array($expense_type, $allowed_expense_types)) {
    header('Location: ' . $redirect_base . '&error=invalid_type');
    exit;
}
if (!in_array($payment_status, $allowed_payment_statuses)) {
    header('Location: ' . $redirect_base . '&error=invalid_status');
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

// Handle file upload
$attachment_path = null;
if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK) {
    $upload_dir = __DIR__ . '/../uploads/receipts/';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }

    $allowed_ext = ['jpg', 'jpeg', 'png', 'pdf'];
    $max_size    = 5 * 1024 * 1024; // 5 MB

    $orig_name = $_FILES['attachment']['name'];
    $ext       = strtolower(pathinfo($orig_name, PATHINFO_EXTENSION));
    $tmp_path  = $_FILES['attachment']['tmp_name'];
    $file_size = $_FILES['attachment']['size'];

    if (!in_array($ext, $allowed_ext)) {
        header('Location: ' . $redirect_base . '&error=invalid_file_type');
        exit;
    }
    if ($file_size > $max_size) {
        header('Location: ' . $redirect_base . '&error=file_too_large');
        exit;
    }

    // Verify it's actually an image or PDF by MIME type check
    $finfo     = finfo_open(FILEINFO_MIME_TYPE);
    $mime      = finfo_file($finfo, $tmp_path);
    finfo_close($finfo);
    $allowed_mimes = ['image/jpeg', 'image/png', 'application/pdf'];
    if (!in_array($mime, $allowed_mimes)) {
        header('Location: ' . $redirect_base . '&error=invalid_file_type');
        exit;
    }

    $safe_name       = 'receipt_' . time() . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
    $dest            = $upload_dir . $safe_name;
    if (move_uploaded_file($tmp_path, $dest)) {
        $attachment_path = 'uploads/receipts/' . $safe_name;
    }
}

$total_price = round($quantity * $unit_price, 2);

try {
    $stmt = $pdo->prepare("
        INSERT INTO `repair_expenses`
            (`ticket_id`, `device_id`, `expense_type`, `part_name`, `description`,
             `quantity`, `unit_price`, `total_price`, `supplier`, `receipt_number`,
             `attachment_path`, `warranty_months`, `expense_date`, `payment_status`, `created_by`)
        VALUES
            (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $ticket_id, $device_id, $expense_type, $part_name, $description ?: null,
        $quantity, $unit_price, $total_price, $supplier ?: null, $receipt_number ?: null,
        $attachment_path, $warranty_months, $expense_date, $payment_status, $created_by
    ]);

    // Log in device history
    $user_name = $_SESSION['user_fullname'] ?? 'Адмін';
    $type_label = $expense_type;
    $log_value  = "«{$part_name}» ({$type_label}) — {$total_price} грн";
    $stmt_log = $pdo->prepare("
        INSERT INTO `device_history` (`device_id`, `event_type`, `old_value`, `new_value`, `responsible_person`)
        VALUES (?, 'ремонт', 'Нова витрата на обслуговування', ?, ?)
    ");
    $stmt_log->execute([$device_id, $log_value, $user_name]);

    header('Location: ' . $redirect_base . '&success=expense_added');
    exit;

} catch (\PDOException $e) {
    header('Location: ' . $redirect_base . '&error=db_error&msg=' . urlencode($e->getMessage()));
    exit;
}
