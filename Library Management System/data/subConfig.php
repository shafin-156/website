<?php
require_once 'config.php';
date_default_timezone_set('Asia/Dhaka');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}


array_walk_recursive($_GET, function(&$item) { $item = htmlspecialchars(trim($item)); });
array_walk_recursive($_POST, function(&$item) { $item = htmlspecialchars(trim($item)); });

function system_log($message) {
    $logFile = __DIR__ . '/systemLog.txt';
    $timestamp = date('Y-m-d H:i:s');
    $userName = $_SESSION['name'] ?? 'Unknown Guest'; 
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    

    $safeMsg = str_replace(["\r", "\n"], '', $message);
    $logEntry = "[$timestamp] [User: $userName] [IP: $ip] $safeMsg" . PHP_EOL;

    $file = @fopen($logFile, 'a');
    if ($file) { fwrite($file, $logEntry); fclose($file); }
}

function protect_page($required_role) {
    if (!isset($_SESSION['user_id'])) {
        system_log("Access Denied: No active session.");
        header("Location: ../../");
        exit();
    }
    
    // Session Timeout (1h = 3600 s , 30 Min = 1800 S)
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > 1800)) {
        session_unset(); session_destroy();
        header("Location: ../../?status=timeout");
        exit();
    }
    $_SESSION['last_activity'] = time();

    if ($_SESSION['role'] !== $required_role && $_SESSION['role'] !== 'admin') {
        system_log("Permission Error: User attempted unauthorized access.");
        header("Location: ../../");
        exit();
    }
}

function get_count($conn, $table, $condition = "", $params = [], $types = "") {
    // SECURITY: SQL Injection
    $table = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
    $sql = "SELECT COUNT(*) as total FROM `$table` $condition";
    $stmt = $conn->prepare($sql);
    
    if (!empty($params)) { $stmt->bind_param($types, ...$params); }
    
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc()['total'] ?? 0;
}

function get_user_data_filter($column_alias = 'user_id') {
    $column_alias = preg_replace('/[^a-zA-Z0-9_.]/', '', $column_alias);
    if ($_SESSION['role'] === 'admin') {
        return ['sql' => "1=1", 'bind' => false];
    } else {
        return ['sql' => "$column_alias = ?", 'bind' => true, 'id' => $_SESSION['user_id']];
    }
}
?>