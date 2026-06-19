<?php
require_once 'config/db.php';

if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) {
    header('Location: index.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if (!empty($username) && !empty($password)) {
        $stmt_user = $pdo->prepare("SELECT * FROM users WHERE username = ?");
        $stmt_user->execute([$username]);
        $db_user = $stmt_user->fetch();

        if ($db_user && password_verify($password, $db_user['password'])) {
            $_SESSION['logged_in'] = true;
            $_SESSION['user_id'] = $db_user['id'];
            $_SESSION['user_role'] = $db_user['role'] ?? 'teacher';
            $_SESSION['user_fullname'] = $db_user['full_name'] ?? $db_user['username'];
            header('Location: index.php');
            exit;
        } else {
            $error = 'Невірний логін або пароль';
        }
    } else {
        $error = 'Будь ласка, заповніть усі поля';
    }
}
?>
<!DOCTYPE html>
<html lang="uk">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Особистий кабінет - Авторизація</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body {
            background-color: #ffffff;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }
        .header-container {
            background: #ffffff;
            padding: 15px 0 10px 0;
        }
        .brand-logo {
            color: #3b5394;
            font-size: 24px;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 12px;
            text-decoration: none;
        }
        .brand-logo svg {
            width: 32px;
            height: 32px;
            fill: #3b5394;
        }
        .divider-line {
            height: 4px;
            background-color: #3b5394;
            width: 100%;
        }
        .main-content {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 40px 15px;
        }
        .login-card {
            width: 100%;
            max-width: 450px;
            background: #fdfdfd;
            border: 1px solid #dee2e6;
            border-radius: 4px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }
        .login-card-header {
            background-color: #3b5394;
            color: #ffffff;
            padding: 12px 15px;
            font-size: 16px;
            font-weight: 600;
            border-top-left-radius: 3px;
            border-top-right-radius: 3px;
        }
        .login-card-body {
            padding: 20px 20px 15px 20px;
        }
        .form-label {
            font-weight: 500;
            margin-bottom: 6px;
            color: #333333;
        }
        .form-control {
            border: 1px solid #cccccc;
            border-radius: 3px;
            padding: 8px 12px;
        }
        .form-control:focus {
            border-color: #3b5394;
            box-shadow: 0 0 0 3px rgba(59, 83, 148, 0.25);
        }
        .btn-sumdu {
            background-color: #3b5394;
            border: 1px solid #2d4277;
            color: #ffffff;
            border-radius: 3px;
            padding: 6px 20px;
            font-weight: 500;
            transition: background-color 0.2s;
        }
        .btn-sumdu:hover {
            background-color: #2d4277;
            color: #ffffff;
        }
        .login-footer {
            border-top: 1px solid #dee2e6;
            padding: 15px 0;
            font-size: 13px;
            color: #666666;
        }
        .error-message {
            color: #dc3545;
            font-size: 14px;
            margin-bottom: 15px;
        }
    </style>
</head>
<body>

    <div class="header-container">
        <div class="container d-flex align-items-center">
            <a href="#" class="brand-logo">
                <svg viewBox="0 0 100 100">
                    <rect x="15" y="15" width="70" height="70" rx="10" fill="none" stroke="#3b5394" stroke-width="8"/>
                    <path d="M35 30 V55 C35 65, 65 65, 65 55 V30" fill="none" stroke="#3b5394" stroke-width="8" stroke-linecap="round"/>
                </svg>
                Особистий кабінет
            </a>
        </div>
    </div>
    <div class="divider-line"></div>

    <div class="main-content">
        <div class="login-card">
            <div class="login-card-header">
                Авторизація
            </div>
            <div class="login-card-body">
                <?php if (!empty($error)): ?>
                    <div class="error-message">
                        <i class="bi bi-exclamation-circle-fill"></i> <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>

                <form method="POST" action="login.php">
                    <div class="mb-3">
                        <label for="username" class="form-label">username</label>
                        <input type="text" class="form-control" id="username" name="username" required autocomplete="username">
                    </div>
                    <div class="mb-3">
                        <label for="password" class="form-label">password</label>
                        <input type="password" class="form-control" id="password" name="password" required autocomplete="current-password">
                    </div>
                    <div class="d-flex justify-content-start mt-4">
                        <button type="submit" class="btn btn-sumdu">Вхід</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <footer class="login-footer">
        <div class="container text-center">
            <span>© Центр інформаційних систем 2026</span>
        </div>
    </footer>

</body>
</html>
