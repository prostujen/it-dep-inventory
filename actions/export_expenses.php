<?php
require_once '../config/db.php';

if (!$is_admin) {
    header('HTTP/1.1 403 Forbidden');
    exit('Доступ заборонено');
}

// Build the same filter query as repair_costs.php
$date_from     = isset($_GET['date_from']) ? trim($_GET['date_from']) : '';
$date_to       = isset($_GET['date_to']) ? trim($_GET['date_to']) : '';
$filter_type   = isset($_GET['expense_type']) ? trim($_GET['expense_type']) : '';
$filter_status = isset($_GET['payment_status']) ? trim($_GET['payment_status']) : '';
$filter_device = isset($_GET['device_id']) ? (int)$_GET['device_id'] : 0;

$sql    = "SELECT re.*, d.name AS device_name, d.model AS device_model, d.inventory_number,
                  d.location, t.description AS ticket_desc, u.full_name AS created_by_name
           FROM `repair_expenses` re
           LEFT JOIN `devices` d ON re.device_id = d.id
           LEFT JOIN `tickets` t ON re.ticket_id = t.id
           LEFT JOIN `users` u ON re.created_by = u.id
           WHERE 1=1";
$params = [];

if ($date_from !== '') { $sql .= " AND re.expense_date >= ?"; $params[] = $date_from; }
if ($date_to   !== '') { $sql .= " AND re.expense_date <= ?"; $params[] = $date_to; }
if ($filter_type   !== '') { $sql .= " AND re.expense_type = ?"; $params[] = $filter_type; }
if ($filter_status !== '') { $sql .= " AND re.payment_status = ?"; $params[] = $filter_status; }
if ($filter_device > 0)   { $sql .= " AND re.device_id = ?"; $params[] = $filter_device; }

$sql .= " ORDER BY re.expense_date DESC, re.id DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

// Build filename with date range
$fname_date = ($date_from && $date_to) ? "_{$date_from}_{$date_to}" : '_' . date('Y-m-d');
$filename   = "vitrati_na_remont{$fname_date}.csv";

// Send headers for CSV download
header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: no-cache, no-store, must-revalidate');

// Open output
$out = fopen('php://output', 'w');

// UTF-8 BOM so Excel opens Cyrillic correctly
fwrite($out, "\xEF\xBB\xBF");

// Header row
fputcsv($out, [
    'ID', 'Дата', 'Пристрій', 'Модель', 'Інв. номер', 'Аудиторія',
    'Тип витрати', 'Назва', 'Опис', 'К-сть', 'Ціна (грн)', 'Сума (грн)',
    'Постачальник', 'Номер чека', 'Гарантія (міс.)', 'Статус оплати',
    'Прив. заявка', 'Хто додав'
], ';');

foreach ($rows as $r) {
    fputcsv($out, [
        $r['id'],
        $r['expense_date'],
        $r['device_name'],
        $r['device_model'],
        $r['inventory_number'],
        $r['location'],
        $r['expense_type'],
        $r['part_name'],
        $r['description'],
        $r['quantity'],
        number_format($r['unit_price'], 2, '.', ''),
        number_format($r['total_price'], 2, '.', ''),
        $r['supplier'],
        $r['receipt_number'],
        $r['warranty_months'],
        $r['payment_status'],
        $r['ticket_id'] ? '#' . $r['ticket_id'] . ' — ' . mb_strimwidth($r['ticket_desc'] ?? '', 0, 50, '…') : '',
        $r['created_by_name'],
    ], ';');
}

fclose($out);
exit;
