<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

session_destroy();
echo "<script>alert('Anda telah logout')</script>";
echo "<meta http-equiv='refresh' content='0;url=login.php'>";

?>
