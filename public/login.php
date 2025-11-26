<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login Aplikasi Zakat</title>
    <link rel="stylesheet" href="assets/css/login.css">
    <script src="https://kit.fontawesome.com/a076d05399.js" crossorigin="anonymous"></script>
</head>

<body>
    <div class="container">
        <!-- Bagian kiri -->
        <section class="left-box">
            <h1>Selamat Datang di<br><span>Aplikasi Zakat</span></h1>
            <img src="bb25921d-a881-4d0d-92c0-cf347a27788e.png" alt="Gambar Islami">
            <div class="ketupat"></div>
            <div class="ketupat ketupat2"></div>
        </section>

        <!-- Bagian kanan (form login) -->
        <section class="login-box">
            <h2>Login Aplikasi</h2>
            <form method="post" action="index.php?page=ceklogin">
                <div class="input-group">
                    <i class="fas fa-user"></i>
                    <input type="text" placeholder="Username" name="username" required>
                </div>
                <div class="input-group">
                    <i class="fas fa-lock"></i>
                    <input type="password" placeholder="Password" name="password" required>
                </div>
                <input type="submit" value="Login">
            </form>
            <div class="user_masyarakat">
                <label for="masyarakat">Lihat Data Sebagai Masyarakat</label>
                <button type="button" onclick="window.location.href='index.php?page=masyarakat'">Klik Ini</button>
            </div>

        </section>
    </div>
</body>

</html>