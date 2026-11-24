<?php
require_once '../koneksi.php';
$user = wajib_mahasiswa();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $jenis = post('jenis');
    if (!in_array($jenis, JENIS_SURAT_MHS, true) || post('keperluan') === '') {
        pesan('Pilih jenis surat dan isi keperluan.', 'gagal');
    } else {
        jalankan("INSERT INTO surat (jenis, pemohon_tipe, pemohon_ref, pemohon_nama, prodi, keperluan, status, dibuat) VALUES (?, 'mahasiswa', ?, ?, ?, ?, 'Diajukan', ?)",
                 [$jenis, $user['nim'], $user['nama_lengkap'], $user['program_studi'], post('keperluan'), date('Y-m-d H:i:s')]);
        catat_log('surat', 'ajukan surat', $jenis);
        pesan('Pengajuan surat terkirim.');
    }
    pindah('surat.php');
}
$daftar = ambil_semua("SELECT * FROM surat WHERE pemohon_tipe = 'mahasiswa' AND pemohon_ref = ? ORDER BY dibuat DESC, id DESC", [$user['nim']]);
$judul = 'Surat';
include '../includes/header.php';
?>
<h1>Pengajuan Surat</h1>
<div class="kartu"><h2>Ajukan surat</h2>
    <form method="post" class="form-isi">
        <div><label for="jenis">Jenis surat</label><select id="jenis" name="jenis"><?= opsi(sama(JENIS_SURAT_MHS)) ?></select></div>
        <div class="penuh"><label for="keperluan">Keperluan</label><textarea id="keperluan" name="keperluan" rows="2" required></textarea></div>
        <div class="penuh"><button type="submit" class="tombol">Ajukan</button></div>
    </form></div>
<div class="kartu"><h2>Riwayat pengajuan</h2>
    <?php if (!$daftar): ?><p class="kosong">Belum ada pengajuan.</p><?php else: ?>
    <div class="gulir"><table><tr><th>Tanggal</th><th>Jenis</th><th>Keperluan</th><th>Status</th><th></th></tr>
    <?php foreach ($daftar as $s): ?><tr><td><?= tanggal($s['dibuat']) ?></td><td><?= e($s['jenis']) ?></td><td><?= e($s['keperluan']) ?></td><td><?= lencana($s['status']) ?><?php if ($s['catatan']): ?><br><span class="keterangan"><?= e($s['catatan']) ?></span><?php endif; ?></td>
    <td><?php if (in_array($s['status'], ['Disetujui', 'Selesai'], true)): ?><a href="../portal/cetak.php?jenis=surat&amp;id=<?= $s['id'] ?>" target="_blank" class="tombol abu kecil">Cetak</a><?php endif; ?></td></tr><?php endforeach; ?></table></div><?php endif; ?></div>
<?php include '../includes/footer.php'; ?>
