<?php
require_once '../koneksi.php';
$user = wajib_portal('laporan_keuangan');
$role = $user['role'];
$bp = prodi_batas();
$rinci = $role !== 'kaprodi'; // Kaprodi hanya ringkasan prodi
perbarui_keterlambatan();

$fProdi = $bp !== null ? $bp : get('prodi');
$fAngkatan = get('angkatan');
$bulan = get('bulan') !== '' ? get('bulan') : date('Y-m');
if (!preg_match('/^[0-9]{4}-[0-9]{2}$/', $bulan)) { $bulan = date('Y-m'); }

$syarat = "t.status <> 'Dibatalkan' AND (? = '' OR mh.program_studi = ?) AND (? = '' OR mh.angkatan = ?)";
$param = [$fProdi, $fProdi, $fAngkatan, $fAngkatan];

function ringkas_per($kolom, $syarat, $param)
{
    $baris = ambil_semua("SELECT $kolom AS kunci, COUNT(*) AS jumlah, COALESCE(SUM(t.total_bayar), 0) AS total,
        COALESCE(SUM((SELECT COALESCE(SUM(p.jumlah), 0) FROM pembayaran p WHERE p.tagihan_id = t.id AND p.status = 'Terverifikasi')), 0) AS bayar
        FROM tagihan t JOIN mahasiswa mh ON mh.nim = t.nim WHERE $syarat GROUP BY $kolom ORDER BY $kolom", $param);
    return $baris;
}
$perProdi = ringkas_per('mh.program_studi', $syarat, $param);
$perAngkatan = ringkas_per('mh.angkatan', $syarat, $param);
$jumlah = array_sum(array_column($perProdi, 'jumlah'));
$total = array_sum(array_column($perProdi, 'total'));
$bayar = array_sum(array_column($perProdi, 'bayar'));

$harian = $tunggakan = [];
if ($rinci) {
    $harian = ambil_semua("SELECT p.tanggal, COUNT(*) AS n, SUM(p.jumlah) AS total FROM pembayaran p JOIN tagihan t ON t.id = p.tagihan_id JOIN mahasiswa mh ON mh.nim = t.nim
        WHERE p.status = 'Terverifikasi' AND p.tanggal >= ? AND p.tanggal <= ? AND (? = '' OR mh.program_studi = ?) AND (? = '' OR mh.angkatan = ?) GROUP BY p.tanggal ORDER BY p.tanggal",
        array_merge([$bulan . '-01', $bulan . '-31'], [$fProdi, $fProdi, $fAngkatan, $fAngkatan]));
    $tunggakan = ambil_semua("SELECT mh.nim, mh.nama_lengkap, mh.program_studi, COUNT(*) AS n, SUM(t.total_bayar - COALESCE((SELECT SUM(p.jumlah) FROM pembayaran p WHERE p.tagihan_id = t.id AND p.status = 'Terverifikasi'), 0)) AS sisa
        FROM tagihan t JOIN mahasiswa mh ON mh.nim = t.nim WHERE t.status IN ('Belum Dibayar', 'Sebagian', 'Terlambat') AND (? = '' OR mh.program_studi = ?) AND (? = '' OR mh.angkatan = ?)
        GROUP BY mh.nim, mh.nama_lengkap, mh.program_studi ORDER BY sisa DESC", [$fProdi, $fProdi, $fAngkatan, $fAngkatan]);
}

$judul = 'Laporan Keuangan';
include '../includes/header.php';
?>
<style>@media print{.atas,.filter,footer,.tanpa-cetak{display:none}}</style>
<h1>Laporan Keuangan<?= $bp !== null ? ' (ringkasan prodi ' . e($bp) . ')' : '' ?></h1>
<div class="kartu"><form method="get" class="filter">
    <?php if ($bp === null): ?><div><label for="prodi">Program studi</label><select id="prodi" name="prodi"><option value="">Semua</option><?= opsi(sama(PRODI), $fProdi) ?></select></div><?php endif; ?>
    <div><label for="angkatan">Angkatan</label><input type="number" id="angkatan" name="angkatan" min="2000" max="2100" value="<?= e($fAngkatan) ?>"></div>
    <?php if ($rinci): ?><div><label for="bulan">Bulan (pembayaran)</label><input type="month" id="bulan" name="bulan" value="<?= e($bulan) ?>"></div><?php endif; ?>
    <button type="submit" class="tombol abu">Terapkan</button><button type="button" class="tombol" onclick="window.print()">Cetak laporan</button></form></div>
<div class="ringkasan">
    <div class="kartu"><span>Jumlah tagihan</span><strong><?= $jumlah ?></strong></div>
    <div class="kartu"><span>Total tagihan</span><strong><?= rupiah($total) ?></strong></div>
    <div class="kartu"><span>Sudah dibayar (terverifikasi)</span><strong><?= rupiah($bayar) ?></strong></div>
    <div class="kartu"><span>Sisa / tunggakan</span><strong><?= rupiah(max(0, $total - $bayar)) ?></strong></div>
</div>
<div class="dua-kolom sama-kolom">
<div class="kartu"><h2>Per program studi</h2><div class="gulir"><table><tr><th>Prodi</th><th class="angka">Tagihan</th><th class="angka">Total</th><th class="angka">Dibayar</th></tr>
<?php foreach ($perProdi as $r): ?><tr><td><?= e($r['kunci']) ?></td><td class="angka"><?= $r['jumlah'] ?></td><td class="angka"><?= rupiah($r['total']) ?></td><td class="angka"><?= rupiah($r['bayar']) ?></td></tr><?php endforeach; ?></table></div><?php if (!$perProdi): ?><p class="kosong">Belum ada data.</p><?php endif; ?></div>
<div class="kartu"><h2>Per angkatan</h2><div class="gulir"><table><tr><th>Angkatan</th><th class="angka">Tagihan</th><th class="angka">Total</th><th class="angka">Dibayar</th></tr>
<?php foreach ($perAngkatan as $r): ?><tr><td><?= e($r['kunci']) ?></td><td class="angka"><?= $r['jumlah'] ?></td><td class="angka"><?= rupiah($r['total']) ?></td><td class="angka"><?= rupiah($r['bayar']) ?></td></tr><?php endforeach; ?></table></div><?php if (!$perAngkatan): ?><p class="kosong">Belum ada data.</p><?php endif; ?></div>
</div>
<?php if ($rinci): ?>
<div class="kartu" style="margin-top:16px"><h2>Penerimaan harian - <?= e($bulan) ?></h2>
<?php if (!$harian): ?><p class="kosong">Tidak ada pembayaran terverifikasi pada bulan ini.</p><?php else: ?><div class="gulir"><table><tr><th>Tanggal</th><th class="angka">Transaksi</th><th class="angka">Jumlah</th></tr>
<?php foreach ($harian as $h): ?><tr><td><?= tanggal($h['tanggal']) ?></td><td class="angka"><?= $h['n'] ?></td><td class="angka"><?= rupiah($h['total']) ?></td></tr><?php endforeach; ?>
<tr><th>Total bulan</th><th class="angka"><?= array_sum(array_column($harian, 'n')) ?></th><th class="angka"><?= rupiah(array_sum(array_column($harian, 'total'))) ?></th></tr></table></div><?php endif; ?></div>
<div class="kartu"><h2>Rekap tunggakan per mahasiswa</h2>
<?php if (!$tunggakan): ?><p class="kosong">Tidak ada tunggakan.</p><?php else: ?><div class="gulir"><table><tr><th>Mahasiswa</th><th>Prodi</th><th class="angka">Tagihan terbuka</th><th class="angka">Sisa</th></tr>
<?php foreach ($tunggakan as $t): ?><tr><td><?= e($t['nim']) ?> - <?= e($t['nama_lengkap']) ?></td><td><?= e($t['program_studi']) ?></td><td class="angka"><?= $t['n'] ?></td><td class="angka"><?= rupiah($t['sisa']) ?></td></tr><?php endforeach; ?></table></div><?php endif; ?></div>
<?php endif; ?>
<?php include '../includes/footer.php'; ?>
