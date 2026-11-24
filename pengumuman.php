<?php
require_once '../koneksi.php';
$user = wajib_mahasiswa();
$daftar = ambil_semua('SELECT * FROM pengumuman WHERE prodi IS NULL OR prodi = ? ORDER BY dibuat DESC, id DESC', [$user['program_studi'] ?? '']);
$ta = ambil_semua('SELECT * FROM tahun_akademik ORDER BY nama DESC, semester DESC');
$judul = 'Pengumuman';
include '../includes/header.php';
?>
<h1>Pengumuman</h1>
<div class="kartu"><p><strong>Tahun akademik aktif:</strong> <?= e(tahun_aktif()) ?></p>
<?php foreach ($ta as $t): if ($t['aktif'] && ($t['tgl_mulai'] || $t['tgl_selesai'])): ?><p class="keterangan">Periode: <?= tanggal($t['tgl_mulai']) ?> s.d. <?= tanggal($t['tgl_selesai']) ?></p><?php endif; endforeach; ?></div>
<div class="kartu">
    <?php if (!$daftar): ?><p class="kosong">Belum ada pengumuman.</p><?php endif; ?>
    <?php foreach ($daftar as $p): ?>
        <div class="item"><h3><?= e($p['judul']) ?></h3><p class="keterangan"><?= tanggal_jam($p['dibuat']) ?> - <?= e($p['penulis']) ?> - <?= $p['prodi'] ? 'Prodi ' . e($p['prodi']) : 'Umum' ?></p><p><?= nl2br(e($p['isi'])) ?></p></div>
    <?php endforeach; ?>
</div>
<?php include '../includes/footer.php'; ?>
