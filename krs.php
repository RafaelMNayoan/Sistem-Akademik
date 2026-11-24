<?php
require_once '../koneksi.php';
$user = wajib_mahasiswa();
$nim = $user['nim'];
$semester = (int) $user['semester'];
$batasSks = 24;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['batal'])) {
        $baris = ambil_satu('SELECT status FROM krs WHERE id = ? AND nim = ?', [(int) $_POST['batal'], $nim]);
        if ($baris && $baris['status'] === 'Disetujui') {
            pesan('KRS yang sudah disetujui tidak dapat dibatalkan sendiri. Hubungi Kaprodi atau Staff.', 'gagal');
            pindah('krs.php');
        }
        jalankan('DELETE FROM krs WHERE id = ? AND nim = ?', [(int) $_POST['batal'], $nim]);
        pesan('Mata kuliah dibatalkan dari KRS.');
        pindah('krs.php');
    }
    $kode = post('ambil');
    $mk = ambil_satu("SELECT * FROM mata_kuliah WHERE kode_mk = ? AND status_kurikulum = 'Disetujui' AND (prodi IS NULL OR prodi = ?)", [$kode, $user['program_studi'] ?? '']);
    $sudahAda = ambil_satu('SELECT COALESCE(SUM(m.sks), 0) AS total FROM krs k JOIN mata_kuliah m ON m.kode_mk = k.kode_mk WHERE k.nim = ? AND k.semester = ?', [$nim, $semester]);
    if (!$mk) {
        pesan('Mata kuliah tidak ditemukan atau belum masuk kurikulum aktif prodi Anda.', 'gagal');
    } elseif (ambil_pengaturan('krs_wajib_lunas') === '1' && jumlah_tunggakan($nim) > 0) {
        pesan('Anda masih memiliki tagihan yang belum lunas. Selesaikan pembayaran untuk mengisi KRS.', 'gagal');
    } elseif ((int)$sudahAda['total'] + (int)$mk['sks'] > $batasSks) {
        pesan('Total SKS tidak boleh lebih dari ' . $batasSks . '.', 'gagal');
    } else {
        jalankan('INSERT IGNORE INTO krs (nim, semester, kode_mk) VALUES (?, ?, ?)', [$nim, $semester, $kode]);
        pesan('Mata kuliah ' . $kode . ' masuk ke KRS.');
    }
    pindah('krs.php');
}

$krs = ambil_semua('SELECT k.id, k.status, k.catatan, m.kode_mk, m.nama_mk, m.sks, m.kelas FROM krs k JOIN mata_kuliah m ON m.kode_mk = k.kode_mk WHERE k.nim = ? AND k.semester = ? ORDER BY m.kode_mk', [$nim, $semester]);
$totalSks = array_sum(array_column($krs, 'sks'));
$kodeDiambil = array_column($krs, 'kode_mk');
$semuaMk = ambil_semua("SELECT * FROM mata_kuliah WHERE status_kurikulum = 'Disetujui' AND (prodi IS NULL OR prodi = ?) ORDER BY kode_mk", [$user['program_studi'] ?? '']);
$kaprodi = ambil_satu("SELECT nama FROM akun_pengguna WHERE role = 'kaprodi' AND status = 'Aktif' ORDER BY (prodi = ?) DESC, nama LIMIT 1", [$user['program_studi'] ?? '']);
$tanggalCetak = date('d F Y');
$judul = 'KRS';
include '../includes/header.php';
?>
<h1>Kartu Rencana Studi - Semester <?= e($semester) ?></h1>
<div class="kartu">
    <h2>Mata kuliah yang diambil</h2>
    <?php if (!$krs): ?>
        <p class="kosong">Belum ada mata kuliah di KRS. Pilih dari daftar di bawah.</p>
    <?php else: ?>
        <div class="gulir"><table>
            <tr><th>No</th><th>Kode</th><th>Mata kuliah</th><th class="angka">SKS</th><th>Kelas</th><th>Status</th><th></th></tr>
            <?php foreach ($krs as $no => $mk): ?>
            <tr>
                <td><?= $no + 1 ?></td><td><?= e($mk['kode_mk']) ?></td><td><?= e($mk['nama_mk']) ?></td><td class="angka"><?= (int)$mk['sks'] ?></td><td><?= e($mk['kelas']) ?></td>
                <td><?= lencana($mk['status']) ?><?php if ($mk['catatan']): ?><br><span class="keterangan"><?= e($mk['catatan']) ?></span><?php endif; ?></td>
                <td><?php if ($mk['status'] !== 'Disetujui'): ?><form method="post" onsubmit="return confirm('Batalkan mata kuliah ini dari KRS?')"><input type="hidden" name="batal" value="<?= (int)$mk['id'] ?>"><button type="submit" class="tombol merah kecil">Batalkan</button></form><?php endif; ?></td>
            </tr>
            <?php endforeach; ?>
        </table></div>
        <p><strong>Total: <?= (int)$totalSks ?> SKS</strong> dari maksimal <?= $batasSks ?> SKS</p>
        <button type="button" class="tombol" onclick="window.print()">Cetak KRS</button>
    <?php endif; ?>
