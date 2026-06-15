<?php
// index.php
require_once 'config/db.php';

// Отримання статистичних даних
$stmt_total = $pdo->query("SELECT COUNT(*) FROM devices");
$total_devices = $stmt_total->fetchColumn();

$stmt_active = $pdo->query("SELECT COUNT(*) FROM devices WHERE status = 'в роботі'");
$active_devices = $stmt_active->fetchColumn();

$stmt_repair = $pdo->query("SELECT COUNT(*) FROM devices WHERE status = 'на ремонті'");
$repair_devices = $stmt_repair->fetchColumn();

$stmt_reserve = $pdo->query("SELECT COUNT(*) FROM devices WHERE status = 'в резерві'");
$reserve_devices = $stmt_reserve->fetchColumn();

// Отримання унікальних кабінетів та типів техніки для фільтрів
$locations_query = $pdo->query("SELECT DISTINCT location FROM devices WHERE location IS NOT NULL AND location != '' ORDER BY location");
$locations = $locations_query->fetchAll(PDO::FETCH_COLUMN);

$types_query = $pdo->query("SELECT DISTINCT name FROM devices WHERE name IS NOT NULL AND name != '' ORDER BY name");
$types = $types_query->fetchAll(PDO::FETCH_COLUMN);

// Параметри пошуку та фільтрації
$search = isset($_GET['q']) ? trim($_GET['q']) : '';
$filter_location = isset($_GET['location']) ? trim($_GET['location']) : '';
$filter_type = isset($_GET['type']) ? trim($_GET['type']) : '';
$filter_status = isset($_GET['status']) ? trim($_GET['status']) : '';
$report = isset($_GET['report']) ? trim($_GET['report']) : '';
$date_start = isset($_GET['date_start']) ? trim($_GET['date_start']) : '';
$date_end = isset($_GET['date_end']) ? trim($_GET['date_end']) : '';

// Базовий запит з LEFT JOIN для виведення IP адреси
$sql = "SELECT d.*, n.ip_address, n.subnet_mask, n.gateway, n.dns_server 
        FROM devices d 
        LEFT JOIN network_settings n ON d.id = n.device_id 
        WHERE 1=1";
$params = [];

// Додавання умов пошуку
if ($search !== '') {
    $sql .= " AND (d.name LIKE :search OR d.model LIKE :search OR d.serial_number LIKE :search OR d.inventory_number LIKE :search)";
    $params['search'] = "%$search%";
}

// Додавання фільтрів
if ($filter_location !== '') {
    $sql .= " AND d.location = :location";
    $params['location'] = $filter_location;
}

if ($filter_type !== '') {
    $sql .= " AND d.name = :type";
    $params['type'] = $filter_type;
}

if ($filter_status !== '') {
    $sql .= " AND d.status = :status";
    $params['status'] = $filter_status;
}

// Логіка швидких звітів
$report_title = '';
if ($report === 'repair') {
    $sql .= " AND d.status = 'на ремонті'";
    $report_title = "Звіт: Обладнання на ремонті";
} elseif ($report === 'ips') {
    $sql .= " AND n.ip_address IS NOT NULL AND n.ip_address != ''";
    $report_title = "Звіт: Зайняті IP-адреси";
} elseif ($report === 'period' && $date_start !== '' && $date_end !== '') {
    $sql .= " AND d.accepted_date BETWEEN :date_start AND :date_end";
    $params['date_start'] = $date_start;
    $params['date_end'] = $date_end;
    $report_title = "Звіт: Отримане обладнання з " . date('d.m.Y', strtotime($date_start)) . " по " . date('d.m.Y', strtotime($date_end));
}

// Сортування
$sql .= " ORDER BY d.id DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$devices = $stmt->fetchAll();

require_once 'includes/header.php';
?>

