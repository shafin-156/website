<?php
session_start();

$servername = "sql107.byetcluster.com:3306"; 
$username = "if0_40979562";             
$password = "XaqSrFTid8E57SV"; 
$dbname = "if0_40979562_BIST";

if (!isset($_COOKIE['db_auth'])) {
    $conn = new mysqli($servername, $username, $password, $dbname);
    
    if ($conn->connect_error) {
        exit;
    }
    
    setcookie("db_auth", md5($username), time() + 3600, "/");
} else {
    $conn = new mysqli($servername, $username, $password, $dbname);
    if ($conn->connect_error) {
        setcookie("db_auth", "", time() - 3600, "/");
        exit;
    }
}
?>