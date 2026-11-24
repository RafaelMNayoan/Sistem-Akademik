<?php
require_once '../koneksi.php';
$user = wajib_portal('laporan_akademik');
$role = $user['role'];
$bp = prodi_batas();
$cak = cakupan_mk();   // dosen: kelasnya; kaprodi: mk prodinya; lainnya: semua
[$inMk, $pMk] = $cak === null ? ['', []] : daftar_in($cak);
$sqlMk = $cak === null ? '' : " AND kode_mk IN ($inMk)";

// Daftar mahasiswa dalam cakupan: seluruh prodi (kaprodi), peserta kelas (dosen), atau semua.
$mhsSyarat = '1 = 1'; $mhsParam = [];
if ($bp !== null) { $mhsSyarat = 'program_studi = ?'; $mhsParam = [$bp]; }
if ($role === 'dosen') { $mhsSyarat = "nim IN (SELECT nim FROM krs WHERE 1 = 1$sqlMk)"; $mhsParam = $pMk; }

$perStatus = ambil_semua("SELECT status, COUNT(*) AS n FROM mahasiswa WHERE $mhsSyarat GROUP BY status", $mhsParam);
$perAngkatan = ambil_semua("SELECT angkatan, COUNT(*) AS n FROM mahasiswa WHERE $mhsSyarat GROUP BY angkatan ORDER BY angkatan DESC", $mhsParam);
$perProdi = $role === 'dosen' ? [] : ambil_semua("SELECT program_studi, COUNT(*) AS n FROM mahasiswa WHERE $mhsSyarat GROUP BY program_studi", $mhsParam);
$krsStat = ambil_semua("SELECT status, COUNT(*) AS n FROM krs WHERE 1 = 1$sqlMk GROUP BY status", $pMk);
$dist = ambil_semua("SELECT kode_mk, huruf_grade, COUNT(*) AS n FROM nilai WHERE 1 = 1$sqlMk GROUP BY kode_mk, huruf_grade ORDER BY kode_mk", $pMk);
$distPer = [];
foreach ($dist as $d) { $distPer[$d['kode_mk']][$d['huruf_grade']] = $d['n']; }
$hadir = ambil_semua("SELECT s.kode_mk, COUNT(DISTINCT s.id) AS sesi, SUM(CASE WHEN p.status = 'Hadir' THEN 1 ELSE 0 END) AS hadir, COUNT(p.id) AS isi
    FROM sesi_presensi s LEFT JOIN presensi p ON p.sesi_id = s.id WHERE 1 = 1" . str_replace('kode_mk IN', 's.kode_mk IN', $sqlMk) . ' GROUP BY s.kode_mk ORDER BY s.kode_mk', $pMk);
$ipk = [];
foreach (ambil_semua("SELECT nim FROM mahasiswa WHERE $mhsSyarat", $mhsParam) as $m) {
    $v = hitung_ipk($m['nim']);
    if ($v > 0) $ipk[] = $v;
}
$namaMk = array_column(ambil_semua('SELECT kode_mk, nama_mk FROM mata_kuliah'), 'nama_mk', 'kode_mk');

$judul = 'Laporan & Statistik Akademik';
include '../includes/header.php';
?>
<style>@media print{.atas,.filter,footer,.tanpa-cetak{display:none}}</style>
<h1>Laporan & Statistik Akademik<?= $bp !== null ? ' - prodi ' . e($bp) : '' ?><?= $role === 'dosen' ? ' - kelas saya' : '' ?></h1>
<p class="tanpa-cetak"><button type="button" class="tombol" onclick="window.print()">Cetak laporan</button></p>
<div class="ringkasan">
    <div class="kartu"><span>Mahasiswa</span><strong><?= array_sum(array_column($perStatus, 'n')) ?></strong></div>
    <div class="kartu"><span>Rata-rata IPK</span><strong><?= $ipk ? number_format(array_sum($ipk) / count($ipk), 2) : '-' ?></strong></div>
    <div class="kartu"><span>Baris KRS</span><strong><?= array_sum(array_column($krsStat, 'n')) ?></strong></div>
    <div class="kartu"><span>Nilai terinput</span><strong><?= array_sum(array_column($dist, 'n')) ?></strong></div>
</div>
<div class="dua-kolom sama-kolom">
    <div class="kartu"><h2>Mahasiswa per status</h2><?php foreach ($perStatus as $r): ?><p><?= lencana($r['status']) ?> <?= $r['n'] ?></p><?php endforeach; ?><?php if (!$perStatus): ?><p class="kosong">Belum ada data.</p><?php endif; ?></div>
    <div class="kartu"><h2>Status KRS</h2><?php foreach ($krsStat as $r): ?><p><?= lencana($r['status']) ?> <?= $r['n'] ?></p><?php endforeach; ?><?php if (!$krsStat): ?><p class="kosong">Belum ada KRS.</p><?php endif; ?></div>
</div>
<div class="dua-kolom sama-kolom" style="margin-top:16px">
    <div class="kartu"><h2>Mahasiswa per angkatan</h2><?php foreach ($perAngkatan as $r): ?><p>Angkatan <?= e($r['angkatan']) ?>: <strong><?= $r['n'] ?></strong></p><?php endforeach; ?><?php if (!$perAngkatan): ?><p class="kosong">Belum ada data.</p><?php endif; ?></div>
    <?php if ($perProdi): ?><div class="kartu"><h2>Mahasiswa per prodi</h2><?php foreach ($perProdi as $r): ?><p><?= e($r['program_studi']) ?>: <strong><?= $r['n'] ?></strong></p><?php endforeach; ?></div><?php endif; ?>
</div>
<div class="kartu" style="margin-top:16px"><h2>Sebaran nilai per mata kuliah</h2>
<?php if (!$distPer): ?><p class="kosong">Belum ada nilai.</p><?php else: ?><div class="gulir"><table><tr><th>Mata kuliah</th><?php foreach (GRADE as $g): ?><th class="angka"><?= $g ?></th><?php endforeach; ?></tr>
<?php foreach ($distPer as $kd => $r): ?><tr><td><?= e($kd) ?> - <?= e($namaMk[$kd] ?? '') ?></td><?php foreach (GRADE as $g): ?><td class="angka"><?= $r[$g] ?? 0 ?></td><?php endforeach; ?></tr><?php endforeach; ?></table></div><?php endif; ?></div>
<div class="kartu"><h2>Kehadiran per mata kuliah</h2>
<?php if (!$hadir): ?><p class="kosong">Belum ada sesi presensi.</p><?php else: ?><div class="gulir"><table><tr><th>Mata kuliah</th><th class="angka">Sesi</th><th class="angka">Presensi terisi</th><th class="angka">% hadir</th></tr>
<?php foreach ($hadir as $h): ?><tr><td><?= e($h['kode_mk']) ?> - <?= e($namaMk[$h['kode_mk']] ?? '') ?></td><td class="angka"><?= $h['sesi'] ?></td><td class="angka"><?= $h['isi'] ?></td><td class="angka"><?= $h['isi'] > 0 ? round($h['hadir'] / $h['isi'] * 100) . '%' : '-' ?></td></tr><?php endforeach; ?></table></div><?php endif; ?></div>
<?php include '../includes/footer.php'; ?>
