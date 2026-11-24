<?php
require_once '../koneksi.php';
$user = wajib_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (isset($_POST['hapus'])) {
        jalankan('DELETE FROM materi_kuliah WHERE id = ?', [(int) $_POST['hapus']]);
        pesan('Materi dihapus.');
        pindah('materi.php');
    }

    $id = (int) post('id');
    $kode = post('kode_mk');
    $judulMateri = post('judul_materi');
    $deskripsi = post('deskripsi');

    if (!ambil_satu('SELECT kode_mk FROM mata_kuliah WHERE kode_mk = ?', [$kode]) || $judulMateri === '') {
        pesan('Pilih mata kuliah dan isi judul materi.', 'gagal');
        pindah('materi.php');
    }

    $file = simpan_upload('file');
    if ($file === false) {
        pesan('File gagal diunggah. Format yang boleh: pdf, doc, docx, ppt, pptx, jpg, png, mp4, zip.', 'gagal');
        pindah('materi.php');
    }

    if ($id) {
        jalankan('UPDATE materi_kuliah SET kode_mk = ?, judul_materi = ?, deskripsi = ? WHERE id = ?',
                 [$kode, $judulMateri, $deskripsi, $id]);
        if ($file) {
            jalankan('UPDATE materi_kuliah SET file_url = ? WHERE id = ?', [$file, $id]);
        }
        pesan('Materi diperbarui.');
    } else {
        jalankan('INSERT INTO materi_kuliah (kode_mk, judul_materi, deskripsi, file_url, tanggal_upload) VALUES (?, ?, ?, ?, ?)',
                 [$kode, $judulMateri, $deskripsi, $file, date('Y-m-d')]);
        pesan('Materi ditambahkan.');
    }
    pindah('materi.php');
}

$edit = get('edit') !== '' ? ambil_satu('SELECT * FROM materi_kuliah WHERE id = ?', [(int) get('edit')]) : null;

$daftar = ambil_semua('SELECT t.*, m.nama_mk FROM materi_kuliah t JOIN mata_kuliah m ON m.kode_mk = t.kode_mk ORDER BY t.id DESC');

$judul = 'Kelola Materi';
include '../includes/header.php';
?>
<h1>Kelola Materi Kuliah</h1>

<div class="kartu">
    <h2><?= $edit ? 'Ubah materi' : 'Tambah materi' ?></h2>
    <form method="post" enctype="multipart/form-data" class="form-isi">
        <input type="hidden" name="id" value="<?= $edit ? $edit['id'] : '' ?>">
        <div>
            <label for="kode_mk">Mata kuliah</label>
            <select id="kode_mk" name="kode_mk" required><?= opsi(daftar_matkul(), $edit['kode_mk'] ?? '') ?></select>
        </div>
        <div>
            <label for="judul_materi">Judul materi</label>
            <input type="text" id="judul_materi" name="judul_materi" value="<?= e($edit['judul_materi'] ?? '') ?>" required>
        </div>
        <div class="penuh">
            <label for="deskripsi">Deskripsi</label>
            <textarea id="deskripsi" name="deskripsi" rows="3"><?= e($edit['deskripsi'] ?? '') ?></textarea>
        </div>
        <div class="penuh">
            <label for="file">File</label>
            <input type="file" id="file" name="file">
            <small>pdf, doc, docx, ppt, pptx, jpg, png, mp4, zip.
                <?= $edit && $edit['file_url'] ? 'Kosongkan jika tidak ingin mengganti file.' : '' ?></small>
        </div>
        <div class="penuh baris-tombol">
            <button type="submit" class="tombol"><?= $edit ? 'Simpan perubahan' : 'Tambah materi' ?></button>
            <?php if ($edit): ?><a href="materi.php" class="tombol abu">Batal</a><?php endif; ?>
        </div>
    </form>
</div>

<div class="kartu">
    <h2>Daftar materi</h2>
    <?php if (!$daftar): ?>
        <p class="kosong">Belum ada materi.</p>
    <?php else: ?>
        <?php foreach ($daftar as $m): ?>
            <div class="item">
                <h3><?= e($m['judul_materi']) ?></h3>
                <p class="keterangan"><?= e($m['nama_mk']) ?> - diunggah <?= tanggal($m['tanggal_upload']) ?></p>
                <?php if ($m['deskripsi']): ?><p><?= nl2br(e($m['deskripsi'])) ?></p><?php endif; ?>
                <div class="aksi">
                    <?php if ($m['file_url']): ?>
                        <a href="../<?= e($m['file_url']) ?>" target="_blank" class="tombol abu kecil">Buka file</a>
                    <?php endif; ?>
                    <a href="?edit=<?= $m['id'] ?>" class="tombol kecil">Ubah</a>
                    <form method="post" onsubmit="return confirm('Hapus materi ini?')">
                        <input type="hidden" name="hapus" value="<?= $m['id'] ?>">
                        <button type="submit" class="tombol merah kecil">Hapus</button>
                    </form>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>
<?php include '../includes/footer.php'; ?>
