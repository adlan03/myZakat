<?php
session_start();
include 'config.php';

$username = $_POST['username'];
$password = md5($_POST['password']);

$stmt = $mysqli->prepare("SELECT * FROM users WHERE username=? AND password=?");
$stmt->bind_param("ss", $username, $password);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 1) {
    $_SESSION['login'] = 1;
    $_SESSION['username'] = $username;
    header('location:index.php');
    exit;
} else {
    echo "<script>alert('Username atau password salah');</script>";
    echo "<meta http-equiv='refresh' content='1;url=login.php'>";
}
?>
