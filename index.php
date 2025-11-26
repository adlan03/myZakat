<?php
session_start();
ob_start();

// Wajib login
if (empty($_SESSION['username'])) {
    echo "<script>alert('Anda harus login terlebih dahulu');</script>";
    echo "<meta http-equiv='refresh' content='0;url=login.php'>";
    exit;
}

require_once 'config.php';

/* ----- helper: get setting ----- */
function get_setting($mysqli)
{
    $sql = "SELECT harga, beras, jagung, locked FROM settings WHERE id = 1";
    $res = $mysqli->query($sql);
    if ($res && $row = $res->fetch_assoc()) return $row;
    return ['harga' => 35000, 'beras' => 3.5, 'jagung' => 2.0, 'locked' => 0];
}

/* ----- update setting (enforce lock di server) ----- */
if (isset($_POST['save_setting'])) {
    $isLocked = (int)$mysqli->query("SELECT locked FROM settings WHERE id=1")->fetch_column();
    if ($isLocked) {
        header("Location: index.php?err=locked");
        exit;
    }

    $harga  = intval($_POST['harga'] ?? 0);
    $beras  = floatval($_POST['beras'] ?? 0);
    $jagung = floatval($_POST['jagung'] ?? 0);

    $stmt = $mysqli->prepare("UPDATE settings SET harga=?, beras=?, jagung=? WHERE id=1");
    $stmt->bind_param("idd", $harga, $beras, $jagung);
    $stmt->execute();
    $stmt->close();

    header("Location: index.php?ok=setting");
    exit;
}

if (isset($_POST['lock'])) {
    $mysqli->query("UPDATE settings SET locked=1 WHERE id=1");
    header("Location: index.php");
    exit;
}
if (isset($_POST['unlock'])) {
    $mysqli->query("UPDATE settings SET locked=0 WHERE id=1");
    header("Location: index.php");
    exit; // <- diperbaiki (tadinya "index")
}

/* ----- simpan 1 keluarga dengan banyak anggota ----- */
if (isset($_POST['simpan'])) {
    // kompilasi anggota dari form
    $anggota = [];
    if (!empty($_POST['nama']) && is_array($_POST['nama'])) {
        foreach ($_POST['nama'] as $i => $nama) {
            $nama = trim($nama);
            if ($nama === "") continue;
            $anggota[] = [
                'nama'   => $nama,
                'jk'     => $_POST['jk'][$i] ?? '',
                'uang'   => isset($_POST['uang'][$i]) ? 1 : 0,
                'beras'  => isset($_POST['beras'][$i]) ? 1 : 0,
                'jagung' => isset($_POST['jagung'][$i]) ? 1 : 0
            ];
        }
    }

    if (!empty($anggota)) {
        // nama kepala keluarga = anggota pertama
        $kepala = trim($_POST['nama'][0] ?? '');
        if ($kepala === '') {
            // fallback bila kosong
            $res = $mysqli->query("SELECT COUNT(*) AS cnt FROM families");
            $row = $res->fetch_assoc();
            $nextIndex = intval($row['cnt']) + 1;
            $kepala = "Kepala Keluarga " . $nextIndex;
        }

        // infaq opsional
        $infaq = isset($_POST['infaq']) ? 15000 : 0;

        // simpan data keluarga
        $stmt = $mysqli->prepare("INSERT INTO families (kepala, infaq) VALUES (?, ?)");
        $stmt->bind_param("si", $kepala, $infaq);
        $stmt->execute();
        $family_id = $stmt->insert_id;
        $stmt->close();

        // simpan anggota
        $stmt = $mysqli->prepare("
            INSERT INTO members (family_id, nama, jk, uang, beras, jagung)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        foreach ($anggota as $m) {
            $stmt->bind_param("issiii", $family_id, $m['nama'], $m['jk'], $m['uang'], $m['beras'], $m['jagung']);
            $stmt->execute();
        }
        $stmt->close();
    }

    header("Location: index.php?ok=saved");
    exit;
}

/* ----- load setting untuk UI & JS ----- */
$setting = get_setting($mysqli);
?>
<!doctype html>
<html lang="id">

<head>
    <meta charset="utf-8">
    <title>Dashboard - Input Keluarga (MySQL)</title>
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <link rel="stylesheet" href="style.css">
</head>

<body>
    <header>Dashboard</header>

    <div class="container">
        <aside>
            <ul class="menu">
                <li><a href="dashboard.php" class="active">Dashboard</a></li>
                <li><a href="lihat_data.php">Lihat / Edit Data</a></li>
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
    <script src="keluarga.js"></script>
</body>

</html>