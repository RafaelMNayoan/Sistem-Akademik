<?php
require_once '../koneksi.php';
$user = wajib_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (isset($_POST['hapus'])) {
        jalankan('DELETE FROM krs WHERE id = ?', [(int) $_POST['hapus']]);
        pesan('Data KRS dihapus.');
        pindah('krs.php');
    }

    $id = (int) post('id');
    $nim = post('nim');
    $kode = post('kode_mk');
    $semester = (int) post('semester');

    $adaMhs = ambil_satu('SELECT nim FROM mahasiswa WHERE nim = ?', [$nim]);
    $adaMk = ambil_satu('SELECT kode_mk FROM mata_kuliah WHERE kode_mk = ?', [$kode]);

    if (!$adaMhs || !$adaMk || $semester < 1 || $semester > 14) {
        pesan('Pilih mahasiswa, mata kuliah, dan semester (1-14) dengan benar.', 'gagal');
        pindah('krs.php');
    }

    try {
        if ($id) {
            jalankan('UPDATE krs SET nim = ?, semester = ?, kode_mk = ? WHERE id = ?', [$nim, $semester, $kode, $id]);
            pesan('KRS diperbarui.');
        } else {
            jalankan('INSERT INTO krs (nim, semester, kode_mk) VALUES (?, ?, ?)', [$nim, $semester, $kode]);
            pesan('Mata kuliah ditambahkan ke KRS mahasiswa.');
        }
    } catch (PDOException $ex) {
        pesan('Mata kuliah itu sudah ada di KRS mahasiswa pada semester tersebut.', 'gagal');
    }
    pindah('krs.php');
}

$edit = get('edit') !== '' ? ambil_satu('SELECT * FROM krs WHERE id = ?', [(int) get('edit')]) : null;

$filter = get('nim');
$daftar = ambil_semua("SELECT k.id, k.nim, k.semester, mh.nama_lengkap, m.kode_mk, m.nama_mk, m.sks
        FROM krs k
        JOIN mahasiswa mh ON mh.nim = k.nim
        JOIN mata_kuliah m ON m.kode_mk = k.kode_mk
        WHERE (? = '' OR k.nim = ?)
        ORDER BY k.nim, k.semester, m.kode_mk", [$filter, $filter]);
$totalSks = array_sum(array_column($daftar, 'sks'));

$judul = 'Kelola KRS';
include '../includes/header.php';
?>
<h1>Kelola KRS</h1>

<div class="kartu">
    <h2><?= $edit ? 'Ubah data KRS' : 'Tambah mata kuliah ke KRS mahasiswa' ?></h2>
    <form method="post" class="form-isi">
        <input type="hidden" name="id" value="<?= $edit ? $edit['id'] : '' ?>">
        <div>
            <label for="nim">Mahasiswa</label>
            <select id="nim" name="nim" required><?= opsi(daftar_mahasiswa(), $edit['nim'] ?? '') ?></select>
        </div>
        <div>
            <label for="kode_mk">Mata kuliah</label>
            <select id="kode_mk" name="kode_mk" required><?= opsi(daftar_matkul(), $edit['kode_mk'] ?? '') ?></select>
        </div>
        <div>
            <label for="semester">Semester</label>
            <input type="number" id="semester" name="semester" min="1" max="14" value="<?= e($edit['semester'] ?? 1) ?>" required>
        </div>
        <div class="penuh baris-tombol">
            <button type="submit" class="tombol"><?= $edit ? 'Simpan perubahan' : 'Tambah ke KRS' ?></button>
            <?php if ($edit): ?><a href="krs.php" class="tombol abu">Batal</a><?php endif; ?>
        </div>
    </form>
</div>

<div class="kartu">
    <h2>Data KRS</h2>
    <form method="get" class="filter">
        <div>
            <label for="saring">Saring mahasiswa</label>
            <select id="saring" name="nim"><option value="">Semua mahasiswa</option><?= opsi(daftar_mahasiswa(), $filter) ?></select>
        </div>
        <button type="submit" class="tombol abu">Saring</button>
    </form>

    <?php if (!$daftar): ?>
        <p class="kosong">Belum ada data KRS.</p>
    <?php else: ?>
        <div class="gulir">
            <table>
                <tr><th>Mahasiswa</th><th>Sem</th><th>Kode</th><th>Mata kuliah</th><th class="angka">SKS</th><th></th></tr>
                <?php foreach ($daftar as $k): ?>
                    <tr>
                        <td><?= e($k['nim']) ?> - <?= e($k['nama_lengkap']) ?></td>
                        <td><?= e($k['semester']) ?></td>
                        <td><?= e($k['kode_mk']) ?></td>
                        <td><?= e($k['nama_mk']) ?></td>
                        <td class="angka"><?= $k['sks'] ?></td>
                        <td>
                            <div class="aksi">
                                <a href="?edit=<?= $k['id'] ?>" class="tombol kecil">Ubah</a>
                                <form method="post" onsubmit="return confirm('Hapus mata kuliah ini dari KRS?')">
                                    <input type="hidden" name="hapus" value="<?= $k['id'] ?>">
                                    <button type="submit" class="tombol merah kecil">Hapus</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </table>
        </div>
        <?php if ($filter !== ''): ?><p><strong>Total tampil: <?= $totalSks ?> SKS</strong></p><?php endif; ?>
    <?php endif; ?>
</div>
<?php include '../includes/footer.php'; ?>
