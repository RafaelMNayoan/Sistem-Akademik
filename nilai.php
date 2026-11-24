<?php
require_once '../koneksi.php';
$user = wajib_mahasiswa();
$daftar = ambil_semua('SELECT n.huruf_grade, m.kode_mk, m.nama_mk, m.sks FROM nilai n JOIN mata_kuliah m ON m.kode_mk = n.kode_mk WHERE n.nim = ? ORDER BY m.kode_mk', [$user['nim']]);
$sks = array_sum(array_column($daftar, 'sks'));
$judul = 'Nilai';
include '../includes/header.php';
?>
<h1>Nilai (KHS)</h1>
<div class="ringkasan"><div class="kartu"><span>IPK</span><strong><?= number_format(hitung_ipk($user['nim']), 2) ?></strong></div><div class="kartu"><span>SKS lulus dinilai</span><strong><?= $sks ?></strong></div></div>
<div class="kartu">
    <?php if (!$daftar): ?><p class="kosong">Belum ada nilai.</p><?php else: ?>
    <div class="gulir"><table><tr><th>Kode</th><th>Mata kuliah</th><th class="angka">SKS</th><th>Nilai</th></tr>
    <?php foreach ($daftar as $n): ?><tr><td><?= e($n['kode_mk']) ?></td><td><?= e($n['nama_mk']) ?></td><td class="angka"><?= (int) $n['sks'] ?></td><td><span class="lencana biru"><?= e($n['huruf_grade']) ?></span></td></tr><?php endforeach; ?></table></div><?php endif; ?>
</div>
<?php include '../includes/footer.php'; ?>
