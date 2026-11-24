<?php
require_once '../koneksi.php';
$user = wajib_mahasiswa();
$nim = $user['nim'];
$aktif = ambil_satu("SELECT * FROM skripsi WHERE nim = ? AND status <> 'Ditolak' ORDER BY id DESC LIMIT 1", [$nim]);
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (post('aksi') === 'ajukan') {
        if ($aktif) { pesan('Anda sudah memiliki pengajuan skripsi yang berjalan.', 'gagal'); }
        elseif (post('judul') === '') { pesan('Judul wajib diisi.', 'gagal'); }
        else {
            jalankan("INSERT INTO skripsi (nim, judul, abstrak, status, diajukan) VALUES (?, ?, ?, 'Diajukan', ?)", [$nim, post('judul'), kosong_ke_null(post('abstrak')), date('Y-m-d H:i:s')]);
            catat_log('skripsi', 'ajukan judul', post('judul'));
            pesan('Judul skripsi diajukan ke Kaprodi.');
        }
    } elseif (post('aksi') === 'bimbingan') {
        if ($aktif && in_array($aktif['status'], ['Disetujui', 'Layak Sidang'], true) && post('isi') !== '') {
            jalankan('INSERT INTO bimbingan_skripsi (skripsi_id, waktu, isi, oleh, peran) VALUES (?, ?, ?, ?, ?)', [$aktif['id'], date('Y-m-d H:i:s'), post('isi'), $user['nama_lengkap'], 'mahasiswa']);
            pesan('Catatan bimbingan tersimpan.');
        } else { pesan('Catatan bimbingan hanya dapat diisi setelah judul disetujui.', 'gagal'); }
    }
    pindah('skripsi.php');
}
$semua = ambil_semua('SELECT s.*, d.nama AS pembimbing FROM skripsi s LEFT JOIN dosen d ON d.id = s.pembimbing_id WHERE s.nim = ? ORDER BY s.id DESC', [$nim]);
$log = $aktif ? ambil_semua('SELECT * FROM bimbingan_skripsi WHERE skripsi_id = ? ORDER BY waktu', [$aktif['id']]) : [];
$judul = 'Skripsi';
include '../includes/header.php';
?>
<h1>Skripsi / Tugas Akhir</h1>
<?php if (!$aktif): ?>
<div class="kartu"><h2>Ajukan judul</h2>
    <form method="post" class="form-isi"><input type="hidden" name="aksi" value="ajukan">
        <div class="penuh"><label for="judul">Judul skripsi</label><input type="text" id="judul" name="judul" required></div>
        <div class="penuh"><label for="abstrak">Abstrak singkat</label><textarea id="abstrak" name="abstrak" rows="3"></textarea></div>
        <div class="penuh"><button type="submit" class="tombol">Ajukan</button></div></form></div>
<?php endif; ?>
<div class="kartu"><h2>Pengajuan saya</h2>
    <?php if (!$semua): ?><p class="kosong">Belum ada pengajuan.</p><?php else: ?>
    <div class="gulir"><table><tr><th>Judul</th><th>Pembimbing</th><th>Status</th><th>Nilai</th></tr>
    <?php foreach ($semua as $s): ?><tr><td><?= e($s['judul']) ?></td><td><?= e($s['pembimbing'] ?: '-') ?></td><td><?= lencana($s['status']) ?><?php if ($s['catatan_kaprodi']): ?><br><span class="keterangan"><?= e($s['catatan_kaprodi']) ?></span><?php endif; ?></td><td><?= e($s['nilai_huruf'] ?: '-') ?></td></tr><?php endforeach; ?></table></div><?php endif; ?></div>
<?php if ($aktif && in_array($aktif['status'], ['Disetujui', 'Layak Sidang', 'Selesai'], true)): ?>
<div class="kartu"><h2>Catatan bimbingan</h2>
    <?php if (!$log): ?><p class="kosong">Belum ada catatan.</p><?php endif; ?>
    <?php foreach ($log as $l): ?><div class="item"><p class="keterangan"><?= tanggal_jam($l['waktu']) ?> - <?= e($l['oleh']) ?> (<?= e($l['peran']) ?>)</p><p><?= nl2br(e($l['isi'])) ?></p></div><?php endforeach; ?>
    <?php if ($aktif['status'] !== 'Selesai'): ?><form method="post" class="form-isi"><input type="hidden" name="aksi" value="bimbingan"><div class="penuh"><label for="isi">Catat hasil bimbingan / pertanyaan</label><textarea id="isi" name="isi" rows="3" required></textarea></div><div class="penuh"><button type="submit" class="tombol">Simpan</button></div></form><?php endif; ?></div>
<?php endif; ?>
<?php include '../includes/footer.php'; ?>