<!-- Панель статистики -->
<div class="row g-4 mb-5 no-print">
    <div class="col-md-3">
        <div class="glass-card widget-card h-100" style="--widget-rgb: 14, 165, 233;">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h6 class="text-muted text-uppercase mb-2">Всього обладнання</h6>
                    <h2 class="fw-bold mb-0 text-gradient"><?php echo $total_devices; ?></h2>
                </div>
                <div class="fs-1 text-info opacity-75">
                    <i class="bi bi-hdd-network-fill"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="glass-card widget-card h-100" style="--widget-rgb: 16, 185, 129;">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h6 class="text-muted text-uppercase mb-2">В роботі (Активні)</h6>
                    <h2 class="fw-bold mb-0 text-success"><?php echo $active_devices; ?></h2>
                </div>
                <div class="fs-1 text-success opacity-75">
                    <i class="bi bi-play-circle-fill"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="glass-card widget-card h-100" style="--widget-rgb: 245, 158, 11;">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h6 class="text-muted text-uppercase mb-2">На ремонті</h6>
                    <h2 class="fw-bold mb-0 text-warning"><?php echo $repair_devices; ?></h2>
                </div>
                <div class="fs-1 text-warning opacity-75">
                    <i class="bi bi-wrench-adjustable"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="glass-card widget-card h-100" style="--widget-rgb: 168, 85, 247;">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h6 class="text-muted text-uppercase mb-2">Резервний фонд</h6>
                    <h2 class="fw-bold mb-0 text-purple" style="color: var(--accent-purple);"><?php echo $reserve_devices; ?></h2>
                </div>
                <div class="fs-1 text-purple opacity-75" style="color: var(--accent-purple);">
                    <i class="bi bi-archive-fill"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Панель Фільтрів та Пошуку -->
<div class="glass-card mb-4 no-print">
    <div class="row align-items-center mb-3">
        <div class="col-md-6">
            <h4 class="mb-0 text-gradient-purple"><i class="bi bi-funnel-fill"></i> Фільтрація та пошук</h4>
        </div>
        <div class="col-md-6 text-md-end mt-2 mt-md-0">
            <button class="btn btn-custom-primary" data-bs-toggle="modal" data-bs-target="#addDeviceModal">
                <i class="bi bi-plus-lg"></i> Додати обладнання
            </button>
        </div>
    </div>
    
    <form method="GET" action="index.php" class="row g-3">
        <!-- Пошук по тексту -->
        <div class="col-md-3">
            <label class="form-label text-muted small">Пошук за ключовим словом</label>
            <div class="input-group">
                <span class="input-group-text bg-transparent border-secondary border-opacity-25 text-muted"><i class="bi bi-search"></i></span>
                <input type="text" name="q" class="form-control form-control-custom" placeholder="Назва, модель, S/N..." value="<?php echo htmlspecialchars($search); ?>">
            </div>
        </div>
        
        <!-- Фільтр локації -->
        <div class="col-md-3">
            <label class="form-label text-muted small">Аудиторія / Локація</label>
            <select name="location" class="form-select form-select-custom">
                <option value="">Всі локації</option>
                <?php foreach ($locations as $loc): ?>
                    <option value="<?php echo htmlspecialchars($loc); ?>" <?php echo ($filter_location === $loc) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($loc); ?>
                    </option>
                <?php endforeach; ?>
             </select>
        </div>
        
        <!-- Фільтр типу -->
        <div class="col-md-2">
            <label class="form-label text-muted small">Тип пристрою</label>
            <select name="type" class="form-select form-select-custom">
                <option value="">Всі типи</option>
                <?php foreach ($types as $t): ?>
                    <option value="<?php echo htmlspecialchars($t); ?>" <?php echo ($filter_type === $t) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($t); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <!-- Фільтр статусу -->
        <div class="col-md-2">
            <label class="form-label text-muted small">Статус</label>
            <select name="status" class="form-select form-select-custom">
                <option value="">Всі статуси</option>
                <option value="в роботі" <?php echo ($filter_status === 'в роботі') ? 'selected' : ''; ?>>В роботі</option>
                <option value="на ремонті" <?php echo ($filter_status === 'на ремонті') ? 'selected' : ''; ?>>На ремонті</option>
                <option value="в резерві" <?php echo ($filter_status === 'в резерві') ? 'selected' : ''; ?>>В резерві</option>
                <option value="списано" <?php echo ($filter_status === 'списано') ? 'selected' : ''; ?>>Списано</option>
            </select>
        </div>

        <!-- Кнопки пошуку -->
        <div class="col-md-2 d-flex align-items-end gap-2">
            <button type="submit" class="btn btn-custom-primary w-100">
                <i class="bi bi-filter"></i> Застосувати
            </button>
            <a href="index.php" class="btn btn-custom-secondary" title="Скинути фільтри">
                <i class="bi bi-x-circle"></i>
            </a>
        </div>
    </form>
</div>

