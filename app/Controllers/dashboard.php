<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
ob_start();

require_once dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__) . '/Helpers/helpers.php';
require_once __DIR__ . '/family_service.php';

require_login();

/**
 * Simpan perubahan setting dengan validasi lock.
 */
function handle_setting_update(mysqli $db): void
{
    if (is_settings_locked($db)) {
        redirect_to('index.php', 'err=locked');
    }

    $harga  = filter_var($_POST['harga'] ?? 0, FILTER_VALIDATE_INT);
    $beras  = filter_var($_POST['beras'] ?? 0, FILTER_VALIDATE_FLOAT);
    $jagung = filter_var($_POST['jagung'] ?? 0, FILTER_VALIDATE_FLOAT);

    $harga = $harga !== false ? $harga : 0;
    $beras = $beras !== false ? $beras : 0.0;
    $jagung = $jagung !== false ? $jagung : 0.0;

    update_settings($db, $harga, $beras, $jagung);

    redirect_to('index.php', 'ok=setting');
}

/**
 * Kunci atau buka kunci setting.
 */
function toggle_setting_lock(mysqli $db, bool $locked): void
{
    set_setting_lock($db, $locked);

    redirect_to('index.php');
}

/**
 * Tentukan nama kepala keluarga dengan fallback otomatis.
 */
function resolve_head_name(array $names, mysqli $db): string
{
    $candidate = trim($names[0] ?? '');
    if ($candidate !== '') {
        return $candidate;
    }

    $res = $db->query("SELECT COUNT(*) AS cnt FROM families");
    $row = $res ? $res->fetch_assoc() : ['cnt' => 0];
    $nextIndex = intval($row['cnt'] ?? 0) + 1;

    return "Kepala Keluarga " . $nextIndex;
}

/**
 * Simpan data keluarga beserta anggota.
 */
function handle_family_submission(mysqli $db): void
{
    $members = collect_members_from_post($_POST);
    if (empty($members)) {
        redirect_to('index.php', 'err=empty');
    }

    $kepala = resolve_head_name($_POST['nama'] ?? [], $db);
    $infaq = isset($_POST['infaq']) ? INFAQ_VALUE : 0;

    save_family($db, $kepala, $infaq, $members);

    redirect_to('index.php', 'ok=saved');
}

/**
 * Dispatcher aksi POST agar kode utama lebih bersih.
 */
function handle_post_request(mysqli $db): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        return;
    }

    if (isset($_POST['save_setting'])) {
        handle_setting_update($db);
    }

    if (isset($_POST['lock'])) {
        toggle_setting_lock($db, true);
    }

    if (isset($_POST['unlock'])) {
        toggle_setting_lock($db, false);
    }

    if (isset($_POST['simpan'])) {
        handle_family_submission($db);
    }
}

handle_post_request($mysqli);

/* ----- load setting untuk UI & JS ----- */
$setting = fetch_settings($mysqli);
?>
<!doctype html>
<html lang="id">

<head>
    <meta charset="utf-8">
    <title>Dashboard - Input Keluarga (MySQL)</title>
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <link rel="stylesheet" href="assets/css/style.css">
</head>

<body>
    <header>Dashboard</header>

    <div class="container">
        <aside>
            <ul class="menu">
                <li><a href="index.php" class="active">Dashboard</a></li>
                <li><a href="index.php?page=lihat_data">Lihat / Edit Data</a></li>
                <li><a href="logout.php">Keluar</a></li>
            </ul>
        </aside>

        <section class="main">
            <h2>Input Data Keluarga</h2>

            <!-- setting -->
            <form method="post" class="card setting-form">
                <h4>Harga & Barang (tetap)</h4>
                <label>Harga Uang per anggota (Rp)
                    <input type="number" name="harga" step="100" value="<?= htmlspecialchars((string)$setting['harga']) ?>" <?= $setting['locked'] ? 'readonly' : '' ?>>
                </label>
                <label>Beras (kg per anggota)
                    <input type="number" name="beras" step="0.1" value="<?= htmlspecialchars((string)$setting['beras']) ?>" <?= $setting['locked'] ? 'readonly' : '' ?>>
                </label>
                <label>Jagung (kg per anggota)
                    <input type="number" name="jagung" step="0.1" value="<?= htmlspecialchars((string)$setting['jagung']) ?>" <?= $setting['locked'] ? 'readonly' : '' ?>>
                </label>
                <div class="row">
                    <?php if (!$setting['locked']): ?>
                        <button type="submit" name="save_setting">Simpan Setting</button>
                        <button type="submit" name="lock">🔒 Kunci</button>
                    <?php else: ?>
                        <button type="submit" name="unlock">🔓 Buka Kunci</button>
                    <?php endif; ?>
                </div>
            </form>

            <hr>

            <!-- form keluarga -->
            <form method="post" id="formKeluarga" class="card">
                <table id="tabelKeluarga">
                    <thead>
                        <tr>
                            <th>Nama Anggota</th>
                            <th>JK</th>
                            <th>Uang<br><input type="checkbox" id="checkAllUang"></th>
                            <th>Beras<br><input type="checkbox" id="checkAllBeras"></th>
                            <th>Jagung<br><input type="checkbox" id="checkAllJagung"></th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><input type="text" name="nama[]" required></td>
                            <td>
                                <label><input type="radio" name="jk[0]" value="L">L</label>
                                <label><input type="radio" name="jk[0]" value="P">P</label>
                            </td>
                            <td><input type="checkbox" class="uang" name="uang[0]"></td>
                            <td><input type="checkbox" class="beras" name="beras[0]"></td>
                            <td><input type="checkbox" class="jagung" name="jagung[0]"></td>
                            <td><button type="button" class="btn-del" disabled>Hapus</button></td>
                        </tr>
                    </tbody>
                </table>

                <div class="row">
                    <button type="button" id="tambah">+ Tambah Anggota</button>
                    <label style="margin-left:12px;">
                        <input type="checkbox" id="infaq" name="infaq"> Tambahkan Infaq (Rp 15.000)
                    </label>
                </div>

                <div class="totals card-small">
                    <p id="totalUang">Total Uang: Rp 0</p>
                    <p id="totalBeras">Total Beras: 0 kg</p>
                    <p id="totalJagung">Total Jagung: 0 kg</p>
                    <p id="totalInfaq">Total Infaq: Rp 0</p>
                </div>

                <div class="calc card-small">
                    <label>Uang Diterima (Rp): <input type="number" id="uangDiterima" step="500"></label>
                    <label>Kembalian: <input type="text" id="kembalian" readonly></label>
                </div>

                <div class="row">
                    <button type="submit" name="simpan">💾 Simpan Data (Satu Keluarga)</button>
                </div>
            </form>

        </section>
    </div>

    <footer>&copy; 2024 Sistem Infaq Keluarga</footer>

    <script>
        const SETTING = {
            harga: <?= json_encode((float)$setting['harga']) ?>,
            beras: <?= json_encode((float)$setting['beras']) ?>,
            jagung: <?= json_encode((float)$setting['jagung']) ?>,
            infaqValue: 15000
        };
    </script>
    <script src="assets/js/keluarga.js"></script>
</body>

</html>