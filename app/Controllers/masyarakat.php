<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__) . '/Helpers/helpers.php';

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

// Koleksi data untuk tabel dan ringkasan
$families = [];
while ($row = $result->fetch_assoc()) {
    $families[] = $row;
}

if (empty($_SESSION['username'])) {
    $aggregate = [
        'uang'   => (float)($total['total_uang'] ?? 0),
        'beras'  => (float)($total['total_beras'] ?? 0),
        'jagung' => (float)($total['total_jagung'] ?? 0),
        'infaq'  => (float)($total['total_infaq'] ?? 0),
    ];
} else {
    $aggregate = ['uang' => 0.0, 'beras' => 0.0, 'jagung' => 0.0, 'infaq' => 0.0];
    foreach ($families as $row) {
        $aggregate['uang']   += ((int)($row['jml_uang'] ?? 0)) * $harga;
        $aggregate['beras']  += ((float)($row['jml_beras'] ?? 0)) * $berasV;
        $aggregate['jagung'] += ((float)($row['jml_jagung'] ?? 0)) * $jagungV;
        $aggregate['infaq']  += (float)($row['infaq'] ?? 0);
    }
}

$totalFamilies = count($families);
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1.0">
    <title>Platform Zakat Harmoni</title>
    <link rel="stylesheet" href="assets/css/public.css">
</head>