<!-- Модуль 4: Швидкі Автозвіти (Ревізія) -->
<div class="glass-card mb-4 no-print">
    <h5 class="text-gradient-amber mb-3"><i class="bi bi-file-earmark-bar-graph-fill"></i> Модуль швидких автозвітів</h5>
    <div class="d-flex flex-wrap gap-2 align-items-center">
        <a href="index.php?report=repair" class="btn btn-sm btn-custom-secondary <?php echo ($report === 'repair') ? 'border-warning text-warning' : ''; ?>">
            <i class="bi bi-wrench-adjustable-circle"></i> Техніка на ремонті
        </a>
        <a href="index.php?report=ips" class="btn btn-sm btn-custom-secondary <?php echo ($report === 'ips') ? 'border-info text-info' : ''; ?>">
            <i class="bi bi-signpost-2-fill"></i> Зайняті IP-адреси
        </a>
        
        <!-- Звіт за обраний період -->
        <form method="GET" action="index.php" class="d-inline-flex gap-2 align-items-center ms-lg-3 flex-wrap">
            <input type="hidden" name="report" value="period">
            <span class="text-muted small">За період введення в експлуатацію:</span>
            <input type="date" name="date_start" class="form-control form-control-custom py-1 px-2 text-sm" value="<?php echo htmlspecialchars($date_start); ?>" required>
            <span class="text-muted small">по</span>
            <input type="date" name="date_end" class="form-control form-control-custom py-1 px-2 text-sm" value="<?php echo htmlspecialchars($date_end); ?>" required>
            <button type="submit" class="btn btn-sm btn-custom-primary py-1">Згенерувати</button>
        </form>
    </div>
</div>

<!-- Інформація про активний звіт -->
<?php if ($report_title !== ''): ?>
    <div class="alert alert-info bg-light border-info border-opacity-25 text-primary d-flex justify-content-between align-items-center mb-4 glass-card p-3 no-print">
        <div>
            <i class="bi bi-info-circle-fill me-2"></i>
            <strong><?php echo htmlspecialchars($report_title); ?></strong> (Знайдено записів: <?php echo count($devices); ?>)
        </div>
        <div class="d-flex gap-2">
            <button onclick="window.print()" class="btn btn-sm btn-custom-primary">
                <i class="bi bi-printer"></i> Друкувати звіт
            </button>
            <a href="index.php" class="btn btn-sm btn-custom-secondary">Закрити звіт</a>
        </div>
    </div>

    <!-- Заголовок для друкованої версії звіту -->
    <div class="print-header text-center mb-4">
        <h2>Кафедра комп'ютерних наук СумДУ</h2>
        <h4 class="text-secondary"><?php echo htmlspecialchars($report_title); ?></h4>
        <p class="text-muted small">Дата генерації звіту: <?php echo date('d.m.Y H:i'); ?> | Всього знайдено записів: <?php echo count($devices); ?></p>
        <hr>
    </div>
<?php endif; ?>

