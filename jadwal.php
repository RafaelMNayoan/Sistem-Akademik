<?php
require_once '../koneksi.php';
$user = wajib_mahasiswa();
$semua = get('semua') === '1';
$param = [$user['program_studi'] ?? ''];
$sql = "SELECT k.*, m.nama_mk, m.sks, d.nama AS nama_dosen FROM kelas_kuliah k JOIN mata_kuliah m ON m.kode_mk = k.kode_mk LEFT JOIN dosen d ON d.id = k.dosen_id
        WHERE k.status = 'Disetujui' AND (m.prodi IS NULL OR m.prodi = ?)";
if (!$semua) {
    $sql .= " AND k.kode_mk IN (SELECT kode_mk FROM krs WHERE nim = ? AND status <> 'Ditolak')";
    $param[] = $user['nim'];
}
$sql .= " ORDER BY CASE k.hari WHEN 'Senin' THEN 1 WHEN 'Selasa' THEN 2 WHEN 'Rabu' THEN 3 WHEN 'Kamis' THEN 4 WHEN 'Jumat' THEN 5 ELSE 6 END, k.jam_mulai";
$daftar = ambil_semua($sql, $param);
$judul = 'Jadwal Kuliah';
include '../includes/header.php';
?>
<h1>Jadwal Kuliah</h1>
<div class="kartu">
    <p><a href="jadwal.php" class="tombol <?= $semua ? 'abu' : '' ?> kecil">Jadwal KRS saya</a> <a href="jadwal.php?semua=1" class="tombol <?= $semua ? '' : 'abu' ?> kecil">Semua jadwal prodi</a></p>
    <?php if (!$daftar): ?><p class="kosong"><?= $semua ? 'Belum ada jadwal yang disetujui.' : 'Belum ada jadwal untuk mata kuliah di KRS Anda.' ?></p><?php else: ?>
    <div class="gulir"><table><tr><th>Hari</th><th>Jam</th><th>Mata kuliah</th><th>Dosen</th><th>Ruang</th><th>Tahun akademik</th></tr>
    <?php foreach ($daftar as $k): ?><tr><td><?= e($k['hari']) ?></td><td><?= e($k['jam_mulai']) ?>-<?= e($k['jam_selesai']) ?></td><td><?= e($k['kode_mk']) ?> - <?= e($k['nama_mk']) ?> (<?= (int) $k['sks'] ?> SKS)</td><td><?= e($k['nama_dosen'] ?: '-') ?></td><td><?= e($k['ruang']) ?></td><td><?= e($k['tahun_akademik']) ?></td></tr><?php endforeach; ?>
    </table></div><?php endif; ?>
</div>
<?php include '../includes/footer.php'; ?>
