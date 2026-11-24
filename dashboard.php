<?php
require_once '../koneksi.php';
$user = wajib_admin();

$total = ambil_satu("SELECT
    (SELECT COUNT(*) FROM mahasiswa) AS mahasiswa,
    (SELECT COUNT(*) FROM mata_kuliah) AS matkul,
    (SELECT COUNT(*) FROM sesi_presensi WHERE status = 'Buka') AS presensi_buka,
    (SELECT COUNT(*) FROM tagihan WHERE status NOT IN ('Dibayar','Dibatalkan')) AS tagihan_belum,
    (SELECT COUNT(*) FROM tugas_kuliah) AS tugas,
    (SELECT COUNT(*) FROM materi_kuliah) AS materi");

$terbaru = ambil_semua('SELECT nim, nama_lengkap, program_studi, tgl_daftar FROM mahasiswa ORDER BY tgl_daftar DESC, nim DESC LIMIT 5');

$sesiBuka = ambil_semua("SELECT s.id, s.pertemuan, s.tanggal, m.nama_mk
                         FROM sesi_presensi s JOIN mata_kuliah m ON m.kode_mk = s.kode_mk
                         WHERE s.status = 'Buka' ORDER BY s.tanggal DESC");

$judul = 'Dashboard Admin';
include '../includes/header.php';
?>
<h1>Dashboard Admin</h1>

<div class="ringkasan">
    <div class="kartu"><span>Mahasiswa</span><strong><?= $total['mahasiswa'] ?></strong></div>
    <div class="kartu"><span>Mata kuliah</span><strong><?= $total['matkul'] ?></strong></div>
    <div class="kartu"><span>Presensi dibuka</span><strong><?= $total['presensi_buka'] ?></strong></div>
    <div class="kartu"><span>Tagihan belum lunas</span><strong><?= $total['tagihan_belum'] ?></strong></div>
</div>

<div class="dua-kolom sama-kolom">
    <div class="kartu">
        <h2>Mahasiswa terbaru</h2>
        <?php if (!$terbaru): ?>
            <p class="kosong">Belum ada mahasiswa.</p>
        <?php else: ?>
            <div class="gulir">
                <table>
                    <tr><th>NIM</th><th>Nama</th><th>Program studi</th></tr>
                    <?php foreach ($terbaru as $m): ?>
                        <tr>
                            <td><?= e($m['nim']) ?></td>
                            <td><?= e($m['nama_lengkap']) ?></td>
                            <td><?= e($m['program_studi']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </table>
            </div>
        <?php endif; ?>
        <p><a href="mahasiswa.php">Kelola data mahasiswa</a></p>
    </div>

    <div class="kartu">
        <h2>Presensi yang sedang dibuka</h2>
        <?php if (!$sesiBuka): ?>
            <p class="kosong">Tidak ada sesi presensi yang dibuka.</p>
        <?php else: ?>
            <?php foreach ($sesiBuka as $s): ?>
                <p>
                    <strong><?= e($s['nama_mk']) ?></strong> - pertemuan <?= e($s['pertemuan']) ?>
                    <span class="keterangan">(<?= tanggal($s['tanggal']) ?>)</span>
                </p>
            <?php endforeach; ?>
        <?php endif; ?>
        <p><a href="presensi.php">Buka/tutup presensi</a></p>
        <p class="keterangan">Total tugas: <?= $total['tugas'] ?> - Total materi: <?= $total['materi'] ?></p>
    </div>
</div>
<?php include '../includes/footer.php'; ?>
