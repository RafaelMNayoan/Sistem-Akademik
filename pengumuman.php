<?php
require_once '../koneksi.php';
$user = wajib_portal('konten');
$role = $user['role'];
$bp = prodi_batas();

// Prodi yang boleh dikelola: Kaprodi hanya prodinya sendiri.
function boleh_kelola_pengumuman($baris)
{
    global $bp;
    if ($bp === null) {
        return true;
    }
    return $baris && $baris['prodi'] === $bp;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['hapus'])) {
        if (!bisa('D')) { pesan('Anda tidak berhak menghapus pengumuman.', 'gagal'); pindah('pengumuman.php'); }
        $b = ambil_satu('SELECT * FROM pengumuman WHERE id = ?', [(int) $_POST['hapus']]);
        if ($b) {
            jalankan('DELETE FROM pengumuman WHERE id = ?', [$b['id']]);
            catat_log('konten', 'hapus pengumuman', $b['judul']);
            pesan('Pengumuman dihapus.');
        }
        pindah('pengumuman.php');
    }
    $id = (int) post('id');
    $judulP = post('judul');
    $isi = post('isi');
    $prodiP = $bp !== null ? $bp : kosong_ke_null(post('prodi'));
    if ($judulP === '' || $isi === '' || ($prodiP !== null && !in_array($prodiP, PRODI, true))) {
        pesan('Judul, isi, dan prodi harus diisi dengan benar.', 'gagal');
        pindah('pengumuman.php');
    }
    if ($id) {
        $lama = ambil_satu('SELECT * FROM pengumuman WHERE id = ?', [$id]);
        if (!bisa('U') || !$lama || !boleh_kelola_pengumuman($lama)) {
            pesan('Anda tidak berhak mengubah pengumuman ini.', 'gagal');
            pindah('pengumuman.php');
        }
        jalankan('UPDATE pengumuman SET judul = ?, isi = ?, prodi = ? WHERE id = ?', [$judulP, $isi, $prodiP, $id]);
        catat_log('konten', 'ubah pengumuman', beda($lama, ['judul' => $judulP, 'isi' => $isi, 'prodi' => $prodiP]));
        pesan('Pengumuman diperbarui.');
    } else {
        if (!bisa('C')) { pesan('Anda tidak berhak menambah pengumuman.', 'gagal'); pindah('pengumuman.php'); }
        jalankan('INSERT INTO pengumuman (judul, isi, prodi, penulis, peran_penulis, dibuat) VALUES (?, ?, ?, ?, ?, ?)',
                 [$judulP, $isi, $prodiP, $user['nama'], $role, date('Y-m-d H:i:s')]);
        catat_log('konten', 'tambah pengumuman', $judulP);
        pesan('Pengumuman diterbitkan.');
    }
    pindah('pengumuman.php');
}

$edit = null;
if (get('edit') !== '' && bisa('U')) {
    $edit = ambil_satu('SELECT * FROM pengumuman WHERE id = ?', [(int) get('edit')]);
    if ($edit && !boleh_kelola_pengumuman($edit)) { $edit = null; }
}

// Dosen melihat pengumuman umum dan prodinya; Kaprodi melihat umum dan prodinya.
$prodiLihat = $role === 'dosen' ? (ambil_satu('SELECT prodi FROM dosen WHERE id = ?', [$user['dosen_id'] ?? 0])['prodi'] ?? '') : ($bp ?? '');
if ($role === 'dosen' || $role === 'kaprodi') {
    $daftar = ambil_semua('SELECT * FROM pengumuman WHERE prodi IS NULL OR prodi = ? ORDER BY dibuat DESC, id DESC', [$prodiLihat]);
} else {
    $daftar = ambil_semua('SELECT * FROM pengumuman ORDER BY dibuat DESC, id DESC');
}

$judul = 'Pengumuman';
include '../includes/header.php';
?>
<h1>Pengumuman & Konten Website</h1>
<?php if (bisa('C') || $edit): ?>
<div class="kartu">
    <h2><?= $edit ? 'Ubah pengumuman' : 'Tulis pengumuman' ?></h2>
    <form method="post" class="form-isi">
        <input type="hidden" name="id" value="<?= $edit ? $edit['id'] : '' ?>">
        <div><label for="judul">Judul</label><input type="text" id="judul" name="judul" value="<?= e($edit['judul'] ?? '') ?>" required></div>
        <div><label for="prodi">Berlaku untuk</label>
            <?php if ($bp !== null): ?><input type="text" value="Prodi <?= e($bp) ?>" readonly>
            <?php else: ?><select id="prodi" name="prodi"><option value="">Semua (umum)</option><?= opsi(sama(PRODI), $edit['prodi'] ?? '') ?></select><?php endif; ?></div>
        <div class="penuh"><label for="isi">Isi pengumuman</label><textarea id="isi" name="isi" rows="4" required><?= e($edit['isi'] ?? '') ?></textarea></div>
        <div class="penuh baris-tombol"><button type="submit" class="tombol"><?= $edit ? 'Simpan perubahan' : 'Terbitkan' ?></button>
            <?php if ($edit): ?><a href="pengumuman.php" class="tombol abu">Batal</a><?php endif; ?></div>
    </form>
</div>
<?php endif; ?>
<div class="kartu">
    <h2>Daftar pengumuman</h2>
    <?php if (!$daftar): ?><p class="kosong">Belum ada pengumuman.</p><?php endif; ?>
    <?php foreach ($daftar as $p): ?>
        <div class="item">
            <h3><?= e($p['judul']) ?></h3>
            <p class="keterangan"><?= tanggal_jam($p['dibuat']) ?> - <?= e($p['penulis']) ?> - <?= $p['prodi'] ? 'Prodi ' . e($p['prodi']) : 'Umum' ?></p>
            <p><?= nl2br(e($p['isi'])) ?></p>
            <?php if ((bisa('U') && boleh_kelola_pengumuman($p)) || bisa('D')): ?>
            <div class="aksi">
                <?php if (bisa('U') && boleh_kelola_pengumuman($p)): ?><a href="?edit=<?= $p['id'] ?>" class="tombol kecil">Ubah</a><?php endif; ?>
                <?php if (bisa('D')): ?><form method="post" onsubmit="return confirm('Hapus pengumuman ini?')"><input type="hidden" name="hapus" value="<?= $p['id'] ?>"><button type="submit" class="tombol merah kecil">Hapus</button></form><?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>
</div>
<?php include '../includes/footer.php'; ?>
