<?php
require_once '../koneksi.php';
$user = wajib_portal('presensi');
$role = $user['role'];
$cak = cakupan_mk();                // null = semua; array = batas kode MK
$kodeSaya = $role === 'dosen' ? mk_dosen() : [];
[$inCak, $pCak] = $cak === null ? ['', []] : daftar_in($cak);
$batasSql = $cak === null ? '' : " AND s.kode_mk IN ($inCak)";

function sesi_milik_saya($sesi)
{
    global $kodeSaya;
    return $sesi && in_array($sesi['kode_mk'], $kodeSaya, true);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $aksi = post('aksi');

    if ($aksi === 'buka_sesi') {
        $kode = post('kode_mk');
        $pertemuan = (int) post('pertemuan');
        $tgl = post('tanggal');
        if (!bisa('C') || !in_array($kode, $kodeSaya, true)) {
            pesan('Anda hanya dapat membuka presensi untuk kelas yang Anda ampu.', 'gagal');
        } elseif ($pertemuan < 1 || $pertemuan > 30 || !strtotime($tgl)) {
            pesan('Isi pertemuan (1-30) dan tanggal dengan benar.', 'gagal');
        } elseif (ambil_satu('SELECT id FROM sesi_presensi WHERE kode_mk = ? AND pertemuan = ?', [$kode, $pertemuan])) {
            pesan('Pertemuan ke-' . $pertemuan . ' untuk mata kuliah ini sudah dibuat.', 'gagal');
        } else {
            jalankan("INSERT INTO sesi_presensi (kode_mk, pertemuan, tanggal, status) VALUES (?, ?, ?, 'Buka')", [$kode, $pertemuan, $tgl]);
            catat_log('presensi', 'buka sesi', "$kode pertemuan $pertemuan");
            pesan('Sesi presensi dibuka. Mahasiswa sudah bisa mengisi presensi.');
        }
        pindah('presensi.php');
    }

    $sesi = ambil_satu('SELECT * FROM sesi_presensi WHERE id = ?', [(int) post('id')]);
    if ($aksi === 'saklar') {
        if (!bisa('U') || !sesi_milik_saya($sesi)) {
            pesan('Anda tidak berhak mengubah sesi ini.', 'gagal');
        } else {
            $baru = $sesi['status'] === 'Buka' ? 'Tutup' : 'Buka';
            jalankan('UPDATE sesi_presensi SET status = ? WHERE id = ?', [$baru, $sesi['id']]);
            catat_log('presensi', $baru === 'Tutup' ? 'tutup sesi' : 'buka lagi sesi', "{$sesi['kode_mk']} pertemuan {$sesi['pertemuan']}");
            pesan('Status sesi presensi diubah.');
        }
        pindah('presensi.php');
    }

    if ($aksi === 'ubah_status') {
        $sesi = ambil_satu('SELECT * FROM sesi_presensi WHERE id = ?', [(int) post('sesi_id')]);
        $nim = post('nim');
        $status = post('status');
        $peserta = $sesi ? ambil_satu('SELECT id FROM krs WHERE kode_mk = ? AND nim = ?', [$sesi['kode_mk'], $nim]) : null;
        if (!bisa('U') || !sesi_milik_saya($sesi) || !$peserta) {
            pesan('Anda tidak berhak mengubah kehadiran ini.', 'gagal');
        } elseif (!in_array($status, ['Hadir', 'Izin', 'Sakit', 'Alpha'], true)) {
            pesan('Pilih status kehadiran.', 'gagal');
        } else {
            simpan_presensi($sesi['id'], $nim, $status);
            pesan('Kehadiran diperbarui.');
        }
        pindah('presensi.php?sesi=' . (int) post('sesi_id'));
    }
    pindah('presensi.php');
}

