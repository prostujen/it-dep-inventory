<?php
// history_logs.php
require_once 'config/db.php';

try {
    // 1. Отримуємо всі мережеві логи
    $net_logs_stmt = $pdo->query("
        SELECT n.*, d.name AS device_name, d.inventory_number, d.model 
        FROM network_history_and_logs n
        JOIN devices d ON n.device_id = d.id
        ORDER BY n.log_date DESC
    ");
    $net_logs = $net_logs_stmt->fetchAll();

    // 2. Отримуємо всю історію переміщень/статусів пристроїв
    $dev_history_stmt = $pdo->query("
        SELECT h.*, d.name AS device_name, d.inventory_number, d.model 
        FROM device_history h
        JOIN devices d ON h.device_id = d.id
        ORDER BY h.event_date DESC
    ");
    $dev_history = $dev_history_stmt->fetchAll();

} catch (\PDOException $e) {
    die("Помилка бази даних: " . htmlspecialchars($e->getMessage()));
}

require_once 'includes/header.php';
?>

<div class="glass-card mb-4">
    <div class="row align-items-center">
        <div class="col-md-8">
            <h2 class="text-gradient mb-1"><i class="bi bi-clock-history"></i> Журнал подій та системних логів</h2>
            <p class="text-muted mb-0">Тут фіксується кожна зміна локації, статусу, параметрів мережі та автоматичні системні збої.</p>
        </div>
        <div class="col-md-4 text-md-end mt-3 mt-md-0">
            <a href="index.php" class="btn btn-custom-secondary">
                <i class="bi bi-grid-fill"></i> На головну панель
            </a>
        </div>
    </div>
</div>

<div class="glass-card">
    <!-- Навігація вкладок журналу -->
    <ul class="nav nav-tabs border-secondary border-opacity-10 mb-4" id="globalLogTabs" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active py-2 text-white bg-transparent border-0 border-bottom border-3 border-transparent" id="global-net-tab" data-bs-toggle="tab" data-bs-target="#global-net" type="button" role="tab" aria-controls="global-net" aria-selected="true">
                <i class="bi bi-activity text-info"></i> Логи мережі та збоїв зв'язку (<?php echo count($net_logs); ?>)
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link py-2 text-white bg-transparent border-0 border-bottom border-3 border-transparent" id="global-dev-tab" data-bs-toggle="tab" data-bs-target="#global-dev" type="button" role="tab" aria-controls="global-dev" aria-selected="false">
                <i class="bi bi-journal-text text-purple"></i> Логи життєвого циклу обладнання (<?php echo count($dev_history); ?>)
            </button>
        </li>
    </ul>

    <div class="tab-content" id="globalLogTabsContent">
        <!-- ВКЛАДКА 1: Системні мережеві логи -->
        <div class="tab-pane fade show active" id="global-net" role="tabpanel" aria-labelledby="global-net-tab">
            <?php if (empty($net_logs)): ?>
                <div class="text-center py-5 text-muted">
                    <i class="bi bi-journal-x fs-1 mb-2 d-block"></i>
                    Журнал мережевих логів порожній.
                </div>
            <?php else: ?>
                <div class="table-responsive table-responsive-custom">
                    <table class="table table-custom table-hover">
                        <thead>
                            <tr>
                                <th>Дата запису</th>
                                <th>Обладнання (Інв. №)</th>
                                <th>Адміністратор / Монітор</th>
                                <th>Тип події</th>
                                <th>Мережеві параметри</th>
                                <th>Статус</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($net_logs as $log): ?>
                                <tr>
                                    <td>
                                        <i class="bi bi-clock text-muted me-1"></i>
                                        <?php echo date('d.m.Y H:i:s', strtotime($log['log_date'])); ?>
                                    </td>
                                    <td>
                                        <a href="device_view.php?id=<?php echo $log['device_id']; ?>" class="fw-semibold text-white text-decoration-none hover-link">
                                            <?php echo htmlspecialchars($log['device_name']); ?>
                                        </a>
                                        <div class="small text-muted"><?php echo htmlspecialchars($log['inventory_number']); ?> (<?php echo htmlspecialchars($log['model']); ?>)</div>
                                    </td>
                                    <td>
                                        <code class="text-white-50 bg-secondary bg-opacity-25 px-2 py-1 rounded"><?php echo htmlspecialchars($log['admin_name']); ?></code>
                                    </td>
                                    <td>
                                        <?php if ($log['log_type'] === 'зміна налаштувань'): ?>
                                            <span class="text-info fw-medium"><i class="bi bi-gear-fill me-1"></i> Зміна конфіг.</span>
                                        <?php elseif ($log['log_type'] === 'збій зв\'язку'): ?>
                                            <span class="text-danger fw-medium"><i class="bi bi-wifi-off me-1"></i> Збій зв'язку</span>
                                        <?php elseif ($log['log_type'] === 'відновлення'): ?>
                                            <span class="text-success fw-medium"><i class="bi bi-arrow-counterclockwise me-1"></i> Відновлення</span>
                                        <?php else: ?>
                                            <span class="text-warning fw-medium"><i class="bi bi-exclamation-octagon-fill me-1"></i> Конфлікт IP</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($log['ip_address']): ?>
                                            <div><strong>IP:</strong> <?php echo htmlspecialchars($log['ip_address']); ?></div>
                                            <div class="text-muted small"><strong>Mask:</strong> <?php echo htmlspecialchars($log['subnet_mask']); ?></div>
                                        <?php else: ?>
                                            <span class="text-muted">Не налаштовано</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php 
                                            $statusBadge = 'bg-secondary text-white';
                                            if ($log['session_status'] === 'успішно') $statusBadge = 'badge-in-work';
                                            elseif ($log['session_status'] === 'конфлікт') $statusBadge = 'badge-repair';
                                            elseif ($log['session_status'] === 'збій') $statusBadge = 'badge-decommissioned';
                                        ?>
                                        <span class="badge-custom <?php echo $statusBadge; ?>" style="font-size: 0.75rem; padding: 0.4rem 0.6rem;">
                                            <?php echo htmlspecialchars($log['session_status']); ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <!-- ВКЛАДКА 2: Життєвий цикл обладнання -->
        <div class="tab-pane fade" id="global-dev" role="tabpanel" aria-labelledby="global-dev-tab">
            <?php if (empty($dev_history)): ?>
                <div class="text-center py-5 text-muted">
                    <i class="bi bi-journal-x fs-1 mb-2 d-block"></i>
                    Журнал подій обладнання порожній.
                </div>
            <?php else: ?>
                <div class="table-responsive table-responsive-custom">
                    <table class="table table-custom table-hover">
                        <thead>
                            <tr>
                                <th>Дата події</th>
                                <th>Обладнання (Інв. №)</th>
                                <th>Тип події</th>
                                <th>Старе значення</th>
                                <th>Нове значення</th>
                                <th>Відповідальна особа</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($dev_history as $rec): ?>
                                <tr>
                                    <td>
                                        <i class="bi bi-clock text-muted me-1"></i>
                                        <?php echo date('d.m.Y H:i:s', strtotime($rec['event_date'])); ?>
                                    </td>
                                    <td>
                                        <a href="device_view.php?id=<?php echo $rec['device_id']; ?>" class="fw-semibold text-white text-decoration-none hover-link">
                                            <?php echo htmlspecialchars($rec['device_name']); ?>
                                        </a>
                                        <div class="small text-muted"><?php echo htmlspecialchars($rec['inventory_number']); ?></div>
                                    </td>
                                    <td>
                                        <?php if ($rec['event_type'] === 'створення'): ?>
                                            <span class="badge bg-success bg-opacity-25 text-success border border-success border-opacity-50">Створення</span>
                                        <?php elseif ($rec['event_type'] === 'переміщення'): ?>
                                            <span class="badge bg-info bg-opacity-25 text-info border border-info border-opacity-50">Переміщення</span>
                                        <?php elseif ($rec['event_type'] === 'зміна статусу'): ?>
                                            <span class="badge bg-warning bg-opacity-25 text-warning border border-warning border-opacity-50">Зміна статусу</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary bg-opacity-25 text-white border border-secondary border-opacity-50">Інша подія</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="text-muted"><?php echo htmlspecialchars($rec['old_value'] ?? '-'); ?></span>
                                    </td>
                                    <td class="text-white fw-semibold">
                                        <?php echo htmlspecialchars($rec['new_value'] ?? '-'); ?>
                                    </td>
                                    <td>
                                        <small class="text-muted"><?php echo htmlspecialchars($rec['responsible_person']); ?></small>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
#globalLogTabs .nav-link.active {
    border-bottom: 3px solid var(--accent-blue) !important;
    color: var(--accent-blue) !important;
}
.hover-link:hover {
    color: var(--accent-blue) !important;
    text-decoration: underline !important;
}
</style>

<?php 
require_once 'includes/footer.php';
?>