<!-- Таблиця списку пристроїв -->
<div class="glass-card">
    <div class="table-responsive table-responsive-custom">
        <table class="table table-custom table-hover">
            <thead>
                <tr>
                    <th>Інвентарний №</th>
                    <th>Назва / Модель</th>
                    <th>Локація</th>
                    <th>Мережевий IP</th>
                    <th>Статус</th>
                    <th>Відповідальна особа</th>
                    <th class="text-center">Дії</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($devices)): ?>
                    <tr>
                        <td colspan="7" class="text-center py-5 text-muted">
                            <i class="bi bi-search fs-1 mb-2 d-block"></i>
                            Пристроїв не знайдено за вказаними критеріями.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($devices as $dev): ?>
                        <tr>
                            <td class="fw-bold text-white"><?php echo htmlspecialchars($dev['inventory_number']); ?></td>
                            <td>
                                <div class="fw-semibold text-white"><?php echo htmlspecialchars($dev['name']); ?></div>
                                <div class="small text-muted"><?php echo htmlspecialchars($dev['model']); ?></div>
                            </td>
                            <td>
                                <i class="bi bi-geo-alt-fill text-muted me-1"></i>
                                <?php echo htmlspecialchars($dev['location']); ?>
                            </td>
                            <td>
                                <?php if ($dev['ip_address']): ?>
                                    <code class="text-info"><?php echo htmlspecialchars($dev['ip_address']); ?></code>
                                <?php else: ?>
                                    <span class="text-muted small">Не налаштовано</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php 
                                    $statusClass = 'badge-reserve';
                                    if ($dev['status'] === 'в роботі') $statusClass = 'badge-in-work';
                                    elseif ($dev['status'] === 'на ремонті') $statusClass = 'badge-repair';
                                    elseif ($dev['status'] === 'списано') $statusClass = 'badge-decommissioned';
                                ?>
                                <span class="badge-custom <?php echo $statusClass; ?>">
                                    <span class="pulsing-dot <?php 
                                        if ($dev['status'] === 'в роботі') echo 'pulsing-dot-success';
                                        elseif ($dev['status'] === 'на ремонті') echo 'pulsing-dot-warning';
                                        else echo 'pulsing-dot-danger';
                                    ?>"></span>
                                    <?php echo htmlspecialchars($dev['status']); ?>
                                </span>
                            </td>
                            <td>
                                <div class="small"><?php echo htmlspecialchars($dev['responsible_person']); ?></div>
                                <div class="text-muted" style="font-size: 0.75rem;">з <?php echo date('d.m.Y', strtotime($dev['accepted_date'])); ?></div>
                            </td>
                            <td class="text-center">
                                <div class="d-flex gap-2 justify-content-center">
                                    <a href="device_view.php?id=<?php echo $dev['id']; ?>" class="btn btn-sm btn-custom-secondary" title="Перегляд картки">
                                        <i class="bi bi-eye-fill text-info"></i> Картка
                                    </a>
                                    <button class="btn btn-sm btn-custom-secondary edit-device-btn" 
                                            data-id="<?php echo $dev['id']; ?>"
                                            data-inv="<?php echo htmlspecialchars($dev['inventory_number']); ?>"
                                            data-name="<?php echo htmlspecialchars($dev['name']); ?>"
                                            data-model="<?php echo htmlspecialchars($dev['model']); ?>"
                                            data-sn="<?php echo htmlspecialchars($dev['serial_number']); ?>"
                                            data-status="<?php echo htmlspecialchars($dev['status']); ?>"
                                            data-loc="<?php echo htmlspecialchars($dev['location']); ?>"
                                            data-date="<?php echo htmlspecialchars($dev['accepted_date']); ?>"
                                            data-resp="<?php echo htmlspecialchars($dev['responsible_person']); ?>"
                                            data-bs-toggle="modal" 
                                            data-bs-target="#editDeviceModal"
                                            title="Редагувати">
                                        <i class="bi bi-pencil-square text-warning"></i>
                                    </button>
                                    <a href="actions/delete_device.php?id=<?php echo $dev['id']; ?>" 
                                       class="btn btn-sm btn-custom-secondary" 
                                       onclick="return confirm('Ви впевнені, що хочете видалити цей пристрій та всі його налаштування й логи?');"
                                       title="Видалити">
                                        <i class="bi bi-trash3-fill text-danger"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- МОДАЛЬНЕ ВІКНО: Додати новий пристрій -->
