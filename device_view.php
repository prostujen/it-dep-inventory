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

} catch (\PDOException $e) {
    die("Помилка бази даних: " . htmlspecialchars($e->getMessage()));
}

require_once 'includes/header.php';
?>

<div class="mb-4">
    <a href="index.php" class="btn btn-custom-secondary d-inline-flex align-items-center gap-2">
        <i class="bi bi-arrow-left-short fs-5"></i> Назад до списку
    </a>
</div>

<?php if (isset($_GET['success'])): ?>
    <div class="alert alert-success bg-emerald-950 border-success border-opacity-25 text-success glass-card mb-4">
        <i class="bi bi-check-circle-fill me-2"></i>
        <?php 
            if ($_GET['success'] === 'net_updated') echo 'Мережеві налаштування успішно оновлено!';
            elseif ($_GET['success'] === 'rolled_back') echo 'Параметри мережі успішно відновлено з архівного логу!';
        ?>
    </div>
<?php endif; ?>

<?php if (isset($_GET['error'])): ?>
    <div class="alert alert-danger bg-rose-950 border-danger border-opacity-25 text-danger glass-card mb-4">
        <i class="bi bi-exclamation-triangle-fill me-2"></i>
        <strong>Помилка: </strong>
        <?php 
            if ($_GET['error'] === 'ip_conflict') {
                $conflicting_inv = isset($_GET['conflicting_inv']) ? htmlspecialchars($_GET['conflicting_inv']) : 'іншим пристроєм';
                echo 'Конфлікт мережі! Вказана IP-адреса вже використовується активним обладнанням з інвентарним номером: <strong>' . $conflicting_inv . '</strong>. Спроба зміни логувалася.';
            } elseif ($_GET['error'] === 'invalid_ip') {
                echo 'Некоректний формат IP-адреси.';
            } elseif ($_GET['error'] === 'db_error') {
                echo 'Помилка бази даних: ' . htmlspecialchars($_GET['msg']);
            } else {
                echo 'Неможливо оновити мережеві налаштування.';
            }
        ?>
    </div>
<?php endif; ?>

