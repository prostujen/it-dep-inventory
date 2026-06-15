<?php
// includes/header.php

// Отримання поточної сторінки для підсвічування меню
$current_page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="uk">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>IT Inventory & Control</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>

    <!-- Навігаційна панель -->
    <nav class="navbar navbar-expand-lg navbar-dark navbar-custom sticky-top">
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
                        <a class="nav-link d-flex align-items-center gap-2 <?php echo ($current_page == 'history_logs.php') ? 'active' : ''; ?>" href="history_logs.php">
                            <i class="bi bi-journal-text"></i> Логи та Події
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Головний контейнер сторінки -->
    <div class="container py-4 my-3">