$sesiDipilih = null;
if (get('sesi') !== '') {
    $sesiDipilih = ambil_satu('SELECT s.*, m.nama_mk FROM sesi_presensi s JOIN mata_kuliah m ON m.kode_mk = s.kode_mk WHERE s.id = ?' . $batasSql, array_merge([(int) get('sesi')], $pCak));
}
$rekap = [];
if ($sesiDipilih) {
    $rekap = ambil_semua('SELECT mh.nim, mh.nama_lengkap, p.status, p.jam_masuk, p.catatan
        FROM (SELECT DISTINCT nim FROM krs WHERE kode_mk = ?) k JOIN mahasiswa mh ON mh.nim = k.nim
        LEFT JOIN presensi p ON p.nim = k.nim AND p.sesi_id = ? ORDER BY mh.nama_lengkap', [$sesiDipilih['kode_mk'], $sesiDipilih['id']]);
}
$dapatUbah = $sesiDipilih && bisa('U') && sesi_milik_saya($sesiDipilih);

$daftar = ambil_semua("SELECT s.*, m.nama_mk,
    (SELECT COUNT(DISTINCT k.nim) FROM krs k WHERE k.kode_mk = s.kode_mk) AS peserta,
    (SELECT COUNT(*) FROM presensi p WHERE p.sesi_id = s.id AND p.status = 'Hadir') AS hadir
    FROM sesi_presensi s JOIN mata_kuliah m ON m.kode_mk = s.kode_mk WHERE 1 = 1 $batasSql
    ORDER BY s.tanggal DESC, s.id DESC LIMIT 100", $pCak);

// Rekap kehadiran per mata kuliah (untuk memantau kehadiran).
$ringkasMk = [];
foreach ($daftar as $s) {
    $r = &$ringkasMk[$s['kode_mk']];
    $r['nama'] = $s['nama_mk'];
    $r['sesi'] = ($r['sesi'] ?? 0) + 1;
    $r['hadir'] = ($r['hadir'] ?? 0) + (int) $s['hadir'];
    $r['kapasitas'] = ($r['kapasitas'] ?? 0) + (int) $s['peserta'];
    unset($r);
}
$pilihanMk = [];
foreach ($kodeSaya as $kd) { $mk = ambil_satu('SELECT nama_mk FROM mata_kuliah WHERE kode_mk = ?', [$kd]); $pilihanMk[$kd] = $kd . ' - ' . ($mk['nama_mk'] ?? ''); }

$judul = 'Presensi';
include '../includes/header.php';
?>
<h1>Presensi Kuliah</h1>
<?php if ($sesiDipilih): ?>
<div class="kartu">
    <h2>Rekap: <?= e($sesiDipilih['nama_mk']) ?> - pertemuan <?= e($sesiDipilih['pertemuan']) ?> (<?= tanggal($sesiDipilih['tanggal']) ?>)</h2>
    <p>Status sesi: <?= lencana($sesiDipilih['status']) ?> <a href="presensi.php" class="tombol abu kecil">Kembali</a></p>
    <?php if (!$rekap): ?><p class="kosong">Belum ada mahasiswa yang mengambil mata kuliah ini di KRS.</p><?php else: ?>
    <div class="gulir"><table>
        <tr><th>NIM</th><th>Nama</th><th>Jam</th><th>Status</th><th>Catatan</th><?php if ($dapatUbah): ?><th>Ubah kehadiran</th><?php endif; ?></tr>
        <?php foreach ($rekap as $r): ?><tr><td><?= e($r['nim']) ?></td><td><?= e($r['nama_lengkap']) ?></td><td><?= $r['jam_masuk'] ? e(substr($r['jam_masuk'], 0, 5)) : '-' ?></td><td><?= $r['status'] ? lencana($r['status']) : '-' ?></td><td><?= e($r['catatan']) ?></td>
        <?php if ($dapatUbah): ?><td><form method="post" class="aksi"><input type="hidden" name="aksi" value="ubah_status"><input type="hidden" name="sesi_id" value="<?= $sesiDipilih['id'] ?>"><input type="hidden" name="nim" value="<?= e($r['nim']) ?>">
            <select name="status"><?= opsi(sama(['Hadir', 'Izin', 'Sakit', 'Alpha']), $r['status'] ?? '') ?></select><button type="submit" class="tombol kecil">Simpan</button></form></td><?php endif; ?></tr><?php endforeach; ?>
    </table></div><?php endif; ?>
</div>
<?php endif; ?>

<?php if (bisa('C')): ?>
<div class="kartu">
    <h2>Buka sesi presensi baru</h2>
    <?php if (!$pilihanMk): ?><p class="kosong">Anda belum memiliki kelas yang disetujui. Kelas muncul setelah jadwal Anda disetujui Kaprodi.</p><?php else: ?>
    <form method="post" class="form-isi"><input type="hidden" name="aksi" value="buka_sesi">
        <div><label for="kode_mk">Mata kuliah (kelas saya)</label><select id="kode_mk" name="kode_mk" required><?= opsi($pilihanMk) ?></select></div>
        <div><label for="pertemuan">Pertemuan ke-</label><input type="number" id="pertemuan" name="pertemuan" min="1" max="30" value="1" required></div>
        <div><label for="tanggal">Tanggal</label><input type="date" id="tanggal" name="tanggal" value="<?= date('Y-m-d') ?>" required></div>
        <div class="penuh"><button type="submit" class="tombol hijau">Buka presensi</button></div>
    </form><?php endif; ?>
</div>
<?php endif; ?>

<div class="kartu">
    <h2>Daftar sesi presensi</h2>
    <?php if (!$daftar): ?><p class="kosong">Belum ada sesi presensi.</p><?php else: ?>
    <div class="gulir"><table>
        <tr><th>Tanggal</th><th>Mata kuliah</th><th>Pertemuan</th><th>Hadir</th><th>Status</th><th></th></tr>
        <?php foreach ($daftar as $s): ?><tr><td><?= tanggal($s['tanggal']) ?></td><td><?= e($s['nama_mk']) ?></td><td><?= e($s['pertemuan']) ?></td><td><?= $s['hadir'] ?> / <?= $s['peserta'] ?></td><td><?= lencana($s['status']) ?></td>
        <td><div class="aksi">
            <?php if (bisa('U') && sesi_milik_saya($s)): ?><form method="post"><input type="hidden" name="aksi" value="saklar"><input type="hidden" name="id" value="<?= $s['id'] ?>"><?php if ($s['status'] === 'Buka'): ?><button type="submit" class="tombol merah kecil">Tutup</button><?php else: ?><button type="submit" class="tombol hijau kecil">Buka lagi</button><?php endif; ?></form><?php endif; ?>
            <a href="?sesi=<?= $s['id'] ?>" class="tombol kecil">Rekap</a>
        </div></td></tr><?php endforeach; ?>
    </table></div><?php endif; ?>
</div>

<?php if ($ringkasMk): ?>
<div class="kartu"><h2>Ringkasan kehadiran per mata kuliah</h2><div class="gulir"><table>
    <tr><th>Mata kuliah</th><th class="angka">Sesi</th><th class="angka">Rata-rata hadir</th></tr>
    <?php foreach ($ringkasMk as $kd => $r): ?><tr><td><?= e($kd) ?> - <?= e($r['nama']) ?></td><td class="angka"><?= $r['sesi'] ?></td><td class="angka"><?= $r['kapasitas'] > 0 ? round($r['hadir'] / $r['kapasitas'] * 100) . '%' : '-' ?></td></tr><?php endforeach; ?>
</table></div></div>
<?php endif; ?>
<?php include '../includes/footer.php'; ?>
