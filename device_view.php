<?php
require_once 'config/db.php';

$device_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($device_id <= 0) {
    header("Location: index.php?error=invalid_id");
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT * FROM devices WHERE id = ?");
    $stmt->execute([$device_id]);
    $device = $stmt->fetch();

    if (!$device) {
        header("Location: index.php?error=not_found");
        exit;
    }

    $stmt_net = $pdo->prepare("SELECT * FROM network_settings WHERE device_id = ?");
    $stmt_net->execute([$device_id]);
    $net_settings = $stmt_net->fetch();

    $stmt_history = $pdo->prepare("SELECT * FROM device_history WHERE device_id = ? ORDER BY event_date DESC");
    $stmt_history->execute([$device_id]);
    $history_records = $stmt_history->fetchAll();

    $stmt_net_logs = $pdo->prepare("SELECT * FROM network_history_and_logs WHERE device_id = ? ORDER BY log_date DESC");
    $stmt_net_logs->execute([$device_id]);
    $net_logs = $stmt_net_logs->fetchAll();

    $stmt_specs = $pdo->prepare("SELECT * FROM device_specifications WHERE device_id = ?");
    $stmt_specs->execute([$device_id]);
    $specs = $stmt_specs->fetch();

    $stmt_tickets = $pdo->prepare("
        SELECT t.*, u.full_name AS reporter_name, u.role AS reporter_role 
        FROM tickets t 
        JOIN users u ON t.user_id = u.id 
        WHERE t.device_id = ? 
        ORDER BY t.created_at DESC
    ");
    $stmt_tickets->execute([$device_id]);
    $device_tickets = $stmt_tickets->fetchAll();

    $stmt_expenses = $pdo->prepare("
        SELECT re.*, u.full_name AS added_by_name
        FROM repair_expenses re
        LEFT JOIN users u ON re.created_by = u.id
        WHERE re.device_id = ?
        ORDER BY re.expense_date DESC, re.id DESC
    ");
    $stmt_expenses->execute([$device_id]);
    $device_expenses = $stmt_expenses->fetchAll();
    $total_expense_sum = array_sum(array_column($device_expenses, 'total_price'));

} catch (\PDOException $e) {
    die("Помилка бази даних: " . htmlspecialchars($e->getMessage()));
}

$is_admin = (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin');
require_once 'includes/header.php';
?>

<div class="mb-4">
    <a href="index.php" class="btn btn-custom-secondary d-inline-flex align-items-center gap-2">
        <i class="bi bi-arrow-left-short fs-5"></i> Назад до списку
    </a>
</div>

<?php if (isset($_GET['success'])): ?>
    <div class="alert alert-success p-3 mb-4 animate__animated animate__fadeIn">
        <i class="bi bi-check-circle-fill me-2"></i>
        <?php 
            if ($_GET['success'] === 'net_updated') echo 'Мережеві налаштування успішно оновлено!';
            elseif ($_GET['success'] === 'rolled_back') echo 'Параметри мережі успішно відновлено з архівного логу!';
            elseif ($_GET['success'] === 'specs_updated') echo 'Характеристики заліза успішно оновлено!';
            elseif ($_GET['success'] === 'ticket_created') echo 'Заявку на обслуговування успішно створено та надіслано адміністратору!';
            elseif ($_GET['success'] === 'ticket_taken') echo 'Заявку успішно взято в роботу, пристрій переведено в статус «На ремонті»!';
            elseif ($_GET['success'] === 'ticket_closed') echo 'Заявку успішно виконано, пристрій повернуто до роботи!';
            elseif ($_GET['success'] === 'ticket_rejected') echo 'Заявку відхилено, пристрій повернуто до роботи!';
        ?>
    </div>
<?php endif; ?>

<?php if (isset($_GET['error'])): ?>
    <div class="alert alert-danger p-3 mb-4">
        <i class="bi bi-exclamation-triangle-fill me-2"></i>
        <strong>Помилка: </strong>
        <?php 
            if ($_GET['error'] === 'ip_conflict') {
                $conflicting_inv = isset($_GET['conflicting_inv']) ? htmlspecialchars($_GET['conflicting_inv']) : 'іншим пристроєм';
                echo 'Конфлікт мережі! Вказана IP-адреса вже використовується активним обладнанням з інвентарним номером: <strong>' . $conflicting_inv . '</strong>. Спроба зміни логувалася.';
            } elseif ($_GET['error'] === 'mac_conflict') {
                $conflicting_inv = isset($_GET['conflicting_inv']) ? htmlspecialchars($_GET['conflicting_inv']) : 'іншим пристроєм';
                echo 'Конфлікт MAC-адреси! Вказана фізична адреса вже призначена активному обладнанню з інвентарним номером: <strong>' . $conflicting_inv . '</strong>.';
            } elseif ($_GET['error'] === 'invalid_ip') {
                echo 'Некоректний формат IP-адреси.';
            } elseif ($_GET['error'] === 'db_error') {
                echo 'Помилка бази даних: ' . htmlspecialchars($_GET['msg']);
            } elseif ($_GET['error'] === 'access_denied') {
                echo 'Доступ заборонено! Тільки адміністратори мають право виконувати цю операцію.';
            } else {
                echo 'Неможливо оновити мережеві налаштування.';
            }
        ?>
    </div>
<?php endif; ?>

<div class="row g-4">
    <div class="col-12">
        <div class="glass-card">
            
            <!-- Header section inside card -->
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 border-bottom border-secondary border-opacity-10 pb-3 mb-4">
                <div>
                    <?php 
                        $statusClass = 'badge-reserve';
                        if ($device['status'] === 'в роботі') $statusClass = 'badge-in-work';
                        elseif ($device['status'] === 'очікує перевірки') $statusClass = 'badge-pending';
                        elseif ($device['status'] === 'на ремонті') $statusClass = 'badge-repair';
                        elseif ($device['status'] === 'списано') $statusClass = 'badge-decommissioned';
                    ?>
                    <span class="badge-custom <?php echo $statusClass; ?> mb-2 d-inline-block">
                        <span class="pulsing-dot <?php 
                            if ($device['status'] === 'в роботі') echo 'pulsing-dot-success';
                            elseif ($device['status'] === 'очікує перевірки') echo 'pulsing-dot-info';
                            elseif ($device['status'] === 'на ремонті') echo 'pulsing-dot-warning';
                            else echo 'pulsing-dot-danger';
                        ?>"></span>
                        <?php echo htmlspecialchars($device['status']); ?>
                    </span>
                    <h2 class="fw-bold text-dark mb-1"><?php echo htmlspecialchars($device['name']); ?></h2>
                    <div class="text-muted small">
                        Модель: <span class="text-dark fw-semibold"><?php echo htmlspecialchars($device['model']); ?></span> | 
                        Серійний №: <span class="text-dark fw-semibold"><?php echo htmlspecialchars($device['serial_number']); ?></span> | 
                        Інвентарний №: <span class="text-dark fw-semibold"><?php echo htmlspecialchars($device['inventory_number']); ?></span>
                    </div>
                </div>
                
                <div class="d-flex flex-wrap gap-2">
                    <?php if ($is_admin): ?>
                        <button class="btn btn-sm btn-warning fw-semibold text-dark d-inline-flex align-items-center gap-2 px-3 py-2" data-bs-toggle="modal" data-bs-target="#editDeviceModal">
                            <i class="bi bi-pencil-square fs-6"></i> Редагувати
                        </button>
                        <a href="actions/delete_device.php?id=<?php echo $device['id']; ?>" class="btn btn-sm btn-danger fw-semibold text-white d-inline-flex align-items-center gap-2 px-3 py-2 delete-device-btn">
                            <i class="bi bi-trash3-fill fs-6"></i> Видалити
                        </a>
                    <?php endif; ?>
                    
                    <?php if ($device['status'] === 'на ремонті'): ?>
                        <span class="btn btn-sm btn-outline-warning disabled px-3 py-2"><i class="bi bi-info-circle-fill"></i> Пристрій на ремонті</span>
                    <?php elseif ($device['status'] === 'очікує перевірки'): ?>
                        <span class="btn btn-sm btn-outline-info disabled px-3 py-2"><i class="bi bi-info-circle-fill"></i> Очікує перевірки</span>
                    <?php elseif ($device['status'] === 'списано'): ?>
                        <span class="btn btn-sm btn-outline-danger disabled px-3 py-2"><i class="bi bi-x-circle-fill"></i> Списано</span>
                    <?php else: ?>
                        <button type="button" class="btn btn-sm btn-danger fw-semibold d-inline-flex align-items-center gap-2 px-3 py-2" data-bs-toggle="modal" data-bs-target="#ticketModal">
                            <i class="bi bi-exclamation-triangle-fill fs-6"></i> Повідомити про проблему
                        </button>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Tabs Navigation -->
            <ul class="nav nav-tabs border-secondary border-opacity-10 mb-4" id="logTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active py-2" id="general-info-tab" data-bs-toggle="tab" data-bs-target="#general-info" type="button" role="tab" aria-controls="general-info" aria-selected="true">
                        <i class="bi bi-info-circle text-primary"></i> Параметри та Мережа
                    </button>
                </li>
                <?php if (isset($device['is_pc']) && $device['is_pc'] == 1): ?>
                <li class="nav-item" role="presentation">
                    <button class="nav-link py-2" id="device-specs-tab" data-bs-toggle="tab" data-bs-target="#device-specs" type="button" role="tab" aria-controls="device-specs" aria-selected="false">
                        <i class="bi bi-cpu-fill text-warning"></i> Характеристики заліза
                    </button>
                </li>
                <?php endif; ?>
                <li class="nav-item" role="presentation">
                    <button class="nav-link py-2" id="net-logs-tab" data-bs-toggle="tab" data-bs-target="#net-logs" type="button" role="tab" aria-controls="net-logs" aria-selected="false">
                        <i class="bi bi-activity text-info"></i> Логи мережі та сесії
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link py-2" id="device-history-tab" data-bs-toggle="tab" data-bs-target="#device-history" type="button" role="tab" aria-controls="device-history" aria-selected="false">
                        <i class="bi bi-journal-text text-purple"></i> Життєвий цикл
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link py-2" id="device-tickets-tab" data-bs-toggle="tab" data-bs-target="#device-tickets" type="button" role="tab" aria-controls="device-tickets" aria-selected="false">
                        <i class="bi bi-wrench-adjustable text-danger"></i> Заявки на ремонт (<?php echo count($device_tickets); ?>)
                    </button>
                </li>
                <?php if ($is_admin): ?>
                <li class="nav-item" role="presentation">
                    <button class="nav-link py-2 <?php echo (isset($_GET['tab']) && $_GET['tab'] === 'expenses') ? 'active' : ''; ?>" id="device-expenses-tab" data-bs-toggle="tab" data-bs-target="#device-expenses" type="button" role="tab" aria-controls="device-expenses" aria-selected="false">
                        <i class="bi bi-currency-dollar text-success"></i> Витрати на ремонт <?php if ($total_expense_sum > 0): ?><span class="badge bg-success bg-opacity-20 text-success ms-1" style="font-size:0.68rem;"><?php echo number_format($total_expense_sum, 2, '.', ' '); ?> грн</span><?php endif; ?>
                    </button>
                </li>
                <?php endif; ?>
            </ul>

            <div class="tab-content" id="logTabsContent">
                
                <!-- Tab 1: General Info & Network Configuration -->
                <div class="tab-pane fade show active" id="general-info" role="tabpanel" aria-labelledby="general-info-tab">
                    <div class="row g-4">
                        <!-- Left Side: General Details & QR -->
                        <div class="col-md-6 border-end border-secondary border-opacity-10 pe-md-4">
                            <h6 class="text-secondary mb-3 small text-uppercase tracking-wider fw-bold">Відомості про обладнання</h6>
                            <table class="table table-borderless text-muted small mb-4">
                                <tr>
                                    <td class="ps-0 py-2" style="width: 40%;">Поточне розташування:</td>
                                    <td class="text-dark py-2 fw-semibold">
                                        <i class="bi bi-geo-alt-fill text-info me-1"></i>
                                        <?php echo htmlspecialchars($device['location'] ?: 'Не вказано'); ?>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="ps-0 py-2">Матеріально відповідальний:</td>
                                    <td class="text-dark py-2 fw-semibold"><?php echo htmlspecialchars($device['responsible_person'] ?: 'Не вказано'); ?></td>
                                </tr>
                                <tr>
                                    <td class="ps-0 py-2">В експлуатації з:</td>
                                    <td class="text-dark py-2"><?php echo date('d.m.Y', strtotime($device['accepted_date'])); ?></td>
                                </tr>
                                <?php if ($net_settings && !empty($net_settings['mac_address'])): ?>
                                <tr>
                                    <td class="ps-0 py-2">Фізична MAC-адреса:</td>
                                    <td class="text-dark py-2"><code><?php echo htmlspecialchars($net_settings['mac_address']); ?></code></td>
                                </tr>
                                <?php endif; ?>
                            </table>

                            <div class="d-flex align-items-center gap-4 bg-light p-3 rounded border border-secondary border-opacity-10 mt-3">
                                <?php
                                    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
                                    $host = $_SERVER['HTTP_HOST'];
                                    $device_url = $protocol . $host . "/it-dep-inventory/device_view.php?id=" . $device['id'];
                                    $qr_api_url = "https://api.qrserver.com/v1/create-qr-code/?size=120x120&data=" . urlencode($device_url);
                                ?>
                                <div class="p-2 bg-white rounded border border-secondary border-opacity-10">
                                    <img src="<?php echo $qr_api_url; ?>" alt="QR Code" style="width: 100px; height: 100px;">
                                </div>
                                <div>
                                    <h6 class="text-secondary mb-1 small text-uppercase tracking-wider fw-bold">QR-код маркування</h6>
                                    <p class="text-muted small mb-2">Призначений для швидкої ідентифікації пристрою на місці.</p>
                                    <button onclick="printInventoryTag()" class="btn btn-sm btn-custom-secondary px-3">
                                        <i class="bi bi-printer"></i> Друкувати етикетку
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Right Side: Network Settings Form -->
                        <div class="col-md-6 ps-md-4">
                            <h6 class="text-secondary mb-3 small text-uppercase tracking-wider fw-bold">Конфігурація мережевого підключення</h6>
                            <form action="actions/update_net.php" method="POST">
                                <input type="hidden" name="device_id" value="<?php echo $device['id']; ?>">
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label class="form-label text-muted small mb-1">IP-адреса</label>
                                        <input type="text" name="ip_address" id="net-ip" class="form-control form-control-custom validate-ip" placeholder="Напр: 192.168.1.15" value="<?php echo htmlspecialchars($net_settings['ip_address'] ?? ''); ?>" <?php echo !$is_admin ? 'disabled' : ''; ?>>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label text-muted small mb-1">MAC-адреса</label>
                                        <input type="text" name="mac_address" id="net-mac" class="form-control form-control-custom" placeholder="Напр: 00:1A:2B:3C:4D:5E" value="<?php echo htmlspecialchars($net_settings['mac_address'] ?? ''); ?>" <?php echo !$is_admin ? 'disabled' : ''; ?>>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label text-muted small mb-1">Маска підмережі</label>
                                        <input type="text" name="subnet_mask" id="net-mask" class="form-control form-control-custom validate-ip" placeholder="Напр: 255.255.255.0" value="<?php echo htmlspecialchars($net_settings['subnet_mask'] ?? ''); ?>" <?php echo !$is_admin ? 'disabled' : ''; ?>>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label text-muted small mb-1">Основний шлюз</label>
                                        <input type="text" name="gateway" id="net-gateway" class="form-control form-control-custom validate-ip" placeholder="Напр: 192.168.1.1" value="<?php echo htmlspecialchars($net_settings['gateway'] ?? ''); ?>" <?php echo !$is_admin ? 'disabled' : ''; ?>>
                                    </div>
                                    <div class="col-md-12">
                                        <label class="form-label text-muted small mb-1">DNS Сервер</label>
                                        <input type="text" name="dns_server" id="net-dns" class="form-control form-control-custom validate-ip" placeholder="Напр: 8.8.8.8" value="<?php echo htmlspecialchars($net_settings['dns_server'] ?? ''); ?>" <?php echo !$is_admin ? 'disabled' : ''; ?>>
                                    </div>
                                    
                                    <?php if ($is_admin): ?>
                                    <div class="col-12 mt-4 text-end">
                                        <button type="submit" class="btn btn-sm btn-custom-primary px-4 py-2">
                                            <i class="bi bi-save2"></i> Застосувати зміни
                                        </button>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Tab 2: Hardware Specifications -->
                <?php if (isset($device['is_pc']) && $device['is_pc'] == 1): ?>
                <div class="tab-pane fade" id="device-specs" role="tabpanel" aria-labelledby="device-specs-tab">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h6 class="text-secondary mb-0 text-uppercase fw-bold" style="letter-spacing: 0.06em; font-size: 0.85rem;">Характеристики та комплектуючі</h6>
                        <button class="btn btn-sm btn-custom-secondary" id="edit-specs-btn" onclick="toggleSpecsEdit()">
                            <i class="bi bi-pencil-square text-warning"></i> Редагувати
                        </button>
                    </div>

                    <!-- View Specs Mode -->
                    <div id="specs-view-mode">
                        <div class="table-responsive table-responsive-custom">
                            <table class="table table-custom mb-0">
                                <tbody>
                                    <tr>
                                        <td class="fw-semibold text-muted" style="width: 35%;">Процесор (CPU)</td>
                                        <td class="text-dark"><?php echo htmlspecialchars($specs['cpu'] ?? 'Не вказано'); ?></td>
                                    </tr>
                                    <tr>
                                        <td class="fw-semibold text-muted">Оперативна пам'ять (RAM)</td>
                                        <td class="text-dark"><?php echo htmlspecialchars($specs['ram'] ?? 'Не вказано'); ?></td>
                                    </tr>
                                    <tr>
                                        <td class="fw-semibold text-muted">Накопичувач (Storage SSD/HDD)</td>
                                        <td class="text-dark"><?php echo htmlspecialchars($specs['storage'] ?? 'Не вказано'); ?></td>
                                    </tr>
                                    <tr>
                                        <td class="fw-semibold text-muted">Відеокарта (GPU)</td>
                                        <td class="text-dark"><?php echo htmlspecialchars($specs['gpu'] ?? 'Не вказано'); ?></td>
                                    </tr>
                                    <tr>
                                        <td class="fw-semibold text-muted">Операційна система (OS)</td>
                                        <td class="text-dark"><?php echo htmlspecialchars($specs['os'] ?? 'Не вказано'); ?></td>
                                    </tr>
                                    <tr>
                                        <td class="fw-semibold text-muted">Материнська плата</td>
                                        <td class="text-dark"><?php echo htmlspecialchars($specs['motherboard'] ?? 'Не вказано'); ?></td>
                                    </tr>
                                    <tr>
                                        <td class="fw-semibold text-muted">Блок живлення (PSU)</td>
                                        <td class="text-dark"><?php echo htmlspecialchars($specs['power_supply'] ?? 'Не вказано'); ?></td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Edit Specs Mode (Hidden by default) -->
                    <div id="specs-edit-mode" class="d-none">
                        <form action="actions/save_specs.php" method="POST">
                            <input type="hidden" name="device_id" value="<?php echo $device['id']; ?>">
                            
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label text-muted small">Процесор (CPU)</label>
                                    <input type="text" name="cpu" class="form-control form-control-custom filter-row-input" placeholder="Напр: Intel Core i5-12400" value="<?php echo htmlspecialchars($specs['cpu'] ?? ''); ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label text-muted small">Оперативна пам'ять (RAM)</label>
                                    <input type="text" name="ram" class="form-control form-control-custom filter-row-input" placeholder="Напр: 16GB DDR4 3200MHz" value="<?php echo htmlspecialchars($specs['ram'] ?? ''); ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label text-muted small">Накопичувач (Storage SSD/HDD)</label>
                                    <input type="text" name="storage" class="form-control form-control-custom filter-row-input" placeholder="Напр: 512GB NVMe SSD" value="<?php echo htmlspecialchars($specs['storage'] ?? ''); ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label text-muted small">Відеокарта (GPU)</label>
                                    <input type="text" name="gpu" class="form-control form-control-custom filter-row-input" placeholder="Напр: NVIDIA RTX 3060" value="<?php echo htmlspecialchars($specs['gpu'] ?? ''); ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label text-muted small">Операційна система (OS)</label>
                                    <input type="text" name="os" class="form-control form-control-custom filter-row-input" placeholder="Напр: Windows 11 Pro" value="<?php echo htmlspecialchars($specs['os'] ?? ''); ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label text-muted small">Материнська плата</label>
                                    <input type="text" name="motherboard" class="form-control form-control-custom filter-row-input" placeholder="Напр: ASUS PRIME B660M" value="<?php echo htmlspecialchars($specs['motherboard'] ?? ''); ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label text-muted small">Блок живлення (PSU)</label>
                                    <input type="text" name="power_supply" class="form-control form-control-custom filter-row-input" placeholder="Напр: Corsair CV650 650W" value="<?php echo htmlspecialchars($specs['power_supply'] ?? ''); ?>">
                                </div>
                                <div class="col-md-12 d-flex gap-2 justify-content-end mt-4">
                                    <button type="button" class="btn btn-custom-secondary btn-sm" onclick="toggleSpecsEdit()">Скасувати</button>
                                    <button type="submit" class="btn btn-custom-primary btn-sm px-3">Зберегти специфікацію</button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Tab 3: Network Logs -->
                <div class="tab-pane fade" id="net-logs" role="tabpanel" aria-labelledby="net-logs-tab">
                    <h6 class="text-secondary mb-3 text-uppercase fw-bold" style="letter-spacing: 0.06em; font-size: 0.85rem;">Мережева історія</h6>
                    <?php if (empty($net_logs)): ?>
                        <div class="text-center py-5 text-muted">
                            <i class="bi bi-journal-x fs-1 mb-2 d-block"></i>
                            Логи для цього пристрою ще не створювалися.
                        </div>
                    <?php else: ?>
                        <div class="table-responsive table-responsive-custom">
                            <table class="table table-custom table-hover small">
                                <thead>
                                    <tr>
                                        <th>Дата запису</th>
                                        <th>Користувач</th>
                                        <th>Тип запису</th>
                                        <th>Конфігурація IP</th>
                                        <th>Статус</th>
                                        <th class="text-center">Дія</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($net_logs as $log): ?>
                                        <tr>
                                            <td><?php echo date('d.m.Y H:i:s', strtotime($log['log_date'])); ?></td>
                                            <td><code><?php echo htmlspecialchars($log['admin_name']); ?></code></td>
                                            <td>
                                                <?php if ($log['log_type'] === 'зміна налаштувань'): ?>
                                                    <span class="text-info"><i class="bi bi-gear-fill"></i> Зміна конфіг.</span>
                                                <?php elseif ($log['log_type'] === 'збій зв\'язку'): ?>
                                                    <span class="text-danger"><i class="bi bi-wifi-off"></i> Збій зв'язку</span>
                                                <?php elseif ($log['log_type'] === 'відновлення'): ?>
                                                    <span class="text-success"><i class="bi bi-arrow-counterclockwise"></i> Відновлення</span>
                                                <?php else: ?>
                                                    <span class="text-warning"><i class="bi bi-exclamation-octagon-fill"></i> Конфлікт</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($log['ip_address']): ?>
                                                    <div><strong>IP:</strong> <?php echo htmlspecialchars($log['ip_address']); ?></div>
                                                    <div class="text-muted" style="font-size: 0.75rem;"><strong>Mask:</strong> <?php echo htmlspecialchars($log['subnet_mask']); ?></div>
                                                <?php else: ?>
                                                    <span class="text-muted">Немає IP</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php 
                                                    $statusBadge = 'bg-secondary text-white';
                                                    if ($log['session_status'] === 'успішно') $statusBadge = 'badge-in-work';
                                                    elseif ($log['session_status'] === 'конфлікт') $statusBadge = 'badge-repair';
                                                    elseif ($log['session_status'] === 'збій') $statusBadge = 'badge-decommissioned';
                                                ?>
                                                <span class="badge-custom <?php echo $statusBadge; ?>" style="font-size: 0.7rem; padding: 0.3rem 0.5rem;">
                                                    <?php echo htmlspecialchars($log['session_status']); ?>
                                                </span>
                                            </td>
                                            <td class="text-center">
                                                <?php if ($log['log_type'] !== 'збій зв\'язку' && $log['ip_address'] !== null): ?>
                                                    <a href="actions/rollback_net.php?log_id=<?php echo $log['id']; ?>&device_id=<?php echo $device_id; ?>" 
                                                       class="btn btn-sm btn-outline-info py-0 px-2"
                                                       onclick="return confirm('Ви впевнені, що хочете відновити налаштування мережі з цієї сесії? Поточні налаштування буде змінено.');"
                                                       title="Відновити налаштування з цієї сесії">
                                                        <i class="bi bi-arrow-counterclockwise"></i> Відновити
                                                    </a>
                                                <?php else: ?>
                                                    <span class="text-muted small">-</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Tab 4: Lifecycle History -->
                <div class="tab-pane fade" id="device-history" role="tabpanel" aria-labelledby="device-history-tab">
                    <h6 class="text-secondary mb-3 text-uppercase fw-bold" style="letter-spacing: 0.06em; font-size: 0.85rem;">Історія переміщень, ремонтів та життєвого циклу</h6>
                    <?php if (empty($history_records)): ?>
                        <div class="text-center py-5 text-muted">
                            <i class="bi bi-clock-history fs-1 mb-2 d-block"></i>
                            Жодної події життєвого циклу не зафіксовано.
                        </div>
                    <?php else: ?>
                        <div class="table-responsive table-responsive-custom">
                            <table class="table table-custom table-hover small">
                                <thead>
                                    <tr>
                                        <th>Дата події</th>
                                        <th>Тип події</th>
                                        <th>Старе значення</th>
                                        <th>Нове значення</th>
                                        <th>Відповідальна особа</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($history_records as $rec): ?>
                                        <tr>
                                            <td><?php echo date('d.m.Y H:i:s', strtotime($rec['event_date'])); ?></td>
                                            <td>
                                                <?php if ($rec['event_type'] === 'створення'): ?>
                                                    <span class="badge bg-success bg-opacity-25 text-success border border-success border-opacity-50">Створення</span>
                                                <?php elseif ($rec['event_type'] === 'переміщення'): ?>
                                                    <span class="badge bg-info bg-opacity-25 text-info border border-info border-opacity-50">Переміщення</span>
                                                <?php elseif ($rec['event_type'] === 'зміна статусу'): ?>
                                                    <span class="badge bg-warning bg-opacity-25 text-warning border border-warning border-opacity-50">Статус</span>
                                                <?php else: ?>
                                                    <span class="badge bg-secondary bg-opacity-25 text-white border border-secondary border-opacity-50">Зміна</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><span class="text-muted"><?php echo htmlspecialchars($rec['old_value'] ?? '-'); ?></span></td>
                                            <td class="text-dark"><strong><?php echo htmlspecialchars($rec['new_value'] ?? '-'); ?></strong></td>
                                            <td><small class="text-muted"><?php echo htmlspecialchars($rec['responsible_person']); ?></small></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Tab 5: Maintenance Tickets -->
                <div class="tab-pane fade" id="device-tickets" role="tabpanel" aria-labelledby="device-tickets-tab">
                    <h6 class="text-secondary mb-3 text-uppercase fw-bold" style="letter-spacing: 0.06em; font-size: 0.85rem;">Історія заявок на обслуговування</h6>
                    <?php if (empty($device_tickets)): ?>
                        <div class="text-center py-5 text-muted">
                            <i class="bi bi-clipboard-x fs-1 mb-2 d-block"></i>
                            Заявки на обслуговування для цього пристрою відсутні.
                        </div>
                    <?php else: ?>
                        <div class="table-responsive table-responsive-custom">
                            <table class="table table-custom table-hover small mb-0">
                                <thead>
                                    <tr>
                                        <th>Дата</th>
                                        <th>Ініціатор</th>
                                        <th>Опис проблеми</th>
                                        <th>Статус</th>
                                        <th>Коментар адміна</th>
                                        <?php if ($is_admin): ?>
                                            <th class="text-end text-nowrap" style="width: 180px;">Дії</th>
                                        <?php endif; ?>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($device_tickets as $ticket): ?>
                                        <tr>
                                            <td><span class="text-muted d-block small"><?php echo date('d.m.Y H:i', strtotime($ticket['created_at'])); ?></span></td>
                                            <td>
                                                <strong><?php echo htmlspecialchars($ticket['reporter_name']); ?></strong>
                                                <span class="d-block small text-muted"><?php echo ($ticket['reporter_role'] === 'admin') ? 'Адмін' : 'Викладач'; ?></span>
                                            </td>
                                            <td><div class="text-wrap" style="max-width: 250px;"><?php echo htmlspecialchars($ticket['description']); ?></div></td>
                                            <td>
                                                <?php 
                                                    $ticketStatusClass = 'bg-secondary text-white';
                                                    if ($ticket['status'] === 'нова') $ticketStatusClass = 'badge-pending';
                                                    elseif ($ticket['status'] === 'в роботі') $ticketStatusClass = 'badge-repair';
                                                    elseif ($ticket['status'] === 'виконана') $ticketStatusClass = 'badge-in-work';
                                                    elseif ($ticket['status'] === 'відхилена') $ticketStatusClass = 'badge-decommissioned';
                                                ?>
                                                <span class="badge-custom <?php echo $ticketStatusClass; ?>" style="font-size: 0.7rem; padding: 0.3rem 0.5rem;">
                                                    <?php echo htmlspecialchars($ticket['status']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div class="text-wrap small text-muted" style="max-width: 200px;">
                                                    <?php echo !empty($ticket['comment']) ? htmlspecialchars($ticket['comment']) : '<em>Немає коментаря</em>'; ?>
                                                </div>
                                            </td>
                                            <?php if ($is_admin): ?>
                                                <td class="text-end text-nowrap">
                                                    <div class="d-flex gap-2 justify-content-end align-items-center">
                                                        <?php if ($ticket['status'] === 'нова'): ?>
                                                            <form action="actions/update_ticket.php" method="POST" class="m-0 d-inline-block">
                                                                <input type="hidden" name="ticket_id" value="<?php echo $ticket['id']; ?>">
                                                                <input type="hidden" name="action" value="take_job">
                                                                <button type="submit" class="btn btn-sm btn-success py-1 px-2 text-nowrap d-inline-flex align-items-center gap-1" title="Взяти в роботу">
                                                                    <i class="bi bi-play-fill"></i> В роботу
                                                                </button>
                                                            </form>
                                                            <button type="button" class="btn btn-sm btn-outline-danger py-1 px-2" onclick="openTicketActionModal(<?php echo $ticket['id']; ?>, 'reject')" title="Відхилити">
                                                                <i class="bi bi-x"></i> Відхилити
                                                            </button>
                                                        <?php elseif ($ticket['status'] === 'в роботі'): ?>
                                                            <button type="button" class="btn btn-sm btn-primary py-1 px-2" onclick="openTicketActionModal(<?php echo $ticket['id']; ?>, 'close')" title="Вирішено">
                                                                <i class="bi bi-check-lg"></i> Вирішено
                                                            </button>
                                                        <?php else: ?>
                                                            <span class="text-muted small">-</span>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                            <?php endif; ?>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>

                <?php if ($is_admin): ?>
                <!-- Tab 6: Repair Expenses -->
                <div class="tab-pane fade <?php echo (isset($_GET['tab']) && $_GET['tab'] === 'expenses') ? 'show active' : ''; ?>" id="device-expenses" role="tabpanel" aria-labelledby="device-expenses-tab">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h6 class="text-secondary mb-0 text-uppercase fw-bold" style="letter-spacing: 0.06em; font-size: 0.85rem;"><i class="bi bi-currency-dollar me-1"></i>Фінансовий облік витрат на обслуговування</h6>
                        <button class="btn btn-custom-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addExpenseModal">
                            <i class="bi bi-plus-lg"></i> Додати витрату
                        </button>
                    </div>

                    <!-- Summary widgets -->
                    <div class="row g-3 mb-4">
                        <div class="col-md-4">
                            <div class="expense-total-widget">
                                <div class="sub-label">Загальні витрати на пристрій</div>
                                <div class="big-number"><?php echo number_format($total_expense_sum, 2, '.', ' '); ?> грн</div>
                                <div class="sub-label mt-2">за весь час</div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="glass-card h-100">
                                <div class="text-muted small text-uppercase fw-bold mb-2">Кількість записів</div>
                                <div class="fs-2 fw-bold text-primary"><?php echo count($device_expenses); ?></div>
                                <div class="text-muted small">витрат зафіксовано</div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="glass-card h-100">
                                <div class="text-muted small text-uppercase fw-bold mb-2">Активні гарантії</div>
                                <?php
                                $today = new DateTime();
                                $active_warranties = 0;
                                foreach ($device_expenses as $exp) {
                                    if ($exp['warranty_months'] > 0) {
                                        $end_date = (new DateTime($exp['expense_date']))->modify('+' . $exp['warranty_months'] . ' months');
                                        if ($end_date > $today) $active_warranties++;
                                    }
                                }
                                ?>
                                <div class="fs-2 fw-bold text-success"><?php echo $active_warranties; ?></div>
                                <div class="text-muted small">деталей на гарантії</div>
                            </div>
                        </div>
                    </div>

                    <?php if (isset($_GET['success']) && $_GET['success'] === 'expense_added'): ?>
                    <div class="alert alert-success p-3 mb-3"><i class="bi bi-check-circle-fill me-2"></i>Витрату успішно додано!</div>
                    <?php elseif (isset($_GET['success']) && $_GET['success'] === 'expense_deleted'): ?>
                    <div class="alert alert-success p-3 mb-3"><i class="bi bi-check-circle-fill me-2"></i>Запис витрати видалено!</div>
                    <?php endif; ?>

                    <?php if (empty($device_expenses)): ?>
                        <div class="text-center py-5 text-muted">
                            <i class="bi bi-cash-coin fs-1 mb-2 d-block text-muted opacity-25"></i>
                            Витрати на ремонт та обслуговування ще не фіксувалися.
                            <div class="mt-3">
                                <button class="btn btn-custom-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addExpenseModal"><i class="bi bi-plus-lg"></i> Додати першу витрату</button>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive table-responsive-custom">
                            <table class="table table-custom table-hover small mb-0">
                                <thead>
                                    <tr>
                                        <th>Дата</th>
                                        <th>Тип</th>
                                        <th>Назва</th>
                                        <th>К-сть</th>
                                        <th>Ціна</th>
                                        <th>Сума</th>
                                        <th>Постачальник</th>
                                        <th>Гарантія</th>
                                        <th>Оплата</th>
                                        <th>Чек</th>
                                        <th>Заявка</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($device_expenses as $exp):
                                        $exp_today = new DateTime();
                                        $warr_active = false;
                                        $warr_remaining = '';
                                        if ($exp['warranty_months'] > 0) {
                                            $end_date = (new DateTime($exp['expense_date']))->modify('+' . $exp['warranty_months'] . ' months');
                                            $warr_active = $end_date > $exp_today;
                                            if ($warr_active) {
                                                $diff = $exp_today->diff($end_date);
                                                $warr_remaining = $diff->m + $diff->y * 12 . ' міс.';
                                            }
                                        }
                                        $expTypeClass = match($exp['expense_type']) {
                                            'запчастина' => 'badge-expense-part',
                                            'витратний матеріал' => 'badge-expense-material',
                                            'послуга' => 'badge-expense-service',
                                            'доставка' => 'badge-expense-delivery',
                                            default => 'badge-expense-other'
                                        };
                                        $payClass = match($exp['payment_status']) {
                                            'оплачено' => 'badge-paid',
                                            'очікує оплати' => 'badge-pending',
                                            'заплановано' => 'badge-planned',
                                            default => 'badge-paid'
                                        };
                                    ?>
                                    <tr class="expense-row">
                                        <td><span class="text-muted"><?php echo date('d.m.Y', strtotime($exp['expense_date'])); ?></span></td>
                                        <td><span class="<?php echo $expTypeClass; ?>"><?php echo htmlspecialchars($exp['expense_type']); ?></span></td>
                                        <td>
                                            <strong><?php echo htmlspecialchars($exp['part_name']); ?></strong>
                                            <?php if (!empty($exp['description'])): ?>
                                            <div class="text-muted small text-wrap" style="max-width:180px;"><?php echo htmlspecialchars($exp['description']); ?></div>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo (int)$exp['quantity']; ?></td>
                                        <td><?php echo number_format($exp['unit_price'], 2, '.', ' '); ?> грн</td>
                                        <td><strong><?php echo number_format($exp['total_price'], 2, '.', ' '); ?> грн</strong></td>
                                        <td><span class="text-muted"><?php echo htmlspecialchars($exp['supplier'] ?? '—'); ?></span></td>
                                        <td>
                                            <?php if ($exp['warranty_months'] <= 0): ?>
                                                <span class="warranty-none">Без гарантії</span>
                                            <?php elseif ($warr_active): ?>
                                                <span class="warranty-active">~<?php echo $warr_remaining; ?></span>
                                            <?php else: ?>
                                                <span class="warranty-expired">Вийшла</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><span class="<?php echo $payClass; ?>"><?php echo htmlspecialchars($exp['payment_status']); ?></span></td>
                                        <td>
                                            <?php if (!empty($exp['attachment_path'])): ?>
                                                <a href="<?php echo htmlspecialchars($exp['attachment_path']); ?>" target="_blank" class="attachment-link">
                                                    <i class="bi bi-paperclip"></i> Переглянути
                                                </a>
                                            <?php else: ?>
                                                <span class="text-muted">—</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if (!empty($exp['ticket_id'])): ?>
                                                <span class="text-muted small">#<?php echo (int)$exp['ticket_id']; ?></span>
                                            <?php else: ?>
                                                <span class="text-muted">—</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <a href="actions/delete_expense.php?id=<?php echo $exp['id']; ?>&device_id=<?php echo $device_id; ?>" class="btn btn-sm btn-outline-danger py-0 px-2 delete-expense-btn" title="Видалити">
                                                <i class="bi bi-trash3"></i>
                                            </a>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                                <tfoot>
                                    <tr class="fw-bold">
                                        <td colspan="5" class="text-end">Загалом:</td>
                                        <td><?php echo number_format($total_expense_sum, 2, '.', ' '); ?> грн</td>
                                        <td colspan="6"></td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>

            </div>
        </div>
    </div>
</div>

<div class="d-none d-print-block print-tag-container text-center p-3 border border-dark rounded" style="width: 80mm; height: 50mm; margin: auto;">
    <h5 class="fw-bold mb-1" style="font-size: 14px; color: #000; font-family: monospace;">IT-DEP INVENTORY</h5>
    <div style="font-size: 11px; margin-bottom: 5px; color: #000; font-weight: bold;"><?php echo htmlspecialchars($device['name']); ?></div>
    <div class="d-flex align-items-center justify-content-center gap-3">
        <img src="<?php echo $qr_api_url; ?>" style="width: 110px; height: 110px;">
        <div class="text-start" style="font-size: 9px; color: #000; line-height: 1.4; font-family: monospace;">
            <div><strong>ІНВ №:</strong> <?php echo htmlspecialchars($device['inventory_number'] ?? 'НЕМАЄ'); ?></div>
            <div><strong>S/N:</strong> <?php echo htmlspecialchars($device['serial_number'] ?? 'НЕМАЄ'); ?></div>
            <div><strong>МОДЕЛЬ:</strong> <?php echo htmlspecialchars($device['model'] ?? 'НЕМАЄ'); ?></div>
            <div><strong>ВІДП.:</strong> <?php echo htmlspecialchars($device['responsible_person'] ?? 'НЕМАЄ'); ?></div>
        </div>
    </div>
</div>
            </div>
        </div>
    </div>
<!-- Modal for submitting ticket -->
<div class="modal fade" id="ticketModal" tabindex="-1" aria-labelledby="ticketModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content modal-content-custom bg-white border border-opacity-10 shadow">
            <div class="modal-header border-bottom-0 pb-0">
                <h5 class="modal-title" id="ticketModalLabel"><i class="bi bi-exclamation-triangle-fill text-warning"></i> Подати заявку на обслуговування</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="actions/create_ticket.php" method="POST">
                <input type="hidden" name="device_id" value="<?php echo $device['id']; ?>">
                <div class="modal-body">
                    <p class="text-muted small">Опишіть несправність або проблему з пристроєм. Адміністратор отримає сповіщення та вживе необхідних заходів.</p>
                    <div class="mb-3">
                        <label class="form-label text-muted small">Опис несправності</label>
                        <textarea class="form-control form-control-custom" id="description" name="description" rows="4" required placeholder="Наприклад: не запускається операційна система, або комп'ютер раптово вимикається..."></textarea>
                    </div>
                </div>
                <div class="modal-footer border-top-0 pt-0">
                    <button type="button" class="btn btn-custom-secondary btn-sm px-3" data-bs-dismiss="modal">Скасувати</button>
                    <button type="submit" class="btn btn-custom-primary btn-sm px-4">Надіслати заявку</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal for closing/rejecting ticket -->
<div class="modal fade" id="actionTicketModal" tabindex="-1" aria-labelledby="actionTicketModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content modal-content-custom bg-white border border-opacity-10 shadow">
            <div class="modal-header border-bottom-0 pb-0">
                <h5 class="modal-title" id="actionTicketModalLabel"><i class="bi bi-wrench-adjustable-circle text-primary"></i> <span id="action-modal-title">Вирішення заявки</span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="actions/update_ticket.php" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="ticket_id" id="modal-ticket-id" value="">
                <input type="hidden" name="action" id="modal-ticket-action" value="">
                <input type="hidden" name="device_id" value="<?php echo $device_id; ?>">
                <div class="modal-body">
                    <p class="text-muted small" id="action-modal-desc">Додайте супровідний коментар (наприклад, що саме було зроблено для вирішення проблеми).</p>
                    <div class="mb-3">
                        <label class="form-label text-muted small">Коментар</label>
                        <textarea class="form-control form-control-custom" id="modal-ticket-comment" name="comment" rows="3" placeholder="Наприклад: Замінено плашку оперативної пам'яті DDR4 8GB на нову..."></textarea>
                    </div>
                    <?php if ($is_admin): ?>
                    <hr class="border-secondary border-opacity-25">
                    <div class="form-check mb-2">
                        <input type="checkbox" class="form-check-input" id="add-expense-checkbox" onchange="toggleExpenseSection()">
                        <label class="form-check-label fw-semibold" for="add-expense-checkbox">
                            <i class="bi bi-currency-dollar text-success"></i> Зафіксувати фінансові витрати на цей ремонт
                        </label>
                    </div>
                    <div id="expense-section" style="display:none;">
                        <div class="d-flex justify-content-between align-items-center mb-2 mt-3">
                            <span class="text-muted small fw-semibold">Позиції витрат:</span>
                            <button type="button" class="btn btn-sm btn-outline-primary py-0 px-2" onclick="addExpenseRow()">
                                <i class="bi bi-plus"></i> Додати позицію
                            </button>
                        </div>
                        <div id="expense-rows-placeholder" class="text-center py-3 text-muted small">Натисніть «Додати позицію», щоб внести витрати</div>
                        <div id="expense-rows-container"></div>
                        <div class="text-end mt-2 pt-2 border-top border-secondary border-opacity-25">
                            <span class="text-muted small">Разом: </span>
                            <strong class="text-primary" id="expense-grand-total">0.00 грн</strong>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
                <div class="modal-footer border-top-0 pt-0">
                    <button type="button" class="btn btn-custom-secondary btn-sm px-3" data-bs-dismiss="modal">Скасувати</button>
                    <button type="submit" class="btn btn-custom-primary btn-sm px-4" id="action-modal-btn">Підтвердити</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php if ($is_admin): ?>
<!-- Modal: Add Standalone Expense -->
<div class="modal fade" id="addExpenseModal" tabindex="-1" aria-labelledby="addExpenseModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-xl">
        <div class="modal-content modal-content-custom bg-white border border-opacity-10 shadow">
            <div class="modal-header modal-header-custom py-2 px-3">
                <h5 class="modal-title fs-6 text-gradient d-flex align-items-center gap-2" id="addExpenseModalLabel">
                    <i class="bi bi-cash-coin"></i> Додати витрати на обслуговування
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="actions/save_expense.php" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="device_id" value="<?php echo $device_id; ?>">
                <div class="modal-body py-4">
                    <div class="row g-3">
                        <!-- Global Fields for the Expense/Invoice -->
                        <div class="col-md-3">
                            <label class="form-label text-muted small mb-1">Дата витрати *</label>
                            <input type="date" name="expense_date" class="form-control form-control-custom" value="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label text-muted small mb-1">Номер чека/накладної</label>
                            <input type="text" name="receipt_number" class="form-control form-control-custom" placeholder="№ документу">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-muted small mb-1">Прив'язка до заявки (необов'язково)</label>
                            <select name="ticket_id" class="form-select form-control-custom">
                                <option value="">— Без прив'язки —</option>
                                <?php foreach ($device_tickets as $t): ?>
                                <option value="<?php echo $t['id']; ?>">#<?php echo $t['id']; ?> — <?php echo htmlspecialchars(mb_strimwidth($t['description'], 0, 60, '...')); ?> [<?php echo $t['status']; ?>]</option>
                                <?php endforeach; ?>
                            </select>
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
<!-- Modal for editing device -->
<div class="modal fade" id="editDeviceModal" tabindex="-1" aria-labelledby="editDeviceModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content modal-content-custom">
            <div class="modal-header modal-header-custom">
                <h5 class="modal-title text-gradient-amber" id="editDeviceModalLabel"><i class="bi bi-pencil-square"></i> Редагувати інформацію про обладнання</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="actions/save_device.php" method="POST">
                <input type="hidden" name="device_id" value="<?php echo $device['id']; ?>">
                <input type="hidden" name="redirect_to" value="../device_view.php?id=<?php echo $device['id']; ?>">
                
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label text-muted small">Назва / Клас обладнання *</label>
                            <input type="text" name="name" class="form-control form-control-custom" value="<?php echo htmlspecialchars($device['name']); ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-muted small">Поточний статус *</label>
                            <select name="status" class="form-select form-select-custom" required>
                                <option value="в резерві" <?php echo ($device['status'] === 'в резерві') ? 'selected' : ''; ?>>В резерві</option>
                                <option value="в роботі" <?php echo ($device['status'] === 'в роботі') ? 'selected' : ''; ?>>В роботі</option>
                                <option value="очікує перевірки" <?php echo ($device['status'] === 'очікує перевірки') ? 'selected' : ''; ?>>Очікує перевірки</option>
                                <option value="на ремонті" <?php echo ($device['status'] === 'на ремонті') ? 'selected' : ''; ?>>На ремонті</option>
                                <option value="списано" <?php echo ($device['status'] === 'списано') ? 'selected' : ''; ?>>Списано</option>
                            </select>
                        </div>
                        <div class="col-md-12">
                            <label class="form-label text-muted small">Тип пристрою (ПК / Комп'ютер) *</label>
                            <select name="is_pc" class="form-select form-select-custom" required>
                                <option value="1" <?php echo ($device['is_pc'] == 1) ? 'selected' : ''; ?>>Так (ПК / Ноутбук / Сервер)</option>
                                <option value="0" <?php echo ($device['is_pc'] == 0) ? 'selected' : ''; ?>>Ні (інше обладнання)</option>
                            </select>
                        </div>
                        <div class="col-md-12">
                            <label class="form-label text-muted small">Відповідальна особа *</label>
                            <input type="text" name="responsible_person" class="form-control form-control-custom" value="<?php echo htmlspecialchars($device['responsible_person']); ?>" required>
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label text-muted small">Інвентарний номер</label>
                            <input type="text" name="inventory_number" class="form-control form-control-custom" value="<?php echo htmlspecialchars($device['inventory_number']); ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-muted small">Серійний номер</label>
                            <input type="text" name="serial_number" class="form-control form-control-custom" value="<?php echo htmlspecialchars($device['serial_number']); ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-muted small">Модель</label>
                            <input type="text" name="model" class="form-control form-control-custom" value="<?php echo htmlspecialchars($device['model']); ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-muted small">Поточна локація</label>
                            <input type="text" name="location" class="form-control form-control-custom" value="<?php echo htmlspecialchars($device['location']); ?>">
                        </div>
                        <div class="col-md-12">
                            <label class="form-label text-muted small">Дата прийняття</label>
                            <input type="date" name="accepted_date" class="form-control form-control-custom" value="<?php echo htmlspecialchars($device['accepted_date']); ?>">
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

<style>
#logTabs {
    flex-wrap: nowrap !important;
    overflow-x: auto !important;
    overflow-y: hidden !important;
    white-space: nowrap !important;
    scrollbar-width: thin !important;
}
#logTabs::-webkit-scrollbar {
    height: 4px;
}
#logTabs::-webkit-scrollbar-thumb {
    background: rgba(var(--accent-blue-rgb), 0.2);
    border-radius: 2px;
}
#logTabs::-webkit-scrollbar-track {
    background: transparent;
}
#logTabs .nav-item {
    white-space: nowrap !important;
}
#logTabs .nav-link {
    border-radius: 0;
    white-space: nowrap !important;
}
#logTabs .nav-link.active {
    border-bottom: 3px solid var(--accent-blue) !important;
    color: var(--accent-blue) !important;
}
</style>

<?php 
require_once 'includes/footer.php';
?>
