<?php
session_start();
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/helpers.php';

/* --- Ambil setting --- */
$setting = fetch_settings($mysqli);
$harga  = setting_value($setting, 'harga');
$berasV = setting_value($setting, 'beras');
$jagungV = setting_value($setting, 'jagung');

/* --- MODE PUBLIK (tanpa login) --- */
if (empty($_SESSION['username'])) {
    $query = "
        SELECT 
            f.id,
            f.kepala AS nama_kepala,
            COUNT(m.id) AS jumlah_anggota,
            COALESCE(SUM(m.uang),0) AS jml_uang,
            COALESCE(SUM(m.beras),0) AS jml_beras,
            COALESCE(SUM(m.jagung),0) AS jml_jagung,
            COALESCE(f.infaq,0) AS infaq
        FROM families f
        LEFT JOIN members m ON f.id = m.family_id
        GROUP BY f.id
        ORDER BY f.id ASC
    ";
    $result = $mysqli->query($query);

    // total keseluruhan (hindari dobel infaq per anggota)
    $totalQ = "
        SELECT 
            SUM(a.jml_uang * ?)   AS total_uang,
            SUM(a.jml_beras * ?)  AS total_beras,
            SUM(a.jml_jagung * ?) AS total_jagung,
            SUM(a.infaq)          AS total_infaq
        FROM (
            SELECT 
                COALESCE(SUM(m.uang),0)   AS jml_uang,
                COALESCE(SUM(m.beras),0)  AS jml_beras,
                COALESCE(SUM(m.jagung),0) AS jml_jagung,
                COALESCE(f.infaq,0)       AS infaq
            FROM families f
            LEFT JOIN members m ON f.id = m.family_id
            GROUP BY f.id
        ) a
    ";
    $stmtT = $mysqli->prepare($totalQ);
    $stmtT->bind_param("ddd", $harga, $berasV, $jagungV);
    $stmtT->execute();
    $total = $stmtT->get_result()->fetch_assoc();
    $stmtT->close();

    /* --- MODE LOGIN (khusus keluarga tertentu) --- */
} else {
    $username = $_SESSION['username']; // diasumsikan = nama kepala keluarga
    $query = "
        SELECT 
            f.id,
            f.kepala AS nama_kepala,
            COUNT(m.id) AS jumlah_anggota,
            COALESCE(SUM(m.uang),0) AS jml_uang,
            COALESCE(SUM(m.beras),0) AS jml_beras,
            COALESCE(SUM(m.jagung),0) AS jml_jagung,
            COALESCE(f.infaq,0) AS infaq
        FROM families f
        LEFT JOIN members m ON f.id = m.family_id
        WHERE f.kepala = ?
        GROUP BY f.id
    ";
    $stmt = $mysqli->prepare($query);
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1.0">
    <title>Transparansi Infaq & Zakat Masyarakat</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f7f7f7;
            padding: 20px;
        }

        h1 {
            text-align: center;
            color: #2c3e50;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 25px;
            background: white;
            box-shadow: 0 0 5px rgba(0, 0, 0, 0.1);
        }

        th,
        td {
            border: 1px solid #ddd;
            padding: 10px;
            text-align: center;
        }

        th {
            background: #27ae60;
            color: white;
        }

        .total {
            margin-top: 20px;
            background: #eafaf1;
            padding: 15px;
            border-radius: 8px;
        }

        .logout {
            text-align: right;
            margin-bottom: 10px;
        }
    </style>
</head>

<body>
    <h1>Data Infaq & Zakat Masyarakat</h1>

    <?php if (!empty($_SESSION['username'])): ?>
        <div class="logout">
            <p>Login sebagai: <strong><?= htmlspecialchars($_SESSION['username']) ?></strong></p>
            <a href="logout.php">Keluar</a>
        </div>
    <?php endif; ?>

    <table>
        <tr>
            <th>Nama Kepala Keluarga</th>
            <th>Jumlah Anggota</th>
            <th>Uang (Rp)</th>
            <th>Beras (kg)</th>
            <th>Jagung (kg)</th>
            <th>Infaq (Rp)</th>
        </tr>

        <?php while ($row = $result->fetch_assoc()): ?>
            <?php
            $jmlU = (int)($row['jml_uang'] ?? 0);
            $jmlB = (int)($row['jml_beras'] ?? 0);
            $jmlJ = (int)($row['jml_jagung'] ?? 0);
            $infaq = (int)($row['infaq'] ?? 0);

            $uangRp   = $jmlU * $harga;
            $berasKg  = $jmlB * $berasV;
            $jagungKg = $jmlJ * $jagungV;
            ?>
            <tr>
                <td>
                    <strong><?= htmlspecialchars($row['nama_kepala']); ?></strong><br>
                    <small>(+ <?= max(0, (int)$row['jumlah_anggota'] - 1); ?> orang)</small>
                </td>
                <td><?= (int)$row['jumlah_anggota']; ?></td>
                <td><?= format_rupiah((float)$uangRp); ?></td>
                <td><?= $berasKg; ?></td>
                <td><?= $jagungKg; ?></td>
                <td><?= format_rupiah((float)$infaq); ?></td>
            </tr>
        <?php endwhile; ?>
    </table>

    <?php if (empty($_SESSION['username'])): ?>
        <div class="total">
            <h3>Total Keseluruhan:</h3>
            <p><strong>Uang:</strong> Rp <?= format_rupiah((float)($total['total_uang'] ?? 0)); ?></p>
            <p><strong>Beras:</strong> <?= (float)($total['total_beras'] ?? 0); ?> kg</p>
            <p><strong>Jagung:</strong> <?= (float)($total['total_jagung'] ?? 0); ?> kg</p>
            <p><strong>Infaq:</strong> Rp <?= format_rupiah((float)($total['total_infaq'] ?? 0)); ?></p>
        </div>
    <?php endif; ?>

</body>

</html>