<body>
    <nav class="navbar">
        <div class="container navbar-inner">
            <div class="brand"><span class="dot"></span> Zakat Harmoni</div>
            <div class="nav-links">
                <a href="#hero">Beranda</a>
                <a href="#stats">Statistik</a>
                <a href="#payment">Pembayaran</a>
                <a href="#data">Data</a>
            </div>
            <?php if (!empty($_SESSION['username'])): ?>
                <a class="cta-nav" href="logout.php">Keluar</a>
            <?php else: ?>
                <a class="cta-nav" href="login.php">Masuk Admin</a>
            <?php endif; ?>
        </div>
    </nav>

    <section class="hero" id="hero">
        <div class="container hero-grid">
            <div>
                <div class="hero-badge">Transparan, cepat, dan aman</div>
                <h1>Platform zakat modern yang nyaman dibaca di setiap perangkat.</h1>
                <p>Ikuti perkembangan zakat keluarga dan salurkan pembayaran dengan antarmuka minimalis, ramah mata, serta elemen islami yang subtil.</p>
                <div class="hero-actions">
                    <button class="btn-primary" onclick="document.getElementById('payment').scrollIntoView({behavior:'smooth'});">Mulai Bayar</button>
                    <button class="btn-secondary" onclick="document.getElementById('data').scrollIntoView({behavior:'smooth'});">Lihat Data</button>
                </div>
                <p style="margin-top:18px;color:#4c5b55;">Saat ini tercatat <strong><?= $totalFamilies; ?></strong> keluarga dengan pemantauan real time.</p>
            </div>
            <div class="hero-card">
                <img src="https://images.unsplash.com/photo-1500530855697-b586d89ba3ee?auto=format&fit=crop&w=1200&q=80" alt="Ilustrasi komunitas berzakat yang harmonis">
            </div>
        </div>
    </section>

    <section id="stats">
        <div class="container">
            <h2>Statistik Zakat Ringkas</h2>
            <p class="muted">Data diperbarui otomatis dari input keluarga dan pilihan jenis zakat.</p>
            <div class="stats-grid" aria-label="Ringkasan statistik zakat">
                <div class="stat-card">
                    <p class="stat-title">Total Uang</p>
                    <p class="stat-value">Rp <?= format_rupiah($aggregate['uang']); ?></p>
                </div>
                <div class="stat-card">
                    <p class="stat-title">Total Beras</p>
                    <p class="stat-value"><?= number_format($aggregate['beras'], 1); ?> kg</p>
                </div>
                <div class="stat-card">
                    <p class="stat-title">Total Jagung</p>
                    <p class="stat-value"><?= number_format($aggregate['jagung'], 1); ?> kg</p>
                </div>
                <div class="stat-card">
                    <p class="stat-title">Total Infaq</p>
                    <p class="stat-value">Rp <?= format_rupiah($aggregate['infaq']); ?></p>
                </div>
            </div>
        </div>
    </section>

    <section id="payment">
        <div class="container">
            <div class="card">
                <h2>Form Pembayaran Zakat</h2>
                <p class="muted">Form responsif dengan grid adaptif memastikan kolom mudah diakses di layar kecil.</p>
                <form class="form-grid" action="#" onsubmit="return false;">
                    <div>
                        <label for="nama">Nama lengkap</label>
                        <input type="text" id="nama" name="nama" placeholder="Contoh: Ahmad Yusuf" required>
                    </div>
                    <div>
                        <label for="email">Email</label>
                        <input type="email" id="email" name="email" placeholder="email@contoh.com">
                    </div>
                    <div>
                        <label for="nominal">Nominal / berat</label>
                        <input type="number" id="nominal" name="nominal" placeholder="Isi sesuai jenis zakat" min="0" step="500" required>
                    </div>
                    <div>
                        <label for="jenis">Jenis zakat</label>
                        <select id="jenis" name="jenis">
                            <option value="fitrah">Zakat Fitrah (Uang)</option>
                            <option value="beras">Zakat Fitrah (Beras)</option>
                            <option value="jagung">Zakat Fitrah (Jagung)</option>
                            <option value="maal">Zakat Maal</option>
                        </select>
                    </div>
                    <div>
                        <label for="metode">Metode pembayaran</label>
                        <select id="metode" name="metode">
                            <option>Transfer bank</option>
                            <option>E-wallet</option>
                            <option>Tunai</option>
                        </select>
                    </div>
                    <div>
                        <label for="catatan">Catatan</label>
                        <textarea id="catatan" name="catatan" rows="3" placeholder="Tambahkan niat atau catatan penyaluran"></textarea>
                    </div>
                    <div class="form-actions">
                        <button class="btn-primary" type="submit">Kirim Pembayaran</button>
                        <button class="btn-secondary" type="reset">Bersihkan Form</button>
                    </div>
                </form>
            </div>
        </div>
    </section>

    <section id="data">
        <div class="container">
            <div class="card table-card">
                <div style="display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;">
                    <div>
                        <h2 style="margin:0;">Data Infaq &amp; Zakat</h2>
                        <p class="muted" style="margin:6px 0 0;">Tabel otomatis dapat digulir secara horizontal di layar kecil.</p>
                    </div>
                    <?php if (!empty($_SESSION['username'])): ?>
                        <div style="color:#4c5b55;">Login sebagai <strong><?= htmlspecialchars($_SESSION['username']); ?></strong></div>
                    <?php endif; ?>
                </div>

                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>Nama Kepala Keluarga</th>
                                <th>Jumlah Anggota</th>
                                <th>Uang (Rp)</th>
                                <th>Beras (kg)</th>
                                <th>Jagung (kg)</th>
                                <th>Infaq (Rp)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($families as $row): ?>
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
                                        <small>(+ <?= max(0, (int)$row['jumlah_anggota'] - 1); ?> anggota)</small>
                                    </td>
                                    <td><?= (int)$row['jumlah_anggota']; ?></td>
                                    <td><?= format_rupiah((float)$uangRp); ?></td>
                                    <td><?= number_format((float)$berasKg, 1); ?></td>
                                    <td><?= number_format((float)$jagungKg, 1); ?></td>
                                    <td><?= format_rupiah((float)$infaq); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </section>

    <footer>
        <div class="container footer-grid">
            <div class="footer-brand">Zakat Harmoni</div>
            <div>
                <strong>Breakpoints yang direkomendasikan</strong>
                <ul class="breakpoints">
                    <li>≥1200px: Desktop lebar, grid 4 kolom</li>
                    <li>901-1199px: Tablet landscape, grid 3 kolom</li>
                    <li>641-900px: Tablet/phone besar, grid 2 kolom</li>
                    <li>≤640px: Ponsel, tombol &amp; form full width</li>
                </ul>
            </div>
            <div>
                <strong>Kontak layanan zakat</strong>
                <p style="margin:6px 0 0; color:#c0d5cd;">Email: info@zakat-harmoni.id</p>
            </div>
        </div>
    </footer>

</body>

</html>