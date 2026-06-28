<?php
require_once 'config/db.php';

if (!$is_admin) {
    header('Location: index.php?error=access_denied');
    exit;
}

// 1. Handle Filters
$date_from     = isset($_GET['date_from']) ? trim($_GET['date_from']) : '';
$date_to       = isset($_GET['date_to']) ? trim($_GET['date_to']) : '';
$filter_type   = isset($_GET['expense_type']) ? trim($_GET['expense_type']) : '';
$filter_status = isset($_GET['payment_status']) ? trim($_GET['payment_status']) : '';
$filter_device = isset($_GET['device_id']) ? (int)$_GET['device_id'] : 0;
$search        = isset($_GET['q']) ? trim($_GET['q']) : '';

// 2. Fetch Stats
// Total repair costs (paid + pending)
$stmt_total_cost = $pdo->query("SELECT COALESCE(SUM(total_price),0) FROM repair_expenses WHERE payment_status != 'заплановано'");
$total_repair_cost = (float)$stmt_total_cost->fetchColumn();

// Repair costs current month
$stmt_cost_month = $pdo->query("SELECT COALESCE(SUM(total_price),0) FROM repair_expenses WHERE payment_status != 'заплановано' AND YEAR(expense_date) = YEAR(CURDATE()) AND MONTH(expense_date) = MONTH(CURDATE())");
$cost_month = (float)$stmt_cost_month->fetchColumn();

// Repair costs previous month
$stmt_cost_prev = $pdo->query("SELECT COALESCE(SUM(total_price),0) FROM repair_expenses WHERE payment_status != 'заплановано' AND YEAR(expense_date) = YEAR(DATE_SUB(CURDATE(), INTERVAL 1 MONTH)) AND MONTH(expense_date) = MONTH(DATE_SUB(CURDATE(), INTERVAL 1 MONTH))");
$cost_prev = (float)$stmt_cost_prev->fetchColumn();
$cost_change = $cost_prev > 0 ? round(($cost_month - $cost_prev) / $cost_prev * 100, 1) : null;

// Average cost per expense item
$stmt_avg_cost = $pdo->query("SELECT COALESCE(AVG(total_price),0) FROM repair_expenses WHERE payment_status != 'заплановано'");
$avg_cost = (float)$stmt_avg_cost->fetchColumn();

// Active warranties count
$stmt_warranty_count = $pdo->query("SELECT COUNT(*) FROM repair_expenses WHERE warranty_months > 0 AND DATE_ADD(expense_date, INTERVAL warranty_months MONTH) >= CURDATE()");
$active_warranties = (int)$stmt_warranty_count->fetchColumn();