<div class="modal fade" id="addDeviceModal" tabindex="-1" aria-labelledby="addDeviceModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content modal-content-custom">
            <div class="modal-header modal-header-custom">
                <h5 class="modal-title text-gradient" id="addDeviceModalLabel"><i class="bi bi-plus-circle-fill"></i> Додати нове обладнання</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="actions/save_device.php" method="POST">
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label text-muted small">Інвентарний номер *</label>
                            <input type="text" name="inventory_number" class="form-control form-control-custom" placeholder="Напр: INV-2026-0006" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-muted small">Серійний номер *</label>
                            <input type="text" name="serial_number" class="form-control form-control-custom" placeholder="Напр: SN-XYZ-1234" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-muted small">Назва / Клас обладнання *</label>
                            <input type="text" name="name" class="form-control form-control-custom" placeholder="Напр: Комутатор доступу" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-muted small">Модель *</label>
                            <input type="text" name="model" class="form-control form-control-custom" placeholder="Напр: Cisco Catalyst 2960" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-muted small">Поточний статус *</label>
                            <select name="status" class="form-select form-select-custom" required>
                                <option value="в резерві" selected>В резерві</option>
                                <option value="в роботі">В роботі</option>
                                <option value="на ремонті">На ремонті</option>
                                <option value="списано">Списано</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-muted small">Поточна локація *</label>
                            <input type="text" name="location" class="form-control form-control-custom" placeholder="Напр: Аудиторія 204" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-muted small">Дата прийняття *</label>
                            <input type="date" name="accepted_date" class="form-control form-control-custom" value="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-muted small">Відповідальна особа *</label>
                            <input type="text" name="responsible_person" class="form-control form-control-custom" placeholder="Напр: доц. Петренко І.В." required>
                        </div>
                        
                        <hr class="border-secondary border-opacity-25 my-4">
                        
                        <h6 class="text-info mb-2"><i class="bi bi-globe"></i> Первинні мережеві налаштування (необов'язково)</h6>
                        
                        <div class="col-md-6">
                            <label class="form-label text-muted small">IP-адреса</label>
                            <input type="text" name="ip_address" class="form-control form-control-custom validate-ip" placeholder="192.168.1.50">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-muted small">Маска підмережі</label>
                            <input type="text" name="subnet_mask" class="form-control form-control-custom validate-ip" placeholder="255.255.255.0">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-muted small">Основний шлюз</label>
                            <input type="text" name="gateway" class="form-control form-control-custom validate-ip" placeholder="192.168.1.1">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-muted small">DNS Сервер</label>
                            <input type="text" name="dns_server" class="form-control form-control-custom validate-ip" placeholder="8.8.8.8">
                        </div>
                    </div>
                </div>
                <div class="modal-footer modal-footer-custom">
                    <button type="button" class="btn btn-custom-secondary" data-bs-dismiss="modal">Скасувати</button>
                    <button type="submit" class="btn btn-custom-primary">Зберегти пристрій</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- МОДАЛЬНЕ ВІКНО: Редагувати пристрій -->
<div class="modal fade" id="editDeviceModal" tabindex="-1" aria-labelledby="editDeviceModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content modal-content-custom">
            <div class="modal-header modal-header-custom">
                <h5 class="modal-title text-gradient-amber" id="editDeviceModalLabel"><i class="bi bi-pencil-square"></i> Редагувати інформацію про обладнання</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="actions/save_device.php" method="POST">
                <!-- ID пристрою, який редагується -->
                <input type="hidden" name="device_id" id="edit-id">
                
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label text-muted small">Інвентарний номер *</label>
                            <input type="text" name="inventory_number" id="edit-inv" class="form-control form-control-custom" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-muted small">Серійний номер *</label>
                            <input type="text" name="serial_number" id="edit-sn" class="form-control form-control-custom" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-muted small">Назва / Клас обладнання *</label>
                            <input type="text" name="name" id="edit-name" class="form-control form-control-custom" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-muted small">Модель *</label>
                            <input type="text" name="model" id="edit-model" class="form-control form-control-custom" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-muted small">Поточний статус *</label>
                            <select name="status" id="edit-status" class="form-select form-select-custom" required>
                                <option value="в резерві">В резерві</option>
                                <option value="в роботі">В роботі</option>
                                <option value="на ремонті">На ремонті</option>
                                <option value="списано">Списано</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-muted small">Поточна локація *</label>
                            <input type="text" name="location" id="edit-loc" class="form-control form-control-custom" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-muted small">Дата прийняття *</label>
                            <input type="date" name="accepted_date" id="edit-date" class="form-control form-control-custom" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-muted small">Відповідальна особа *</label>
                            <input type="text" name="responsible_person" id="edit-resp" class="form-control form-control-custom" required>
                        </div>
                    </div>
                </div>
                <div class="modal-footer modal-footer-custom">
                    <button type="button" class="btn btn-custom-secondary" data-bs-dismiss="modal">Скасувати</button>
                    <button type="submit" class="btn btn-custom-primary">Зберегти зміни</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Обробник для динамічного заповнення форми редагування
    const editButtons = document.querySelectorAll('.edit-device-btn');
    editButtons.forEach(btn => {
        btn.addEventListener('click', function() {
            document.getElementById('edit-id').value = this.getAttribute('data-id');
            document.getElementById('edit-inv').value = this.getAttribute('data-inv');
            document.getElementById('edit-name').value = this.getAttribute('data-name');
            document.getElementById('edit-model').value = this.getAttribute('data-model');
            document.getElementById('edit-sn').value = this.getAttribute('data-sn');
            document.getElementById('edit-status').value = this.getAttribute('data-status');
            document.getElementById('edit-loc').value = this.getAttribute('data-loc');
            document.getElementById('edit-date').value = this.getAttribute('data-date');
            document.getElementById('edit-resp').value = this.getAttribute('data-resp');
        });
    });
});
</script>

<?php 
require_once 'includes/footer.php';
?>