<div class="row g-4">
    <div class="col-lg-5">
        
        <div class="glass-card mb-4 position-relative overflow-hidden">
            <div class="d-flex justify-content-between align-items-start mb-3">
                <div>
                    <h3 class="fw-bold text-white mb-0"><?php echo htmlspecialchars($device['name']); ?></h3>
                    <span class="text-muted small"><?php echo htmlspecialchars($device['model']); ?></span>
                </div>
                <?php 
                    $statusClass = 'badge-reserve';
                    if ($device['status'] === 'в роботі') $statusClass = 'badge-in-work';
                    elseif ($device['status'] === 'на ремонті') $statusClass = 'badge-repair';
                    elseif ($device['status'] === 'списано') $statusClass = 'badge-decommissioned';
                ?>
                <span class="badge-custom <?php echo $statusClass; ?>">
                    <span class="pulsing-dot <?php 
                        if ($device['status'] === 'в роботі') echo 'pulsing-dot-success';
                        elseif ($device['status'] === 'на ремонті') echo 'pulsing-dot-warning';
                        else echo 'pulsing-dot-danger';
                    ?>"></span>
                    <?php echo htmlspecialchars($device['status']); ?>
                </span>
            </div>
            
            <div class="mb-4">
                <table class="table table-borderless text-muted small mb-0">
                    <tr>
                        <td class="ps-0 py-1" style="width: 40%;">Інвентарний №:</td>
                        <td class="text-white py-1 fw-semibold"><?php echo htmlspecialchars($device['inventory_number']); ?></td>
                    </tr>
                    <tr>
                        <td class="ps-0 py-1">Серійний номер:</td>
                        <td class="text-white py-1"><?php echo htmlspecialchars($device['serial_number']); ?></td>
                    </tr>
                    <tr>
                        <td class="ps-0 py-1">Поточне розташування:</td>
                        <td class="text-white py-1">
                            <i class="bi bi-geo-alt-fill text-info me-1"></i>
                            <?php echo htmlspecialchars($device['location']); ?>
                        </td>
                    </tr>
                    <tr>
                        <td class="ps-0 py-1">Матеріально відповідальний:</td>
                        <td class="text-white py-1"><?php echo htmlspecialchars($device['responsible_person']); ?></td>
                    </tr>
                    <tr>
                        <td class="ps-0 py-1">В експлуатації з:</td>
                        <td class="text-white py-1"><?php echo date('d.m.Y', strtotime($device['accepted_date'])); ?></td>
                    </tr>
                </table>
            </div>

            <?php if ($net_settings && !empty($net_settings['ip_address'])): ?>
                <div class="p-3 rounded border border-secondary border-opacity-10" style="background: rgba(255, 255, 255, 0.01);">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <span class="text-muted small d-block">Симуляція моніторингу залізом</span>
                            <strong><?php echo htmlspecialchars($net_settings['ip_address']); ?></strong>
                        </div>
                        <button id="ping-btn-<?php echo $device['id']; ?>" class="btn btn-sm btn-custom-primary" onclick="runPingTest(<?php echo $device['id']; ?>)">
                            <i class="bi bi-broadcast"></i> Ping
                        </button>
                    </div>
                    <div id="ping-status-<?php echo $device['id']; ?>" class="mt-2 text-muted small">
                        <i class="bi bi-info-circle"></i> Натисніть кнопку для опитування пристрою
                    </div>
                </div>
            <?php else: ?>
                <div class="alert alert-secondary bg-transparent border-secondary border-opacity-25 text-muted small mb-0">
                    <i class="bi bi-info-circle-fill"></i> Моніторинг зв'язку неможливий, оскільки пристрою не призначено IP-адресу.
                </div>
            <?php endif; ?>
        </div>

        <div class="glass-card">
            <h4 class="text-gradient mb-3"><i class="bi bi-sliders"></i> Конфігурація мережі</h4>
            
            <form action="actions/update_net.php" method="POST">
                <input type="hidden" name="device_id" value="<?php echo $device['id']; ?>">
                
                <div class="mb-3">
                    <label class="form-label text-muted small">IP-адреса</label>
                    <input type="text" name="ip_address" class="form-control form-control-custom validate-ip" 
                           placeholder="Напр: 192.168.1.15" 
                           value="<?php echo htmlspecialchars($net_settings['ip_address'] ?? ''); ?>">
                </div>
                
                <div class="mb-3">
                    <label class="form-label text-muted small">Маска підмережі</label>
                    <input type="text" name="subnet_mask" class="form-control form-control-custom validate-ip" 
                           placeholder="Напр: 255.255.255.0" 
                           value="<?php echo htmlspecialchars($net_settings['subnet_mask'] ?? ''); ?>">
                </div>
                
                <div class="mb-3">
                    <label class="form-label text-muted small">Основний шлюз (Gateway)</label>
                    <input type="text" name="gateway" class="form-control form-control-custom validate-ip" 
                           placeholder="Напр: 192.168.1.1" 
                           value="<?php echo htmlspecialchars($net_settings['gateway'] ?? ''); ?>">
                </div>
                
                <div class="mb-4">
                    <label class="form-label text-muted small">DNS Сервер</label>
                    <input type="text" name="dns_server" class="form-control form-control-custom validate-ip" 
                           placeholder="Напр: 8.8.8.8" 
                           value="<?php echo htmlspecialchars($net_settings['dns_server'] ?? ''); ?>">
                </div>
                
                <div class="d-grid">
                    <button type="submit" class="btn btn-custom-primary">
                        <i class="bi bi-save2"></i> Застосувати зміни
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div class="col-lg-7">
        <div class="glass-card h-100">
            <ul class="nav nav-tabs border-secondary border-opacity-10 mb-4" id="logTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active py-2 text-white bg-transparent border-0 border-bottom border-3 border-transparent" id="net-logs-tab" data-bs-toggle="tab" data-bs-target="#net-logs" type="button" role="tab" aria-controls="net-logs" aria-selected="true">
                        <i class="bi bi-activity text-info"></i> Логи мережі та сесії
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link py-2 text-white bg-transparent border-0 border-bottom border-3 border-transparent" id="device-history-tab" data-bs-toggle="tab" data-bs-target="#device-history" type="button" role="tab" aria-controls="device-history" aria-selected="false">
                        <i class="bi bi-journal-text text-purple"></i> Життєвий цикл пристрою
                    </button>
                </li>
            </ul>

            <div class="tab-content" id="logTabsContent">
                <div class="tab-pane fade show active" id="net-logs" role="tabpanel" aria-labelledby="net-logs-tab">
                    <h5 class="text-white mb-3 small text-uppercase tracking-wider">Мережева історія (Зміни налаштувань, збої зв'язку)</h5>
                    
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

                <div class="tab-pane fade" id="device-history" role="tabpanel" aria-labelledby="device-history-tab">
                    <h5 class="text-white mb-3 small text-uppercase tracking-wider">Історія переміщень, ремонтів та життєвого циклу</h5>
                    
                    <?php if (empty($history_records)): ?>
                        <div class="text-center py-5 text-muted">
                            <i class="bi bi-clock-history fs-1 mb-2 d-block"></i>
                            Жодної події життєвого циклу не зафіксовано.
                        </div>
                    <?php else: ?>
                        <div class="timeline">
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
                                                <td>
                                                    <span class="text-muted"><?php echo htmlspecialchars($rec['old_value'] ?? '-'); ?></span>
                                                </td>
                                                <td class="text-white">
                                                    <strong><?php echo htmlspecialchars($rec['new_value'] ?? '-'); ?></strong>
                                                </td>
                                                <td>
                                                    <small class="text-muted"><?php echo htmlspecialchars($rec['responsible_person']); ?></small>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
#logTabs .nav-link {
    border-radius: 0;
}
#logTabs .nav-link.active {
    border-bottom: 3px solid var(--accent-blue) !important;
    color: var(--accent-blue) !important;
}
</style>

<?php 
require_once 'includes/footer.php';
?>
