<?php
require_once '../config/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: ../index.php");
    exit;
}

$device_id    = isset($_POST['device_id']) ? intval($_POST['device_id']) : 0;
$cpu          = isset($_POST['cpu']) ? trim($_POST['cpu']) : null;
$ram          = isset($_POST['ram']) ? trim($_POST['ram']) : null;
$storage      = isset($_POST['storage']) ? trim($_POST['storage']) : null;
$gpu          = isset($_POST['gpu']) ? trim($_POST['gpu']) : null;
$os           = isset($_POST['os']) ? trim($_POST['os']) : null;
$motherboard  = isset($_POST['motherboard']) ? trim($_POST['motherboard']) : null;
$power_supply = isset($_POST['power_supply']) ? trim($_POST['power_supply']) : null;

if ($device_id <= 0) {
    header("Location: ../index.php?error=invalid_id");
    exit;
}

try {
    $stmt_check = $pdo->prepare("SELECT is_pc FROM devices WHERE id = ?");
    $stmt_check->execute([$device_id]);
    $device_row = $stmt_check->fetch();
    if (!$device_row) {
        header("Location: ../index.php?error=not_found");
        exit;
    }
    if ($device_row['is_pc'] != 1) {
        header("Location: ../device_view.php?id=$device_id&error=not_a_pc");
        exit;
    }

    $stmt_spec = $pdo->prepare("SELECT COUNT(*) FROM device_specifications WHERE device_id = ?");
    $stmt_spec->execute([$device_id]);
    $exists = $stmt_spec->fetchColumn() > 0;

    if ($exists) {
        $stmt_update = $pdo->prepare("
            UPDATE device_specifications 
            SET cpu = ?, ram = ?, storage = ?, gpu = ?, os = ?, motherboard = ?, power_supply = ? 
            WHERE device_id = ?
        ");
        $stmt_update->execute([$cpu, $ram, $storage, $gpu, $os, $motherboard, $power_supply, $device_id]);
    } else {
        $stmt_insert = $pdo->prepare("
            INSERT INTO device_specifications (device_id, cpu, ram, storage, gpu, os, motherboard, power_supply) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt_insert->execute([$device_id, $cpu, $ram, $storage, $gpu, $os, $motherboard, $power_supply]);
    }

    header("Location: ../device_view.php?id=$device_id&success=specs_updated");
    exit;

} catch (\PDOException $e) {
    header("Location: ../device_view.php?id=$device_id&error=db_error&msg=" . urlencode($e->getMessage()));
    exit;
}
