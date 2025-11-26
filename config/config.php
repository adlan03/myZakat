<?php
// config.php — koneksi database & setting global
$host = "localhost";
$user = "root";
$pass = "";
$db   = "db_infaq";

$mysqli = new mysqli($host, $user, $pass, $db);
if ($mysqli->connect_errno) {
    die("Koneksi database gagal: " . $mysqli->connect_error);
}
$mysqli->set_charset('utf8mb4');
