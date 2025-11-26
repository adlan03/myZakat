<?php
require_once __DIR__ . '/config.php'; // gunakan path absolut agar selalu terbaca
session_start();
ob_start();

// pastikan koneksi tersedia
if (!isset($mysqli) || !$mysqli instanceof mysqli) {
    die("Koneksi database tidak terbentuk. Pastikan config.php sudah benar.");
}

/* load setting */
$res = $mysqli->query("SELECT harga, beras, jagung FROM settings WHERE id=1");
$setting = $res->fetch_assoc();
if (!$setting) $setting = ['harga' => 35000, 'beras' => 3.5, 'jagung' => 2.0];

/* load semua families + anggota */
function get_all_families($mysqli)
{
    $out = [];
    $fres = $mysqli->query("SELECT * FROM families ORDER BY id ASC");
    while ($f = $fres->fetch_assoc()) {
        $fid = $f['id'];
        $mres = $mysqli->query("SELECT * FROM members WHERE family_id = " . intval($fid) . " ORDER BY id ASC");
        $members = [];
        while ($m = $mres->fetch_assoc()) $members[] = $m;
        $f['anggota'] = $members;
        $out[] = $f;
    }
    return $out;
}

/* HAPUS keluarga */
if (isset($_POST['hapus_index'])) {
    $id = intval($_POST['hapus_index']);
    // foreign key dengan ON DELETE CASCADE akan hapus members otomatis
    $stmt = $mysqli->prepare("DELETE FROM families WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close();
    header("Location: lihat_data.php");
    exit;
}

/* RESET semua */
if (isset($_POST['reset_semua'])) {
    $mysqli->query("DELETE FROM members");
    $mysqli->query("DELETE FROM families");
    header("Location: lihat_data.php");
    exit;
}

/* UPDATE keluarga (edit) */
if (isset($_POST['update_index'])) {
    $fid = intval($_POST['update_index']);
    $infaq = isset($_POST['infaq']) ? 15000 : 0;

    // delete existing members for that family
    $stmt = $mysqli->prepare("DELETE FROM members WHERE family_id = ?");
    $stmt->bind_param("i", $fid);
    $stmt->execute();
    $stmt->close();

    // insert new members from form (nama[], jk[], uang[], beras[], jagung[])
    if (!empty($_POST['nama']) && is_array($_POST['nama'])) {
        $stmt = $mysqli->prepare("INSERT INTO members (family_id, nama, jk, uang, beras, jagung) VALUES (?, ?, ?, ?, ?, ?)");
        foreach ($_POST['nama'] as $i => $nama) {
            if (trim($nama) === "") continue;
            $jk = $_POST['jk'][$i] ?? '';
            $uang = isset($_POST['uang'][$i]) ? 1 : 0;
            $beras = isset($_POST['beras'][$i]) ? 1 : 0;
            $jagung = isset($_POST['jagung'][$i]) ? 1 : 0;
            $stmt->bind_param("issiii", $fid, $nama, $jk, $uang, $beras, $jagung);
            $stmt->execute();
        }
        $stmt->close();
    }

    // update infaq on families row
    $stmt = $mysqli->prepare("UPDATE families SET infaq = ? WHERE id = ?");
    $stmt->bind_param("ii", $infaq, $fid);
    $stmt->execute();
    $stmt->close();

    header("Location: lihat_data.php");
    exit;
}

/* fetch data */
$data = get_all_families($mysqli);

/* totals overall */
$totalUang = 0;
$totalBeras = 0;
$totalJagung = 0;
$totalInfaq = 0;
foreach ($data as $family) {
    foreach ($family['anggota'] as $m) {
        if (!empty($m['uang'])) $totalUang += $setting['harga'];
        if (!empty($m['beras'])) $totalBeras += $setting['beras'];
        if (!empty($m['jagung'])) $totalJagung += $setting['jagung'];
    }
    $totalInfaq += intval($family['infaq']);
}
?>
<!doctype html>
<html lang="id">

<head>
    <meta charset="utf-8">
    <title>Lihat Data - Infaq</title>
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <link rel="stylesheet" href="style.css">
</head>

<body>
    <header>Lihat / Edit Data</header>

    <div class="container">
        <aside>
            <ul class="menu">
                <li><a href="index.php">← Kembali</a></li>
            </ul>
        </aside>

        <section class="main">
            <h2>Data Keluarga Tersimpan</h2>

            <?php if (empty($data)): ?>
                <p>Belum ada data.</p>
            <?php else: ?>
                <?php foreach ($data as $i => $family): ?>
                    <div class="card family-card">
                        <h3><?= htmlspecialchars($family['kepala']) ?></h3>
                        <form method="post" class="edit-family">
                            <input type="hidden" name="update_index" value="<?= intval($family['id']) ?>">
                            <table class="mini">
                                <thead>
                                    <tr>
                                        <th>No</th>
                                        <th>Nama</th>
                                        <th>JK</th>
                                        <th>Uang</th>
                                        <th>Beras</th>
                                        <th>Jagung</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($family['anggota'] as $j => $m): ?>
                                        <tr>
                                            <td><?= $j + 1 ?></td>
                                            <td><input type="text" name="nama[]" value="<?= htmlspecialchars($m['nama']) ?>"></td>
                                            <td>
                                                <label><input type="radio" name="jk[<?= $j ?>]" value="L" <?= ($m['jk'] == "L") ? "checked" : "" ?>>L</label>
                                                <label><input type="radio" name="jk[<?= $j ?>]" value="P" <?= ($m['jk'] == "P") ? "checked" : "" ?>>P</label>
                                            </td>
                                            <td><input type="checkbox" name="uang[<?= $j ?>]" <?= !empty($m['uang']) ? "checked" : "" ?>></td>
                                            <td><input type="checkbox" name="beras[<?= $j ?>]" <?= !empty($m['beras']) ? "checked" : "" ?>></td>
                                            <td><input type="checkbox" name="jagung[<?= $j ?>]" <?= !empty($m['jagung']) ? "checked" : "" ?>></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>

                            <div class="row">
                                <label><input type="checkbox" name="infaq" <?= (!empty($family['infaq']) ? "checked" : "") ?>> Infaq (Rp 15.000)</label>
                                <button type="submit">Simpan Perubahan</button>
                            </div>



                        </form>

                        <div class="family-totals">
                            <?php
                            $u = 0;
                            $b = 0;
                            $jg = 0;
                            $inf = intval($family['infaq']);
                            foreach ($family['anggota'] as $m) {
                                if (!empty($m['uang'])) $u += $setting['harga'];
                                if (!empty($m['beras'])) $b += $setting['beras'];
                                if (!empty($m['jagung'])) $jg += $setting['jagung'];
                            }
                            ?>
                            <p>Total Uang: Rp <?= number_format($u, 0, ',', '.') ?></p>
                            <p>Total Beras: <?= $b ?> kg</p>
                            <p>Total Jagung: <?= $jg ?> kg</p>
                            <p>Total Infaq: Rp <?= number_format($inf, 0, ',', '.') ?></p>
                        </div>

                        <form method="post" onsubmit="return confirm('Hapus keluarga ini?')">
                            <input type="hidden" name="hapus_index" value="<?= intval($family['id']) ?>">
                            <button type="submit" class="danger">Hapus Keluarga</button>
                        </form>
                    </div>
                <?php endforeach; ?>

                <div class="card totals-all">
                    <h3>Total Keseluruhan</h3>
                    <p>Total Uang: Rp <?= number_format($totalUang, 0, ',', '.') ?></p>
                    <p>Total Beras: <?= $totalBeras ?> kg</p>
                    <p>Total Jagung: <?= $totalJagung ?> kg</p>
                    <p>Total Infaq: Rp <?= number_format($totalInfaq, 0, ',', '.') ?></p>

                    <form method="post" onsubmit="return confirm('Reset semua data?')">
                        <button type="submit" name="reset_semua" class="danger">🔄 Reset Semua Data</button>
                        <div class="row" style="margin:12px 0;">
                            <a class="button" href="export_excel.php?type=summary">⬇️ Export Ringkas (CSV)</a>
                            <a class="button" href="export_excel.php?type=detail">⬇️ Export Detail (CSV)</a>
                      </div>
                    </form>


                </div>
            <?php endif; ?>
        </section>
    </div>

    <footer>&copy; 2024 Sistem Infaq Keluarga</footer>
</body>

</html>