// 3. Chart Data
// Monthly Trend (past 6 months)
$stmt_chart_monthly = $pdo->query("
    SELECT 
        DATE_FORMAT(expense_date, '%m.%Y') AS ym,
        SUM(total_price) AS total
    FROM repair_expenses
    WHERE payment_status != 'заплановано'
      AND expense_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
    GROUP BY DATE_FORMAT(expense_date, '%Y-%m'), ym
    ORDER BY DATE_FORMAT(expense_date, '%Y-%m') ASC
");
$chart_monthly = $stmt_chart_monthly->fetchAll();
$monthly_labels = [];
$monthly_values = [];
foreach ($chart_monthly as $row) {
    $monthly_labels[] = $row['ym'];
    $monthly_values[] = (float)$row['total'];
}

// Category Distribution
$stmt_chart_cat = $pdo->query("
    SELECT expense_type, SUM(total_price) AS total
    FROM repair_expenses
    WHERE payment_status != 'заплановано'
    GROUP BY expense_type
");
$chart_cat = $stmt_chart_cat->fetchAll();
$cat_labels = [];
$cat_values = [];
foreach ($chart_cat as $row) {
    $cat_labels[] = mb_convert_case($row['expense_type'], MB_CASE_TITLE, "UTF-8");
    $cat_values[] = (float)$row['total'];
}

// Location ranking (Top 5)
$stmt_chart_loc = $pdo->query("
    SELECT d.location, SUM(re.total_price) AS total
    FROM repair_expenses re
    JOIN devices d ON re.device_id = d.id
    WHERE re.payment_status != 'заплановано' AND d.location IS NOT NULL AND d.location != ''
    GROUP BY d.location
    ORDER BY total DESC
    LIMIT 5
");
$chart_loc = $stmt_chart_loc->fetchAll();
$loc_labels = [];
$loc_values = [];
foreach ($chart_loc as $row) {
    $loc_labels[] = $row['location'];
    $loc_values[] = (float)$row['total'];
}

// 4. Rankings List
// Top-5 devices by cost
$stmt_top_devices = $pdo->query("
    SELECT d.id, d.name, d.model, d.inventory_number, SUM(re.total_price) AS total
    FROM repair_expenses re
    JOIN devices d ON re.device_id = d.id
    WHERE re.payment_status != 'заплановано'
    GROUP BY d.id
    ORDER BY total DESC
    LIMIT 5
");
$top_devices = $stmt_top_devices->fetchAll();
$highest_device_cost = !empty($top_devices) ? (float)$top_devices[0]['total'] : 1;

// Top-5 suppliers
$stmt_top_suppliers = $pdo->query("
    SELECT supplier, SUM(total_price) AS total
    FROM repair_expenses
    WHERE payment_status != 'заплановано' AND supplier IS NOT NULL AND supplier != ''
    GROUP BY supplier
    ORDER BY total DESC
    LIMIT 5
");
$top_suppliers = $stmt_top_suppliers->fetchAll();
$highest_supplier_cost = !empty($top_suppliers) ? (float)$top_suppliers[0]['total'] : 1;


// 5. Paginated Detailed List Query Build
$sql = "SELECT re.*, d.name AS device_name, d.model AS device_model, d.inventory_number, d.location,
               t.description AS ticket_desc, u.full_name AS created_by_name
        FROM `repair_expenses` re
        LEFT JOIN `devices` d ON re.device_id = d.id
        LEFT JOIN `tickets` t ON re.ticket_id = t.id
        LEFT JOIN `users` u ON re.created_by = u.id
        WHERE 1=1";
$params = [];

if ($date_from !== '') {
    $sql .= " AND re.expense_date >= ?";
    $params[] = $date_from;
}
if ($date_to !== '') {
    $sql .= " AND re.expense_date <= ?";
    $params[] = $date_to;
}
if ($filter_type !== '') {
    $sql .= " AND re.expense_type = ?";
    $params[] = $filter_type;
}
if ($filter_status !== '') {
    $sql .= " AND re.payment_status = ?";
    $params[] = $filter_status;
}
if ($filter_device > 0) {
    $sql .= " AND re.device_id = ?";
    $params[] = $filter_device;
}
if ($search !== '') {
    $sql .= " AND (re.part_name LIKE ? OR re.description LIKE ? OR re.supplier LIKE ? OR re.receipt_number LIKE ? OR d.name LIKE ? OR d.model LIKE ? OR d.inventory_number LIKE ?)";
    $search_param = "%{$search}%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}

// Count total rows for pagination
$sql_count = "SELECT COUNT(*) FROM (" . $sql . ") AS count_table";
$stmt_count = $pdo->prepare($sql_count);
$stmt_count->execute($params);
$total_rows = (int)$stmt_count->fetchColumn();

$limit = 20;
$total_pages = ceil($total_rows / $limit);
$page = isset($_GET['page']) ? max(1, min($total_pages, (int)$_GET['page'])) : 1;
$offset = ($page - 1) * $limit;

$sql .= " ORDER BY re.expense_date DESC, re.id DESC LIMIT $limit OFFSET $offset";
$stmt_data = $pdo->prepare($sql);
$stmt_data->execute($params);
$expenses = $stmt_data->fetchAll();

// Devices for filter dropdown
$stmt_dev_dropdown = $pdo->query("SELECT id, name, model, inventory_number FROM devices ORDER BY name ASC, model ASC");
$devices_dropdown = $stmt_dev_dropdown->fetchAll();

// Preserve query params for pagination links
$query_params = $_GET;
unset($query_params['page']);
$page_query = http_build_query($query_params);
if (!empty($page_query)) {
    $page_query = '&' . $page_query;
}

// Warranty status renderer helper
function getWarrantyStatus($expense_date, $warranty_months) {
    if ($warranty_months <= 0) {
        return '<span class="warranty-none">—</span>';
    }
    $expiry_date = date('Y-m-d', strtotime($expense_date . " + {$warranty_months} month"));
    $today = date('Y-m-d');
    if ($expiry_date >= $today) {
        $exp_ts = strtotime($expiry_date);
        $today_ts = strtotime($today);
        $diff_days = round(($exp_ts - $today_ts) / (60 * 60 * 24));
        if ($diff_days > 30) {
            $rem_months = floor($diff_days / 30);
            $text = "ще {$rem_months} міс.";
        } else {
            $text = "ще {$diff_days} дн.";
        }
        return '<span class="warranty-active" title="До ' . date('d.m.Y', $exp_ts) . '">На гарантії (' . $text . ')</span>';
    } else {
        return '<span class="warranty-expired" title="Закінчилась ' . date('d.m.Y', strtotime($expiry_date)) . '">Закінчилась</span>';
    }
}

require_once 'includes/header.php';
?>

<!-- Print-only header -->
<div class="print-header mb-4 text-center">
    <h3 class="fw-bold">ЗВІТ ПРО ВИТРАТИ НА РЕМОНТ ТА ОБСЛУГОВУВАННЯ ОБЛАДНАННЯ</h3>
    <p class="text-muted small">Згенеровано: <?php echo date('d.m.Y H:i'); ?> | IT-Dep Inventory</p>
</div>

<!-- Alerts -->
<?php if (isset($_GET['success'])): ?>
    <div class="alert alert-success p-3 mb-4 no-print">
        <i class="bi bi-check-circle-fill me-2"></i>
        <?php 
            if ($_GET['success'] === 'expense_deleted') echo 'Запис витрати успішно видалено!';
            elseif ($_GET['success'] === 'expense_added') echo 'Витрату успішно додано!';
        ?>
    </div>
<?php endif; ?>
<?php if (isset($_GET['error'])): ?>
    <div class="alert alert-danger p-3 mb-4 no-print">
        <i class="bi bi-exclamation-triangle-fill me-2"></i>
        <strong>Помилка: </strong>
        <?php 
            if ($_GET['error'] === 'db_error') echo 'Помилка бази даних: ' . htmlspecialchars($_GET['msg'] ?? '');
            elseif ($_GET['error'] === 'not_found') echo 'Запис витрати не знайдено.';
            else echo 'Невідома помилка.';
        ?>
    </div>
<?php endif; ?>

<!-- Dashboard KPI Widgets -->
<div class="row g-4 mb-4 no-print">
    <div class="col-lg-3 col-sm-6">
        <div class="glass-card widget-card h-100" style="--widget-rgb: 45, 74, 130;">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h6 class="text-muted text-uppercase mb-2">Загальні витрати</h6>
                    <h2 class="fw-bold mb-0 text-gradient" style="color: var(--accent-blue) !important;"><?php echo number_format($total_repair_cost, 2, '.', ' '); ?> <small style="font-size: 0.9rem;">грн</small></h2>
                </div>
                <div class="fs-1 text-primary opacity-75">
                    <i class="bi bi-wallet2"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-3 col-sm-6">
        <div class="glass-card widget-card h-100" style="--widget-rgb: 245, 158, 11;">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h6 class="text-muted text-uppercase mb-2">Цього місяця</h6>
                    <h2 class="fw-bold mb-0 text-warning"><?php echo number_format($cost_month, 2, '.', ' '); ?> <small style="font-size: 0.9rem;">грн</small></h2>
                    <?php if ($cost_change !== null): ?>
                    <div class="mt-1" style="font-size:0.75rem;">
                        <?php if ($cost_change > 0): ?>
                        <span class="text-danger"><i class="bi bi-arrow-up-right"></i> +<?php echo $cost_change; ?>%</span>
                        <?php elseif ($cost_change < 0): ?>
                        <span class="text-success"><i class="bi bi-arrow-down-right"></i> <?php echo $cost_change; ?>%</span>
                        <?php else: ?>
                        <span class="text-muted">= без змін</span>
                        <?php endif; ?>
                        <span class="text-muted"> vs мин. міс.</span>
                    </div>
                    <?php endif; ?>
                </div>
                <div class="fs-1 text-warning opacity-75">
                    <i class="bi bi-graph-up-arrow"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-3 col-sm-6">
        <div class="glass-card widget-card h-100" style="--widget-rgb: 16, 185, 129;">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h6 class="text-muted text-uppercase mb-2">Сер. чек ремонту</h6>
                    <h2 class="fw-bold mb-0 text-success"><?php echo number_format($avg_cost, 2, '.', ' '); ?> <small style="font-size: 0.9rem;">грн</small></h2>
                </div>
                <div class="fs-1 text-success opacity-75">
                    <i class="bi bi-calculator"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-3 col-sm-6">
        <div class="glass-card widget-card h-100" style="--widget-rgb: 111, 66, 193;">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h6 class="text-muted text-uppercase mb-2">На гарантії</h6>
                    <h2 class="fw-bold mb-0 text-purple" style="color: var(--accent-purple) !important;"><?php echo $active_warranties; ?> <small style="font-size: 0.9rem;">деталей</small></h2>
                </div>
                <div class="fs-1 text-purple opacity-75" style="color: var(--accent-purple) !important;">
                    <i class="bi bi-shield-check"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Interactive Analytics Charts (Chart.js) -->
<div class="row g-4 mb-4 no-print">
    <div class="col-md-4">
        <div class="chart-card h-100">
            <div class="chart-title">Динаміка витрат по місяцях</div>
            <?php if (empty($monthly_values)): ?>
                <div class="text-center py-5 text-muted small">Немає фінансових даних за останні 6 місяців</div>
            <?php else: ?>
                <canvas id="monthlyTrendChart"></canvas>
            <?php endif; ?>
        </div>
    </div>
    <div class="col-md-4">
        <div class="chart-card h-100">
            <div class="chart-title">Витрати за категоріями</div>
            <?php if (empty($cat_values)): ?>
                <div class="text-center py-5 text-muted small">Немає фінансових даних для відображення категорій</div>
            <?php else: ?>
                <canvas id="categoryDistributionChart"></canvas>
            <?php endif; ?>
        </div>
    </div>
    <div class="col-md-4">
        <div class="chart-card h-100">
            <div class="chart-title">Витрати по аудиторіях (Топ-5)</div>
            <?php if (empty($loc_values)): ?>
                <div class="text-center py-5 text-muted small">Немає локацій з витратами на обслуговування</div>
            <?php else: ?>
                <canvas id="locationExpensesChart"></canvas>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Filters and Actions -->
<div class="glass-card mb-4 no-print">
    <h5 class="fw-bold mb-3 d-flex align-items-center gap-2" style="color:var(--accent-blue);">
        <i class="bi bi-funnel-fill"></i> Фільтрація транзакцій та експорт
    </h5>
    <form method="GET" action="repair_costs.php" id="filterForm">
        <div class="row g-3">
            <div class="col-lg-3 col-md-6">
                <label class="form-label text-muted small mb-1">Пошук запчастини / чека</label>
                <div class="input-group">
                    <span class="input-group-text bg-transparent border-secondary border-opacity-25 text-muted filter-row-input"><i class="bi bi-search"></i></span>
                    <input type="text" name="q" class="form-control form-control-custom filter-row-input" placeholder="Введіть запит..." value="<?php echo htmlspecialchars($search); ?>">
                </div>
            </div>
            
            <div class="col-lg-2 col-md-6">
                <label class="form-label text-muted small mb-1">Обладнання (Пристрій)</label>
                <select name="device_id" class="form-select form-select-custom filter-row-input">
                    <option value="">-- Всі пристрої --</option>
                    <?php foreach ($devices_dropdown as $dev): ?>
                        <option value="<?php echo $dev['id']; ?>" <?php echo $filter_device == $dev['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($dev['name'] . ' ' . $dev['model'] . ' [' . $dev['inventory_number'] . ']'); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="col-lg-2 col-md-4">
                <label class="form-label text-muted small mb-1">Тип витрати</label>
                <select name="expense_type" class="form-select form-select-custom filter-row-input">
                    <option value="">-- Всі типи --</option>
                    <option value="запчастина" <?php echo $filter_type === 'запчастина' ? 'selected' : ''; ?>>🔩 Запчастина</option>
                    <option value="витратний матеріал" <?php echo $filter_type === 'витратний матеріал' ? 'selected' : ''; ?>>📦 Витратний матеріал</option>
                    <option value="послуга" <?php echo $filter_type === 'послуга' ? 'selected' : ''; ?>>🛠️ Послуга</option>
                    <option value="доставка" <?php echo $filter_type === 'доставка' ? 'selected' : ''; ?>>🚚 Доставка</option>
                    <option value="інше" <?php echo $filter_type === 'інше' ? 'selected' : ''; ?>>📋 Інше</option>
                </select>
            </div>

            <div class="col-lg-2 col-md-4">
                <label class="form-label text-muted small mb-1">Статус оплати</label>
                <select name="payment_status" class="form-select form-select-custom filter-row-input">
                    <option value="">-- Всі статуси --</option>
                    <option value="оплачено" <?php echo $filter_status === 'оплачено' ? 'selected' : ''; ?>>✅ Оплачено</option>
                    <option value="очікує оплати" <?php echo $filter_status === 'очікує оплати' ? 'selected' : ''; ?>>⏳ Очікує</option>
                    <option value="заплановано" <?php echo $filter_status === 'заплановано' ? 'selected' : ''; ?>>📅 Заплановано</option>
                </select>
            </div>

            <div class="col-lg-3 col-md-4">
                <label class="form-label text-muted small mb-1">Період (Дати)</label>
                <div class="d-flex gap-2">
                    <input type="date" name="date_from" id="filter-date-from" class="form-control form-control-custom filter-row-input w-50" value="<?php echo htmlspecialchars($date_from); ?>">
                    <input type="date" name="date_to" id="filter-date-to" class="form-control form-control-custom filter-row-input w-50" value="<?php echo htmlspecialchars($date_to); ?>">
                </div>
            </div>
            
            <div class="col-12 d-flex flex-wrap justify-content-between align-items-center gap-2 mt-2 pt-2 border-top border-secondary border-opacity-10">
                <div class="d-flex align-items-center gap-2">
                    <span class="text-muted small">Швидкі періоди:</span>
                    <button type="button" class="btn-period" data-period="month" onclick="setDatePeriod('month')">Цей місяць</button>
                    <button type="button" class="btn-period" data-period="year" onclick="setDatePeriod('year')">Цей рік</button>
                    <button type="button" class="btn-period" data-period="all" onclick="setDatePeriod('all')">Весь час</button>
                </div>
                <div class="d-flex gap-2">
                    <a href="repair_costs.php" class="btn btn-custom-secondary btn-filter-reset" title="Очистити фільтри">
                        <i class="bi bi-arrow-counterclockwise fs-5"></i>
                    </a>
                    <button type="submit" class="btn btn-custom-primary btn-filter-apply py-0 px-3">
                        <i class="bi bi-filter"></i> Застосувати
                    </button>
                    <a href="actions/export_expenses.php?<?php echo http_build_query($_GET); ?>" class="btn btn-outline-success d-inline-flex align-items-center justify-content-center gap-2 py-0 px-3" style="height:38px; font-weight:500;">
                        <i class="bi bi-file-earmark-excel"></i> Експорт Excel
                    </a>
                    <button type="button" class="btn btn-outline-secondary d-inline-flex align-items-center justify-content-center gap-2 py-0 px-3" onclick="printExpenseReport()" style="height:38px; font-weight:500;">
                        <i class="bi bi-printer"></i> Друк звіту
                    </button>
                </div>
            </div>
        </div>
    </form>
</div>

<!-- Split rankings (Top devices & Top suppliers) -->
<div class="row g-4 mb-4 no-print">
    <div class="col-md-6">
        <div class="glass-card h-100">
            <h6 class="fw-bold text-gradient mb-3" style="color:var(--accent-blue) !important;">
                <i class="bi bi-hdd-network-fill"></i> ТОП-5 найдорожчих пристроїв в обслуговуванні
            </h6>
            <?php if (empty($top_devices)): ?>
                <div class="text-center py-4 text-muted small">Немає даних про витрати</div>
            <?php else: ?>
                <?php foreach ($top_devices as $dev): 
                    $percent = round(($dev['total'] / $highest_device_cost) * 100);
                ?>
                <div class="analytics-progress-row">
                    <div class="device-label">
                        <a href="device_view.php?id=<?php echo $dev['id']; ?>" class="text-decoration-none fw-semibold"><?php echo htmlspecialchars($dev['name'] . ' ' . $dev['model']); ?></a>
                        <span><?php echo number_format($dev['total'], 2, '.', ' '); ?> грн</span>
                    </div>
                    <div class="progress">
                        <div class="progress-bar" role="progressbar" style="width: <?php echo $percent; ?>%" aria-valuenow="<?php echo $percent; ?>" aria-valuemin="0" aria-valuemax="100"></div>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
    
    <div class="col-md-6">
        <div class="glass-card h-100">
            <h6 class="fw-bold text-gradient mb-3" style="color:var(--accent-blue) !important;">
                <i class="bi bi-shop"></i> ТОП-5 постачальників за сумою замовлень
            </h6>
            <?php if (empty($top_suppliers)): ?>
                <div class="text-center py-4 text-muted small">Немає даних про постачальників</div>
            <?php else: ?>
                <?php foreach ($top_suppliers as $sup): 
                    $percent = round(($sup['total'] / $highest_supplier_cost) * 100);
                ?>
                <div class="analytics-progress-row">
                    <div class="device-label">
                        <span class="fw-semibold text-secondary"><?php echo htmlspecialchars($sup['supplier']); ?></span>
                        <span><?php echo number_format($sup['total'], 2, '.', ' '); ?> грн</span>
                    </div>
                    <div class="progress">
                        <div class="progress-bar bg-success" role="progressbar" style="width: <?php echo $percent; ?>%; background: linear-gradient(90deg, #10b981, #059669);" aria-valuenow="<?php echo $percent; ?>" aria-valuemin="0" aria-valuemax="100"></div>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Transaction Table -->
<div class="glass-card">
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <h5 class="fw-bold m-0 d-flex align-items-center gap-2" style="color:var(--accent-blue);">
            <i class="bi bi-list-columns-reverse"></i> Список транзакцій та витрат
        </h5>
        <button type="button" class="btn btn-sm btn-custom-primary d-inline-flex align-items-center gap-2 px-3 py-2 no-print" data-bs-toggle="modal" data-bs-target="#addExpenseModal">
            <i class="bi bi-plus-circle"></i> Додати витрату
        </button>
    </div>
    
    <div class="table-responsive table-responsive-custom">
        <table class="table table-custom table-hover m-0">
            <thead>
                <tr>
                    <th style="width: 5%;">ID</th>
                    <th style="width: 10%;">Дата</th>
                    <th style="width: 15%;">Пристрій / Інв. №</th>
                    <th style="width: 12%;">Тип</th>
                    <th>Назва витрати / Опис</th>
                    <th style="width: 8%; text-align: right;">К-сть</th>
                    <th style="width: 10%; text-align: right;">Ціна</th>
                    <th style="width: 12%; text-align: right;">Сума</th>
                    <th style="width: 10%;">Гарантія</th>
                    <th style="width: 10%;">Статус</th>
                    <th style="width: 8%;" class="no-print">Чек</th>
                    <th style="width: 5%;" class="no-print">Дії</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($expenses)): ?>
                    <tr>
                        <td colspan="12" class="text-center py-4 text-muted">Витрати за вказаними критеріями не знайдені.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($expenses as $exp): ?>
                        <tr class="expense-row">
                            <td>#<?php echo $exp['id']; ?></td>
                            <td><span class="text-muted small"><?php echo date('d.m.Y', strtotime($exp['expense_date'])); ?></span></td>
                            <td>
                                <?php if (!empty($exp['device_name'])): ?>
                                    <a href="device_view.php?id=<?php echo $exp['device_id']; ?>" class="fw-semibold text-decoration-none text-primary d-block">
                                        <?php echo htmlspecialchars($exp['device_name']); ?>
                                    </a>
                                    <span class="text-muted small d-block"><?php echo htmlspecialchars($exp['device_model'] . ' [' . $exp['inventory_number'] . ']'); ?></span>
                                    <?php if (!empty($exp['location'])): ?>
                                        <span class="badge bg-light text-dark border small mt-1" style="font-size:0.68rem; padding: 0.15rem 0.3rem;"><i class="bi bi-geo-alt"></i> <?php echo htmlspecialchars($exp['location']); ?></span>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span class="text-danger small">Пристрій видалено</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php 
                                    $badge_class = 'badge-expense-other';
                                    if ($exp['expense_type'] === 'запчастина') $badge_class = 'badge-expense-part';
                                    elseif ($exp['expense_type'] === 'витратний матеріал') $badge_class = 'badge-expense-material';
                                    elseif ($exp['expense_type'] === 'послуга') $badge_class = 'badge-expense-service';
                                    elseif ($exp['expense_type'] === 'доставка') $badge_class = 'badge-expense-delivery';
                                ?>
                                <span class="<?php echo $badge_class; ?>"><?php echo mb_convert_case($exp['expense_type'], MB_CASE_TITLE, "UTF-8"); ?></span>
                            </td>
                            <td>
                                <strong class="text-dark d-block"><?php echo htmlspecialchars($exp['part_name']); ?></strong>
                                <?php if (!empty($exp['description'])): ?>
                                    <span class="text-muted small d-block"><?php echo htmlspecialchars($exp['description']); ?></span>
                                <?php endif; ?>
                                <?php if (!empty($exp['supplier'])): ?>
                                    <span class="text-secondary small d-block"><i class="bi bi-shop"></i> <?php echo htmlspecialchars($exp['supplier']); ?></span>
                                <?php endif; ?>
                                <?php if (!empty($exp['ticket_id'])): ?>
                                    <span class="text-muted small d-block text-truncate" style="max-width: 250px;" title="Заявка #<?php echo $exp['ticket_id']; ?>: <?php echo htmlspecialchars($exp['ticket_desc'] ?? ''); ?>">
                                        <i class="bi bi-wrench"></i> Заявка #<?php echo $exp['ticket_id']; ?>: <?php echo htmlspecialchars($exp['ticket_desc'] ?? ''); ?>
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td style="text-align: right;"><?php echo $exp['quantity']; ?></td>
                            <td style="text-align: right;" class="text-secondary"><?php echo number_format($exp['unit_price'], 2, '.', ' '); ?> грн</td>
                            <td style="text-align: right;"><strong class="text-dark"><?php echo number_format($exp['total_price'], 2, '.', ' '); ?> грн</strong></td>
                            <td><?php echo getWarrantyStatus($exp['expense_date'], $exp['warranty_months']); ?></td>
                            <td>
                                <?php 
                                    $status_class = 'badge-paid';
                                    $status_text = 'Оплачено';
                                    if ($exp['payment_status'] === 'очікує оплати') {
                                        $status_class = 'badge-pending';
                                        $status_text = 'Очікує';
                                    } elseif ($exp['payment_status'] === 'заплановано') {
                                        $status_class = 'badge-planned';
                                        $status_text = 'Заплановано';
                                    }
                                ?>
                                <span class="<?php echo $status_class; ?>"><?php echo $status_text; ?></span>
                            </td>
                            <td class="no-print">
                                <?php if (!empty($exp['attachment_path'])): ?>
                                    <a href="<?php echo htmlspecialchars($exp['attachment_path']); ?>" target="_blank" class="attachment-link" title="Переглянути файл">
                                        <i class="bi bi-file-earmark-text"></i> Чек
                                    </a>
                                <?php else: ?>
                                    <span class="text-muted small">—</span>
                                <?php endif; ?>
                            </td>
                            <td class="no-print">
                                <a href="actions/delete_expense.php?id=<?php echo $exp['id']; ?>&device_id=<?php echo $exp['device_id']; ?>&redirect_to=repair_costs.php" class="btn btn-outline-danger btn-sm p-1 delete-expense-btn" title="Видалити запис">
                                    <i class="bi bi-trash3"></i>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    
    <!-- Pagination -->
    <?php if ($total_pages > 1): ?>
        <nav aria-label="Transaction table pagination" class="mt-3 no-print">
            <ul class="pagination pagination-sm justify-content-center gap-1">
                <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                    <a class="page-link border-0 rounded" href="repair_costs.php?page=<?php echo $page - 1 . $page_query; ?>">Назад</a>
                </li>
                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                    <li class="page-item <?php echo $page == $i ? 'active' : ''; ?>">
                        <a class="page-link border-0 rounded <?php echo $page == $i ? 'btn-custom-primary text-white' : 'text-dark bg-light'; ?>" href="repair_costs.php?page=<?php echo $i . $page_query; ?>"><?php echo $i; ?></a>
                    </li>
                <?php endfor; ?>
                <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                    <a class="page-link border-0 rounded" href="repair_costs.php?page=<?php echo $page + 1 . $page_query; ?>">Вперед</a>
                </li>
            </ul>
        </nav>
    <?php endif; ?>
</div>

<!-- Chart.js and logic -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Shared Chart Options
    const chartOptions = {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                display: false
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                grid: {
                    color: 'rgba(0, 0, 0, 0.05)'
                },
                ticks: {
                    font: {
                        family: 'Outfit'
                    }
                }
            },
            x: {
                grid: {
                    display: false
                },
                ticks: {
                    font: {
                        family: 'Outfit'
                    }
                }
            }
        }
    };

    // 1. Monthly Trend Chart
    <?php if (!empty($monthly_values)): ?>
    new Chart(document.getElementById('monthlyTrendChart'), {
        type: 'line',
        data: {
            labels: <?php echo json_encode($monthly_labels); ?>,
            datasets: [{
                data: <?php echo json_encode($monthly_values); ?>,
                borderColor: '#2d4a82',
                backgroundColor: 'rgba(45, 74, 130, 0.08)',
                borderWidth: 3,
                fill: true,
                tension: 0.35,
                pointBackgroundColor: '#2d4a82',
                pointRadius: 4
            }]
        },
        options: chartOptions
    });
    <?php endif; ?>

    // 2. Category Distribution Chart (Doughnut)
    <?php if (!empty($cat_values)): ?>
    new Chart(document.getElementById('categoryDistributionChart'), {
        type: 'doughnut',
        data: {
            labels: <?php echo json_encode($cat_labels); ?>,
            datasets: [{
                data: <?php echo json_encode($cat_values); ?>,
                backgroundColor: [
                    '#0ea5e9', // Part
                    '#8b5cf6', // Material
                    '#10b981', // Service
                    '#f59e0b', // Delivery
                    '#6b7280'  // Other
                ],
                borderWidth: 2,
                borderColor: '#ffffff'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: true,
                    position: 'bottom',
                    labels: {
                        boxWidth: 12,
                        font: {
                            family: 'Outfit',
                            size: 11
                        }
                    }
                }
            }
        }
    });
    <?php endif; ?>

    // 3. Location Expenses Chart (Bar)
    <?php if (!empty($loc_values)): ?>
    new Chart(document.getElementById('locationExpensesChart'), {
        type: 'bar',
        data: {
            labels: <?php echo json_encode($loc_labels); ?>,
            datasets: [{
                data: <?php echo json_encode($loc_values); ?>,
                backgroundColor: 'rgba(111, 66, 193, 0.85)',
                hoverBackgroundColor: '#6f42c1',
                borderRadius: 5
            }]
        },
        options: chartOptions
    });
    <?php endif; ?>
});
</script>

<?php if ($is_admin): ?>
<!-- Modal: Add Standalone Expense -->
<div class="modal fade" id="addExpenseModal" tabindex="-1" aria-labelledby="addExpenseModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-xl">
        <div class="modal-content modal-content-custom bg-white border border-opacity-10 shadow">
            <div class="modal-header modal-header-custom py-2 px-3">
                <h5 class="modal-title fs-6 text-gradient d-flex align-items-center gap-2" id="addExpenseModalLabel">
                    <i class="bi bi-cash-coin"></i> Додати витрати на обслуговування
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="actions/save_expense.php" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="redirect_to" value="repair_costs.php">
                <div class="modal-body py-4">
                    <div class="row g-3">
                        <!-- Global Fields for the Expense/Invoice -->
                        <div class="col-md-6">
                            <label class="form-label text-muted small mb-1">Пристрій / Обладнання *</label>
                            <select name="device_id" class="form-select form-control-custom" required>
                                <option value="">-- Оберіть пристрій --</option>
                                <?php foreach ($devices_dropdown as $dev): ?>
                                    <option value="<?php echo $dev['id']; ?>">
                                        <?php echo htmlspecialchars($dev['name'] . ' ' . $dev['model'] . ' [' . $dev['inventory_number'] . ']'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label text-muted small mb-1">Дата витрати *</label>
                            <input type="date" name="expense_date" class="form-control form-control-custom" value="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label text-muted small mb-1">Номер чека/накладної</label>
                            <input type="text" name="receipt_number" class="form-control form-control-custom" placeholder="№ документу">
                        </div>
                        <div class="col-md-12">
                            <label class="form-label text-muted small mb-1">Опис / Загальний коментар (необов'язково)</label>
                            <textarea name="description" class="form-control form-control-custom" rows="2" placeholder="Загальні деталі щодо ремонту..."></textarea>
                        </div>
                    </div>
                    
                    <hr class="border-secondary border-opacity-25 my-4">
                    
                    <!-- Dynamic Rows Section -->
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <span class="text-muted small fw-semibold"><i class="bi bi-list-stars"></i> Складові витрат (позиції):</span>
                        <button type="button" class="btn btn-sm btn-outline-primary d-inline-flex align-items-center gap-1 py-1 px-3" onclick="addStandaloneExpenseRow()">
                            <i class="bi bi-plus-circle"></i> Додати позицію
                        </button>
                    </div>
                    
                    <div id="standalone-expense-rows-placeholder" class="text-center py-4 text-muted small border rounded border-dashed" style="display:none;">
                        Натисніть «Додати позицію», щоб внести складові витрат.
                    </div>
                    
                    <div id="standalone-expense-rows-container"></div>
                    
                    <div class="text-end mt-3 pt-3 border-top border-secondary border-opacity-25">
                        <span class="text-muted small">Загальна сума витрат: </span>
                        <strong class="text-primary fs-5" id="standalone-expense-grand-total">0.00 грн</strong>
                    </div>
                </div>
                <div class="modal-footer modal-footer-custom py-2 px-3">
                    <button type="button" class="btn btn-sm btn-custom-secondary" data-bs-dismiss="modal">Скасувати</button>
                    <button type="submit" class="btn btn-sm btn-custom-primary">Зберегти витрати</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<?php 
require_once 'includes/footer.php';
?>
