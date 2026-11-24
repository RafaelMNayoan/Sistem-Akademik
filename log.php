<?php
require_once '../koneksi.php';
$jenis = get('jenis') === 'keuangan' ? 'keuangan' : 'umum';
$user = wajib_portal($jenis === 'keuangan' ? 'log_keuangan' : 'log_aktivitas');
$fModul = get('modul');
$fTgl = get('tanggal');
$syarat = '(? = \'\' OR modul = ?) AND (? = \'\' OR waktu >= ?) AND (? = \'\' OR waktu < ?)';
$awal = $fTgl !== '' && strtotime($fTgl) ? $fTgl . ' 00:00:00' : '';
$akhir = $awal !== '' ? date('Y-m-d H:i:s', strtotime($fTgl . ' +1 day')) : '';
$param = [$fModul, $fModul, $awal, $awal, $akhir, $akhir];
if ($jenis === 'keuangan') { $syarat .= " AND jenis = 'keuangan'"; }
$daftar = ambil_semua("SELECT * FROM log_aktivitas WHERE $syarat ORDER BY id DESC LIMIT 300", $param);
$modulOps = array_column(ambil_semua('SELECT DISTINCT modul FROM log_aktivitas ORDER BY modul'), 'modul', 'modul');

$judul = $jenis === 'keuangan' ? 'Log Transaksi Keuangan' : 'Log Aktivitas';
include '../includes/header.php';
?>
<h1><?= e($judul) ?></h1>
<div class="kartu"><form method="get" class="filter"><input type="hidden" name="jenis" value="<?= e($jenis) ?>">
    <div><label for="modul">Modul</label><select id="modul" name="modul"><option value="">Semua</option><?= opsi($modulOps, $fModul) ?></select></div>
    <div><label for="tanggal">Tanggal</label><input type="date" id="tanggal" name="tanggal" value="<?= e($fTgl) ?>"></div>
    <button type="submit" class="tombol abu">Saring</button></form></div>
<div class="kartu"><?php if (!$daftar): ?><p class="kosong">Belum ada catatan.</p><?php else: ?>
<div class="gulir"><table><tr><th>Waktu</th><th>Pengguna</th><th>Modul</th><th>Aksi</th><th>Detail (sebelum -&gt; sesudah)</th></tr>
<?php foreach ($daftar as $l): ?><tr><td><?= tanggal_jam($l['waktu']) ?></td><td><?= e($l['pengguna']) ?><br><span class="keterangan"><?= e($l['peran']) ?></span></td><td><?= e($l['modul']) ?></td><td><?= e($l['aksi']) ?></td><td><?= e($l['detail']) ?></td></tr><?php endforeach; ?></table></div><?php endif; ?>
<p class="keterangan">Menampilkan maksimal 300 catatan terbaru. Catatan bersifat tetap dan tidak dapat diubah atau dihapus dari aplikasi.</p></div>
<?php include '../includes/footer.php'; ?>
