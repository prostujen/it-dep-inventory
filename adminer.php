<?php
session_start();
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    http_response_code(403);
    die("Доступ заборонено. Будь ласка, спочатку авторизуйтеся в системі як адміністратор.");
}

// Custom Adminer configuration to allow empty password database access
function adminer_object() {
    require_once './adminer-helper.php';
    return new AdminerCustom;
}

// Include the core Adminer script
require './adminer-core.php';
