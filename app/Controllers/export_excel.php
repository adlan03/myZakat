<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__) . '/Helpers/helpers.php';

require_login();

$type = $_GET['type'] ?? 'summary';
$setting = fetch_settings($mysqli);
$harga  = setting_value($setting, 'harga');
$berasV = setting_value($setting, 'beras');
$jagungV = setting_value($setting, 'jagung');

$filename = "export_{$type}_" . date('Ymd_His') . ".xls";
header("Content-Type: application/vnd.ms-excel; charset=UTF-8");
header("Content-Disposition: attachment; filename=\"$filename\"");
echo "\xEF\xBB\xBF"; // BOM untuk UTF-8

// =====================
// CSS LANGSUNG DI HTML
// =====================
echo <<<CSS
<style>
table {
  border-collapse: collapse;
  width: 100%;
  font-family: Arial, sans-serif;
}
th {
  background: #fdd835;
  color: #176b41;
  font-weight: bold;
  border: 1px solid #999;
  padding: 8px;
  text-align: center;
}
td {
  border: 1px solid #999;
  padding: 8px;
  text-align: center;
}
tr:nth-child(even) { background: #f5f5f5; }
tr:hover { background: #e8f5e9; }
h2 {
  color: #176b41;
}
</style>
CSS;

// =====================
// MODE RINGKAS
// =====================
if ($type === 'summary') {
    echo "<h2>Data Ringkas Infaq & Zakat</h2>";
    echo "<table><thead><tr>
        <th>No</th><th>Kepala Keluarga</th><th>Jumlah Anggota</th>
        <th>Uang (Rp)</th><th>Beras (kg)</th><th>Jagung (kg)</th><th>Infaq (Rp)</th>
    </tr></thead><tbody>";

    $sql = "
        SELECT 
            f.id, f.kepala, COUNT(m.id) AS jumlah_anggota,
            COALESCE(SUM(m.uang),0) AS jml_uang,
            COALESCE(SUM(m.beras),0) AS jml_beras,
            COALESCE(SUM(m.jagung),0) AS jml_jagung,
            COALESCE(f.infaq,0) AS infaq
        FROM families f
        LEFT JOIN members m ON f.id = m.family_id
        GROUP BY f.id ORDER BY f.id ASC";
    $res = $mysqli->query($sql);

    $no = 1;
    $grandUang = 0;
    $grandBeras = 0;
    $grandJagung = 0;
    $grandInfaq = 0;
    while ($r = $res->fetch_assoc()) {
        $uangRp = $r['jml_uang'] * $harga;
        $beras = $r['jml_beras'] * $berasV;
        $jagung = $r['jml_jagung'] * $jagungV;
        $infaq = $r['infaq'];
        echo "<tr>
            <td>{$no}</td>
            <td>{$r['kepala']}</td>
            <td>{$r['jumlah_anggota']}</td>
            <td>" . format_rupiah((float)$uangRp) . "</td>
            <td>{$beras}</td>
            <td>{$jagung}</td>
            <td>" . format_rupiah((float)$infaq) . "</td>
        </tr>";
        $grandUang += $uangRp;
        $grandBeras += $beras;
        $grandJagung += $jagung;
        $grandInfaq += $infaq;
        $no++;
    }

    echo "<tr style='font-weight:bold;background:#fff59d;'>
        <td colspan='3'>TOTAL</td>
        <td>" . format_rupiah((float)$grandUang) . "</td>
        <td>{$grandBeras}</td>
        <td>{$grandJagung}</td>
        <td>" . format_rupiah((float)$grandInfaq) . "</td>
    </tr>";

    echo "</tbody></table>";
    exit;
}

// ====== MODE DETAIL (tanpa kolom ID, dengan total akhir) ======
if ($type === 'detail') {
    echo "<h2>Data Detail Anggota</h2>";
    echo "<table><thead><tr>
        <th>No</th>
        <th>Kepala</th>
        <th>Nama Anggota</th>
        <th>JK</th>
        <th>Pilihan</th>
        <th>Uang (Rp)</th>
        <th>Beras (kg)</th>
        <th>Jagung (kg)</th>
        <th>Infaq (Rp)</th>
    </tr></thead><tbody>";

    $sql = "
        SELECT 
            f.id AS family_id,
            f.kepala,
            f.infaq,
            m.nama,
            m.jk,
            m.uang, m.beras, m.jagung
        FROM families f
        LEFT JOIN members m ON f.id = m.family_id
        ORDER BY f.id ASC, m.id ASC
    ";
    $res = $mysqli->query($sql);

    $no = 1;
    $lastFamily = null;

    // variabel total keseluruhan
    $totalUang = 0;
    $totalBeras = 0;
    $totalJagung = 0;
    $totalInfaq = 0;

    while ($r = $res->fetch_assoc()) {
        // tampilkan infaq hanya sekali per keluarga
        $infaqOut = '';
        if ($lastFamily !== $r['family_id']) {
            $infaqOut = (int)$r['infaq'];
            $totalInfaq += $infaqOut;
            $lastFamily = $r['family_id'];
        }

        // pilihan per anggota
        $pilihan = [];
        $uangRp = 0;
        $berasKg = 0;
        $jagungKg = 0;

        if ((int)$r['uang'] === 1) {
            $pilihan[] = 'Uang';
            $uangRp = $harga;
            $totalUang += $uangRp;
        }
        if ((int)$r['beras'] === 1) {
            $pilihan[] = 'Beras';
            $berasKg = $berasV;
            $totalBeras += $berasKg;
        }
        if ((int)$r['jagung'] === 1) {
            $pilihan[] = 'Jagung';
            $jagungKg = $jagungV;
            $totalJagung += $jagungKg;
        }

        echo "<tr>
            <td>{$no}</td>
            <td>{$r['kepala']}</td>
            <td>{$r['nama']}</td>
            <td>{$r['jk']}</td>
            <td>" . implode('+', $pilihan) . "</td>
            <td>" . format_rupiah((float)$uangRp) . "</td>
            <td>{$berasKg}</td>
            <td>{$jagungKg}</td>
            <td>" . ($infaqOut ? format_rupiah((float)$infaqOut) : '') . "</td>
        </tr>";
        $no++;
    }

    // tambahkan baris total keseluruhan
    echo "<tr style='font-weight:bold; background:#fff59d;'>
        <td colspan='5' style='text-align:right;'>TOTAL KESELURUHAN</td>
        <td>Rp " . format_rupiah((float)$totalUang) . "</td>
        <td>{$totalBeras}</td>
        <td>{$totalJagung}</td>
        <td>Rp " . format_rupiah((float)$totalInfaq) . "</td>
    </tr>";

    echo "</tbody></table>";
    exit;
}
