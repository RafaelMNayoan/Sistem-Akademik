<?php
require_once '../koneksi.php';
$user = wajib_portal('materi');
$role = $user['role'];
$kodeSaya = $role === 'dosen' ? mk_dosen() : [];
$cak = cakupan_mk();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['hapus'])) {
        $m = ambil_satu('SELECT * FROM materi_kuliah WHERE id = ?', [(int) $_POST['hapus']]);
        if (!bisa('D') || !$m || !in_array($m['kode_mk'], $kodeSaya, true)) {
            pesan('Anda hanya dapat menghapus materi kelas Anda sendiri.', 'gagal');
        } else {
            jalankan('DELETE FROM materi_kuliah WHERE id = ?', [$m['id']]);
            catat_log('materi', 'hapus materi', $m['kode_mk'] . ' ' . $m['judul_materi']);
            pesan('Materi dihapus.');
        }
        pindah('materi.php');
    }
    $id = (int) post('id');
    $kode = post('kode_mk');
    $judulMateri = post('judul_materi');
    if ($id ? !bisa('U') : !bisa('C')) { pesan('Anda tidak berhak untuk aksi ini.', 'gagal'); pindah('materi.php'); }
    if (!in_array($kode, $kodeSaya, true) || $judulMateri === '') {
        pesan('Pilih kelas yang Anda ampu dan isi judul materi.', 'gagal');
        pindah('materi.php');
    }
    $lama = $id ? ambil_satu('SELECT * FROM materi_kuliah WHERE id = ?', [$id]) : null;
    if ($id && (!$lama || !in_array($lama['kode_mk'], $kodeSaya, true))) { pesan('Materi bukan milik kelas Anda.', 'gagal'); pindah('materi.php'); }
    $file = simpan_upload('file');
    if ($file === false) {
        pesan('File gagal diunggah. Format yang boleh: pdf, doc, docx, ppt, pptx, jpg, png, mp4, zip.', 'gagal');
        pindah('materi.php');
    }
    if ($id) {
        jalankan('UPDATE materi_kuliah SET kode_mk = ?, judul_materi = ?, deskripsi = ? WHERE id = ?', [$kode, $judulMateri, post('deskripsi'), $id]);
        if ($file) { jalankan('UPDATE materi_kuliah SET file_url = ? WHERE id = ?', [$file, $id]); }
        catat_log('materi', 'ubah materi', "id $id $kode $judulMateri");
        pesan('Materi diperbarui.');
    } else {
        jalankan('INSERT INTO materi_kuliah (kode_mk, judul_materi, deskripsi, file_url, tanggal_upload) VALUES (?, ?, ?, ?, ?)', [$kode, $judulMateri, post('deskripsi'), $file, date('Y-m-d')]);
        catat_log('materi', 'tambah materi', "$kode $judulMateri");
        pesan('Materi ditambahkan.');
    }
    pindah('materi.php');
}

$edit = get('edit') !== '' && bisa('U') ? ambil_satu('SELECT * FROM materi_kuliah WHERE id = ?', [(int) get('edit')]) : null;
if ($edit && !in_array($edit['kode_mk'], $kodeSaya, true)) { $edit = null; }
$syarat = '1 = 1'; $param = [];
if ($cak !== null) { [$in, $pi] = daftar_in($cak); $syarat = "t.kode_mk IN ($in)"; $param = $pi; }
$daftar = ambil_semua("SELECT t.*, m.nama_mk FROM materi_kuliah t JOIN mata_kuliah m ON m.kode_mk = t.kode_mk WHERE $syarat ORDER BY t.id DESC", $param);
$pilihanMk = [];
foreach ($kodeSaya as $kd) { $mk = ambil_satu('SELECT nama_mk FROM mata_kuliah WHERE kode_mk = ?', [$kd]); $pilihanMk[$kd] = $kd . ' - ' . ($mk['nama_mk'] ?? ''); }

$judul = 'Materi Kuliah';
include '../includes/header.php';
?>
<h1>Materi Kuliah</h1>
<?php if ($role === 'dosen' && !$pilihanMk): ?><div class="pesan gagal">Anda belum memiliki kelas yang disetujui, sehingga belum bisa mengunggah materi.</div><?php endif; ?>
<?php if ((bisa('C') && $pilihanMk) || $edit): ?>
<div class="kartu">
    <h2><?= $edit ? 'Ubah materi' : 'Tambah materi' ?></h2>
    <form method="post" enctype="multipart/form-data" class="form-isi">
        <input type="hidden" name="id" value="<?= $edit ? $edit['id'] : '' ?>">
        <div><label for="kode_mk">Mata kuliah (kelas saya)</label><select id="kode_mk" name="kode_mk" required><?= opsi($pilihanMk, $edit['kode_mk'] ?? '') ?></select></div>
        <div><label for="judul_materi">Judul materi</label><input type="text" id="judul_materi" name="judul_materi" value="<?= e($edit['judul_materi'] ?? '') ?>" required></div>
        <div class="penuh"><label for="deskripsi">Deskripsi</label><textarea id="deskripsi" name="deskripsi" rows="3"><?= e($edit['deskripsi'] ?? '') ?></textarea></div>
        <div class="penuh"><label for="file">File</label><input type="file" id="file" name="file"><small>pdf, doc, docx, ppt, pptx, jpg, png, mp4, zip. <?= $edit && $edit['file_url'] ? 'Kosongkan jika tidak ingin mengganti file.' : '' ?></small></div>
        <div class="penuh baris-tombol"><button type="submit" class="tombol"><?= $edit ? 'Simpan perubahan' : 'Tambah materi' ?></button><?php if ($edit): ?><a href="materi.php" class="tombol abu">Batal</a><?php endif; ?></div>
    </form>
</div>
<?php endif; ?>
<div class="kartu">
    <h2>Daftar materi</h2>
    <?php if (!$daftar): ?><p class="kosong">Belum ada materi.</p><?php endif; ?>
    <?php foreach ($daftar as $m): $milik = in_array($m['kode_mk'], $kodeSaya, true); ?>
    <div class="item"><h3><?= e($m['judul_materi']) ?></h3>
        <p class="keterangan"><?= e($m['nama_mk']) ?> - diunggah <?= tanggal($m['tanggal_upload']) ?></p>
        <?php if ($m['deskripsi']): ?><p><?= nl2br(e($m['deskripsi'])) ?></p><?php endif; ?>
        <div class="aksi">
            <?php if ($m['file_url']): ?><a href="../<?= e($m['file_url']) ?>" target="_blank" class="tombol abu kecil">Buka file</a><?php endif; ?>
            <?php if (bisa('U') && $milik): ?><a href="?edit=<?= $m['id'] ?>" class="tombol kecil">Ubah</a><?php endif; ?>
            <?php if (bisa('D') && $milik): ?><form method="post" onsubmit="return confirm('Hapus materi ini?')"><input type="hidden" name="hapus" value="<?= $m['id'] ?>"><button type="submit" class="tombol merah kecil">Hapus</button></form><?php endif; ?>
        </div></div>
    <?php endforeach; ?>
</div>
<?php include '../includes/footer.php'; ?>
