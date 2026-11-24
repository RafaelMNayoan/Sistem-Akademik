<?php
require_once '../koneksi.php';
$user = wajib_mahasiswa();
$nim = $user['nim'];
$semester = $user['semester'];

$ipk = hitung_ipk($nim);
$krs = mk_diambil($nim, $semester);
$totalSks = array_sum(array_column($krs, 'sks'));

$tagihan = ambil_semua('SELECT * FROM tagihan WHERE nim = ? ORDER BY id DESC LIMIT 3', [$nim]);

$baris = ambil_satu("SELECT COUNT(*) AS total FROM tagihan WHERE nim = ? AND status NOT IN ('Dibayar','Dibatalkan')", [$nim]);
$belumBayar = $baris['total'];

$presensiBuka = ambil_semua("SELECT s.pertemuan, m.nama_mk
        FROM sesi_presensi s
        JOIN mata_kuliah m ON m.kode_mk = s.kode_mk
        JOIN krs k ON k.kode_mk = s.kode_mk AND k.nim = ? AND k.semester = ?
        WHERE s.status = 'Buka' AND s.id NOT IN (SELECT sesi_id FROM presensi WHERE nim = ?)",
        [$nim, $semester, $nim]);

$baris = ambil_satu("SELECT COUNT(*) AS total FROM tugas_kuliah t
        JOIN krs k ON k.kode_mk = t.kode_mk AND k.nim = ? AND k.semester = ?
        WHERE t.batas_waktu > NOW()
          AND t.id NOT IN (SELECT tugas_id FROM pengumpulan_tugas WHERE nim = ?)",
        [$nim, $semester, $nim]);
$tugasBelum = $baris['total'];

$judul = 'Dashboard';
include '../includes/header.php';
?>
<h1>Dashboard</h1>

<div class="ringkasan">
    <div class="kartu"><span>Status</span><strong><?= e($user['status']) ?></strong></div>
    <div class="kartu"><span>Semester</span><strong><?= e($semester) ?></strong></div>
    <div class="kartu"><span>IPK</span><strong><?= number_format($ipk, 2) ?></strong></div>
    <div class="kartu"><span>SKS semester ini</span><strong><?= $totalSks ?></strong></div>
</div>

<?php if ($presensiBuka): ?>
    <div class="pesan sukses">
        Presensi sedang dibuka untuk:
        <?php foreach ($presensiBuka as $p): ?>
            <strong><?= e($p['nama_mk']) ?></strong> (pertemuan <?= e($p['pertemuan']) ?>)<?= $p === end($presensiBuka) ? '.' : ',' ?>
        <?php endforeach; ?>
        <a href="presensi.php">Isi presensi sekarang</a>
    </div>
<?php endif; ?>
<?php if ($tugasBelum > 0): ?>
    <div class="pesan gagal">Ada <?= $tugasBelum ?> tugas yang belum Anda kumpulkan. <a href="tugas.php">Lihat tugas</a></div>
<?php endif; ?>

<div class="dua-kolom">
    <div class="kartu">
        <h2>KRS semester <?= e($semester) ?></h2>
        <?php if (!$krs): ?>
            <p class="kosong">Belum ada mata kuliah. <a href="krs.php">Isi KRS sekarang</a>.</p>
        <?php else: ?>
            <div class="gulir">
                <table>
                    <tr><th>Kode</th><th>Mata kuliah</th><th class="angka">SKS</th></tr>
                    <?php foreach ($krs as $mk): ?>
                        <tr>
                            <td><?= e($mk['kode_mk']) ?></td>
                            <td><?= e($mk['nama_mk']) ?></td>
                            <td class="angka"><?= $mk['sks'] ?></td>
                        </tr>
                    <?php endforeach; ?>
                </table>
            </div>
            <p><a href="krs.php">Kelola KRS</a></p>
        <?php endif; ?>
    </div>

    <div class="kartu">
        <h2>Tagihan terbaru</h2>
        <?php if (!$tagihan): ?>
            <p class="kosong">Belum ada tagihan.</p>
        <?php else: ?>
            <?php foreach ($tagihan as $t): ?>
                <p>
                    <?= e($t['nama_tagihan']) ?><br>
                    <?= rupiah($t['total_bayar']) ?>
                    <span class="lencana <?= $t['status'] === 'Dibayar' ? 'hijau' : 'kuning' ?>"><?= e($t['status']) ?></span>
                </p>
            <?php endforeach; ?>
            <p class="keterangan"><?= $belumBayar ?> tagihan belum dibayar.</p>
            <p><a href="tagihan.php">Lihat semua tagihan</a></p>
        <?php endif; ?>
    </div>
</div>
<?php include '../includes/footer.php'; ?>
