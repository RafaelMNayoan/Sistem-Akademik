<?php
require_once '../koneksi.php';
$user = wajib_portal('nilai');
$role = $user['role'];
$bp = prodi_batas();
$kodeSaya = $role === 'dosen' ? mk_dosen() : [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nim = post('nim');
    $kode = post('kode_mk');
    $grade = post('huruf_grade');
    $peserta = ambil_satu("SELECT k.id FROM krs k WHERE k.nim = ? AND k.kode_mk = ? AND k.status <> 'Ditolak'", [$nim, $kode]);
    if (!(bisa('C') || bisa('U')) || !in_array($kode, $kodeSaya, true)) {
        pesan('Anda hanya dapat mengisi nilai untuk kelas yang Anda ampu.', 'gagal');
    } elseif (!$peserta) {
        pesan('Mahasiswa itu tidak terdaftar di kelas ini.', 'gagal');
    } elseif (!in_array($grade, GRADE, true)) {
        pesan('Pilih nilai huruf yang valid.', 'gagal');
    } else {
        $lama = simpan_nilai($nim, $kode, $grade);
        catat_log('nilai', $lama === null ? 'input nilai' : 'ubah nilai', "nim $nim $kode: " . ($lama ?? '-') . ' -> ' . $grade);
        pesan('Nilai disimpan.');
    }
    pindah('nilai.php?kelas=' . urlencode($kode));
}

$kelas = get('kelas');
$pesertaKelas = [];
if ($role === 'dosen' && in_array($kelas, $kodeSaya, true)) {
    $pesertaKelas = ambil_semua("SELECT mh.nim, mh.nama_lengkap, n.huruf_grade FROM (SELECT DISTINCT nim FROM krs WHERE kode_mk = ? AND status <> 'Ditolak') k
        JOIN mahasiswa mh ON mh.nim = k.nim LEFT JOIN nilai n ON n.nim = k.nim AND n.kode_mk = ? ORDER BY mh.nama_lengkap", [$kelas, $kelas]);
}

$cak = cakupan_mk();
$syarat = '1 = 1';
$param = [];
if ($cak !== null) { [$in, $pi] = daftar_in($cak); $syarat .= " AND n.kode_mk IN ($in)"; $param = $pi; }
if ($bp !== null) { $syarat .= ' AND mh.program_studi = ?'; $param[] = $bp; }
$fNim = get('nim');
if ($fNim !== '') { $syarat .= ' AND n.nim = ?'; $param[] = $fNim; }
$daftar = ambil_semua("SELECT n.*, mh.nama_lengkap, m.nama_mk, m.sks FROM nilai n JOIN mahasiswa mh ON mh.nim = n.nim JOIN mata_kuliah m ON m.kode_mk = n.kode_mk
    WHERE $syarat ORDER BY n.nim, n.kode_mk LIMIT 300", $param);
$pilihanMk = [];
foreach ($kodeSaya as $kd) { $mk = ambil_satu('SELECT nama_mk FROM mata_kuliah WHERE kode_mk = ?', [$kd]); $pilihanMk[$kd] = $kd . ' - ' . ($mk['nama_mk'] ?? ''); }

$judul = 'Nilai Mahasiswa';
include '../includes/header.php';
?>
<h1>Nilai Mahasiswa</h1>
<?php if ($role === 'dosen'): ?>
<div class="kartu">
    <h2>Input nilai kelas saya</h2>
    <?php if (!$pilihanMk): ?><p class="kosong">Anda belum memiliki kelas yang disetujui.</p><?php else: ?>
    <form method="get" class="filter"><div><label for="kelas">Mata kuliah</label><select id="kelas" name="kelas"><option value="">Pilih kelas</option><?= opsi($pilihanMk, $kelas) ?></select></div><button type="submit" class="tombol abu">Tampilkan</button></form>
    <?php if ($kelas !== '' && !$pesertaKelas): ?><p class="kosong">Belum ada mahasiswa di kelas ini.</p><?php endif; ?>
    <?php if ($pesertaKelas): ?><div class="gulir"><table><tr><th>NIM</th><th>Nama</th><th>Nilai huruf</th></tr>
        <?php foreach ($pesertaKelas as $p): ?><tr><td><?= e($p['nim']) ?></td><td><?= e($p['nama_lengkap']) ?></td><td>
            <form method="post" class="aksi"><input type="hidden" name="nim" value="<?= e($p['nim']) ?>"><input type="hidden" name="kode_mk" value="<?= e($kelas) ?>">
                <select name="huruf_grade"><?php if (!$p['huruf_grade']): ?><option value="">Belum dinilai</option><?php endif; ?><?= opsi(sama(GRADE), $p['huruf_grade'] ?? '') ?></select><button type="submit" class="tombol kecil">Simpan</button></form></td></tr><?php endforeach; ?>
    </table></div><?php endif; ?><?php endif; ?>
</div>
<?php endif; ?>
<div class="kartu">
    <h2>Daftar nilai<?= $role === 'dosen' ? ' (kelas saya)' : ($bp !== null ? ' prodi ' . e($bp) : '') ?></h2>
    <form method="get" class="filter"><div><label for="nim">NIM</label><input type="text" id="nim" name="nim" value="<?= e($fNim) ?>"></div><button type="submit" class="tombol abu">Saring</button></form>
    <?php if (!$daftar): ?><p class="kosong">Belum ada nilai.</p><?php else: ?>
    <div class="gulir"><table><tr><th>Mahasiswa</th><th>Mata kuliah</th><th class="angka">SKS</th><th>Nilai</th></tr>
        <?php foreach ($daftar as $n): ?><tr><td><?= e($n['nim']) ?> - <?= e($n['nama_lengkap']) ?></td><td><?= e($n['kode_mk']) ?> - <?= e($n['nama_mk']) ?></td><td class="angka"><?= $n['sks'] ?></td><td><span class="lencana biru"><?= e($n['huruf_grade']) ?></span></td></tr><?php endforeach; ?>
    </table></div>
    <?php if ($fNim !== '' && $role !== 'dosen'): ?><p><strong>IPK mahasiswa ini: <?= number_format(hitung_ipk($fNim), 2) ?></strong></p><?php endif; ?><?php endif; ?>
</div>
<?php include '../includes/footer.php'; ?>
