<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/Helpers/helpers.php';

/**
 * Kumpulan fungsi layanan untuk operasi keluarga dan pengaturan.
 */

function is_settings_locked(mysqli $db): bool
{
    $res = $db->query("SELECT locked FROM settings WHERE id=1");
    if (!$res) {
        return false;
    }

    $row = $res->fetch_assoc();
    return isset($row['locked']) && (int)$row['locked'] === 1;
}

function update_settings(mysqli $db, int $harga, float $beras, float $jagung): void
{
    $stmt = $db->prepare("UPDATE settings SET harga=?, beras=?, jagung=? WHERE id=1");
    $stmt->bind_param("idd", $harga, $beras, $jagung);
    $stmt->execute();
    $stmt->close();
}

function set_setting_lock(mysqli $db, bool $locked): void
{
    $stmt = $db->prepare("UPDATE settings SET locked=? WHERE id=1");
    $lockVal = $locked ? 1 : 0;
    $stmt->bind_param("i", $lockVal);
    $stmt->execute();
    $stmt->close();
}

function collect_members_from_post(array $post): array
{
    $members = [];
    if (empty($post['nama']) || !is_array($post['nama'])) {
        return $members;
    }

    foreach ($post['nama'] as $i => $nama) {
        $nama = trim((string)$nama);
        if ($nama === '') {
            continue;
        }

        $members[] = [
            'nama'   => $nama,
            'jk'     => $post['jk'][$i] ?? '',
            'uang'   => isset($post['uang'][$i]) ? 1 : 0,
            'beras'  => isset($post['beras'][$i]) ? 1 : 0,
            'jagung' => isset($post['jagung'][$i]) ? 1 : 0,
        ];
    }

    return $members;
}

function insert_family(mysqli $db, string $kepala, int $infaq): int
{
    $stmt = $db->prepare("INSERT INTO families (kepala, infaq) VALUES (?, ?)");
    $stmt->bind_param("si", $kepala, $infaq);
    $stmt->execute();
    $familyId = $stmt->insert_id;
    $stmt->close();

    return (int)$familyId;
}

function insert_members(mysqli $db, int $familyId, array $members): void
{
    $stmt = $db->prepare(
        "INSERT INTO members (family_id, nama, jk, uang, beras, jagung) VALUES (?, ?, ?, ?, ?, ?)"
    );

    foreach ($members as $member) {
        $stmt->bind_param(
            "issiii",
            $familyId,
            $member['nama'],
            $member['jk'],
            $member['uang'],
            $member['beras'],
            $member['jagung']
        );
        $stmt->execute();
    }

    $stmt->close();
}

function save_family(mysqli $db, string $kepala, int $infaq, array $members): void
{
    $familyId = insert_family($db, $kepala, $infaq);
    insert_members($db, $familyId, $members);
}

function fetch_family_members(mysqli $db, int $familyId): array
{
    $members = [];
    $memberResult = $db->query("SELECT * FROM members WHERE family_id = {$familyId} ORDER BY id ASC");
    while ($memberResult && $member = $memberResult->fetch_assoc()) {
        $members[] = $member;
    }

    return $members;
}

function fetch_all_families(mysqli $db): array
{
    $out = [];
    $familyResult = $db->query("SELECT * FROM families ORDER BY id ASC");
    if (!$familyResult) {
        return $out;
    }

    while ($family = $familyResult->fetch_assoc()) {
        $familyId = (int)$family['id'];
        $family['anggota'] = fetch_family_members($db, $familyId);
        $out[] = $family;
    }

    return $out;
}

function delete_family(mysqli $db, int $familyId): void
{
    $stmt = $db->prepare("DELETE FROM families WHERE id = ?");
    $stmt->bind_param("i", $familyId);
    $stmt->execute();
    $stmt->close();
}

function reset_all_families(mysqli $db): void
{
    $db->query("DELETE FROM members");
    $db->query("DELETE FROM families");
}

function replace_family(mysqli $db, int $familyId, int $infaq, array $members): void
{
    $stmt = $db->prepare("DELETE FROM members WHERE family_id = ?");
    $stmt->bind_param("i", $familyId);
    $stmt->execute();
    $stmt->close();

    insert_members($db, $familyId, $members);

    $stmt = $db->prepare("UPDATE families SET infaq = ? WHERE id = ?");
    $stmt->bind_param("ii", $infaq, $familyId);
    $stmt->execute();
    $stmt->close();
}

function calculate_family_totals(array $family, array $setting): array
{
    $totals = [
        'uang' => 0.0,
        'beras' => 0.0,
        'jagung' => 0.0,
        'infaq' => (int)($family['infaq'] ?? 0),
    ];

    foreach ($family['anggota'] as $member) {
        if (!empty($member['uang'])) {
            $totals['uang'] += setting_value($setting, 'harga');
        }
        if (!empty($member['beras'])) {
            $totals['beras'] += setting_value($setting, 'beras');
        }
        if (!empty($member['jagung'])) {
            $totals['jagung'] += setting_value($setting, 'jagung');
        }
    }

    return $totals;
}

function calculate_overall_totals(array $families, array $setting): array
{
    $overall = ['uang' => 0.0, 'beras' => 0.0, 'jagung' => 0.0, 'infaq' => 0];
    foreach ($families as $family) {
        $familyTotals = calculate_family_totals($family, $setting);
        $overall['uang'] += $familyTotals['uang'];
        $overall['beras'] += $familyTotals['beras'];
        $overall['jagung'] += $familyTotals['jagung'];
        $overall['infaq'] += $familyTotals['infaq'];
    }

    return $overall;
}
