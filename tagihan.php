<?php
require_once '../koneksi.php';
$user = wajib_mahasiswa();

$semester = (int) (get('semester') !== '' ? get('semester') : $user['semester']);
$cari = get('cari');

$tagihan = ambil_semua('SELECT * FROM tagihan
        WHERE nim = ? AND semester = ? AND (kode_tagihan LIKE ? OR nama_tagihan LIKE ?)
        ORDER BY tanggal_jatuh_tempo', [$user['nim'], $semester, "%$cari%", "%$cari%"]);

$daftarSemester = array_column(ambil_semua('SELECT DISTINCT semester FROM tagihan WHERE nim = ? ORDER BY semester',
                                           [$user['nim']]), 'semester');
if (!in_array($user['semester'], $daftarSemester)) {
    $daftarSemester[] = $user['semester'];
}
sort($daftarSemester);

$judul = 'Tagihan';
include '../includes/header.php';
?>
<h1>Informasi Tagihan</h1>

<div class="kartu">
    <form method="get" class="filter">
        <div>
            <label for="semester">Semester</label>
            <select id="semester" name="semester">
                <?php foreach ($daftarSemester as $sem): ?>
                    <option value="<?= $sem ?>" <?= $sem == $semester ? 'selected' : '' ?>>Semester <?= $sem ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label for="cari">Cari tagihan</label>
            <input type="text" id="cari" name="cari" placeholder="Kode atau nama" value="<?= e($cari) ?>">
        </div>
        <button type="submit" class="tombol">Tampilkan</button>
    </form>
</div>

<div class="kartu">
    <?php if (!$tagihan): ?>
        <p class="kosong">Tidak ada tagihan untuk semester ini.</p>
    <?php else: ?>
        <div class="gulir">
            <table>
                <tr>
                    <th>Kode</th><th>Nama tagihan</th>
                    <th class="angka">Biaya</th><th class="angka">Potongan</th><th class="angka">Total</th>
                    <th>Jatuh tempo</th><th>Dibayar</th><th>Status</th>
                </tr>
                <?php foreach ($tagihan as $t): ?>
                    <?php
                    $warna = $t['status'] === 'Dibayar' ? 'hijau' : (in_array($t['status'], ['Terlambat', 'Dibatalkan'], true) ? 'merah' : 'kuning');
                    ?>
                    <tr>
                        <td><?= e($t['kode_tagihan']) ?></td>
                        <td><?= e($t['nama_tagihan']) ?></td>
                        <td class="angka"><?= rupiah($t['biaya']) ?></td>
                        <td class="angka"><?= rupiah($t['potongan']) ?></td>
                        <td class="angka"><?= rupiah($t['total_bayar']) ?></td>
                        <td><?= tanggal($t['tanggal_jatuh_tempo']) ?></td>
                        <td><?= tanggal($t['tanggal_bayar']) ?></td>
                        <td><span class="lencana <?= $warna ?>"><?= e($t['status']) ?></span></td>
                    </tr>
                <?php endforeach; ?>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php
$beasiswa = ambil_semua('SELECT * FROM beasiswa WHERE nim = ? ORDER BY id DESC', [$user['nim']]);
$tarif = ambil_semua('SELECT * FROM komponen_biaya WHERE aktif = 1 AND (prodi IS NULL OR prodi = ?) AND (angkatan IS NULL OR angkatan = ?) ORDER BY nama', [$user['program_studi'] ?? '', $user['angkatan'] ?? 0]);
?>
<div class="kartu">
    <h2>Beasiswa &amp; keringanan saya</h2>
    <?php if (!$beasiswa): ?><p class="kosong">Belum ada beasiswa atau keringanan.</p><?php else: ?>
    <div class="gulir"><table><tr><th>Jenis</th><th>Program</th><th class="angka">Potongan</th><th>Status</th></tr>
    <?php foreach ($beasiswa as $b): ?><tr><td><?= e($b['jenis']) ?></td><td><?= e($b['nama']) ?></td><td class="angka"><?= rupiah($b['nilai_potongan']) ?></td><td><?= lencana($b['status']) ?></td></tr><?php endforeach; ?></table></div><?php endif; ?>
</div>
<div class="kartu">
    <h2>Komponen biaya &amp; tarif</h2>
    <?php if (!$tarif): ?><p class="kosong">Belum ada tarif yang ditetapkan.</p><?php else: ?>
    <div class="gulir"><table><tr><th>Komponen</th><th class="angka">Nominal</th></tr>
    <?php foreach ($tarif as $k): ?><tr><td><?= e($k['nama']) ?></td><td class="angka"><?= rupiah($k['nominal']) ?></td></tr><?php endforeach; ?></table></div><?php endif; ?>
</div>
<?php include '../includes/footer.php'; ?>
