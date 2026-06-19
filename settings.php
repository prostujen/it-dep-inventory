<?php
require_once 'config/db.php';

// Check if user is admin
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || !isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: index.php?error=access_denied");
    exit;
}

$error = '';
$success = '';
$active_tab = 'security';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $active_tab = 'security';
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if (!empty($current_password) && !empty($new_password) && !empty($confirm_password)) {
        $stmt_admin = $pdo->prepare("SELECT * FROM users WHERE id = ?");
        $stmt_admin->execute([$_SESSION['user_id']]);
        $admin_user = $stmt_admin->fetch();

        if ($admin_user && password_verify($current_password, $admin_user['password'])) {
            if ($new_password === $confirm_password) {
                $new_hash = password_hash($new_password, PASSWORD_DEFAULT);
                $stmt_update = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
                $stmt_update->execute([$new_hash, $_SESSION['user_id']]);
                $success = 'Пароль успішно змінено!';
            } else {
                $error = 'Новий пароль та підтвердження не співпадають';
            }
        } else {
            $error = 'Невірний поточний пароль';
        }
    } else {
        $error = 'Будь ласка, заповніть усі поля';
    }
}

try {
    $net_logs_stmt = $pdo->query("
        SELECT n.*, d.name AS device_name, d.inventory_number, d.model 
        FROM network_history_and_logs n
        JOIN devices d ON n.device_id = d.id
        ORDER BY n.log_date DESC
    ");
    $net_logs = $net_logs_stmt->fetchAll();

    $dev_history_stmt = $pdo->query("
        SELECT h.*, d.name AS device_name, d.inventory_number, d.model 
        FROM device_history h
        JOIN devices d ON h.device_id = d.id
        ORDER BY h.event_date DESC
    ");
    $dev_history = $dev_history_stmt->fetchAll();

    // Query all users for user management tab
    $users_stmt = $pdo->query("SELECT id, username, full_name, role FROM users ORDER BY role ASC, full_name ASC");
    $users_list = $users_stmt->fetchAll();
} catch (\PDOException $e) {
    die("Помилка бази даних: " . htmlspecialchars($e->getMessage()));
}

if (isset($_GET['tab']) && in_array($_GET['tab'], ['security', 'logs', 'users'])) {
    $active_tab = $_GET['tab'];
}

require_once 'includes/header.php';
?>

<div class="glass-card mb-4">
    <div class="row align-items-center">
        <div class="col-md-8">
            <h2 class="text-gradient mb-1"><i class="bi bi-gear-fill"></i> Налаштування та системні логи</h2>
            <p class="text-muted mb-0">Керування безпекою облікового запису та перегляд журналів подій обладнання.</p>
        </div>
        <div class="col-md-4 text-md-end mt-3 mt-md-0">
            <a href="index.php" class="btn btn-custom-secondary">
                <i class="bi bi-grid-fill"></i> На головну панель
            </a>
        </div>
    </div>
</div>

<div class="glass-card">
    <ul class="nav nav-tabs border-secondary border-opacity-10 mb-4" id="settingsTabs" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link <?php echo ($active_tab === 'security') ? 'active' : ''; ?> py-2" id="security-tab" data-bs-toggle="tab" data-bs-target="#security" type="button" role="tab" aria-controls="security" aria-selected="<?php echo ($active_tab === 'security') ? 'true' : 'false'; ?>">
                <i class="bi bi-shield-lock-fill text-warning"></i> Безпека (Зміна пароля)
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link <?php echo ($active_tab === 'users') ? 'active' : ''; ?> py-2" id="users-tab" data-bs-toggle="tab" data-bs-target="#users" type="button" role="tab" aria-controls="users" aria-selected="<?php echo ($active_tab === 'users') ? 'true' : 'false'; ?>">
                <i class="bi bi-people-fill text-primary"></i> Керування користувачами (<?php echo count($users_list); ?>)
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link <?php echo ($active_tab === 'logs') ? 'active' : ''; ?> py-2" id="logs-tab" data-bs-toggle="tab" data-bs-target="#logs" type="button" role="tab" aria-controls="logs" aria-selected="<?php echo ($active_tab === 'logs') ? 'true' : 'false'; ?>">
                <i class="bi bi-clock-history text-info"></i> Журнал подій та логів (<?php echo count($net_logs) + count($dev_history); ?>)
            </button>
        </li>
    </ul>

    <div class="tab-content" id="settingsTabsContent">
        <div class="tab-pane fade <?php echo ($active_tab === 'security') ? 'show active' : ''; ?>" id="security" role="tabpanel" aria-labelledby="security-tab">
            <div style="max-width: 500px;">
                <h6 class="text-secondary mb-3 text-uppercase fw-bold" style="letter-spacing: 0.06em; font-size: 0.85rem;">Зміна пароля адміністратора</h6>
                
                <?php if (!empty($error)): ?>
                    <div class="alert alert-danger p-3 mb-3">
                        <i class="bi bi-exclamation-triangle-fill me-2"></i> <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>

                <?php if (!empty($success)): ?>
                    <div class="alert alert-success p-3 mb-3">
                        <i class="bi bi-check-circle-fill me-2"></i> <?php echo htmlspecialchars($success); ?>
                    </div>
                <?php endif; ?>

                <form method="POST" action="settings.php">
                    <input type="hidden" name="change_password" value="1">
                    <div class="mb-3">
                        <label for="current_password" class="form-label text-muted small">Поточний пароль</label>
                        <input type="password" class="form-control form-control-custom" id="current_password" name="current_password" required>
                    </div>
                    <div class="mb-3">
                        <label for="new_password" class="form-label text-muted small">Новий пароль</label>
                        <input type="password" class="form-control form-control-custom" id="new_password" name="new_password" required>
                    </div>
                    <div class="mb-4">
                        <label for="confirm_password" class="form-label text-muted small">Підтвердження нового пароля</label>
                        <input type="password" class="form-control form-control-custom" id="confirm_password" name="confirm_password" required>
                    </div>
                    <button type="submit" class="btn btn-custom-primary">
                        <i class="bi bi-key-fill"></i> Оновити пароль
                    </button>
                </form>
            </div>
        </div>

        <div class="tab-pane fade <?php echo ($active_tab === 'users') ? 'show active' : ''; ?>" id="users" role="tabpanel" aria-labelledby="users-tab">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h6 class="text-secondary text-uppercase fw-bold mb-0" style="letter-spacing: 0.06em; font-size: 0.85rem;">Список користувачів системи</h6>
                <button type="button" class="btn btn-sm btn-custom-primary" data-bs-toggle="modal" data-bs-target="#addUserModal">
                    <i class="bi bi-person-plus-fill"></i> Додати користувача
                </button>
            </div>

            <?php if (isset($_GET['success'])): ?>
                <?php if ($_GET['success'] === 'user_added'): ?>
                    <div class="alert alert-success alert-dismissible fade show p-3 mb-3 animate__animated animate__fadeIn" role="alert">
                        <i class="bi bi-check-circle-fill me-2"></i> Користувача успішно додано!
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php elseif ($_GET['success'] === 'user_deleted'): ?>
                    <div class="alert alert-success alert-dismissible fade show p-3 mb-3 animate__animated animate__fadeIn" role="alert">
                        <i class="bi bi-check-circle-fill me-2"></i> Користувача успішно видалено!
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>
            <?php endif; ?>

            <?php if (isset($_GET['error'])): ?>
                <div class="alert alert-danger alert-dismissible fade show p-3 mb-3 animate__animated animate__fadeIn" role="alert">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i> 
                    <?php 
                        $err_msg = 'Помилка виконання операції.';
                        if ($_GET['error'] === 'username_exists') $err_msg = 'Користувач з таким логіном вже існує.';
                        elseif ($_GET['error'] === 'empty_fields') $err_msg = 'Будь ласка, заповніть усі обов\'язкові поля.';
                        elseif ($_GET['error'] === 'cannot_delete_self') $err_msg = 'Ви не можете видалити власний обліковий запис.';
                        elseif ($_GET['error'] === 'user_not_found') $err_msg = 'Користувача не знайдено.';
                        echo htmlspecialchars($err_msg);
                    ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <div class="table-responsive table-responsive-custom">
                <table class="table table-custom table-hover">
                    <thead>
                        <tr>
                            <th>ПІБ користувача</th>
                            <th>Логін (username)</th>
                            <th>Роль в системі</th>
                            <th class="text-end">Дії</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users_list as $u): ?>
                            <tr>
                                <td>
                                    <div class="fw-semibold text-dark"><?php echo htmlspecialchars($u['full_name']); ?></div>
                                </td>
                                <td>
                                    <code><?php echo htmlspecialchars($u['username']); ?></code>
                                </td>
                                <td>
                                    <?php if ($u['role'] === 'admin'): ?>
                                        <span class="badge bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25 px-2 py-1">Адміністратор</span>
                                    <?php else: ?>
                                        <span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25 px-2 py-1">Викладач</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end">
                                    <?php if (intval($u['id']) !== intval($_SESSION['user_id'])): ?>
                                        <a href="actions/manage_user.php?action=delete&id=<?php echo $u['id']; ?>" class="btn btn-sm btn-outline-danger delete-user-btn">
                                            <i class="bi bi-trash-fill"></i> Видалити
                                        </a>
                                    <?php else: ?>
                                        <span class="text-muted small">Це ви (поточна сесія)</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="tab-pane fade <?php echo ($active_tab === 'logs') ? 'show active' : ''; ?>" id="logs" role="tabpanel" aria-labelledby="logs-tab">
            <ul class="nav nav-pills mb-4 gap-2" id="logsPills" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active btn btn-sm btn-custom-secondary px-3 py-1" id="pills-net-tab" data-bs-toggle="pill" data-bs-target="#pills-net" type="button" role="tab" aria-controls="pills-net" aria-selected="true">
                        Логи мережі (<?php echo count($net_logs); ?>)
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link btn btn-sm btn-custom-secondary px-3 py-1" id="pills-dev-tab" data-bs-toggle="pill" data-bs-target="#pills-dev" type="button" role="tab" aria-controls="pills-dev" aria-selected="false">
                        Історія пристроїв (<?php echo count($dev_history); ?>)
                    </button>
                </li>
            </ul>

            <div class="tab-content" id="logsPillsContent">
                <div class="tab-pane fade show active" id="pills-net" role="tabpanel" aria-labelledby="pills-net-tab">
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
                                                <a href="device_view.php?id=<?php echo $log['device_id']; ?>" class="fw-semibold text-dark text-decoration-none hover-link">
                                                    <?php echo htmlspecialchars($log['device_name']); ?>
                                                </a>
                                                <div class="small text-muted"><?php echo htmlspecialchars($log['inventory_number']); ?> (<?php echo htmlspecialchars($log['model']); ?>)</div>
                                            </td>
                                            <td>
                                                <code class="text-dark bg-secondary bg-opacity-25 px-2 py-1 rounded"><?php echo htmlspecialchars($log['admin_name']); ?></code>
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

                <div class="tab-pane fade" id="pills-dev" role="tabpanel" aria-labelledby="pills-dev-tab">
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
                                                <a href="device_view.php?id=<?php echo $rec['device_id']; ?>" class="fw-semibold text-dark text-decoration-none hover-link">
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
                                            <td class="text-dark fw-semibold">
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
    </div>
</div>
<!-- Modal for adding user -->
<div class="modal fade" id="addUserModal" tabindex="-1" aria-labelledby="addUserModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content modal-content-custom bg-white border border-opacity-10 shadow">
            <div class="modal-header border-bottom-0 pb-0">
                <h5 class="modal-title" id="addUserModalLabel"><i class="bi bi-person-plus-fill text-primary"></i> Новий користувач</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="actions/manage_user.php" method="POST">
                <input type="hidden" name="action" value="add">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="full_name" class="form-label text-muted small">ПІБ користувача (викладача)</label>
                        <input type="text" class="form-control form-control-custom" id="full_name" name="full_name" required placeholder="доц. Петренко П.П.">
                    </div>
                    <div class="mb-3">
                        <label for="username" class="form-label text-muted small">Логін (username)</label>
                        <input type="text" class="form-control form-control-custom" id="username" name="username" required autocomplete="username">
                    </div>
                    <div class="mb-3">
                        <label for="password" class="form-label text-muted small">Пароль</label>
                        <input type="password" class="form-control form-control-custom" id="password" name="password" required autocomplete="new-password">
                    </div>
                    <div class="mb-3">
                        <label for="role" class="form-label text-muted small">Роль у системі</label>
                        <select class="form-select form-control-custom" id="role" name="role" required>
                            <option value="teacher" selected>Викладач</option>
                            <option value="admin">Адміністратор</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer border-top-0 pt-0">
                    <button type="button" class="btn btn-custom-secondary btn-sm px-3" data-bs-dismiss="modal">Скасувати</button>
                    <button type="submit" class="btn btn-custom-primary btn-sm px-4">Зберегти</button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
#settingsTabs .nav-link.active {
    border-bottom: 3px solid var(--accent-blue) !important;
    color: var(--accent-blue) !important;
}
.hover-link:hover {
    color: var(--accent-blue) !important;
    text-decoration: underline !important;
}
#logsPills .nav-link.active {
    background-color: var(--accent-blue) !important;
    color: #ffffff !important;
    border-color: var(--accent-blue) !important;
}
</style>

<?php 
require_once 'includes/footer.php';
?>