</div>

<?php if ($krs): ?>
<section class="area-cetak">
    <div class="kop-krs">
        <h2>STMIK PESAT NABIRE</h2>
        <p><strong>KARTU RENCANA STUDI (KRS)</strong></p>
        <p>Tahun Akademik: ____________________ &nbsp; Semester: <?= e($semester) ?></p><hr>
    </div>
    <table class="identitas-krs">
        <tr><td width="22%">Nama Mahasiswa</td><td>: <?= e($user['nama_lengkap']) ?></td><td width="15%">NIM</td><td>: <?= e($nim) ?></td></tr>
        <tr><td>Program Studi</td><td>: <?= e($user['program_studi'] ?? '-') ?></td><td>Semester</td><td>: <?= e($semester) ?></td></tr>
    </table>
    <table class="tabel-cetak-krs">
        <thead><tr><th>No</th><th>Kode MK</th><th>Mata Kuliah</th><th>SKS</th><th>Kelas</th></tr></thead>
        <tbody><?php foreach ($krs as $no => $mk): ?><tr><td><?= $no + 1 ?></td><td><?= e($mk['kode_mk']) ?></td><td><?= e($mk['nama_mk']) ?></td><td><?= (int)$mk['sks'] ?></td><td><?= e($mk['kelas']) ?></td></tr><?php endforeach; ?></tbody>
        <tfoot><tr><th colspan="3" style="text-align:right">Total SKS</th><th><?= (int)$totalSks ?></th><th></th></tr></tfoot>
    </table>
    <p class="tanggal-krs">Nabire, <?= e($tanggalCetak) ?></p>
    <div class="ttd-krs">
        <div><p>Mengetahui,<br>Ketua Program Studi</p><div class="ruang-ttd"></div><strong><?= e($kaprodi['nama'] ?? '________________________') ?></strong><br><span>Kaprodi</span></div>
        <div><p>Mahasiswa</p><div class="ruang-ttd"></div><strong><?= e($user['nama_lengkap']) ?></strong><br><span><?= e($nim) ?></span></div>
    </div>
</section>
<?php endif; ?>

<div class="kartu">
    <h2>Daftar mata kuliah tersedia</h2>
    <?php if (!$semuaMk): ?><p class="kosong">Admin belum menambahkan mata kuliah.</p><?php else: ?>
    <div class="gulir"><table><tr><th>Kode</th><th>Mata kuliah</th><th class="angka">SKS</th><th>Kelas</th><th></th></tr>
    <?php foreach ($semuaMk as $mk): ?><tr>
        <td><?= e($mk['kode_mk']) ?></td><td><?= e($mk['nama_mk']) ?></td><td class="angka"><?= (int)$mk['sks'] ?></td><td><?= e($mk['kelas']) ?></td><td>
        <?php if (in_array($mk['kode_mk'], $kodeDiambil, true)): ?><span class="lencana hijau">Sudah diambil</span><?php else: ?><form method="post"><input type="hidden" name="ambil" value="<?= e($mk['kode_mk']) ?>"><button type="submit" class="tombol kecil">Ambil</button></form><?php endif; ?>
        </td></tr><?php endforeach; ?>
    </table></div><?php endif; ?>
</div>
<style>
.area-cetak{display:none}
@media print{
 @page{size:A4;margin:18mm}
 body{background:#fff!important;color:#000!important;font-family:Arial,sans-serif}
 body *{visibility:hidden!important}
 .area-cetak,.area-cetak *{visibility:visible!important}
 .area-cetak{display:block!important;position:absolute;left:0;top:0;width:100%;font-size:12px}
 .kop-krs{text-align:center;line-height:1.35}.kop-krs h2{font-size:17px;margin:0}.kop-krs p{margin:5px 0}
 .identitas-krs{width:100%;margin:18px 0 14px;border-collapse:collapse}.identitas-krs td{border:0;padding:4px 2px}
 .tabel-cetak-krs{width:100%;border-collapse:collapse;margin-top:8px}.tabel-cetak-krs th,.tabel-cetak-krs td{border:1px solid #222;padding:7px;text-align:left}
 .tabel-cetak-krs th:first-child,.tabel-cetak-krs th:nth-child(4),.tabel-cetak-krs td:first-child,.tabel-cetak-krs td:nth-child(4){text-align:center}
 .tanggal-krs{text-align:right;margin-top:35px}.ttd-krs{display:flex;justify-content:space-between;text-align:center;margin-top:18px;gap:50px}
 .ttd-krs>div{width:45%}.ttd-krs p{min-height:36px;margin:0}.ruang-ttd{height:75px}
 .ttd-krs strong{display:inline-block;min-width:180px;text-decoration:underline}
}
</style>
<?php include '../includes/footer.php'; ?>
