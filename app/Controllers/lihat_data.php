<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
ob_start();
require_once dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__) . '/Helpers/helpers.php';
require_once __DIR__ . '/family_service.php';

// pastikan koneksi tersedia
if (!isset($mysqli) || !$mysqli instanceof mysqli) {
    die("Koneksi database tidak terbentuk. Pastikan config.php sudah benar.");
}

/* load setting */
$setting = fetch_settings($mysqli);

/* HAPUS keluarga */
if (isset($_POST['hapus_index'])) {
    $id = intval($_POST['hapus_index']);
    // foreign key dengan ON DELETE CASCADE akan hapus members otomatis
    delete_family($mysqli, $id);
    header("Location: index.php?page=lihat_data");
    exit;
}

/* RESET semua */
if (isset($_POST['reset_semua'])) {
    reset_all_families($mysqli);
    header("Location: index.php?page=lihat_data");
    exit;
}

/* UPDATE keluarga (edit) */
if (isset($_POST['update_index'])) {
    $fid = intval($_POST['update_index']);
    $infaq = isset($_POST['infaq']) ? INFAQ_VALUE : 0;

    $members = collect_members_from_post($_POST);
    replace_family($mysqli, $fid, $infaq, $members);

    header("Location: index.php?page=lihat_data");
    exit;
}

/* fetch data */
$data = fetch_all_families($mysqli);

$overallTotals = calculate_overall_totals($data, $setting);
?>
<!doctype html>
<html lang="id">

<head>
    <meta charset="utf-8">
    <title>Lihat Data - Infaq</title>
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <link rel="stylesheet" href="assets/css/style.css">
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
                            <?php $familyTotals = calculate_family_totals($family, $setting); ?>
                            <p>Total Uang: Rp <?= format_rupiah($familyTotals['uang']) ?></p>
                            <p>Total Beras: <?= $familyTotals['beras'] ?> kg</p>
                            <p>Total Jagung: <?= $familyTotals['jagung'] ?> kg</p>
                            <p>Total Infaq: Rp <?= format_rupiah((float)$familyTotals['infaq']) ?></p>
                        </div>

                        <form method="post" onsubmit="return confirm('Hapus keluarga ini?')">
                            <input type="hidden" name="hapus_index" value="<?= intval($family['id']) ?>">
                            <button type="submit" class="danger">Hapus Keluarga</button>
                        </form>
                    </div>
                <?php endforeach; ?>

                    <div class="card totals-all">
                        <h3>Total Keseluruhan</h3>
                        <p>Total Uang: Rp <?= format_rupiah($overallTotals['uang']) ?></p>
                        <p>Total Beras: <?= $overallTotals['beras'] ?> kg</p>
                        <p>Total Jagung: <?= $overallTotals['jagung'] ?> kg</p>
                        <p>Total Infaq: Rp <?= format_rupiah((float)$overallTotals['infaq']) ?></p>

                    <form method="post" onsubmit="return confirm('Reset semua data?')">
                        <button type="submit" name="reset_semua" class="danger">🔄 Reset Semua Data</button>
                        <div class="row" style="margin:12px 0;">
                            <a class="button" href="index.php?page=export_excel&type=summary">⬇️ Export Ringkas (CSV)</a>
                            <a class="button" href="index.php?page=export_excel&type=detail">⬇️ Export Detail (CSV)</a>
                      </div>
                    </form>


                </div>
            <?php endif; ?>
        </section>
    </div>

    <footer>&copy; 2024 Sistem Infaq Keluarga</footer>
</body>

</html>