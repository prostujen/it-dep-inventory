<?php
$current_page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="uk">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>IT Inventory & Control</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css?v=<?php echo time(); ?>">
</head>
<body>

    <nav class="navbar navbar-expand-lg navbar-light navbar-custom sticky-top">
        <div class="container">
            <a class="navbar-brand d-flex align-items-center gap-2" href="index.php">
                <i class="bi bi-cpu text-gradient fs-3"></i>
                <span class="text-gradient">IT-Dep Inventory</span>
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto gap-2">
                    <li class="nav-item">
                        <a class="nav-link d-flex align-items-center gap-2 <?php echo ($current_page == 'index.php') ? 'active' : ''; ?>" href="index.php">
                            <i class="bi bi-grid-fill"></i> Панель керування
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link d-flex align-items-center gap-2 <?php echo ($current_page == 'settings.php') ? 'active' : ''; ?>" href="settings.php">
                            <i class="bi bi-gear-fill"></i> Налаштування
                        </a>
                    </li>
                    <li class="nav-item ms-lg-3">
                        <a class="nav-link btn btn-outline-danger d-flex align-items-center gap-2 px-3 py-1 text-danger" href="actions/logout.php">
                            <i class="bi bi-box-arrow-right"></i> Вихід
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container py-4 my-3">
